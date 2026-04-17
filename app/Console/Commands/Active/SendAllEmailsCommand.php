<?php

namespace App\Console\Commands\Active;

use App\Http\Repository\ConfigFinanceNotificationRepository;
use App\Models\Client;
use App\Models\EmailSetting;
use App\Models\Reminder;
use App\Services\EmailConfigService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendAllEmailsCommand extends Command
{
    protected $signature = 'app:send-all-emails-command';
    protected $description = 'Envía automáticamente todos los tipos de emails pendientes en horarios permitidos';

    // Mapeo de tipos de email a sus modelos y configuraciones
    protected $emailTypes = [
        'proforma_invoice' => [
            'model' => \App\Models\ProformaInvoiceEmail::class,
            'config_key' => 'proforma_invoice',
            'needs_config' => true
        ],
        'payment' => [
            'model' => \App\Models\PaymentEmail::class,
            'config_key' => 'payment',
            'needs_config' => true
        ],
        'invoice' => [
            'model' => \App\Models\InvoiceEmail::class,
            'config_key' => 'invoice',
            'needs_config' => true
        ],
        'reminder' => [
            'model' => Reminder::class,
            'needs_config' => false,
            'default_hours' => '09:00,12:00,15:00' // Horario por defecto para recordatorios
        ]
    ];

    public function handle()
    {
        $emailSetting = EmailSetting::first();
        $limitPerHour = $emailSetting->limit_per_hour;
        $unlimitedMode = $limitPerHour === 0;

        $totalSent = 0;

        foreach ($this->emailTypes as $type => $settings) {
            $this->info("\nProcesando emails de tipo: {$type}");

            // Obtener configuración solo si es necesario
            if ($settings['needs_config']) {
                $configFinanceNotification = (new ConfigFinanceNotificationRepository())
                    ->getNotificationType($settings['config_key']);

                if (!$configFinanceNotification->auto_send_notifications) {
                    $this->info("El envío automático para {$type} está desactivado.");
                    continue;
                }
                $allowedHours = $this->parseAllowedHours($configFinanceNotification->notification_hours);
            } else {
                // Usar configuración por defecto para recordatorios
                $allowedHours = $this->parseAllowedHours($settings['default_hours']);
            }

            // Validar horarios permitidos
            if (!$this->isCurrentTimeAllowed($allowedHours)) {
                $this->info("Fuera del horario permitido para {$type}.");
                continue;
            }

            $modelClass = $settings['model'];

            if (!$unlimitedMode) {
                $sentLastHour = $modelClass::where('status', 'sent')
                    ->where('updated_at', '>=', Carbon::now()->subHour())
                    ->count();

                $remainingSlots = max(0, $limitPerHour - $sentLastHour);

                if ($remainingSlots <= 0) {
                    $this->info("Límite por hora alcanzado para {$type} ({$limitPerHour}).");
                    continue;
                }
            }

            // Construir consulta según tipo de email
            $query = $this->buildQueryForType($modelClass);

            if (!$unlimitedMode) {
                $query->limit($remainingSlots);
            }

            $emailsToSend = $query->get();
            $typeSentCount = 0;

            foreach ($emailsToSend as $email) {
                try {
                    $emailConfigService = new EmailConfigService();
                    $emailConfigService->sendEmail($type, $email);
                    $this->updateEmailStatus($type, $email);

                    $typeSentCount++;
                    $totalSent++;

                    $message = "Email {$type} enviado a {$email->recipient_email}";
                    $message .= $unlimitedMode ? "" : " ({$typeSentCount}/{$remainingSlots})";
                    $this->info($message);
                } catch (\Exception $e) {
                    $this->handleEmailError($type, $email, $e);
                }
            }

            $this->info("Total {$type} enviados: {$typeSentCount}");
        }

        $this->info("\nResumen global:");
        $this->info("Total de emails enviados: {$totalSent}");
        $this->info("Límite por hora: " . ($unlimitedMode ? "Ilimitado" : $limitPerHour));
    }

    protected function buildQueryForType(string $modelClass)
    {
        return $modelClass::where('via', 'email')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc');
    }

    protected function getClientForEmail($email)
    {
        return Client::where('id', $email->client_id)->first();
    }

    protected function getEmailProps(string $type, $email): array
    {
        $props = [
            'subject' => $email->subject,
            'body' => $email->html,
            'to' => $email->recipient_email,
        ];

        if ($type === 'reminder') {
            $props['cc'] = $email->cc_email ?? null;
        } else {
            $props['cc'] = $email->email_bcc ?? null;
        }

        return $props;
    }

    protected function updateEmailStatus(string $type, $email)
    {
        $updateData = [
            'status' => 'sent',
            'sent_at' => now()
        ];
        $email->update($updateData);
    }

    protected function handleEmailError(string $type, $email, \Exception $e)
    {
        $this->error("Error enviando email {$type}: " . $e->getMessage());

        $updateData = [
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'email_if_error' => $email->recipient_email
        ];

        $email->update($updateData);
    }

    protected function parseAllowedHours(string $hoursString): array
    {
        return array_filter(array_map('trim', explode(',', $hoursString)));
    }

    protected function isCurrentTimeAllowed(array $allowedHours): bool
    {
        if (empty($allowedHours)) return true; // Si no hay horarios definidos, se permite siempre

        $currentTime = Carbon::now()->format('H:i');

        foreach ($allowedHours as $hour) {
            if (
                $currentTime === $hour ||
                (Carbon::now()->between(
                    Carbon::createFromFormat('H:i', $hour),
                    Carbon::createFromFormat('H:i', $hour)->addHour()
                ))
            ) {
                return true;
            }
        }

        return false;
    }
}

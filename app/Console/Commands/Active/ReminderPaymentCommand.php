<?php

namespace App\Console\Commands\Active;

use App\Http\Repository\DocumentTemplateRepository;
use App\Models\BillingReminder;
use App\Models\Client;
use App\Models\Reminder;
use App\Services\ClientService\ContractClientService;
use App\Services\DocumentTemplateService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReminderPaymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reminder-payment-command
                            {--force : Forzar la ejecución del comando, ignorando la verificación diaria}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Se obtendran los clientes que su fecha de pago esta proxima a vencerse segun la configuracion de recordatorios de
    pago 1, 2 y 3 desde la base de datos y se almacenaran en la tabla reminders de la base de datos.
    Para luego con otro comando se envian los recordatorios de pago a los clientes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Verificar si ya se ejecutó el comando hoy, a menos que se fuerce la ejecución
        if (!$this->option('force')) {
            $today = Carbon::now()->format('Y-m-d');
            $existingReminders = Reminder::whereDate('created_at', $today)->exists();

            if ($existingReminders) {
                $this->info('El comando ya se ejecutó hoy. Usa la opción --force para forzar la ejecución.');
                return;
            }
        }

        $billingReminder = BillingReminder::first();
        $this->reminderPayment($billingReminder, 1);
        $this->reminderPayment($billingReminder, 2);
        $this->reminderPayment($billingReminder, 3);
    }

    public function reminderPayment($billingReminder, $reminderNumber)
    {
        $templateId = $billingReminder->{"reminder_{$reminderNumber}_email_template"};
        $documentTemplateRepository = new DocumentTemplateRepository();
        $htmlTemplate = $documentTemplateRepository->getHtmlById($templateId);

        if (!$htmlTemplate) {
            $this->error("No se encontró la plantilla de email para el recordatorio de pago {$reminderNumber}.");
            return;
        }

        $diasAntesDeVencimiento = $billingReminder->{"reminder_{$reminderNumber}_days"};
        $horaDeEnvio = $billingReminder->send_time;
        $data = [
            'type' => "Recordatorio-Pago-{$reminderNumber}",
            'via' => $billingReminder->message_type,
            'subject' => $billingReminder->{"reminder_{$reminderNumber}_subject"},
        ];

        $fechaVencimiento = now()->addDays($diasAntesDeVencimiento)->format('Y-m-d');

        // Obtiene los clientes cuya fecha de corte es igual a la fecha de vencimiento calculada
        $clients = Client::whereHas('client_main_information')->whereDate('fecha_corte', '=', $fechaVencimiento)->get();

        // Verifica si hay clientes para notificar
        if ($clients->isEmpty()) {
            $this->info("No hay clientes por notificar hoy para el recordatorio de pago {$reminderNumber}.");
            return;
        }

        foreach ($clients as $client) {
            $dataClient = (new ContractClientService())->getDataClient($client);
            $documentTemplateService = new DocumentTemplateService();
            $validation = $documentTemplateService->validateAndReplaceTemplate($htmlTemplate, $dataClient);
            if ($validation['status'] === 'fail') {
                $this->error("Error en la validación de la plantilla para el recordatorio de pago {$reminderNumber}.");
                // Guardar el error en la base de datos
                Reminder::create([
                    'client_id' => $client->id,
                    'type' => $data['type'],
                    'via' => $data['via'],
                    'sent_at' => null,
                    'error_message' => "Error al correr el comando de recordatorio de pago {$reminderNumber}, error en la validacion de la plantilla",
                    'email_if_error' => null,
                    'recipient_email' => $client->client_main_information->email,
                    'cc_email' => $billingReminder->cc_email,
                    'recipient_phone' => null,
                    'subject' => $data['subject'],
                    'html' => $validation['html']
                ]);
            } else {
                $html = $validation['html'];
                Reminder::create([
                    'client_id' => $client->id,
                    'type' => $data['type'],
                    'via' => $data['via'],
                    'sent_at' => null,
                    'error_message' => null,
                    'email_if_error' => null,
                    'recipient_email' => $client->client_main_information->email,
                    'cc_email' => "inving9378@hotmail.com,cr584136@gmail.com",
                    'recipient_phone' => null,
                    'subject' => $data['subject'],
                    'html' => $html
                ]);
            }
        }
    }
}

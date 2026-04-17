<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\DocumentTemplateRepository;
use App\Models\BillingReminder;
use App\Models\Client;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\StandardNotification;
use App\Services\ClientService\ContractClientService;
use App\Services\DocumentTemplateService;
use Illuminate\Console\Command;

class ExampleBillingReminderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:example-billing-reminder-command
                            {--force : Forzar la ejecución del comando, ignorando la verificación diaria}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia un recordatorio de pago de prueba a un cliente';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando ejemplo de crear recordatorio de pago...');

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
        $clients = Client::whereHas('client_main_information')->where('id', '=', 1437)->get();

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
                    'recipient_email' => 'cr584136@gmail.com',
                    'cc_email' => "",
                    'recipient_phone' => null,
                    'subject' => $data['subject'],
                    'html' => $htmlTemplate
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
                    'recipient_email' => 'cr584136@gmail.com',
                    'cc_email' => "",
                    'recipient_phone' => null,
                    'subject' => $data['subject'],
                    'html' => $html
                ]);
            }
        }
    }
}

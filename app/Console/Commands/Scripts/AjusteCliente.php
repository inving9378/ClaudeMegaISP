<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\ClientAdditionalInformationRepository;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Http\Repository\ClientCustomServiceRepository;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientInvoiceRepository;
use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientVozServiceRepository;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Repository\PaymentRepository;
use App\Http\Repository\TransactionRepository;
use App\Models\Balance;
use App\Models\BillingConfiguration;
use App\Models\Client;
use App\Models\DocumentClient;
use App\Models\RemindersConfiguration;
use App\Models\User;
use App\Services\LogService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AjusteCliente extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ajuste-cliente {id_actual : ID actual del cliente} {id_nuevo : Nuevo ID que tendrá el cliente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cambia el ID de un cliente y todas sus relaciones';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        return Model::withoutEvents(function () {
            return DB::transaction(function () {
                try {
                    DB::statement('SET FOREIGN_KEY_CHECKS=0');
                    $idActual = $this->argument('id_actual');
                    $idNew = $this->argument('id_nuevo');


                    //Informacion
                    $clientMainInformationRepository = new ClientMainInformationRepository();
                    $clientMainInformation = $clientMainInformationRepository->getClientMainInformationByClientIdGet($idActual);
                    $this->updateId($clientMainInformation, $idNew, 'client_id');

                    $clientAdditionalInformationRepository = new ClientAdditionalInformationRepository();
                    $clientAdditionalInformation = $clientAdditionalInformationRepository->getClientAdditionalInformationByClientId($idActual);
                    $this->updateId($clientAdditionalInformation, $idNew, 'client_id');

                    //billingCOnfiguration
                    $billingConfiguration = BillingConfiguration::where('client_id', $idActual)->get();
                    $this->updateId($billingConfiguration, $idNew, 'client_id');
                    //balances
                    $balances = Balance::where('balanceable_id', $idActual)->get();
                    $this->updateId($balances, $idNew, 'balanceable_id');

                    //Servicios
                    $internetServiceRepository = new ClientInternetServiceRepository();
                    $serviciosDeInternet = $internetServiceRepository->getServiceFilterByClientId($idActual);
                    $this->updateId($serviciosDeInternet, $idNew, 'client_id');

                    $vosServiceRepository = new ClientVozServiceRepository();
                    $serviciosDeVoz = $vosServiceRepository->getServiceFilterByClientId($idActual);
                    $this->updateId($serviciosDeVoz, $idNew, 'client_id');

                    $customServiceRepository = new ClientCustomServiceRepository();
                    $serviciosCustom = $customServiceRepository->getServiceFilterByClientId($idActual);
                    $this->updateId($serviciosCustom, $idNew, 'client_id');

                    $bundleServiceRepository = new ClientBundleServiceRepository();
                    $serviciosBundles = $bundleServiceRepository->getServicesFilterByClientId($idActual);
                    $this->updateId($serviciosBundles, $idNew, 'client_id');

                    //Pagos
                    $paymentRepository = new PaymentRepository();
                    $payments = $paymentRepository->getPaymentsByClientId($idActual);
                    $this->updateId($payments, $idNew, 'paymentable_id');

                    //Facturas
                    $invoiceRepository = new ClientInvoiceRepository();
                    $invoices = $invoiceRepository->getInvoicesByClientId($idActual);
                    $this->updateId($invoices, $idNew, 'client_id');

                    //Transacciones
                    $transactionsRepository = new TransactionRepository();
                    $transactions = $transactionsRepository->getTransactionsByClientId($idActual);
                    $this->updateId($transactions, $idNew, 'client_id');

                    //Usuario del Sistema
                    $user = User::where('client_id', $idActual)->get();
                    $this->updateId($user, $idNew, 'client_id');

                    //Redes
                    $networkIpRepository = new NetworkIpRepository();
                    $networkIps = $networkIpRepository->getNetworkIpByClientId($idActual);
                    $this->updateId($networkIps, $idNew, 'client_id');

                    //Reminder Configuration
                    $reminderConfiguration = RemindersConfiguration::where('client_id', $idActual)->get();
                    $this->updateId($reminderConfiguration, $idNew, 'client_id');

                    //Documentos
                    $documents = DocumentClient::where('client_id', $idActual)->get();
                    $this->updateId($documents, $idNew, 'client_id');

                    $client = Client::find($idActual);
                    if ($client) {
                        $client->id = $idNew;
                        $client->save();

                        $activityLogService = new LogService();
                        $activityLogService->log($client, 'Cliente actualizado desde el script app:ajuste-cliente realizado por Carlos rodriguez');
                    }

                    $this->info("Proceso completado exitosamente!");
                    return 0; // Código de éxito para comandos de consola
                } catch (\Exception $e) {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                    $this->error("Error durante el proceso: " . $e->getMessage());
                    return 1; // Código de error
                } finally {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                }
            });
        });
    }

    public function updateId($colections, $idNew, $identificator)
    {
        foreach ($colections as $colect) {
            if ($colect) {
                $colect->$identificator = $idNew;
                $colect->save();
            }
        }
    }
}

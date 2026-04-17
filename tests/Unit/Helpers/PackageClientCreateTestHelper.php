<?php

namespace Tests\Unit\Helpers;


use App\Http\Controllers\Module\Client\ClientBundleServiceController;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\HelpersModule\module\client\ClientBundleServiceDatatableHelper;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\NetworkIpRepository;
use App\Models\ClientBundleService;
use App\Models\ClientCustomService;
use App\Models\ClientInternetService;
use App\Models\ClientVozService;
use App\Models\Router;
use App\Services\MikrotikService;
use App\Services\NetworkIpService;
use Carbon\Carbon;
use Mockery;
use App\Models\Helper\Client as ClientRequest;
use Illuminate\Support\Facades\DB;

trait PackageClientCreateTestHelper
{
    public function createPackageByClient($clientId)
    {       //verifica que llegue clientId
        $this->assertNotNull($clientId, 'No llego el clientId');
        // Crear los mocks correctos
        $request = ClientRequest::requestPackage();
        $this->eliminaloDelMikrotikSiExiste();
        $helperMock = Mockery::mock(ClientBundleServiceDatatableHelper::class);
        $clientBundleServiceRepositoryMock = Mockery::mock(ClientBundleServiceRepository::class);
        $networkIpRepositoryMock = Mockery::mock(NetworkIpRepository::class);
        $clientInternetServiceRepositoryMock = Mockery::mock(ClientInternetServiceRepository::class);

        $clientBundleServiceController = new ClientBundleServiceController($helperMock, $clientBundleServiceRepositoryMock, $networkIpRepositoryMock, $clientInternetServiceRepositoryMock);
        $clientBundleService = $clientBundleServiceController->store($request, $clientId);
        $this->assertInstanceOf(ClientBundleService::class, $clientBundleService, 'No se creo un registro en client_bundle_services');

        $clientBundleService = ClientBundleService::where('client_id', $clientId)->first();
        $this->assertEquals($clientId, $clientBundleService->client_id, 'No se creo un registro en client_bundle_services');
        //Se deben ademas crear los servicios de internet correspondientes voz y custom
        $clientInternetService = ClientInternetService::where('client_bundle_service_id', $clientBundleService->id)->first();
        $this->assertInstanceOf(ClientInternetService::class, $clientInternetService, 'No se creo un registro en client_internet_services');

        $clientVozService = ClientVozService::where('client_bundle_service_id', $clientBundleService->id)->first();
        $this->assertInstanceOf(ClientVozService::class, $clientVozService, 'No se creo un registro en client_voz_services');

        $clientCustomService = ClientCustomService::where('client_bundle_service_id', $clientBundleService->id)->first();
        $this->assertInstanceOf(ClientCustomService::class, $clientCustomService, 'No se creo un registro en client_custom_services');

        //El Servicio de Internet debe crearse en el mikrotik en el ppoeSecret y firewal_address_lis
        $networkIpService = new NetworkIpService($clientInternetService);
        $ip = $networkIpService->getClientIp();
        $this->assertEquals('10.10.8.3', $ip, 'Las IPs no son correctas');

        $mikrotikService = new MikrotikService();
        $router = $clientInternetService->router;
        $connection = $mikrotikService->getConnection($router);
        $idByIp = $this->getIdByIpSecrets($connection, ComunConstantsController::PPP_SECRET_WHIT_SLASH, $ip);
        $this->assertNotNull($idByIp, 'No se creo el ppoeSecret en el mikrotik');
        $idByIp = $this->getIdByIp($connection, ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH, $ip);
        $this->assertNotNull($idByIp, 'No se creo el firewal_address_lis en el mikrotik');

        return $clientBundleService;
    }

    private function eliminaloDelMikrotikSiExiste()
    {
        $ip = '10.10.8.3';
        $router = Router::find(2);
        $mikrotikService = new MikrotikService();
        $connection = $mikrotikService->getConnection($router);
        $idByIpSecret = $this->getIdByIpSecrets($connection, ComunConstantsController::PPP_SECRET_WHIT_SLASH, $ip);
        if ($idByIpSecret) {
            $this->removeById($connection, ComunConstantsController::PPP_SECRET_WHIT_SLASH, $idByIpSecret);
        }
        $idByIpFirewall = $this->getIdByIp($connection, ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH, $ip);
        if ($idByIpFirewall) {
            $this->removeById($connection, ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH, $idByIpFirewall);
        }
    }

    public function createPackageByClientNotConectionMikrotik($clientId)
    {
        $arrayBundleService = [
            'client_id' => $clientId,
            'bundle_id' => 2,
            'description' => 'ESTUDIANTE+',
            'price' => 520,
            'pay_period' => 'Periodo 1',
            'estado' => 'Activado',
            'discount' => 0,
            'discount_percent' => null,
            'start_date_discount' => null,
            'end_date_discount' => null,
            'discount_message' => null,
            'contract_start_date' => '2024-12-27T13:41',
            'contract_end_date' => '2024-10-10 17:20:42',
            'automatic_renewal' => 1,
            'charged' => 0,
            'deployed' => 1,
            'instalation_cost_paid' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $clientBundleServiceId = DB::table('client_bundle_services')->insertGetId($arrayBundleService);
        $clientBundleService = ClientBundleService::find($clientBundleServiceId);
        $this->assertInstanceOf(ClientBundleService::class, $clientBundleService, 'No se creo un registro en client_bundle_services');
        return $clientBundleService;
    }

    public function addTransactionTypeService($client, $clientService)
    {
        return $clientService->transactions()->create([
            'date' => Carbon::now()->toDateTimeString(),
            'debit' => 520,
            'account_balance' => 0,
            'description' => 'Test',
            'category' => 'Servicio',
            'cantidad' => '1',
            'client_id' => $client->id,
            'type' => 'debit',
            'price' => 520,
            'iva' => 0,
            'total' => 520,
            'from_date' => Carbon::now(),
            'to_date' => Carbon::now(),
            'comment' => null,
            'add_to_invoice' => false,
            'company_balance' => 520,
            'movement' => '+ ' . 520,
            'service_name' => "ESTUDIANTE+",
            'invoice' => '',
            'is_payment' => false,
            'payment_id' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

    }

}

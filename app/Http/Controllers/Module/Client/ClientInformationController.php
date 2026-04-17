<?php

namespace App\Http\Controllers\Module\Client;

use App\Jobs\Mikrotik\CheckMikrotikConection;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Models\Client;
use App\Http\Controllers\Controller;
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
use App\Services\LogService;
use Illuminate\Http\Request;
use App\Http\Requests\module\client\ClientInformationRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use App\Models\Balance;
use App\Models\BillingConfiguration;
use App\Models\DocumentClient;
use App\Models\Nomenclature;
use App\Models\RemindersConfiguration;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ClientInformationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\ClientInformation $clientMainInformation
     * @return \Illuminate\Http\Response
     */
    public function show(ClientInformation $clientMainInformation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\ClientInformation $clientMainInformation
     * @return \Illuminate\Http\Response
     */
    public function edit(ClientInformation $clientMainInformation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\ClientInformation $clientInformation
     * @return \Illuminate\Http\Response
     */
    public function update(ClientInformationRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules('ClientMainInformation', $request);
        try {
            DB::beginTransaction();
            $client = \App\Models\Client::with('internet_service.router.mikrotik')->findOrFail($id);
            $input = $request->all();
            // Eliminando la fecha de alta del request para que en el actualizar no cambie el campo
            unset($input['discharge_date']);

            if ($this->ifEstadoChangeToLocked($request)) {
                foreach ($client->internet_service as $clientService) {
                    try {
                        Bus::chain([
                            new CheckMikrotikConection($clientService),
                            new MikrotikCreateAddressList($clientService)
                        ])->dispatch();
                        $logService = new LogService();
                        $logService->log($client, 'Su servicio ' . $clientService->service_name . ' fue colocado en address_list desde el ClientInformationController::update porque fue bloqueado');
                    } catch (\Exception $exception) {
                        Log::info($exception);
                    }
                }
            }

            if ($this->ifEstadoChangeToActive($request)) {
                foreach ($client->internet_service as $clientService) {
                    try {
                        Bus::chain([
                            new CheckMikrotikConection($clientService),
                            new MikrotikRemoveClientServiceFromAddressList($clientService)
                        ])->dispatch();
                        $logService = new LogService();
                        $logService->log($client, 'Su servicio ' . $clientService->service_name . ' fue removido de address_list desde el ClientInformationController::update porque fue Activado');
                    } catch (\Exception $exception) {
                        Log::info($exception);
                    }
                }
            }

            $client->billing_configuration->type_billing_id = $input['type_of_billing_id'];

            //TODO Refactorizar
            $client->billing_configuration->save();
            if (!empty($input['box_nomenclator'])) {
                $nomenclature = Nomenclature::find($input['box_nomenclator']);
                if ($nomenclature->client_id != null && $nomenclature->client_id != $client->id) {
                    // Preparamos el mensaje de error con los duplicados
                    $errors = [
                        'box_nomenclator' => ["Esta Nomenclatura esta siendo Usada"]
                    ];
                    return response()->json([
                        'errors' => $errors
                    ], 422);
                }

                $logService = new LogService();
                $oldNomenclature = Nomenclature::where('client_id', $client->id)->first();
                if ($oldNomenclature) {
                    Nomenclature::where('client_id', $client->id)->update(['client_id' => null]);
                    $logService->log($client, 'Nomenclatura cambiada de ' . $oldNomenclature->name . ' a ' . $nomenclature->name . ' por ' . auth()->user()->name . ' desde el ClientInformationController::update');
                } else {
                    $logService->log($client, 'Nomenclatura Asignada ' . $nomenclature->name . ' por ' . auth()->user()->name . ' desde el NomenclatureController::assignClient');
                }

                $nomenclature->update([
                    'client_id' => $id,
                ]);
            }
            $this->saveSingleRelationIfExist('App\Models\Client', $client, collect($input));
            DB::commit();
            return redirect()->back()->with('message', 'Información Actualizada Correctamente');
        } catch (Exception $e) {
            DB::rollBack();
            Log::info($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\ClientInformation $clientMainInformation
     * @return \Illuminate\Http\Response
     */
    public function destroy(ClientInformation $clientMainInformation)
    {
        //
    }

    public function getClientWithBalance($id)
    {
        $client = Client::where('id', $id)->with('balance')->first();
        if ($client->balance == null) {
            return [
                'name' => $client->clientFullName(),
                'balance' => 0
            ];
        }
        return [
            'name' => $client->clientFullName(),
            'balance' => $client->balance->amount
        ];
    }

    public function getClientTicketsOpen($id)
    {
        $client = Client::where('id', $id)->withCount('tickets_open', 'tickets_closed')->first();
        return [
            'open' => $client->tickets_open_count,
            'closed' => $client->tickets_closed_count
        ];
    }

    public function ifEstadoChangeToLocked($request)
    {
        $input = $request->all();
        return ($input['estado'] == 'Bloqueado');
    }

    public function ifEstadoChangeToActive($request)
    {
        $input = $request->all();
        return ($input['estado'] == 'Activo');
    }

    public function getClientStatus($id)
    {
        $client = \App\Models\Client::findOrFail($id);
        return $client->client_main_information->estado;
    }

    public function editId(Request $request)
    {
        if (!$this->userAutenticated()->can('client_edit_id')) {
            throw new Exception('No tiene permisos para realizar esta acción');
        }

        $request->validate([
            'id_new' => 'required|numeric|unique:clients,id',
        ]);

        // Ejecutar todo dentro de un bloque sin eventos y en transacción
        return Model::withoutEvents(function () use ($request) {
            return DB::transaction(function () use ($request) {
                try {
                    DB::statement('SET FOREIGN_KEY_CHECKS=0');
                    $idActual = $request->id_actual;
                    $idNew = $request->id_new;

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

                    $client = Client::findOrFail($idActual);
                    $client->id = $idNew;
                    $client->save();

                    activity()->tap(function (Activity $activity) use ($client) {
                        $activity->client_id = $client->id;
                    })->log('Cliente #' . $idActual . ' actualizado a ' . $idNew . ' desde el edit_id por el usuario ' . Auth::user()->id);

                    return response()->json(['success' => true]);
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

    public function getDataClientToSelectComponent($id)
    {
        $clientMainInformationRepository = new ClientMainInformationRepository();
        $clientMainInformation = $clientMainInformationRepository->getModelById($id);
        if ($clientMainInformation) {
            return response()->json([
                'address' => $clientMainInformation->address,
                'geo_data' => $clientMainInformation->geo_data,
                'location_id' => $clientMainInformation->location_id,
            ]);
        }
        return response()->json([
            'address' => '',
            'geo_data' => '',
            'location_id' => '',
        ]);
    }
}

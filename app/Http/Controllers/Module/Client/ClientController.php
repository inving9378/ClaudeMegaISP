<?php

namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Jobs\DeletedClientWithServiceJob;
use App\Models\Client;
use Illuminate\Http\Request;
use App\Http\HelpersModule\module\client\ClientDatatableHelper;
use App\Http\Repository\AppLayoutConfigurationRepository;
use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientRepository;
use App\Http\Requests\module\client\ClientCreateRequest;
use App\Models\Balance;
use App\Models\ClientMainInformation;
use App\Models\Module;
use App\Models\User;
use App\Services\ClientService\ClientService;
use App\Services\ColumnsDatatableModuleService;
use App\Services\FormatDateService;
use App\Services\ImportdDBService;
use App\Services\LogService;
use App\Services\PaymentService;
use App\Services\PromotionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;


class ClientController extends Controller
{
    private $helper;
    protected $data = [];

    public function __construct(ClientDatatableHelper $helper)
    {
        $model = 'Client';
        $this->data['url'] = 'meganet.module.client';
        $this->data['module'] = 'client';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'client';
        $this->helper = $helper;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['status'] = Client::statusClients();
        $this->data['allStatusToFilter'] = ComunConstantsController::STATUS_CLIENT_TO_FILTER;
        $this->data['color_datatable'] = $this->getColorDatatable();
        $this->data['allColumnsByModule'] = $this->getAllColumnsByModule();
        $this->data['columnsByUserAuthAndModule'] = $this->getColumnsByUserAndModule();

        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function getAllColumnsByModule()
    {
        $columnDatatableModuleService = new ColumnsDatatableModuleService();
        return $columnDatatableModuleService->getColumnsDatatableByModule('Client', true);
    }

    public function getColumnsByUserAndModule()
    {
        $columnDatatableModuleService = new ColumnsDatatableModuleService();
        return $columnDatatableModuleService->getColumnsDatatableByModule('Client');
    }

    public function getColorDatatable()
    {
        $appLayoutConfigurationRepository = new AppLayoutConfigurationRepository();
        $userLayoutConfiguration = $appLayoutConfigurationRepository->getModelByAuthUserId();
        return $userLayoutConfiguration->client_datatable_color ?? false;
    }

    public function success($id)
    {
        return redirect('/cliente/editar/' . $id);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.add', $this->data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(ClientCreateRequest $request)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        if ($request->import) { //TODO Quitar despues de la primera importacion
            $this->importData($request);
        } else {
            $model = $this->data['model']::create();
            $model = $model->clientCreateClientMainInformation($request)
                ->clientCreateClientAdditionalInformation($request);
            $clientHelper = new ClientHelperController($model);
            $clientHelper->stepNeededWhenNewClientIsCreated();
            return $model;
        }
    }

    public function importData($request)
    {
        $input = [
            'id' => $request->client_id_old,
            'created_at' => (new FormatDateService($request->created_at))->formatDateWithTime(),
            'updated_at' => (new FormatDateService($request->created_at))->formatDateWithTime(),
            'created_by' => 1,
            'updated_by' => 1,
        ];
        $id = DB::table('clients')->insertGetId($input);
        $model = $this->data['model']::where('id', $id)->first();

        $balance = Balance::where('balanceable_id', $model->id)->first();
        if (!$balance) {
            $model->balance()->create();
        }
        //  $model->balance()->create();

        $this->clientCreateClientMainInformation($request, $model);
        $this->clientCreateClientAdditionalInformation($request, $model);
    }

    public function clientCreateBillingConfiguration($request, $model, $typeBilling)
    {
        $fechaFormateada = Carbon::createFromFormat('d/m/Y', $request->created_at)
            ->subDay()
            ->format('d');

        $input = [
            'client_id' => $model->id,
            'billing_activated' => true,
            'type_billing_id' => $typeBilling,
            'period' => '1',
            'billing_date' => $fechaFormateada,
            'billing_expiration' => 1,
            'grace_period' => '90',
            'autopay_invoice' => true,
            'send_financial_notification' => true,
            'payment_method_id' => '1',
            'created_at' => (new FormatDateService($request->created_at))->formatDateWithTime(),
        ];

        DB::table('billing_configurations')->insert($input);
    }

    public function clientCreateClientMainInformation($request, $model)
    {
        $module = Module::where('name', Module::CLIENT_MAIN_INFORMATION_MODULE_NAME)->first();
        $key = $module->fields()->pluck('name')->toArray();

        $input = $request->except('import');

        if ($request->import) {
            $newImportDbService = new ImportdDBService();
            $input = $newImportDbService->processInputImportByModule($input, $module);
        }
        $input = \Illuminate\Support\Arr::only($input, $key);
        $input['created_at'] = (new FormatDateService($request->created_at))->formatDateWithTime();
        $input['client_id'] = $model->id;
        $clientMainInformationId = DB::table('client_main_information')->insertGetId($input);
        $clientMainInformationModel = ClientMainInformation::where('id', $clientMainInformationId)->first();

        $user = User::where('email', $input['email'])->first();
        if (!$user) {
            $this->createNewUserRoleClient($clientMainInformationModel);
        }


        $this->clientCreateBillingConfiguration($request, $model, $input['type_of_billing_id']);
    }

    public function createNewUserRoleClient($clientMainInformation)
    {
        $user = new User();
        $user->name = $clientMainInformation->name;
        $user->email = $clientMainInformation->email;
        $user->father_last_name = $clientMainInformation->father_last_name;
        $user->mother_last_name = $clientMainInformation->mother_last_name;
        $user->phone = $clientMainInformation->phone;
        $user->location = $clientMainInformation->location;
        $user->login_user = $clientMainInformation->user;
        $user->password = base64_encode($clientMainInformation->password);
        $user->client_id = $clientMainInformation->client_id;
        $user->save();

        $role = \Spatie\Permission\Models\Role::findByName('client');
        $user->assignRole($role);
    }

    public function clientCreateClientAdditionalInformation($request, $model)
    {
        $module = Module::where('name', Module::CLIENT_ADDITIONAL_INFORMATION_MODULE_NAME)->first();
        $key = $module->fields()->pluck('name')->toArray();
        $input = $request->except('user', 'import');
        if ($request->import) {
            $newImportDbService = new ImportdDBService();
            $input = $newImportDbService->processInputImportByModule($input, $module);
        }
        $input = \Illuminate\Support\Arr::only($input, $key);
        $input['created_at'] = (new FormatDateService($request->created_at))->formatDateWithTime();
        $input['client_id'] = $model->id;
        DB::table('client_additional_information')->insert($input);
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\Client $client
     * @return \Illuminate\Http\Response
     */
    public function show(Client $client)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Client $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;
        $this->data['tabs'] = $this->getTabs();
        $client_name = Client::findOrFail($id)->clientFullName();
        $this->data['breadcrumb'] = json_encode([
            ['title' => "Dashboard", 'a' => '/cliente'],
            ['title' => "Cliente", 'a' => '/cliente/listar'],
            ['title' => $client_name . ' - ' . $id, 'active' => "active", 'a' => '/cliente/editar/' . $id]
        ]);
        $this->data['authuserid'] = $this->userAutenticated()->id;

        $this->data['after'] = Client::where('id', '<', $id)->orderBy('id', 'desc')->first()->id ?? null;
        $this->data['next'] = Client::where('id', '>', $id)->orderBy('id', 'asc')->first()->id ?? null;

        return view($this->data['url'] . '.edit', $this->data);
    }

    public function getTabs()
    {
        $tabs = [];
        if ($this->userAutenticated()->hasPermissionTo('client_information_view_tab_client') || $this->userAutenticated()->isAdmin()) $tabs[] = 'information';
        $tabs[] = 'documents';
        if ($this->userAutenticated()->hasPermissionTo('client_service_view_tab_client') || $this->userAutenticated()->isAdmin()) $tabs[] = 'services';
        if ($this->userAutenticated()->hasPermissionTo('client_payroll_view_tab_client') || $this->userAutenticated()->isAdmin()) $tabs[] = 'facture';
      //  if ($this->userAutenticated()->hasPermissionTo('client_statistics_view_tab_client') || $this->userAutenticated()->isAdmin()) {
            $tabs[] = 'statistics';
   //     }
        return json_encode($tabs);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Client $client
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Client $client)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Client $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $client = Client::find($id);
        if ($client) {
            foreach ($client->internet_service as $client_internet_service) {
                if ($client_internet_service) {
                    DeletedClientWithServiceJob::dispatchAfterResponse($client_internet_service);
                }
            }

            if ($client->client_main_information && $client->client_main_information->estado == 'Cancelado') {
                $client->client_main_information()->delete();
                $client->client_additional_information()->delete();
                $client->internet_service()->delete();
                $client->voz_service()->delete();
                $client->custom_service()->delete();
                $client->bundle_service()->delete();
                $client->user()->delete();
                $client->user_system()->delete();
                $client->payments()->delete();
                $client->balance()->delete();
                $client->client_invoices()->delete();
                $client->billing_configuration()->delete();
                $client->transactions()->delete(); //SOlo elimina una
                $client->network_ip()->update(['used' => ComunConstantsController::IS_NUMERICAL_FALSE, 'used_by' => '--', 'client_id' => null]);
                $client->delete();
            } else {
                if ($client->client_main_information) {
                    $client->client_main_information()->update(['estado' => 'Cancelado']);
                } else {
                    $client->delete();
                }
            }
        }

        return redirect()->back()->with('message', Str::title($this->data['module']) . ' Eliminado Correctamente');
    }


    public function forceDelete(Request $request)
    {
        $id = $request->id_client;
        $client = Client::withTrashed()->find($id);
        if ($client) {
            // Eliminar la información principal del cliente
            $client->client_main_information()->forceDelete();


            // Eliminar la información adicional del cliente
            $client->client_additional_information()->forceDelete();

            // Eliminar servicios individuales
            $client->internet_service()->forceDelete();
            $client->voz_service()->forceDelete();
            $client->custom_service()->forceDelete();
            $client->bundle_service()->forceDelete();

            // Eliminar usuarios relacionados
            $client->user()->forceDelete();
            $client->user_system()->forceDelete();

            // Eliminar pagos y facturas
            $client->payments()->forceDelete();

            $client->client_invoices()->forceDelete();

            // Eliminar transacciones
            $client->transactions()->forceDelete();

            // Actualizar IPs de red asociadas al cliente
            $client->network_ip()->update([
                'used' => ComunConstantsController::IS_NUMERICAL_FALSE,
                'used_by' => '--',
                'client_id' => null
            ]);

            $client->billing_configuration()->forceDelete();

            $client->balance()->forceDelete();

            // Eliminar el cliente
            $client->forceDelete();
            return response()->json([
                'success' => true
            ]);
        }
        return response()->json([
            'error' => "No se Encontro el Cliente"
        ]);
    }


    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function getVal(Request $request, $id)
    {
        $model = $request->model;
        $field = $request->field;

        $data = $model::where('client_id', $id)->selectRaw($field)->first();
        if ($data) return $data->toArray();
    }

    public function getClientDebit($id)
    {
        $client = Client::find($id);
        $amount = $client->balance()->first()->amount;
        return $amount < 0 ? $amount : 0;
    }

    public function getClientMainInformationIdAndClientAdditionalInformationId($clientId)
    {
        $model = $this->data['model']::findOrFail($clientId);
        return [
            'clientMainInformationId' => $model->client_main_information->id ?? null,
            'clientAdditionalInformationId' => $model->client_additional_information->id ?? null,
        ];
    }

    public function getClientFilteredByBundleService($bundleId)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['status'] = Client::statusClients();
        $this->data['allStatusToFilter'] = ComunConstantsController::STATUS_CLIENT_TO_FILTER;
        $this->data['color_datatable'] = $this->getColorDatatable();
        $this->data['allColumnsByModule'] = $this->getAllColumnsByModule();
        $this->data['columnsByUserAuthAndModule'] = $this->getColumnsByUserAndModule();
        $this->includeLibraryDinamic($this->data['model']);
        if ($bundleId) {
            $filters[] = ["bundle_id" => $bundleId];
        }
        $this->data['filters'] = json_encode($filters);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function getClientFilteredByInternetService($internetId)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['status'] = Client::statusClients();
        $this->data['allStatusToFilter'] = ComunConstantsController::STATUS_CLIENT_TO_FILTER;
        $this->data['color_datatable'] = $this->getColorDatatable();
        $this->data['allColumnsByModule'] = $this->getAllColumnsByModule();
        $this->data['columnsByUserAuthAndModule'] = $this->getColumnsByUserAndModule();
        $this->includeLibraryDinamic($this->data['model']);
        if ($internetId) {
            $filters[] = ["internet_id" => $internetId];
        }
        $this->data['filters'] = json_encode($filters);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function getClientFilteredByCustomService($customId)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['status'] = Client::statusClients();
        $this->data['allStatusToFilter'] = ComunConstantsController::STATUS_CLIENT_TO_FILTER;
        $this->data['color_datatable'] = $this->getColorDatatable();
        $this->data['allColumnsByModule'] = $this->getAllColumnsByModule();
        $this->data['columnsByUserAuthAndModule'] = $this->getColumnsByUserAndModule();
        $this->includeLibraryDinamic($this->data['model']);
        if ($customId) {
            $filters[] = ["custom_id" => $customId];
        }
        $this->data['filters'] = json_encode($filters);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function getClientFilteredByVozService($vozId)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['status'] = Client::statusClients();
        $this->data['allStatusToFilter'] = ComunConstantsController::STATUS_CLIENT_TO_FILTER;
        $this->data['color_datatable'] = $this->getColorDatatable();
        $this->data['allColumnsByModule'] = $this->getAllColumnsByModule();
        $this->data['columnsByUserAuthAndModule'] = $this->getColumnsByUserAndModule();
        $this->includeLibraryDinamic($this->data['model']);
        if ($vozId) {
            $filters[] = ["voz_id" => $vozId];
        }
        $this->data['filters'] = json_encode($filters);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function getClientFilteredBySeller($sellerId)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['status'] = Client::statusClients();
        $this->data['allStatusToFilter'] = ComunConstantsController::STATUS_CLIENT_TO_FILTER;
        $this->data['color_datatable'] = $this->getColorDatatable();
        $this->data['allColumnsByModule'] = $this->getAllColumnsByModule();
        $this->data['columnsByUserAuthAndModule'] = $this->getColumnsByUserAndModule();
        $this->includeLibraryDinamic($this->data['model']);
        if ($sellerId) {
            $filters[] = ["seller_id" => $sellerId];
        }
        $this->data['filters'] = json_encode($filters);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function editCourtDate(Request $request)
    {
        if ($this->userAutenticated()->can('client_edit_fecha_corte')) {
            $client = $this->data['model']::findOrFail($request->id_client);
            $oldFechaCorte = $client->fecha_corte;
            $courtDate = $request->new_court_date;
            $carbonDate = Carbon::parse($courtDate);
            $formattedCourtDate = $carbonDate->format('Y-m-d H:i');
            $client->fecha_corte = $formattedCourtDate;
            $client->save();

            activity()->tap(function (Activity $activity) use ($client, $oldFechaCorte) {
                $activity->client_id = $client->id;
            })->log('Cliente #' . $client->id . ' ACTUALIZADO POR ' . Auth::user()->name . ' FECHA DE CORTE ANTERIOR ' . $oldFechaCorte . '   FECHA DE CORTE ACTUAL ' . $formattedCourtDate);
            return $client;
        }
        return throw new Exception('No tiene permisos para realizar esta accion');
    }

    public function editDatePayment(Request $request)
    {
        if ($this->userAutenticated()->can('client_edit_fecha_pago')) {
            $client = $this->data['model']::findOrFail($request->id_client);
            $oldFechaPago = $client->fecha_corte;
            $datePayment = $request->new_date;
            $carbonDate = Carbon::parse($datePayment);
            $formattedDatePayment = $carbonDate->format('Y-m-d H:i');
            $client->fecha_pago = $formattedDatePayment;
            $client->save();
            activity()->tap(function (Activity $activity) use ($client) {
                $activity->client_id = $client->id;
            })->log('Cliente #' . $client->id . ' ACTUALIZADO POR ' . Auth::user()->name . ' FECHA DE PAGO ANTERIOR ' . $oldFechaPago . '   FECHA DE PAGO ACTUAL ' . $formattedDatePayment);
            return $client;
        }
        return throw new Exception('No tiene permisos para realizar esta accion');
    }

    public function editBalance(Request $request)
    {
        // TODO pedido por irving quitar despues
        if ($this->userAutenticated()->can('client_edit_balance')) {
            $client = $this->data['model']::findOrFail($request->id_client);
            $newBalance = $client->balance()->first();
            $newBalance->amount = $request->new_balance;
            $newBalance->save();
            if ($request->new_balance >= 0) {
                $clientMainInformationRepository = new ClientMainInformationRepository();
                $clientMainInformationRepository->setClientMainInformationByClientId($client->id);
                $clientMainInformationRepository->setStateActive();
            }

            activity()->tap(function (Activity $activity) use ($client) {
                $activity->client_id = $client->id;
            })->log('Cliente #' . $client->id . ' Actualizada Balance anterior: ' . $client->balance->amount . ' Actual : ' . $newBalance->amount . ' por el usuario: ' . Auth::user()->id);

            return $newBalance;
        }

        return throw new Exception('No tiene permisos para realizar esta accion');
    }

    public function getClientToPaymentToDay()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['status'] = Client::statusClients();
        $this->data['allStatusToFilter'] = ComunConstantsController::STATUS_CLIENT_TO_FILTER;
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['color_datatable'] = $this->getColorDatatable();
        $this->data['allColumnsByModule'] = $this->getAllColumnsByModule();
        $this->data['columnsByUserAuthAndModule'] = $this->getColumnsByUserAndModule();

        $filters[] = ["fecha_pago_today" => Carbon::now()->toDateString()];

        $this->data['filters'] = json_encode($filters);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function getClientToSuspendToDay()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['status'] = Client::statusClients();
        $this->data['allStatusToFilter'] = ComunConstantsController::STATUS_CLIENT_TO_FILTER;
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['color_datatable'] = $this->getColorDatatable();
        $this->data['allColumnsByModule'] = $this->getAllColumnsByModule();
        $this->data['columnsByUserAuthAndModule'] = $this->getColumnsByUserAndModule();

        $filters[] = ["fecha_corte_today" => Carbon::now()->toDateString()];

        $this->data['filters'] = json_encode($filters);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function getIsPromisePayment($id)
    {
        $client = $this->data['model']::where('id', $id)->first();
        return $client->active_promise_payment;
    }

    public function geClientIdByClientMainInformationId($id)
    {
        $clientMainInformationRepository = new ClientMainInformationRepository();
        $clientId = $clientMainInformationRepository->getClientIdByClientMainInformationId($id);
        return $clientId;
    }

    public function paymentInstalationCostServices(Request $request)
    {
        $clientId = $request->id_client;
        $clientRepository = new ClientRepository();
        $this->validate($request, [
            'id_client' => 'required',
        ]);

        try {
            DB::beginTransaction();
            $clientRepository = new ClientRepository();
            $clientWithServices = $clientRepository->getServicesForClient($clientId);
            $allServices = ComunConstantsController::ALL_CLIENT_SERVICE;
            $servicesIdString = '';
            foreach ($allServices as $service) {
                foreach ($clientWithServices->$service as $clientService) {
                    if ($clientService->has_active_instalation_cost && !$clientService->instalation_cost_paid) {
                        $clientService->instalation_cost_paid = true;
                        $servicesIdString .= $clientService->id . ', ';
                        $paymentService = new PaymentService($clientService);
                        $paymentService->addPaymentCostInstalationPaid(true, true);
                        $clientService->save();
                    }
                }
            }
            $logService = new LogService();
            $logService->log($clientWithServices, 'Ha pagado el costo de instalacion de los servicios: ' . $servicesIdString);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'El pago de costo de instalación se ha realizado con éxito.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar el pago: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function paymentActivationCost(Request $request)
    {
        $this->validate($request, [
            'id_client' => 'required',
        ]);

        $clientId = $request->id_client;
        $clientRepository = new ClientRepository();
        $client = $clientRepository->getClientById($clientId);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado.',
            ], 404);
        }

        try {
            DB::beginTransaction();
            $clientService = new ClientService($client);
            $clientService->paidActivationCost();

            $logService = new LogService();
            $logService->log($client, 'Ha pagado el costo de activación de los servicios');
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'El costo de activación de pago se ha realizado con éxito.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar el pago: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getPromotionsByClient($clientId)
    {
        $promotionService = new PromotionService();
        $clientRepository = new ClientRepository();
        $clientWithServices = $clientRepository->getServicesForClient($clientId);

        $promotions = $promotionService->getServicesHasPromotionByClient($clientWithServices);
        return $promotions;
    }

    public function getPaymentPeriodByAmount(Request $request, $clientId)
    {
        $amount = $request->amount;

        $client = $this->data['model']::find($clientId);
        if (!$client) {
            return null;
        }

        return (new ClientService($client))->getPaymentPeriod($amount);
    }

    public function getActiveClients()
    {
        $clients = DB::select("SELECT id main_id, client_id, CONCAT(client_id, ' - ', NAME, ' ', COALESCE(father_last_name, ' '), ' ', COALESCE(mother_last_name, ' ')) name FROM client_main_information where estado='Activo' order by name");
        return response()->json($clients);
    }
}

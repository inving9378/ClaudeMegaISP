<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Jobs\DeletedClientWithServiceJob;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Http\Request;
use App\Http\HelpersModule\module\client\ClientDatatableHelper;
use App\Modules\Core\Layout\Repositories\AppLayoutConfigurationRepository;
use App\Modules\Core\Clientes\Repositories\ClientMainInformationRepository;
use App\Modules\Core\Clientes\Repositories\ClientRepository;
use App\Http\Requests\module\client\ClientCreateRequest;
use App\Models\Balance;
use App\Modules\Core\Clientes\Models\ClientMainInformation;
use App\Models\Module;
use App\Models\User;
use App\Modules\Core\Clientes\Services\ClientService;
use App\Services\Client\ClientDeletionService;
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
            $model = $this->data['model']::create(
                array_filter(['referred_by_code' => $request->referred_by_code])
            );
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
        $user->password = \App\Services\Security\PasswordService::make($clientMainInformation->password);
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
        $this->data['moduleTabs'] = $this->getModuleTabs();
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
        $tabs[] = 'promotions';
      //  if ($this->userAutenticated()->hasPermissionTo('client_statistics_view_tab_client') || $this->userAutenticated()->isAdmin()) {
            $tabs[] = 'statistics';
   //     }
        return json_encode($tabs);
    }

    /**
     * Pestañas de ficha de cliente aportadas por módulos addon activos.
     *
     * Lee las declaraciones `client_tab` (no diferidas) que ModuleRegistry
     * compila desde los module.json, y las filtra por el permiso que cada
     * pestaña declara. Es la "infra de ficha de cliente extensible": un módulo
     * sólo necesita declarar su client_tab + registrar su componente Vue para
     * que aparezca aquí, sin tocar ClientController ni ClientCrud.vue.
     *
     * @return string JSON: [{label, component, permission, module}]
     */
    public function getModuleTabs()
    {
        $registry = app(\App\Modules\Core\ModuleManager\Services\ModuleRegistry::class);
        $user     = $this->userAutenticated();
        $isAdmin  = $user->isAdmin();
        $out      = [];

        foreach ($registry->getClientTabs() as $tab) {
            $component = $tab['component'] ?? null;
            if (empty($component)) {
                continue; // declaración inválida: sin componente no hay nada que montar
            }

            $perm = $tab['permission'] ?? null;
            if (! $isAdmin && ! empty($perm)) {
                try {
                    if (! $user->hasPermissionTo($perm)) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    // El permiso declarado aún no existe en BD: ocultar la pestaña
                    // a no-admins en lugar de romper la ficha completa.
                    continue;
                }
            }

            $out[] = [
                'label'      => $tab['label'] ?? ($tab['_module'] ?? 'Módulo'),
                'component'  => $component,
                'permission' => $perm,
                'module'     => $tab['_module'] ?? null,
            ];
        }

        return json_encode($out);
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
     */
    public function destroy($id)
    {
        $client = Client::find($id);
        if (! $client) {
            return redirect()->back()->with('message', 'Cliente no encontrado.');
        }

        // Borrado en dos pasos:
        //  - 1er click (estado != Cancelado): se marca 'Cancelado' (red de seguridad).
        //  - 2º click (ya 'Cancelado') o cliente sin información principal:
        //    borrado FÍSICO definitivo de TODAS sus referencias vía ClientDeletionService.
        $yaCancelado = $client->client_main_information
            && $client->client_main_information->estado == 'Cancelado';

        if ($yaCancelado || ! $client->client_main_information) {
            app(ClientDeletionService::class)->forceDeleteClient($client);

            return redirect()->back()->with('message', 'Cliente eliminado definitivamente.');
        }

        $client->client_main_information()->update(['estado' => 'Cancelado']);

        return redirect()->back()->with('message', 'Cliente cancelado. Vuelve a eliminar para borrarlo definitivamente.');
    }


    public function forceDelete(Request $request)
    {
        $id = $request->id_client;
        $client = Client::withTrashed()->find($id);
        if ($client) {
            // Mismo borrado físico completo que el 2º paso de destroy(): limpia
            // TODAS las referencias (no solo ~14), evitando huérfanos.
            app(ClientDeletionService::class)->forceDeleteClient($client);

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

    public function getClientsWithoutDataPromotions(Request $request)
    {
        $query = DB::table('client_main_information')
            ->selectRaw("client_main_information.client_id, CONCAT(client_main_information.name,' ',client_main_information.father_last_name, ' ', client_main_information.mother_last_name) as client_name, FALSE as selected, olt_onus.service_ports")
            ->where('client_main_information.estado', 'Activo')
            ->whereNotIn('client_main_information.client_id', function ($subquery) {
                $subquery->select('client_id')
                    ->from('client_plan_promotions')
                    ->where('status', 'active');
            })
            ->whereNotNull('client_main_information.name')
            ->join('olt_onus', 'client_main_information.client_id', '=', 'olt_onus.client_id');

        $this->setQueryFilters($query, $request);

        $results = $query->get();

        return response()->json($results);
    }

    public function getClientsWithDataPromotions(Request $request)
    {
        $query = ClientMainInformation::whereHas('client', function ($q) {
            $q->withActiveDataPromotion();
        })->stateActive();
        return response()->json($query->paginate(isset($request->rowsPerPage) ? $request->rowsPerPage : 20, ['*'], 'page', isset($request->page) ? $request->page : null));
    }

    public function setQueryFilters($query, $request)
    {
        $exactFilters = ['municipality_id', 'seller_id', 'state_id', 'colony_id', 'street'];

        foreach ($exactFilters as $f) {
            $query->when($request->filled($f), function ($q) use ($request, $f) {
                return $q->where($f, $request->input($f));
            });
        }

        $dateFilters = ['activation_date', 'discharge_date', 'created_at'];
        foreach ($dateFilters as $f) {
            $query->when($request->filled($f), function ($q) use ($request, $f) {
                $values = $request->input($f);
                $from = $values[0];
                $to = $values[1];
                if ($from && $to) {
                    return $q->whereDate(
                        'client_main_information.' . $f,
                        '>=',
                        $from
                    )->whereDate(
                        'client_main_information.' . $f,
                        '<=',
                        $to
                    );
                }
                return $q;
            });
        }
    }
}

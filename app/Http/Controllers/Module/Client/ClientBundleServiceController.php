<?php

namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Models\Bundle;
use App\Models\ClientAdditionalInformation;
use App\Models\ClientBundleService;
use App\Http\HelpersModule\module\client\ClientBundleServiceDatatableHelper;
use App\Http\Repository\BundleRepository;
use App\Http\Repository\ClientCustomServiceRepository;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\ClientUserRepository;
use App\Http\Repository\ClientVozServiceRepository;
use App\Http\Repository\CustomRepository;
use App\Http\Repository\NetworkIpRepository;
use App\Models\ClientCustomService;
use App\Models\ClientInternetService;
use App\Models\ClientVozService;
use App\Models\Custom;
use App\Models\Internet;
use App\Models\Module;
use App\Models\Voise;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Traits\RouterConnection;
use App\Jobs\CreateClientRouterJob;
use App\Jobs\CreateClientWithServiceJob;
use App\Jobs\DeletedClientWithServiceJob;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use App\Jobs\NetworkIp\SetIPToClientInternetServiceJob;
use App\Models\Client;
use App\Models\NetworkIp;
use App\Services\LogService;
use Carbon\Carbon;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientBundleServiceController extends Controller

{
    use RouterConnection;
    private $helper;
    private $bundleServiceRepository;

    protected $networkIpRepository;
    protected $clientInternetServiceRepository;

    public function __construct(ClientBundleServiceDatatableHelper $helper, ClientBundleServiceRepository $bundleServiceRepository, NetworkIpRepository $networkIpRepository, ClientInternetServiceRepository $clientInternetServiceRepository)
    {
        $model = 'ClientBundleService';
        $this->data['url'] = 'meganet.module' . $model;
        $this->data['module'] = 'ClientBundleService';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->helper = $helper;
        $this->bundleServiceRepository = $bundleServiceRepository;
        $this->networkIpRepository = $networkIpRepository;
        $this->clientInternetServiceRepository = $clientInternetServiceRepository;
    }

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
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.add', $this->data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = $request->all();
        $arraValidation = $this->getInputValidation($input);
        $input = $request->validate($arraValidation);

        $bundleService = $this->createOrUpdateBundleService($input, $id);
        $this->createOrUpdateInternetService($bundleService->id, $input, $id);
        $this->createOrUpdateVozService($bundleService->id, $input, $id);
        $this->createOrUpdateCustomService($bundleService->id, $input, $id);
        return $bundleService;
    }

    public function formatCustomDate($fecha)
    {
        // Crear un objeto Carbon con los componentes de fecha
        $fechaCarbon = Carbon::createFromFormat('m/d/Y', $fecha);
        // Formatear la fecha al formato deseado (día-mes-año)
        $fechaFormateada = $fechaCarbon->format('Y-m-d');
        return $fechaFormateada;
    }

    public function imporDataToTable(Request $request)
    {
        $input = $request->except('import');
        $client = Client::where('id', $request->client_id)->first();
        if ($client) {
            $input['estado'] = $client->client_main_information->estado;
        } else {
            Log::info('No existe cliente con id: ' . $request->client_id);
            $input['estado'] = 'Bloqueado';
        }

        $bundle = Bundle::where('title', $input['bundle_id'])->first();
        if ($bundle) {
            $input['bundle_id'] = $bundle->id;
        } else {
            Log::info("no existe un Paquete con el titulo: " . $input['bundle_id']);
            return null;
        }

        $input['automatic_renewal'] = $input['automatic_renewal'] ?? false;
        $input['contract_start_date'] = $this->formatCustomDate($input['contract_start_date']);
        if (isset($input['contract_end_date']) && !empty($input['contract_end_date'])) {
            $input['contract_end_date'] = $this->formatCustomDate($input['contract_end_date']);
        }

        DB::table('client_bundle_services')->insert($input);
    }


    public function createOrUpdateBundleService($input, $idClient, $id = null)
    {
        $bundle_service = collect($input)->filter(function ($val, $key) {
            return Str::contains($key, ['bundle_']);
        })->mapWithKeys(function ($item, $key) {
            if ($key != 'bundle_id') return [Str::after($key, 'bundle_') => $item];
            return [$key => $item];
        });
        $bundle_input = $bundle_service->toArray();
        $bundleRepository = new BundleRepository();
        $bundle = $bundleRepository->getModelFilterById($bundle_input['bundle_id']);
        $bundle_input['price'] = $bundle->price ?? 0;
        if (array_key_exists('cost_instalation', $bundle_input)) {
            unset($bundle_input['cost_instalation']);
        }

        if ($id) {
            $clientBundleService = ClientBundleService::find($id);
            $clientBundleService->update($bundle_input);
            return $clientBundleService;
        }

        $bundle_input["client_id"] = $idClient;
        return ClientBundleService::create($bundle_input);
    }

    public function createOrUpdateInternetService($bundleServiceId, $input, $idClient, $bundleService = null)
    {
        $relation = 'plan_internet';
        $plan = $this->filterPlan($input, $relation);
        if ($plan->count()) {
            $id = $this->getIdPlan($plan);
            $chunk_by = $this->getChunkBy($plan, $id);

            foreach ($plan->chunk($chunk_by) as $item) {
                $id_plan = $this->getIdPlan($item);

                $plan_service = $item->mapWithKeys(function ($item, $key) use ($relation) {
                    $key = Str::after($key, $relation . '_');
                    $key = Str::beforeLast($key, '_');
                    return [$key => $item];
                });

                $plan_input = $plan_service->toArray();

                // TODO arreglar optimizar y agregar constante
                $this->removeUnusedFieldsByIpv4AssignmentInPlanInput($plan_input);
                if ($bundleService) {
                    $clientInternetService = ClientInternetService::where('client_bundle_service_id', $bundleService->id)
                        ->where('internet_id', $id_plan)
                        ->first();

                    if ($clientInternetService) {
                        if ($this->changeRouter($clientInternetService, $plan_input)) {
                            $this->updateIpClientAndService($clientInternetService, $plan_input);
                        }
                        if ($this->changeIpv4($clientInternetService, $plan_input)) {
                            $this->updateIpv4Service($clientInternetService, $plan_input);
                        }
                        $clientInternetService->update($plan_input);
                    }
                } else {
                    $service = Internet::find($id_plan);
                    $plan_input["client_id"] = $idClient;
                    $plan_input["internet_id"] = $service->id;
                    $plan_input["client_bundle_service_id"] = $bundleServiceId;
                    $plan_input["description"] = $service->service_name;
                    $plan_input["amount"] = 1;
                    $plan_input["unity"] = 1;
                    $plan_input["price"] = $service->price;
                    $plan_input["pay_period"] = 'Periodo 1';
                    $plan_input["start_date"] = \Carbon\Carbon::now()->format('Y-m-d\TH:i');
                    $plan_input["estado"] = ComunConstantsController::STATE_ACTIVE;
                    $plan_input["user"] = $plan_input["client_name"];
                    $clientInternetService = ClientInternetService::create($plan_input);
                    $clientInternetService->save();
                    SetIPToClientInternetServiceJob::dispatch($clientInternetService);
                    CreateClientWithServiceJob::dispatch($clientInternetService);
                    MikrotikCreateAddressList::dispatch($clientInternetService);
                    $client = (new ClientRepository)->getClientById($idClient);
                    $logService = new LogService();
                    $logService->log($client, 'Su servicio fue colocado en address_list desde el createOrUpdateInternetService::create');
                }
            }
        }
    }

    public function createOrUpdateVozService($bundleServiceId, $input, $idClient, $bundleService = null)
    {
        // Crear Voz Service
        $relation = 'plan_voz';
        $plan = $this->filterPlan($input, $relation);

        if ($plan->count()) {
            $id = $this->getIdPlan($plan);
            $chunk_by = $this->getChunkBy($plan, $id);

            foreach ($plan->chunk($chunk_by) as $item) {
                $id_plan = $this->getIdPlan($item);

                $plan_service = $item->mapWithKeys(function ($item, $key) use ($relation) {
                    $key = Str::after($key, $relation . '_');
                    $key = Str::beforeLast($key, '_');
                    return [$key => $item];
                });

                $plan_input = $plan_service->toArray();
                if ($bundleService) {
                    $bundleService->service_voz()->where('voz_id', $id_plan)->update($plan_input);
                } else {
                    $service = Voise::find($id_plan);

                    $plan_input["client_id"] = $idClient;
                    $plan_input["voz_id"] = $service->id;
                    $plan_input["client_bundle_service_id"] = $bundleServiceId;
                    $plan_input["description"] = $service->service_name;
                    $plan_input["amount"] = 1;
                    $plan_input["unity"] = 1;
                    $plan_input["price"] = $service->price;
                    $plan_input["pay_period"] = 'Periodo 1';
                    $plan_input["start_date"] = \Carbon\Carbon::now()->format('Y-m-d\TH:i');
                    $plan_input["estado"] = 'Activado';

                    ClientVozService::create($plan_input);
                }
            }
        }
    }

    public function createOrUpdateCustomService($bundleServiceId, $input, $idClient, $bundleService = null)
    {
        // Crear Custom Service
        $relation = 'plan_custom';
        $plan = $this->filterPlan($input, $relation);

        if ($plan->count()) {
            $id = $this->getIdPlan($plan);
            $chunk_by = $this->getChunkBy($plan, $id);

            foreach ($plan->chunk($chunk_by) as $item) {
                $id_plan = $this->getIdPlan($item);

                $plan_service = $item->mapWithKeys(function ($item, $key) use ($relation) {
                    $key = Str::after($key, $relation . '_');
                    $key = Str::beforeLast($key, '_');
                    return [$key => $item];
                });

                if ($plan_service['user'] != '' || $plan_service['password'] != '') {
                    $client = ClientAdditionalInformation::where('client_id', ClientBundleService::find($bundleServiceId)->client_id)->first();

                    $client->update([
                        'user_film' => $plan_service['user'],
                        'password_film' => $plan_service['password']
                    ]);
                }

                $plan_input = $plan_service->toArray();
                $this->removeUnusedFieldsByIpv4AssignmentInPlanInput($plan_input);
                if ($bundleService) {
                    $clientCustomService = ClientCustomService::where('client_bundle_service_id', $bundleService->id)
                        ->where('custom_id', $id_plan)->first();
                    $clientUserRepository = new ClientUserRepository();
                    if ($clientCustomService && $clientCustomService->internet_id) {
                        $clientUser = $clientUserRepository->getModelByServiceId($clientCustomService->id);
                        if ($clientUser->user != $plan_input['user']) {
                            $clientUser->update([
                                'user' => $clientCustomService->user
                            ]);
                        }
                    }
                    if ($this->changeRouter($clientCustomService, $plan_input)) {
                        $this->updateIpClientAndService($clientCustomService, $plan_input);
                    } else if ($this->changeIpv4($clientCustomService, $plan_input)) {
                        $this->updateIpv4Service($clientCustomService, $plan_input);
                    } else {
                        $clientCustomService->update($plan_input);
                    }
                } else {
                    $service = Custom::find($id_plan);
                    if ($plan_input["router_id"]) {
                        $plan_input["internet_id"] = $service->id;
                    }
                    $plan_input["client_id"] = $idClient;
                    $plan_input["custom_id"] = $service->id;
                    $plan_input["client_bundle_service_id"] = $bundleServiceId;
                    $plan_input["description"] = $service->service_name;
                    $plan_input["amount"] = 1;
                    $plan_input["unity"] = 1;
                    $plan_input["price"] = $service->price;
                    $plan_input["pay_period"] = 'Periodo 1';
                    $plan_input["start_date"] = \Carbon\Carbon::now()->format('Y-m-d\TH:i');
                    $plan_input["estado"] = 'Activado';

                    $clientCustomService = ClientCustomService::create($plan_input);
                    $clientCustomService->save();
                    if ($clientCustomService->internet_id) {
                        SetIPToClientInternetServiceJob::dispatch($clientCustomService);
                        CreateClientWithServiceJob::dispatch($clientCustomService);
                        MikrotikCreateAddressList::dispatch($clientCustomService);
                        $client = (new ClientRepository)->getClientById($idClient);
                        $logService = new LogService();
                        $logService->log($client, 'Su servicio Custom fue colocado en address_list desde el createOrUpdateInternetService::create');
                    }
                }
            }
        }
    }


    public function filterPlan($input, $plan)
    {
        return collect($input)->filter(function ($val, $key) use ($plan) {
            return Str::contains($key, [$plan]);
        });
    }

    public function getIdPlan($plan)
    {
        return Str::afterLast($plan->keys()[0], '_');
    }

    public function getChunkBy($plan, $id)
    {
        return $plan->filter(function ($val, $key) use ($id) {
            return Str::endsWith($key, '_' . $id);
        })->count();
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\ClientBundleService $clientBundleService
     * @return \Illuminate\Http\Response
     */
    public function show(ClientBundleService $clientBundleService)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\ClientBundleService $clientBundleService
     * @return \Illuminate\Http\Response
     */
    public function edit(ClientBundleService $clientBundleService)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\ClientBundleService $clientBundleService
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $clientBundleServiceId)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = $request->all();
        $arraValidation = $this->getInputValidation($input, $clientBundleServiceId);
        $input = $request->validate($arraValidation);
        $idClient = null;

        $bundleService = $this->createOrUpdateBundleService($input, $idClient, $clientBundleServiceId);
        $this->createOrUpdateInternetService($bundleService->id, $input, $idClient, $bundleService);
        $this->createOrUpdateVozService($bundleService->id, $input, $idClient, $bundleService);
        $this->createOrUpdateCustomService($bundleService->id, $input, $idClient, $bundleService);

        return $bundleService;
    }

    public function liberaLaIpUsada($clientInternetService)
    {
        $networkIpRepository = new NetworkIpRepository();
        $networkIp = $networkIpRepository->getNetworkIpByClientInternetServiceId($clientInternetService->id);
        if ($networkIp) {
            $this->networkIpRepository->removeUsedIp($networkIp);
        }

        $clientUserRepository = new ClientUserRepository();
        $clientUser = $clientUserRepository->getModelByServiceId($clientInternetService->id);
        if ($clientUser) {
            $clientUser->delete();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\ClientBundleService $clientBundleService
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $model = $this->data['model']::findOrFail($id);
        $this->removeServiceInternet($id);
        $this->removeServiceCustom($id);
        $model->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function removeServiceInternet($id)
    {
        $clientInternetServiceRepository = new ClientInternetServiceRepository();
        $clientInternetServices = $clientInternetServiceRepository->getClientInternetServiceByClientBundleServiceId($id);
        foreach ($clientInternetServices as $service) {
            try {
                Bus::chain([
                    new DeletedClientWithServiceJob($service)
                ])->dispatch();
            } catch (\Exception $exception) {
                Log::info($exception);
            }
            $this->liberaLaIpUsada($service);
        }
    }
    public function removeServiceCustom($id)
    {
        $clientCustomServiceRepository = new ClientCustomServiceRepository();
        $clientCustomServices = $clientCustomServiceRepository->getClientCustomServiceByClientBundleServiceId($id);
        foreach ($clientCustomServices as $service) {
            if ($service->internet_id) {
                try {
                    Bus::chain([
                        new DeletedClientWithServiceJob($service)
                    ])->dispatch();
                } catch (\Exception $exception) {
                    Log::info($exception);
                }
                $this->liberaLaIpUsada($service);
            }
        }
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }

    public function getPlansById(Request $request, $bundleId)
    {
        $module = Module::where('name', 'ClientBundleService')->first();
        $fields = $module->getfields();
        $bundle = Bundle::find($bundleId)->load(['planes_internet', 'planes_voz', 'planes_custom']);

        $fields["bundle_id"]["value"] = $bundleId;
        // bundle_service_option
        $fields["bundle_description"]["value"] = $bundle->service_description;
        $fields["bundle_price"]["value"] = $bundle->price;
        if ($bundle->cost_instalation_enable == 1) {
            $fields["bundle_cost_instalation"]["value"] = $bundle->cost_instalation ?? 0;
        } else {
            unset($fields["bundle_cost_instalation"]);
        }


        $fields_planes_internet = collect($fields)->filter(function ($value, $key) {
            return Str::contains($key, ['plan_internet_']);
        })->toArray();
        $fields_planes_voz = collect($fields)->filter(function ($value, $key) {
            return Str::contains($key, ['plan_voz_']);
        })->toArray();
        $fields_planes_custom = collect($fields)->filter(function ($value, $key) {
            return Str::contains($key, ['plan_custom_']);
        })->toArray();

        $fields = collect($fields)->filter(function ($value, $key) {
            return !Str::contains($key, ['plan_internet_']);
        });
        $fields = collect($fields)->filter(function ($value, $key) {
            return !Str::contains($key, ['plan_voz_']);
        });
        $fields = collect($fields)->filter(function ($value, $key) {
            return !Str::contains($key, ['plan_custom_']);
        });


        foreach ($bundle->planes_internet as $plan) {
            $keyed = collect($fields_planes_internet)->mapWithKeys(function ($item, $key) use ($plan) {
                return [$key . '_' . $plan->id => $item];
            })->map(function ($val) use ($plan) {
                $val["field"] = $val["field"] . '_' . $plan->id;
                $val["partition"] = $val["partition"] . '_' . $plan->id;
                if ($val["inputs_depend"]) {
                    $val["inputs_depend"] = $this->objectToArray($val["inputs_depend"]);
                    foreach ($val["inputs_depend"] as $k => $v) {
                        $newK = $k . '_' . $plan->id;
                        $v["field"] = $newK;
                        $val["inputs_depend"][$newK] = $v;
                        unset($val["inputs_depend"][$k]);
                    }
                }
                return $val;
            })->toArray();

            $fields = $fields->merge($keyed);
        }

        foreach ($bundle->planes_voz as $plan) {
            $keyed = collect($fields_planes_voz)->mapWithKeys(function ($item, $key) use ($plan) {
                return [$key . '_' . $plan->id => $item];
            })->map(function ($val) use ($plan) {
                $val["field"] = $val["field"] . '_' . $plan->id;
                $val["partition"] = $val["partition"] . '_' . $plan->id;
                return $val;
            })->toArray();

            $fields = $fields->merge($keyed);
        }

        foreach ($bundle->planes_custom as $plan) {
            $keyed = collect($fields_planes_custom)->mapWithKeys(function ($item, $key) use ($plan) {
                return [$key . '_' . $plan->id => $item];
            })->map(function ($val) use ($plan) {
                $field = str_replace('plan_custom_', '', $val["field"]);
                $val["value"] = $plan->$field;
                $val["field"] = $val["field"] . '_' . $plan->id;
                $val["partition"] = $val["partition"] . '_' . $plan->id;
                if ($val["inputs_depend"]) {
                    $val["inputs_depend"] = $this->objectToArray($val["inputs_depend"]);
                    foreach ($val["inputs_depend"] as $k => $v) {
                        $newK = $k . '_' . $plan->id;
                        $v["field"] = $newK;
                        $val["inputs_depend"][$newK] = $v;
                        unset($val["inputs_depend"][$k]);
                    }
                }
                $val["include"] = $this->showAdditionalFieldsWithCheckboxTrue($plan, $field, $val);
                return $val;
            })->toArray();

            $fields = $fields->merge($keyed);
        }
        return [
            'fields' => $fields->toArray(),
            'planes_internet' => $bundle->planes_internet,
            'planes_voz' => $bundle->planes_voz,
            'planes_custom' => $bundle->planes_custom,
        ];
    }

    public function getEditedServiceBundleById($serviceBundleId)
    {
        $clientBundleService = ClientBundleService::find($serviceBundleId)->load(['service_internet', 'service_voz', 'service_custom']);
        $bundleId = $clientBundleService->bundle_id;

        $clientAdditional = ClientAdditionalInformation::where('client_id', $clientBundleService->client_id)->first();

        $module = Module::where('name', 'ClientBundleService')->first();
        $fields = $module->getfields();
        $bundle = Bundle::find($bundleId)->load(['planes_internet', 'planes_voz', 'planes_custom']);


        $fields["bundle_id"]["value"] = $bundleId;
        // bundle_service_option
        $fields["bundle_description"]["value"] = $bundle->service_description;
        $fields["bundle_price"]["value"] = $bundle->price;
        $fields["bundle_automatic_renewal"]["value"] = $clientBundleService->automatic_renewal;
        if ($bundle->active_instalation_cost == 1) {
            $fields["bundle_instalation_cost"]["value"] = $bundle->instalation_cost ?? 0;
        } else {
            unset($fields["bundle_instalation_cost"]);
        }

        $fields_planes_internet = collect($fields)->filter(function ($value, $key) {
            return Str::contains($key, ['plan_internet_']);
        })->toArray();
        $fields_planes_voz = collect($fields)->filter(function ($value, $key) {
            return Str::contains($key, ['plan_voz_']);
        })->toArray();

        $fields_planes_custom = collect($fields)->filter(function ($value, $key) {
            return Str::contains($key, ['plan_custom_']);
        })->toArray();

        $fields = collect($fields)->filter(function ($value, $key) {
            return !Str::contains($key, ['plan_internet_']);
        });
        $fields = collect($fields)->filter(function ($value, $key) {
            return !Str::contains($key, ['plan_voz_']);
        });
        $fields = collect($fields)->filter(function ($value, $key) {
            return !Str::contains($key, ['plan_custom_']);
        });

        foreach ($bundle->planes_internet as $plan) {
            $service_model = $clientBundleService->service_internet->where('internet_id', $plan->id)->first();
            $keyed = collect($fields_planes_internet)->mapWithKeys(function ($item, $key) use ($plan, $service_model) {
                $item["value"] = $this->assignVal($service_model, $item, 'plan_internet_');
                return [$key . '_' . $plan->id => $item];
            })->map(function ($val) use ($plan, $service_model) {
                $val["field"] = $val["field"] . '_' . $plan->id;
                $val["partition"] = $val["partition"] . '_' . $plan->id;
                if ($val["inputs_depend"]) {
                    $val["inputs_depend"] = $this->objectToArray($val["inputs_depend"]);
                    foreach ($val["inputs_depend"] as $k => $v) {
                        $v["value"] = $this->assignVal($service_model, $v, 'plan_internet_');
                        $newK = $k . '_' . $plan->id;
                        $v["field"] = $newK;
                        $val["inputs_depend"][$newK] = $v;
                        unset($val["inputs_depend"][$k]);
                    }
                }
                return $val;
            })->toArray();
            $fields = $fields->merge($keyed);
        }

        foreach ($bundle->planes_voz as $plan) {
            $service_model = $clientBundleService->service_voz->where('voz_id', $plan->id)->first();
            $keyed = collect($fields_planes_voz)->mapWithKeys(function ($item, $key) use ($plan, $service_model) {
                $item["value"] = $this->assignVal($service_model, $item, 'plan_voz_');
                return [$key . '_' . $plan->id => $item];
            })->map(function ($val) use ($plan) {
                $val["field"] = $val["field"] . '_' . $plan->id;
                $val["partition"] = $val["partition"] . '_' . $plan->id;
                return $val;
            })->toArray();

            $fields = $fields->merge($keyed);
        }

        foreach ($bundle->planes_custom as $plan) {
            $service_model = $clientBundleService->service_custom->where('custom_id', $plan->id)->first();
            $keyed = collect($fields_planes_custom)->mapWithKeys(function ($item, $key) use ($plan, $service_model) {
                $item["value"] = $this->assignVal($service_model, $item, 'plan_custom_');
                return [$key . '_' . $plan->id => $item];
            })->map(function ($val) use ($plan, $clientAdditional, $service_model) {
                $field = str_replace('plan_custom_', '', $val["field"]);
                $val["field"] = $val["field"] . '_' . $plan->id;
                $val["partition"] = $val["partition"] . '_' . $plan->id;
                if ($plan->service_name == 'MovieNet') {
                    if ($field == 'user') {
                        $val["value"] = $clientAdditional->user_film;
                    }
                    if ($field == 'password') {
                        $val["value"] = $clientAdditional->password_film;
                    }
                }
                if ($val["inputs_depend"]) {
                    $val["inputs_depend"] = $this->objectToArray($val["inputs_depend"]);
                    foreach ($val["inputs_depend"] as $k => $v) {
                        $v["value"] = $this->assignVal($service_model, $v, 'plan_custom_');
                        $newK = $k . '_' . $plan->id;
                        $v["field"] = $newK;
                        $val["inputs_depend"][$newK] = $v;
                        unset($val["inputs_depend"][$k]);
                    }
                }
                $val["include"] = $this->showAdditionalFieldsWithCheckboxTrue($plan, $field, $val);
                return $val;
            })->toArray();
            $fields = $fields->merge($keyed);
        }
        return [
            'fields' => $fields->toArray(),
            'planes_internet' => $bundle->planes_internet,
            'planes_voz' => $bundle->planes_voz,
            'planes_custom' => $bundle->planes_custom,
        ];
    }


    public function showAdditionalFieldsWithCheckboxTrue($plan, $field, $val)
    {
        $fieldsCheckbox = ['user', 'password', 'router_id', 'mac', 'serial_number', 'bandwidth', 'cost_activation', 'cost_instalation'];
        foreach ($fieldsCheckbox as $fielD) {
            if ($field == 'user' && $plan->{'router_id_enable'} == true || $field == 'password' && $plan->{'router_id_enable'} == true) {
                $val["include"] = true;
            } else {
                if ($field == $fielD) {
                    $val["include"] = $plan->{$fielD . '_enable'};
                }
            }
        }

        return $val["include"];
    }


    public function assignVal($model, $item, $relation)
    {
        $field = Str::after($item["field"], $relation);
        return $model->$field ?? null;
    }

    public function getInputValidation($input, $clientBundleServiceId = null)
    {
        $data = [];
        $this->validateIfClientNameIsEqualOtherInInput($input);

        $fields_planes_internet = collect($input)->filter(function ($value, $key) {
            return Str::contains($key, ['plan_internet_']);
        })->map(function ($value, $key) use ($input, $clientBundleServiceId) {
            $id = Str::afterLast($key, '_');
            if ($clientBundleServiceId) {
                if (Str::contains($key, ['plan_internet_client_name_' . $id])) {
                    return $this->validateIfNameIsDisponible($input, $clientBundleServiceId, 'plan_internet_client_name_' . $id, 'plan_internet_router_id_' . $id);
                }
            }
            if (!$clientBundleServiceId) {
                if ($input['plan_internet_router_id_' . $id]) {
                    $this->validateIfNameExistInMikrotik('plan_internet_router_id_' . $id, 'plan_internet_client_name_' . $id, 'plan_internet_ipv4_assignment_' . $id, $input);
                }
            }

            if (Str::contains($key, ['plan_internet_router_id_' . $id, 'plan_internet_ipv4_assignment_' . $id, 'plan_internet_password_' . $id])) return 'required';
            if (Str::contains($key, ['plan_internet_client_name_' . $id]))  return 'required';
            if (Str::contains(
                $key,
                ['plan_internet_ipv4_' . $id]
            ) && $input['plan_internet_ipv4_assignment_' . $id] == 'IP Estatica') return 'required';
            if (Str::contains(
                $key,
                ['ipv4_pool']
            ) && $input['plan_internet_ipv4_assignment_' . $id] == 'Pool IP') return 'required';
            //validar en caso de que se esten creando 2 o mas servicios en el mismo momento
            if ($input['plan_internet_ipv4_' . $id]) {
                $this->isNetworkIpDisponible($input['plan_internet_ipv4_' . $id], $id, $clientBundleServiceId);
            }
            //si existe mas de un plan_internet_ipv4_ se debe validar que no se repita la ip
            if (Str::contains(
                $key,
                ['plan_internet_ipv4_']
            )) {
                $ip = $input['plan_internet_ipv4_' . $id];
                $routerId = $input['plan_internet_router_id_' . $id];

                $countRouters = collect($input)->filter(function ($value, $key) use ($routerId) {
                    return Str::contains($key, ['plan_internet_router_id_']) && $value == $routerId;
                })->count();
                if ($countRouters > 1) {
                    $count = collect($input)->filter(function ($value, $key) use ($ip) {
                        return Str::contains($key, ['plan_internet_ipv4_']) && !Str::contains($key, ['plan_internet_ipv4_assignment']) && $ip && $value == $ip;
                    })->count();

                    if ($count > 1) {
                        $errorMessages = [];
                        foreach ($input as $key => $value) {
                            if (Str::contains($key, ['plan_internet_ipv4_']) && $value == $ip) {
                                $id = Str::afterLast($key, '_');
                                $errorMessages['plan_internet_ipv4_' . $id] = ['Las direcciones IP no pueden ser iguales.'];
                            }
                        }

                        throw ValidationException::withMessages($errorMessages);
                    }
                }
            }
            return '';
        });

        $fields_planes_voz = collect($input)->filter(function ($value, $key) {
            return Str::contains($key, ['plan_voz_']);
        })->map(function ($value, $key) {
            if (Str::contains($key, ['router_id', 'ipv4_assignment', 'client_name', 'password', 'user'])) return 'required';
            return '';
        })->toArray();

        $fields_planes_custom = collect($input)->filter(function ($value, $key) {
            return Str::contains($key, ['plan_custom_']);
        })->map(function ($value, $key) use ($input, $clientBundleServiceId) {
            $id = Str::afterLast($key, '_');
            if ($clientBundleServiceId) {
                if (Str::contains($key, ['plan_custom_user_' . $id])) {
                    return $this->validateIfNameIsDisponible($input, $clientBundleServiceId, 'plan_custom_user_' . $id, 'plan_custom_router_id_' . $id);
                }
            }
            if (!$clientBundleServiceId) {
                if ($input['plan_custom_router_id_' . $id]) {
                    $this->validateIfNameExistInMikrotik('plan_custom_router_id_' . $id, 'plan_custom_user_' . $id, 'plan_custom_ipv4_assignment_' . $id, $input);
                }
            }

            if (Str::contains($key, ['plan_custom_router_id_' . $id, 'plan_custom_ipv4_assignment_' . $id, 'plan_custom_password_' . $id, 'plan_custom_user_' . $id])) {
                return $this->isEnableCheckForThisPlan($id);
            }

            if (Str::contains(
                $key,
                ['plan_custom_ipv4_' . $id]
            ) && $input['plan_custom_ipv4_assignment_' . $id] == 'IP Estatica') return 'required';
            if (Str::contains(
                $key,
                ['ipv4_pool']
            ) && $input['plan_custom_ipv4_assignment_' . $id] == 'Pool IP') return 'required';
            //validar en caso de que se esten creando 2 o mas servicios en el mismo momento
            if ($input['plan_custom_ipv4_' . $id]) {
                $this->isNetworkIpCustomDisponible($input['plan_custom_ipv4_' . $id], $id, $clientBundleServiceId);
            }
            //si existe mas de un plan_custom_ipv4_ se debe validar que no se repita la ip
            if (Str::contains(
                $key,
                ['plan_custom_ipv4_']
            )) {
                $ip = $input['plan_custom_ipv4_' . $id];
                $routerId = $input['plan_custom_router_id_' . $id];

                $countRouters = collect($input)->filter(function ($value, $key) use ($routerId) {
                    return Str::contains($key, ['plan_custom_router_id_']) && $value == $routerId;
                })->count();
                if ($countRouters > 1) {
                    $count = collect($input)->filter(function ($value, $key) use ($ip) {
                        return Str::contains($key, ['plan_custom_ipv4_']) && !Str::contains($key, ['plan_custom_ipv4_assignment']) && $ip && $value == $ip;
                    })->count();

                    if ($count > 1) {
                        $errorMessages = [];
                        foreach ($input as $key => $value) {
                            if (Str::contains($key, ['plan_custom_ipv4_']) && $value == $ip) {
                                $id = Str::afterLast($key, '_');
                                $errorMessages['plan_custom_ipv4_' . $id] = ['Las direcciones IP no pueden ser iguales.'];
                            }
                        }

                        throw ValidationException::withMessages($errorMessages);
                    }
                }
            }
            return '';
        });



        $fields_service_bundle = collect($input)->filter(function ($value, $key) {
            return Str::contains($key, ['bundle_']);
        })->map(function ($value, $key) {
            return '';
        })->toArray();
        $data = collect($data)->merge($fields_planes_internet)->merge($fields_planes_voz)->merge($fields_planes_custom)->merge($fields_service_bundle);

        return $data->toArray();
    }

    public function isNetworkIpDisponible($ip, $id, $clientBundleServiceId)
    {
        if ($clientBundleServiceId) {
            $networkIp = NetworkIp::find($ip);
            $ipsUsedByClientBundleService = $this->getNetworkIpUsedByClientBundleServiceId($clientBundleServiceId);
            if ($networkIp->used == ComunConstantsController::IS_NUMERICAL_TRUE && !in_array($ip, $ipsUsedByClientBundleService)) {
                return throw ValidationException::withMessages(['plan_internet_ipv4_' . $id => ['La direccion IP ya esta en uso.']]);
            }
        }
    }
    public function isNetworkIpCustomDisponible($ip, $id, $clientBundleServiceId)
    {
        $networkIp = NetworkIp::find($ip);
        $ipsUsedByClientBundleService = $this->bundleServiceRepository->getNetworkIpUsedByCustom($clientBundleServiceId);
        if ($networkIp->used == ComunConstantsController::IS_NUMERICAL_TRUE && !in_array($ip, $ipsUsedByClientBundleService)) {
            return throw ValidationException::withMessages(['plan_custom_ipv4_' . $id => ['La direccion IP ya esta en uso.']]);
        }
    }

    public function validateIfNameIsDisponible($input, $bundleId, $name, $routerId)
    {
        $clientBundleServiceRepository = new ClientBundleServiceRepository();
        $routerId = $input[$routerId];
        $clientId = $clientBundleServiceRepository->getClientIdByClientBundleServiceId($bundleId);


        $clientInternetServiceRepository = new ClientInternetServiceRepository();
        $clientInternetService = $clientInternetServiceRepository->getClientInternetServiceByClientBundleServiceId($bundleId);
        if ($clientId->client_id && $clientInternetService) {
            foreach ($clientInternetService as $clientService) {
                return [
                    Rule::unique('client_users', 'user')->where(function ($query) use ($clientId, $clientService, $routerId) {
                        return $query->where('client_id', '!=', $clientId->client_id)
                            ->where('router_id', '!=', $routerId)
                            ->where('service_id', '!=', $clientService->id);
                    }),
                ];
            }
        }

        $clientCustomServiceRepository = new ClientCustomServiceRepository();
        $clientCustomService = $clientCustomServiceRepository->getClientCustomServiceByClientBundleServiceId($bundleId);
        if ($clientId->client_id && $clientCustomService) {
            foreach ($clientCustomService as $clientService) {
                return [
                    'required',
                    Rule::unique('client_users', 'user')->where(function ($query) use ($clientId, $clientService, $routerId) {
                        return $query->where('client_id', '!=', $clientId->client_id)
                            ->where('router_id', '!=', $routerId)
                            ->where('service_id', '!=', $clientService->id);
                    }),
                ];
            }
        }
    }



    public function getNetworkIpUsedByClientBundleServiceId($clientBundleServiceId)
    {
        return $this->bundleServiceRepository->getNetworkIpUsed($clientBundleServiceId);
    }

    public function removeUnusedFieldsByIpv4AssignmentInPlanInput($plan_input)
    {
        if ($plan_input['ipv4_assignment'] == ClientInternetService::IPV4_ASSIGNMENT_STATIC) {
            $plan_input['ipv4_pool'] = null;
        } else {
            $plan_input['ipv4'] = null;
        }
    }

    public function objectToArray($object)
    {
        return collect($object)->map(function ($item, $key) {
            $res = [];
            foreach ($item as $k => $v) {
                $res[$k] = $v;
            }
            return $res;
        })->toArray();
    }


    public function validateIfClientNameIsEqualOtherInInput($input)
    {
        $arrayRouterIds = $this->filterAndMapArray($input, 'plan_internet_router_id_', 'plan_custom_router_id_');
        $arrayClientNames = $this->filterAndMapArray($input, 'plan_internet_client_name', 'plan_custom_user');

        $duplicateRouters = $this->getDuplicatesInArray($arrayRouterIds);
        $arrayNames = $this->getMatchingClientNames($duplicateRouters, $arrayClientNames);
        $duplicateNames = $this->getDuplicatesInArray($arrayNames);

        $arrayWithValidation = [];
        if (count($duplicateNames) > 1) {
            foreach ($duplicateNames as $key => $name) {
                $arrayWithValidation[$key] = ['No puede usar nombres repetidos para el mismo Router'];
            }
            throw ValidationException::withMessages($arrayWithValidation);
        }
    }

    public function filterAndMapArray($input, $key1, $key2)
    {
        return collect($input)
            ->filter(function ($value, $key) use ($key1, $key2) {
                return (strpos($key, $key1) !== false || strpos($key, $key2) !== false) && $value !== null;
            })
            ->mapWithKeys(function ($value, $key) {
                return [$key => $value];
            })
            ->all();
    }

    public function getMatchingClientNames($duplicateRouters, $arrayClientNames)
    {
        $arrayNames = [];
        foreach ($duplicateRouters as $key => $value) {
            $number = substr($key, strrpos($key, "_") + 1);
            $clientNameKey = 'plan_internet_client_name_' . $number;
            $customUserKey = 'plan_custom_user_' . $number;

            if (isset($arrayClientNames[$clientNameKey])) {
                $arrayNames[$clientNameKey] = $arrayClientNames[$clientNameKey];
            } elseif (isset($arrayClientNames[$customUserKey])) {
                $arrayNames[$customUserKey] = $arrayClientNames[$customUserKey];
            }
        }

        return $arrayNames;
    }

    public function getDuplicatesInArray($array)
    {
        $filteredArray = array_filter($array, function ($value) {
            return is_string($value) || is_int($value);
        });
        $counts = array_count_values(array_values($filteredArray));

        $duplicates = array_filter($array, function ($value) use ($counts) {
            return $counts[$value] > 1;
        });

        return $duplicates;
    }

    public function isEnableCheckForThisPlan($id)
    {
        $customRepository = new CustomRepository();
        $plan = $customRepository->getModelById($id);
        if ($plan->router_id_enable) {
            return 'required';
        } else {
            return '';
        }
    }


    public function changeBundle(Request $request, $id)
    {
        $clientBundleServiceRepository = new ClientBundleServiceRepository();
        $clientBundleService = $clientBundleServiceRepository->getServiceFilterById($id);

        if (!$clientBundleService) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        $input = $request->all();
        $clientId = $clientBundleService->client_id;

        $bundleId = $request->input('bundle_id');
        $bundleRepository = new BundleRepository();

        $serviceMappings = [
            'Internet' => [
                'repository' => new ClientInternetServiceRepository(),
                'currentServices' => 'getClientInternetServiceByClientBundleServiceId',
                'bundleServices' => 'getPlanesInternetByBundleId',
                'foreignKey' => 'internet_id'
            ],
            'Custom' => [
                'repository' => new ClientCustomServiceRepository(),
                'currentServices' => 'getClientCustomServiceByClientBundleServiceId',
                'bundleServices' => 'getPlanesCustomByBundleId',
                'foreignKey' => 'custom_id'
            ],
            'Voz' => [
                'repository' => new ClientVozServiceRepository(),
                'currentServices' => 'getServiceFilterByClientBundleServiceId',
                'bundleServices' => 'getPlanesVozByBundleId',
                'foreignKey' => 'voz_id'
            ]
        ];

        foreach ($serviceMappings as $serviceType => $config) {
            $currentServices = $config['repository']->{$config['currentServices']}($id);
            $bundleServices = $bundleRepository->{$config['bundleServices']}($bundleId);

            $this->syncServices($currentServices, $bundleServices, $config['foreignKey'], $clientBundleService->id);
        }

        $newBundle = $this->createOrUpdateBundleService($input, $clientId, $id);

        // Log de cambios
        $logService = new LogService();
        $clientBundleService->load('client');

        $logService->log(
            $clientBundleService->client,
            "Se le cambia el Paquete: {$clientBundleService->description} por el Paquete {$newBundle->description} desde el ClientBundleServiceController::changeBundle por el usuario " . auth()->user()->name
        );

        $logService->log(
            $clientBundleService->client,
            "Se elimina el Paquete anterior ({$clientBundleService->description}) desde el ClientBundleServiceController::changeBundle por el usuario " . auth()->user()->name
        );

        return $clientBundleService;
    }

    /**
     * Sincroniza los servicios actuales con los nuevos planes.
     *
     * @param Collection $currentServices
     * @param Collection $bundleServices
     * @param string $foreignKey
     * @param int $newBundleId
     */
    private function syncServices($currentServices, $bundleServices, $foreignKey, $newBundleId)
    {
        if ($bundleServices->isNotEmpty()) {
            foreach ($bundleServices as $key => $plan) {
                if (isset($currentServices[$key])) {
                    $currentServices[$key]->client_bundle_service_id = $newBundleId;
                    $currentServices[$key]->$foreignKey = $plan->id;
                    $currentServices[$key]->save();
                }
            }
        } else {
            foreach ($currentServices as $service) {
                $service->delete();
            }
        }
    }


    public function getEqualsPlansById(Request $request, $bundleId)
    {
        $module = Module::where('name', 'ClientBundleService')->first();
        $fields = $module->getfields();
        $bundle = Bundle::find($bundleId)->load(['planes_internet', 'planes_voz', 'planes_custom']);

        $bundleRepository = new BundleRepository();
        $arrayCountsBundleActual = $bundleRepository->getCountOptionsByBundle($bundleId);

        $bundleRepository = new BundleRepository();
        $similarBundles = $bundleRepository->getSimilarBundlesByCountPlanes($arrayCountsBundleActual, $bundleId);

        $optionsBundleId = $similarBundles->pluck('title', 'id')->toArray();

        $fields["bundle_id"]["options"] = $optionsBundleId;
        unset($fields["bundle_id"]["search"]);

        $fields["bundle_id"]["value"] = $bundleId;
        // bundle_service_option
        $fields["bundle_description"]["value"] = $bundle->service_description;
        $fields["bundle_price"]["value"] = $bundle->price;
        if ($bundle->cost_instalation_enable == 1) {
            $fields["bundle_cost_instalation"]["value"] = $bundle->cost_instalation ?? 0;
        } else {
            unset($fields["bundle_cost_instalation"]);
        }

        return [
            'fields' => $fields,
        ];
    }


    public function getPlansToChangeById($serviceBundleId)
    {
        $clientBundleService = ClientBundleService::find($serviceBundleId)->load(['service_internet', 'service_voz', 'service_custom']);
        $bundleId = $clientBundleService->bundle_id;

        $module = Module::where('name', 'ClientBundleService')->first();
        $fields = $module->getfields();
        $bundle = Bundle::find($bundleId)->load(['planes_internet', 'planes_voz', 'planes_custom']);

        $bundleRepository = new BundleRepository();
        $arrayCountsBundleActual = $bundleRepository->getCountOptionsByBundle($bundleId);

        $bundleRepository = new BundleRepository();
        $similarBundles = $bundleRepository->getSimilarBundlesByCountPlanes($arrayCountsBundleActual, $bundleId);

        $optionsBundleId = $similarBundles->pluck('title', 'id')->toArray();

        $fields["bundle_id"]["options"] = $optionsBundleId;
        unset($fields["bundle_id"]["search"]);

        $fields["bundle_id"]["value"] = $bundleId;
        // bundle_service_option
        $fields["bundle_description"]["value"] = $bundle->service_description;
        $fields["bundle_price"]["value"] = $bundle->price;
        $fields["bundle_automatic_renewal"]["value"] = $clientBundleService->automatic_renewal;
        if ($bundle->active_instalation_cost == 1) {
            $fields["bundle_instalation_cost"]["value"] = $bundle->instalation_cost ?? 0;
        } else {
            unset($fields["bundle_instalation_cost"]);
        }

        return [
            'fields' => $fields,
        ];
    }
}

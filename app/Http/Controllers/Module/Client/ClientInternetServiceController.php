<?php

namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Requests\module\client\ClientInternetServiceCreateRequest;
use App\Models\ClientInternetService;
use App\Http\HelpersModule\module\client\ClientInternetServiceDatatableHelper;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Http\Repository\ClientUserRepository;
use App\Http\Repository\InternetRepository;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Requests\module\client\ClientInternetServiceUpdateRequest;
use Illuminate\Http\Request;
use App\Http\Traits\RouterConnection;
use App\Jobs\CreateClientWithServiceJob;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Jobs\NetworkIp\SetIPToClientInternetServiceJob;
use App\Models\Internet;
use App\Models\Module;
use App\Services\ClientService\ClientInternetService as ClientServiceClientInternetService;
use Illuminate\Support\Facades\Log;
use App\Services\ImportdDBService;
use App\Services\LogService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClientInternetServiceController extends Controller
{
    use RouterConnection;

    private $helper;

    public function __construct(ClientInternetServiceDatatableHelper $helper)
    {
        $model = 'ClientInternetService';
        $this->data['url'] = 'meganet.module' . $model;
        $this->data['module'] = 'ClientInternetService';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->helper = $helper;
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
    public function store(ClientInternetServiceCreateRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = $request->all();
        $input['client_id'] = $id;
        //TODO arreglar que desde la migracion obtenga  0 por defecto
        $input['cost_activation'] = $input['cost_activation'] == null ? 0 : $input['cost_activation'];
        if (array_key_exists('cost_instalation', $input)) {
            unset($input['cost_instalation']);
        }
        if (isset($request->router_id) && !$request->import) {
            $this->validateIfNameExistInMikrotik($request->router_id, $request->client_name, $request->ipv4_assignment, $input);
        }

        $internetRepository = new InternetRepository();
        $internet = $internetRepository->getModelFilterById($input['internet_id']);

        $input['price'] = $internet->price ? $internet->price : null;

        if ($input['price'] == null) {
            $input['price'] = 0;
        }
        $input['user'] = $input['client_name'];

        if ($request->import) {
            $this->imporDataToTable($input);
        } else {
            $model = $this->data['model']::create($input);
            return $model;
        }
    }

    public function imporDataToTable($input)
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $newImportDbService = new ImportdDBService();
        $module = Module::where('name', Module::CLIENT_INTERNET_SERVICE_MODULE_NAME)->first();
        $input = $newImportDbService->processInputImportByModule($input, $module);
        $input['client_name'] = $input['user'];
        $input['discount'] = $input['discount'] ?? 0;
        $input['created_at'] = now();
        $input['updated_at'] = now();
        $input['pay_period'] = $input['pay_period'] ?? 'Periodo 1';

        $input['amount'] = 1;
        $input['unity'] = 1;

        $internetPlan = Internet::where('id', $input['internet_id'])->first();
        if ($internetPlan) {
            $price = $internetPlan->price;
        } else {
            $price = 0;
        }
        $input['price'] = $price;
        if ($input['estado'] == 'active') {
            $input['estado'] = ComunConstantsController::STATE_ACTIVE;
        } else {
            $input['estado'] = 'Desactivado';
        }

        unset($input['import']);

        if ($input['ipv4_assignment'] == 'IP Estatica') {
            $networkIpRepository = new NetworkIpRepository();
            $networkIp = $networkIpRepository->getNetworkIpByIp($input['ipv4']);
            if ($networkIp) {
                $input['ipv4'] = $networkIp->id;
            } else {
                Log::info(['No Hubo id para este Ip ' => $input['ipv4']]);
            }
        }

        $input['id'] = $input['id_old'];
        unset($input['id_old']);

        if (isset($input['client_bundle_service_id']) && !empty($input['client_bundle_service_id'])) {
            $clientBundleServiceRepository = new ClientBundleServiceRepository();
            $clientBundleService = $clientBundleServiceRepository->getServiceFilterByClientId($input['client_id']);
            if ($clientBundleService) {
                $input['client_bundle_service_id'] = $clientBundleService->id;
                $input['start_date'] = null;
                $input['finish_date'] = null;
            } else {
                Log::info('No existe el Paquete Padre para este client: ' . $input['client_id']);
                $input['start_date'] = null;
                $input['finish_date'] = null;
            }
        } else {
            $input['start_date'] = $this->formatearFecha($input['start_date']);
        }


        DB::table('client_internet_services')->insert($input);
        $model = $this->data['model']::where('id', $input['id'])->first();

        $this->assignUsedByToNetworkIp($model);

        $clientUserRepository = new ClientUserRepository();
        $clientUserRepository->create($model->client_name, $model->router_id, $model->id, $model->client_id);
        return $model;
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function formatearFecha($date)
    {
        if (empty($date)) {
            return '00-00-00';
        }
        $fechaCarbon = Carbon::createFromFormat('m/d/Y', $date);
        $fechaFormateada = $fechaCarbon->format('Y-m-d');
        return $fechaFormateada;
    }

    public function assignUsedByToNetworkIp($model)
    {
        if ($model->ipv4_assignment == 'IP Estatica') {
            $type = class_basename(get_class($model));
            $networkIpRepository = new NetworkIpRepository();
            $ip = $networkIpRepository->getNetworkIpById($model->ipv4);
            $networkIpRepository->update($ip, [
                'used_by' => $model->id,
                'type_service' => $type,
                'used' => true,
                'client_id' => $model->client_id
            ]);
        } else {
            $type = class_basename(get_class($model));
            $networkIpRepository = new NetworkIpRepository();
            $pool_id = $model->ipv4_pool;
            $router_id = $model->router_id;

            $ipPool = $networkIpRepository->getPoolIp($pool_id, $router_id);
            $networkIpRepository->update($ipPool, [
                'used_by' => $model->id,
                'type_service' => $type,
                'used' => true,
                'client_id' => $model->client_id
            ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\ClientInternetService $clientInternetService
     * @return \Illuminate\Http\Response
     */
    public function show(ClientInternetService $clientInternetService)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\ClientInternetService $clientInternetService
     * @return \Illuminate\Http\Response
     */
    public function edit(ClientInternetService $clientInternetService)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\ClientInternetService $clientInternetService
     * @return \Illuminate\Http\Response
     */
    public function update(ClientInternetServiceUpdateRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::find($id);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        unset($input['price']);
        $input['user'] = $input['client_name'];
        if (array_key_exists('cost_instalation', $input)) {
            unset($input['cost_instalation']);
        }
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');


        $updatedService = null;
        if ($this->changeRouter($model, $input)) {
            $updatedService = $this->updateIpClientAndService($model, $input);
        }
        if ($this->changeIpv4($model, $input)) {
            $updatedService = $this->updateIpv4Service($model, $input);
        }
        if ($updatedService) {
            return $updatedService;
        }
        $model = $model->update($input);

        return $model;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\ClientInternetService $clientInternetService
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->data['model']::findOrFail($id)->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }

    public function changeInternet(Request $request, $id)
    {
        $this->validate($request, [
            'internet_id' => 'required',
        ]);

        $input = $request->all();
        $model = $this->data['model']::find($id);

        if (!$model) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        $descriptionOld = $model->description;
        $internetRepository = new InternetRepository();

        // Deshabilitar eventos para evitar 'updating'
        Model::withoutEvents(function () use ($model, $input, $internetRepository) {
            $model->price = $internetRepository->getPriceById($input['internet_id']);
            $model->internet_id = $input['internet_id'];
            $model->description = $input['description'];
            $model->amount = $input['amount'];
            $model->unity = $input['unity'];
            $model->pay_period = $input['pay_period'];
            $model->start_date = $input['start_date'];
            $model->finish_date = $input['finish_date'];
            $model->discount = $input['discount'];
            $model->discount_percent = $input['discount_percent'];
            $model->start_date_discount = $input['start_date_discount'];
            $model->end_date_discount = $input['end_date_discount'];
            $model->discount_message = $input['discount_message'];
            $model->estado = $input['estado'];
            $model->save();
        });

        // Registrar el cambio en el log
        $logService = new LogService();
        $logService->log(
            $model->client,
            'Plan de Internet cambiado de ' . $descriptionOld . ' a ' . $input['description'] . ' por el usuario ' . auth()->user()->name
        );

        return $model;
    }


    public function refreshIp($id)
    {
        $model = $this->data['model']::find($id);
        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al procesar la solicitud',
            ], 500);
        }
        try {
            $typeIp = $model->ipv4_assignment;
            if ($typeIp == ComunConstantsController::IPV4_ASSIGNMENT_POOL_IP) {
                $ipOld = $model->network_ip_used_by->ip ?? null;
                MikrotikRemoveClientServiceFromAddressList::dispatch($model);
                $this->liberaLaIpUsada($model);
                SetIPToClientInternetServiceJob::dispatch($model, $ipOld);
                $model->fresh();
                CreateClientWithServiceJob::dispatch($model);
                $clientInternetServiceService = new ClientServiceClientInternetService($model);
                $clientInternetServiceService->verifyIsClientActiveOrNotAndRectifyMikrotik();

                return response()->json([
                    'success' => true,
                    'message' => 'Se ha actualizado la ip del servicio .'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrio un error al procesar la solicitud. La ip que tiene el servicio es estatica.',
                ], 500);
            }
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al procesar la solicitud',
            ], 500);
        }
    }
}

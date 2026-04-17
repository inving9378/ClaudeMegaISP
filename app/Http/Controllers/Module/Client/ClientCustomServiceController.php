<?php

namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\HelpersModule\module\client\ClientCustomServiceDatatableHelper;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Http\Repository\ClientUserRepository;
use App\Http\Repository\CustomRepository;
use App\Http\Repository\InternetRepository;
use App\Http\Repository\RouterRepository;
use App\Http\Requests\module\client\ClientCustomServiceCreateRequest;
use App\Http\Traits\RouterConnection;
use App\Jobs\NetworkIp\SetIPToClientInternetServiceJob;
use App\Models\ClientCustomService;
use App\Models\Module;
use App\Services\ImportdDBService;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Services\LogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ClientCustomServiceController extends Controller
{
    use RouterConnection;

    private $helper;

    public function __construct(ClientCustomServiceDatatableHelper $helper)
    {
        $model = 'ClientCustomService';
        $this->data['url'] = 'meganet.module' . $model;
        $this->data['module'] = 'ClientCustomService';
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
    public function store(ClientCustomServiceCreateRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = $request->all();

        $input['client_id'] = $id;
        if (isset($request->router_id) && !$request->import) {
            $this->validateIfNameExistInMikrotik($request->router_id, $request->user, $request->ipv4_assignment, $input);
        }

        $customRepository = new CustomRepository();
        $price = $customRepository->getModelById($input['custom_id'])->price ?? 0;
        $input['price'] = $price;
        if (array_key_exists('cost_instalation', $input)) {
            unset($input['cost_instalation']);
        }

        if (array_key_exists('ip', $input)) {
            unset($input['ip']);
        }
        if ($request->import) {
            $this->imporDataToTable($input);
        } else {
            $model = $this->data['model']::create($input);
            $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);
            return $model;
        }
    }

    public function imporDataToTable($input)
    {
        $newImportDbService = new ImportdDBService();
        $module = Module::where('name', Module::CLIENT_CUSTOM_SERVICE_MODULE_NAME)->first();
        $input = $newImportDbService->processInputImportByModule($input, $module);
        $input['discount'] = $input['discount'] ?? 0;
        $input['created_at'] = now();
        $input['updated_at'] = now();

        $input['amount'] = 1;
        $input['unity'] = 1;

        $input['id'] = $input['id_old'];
        unset($input['id_old']);

        $input['pay_period'] = 'Periodo 1';

        if (isset($input['client_bundle_service_id']) && !empty($input['client_bundle_service_id'])) {
            $clientBundleServiceRepository = new ClientBundleServiceRepository();
            $clientBundleService = $clientBundleServiceRepository->getServiceFilterByClientId($input['client_id']);
            if ($clientBundleService) {
                $input['client_bundle_service_id'] = $clientBundleService->id;
                $input['start_date'] = '00-00-00';
                $input['finish_date'] = '00-00-00';
            } else {
                Log::info('No existe el Paquete Padre para el Servicio de Voz para este client: ' . $input['client_id']);
                return null;
            }
        } else {
            $input['start_date'] = $this->formatearFecha($input['start_date']);
            $input['finish_date'] = $this->formatearFecha($input['finish_date']);
        }

        $customRepository = new CustomRepository();
        $price = $customRepository->getModelById($input['custom_id'])->price ?? 0;
        $input['price'] = $price;
        unset($input['import']);
        $idModel = DB::table('client_custom_services')->insertGetId($input);
        $model = $this->data['model']::where('id', $idModel)->first();
        if ($input['router_id'] != null) {
            SetIPToClientInternetServiceJob::dispatch($model);
            $clientUserRepository = new ClientUserRepository();
            $clientUserRepository->create($model->user, $model->router_id, $model->id, $model->client_id);
        }

        return $model;
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

    /**
     * Display the specified resource.
     *
     * @param \App\Models\ClientCustomService $clientInternetService
     * @return \Illuminate\Http\Response
     */
    public function show(ClientCustomService $clientInternetService)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\ClientCustomService $clientInternetService
     * @return \Illuminate\Http\Response
     */
    public function edit(ClientCustomService $clientInternetService)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\ClientCustomService $clientInternetService
     * @return \Illuminate\Http\Response
     */
    public function update(ClientCustomServiceCreateRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::find($id);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();

        unset($input['price']);
        if ($this->changeRouter($model, $input)) {
            $this->updateIpClientAndService($model, $input);
        } else if ($this->changeIpv4($model, $input)) {
            $this->updateIpv4Service($model, $input);
        } else {
            $model = $model->update($input);
        }
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
        return $model;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\ClientCustomService $clientInternetService
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

    public function changeCustom(Request $request, $id)
    {
        $this->validate($request, [
            'custom_id' => 'required',
        ]);

        $input = $request->all();
        $model = $this->data['model']::find($id);
        if (!$model) {
            return response()->json(['error' => 'Service not found'], 404);
        }
        $customRepository = new CustomRepository();
        $descriptionOld = $model->service_name;
        $model->custom_id = $input['custom_id'];
        $model->service_name = $customRepository->getTitleById($input['custom_id']);
        $model->description = $input['description'];
        $model->amount = $input['amount'];
        $model->unity = $input['unity'];
        $model->pay_period = $input['pay_period'];
        $model->start_date = $input['start_date'];
        $model->estado = $input['estado'];
        $model->payment_type = $input['payment_type'];

        $model->price = $customRepository->getPriceById($input['custom_id']);
        $model->save();
        $model->refresh();
        $logService = new LogService();
        $model->load('client');
        $logService->log($model->client, 'Plan Custom cambiado de ' . $descriptionOld . ' a ' . $model->description . ' por el usuario ' . auth()->user()->name);
        return $model;
    }
}

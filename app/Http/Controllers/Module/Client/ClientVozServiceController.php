<?php


namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientVozService;
use App\Http\HelpersModule\module\client\ClientVozServiceDatatableHelper;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Http\Repository\VoiseRepository;
use App\Http\Requests\module\client\ClientVozServiceCreateRequest;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Module;
use App\Models\Voise;
use App\Services\ImportdDBService;
use App\Services\LogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientVozServiceController extends Controller
{
    private $helper;

    public function __construct(ClientVozServiceDatatableHelper $helper)
    {
        $model = 'ClientVozService';
        $this->data['url'] = 'meganet.module' . $model;
        $this->data['module'] = 'ClientVozService';
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
    public function store(ClientVozServiceCreateRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = $request->all();
        $input['client_id'] = $id;

        if (array_key_exists('cost_instalation', $input)) {
            unset($input['cost_instalation']);
        }

        if ($request->import) {
            $this->imporDataToTable($input, $request);
        } else {
            $model = $this->data['model']::create($input);
            return $model;
        }
    }

    public function imporDataToTable($input, $request)
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $newImportDbService = new ImportdDBService();
        $module = Module::where('name', Module::CLIENT_VOZ_SERVICE_MODULE_NAME)->first();
        $input = $newImportDbService->processInputImportByModule($input, $module);
        $input['discount'] = $input['discount'] ?? 0;
        $input['created_at'] = now();
        $input['updated_at'] = now();
        $input['amount'] = 1;
        $input['unity'] = 1;
        $input['pay_period'] = $request->pay_period ?? 'Periodo 1';

        $input['id'] = $input['id_old'];
        unset($input['id_old']);

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

        $voiseRepository = new VoiseRepository();
        $price = $voiseRepository->getModelFilterById($input['voz_id'])->price ?? 0;
        $input['price'] = $price;
        unset($input['import']);
        $idModel = DB::table('client_voz_services')->insertGetId($input);
        $model = $this->data['model']::where('id', $idModel)->first();
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

    /**
     * Display the specified resource.
     *
     * @param \App\Models\ClientInternetService $clientInternetService
     * @return \Illuminate\Http\Response
     */
    public function show(ClientVozService $clientInternetService)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\ClientInternetService $clientInternetService
     * @return \Illuminate\Http\Response
     */
    public function edit(ClientVozService $clientInternetService)
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
    public function update(ClientVozServiceCreateRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::find($id);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        unset($input['price']);
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
        return $model->update($input);
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


    public function changeVoz(Request $request, $id)
    {
        $this->validate($request, [
            'voz_id' => 'required',
        ]);

        $input = $request->all();
        $model = $this->data['model']::find($id);
        if (!$model) {
            return response()->json(['error' => 'Service not found'], 404);
        }
        $descriptionOld = $model->description;
        $model->voz_id = $input['voz_id'];
        $model->description = $input['description'];
        $model->amount = $input['amount'];
        $model->unity = $input['unity'];
        $model->pay_period = $input['pay_period'];
        $model->start_date = $input['start_date'];
        $model->finish_date = $input['finish_date'];
        $model->discount = $input['discount'];
        $model->estado = $input['estado'];

        $voiseRepository = new VoiseRepository();
        $model->price = $voiseRepository->getPriceById($input['voz_id']);
        $model->save();

        $logService = new LogService();
        $model->refresh();
        $model->load('client');
        $logService->log($model->client, 'Plan de Voz cambiado de ' . $descriptionOld . ' a ' . $model->description . ' por el usuario ' . auth()->user()->name);
        return $model;
    }
}

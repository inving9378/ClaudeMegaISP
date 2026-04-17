<?php

namespace App\Http\Controllers\Module\Network;


use App\Jobs\CreateNetWorkIpRowsJob;
use App\Models\Network;
use App\Models\NetworkIp;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Http\HelpersModule\module\network\NetworkDatatableHelper;
use App\Http\Requests\module\network\NetworkCreateRequest;
use App\Http\Requests\module\network\NetworkUpdateRequest;
use App\Http\Controllers\Module\Network\Ipv4CalculatorController;
use App\Models\Module;
use App\Services\ImportdDBService;
use Illuminate\Support\Facades\DB;

class NetworkController extends Controller
{
    private $helper;

    public function __construct(NetworkDatatableHelper $helper)
    {
        $model = 'Network';
        $this->data['url'] = 'meganet.module.' . Str::lower($model);
        $this->data['module'] = $model;
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'network';
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
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function success()
    {
        $message = 'Grupo de Ip Creado Correctamente';
        return redirect('/red/ipv4/listar')->with(['message' => $message]);
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

    public function store(NetworkCreateRequest $request)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();


        if ($request->import) {
            $this->imporDataToTable($input, $request);
        } else {
            $model = $this->data['model']::create($input);
            $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);

            $Ipv4Calculator = new Ipv4CalculatorController();
            $ips = $Ipv4Calculator->createIpInNetwork($request->network, $request->bm);

            //TODO agregar pasar a segundo plano.
            CreateNetWorkIpRowsJob::dispatchAfterResponse($model, $ips);

            return $model;
        }
    }


    public function imporDataToTable($input, $request)
    {
        $newImportDbService = new ImportdDBService();
        $module = Module::where('name', Module::NETWORK_MODULE_NAME)->first();
        $input = $newImportDbService->processInputImportByModule($input, $module);
        $input['id'] = $request->id_old;

        unset($input['import']);
        unset($input['id_old']);
        $idModel = DB::table('networks')->insertGetId($input);
        $model = $this->data['model']::where('id', $idModel)->first();
        return $model;
    }


    public function update(NetworkUpdateRequest $request, $id)
    {
        try {
            $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
            $model = $this->data['model']::find($id);
            $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
                $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
                $request->all();
            $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
            $model = $model->update($input);
            return response()->json([
                'success' => true,
                'message' => 'Los datos se ha actualizado con éxito.',
                'model' => $model
            ], 200);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $networkIps = NetworkIp::where('network_id', $id)->get();
            $matchedUsed = $networkIps->where('used', 1)->whereNotNull('client_id')->count();
            if ($matchedUsed > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el grupo de ip porque existen clientes que tiene ips asignadas',
                ], 400);
            }
            foreach ($networkIps as $networkIp) {
                $networkIp->delete();
            }
            $this->data['model']::findOrFail($id)->delete();
            return response()->json([
                'success' => true,
                'message' => 'Los datos se han eliminado con éxito.',
            ], 200);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
            ], 500);
        }
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function getIpByNetwork(Request $request, $network_id)
    {
        $offset = 0;
        $limit = 50;
        if (isset($request->page) && $request->page > 0) {
            $offset = ($request->page - 1) * $limit;
        }

        return NetworkIp::where('network_id', $network_id)
            ->skip($offset)
            ->take($limit)
            ->get();
    }
}

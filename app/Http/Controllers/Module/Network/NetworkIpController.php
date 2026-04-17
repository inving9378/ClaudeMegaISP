<?php

namespace App\Http\Controllers\Module\Network;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Http\HelpersModule\module\network\NetworkIpDatatableHelper;
use App\Http\Requests\module\network\NetworkIpCreateRequest;
use App\Http\Requests\module\network\NetworkIpUpdateRequest;
use App\Models\Module;
use App\Services\ImportdDBService;
use Illuminate\Support\Facades\DB;

class NetworkIpController extends Controller
{
    private $helper;

    public function __construct(NetworkIpDatatableHelper $helper)
    {
        $model = 'NetworkIp';
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

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;

        return view($this->data['url'] . '.ver', $this->data);
    }

    public function success()
    {
        return redirect('/red/ipv4/listar');
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

    public function store(NetworkIpCreateRequest $request)
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
            return $model;
        }
    }

    public function imporDataToTable($input, $request)
    {
        $newImportDbService = new ImportdDBService();
        $module = Module::where('name', Module::NETWORK_MODULE_NAME)->first();
        $input = $newImportDbService->processInputImportByModule($input, $module);
        $input['used_by'] = $input['used_by'] ?? '--';
        if ($input['client_id'] != null) {
            $input['host_category'] = 'Customer';
        } else {
            $input['host_category'] = 'Ninguno';
        }
        unset($input['import']);
        $idModel = DB::table('network_ips')->insertGetId($input);
        $model = $this->data['model']::where('id', $idModel)->first();
        return $model;
    }


    public function update(NetworkIpUpdateRequest $request, $id)
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

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->data['model']::findOrFail($id)->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }
}

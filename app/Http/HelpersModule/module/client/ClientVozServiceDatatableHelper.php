<?php


namespace App\Http\HelpersModule\module\client;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Http\Repository\ClientVozServiceRepository;
use App\Http\Repository\VoiseRepository;
use App\Models\ClientVozService;
use App\Models\Module;
use App\Models\Voise;
use Illuminate\Support\Arr;

class ClientVozServiceDatatableHelper extends HelperDatatable
{
    private $model;
    private $columns;
    public function __construct()
    {
        $this->model = ClientVozService::class;
        $moduleName = 'ClientVozService';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
    }


    public function count($idModule)
    {
        return $this->model::where('client_id', $idModule)
            ->count();
    }


    public function ordering_query($start, $limit, $order, $dir, $idModule)
    {
        return $this->model::select('*')
            ->where('client_id', $idModule)
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $idModule)
    {
        return $this->model::filters($this->columns, $search)
            ->select('*')
            ->where('client_id', $idModule)
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }


    public function filtering_query($search, $idModule)
    {
        return $this->model::filters($this->columns, $search)
            ->where('client_id', $idModule)
            ->count();
    }

    public function transform($request)
    {
        $data = array();
        $type_modal_edit = $this->includeButtonEditTypeModalIfIsRequested($request)
            ? '_type_modal' : '';

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    if ($val == 'service_name') {
                        $voiseRepository = new VoiseRepository();
                        $nestedData[$val] = $voiseRepository->getModelFilterById($value->voz_id)->service_name ?? '';
                    } else {
                        $nestedData[$val] = $value->$val;
                    }
                }
                $info = [
                    'id' => $id,
                    'module' => 'ClientVozService',
                    'group' => 'client_service_voz',
                    'submodule' => 'client'
                ];

                if (!$value->client_bundle_service_id) {
                    $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                    $nestedData['action'] = view('meganet.shared.table.module.client.client_voz_service.actions_type_modal', $info)->toHtml();
                }
                $data[] = $nestedData;
            }
        }

        return [
            "draw" => intval($request['request']->input('draw')),
            "recordsTotal" => intval($request['totalData']),
            "recordsFiltered" => intval($request['totalFiltered']),
            "data" => $data
        ];
    }

    public function includeButtonEditTypeModalIfIsRequested($request)
    {
        return isset($request['request']->modal);
    }
}

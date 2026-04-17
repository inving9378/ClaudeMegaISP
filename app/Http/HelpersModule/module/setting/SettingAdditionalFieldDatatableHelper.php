<?php


namespace App\Http\HelpersModule\module\setting;

use App\Http\Repository\ModuleRepository;
use App\Models\FieldModule;
use App\Models\SettingDebtPaymentClientCustom;
use App\Models\Module;
use Illuminate\Support\Arr;

class SettingAdditionalFieldDatatableHelper
{
    private $model, $columns;

    public function __construct()
    {
        $this->model = FieldModule::class;
        $moduleName = 'FieldModule';
        $moduleRepository = new ModuleRepository();
        $this->columns = $moduleRepository->getColumnsByModuleName($moduleName);
    }

    public function fetch_datatable_data($request)
    {
        $idModule = null;
        if (isset($request->data['idModule']) && $request->data['idModule'] != null) {
            $idModule = $request->data['idModule'];
            $totalData = $this->count($idModule);
        } else {
            $totalData = $this->count();
        }

        $totalFiltered = $totalData;

        $limit = $request->limits == 0 ? $totalFiltered : $request->limits;
        $start = 0;
        $order =  $request->order ?? $request->data['columns'][0]['data'];
        $dir = $request->dir == true ? 'desc' : 'asc';
        $array = [];

        if (isset($request->data['filters']) && !empty($request->data['filters'])) {
            $jsonFilters = $request->data['filters']['module_id'];
            $filters = json_decode($jsonFilters, true);
            $filtersByModuleId = $filters;
            $array = $this->get_data_filtered_by_module_id($start, $limit, $order, $dir, $filtersByModuleId);
            $totalFiltered = $this->filtering_query($filtersByModuleId);
        }

        $param_resource = collect(['array' => $array, 'totalData' => $totalData, 'totalFiltered' => $totalFiltered, 'request' => $request]);

        return $this->transform($param_resource);
    }

    public function count()
    {
        return $this->model::count();
    }

    public function ordering_query($start, $limit, $order, $dir)
    {
        return $this->model::select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy('position', 'asc')
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search)
    {
        return $this->model::filters($this->columns, $search)
            ->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy('position', $dir)
            ->get();
    }

    public function get_data_filtered_by_module_id($start, $limit, $order, $dir, $filtersByModuleId, $search = null)
    {
        return $this->model::filtersByModuleId($this->columns, $filtersByModuleId, $search)
            ->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy('position', $dir)
            ->get();
    }

    public function filtering_query($search)
    {
        return $this->model::filters($this->columns, $search)
            ->count();
    }

    public function transform($request)
    {
        $data = array();

        $type_modal_edit = $this->includeButtonEditTypeModalIfIsRequested($request)
            ? '_type_modal' : '';

        $translateModules = trans('translation_modules');
        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    if ($val == 'module_id') {
                        $moduleRepository = new ModuleRepository();
                        $nestedData[$val] = $translateModules[$moduleRepository->getModuleById($value->module_id)->name]  ?? "";
                    } else {
                        $nestedData[$val] = $value->$val;
                    }
                }

                $info = [
                    'id' => $id,
                    'module' => 'FieldModule',
                    'group' => 'fieldmodule',
                    'submodule' => 'fieldmodule'
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);

                $nestedData['position'] = view('meganet.shared.table.module.setting_additional_field.position', [
                    'id' => $id,
                    'position' => $value->position
                ])->toHtml();

                $nestedData['action'] = view('meganet.shared.table.actions' . $type_modal_edit, $info)->toHtml();
                $data[] = $nestedData;
            }
            return [
                "draw" => intval($request['request']->input('draw')),
                "recordsTotal" => intval($request['totalData']),
                "recordsFiltered" => intval($request['totalFiltered']),
                "data" => $data
            ];
        }

        return [
            "draw" => intval($request['request']->input('draw')),
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => $data
        ];
    }

    public function includeButtonEditTypeModalIfIsRequested($request)
    {
        return isset($request['request']->modal);
    }
}

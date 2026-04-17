<?php


namespace App\Http\HelpersModule\module\setting\tools;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Http\Repository\ModuleRepository;
use App\Http\Repository\UserRepository;
use App\Models\SettingToolsImport;
use Illuminate\Support\Arr;

class SettingTollsImportDatatableHelper extends HelperDatatable
{
    private $model, $columns;

    public function __construct()
    {
        $this->model = SettingToolsImport::class;
        $moduleName = 'SettingToolsImport';
        $moduleRepository = new ModuleRepository();
        $this->columns = $moduleRepository->getColumnsByModuleName($moduleName);
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
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search)
    {
        return $this->model::filters($this->columns, $search)
            ->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
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

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    if ($val == 'module_id') {
                        $moduleRepository = new ModuleRepository();
                        $translateModules = trans('translation_modules');
                        $translateModule = $moduleRepository->getModuleById($value->$val)->name;
                        $nestedData[$val] = $translateModules[$translateModule] ?? $translateModule;
                    } else if ($val == 'created_by') {
                        $userRepository = new UserRepository();
                        $nestedData[$val] = $userRepository->getUserById($value->$val)->name;
                    } else {
                        $nestedData[$val] = $value->$val;
                    }
                }
                $info = [
                    'id' => $id,
                    'module' => 'SettingToolsImport',
                    'group' => 'settingToolsImport',
                    'submodule' => 'settingToolsImport'
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['action'] = view('meganet.shared.table.action_delete', $info)->toHtml();
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

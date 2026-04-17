<?php


namespace App\Http\HelpersModule\module\administration\ift;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\Ift;
use App\Models\Module;
use Illuminate\Support\Arr;

class IftDatatableHelper extends HelperDatatable
{
    private $model, $columns;

    public function __construct()
    {
        $this->model = Ift::class;
        $moduleName = 'Ift';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
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
                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => 'ift',
                    'group' => 'ift',
                    'submodule' => 'ift'
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['action'] = view('meganet.shared.table.actions' . $type_modal_edit, $info)->toHtml();
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

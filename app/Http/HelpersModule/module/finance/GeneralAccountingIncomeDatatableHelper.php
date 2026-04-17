<?php


namespace App\Http\HelpersModule\module\finance;

use App\Models\Module;
use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\GeneralAccountingIncome;
use Illuminate\Support\Arr;

class GeneralAccountingIncomeDatatableHelper extends HelperDatatable
{
    private $model, $columns;


    public function __construct()
    {
        $this->model = GeneralAccountingIncome::class;
        $moduleName = 'GeneralAccountingIncome';
        $this->columns = Module::where('name', $moduleName)->first()
            ->columnsDatatable->where('name', '!=', 'action')->pluck('name')->toArray();
    }


    public function count()
    {
        return $this->model::count();
    }

    public function ordering_query($start, $limit, $order, $dir, $filters = null)
    {
        if ($filters) {
            return $this->model::filters($this->columns, null, $filters)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        }
        return $this->model::select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters = null)
    {
        return $this->model::filters($this->columns, $search, $filters)
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

        $type_modal_edit = '';

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => 'GeneralAccountingIncome',
                    'group' => 'finance',
                    'submodule' => 'GeneralAccountingIncome'
                ];

                $nestedData['operation_date'] = view('meganet.shared.table.column_timestamp', [
                    'value' => $value,
                    'column' => 'operation_date',
                ])->toHtml();

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
    public function objectToArray($object)
    {
        return collect($object)->map(function ($item, $key) {
            $result = [];
            foreach ($item as $type => $values) {
                $result[$type] = [];
                foreach ($values as $key => $val) {
                    $result[$type][$key] = $val;
                }
            }
            return $result;
        })->toArray();
    }
    public function isNotAdmin($column)
    {
        return $column->name != 'Administrador';
    }
}

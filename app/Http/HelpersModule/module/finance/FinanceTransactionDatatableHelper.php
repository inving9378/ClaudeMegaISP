<?php


namespace App\Http\HelpersModule\module\finance;

use App\Models\Transaction;
use App\Http\HelpersModule\module\HelperDatatable;
use App\Services\FormatDateService;

class FinanceTransactionDatatableHelper extends HelperDatatable
{
    private $model, $columns;
    private $moduleName = 'FinanceTransaction';


    public function __construct()
    {
        $this->model = Transaction::class;
    }

    /**
     * Carga perezosa + null-safe de las columnas del módulo (#173).
     * No se consulta la BD al construir (evita el crash en route:list y en la
     * resolución del controller); se resuelve la primera vez que se necesita.
     */
    private function columns()
    {
        if ($this->columns === null) {
            $this->columns = $this->resolveModuleColumns($this->moduleName);
        }

        return $this->columns;
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
        return $this->model::filters($this->columns(), $search)
            ->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function filtering_query($search)
    {
        return $this->model::filters($this->columns(), $search)
            ->count();
    }

    public function transform($request)
    {
        $data = array();

        $type_modal_edit = '';

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns() as $val){
                    if ($val == 'date') {
                        $value->date = (new FormatDateService($value->date))->formatDate();
                    }

                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => 'FinanceTransaction',
                    'group' => 'finance',
                    'submodule' => 'FinanceTransaction'
                ];

                $nestedData['created_at'] = view('meganet.shared.table.column_timestamp', [
                    'value' => $value,
                    'column' => 'created_at',
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
    public function isNotAdmin($column){
        return $column->name != 'Administrador';
    }
}

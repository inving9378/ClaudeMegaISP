<?php


namespace App\Http\HelpersModule\module\client;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\ClientInternetService;
use App\Models\Module;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\FormatDateService;
use Illuminate\Support\Arr;

class ClientTransactionDatatableHelper extends HelperDatatable
{
    public $model;
    public $columns;
    public function __construct()
    {
        $this->model = Transaction::class;
        $moduleName = 'ClientTransaction';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
    }

    public function count($clientId)
    {
        return $this->model::where('client_id', $clientId)
            ->count();
    }

    public function ordering_query($start, $limit, $order, $dir, $clientId)
    {
        return $this->model::select('*')
            ->where('client_id', $clientId)
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $clientId)
    {
        return $this->model::filters($this->columns, $search)
            ->select('*')
            ->where('client_id', $clientId)
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function filtering_query($search, $clientId)
    {
        return $this->model::filters($this->columns, $search)
            ->where('client_id', $clientId)
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
                foreach ($this->columns as $val){
                    if ($val == 'date') {
                        $value->date = (new FormatDateService($value->date))->formatDate();
                    }
                    if ($val == 'from_date') {
                        $value->from_date = (new FormatDateService($value->from_date))->formatDate();
                    }
                    if ($val == 'to_date') {
                        $value->to_date = (new FormatDateService($value->to_date))->formatDate();
                    }

                    $nestedData[$val] = $value->$val ?? '--';
                }

                $info = [
                    'id' => $id,
                    'module' => 'ClientTransaction',
                    'group' => 'client_payroll_transaction',
                    'submodule' => 'client'
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['action'] = view('meganet.shared.table.module.client.finance.transaction.actions' . $type_modal_edit, $info)->toHtml();
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

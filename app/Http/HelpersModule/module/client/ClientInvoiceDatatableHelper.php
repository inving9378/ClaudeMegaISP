<?php


namespace App\Http\HelpersModule\module\client;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\Module;
use App\Models\ClientInvoice;
use App\Services\FormatDateService;
use Illuminate\Support\Arr;

class ClientInvoiceDatatableHelper extends HelperDatatable
{
    public $model;
    public $columns;
    public function __construct()
    {
        $this->model = ClientInvoice::class;
        $moduleName = 'ClientInvoice';
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
                    if ($val == 'payment_date') {
                        $value->payment_date = (new FormatDateService($value->payment_date))->formatDate();
                    }
                    if ($val == 'pay_up') {
                        $value->pay_up = (new FormatDateService($value->pay_up))->formatDate();
                    }


                    $nestedData[$val] = $value->$val ?? '--';
                }

                $document_slip = url('/cliente/billing/invoice/pdf/'.$value->id);

                $info = [
                    'id' => $id,
                    'module' => 'ClientInvoice',
                    'group' => 'client_payroll_invoice',
                    'submodule' => 'client',
                    'documentslip' => $document_slip
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

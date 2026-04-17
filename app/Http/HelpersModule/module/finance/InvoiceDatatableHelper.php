<?php


namespace App\Http\HelpersModule\module\finance;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\Invoice;
use App\Services\FormatDateService;
use Illuminate\Support\Arr;

class InvoiceDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(Invoice::class, 'Invoice');
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
                    if ($val == 'status') {
                        $value->status = $value->status_name;
                    }

                    if ($val == 'due_date') {
                        $value->due_date = (new FormatDateService($value->due_date))->formatDate();
                    }

                    if ($val == 'payment_date') {
                        $value->payment_date = (new FormatDateService($value->payment_date))->formatDate();
                    }

                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => $this->moduleName,
                    'group' => strtolower($this->moduleName),
                    'submodule' => strtolower($this->moduleName),
                    'statusClass' => str_replace(' ', '_', $value->status),
                    'status' => $value->status,
                    'clientId' => $value->client_id,
                    'period' => $value->period
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['action'] = view('meganet.shared.table.module.finance.invoice.actions', $info)->toHtml();
                $nestedData['status'] = view('meganet.shared.table.module.finance.invoice.status', $info)->toHtml();
                $nestedData['client_id'] = view('meganet.shared.table.module.finance.invoice.client', $info)->toHtml();
                $nestedData['period'] = view('meganet.shared.table.module.finance.invoice.period', $info)->toHtml();
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
}

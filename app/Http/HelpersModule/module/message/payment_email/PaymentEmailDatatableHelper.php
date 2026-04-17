<?php


namespace App\Http\HelpersModule\module\message\payment_email;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\PaymentEmail;

class PaymentEmailDatatableHelper extends HelperModuleDatatable
{

    public function __construct()
    {
        parent::__construct(PaymentEmail::class, 'PaymentEmail');
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
                    'module' => $this->moduleName,
                    'group' => strtolower($this->moduleName),
                    'submodule' => strtolower($this->moduleName)
                ];

                $nestedData['action'] = view('meganet.shared.table.module.message.payment.actions' , $info)->toHtml();
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

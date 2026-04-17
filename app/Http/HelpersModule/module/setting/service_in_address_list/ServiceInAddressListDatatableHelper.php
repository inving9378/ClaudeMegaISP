<?php


namespace App\Http\HelpersModule\module\setting\service_in_address_list;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\Nomenclature;
use App\Models\ServiceInAddressList;
use App\Models\Team;
use Illuminate\Support\Arr;

class ServiceInAddressListDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(ServiceInAddressList::class, 'ServiceInAddressList');
    }

    public function transform($request)
    {
        $data = array();

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                $client_id = null;
                foreach ($this->columns as $val) {
                    if ($val == 'client_id') {
                        $nestedData[$val] = $value->client_id ? $value->client_id : 'No asignado';
                        $client_id =  $value->client_id;
                    }

                    if ($val == 'ip') {
                        $nestedData[$val] = $value->ip ? $value->ip : 'No asignado';
                    }
                    
                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => $this->moduleName,
                    'group' => strtolower($this->moduleName),
                    'internet_id' => $value->serviceable_id,
                    'submodule' => strtolower($this->moduleName),
                    'client_id' => $client_id
                ];

                $nestedData['client_id'] = view('meganet.shared.table.module.service_in_address_list.client_id', $info)->toHtml();
                $nestedData['action'] = view('meganet.shared.table.module.service_in_address_list.actions', $info)->toHtml();
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

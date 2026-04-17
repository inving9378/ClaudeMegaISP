<?php


namespace App\Http\HelpersModule\module\network;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\ClientMainInformation;
use App\Models\Module;
use App\Models\NetworkIp;

class NetworkIpDatatableHelper extends HelperDatatable
{
    private $model, $columns;


    public function __construct()
    {
        $this->model = NetworkIp::class;
        $moduleName = 'NetworkIp';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->where('name', '!=', 'action')->pluck('name')->toArray();
    }

    public function count($idModule)
    {
        return $this->model::where('network_id', $idModule)
            ->count();
    }


    public function ordering_query($start, $limit, $order, $dir, $idModule)
    {
        $query = $this->model::select('network_ips.*', 'client_main_information.name as client_name')
            ->leftJoin('client_main_information', 'network_ips.client_id', '=', 'client_main_information.client_id')
            ->where('network_id', $idModule)
            ->offset($start)
            ->limit($limit);


        if ($order == 'ip') {
            $query->orderByRaw("INET6_ATON(network_ips.ip) $dir");
        } else {
            $query->orderBy($order, $dir);
        }

        return $query->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $idModule)
    {
        return $this->model::filters($this->columns, $search)
            ->select('network_ips.*', 'client_main_information.name as client_name')
            ->leftJoin('client_main_information', 'network_ips.client_id', '=', 'client_main_information.client_id')
            ->where('network_id', $idModule)
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function filtering_query($search, $idModule)
    {
        return $this->model::filters($this->columns, $search)
            ->where('network_id', $idModule)
            ->count();
    }

    public function transform($request)
    {
        $data = array();
        $buttons = $this->objectToArray(json_decode($request['request']->buttons));
        $dirColumn = 'meganet.shared.table.column';
        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    if ($val == 'used') {
                        $value->$val = $value->$val ? 'Si' : 'No';
                    }

                    if ($val == 'client_name') {
                        $array = $this->getNameClient($id);
                        $value->$val = view($dirColumn, [
                            'dir' => '/cliente/editar/' . $array['clientId'],
                            'value' => $value,
                            'column' => $val,
                        ])->toHtml();
                    }

                    $nestedData[$val] = $value->$val;
                }
                $tdClass = $this->getTdClass($value);
                $nestedData['class_table_row'] = [
                    'td' => $tdClass
                ];

                $info = [
                    'id' => $id,
                    'module' => 'NetworkIp',
                    'group' => 'ipv4',
                    'submodule' => 'ipv4',
                    'modal' => 'modalEditIp'
                ];
                $nestedData['action'] = view('meganet.shared.table.actions_type_modal', $info)->toHtml();
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

    public function getNameClient($id)
    {
        $nameClient = null;
        $networkIp = $this->model::findOrFail($id);
        $clientId = $networkIp->client_id;
        $clientMainInformation = ClientMainInformation::where('client_id', $clientId)->first();
        if ($clientMainInformation) {
            $nameClient = $clientMainInformation->getClientNameWithFathersNamesAttribute();
        }

        return [
            'nameClient' => $nameClient,
            'clientId' => $clientId,
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

    public function isAdmin()
    {
        return auth()->user()->isAdmin();
    }

    public function getTdClass($value)
    {
        $used = $value->used === 'Si';
        return match (true) {
            $used => 'td_network_ip_used_and_ping_ok',
            default => ''
        };
    }
}

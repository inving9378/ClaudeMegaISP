<?php


namespace App\Http\HelpersModule\module\client;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\HelpersModule\module\HelperDatatable;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Repository\NetworkRepository;
use App\Models\ClientCustomService;
use App\Models\Module;
use App\Services\NetworkIpService;
use Illuminate\Support\Arr;

class ClientCustomServiceDatatableHelper extends HelperDatatable
{
    private $model;
    private $columns;
    protected $networkIpService;
    public function __construct()
    {
        $this->model = ClientCustomService::class;
        $moduleName = 'ClientCustomService';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
        $this->networkIpService = new NetworkIpService();
    }

    public function count($idModule)
    {
        return $this->model::where('client_id', $idModule)
            ->count();
    }


    public function ordering_query($start, $limit, $order, $dir, $idModule)
    {
        return $this->model::select('*')
            ->where('client_id', $idModule)
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $idModule)
    {
        return $this->model::filters($this->columns, $search)
            ->select('*')
            ->where('client_id', $idModule)
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }


    public function filtering_query($search, $idModule)
    {
        return $this->model::filters($this->columns, $search)
            ->where('client_id', $idModule)
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
                    if ($val == 'ip') {
                        $nestedData[$val] = $this->getNetworkIp($value);
                    } else {
                        $nestedData[$val] = $value->$val;
                    }
                }

                $info = [
                    'id' => $id,
                    'module' => 'ClientCustomService',
                    'group' => 'client_service_custom',
                    'submodule' => 'client'
                ];

                if (!$value->client_bundle_service_id) {
                    $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                    $nestedData['action'] = view('meganet.shared.table.module.client.client_custom_service.actions_type_modal', $info)->toHtml();
                }
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


    public function getNetworkIp($value)
    {
        $ip = null;
        if ($value->router_id) {
            $ip = $this->networkIpService->getIpFilterByPlanId($value->id,'ClientCustomService');
        }
        return $ip;
    }
}

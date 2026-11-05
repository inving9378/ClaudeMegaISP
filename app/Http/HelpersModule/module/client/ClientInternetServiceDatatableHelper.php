<?php


namespace App\Http\HelpersModule\module\client;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Http\Repository\InternetRepository;
use App\Http\Repository\NetworkIpRepository;
use App\Models\ClientInternetService;
use App\Models\Module;
use App\Services\NetworkIpService;
use Illuminate\Support\Arr;

class ClientInternetServiceDatatableHelper extends HelperDatatable
{
    private $model;
    private $columns;
    protected $networkIpService;

    public function __construct()
    {
        $this->model = ClientInternetService::class;
        $moduleName = 'ClientInternetService';
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
                $nestedData = [];
                foreach ($this->columns as $val) {
                    if ($val == 'ip') {
                        $nestedData[$val] = $this->networkIpService->getIpFilterByPlanId($id,'ClientInternetService');
                    } elseif ($val == 'service_name') {
                        $internetRepository = new InternetRepository();
                        $nestedData[$val] = $internetRepository->getModelFilterById($value->internet_id)->service_name ?? '';
                    } else {
                        $nestedData[$val] = $value->$val;
                    }
                }

                $info = [
                    'id' => $id,
                    'module' => 'ClientInternetService',
                    'group' => 'client_service_internet',
                    'submodule' => 'client',
                    // #988: el botón «Datos de red» solo aparece si el cliente es dedicado.
                    'dedicado' => $this->esDedicado((int) $value->client_id),
                ];

               // if (!$value->client_bundle_service_id) {
                    $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                    $nestedData['action'] = view('meganet.shared.table.module.client.client_internet_service.actions_type_modal', $info)->toHtml();
                // } else {
                //     $nestedData['action'] = view('meganet.shared.table.module.client.client_internet_service.actions', $info)->toHtml();
                // }

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

    /** ¿El cliente tiene prendido «Cliente dedicado»? Una consulta por cliente por tabla. */
    private function esDedicado(int $clienteId): bool
    {
        static $cache = [];

        return $cache[$clienteId] ??= \Illuminate\Support\Facades\DB::table('client_additional_information')
            ->where('client_id', $clienteId)->whereNull('deleted_at')->value('tipo_cliente') === \App\Services\Clientes\TipoCliente::DEDICADO;
    }
}

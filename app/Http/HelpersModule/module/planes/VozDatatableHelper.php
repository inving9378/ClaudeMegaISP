<?php


namespace App\Http\HelpersModule\module\planes;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Http\Repository\ClientVozServiceRepository;
use App\Models\Module;
use App\Models\Voise;
use Illuminate\Support\Facades\DB;

class VozDatatableHelper extends HelperDatatable
{
    private $model;
    private $columns;
    public function __construct()
    {
        $this->model = Voise::class;
        $moduleName = 'Voise';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
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
        return $this->model::filters($this->columns, $search)
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

        if (!empty($request['array'])) {
            $clientCounts = $this->batchClientCounts(collect($request['array']));

            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val){
                    $nestedData[$val] = $value->$val;
                }
                $associated_clients = $clientCounts->get($id, 0);

                if ($associated_clients) {
                    $nestedData['associated_clients'] = view('meganet.shared.table.module.voz.associated_clients',  [
                        'associated_clients' => $associated_clients,
                        'id' => $id
                    ])->toHtml();
                }

                $nestedData['action'] = view('meganet.shared.table.actions',[
                    'id' => $id,
                    'module' => 'voz',
                    'group' => 'plan',
                    'submodule' => 'voz'
                ])->toHtml();
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

    public function getAssociatedClientForThis($id)
    {
        $clientVozServiceRepository = new ClientVozServiceRepository();
        $clients = $clientVozServiceRepository->getClientsByVozId($id);
        $clients = $clients->unique()->toArray();
        $count = count($clients);
        return $count;
    }

    private function batchClientCounts($plans): \Illuminate\Support\Collection
    {
        if ($plans->isEmpty()) return collect();
        $ids = $plans->pluck('id')->unique()->values()->toArray();
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::select("
            SELECT cvs.voz_id AS plan_id, COUNT(DISTINCT cvs.client_id) AS client_count
            FROM client_voz_services cvs
            JOIN clients c ON c.id = cvs.client_id AND c.deleted_at IS NULL
            WHERE cvs.client_bundle_service_id IS NULL
              AND cvs.voz_id IN ($ph)
            GROUP BY cvs.voz_id
        ", $ids);
        return collect($rows)->pluck('client_count', 'plan_id');
    }
}

<?php


namespace App\Http\HelpersModule\module\planes;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Models\ColumnDatatableModule;
use App\Models\Internet;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

class InternetDatatableHelper extends HelperDatatable
{
    private $model;
    private $columns;

    public function __construct()
    {
        $this->model = Internet::class;
        $moduleName = 'Internet';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->where('name', '!=', 'action')->pluck('name')->toArray();
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
                foreach ($this->columns as $val) {
                    $nestedData[$val] = $value->$val;
                }
                $associated_clients = $clientCounts->get($id, 0);

                if ($associated_clients) {
                    $nestedData['associated_clients'] = view('meganet.shared.table.module.internet.associated_clients',  [
                        'associated_clients' => $associated_clients,
                        'id' => $id
                    ])->toHtml();
                }

                $nestedData['action'] = view('meganet.shared.table.actions', [
                    'id' => $id,
                    'module' => 'internet',
                    'group' => 'plan',
                    'submodule' => 'internet'
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

    public function getAssociatedClientForThisBundle($id)
    {
        $clientMainInformationRepository = new ClientInternetServiceRepository();
        $clients = $clientMainInformationRepository->getClientsByInternetId($id);
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
            SELECT cis.internet_id AS plan_id, COUNT(DISTINCT cis.client_id) AS client_count
            FROM client_internet_services cis
            JOIN clients c ON c.id = cis.client_id AND c.deleted_at IS NULL
            WHERE cis.client_bundle_service_id IS NULL
              AND cis.internet_id IN ($ph)
            GROUP BY cis.internet_id
        ", $ids);
        return collect($rows)->pluck('client_count', 'plan_id');
    }
}

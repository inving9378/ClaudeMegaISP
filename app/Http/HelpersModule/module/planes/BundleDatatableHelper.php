<?php


namespace App\Http\HelpersModule\module\planes;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Models\Bundle;
use App\Models\Module;

class BundleDatatableHelper extends HelperDatatable
{
    private $model;
    private $columns;
    public function __construct()
    {
        $this->model = Bundle::class;
        $moduleName = 'Bundle';
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
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    $nestedData[$val] = $value->$val;
                }

                $associated_clients = $this->getAssociatedClientForThisBundle($id);

                if ($associated_clients) {
                    $nestedData['associated_clients'] = view('meganet.shared.table.module.bundle.associated_clients',  [
                        'associated_clients' => $associated_clients,
                        'id' => $id
                    ])->toHtml();
                }


                $nestedData['action'] = view('meganet.shared.table.actions', [
                    'id' => $id,
                    'module' => 'paquetes',
                    'group' => 'plan',
                    'submodule' => 'package'
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
        $clientMainInformationRepository = new ClientBundleServiceRepository();
        $clients = $clientMainInformationRepository->getClientsByBundleId($id);
        $clients = $clients->unique()->toArray();
        $count = count($clients);
        return $count;
    }
}

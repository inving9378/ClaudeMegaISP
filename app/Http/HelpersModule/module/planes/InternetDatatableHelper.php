<?php


namespace App\Http\HelpersModule\module\planes;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Models\ColumnDatatableModule;
use App\Models\Internet;
use App\Models\Module;

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
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    $nestedData[$val] = $value->$val;
                }
                $associated_clients = $this->getAssociatedClientForThisBundle($id);

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
}

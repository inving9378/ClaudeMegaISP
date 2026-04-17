<?php


namespace App\Http\HelpersModule\module\client;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\DocumentClient;
use App\Models\Module;
use Illuminate\Support\Arr;

class DocumentClientDatatableHelper extends HelperDatatable
{
    private $model;
    private $columns;

    public function __construct()
    {
        $this->model = DocumentClient::class;
        $moduleName = 'DocumentClient';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
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

    public function filtering_query($search,$idModule)
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
                    $nestedData[$val] = $value->$val;
                }

                $document = null;
                if ($value->file){
                    $document = url($value->file->path);
                }

                $info = [
                    'id' => $id,
                    'module' => 'DocumentClient',
                    'group' => 'client_document',
                    'submodule' => 'client',
                    'document' => $document
                ];
                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);

                $nestedData['action'] = view('meganet.shared.table.actions' . $type_modal_edit, $info)->toHtml();
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
}

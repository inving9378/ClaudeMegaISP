<?php


namespace App\Http\HelpersModule\module\administration\document_template;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Http\Repository\UserRepository;
use App\Models\ActivityLog;
use App\Models\ClientMainInformation;
use App\Models\DocumentTemplate;
use App\Models\LogActivity;
use App\Models\Module;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Arr;

class DocumentTemplateDatatableHelper extends HelperDatatable
{
    public $model;
    public $columns;
    public function __construct()
    {
        $this->model = DocumentTemplate::class;
        $moduleName = 'DocumentTemplate';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
    }

    public function count()
    {
        return $this->model::count();
    }

    public function ordering_query($start, $limit, $order, $dir, $filters = null)
    {
        if ($filters) {
            return $this->model::filters($this->columns, null, $filters)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        }
        return $this->model::select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters = null)
    {
        return $this->model::filters($this->columns, $search, $filters)
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
        $dirColumn = 'meganet.shared.table.column_activity_log';

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    if ($val == 'type') {
                        $value->type = $this->getTypeDocumentName($id);
                    }

                    $nestedData[$val] = view($dirColumn, [
                        'data_id' => $id,
                        'value' => $value,
                        'column' => $val,
                        'class' => 'show_activity'
                    ])->toHtml();
                }
                $info = [
                    'id' => $id,
                    'group' => 'administration',
                    'modal' => 'modalDocumentTemplates',
                    'submodule' => 'document_template',
                    'document' => '',
                    'data' => $value->html,
                    'edit' => true,
                    'class' => 'show_document_template_pdf'
                ];

                $nestedData['action'] = view('meganet.shared.table.module.document_template.action_delete_and_show_document', $info)->toHtml();

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

    public function getTypeDocumentName($id)
    {
        $documentTemplate = $this->model::find($id);
        $typeName = $documentTemplate->type_template->name;
        return $typeName;
    }
}

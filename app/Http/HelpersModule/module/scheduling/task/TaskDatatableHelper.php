<?php


namespace App\Http\HelpersModule\module\scheduling\task;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\Module;
use App\Models\Task;
use App\Services\FormatDateService;
use Carbon\Carbon;

class TaskDatatableHelper extends HelperDatatable
{
    public $model;
    public $columns;
    public function __construct()
    {
        $this->model = Task::class;
        $moduleName = 'Task';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
    }

    public function count($filters = null)
    {
        if ($filters) {
            $query = $this->model::filters($this->columns, null, $filters);
            if ((!auth()->user()->can('task_view_full_task') && !auth()->user()->isAdmin()) || (!auth()->user()->can('task_view_full_task') && !auth()->user()->isSuperAdmin())) {
                $query = $query->whereHas('users', function ($query) {
                    $query->where('user_id', auth()->user()->id);
                });
            }
            return $query->count();
        }

        $query = $this->model::select('*');
        if ((!auth()->user()->can('task_view_full_task') && !auth()->user()->isAdmin()) || (!auth()->user()->can('task_view_full_task') && !auth()->user()->isSuperAdmin())) {
            $query = $query->whereHas('users', function ($query) {
                $query->where('user_id', auth()->user()->id);
            });
        }
        return $query->count();
    }

    public function ordering_query($start, $limit, $order, $dir, $filters, $columns = null)
    {
        $query = $this->model::query()
            ->with(['users.teams', 'client_main_information', 'project', 'latestNote', 'createdBy', 'updatedBy'])
            ->filters($columns ?? $this->columns, null, $filters);

        // Permisos
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->isSuperAdmin() && !$user->can('task_view_full_task')) {
            $query->whereHas('users', fn($q) => $q->where('user_id', $user->id));
        }

        return $query->orderBy($order, $dir)
            ->offset($start)
            ->limit($limit)
            ->get();
    }
    public function searching_query($start, $limit, $order, $dir, $search, $filters = null, $columns = null)
    {
        $query = $this->model::filters($this->columns,  $search, $filters)
            ->with(['users.teams', 'client_main_information', 'project', 'latestNote', 'createdBy', 'updatedBy'])
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);

        if ((!auth()->user()->can('task_view_full_task') && !auth()->user()->isAdmin()) || (!auth()->user()->can('task_view_full_task') && !auth()->user()->isSuperAdmin())) {
            $query = $query->whereHas('users', function ($query) {
                $query->where('user_id', auth()->user()->id);
            });
        }

        return $query->get();
    }

    public function filtering_query($search)
    {
        $query = $this->model::filters($this->columns, $search);
        if ((!auth()->user()->can('task_view_full_task') && !auth()->user()->isAdmin()) || (!auth()->user()->can('task_view_full_task') && !auth()->user()->isSuperAdmin())) {
            $query = $query->whereHas('users', function ($query) {
                $query->where('user_id', auth()->user()->id);
            });
        }
        return $query->count();
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
                $status_cls = $value->status_cls;
                foreach ($this->columns as $val) {
                    if ($val == 'project_id') {
                        $value->project_id = $value->project_title;
                    }
                    if ($val == 'workflow') {
                        $value->workflow = $value->workflow_name;
                    }

                    if ($val == 'status') {
                        $value->status = $value->status_name;
                    }

                    if ($val == 'assigned_to') {
                        $value->assigned_to = $value->user_name_assigned;
                    }

                    if ($val == 'start_time') {
                        $value->start_time = (new FormatDateService($value->start_time))->formatDate();
                    }

                    if ($val == 'archived_at') {
                        $value->archived_at = (new FormatDateService($value->archived_at))->formatDateWithTime();
                    }

                    if ($val == 'finish_at') {
                        $value->finish_at = (new FormatDateService($value->finish_at))->formatDate();
                    }

                    if ($val == 'client_main_information_id') {
                        $value->client_main_information_id = $value->client_main_information->client_name_with_fathers_names ?? null;
                    }

                    if ($val == 'note') {
                        $value->note = $value->last_note ?? '';
                    }

                    $nestedData[$val] = view('meganet.shared.table.column', [
                        'dir' => '/scheduling/task/editar/' . $value->id,
                        'value' => $value,
                        'column' => $val,
                    ])->toHtml();
                }

                $nestedData['action'] = view('meganet.shared.table.module.task.actions', [
                    'id' => $id,
                    'module' => 'scheduling/task',
                    'group' => 'task',
                    'submodule' => 'task',
                    'archived' => $value->archived,
                ])->toHtml();


                $nestedData['status'] = view('meganet.shared.table.module.task.status', [
                    'id' => $id,
                    'status' => $value->status,
                    'cls' => $status_cls,
                    'dir' => '/scheduling/task/editar/' . $value->id,
                ])->toHtml();

                $clientId = $value->client_main_information->client_id ?? null;
                $nestedData['client_main_information_id'] = view('meganet.shared.table.module.task.client_main_information_id', [
                    'id' => $id,
                    'client_name' => $value->client_main_information_id,
                    'dir' => $clientId ? '/cliente/editar/' . $clientId : 'javascript:void(0);',
                ])->toHtml();

                $nestedData['note'] = view('meganet.shared.table.module.task.last_note', [
                    'id' => $id,
                    'note' => $value->note
                ])->toHtml();

                $nestedData['start_time'] = view('meganet.shared.table.module.task.start_time', [
                    'id' => $id,
                    'start_time' => $value->start_time,
                    'color' => $this->getColorByDate($value->start_time),
                    'dir' => '/scheduling/task/editar/' . $value->id,
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

    public function getColorByDate($date)
    {
        $now = Carbon::now();

        // Eliminar la zona horaria de la cadena
        $dateWithoutTimezone = preg_replace('/\s+GMT.*$/', '', $date);
        $parsedDate = Carbon::parse($dateWithoutTimezone);

        if ($parsedDate < $now) {
            return "#FF7F7F"; // Rojo suave
        }
        return "";
    }



    public function includeButtonEditTypeModalIfIsRequested($request)
    {
        return isset($request['request']->modal);
    }


    public function transformFilter($object)
    {
        if (is_null($object) || (is_array($object) && in_array(null, $object, true))) {
            return [];
        }
        return collect($object)->map(function ($item, $key) {
            $result = [];
            foreach ($item as $type => $values) {
                $result[$type] = $values;
            }
            return $result;
        })->toArray();
    }
}

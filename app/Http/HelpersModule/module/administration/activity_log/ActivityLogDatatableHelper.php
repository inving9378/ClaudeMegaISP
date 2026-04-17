<?php


namespace App\Http\HelpersModule\module\administration\activity_log;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Http\Repository\UserRepository;
use App\Models\ActivityLog;
use App\Models\ClientMainInformation;
use App\Models\LogActivity;
use App\Models\Module;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Arr;

class ActivityLogDatatableHelper extends HelperDatatable
{
    public $model;
    public $columns;
    public function __construct()
    {
        $this->model = ActivityLog::class;
        $moduleName = 'ActivityLog';
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
                    if ($val == 'causer_id') {
                        $value->causer_id = $value->user_name;
                    }
                    $nestedData[$val] = view($dirColumn, [
                        'data_id' => $id,
                        'value' => $value,
                        'column' => $val,
                        'class' => 'show_activity'
                    ])->toHtml();
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

    public function getNameAddBy($id)
    {
        if ($id) {
            $userRepository = new UserRepository();
            $user = $userRepository->getUserById($id);
            if ($user) {
                return $user->getClientNameWithFathersNamesAttribute();
            }
        }
        return $id;
    }


    public function includeButtonEditTypeModalIfIsRequested($request)
    {
        return isset($request['request']->modal);
    }
}

<?php

namespace App\Modules\Core\Auditoria\Controllers;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\administration\activity_log\ActivityLogDatatableHelper;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    private $helper;

    public function __construct(ActivityLogDatatableHelper $helper)
    {
        $model = 'ActivityLog';
        $this->data['url'] = 'meganet.module.administration.activity_log';
        $this->data['module'] = 'ActivityLog';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->helper = $helper;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }
}

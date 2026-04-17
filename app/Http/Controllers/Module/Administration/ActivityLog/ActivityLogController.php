<?php

namespace App\Http\Controllers\Module\Administration\ActivityLog;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\administration\activity_log\ActivityLogDatatableHelper;
use App\Http\HelpersModule\module\log_activity\LogActivityDatatableHelper;
use App\Models\LogActivity;
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

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.add', $this->data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    }

    public function show(LogActivity $log)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Client $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);

        return view($this->data['url'] . '.edit', $this->data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Client $client
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Client $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }
}

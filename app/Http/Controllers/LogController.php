<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClientLog;

class LogController extends Controller
{

    public function __construct()
    {
        $model = 'ClientLog';
        $this->data['url'] = 'meganet.module.logs';
        $this->data['module'] = 'log';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'log';  
        $logs = ClientLog::with('client')->get();
        $this->data['logs'] = $logs; 

    }

    public function index()
    {      
        $this->data['notifications'] = $this->userNotification();
        return view($this->data['url'] . '.index', $this->data);
    }
}

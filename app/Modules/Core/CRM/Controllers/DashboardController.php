<?php

namespace App\Modules\Core\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\CRM\Models\Crm;
use Illuminate\Http\Request;

class DashboardController extends Controller
{

    public function __construct()
    {
        $this->data['url'] = 'core-crm';
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        return view($this->data['url'] . '::dashboard',$this->data);
    }
}

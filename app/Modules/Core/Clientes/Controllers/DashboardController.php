<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Repository\TicketRepository;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{

    public function __construct()
    {
        $this->data['url'] = 'meganet.module.client';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        return view($this->data['url'] . '.dashboard', $this->data);
    }

}

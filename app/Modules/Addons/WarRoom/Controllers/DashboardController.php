<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WarRoom\Models\Meeting;

class DashboardController extends Controller
{
    public function index()
    {
        $activeMeeting = Meeting::active()->with(['sections', 'moderator'])->latest()->first();

        return view('addon-warroom::warroom.index', [
            'activeMeeting' => $activeMeeting,
        ]);
    }
}

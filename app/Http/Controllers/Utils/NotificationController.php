<?php

namespace App\Http\Controllers\Utils;

use App\Http\Controllers\Controller;
use App\Mail\NotificateCrm;
use App\Models\Crm;
use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class NotificationController extends Controller
{
    public function sendNotificationCrm(Request  $request, $id)
    {
        $input = $request->validate([
            "message-notificate-crm" => "required",
            "email-notificate-crm" => "required|email",
            "subject-notificate-crm" => "required",
        ]);

        dispatch(function () use ($input, $id) {
            Crm::findOrFail($id)->log_activities()->create([
                'user_id' => $this->userAutenticated()->id,
                'type' => 'send_email_notification',
                'data' => json_encode($input)
            ]);
            Mail::to($input['email-notificate-crm'])->send(new NotificateCrm($input, $id));
        })->afterResponse();
        return true;
    }

    public function readAll()
    {
        $user = $this->userAutenticated();
        foreach ($user->unreadNotifications as $notification) {
            $notification->markAsRead();
        }
        return redirect()->back();
    }

    public function readNotification($id)
    {
        $user = $this->userAutenticated();
        $notification = $user->unreadNotifications->firstWhere('id', $id);
        $notification->markAsRead();
        return redirect()->back();
    }

    public function userAutenticated()
    {
        return Auth::user();
    }
}

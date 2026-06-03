<?php

namespace App\Modules\Addons\Flotas\Mail;

use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Services\Notifications\GeofenceEventPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FleetGeofenceEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(FleetGeofenceEvent $event)
    {
        $this->data = GeofenceEventPresenter::data($event);
    }

    public function build()
    {
        $d = $this->data;
        return $this->subject("Flotas · {$d['vehicle_name']} {$d['action']} {$d['geofence_name']}")
            ->view('addon-flotas::emails.geofence_event', ['d' => $d]);
    }
}

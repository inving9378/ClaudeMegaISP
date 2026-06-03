<?php

namespace App\Modules\Addons\Flotas\Services\Notifications\Drivers;

use App\Models\User;
use App\Modules\Addons\Flotas\Mail\FleetGeofenceEventMail;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Services\Notifications\NotificationChannelInterface;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'email';
    }

    public function destination(User $user): ?string
    {
        $email = trim((string) ($user->email ?? ''));
        return $email !== '' ? $email : null;
    }

    public function send(User $user, FleetGeofenceEvent $event): bool
    {
        Mail::to($this->destination($user))->send(new FleetGeofenceEventMail($event));
        return true;
    }
}

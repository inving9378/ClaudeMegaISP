<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Auth;

trait LogActivityCrm {
    public function textEmailNotification(){
        return $this->user->name. ' envio una notification a ' . collect($this->data)->toArray()['email-notificate-crm'];
    }

    public function textCreateCrm(){
        return $this->user->name. ' creo el crm.';
    }

    public function textUpdatedCrm(){
        return $this->user->name. ' actualizo el crm.';
    }
}

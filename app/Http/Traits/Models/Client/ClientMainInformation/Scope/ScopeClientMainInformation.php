<?php

namespace App\Http\Traits\Models\Client\ClientMainInformation\Scope;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientMainInformation;

trait ScopeClientMainInformation
{
    public function scopeNotActive($query)
    {
        $query->where('estado', '!=', ComunConstantsController::STATE_ACTIVE);
    }

    public function scopeStateActive($query)
    {
        $query->where('estado', ComunConstantsController::STATE_ACTIVE);
    }

    public function scopeStateBlocked($query)
    {
        $query->where('estado', ClientMainInformation::STATE_BLOCKED);
    }

    public function scopeStateNotInActive($query)
    {
        $query->where('estado','!=', ClientMainInformation::STATE_INACTIVE);
    }


}

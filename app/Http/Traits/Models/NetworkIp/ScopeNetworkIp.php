<?php


namespace App\Http\Traits\Models\NetworkIp;

use App\Http\Controllers\Utils\ComunConstantsController;

trait ScopeNetworkIp
{
    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {
                    if ($value == 'id') {
                        $query->orWhere('network_ips.id', 'like', '%' . $search . '%');
                        $query->orWhere('client_main_information.id', 'like', '%' . $search . '%');
                    } elseif ($value == 'client_name') {
                        $query->orWhere('client_main_information.name', 'like', '%' . $search . '%');
                    } elseif ($value == 'location_id') {
                        $query->orWhere('client_main_information.location_id', 'like', '%' . $search . '%');
                    } else {
                        $query->orWhere($value, 'like', '%' . $search . '%');
                    }
                }
            });
        }
    }

    public function scopeNotUsed($query)
    {
        $query->where('used', ComunConstantsController::IS_NUMERICAL_FALSE);
    }
}

<?php

namespace App\Http\Traits\Models\ActivityLog\scope;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientInternetService;
use Carbon\Carbon;

trait ScopeActivityLog
{
    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)) {
            return $query->where(function ($query) use ($search, $columns) {
                foreach (collect($columns)->filter(function ($value) {
                    return $value != 'action';
                })->toArray() as $value) {
                    if($value == 'causer_id'){
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    }
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        }
    }

}

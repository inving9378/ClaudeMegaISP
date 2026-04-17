<?php

namespace App\Models;

use App\Services\NetworkIpService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceInAddressList extends BaseModel
{
    use HasFactory;

    const SERVICEABLE_TYPE_INTERNET = 'App\Models\ClientInternetService';
    const SERVICEABLE_TYPE_BUNDLE = 'App\Models\ClientBundleService';


    protected $guarded = [];
    protected $appends = ['client_id', 'ip'];

    public function isServiceableTypeInternet()
    {
        return $this->serviceable_type == self::SERVICEABLE_TYPE_INTERNET;
    }

    public function isServiceableTypeBundle()
    {
        return $this->serviceable_type == self::SERVICEABLE_TYPE_BUNDLE;
    }

    public function serviceable()
    {
        return $this->morphTo();
    }

    public function getClientIdAttribute()
    {
        return $this->serviceable ? $this->serviceable->client_id : null;
    }

    public function getIpAttribute()
    {
        $networkIpService = new NetworkIpService($this->serviceable);
        return $networkIpService->getClientIp();
    }

    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search)) {
            $query = $query->where(function ($query) use ($search, $columns) {
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    if ($value == 'client_id') {
                        // Filtramos por client_id en la relación polimórfica
                        $query->orWhereHas('serviceable', function ($query) use ($search) {
                            $query->where('client_id', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'ip') {
                        // Filtramos por IP utilizando una subconsulta que accede al servicio de IP
                        $query->orWhereHas('serviceable', function ($query) use ($search) {
                            $query->whereHas('network_ip', function ($query) use ($search) {
                                $query->where('ip', 'like', '%' . $search . '%');
                            });
                        });
                    } else {
                        // Búsqueda por otros campos
                        $query->orWhere('service_in_address_lists.' . $value, 'like', '%' . $search . '%');
                    }
                }
            });
        }
        return $query;
    }

}

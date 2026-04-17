<?php

namespace App\Models;

use App\Notifications\StandardNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

class Router extends BaseModel
{
    use HasFactory;

    protected $guarded = [];
    protected $with = ['mikrotik'];

    const HOSTPOT_USER = "Hostpot(Users)/API accounting";
    const PPPOE_USER = "PPP(Secrets)/API Acounting";
    const RADIUS_USER = "Hostopt(Radius)/Radius";


    const IS_MIKROTIK = 'Mikrotik';

    const MULTIPLE_RELATIONS = [
        'partners' => 'partners',
    ];

    const SINGLE_RELATIONS = [
        'Mikrotik' => [
            'relation_name' => 'mikrotik',
            'relation_field' => 'router_id',
            'local_relation' => 'id',
        ],
        'MikrotikConfig' => [
            'relation_name' => 'mikrotikconfig',
            'relation_field' => 'router_id',
            'local_relation' => 'id',
        ],
    ];

    public function clientinternetservices()
    {
        return $this->hasMany('App\Models\ClientInternetService');
    }

    public function clientcustomservices()
    {
        return $this->hasMany('App\Models\ClientCustomService');
    }

    public function mikrotik()
    {
        return $this->hasOne('App\Models\Mikrotik');
    }

    public function mikrotikconfig()
    {
        return $this->hasOne('App\Models\MikrotikConfig');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function partners()
    {
        return $this->morphToMany(
            Partner::class,
            'partner_module',
            'partner_module'
        )->withTimestamps();
    }

    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)) {
            return $query->where(function ($query) use ($search, $columns) {
                foreach (
                    collect($columns)
                        ->filter(function ($value) {
                            return $value != 'action';
                        })
                        ->toArray()
                    as $value
                ) {
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        }
    }

    public function isMikrotik()
    {
        return $this->type_of_nas === self::IS_MIKROTIK;
    }

    public function hasMikrotik()
    {
        return $this->mikrotik;
    }

    public function sendNotifications($priority = null, $users = null)
    {
        if (!empty($users)) {
            $notification = new MikrotikNotification();
            $notification->priority = $priority;
            $notification->title = 'Se ha perdido la conexion con el Mikrotik';
            $notification->router_id = $this->id;
            $notification->base_url = '/red/router/mikrotik/read-notification/';
            $notification->save();
            Notification::send($users, new StandardNotification($notification));
        }
    }
}

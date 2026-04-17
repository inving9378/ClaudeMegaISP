<?php

namespace App\Models;

use App\Http\Requests\module\network\NetworkCreateRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Network extends BaseModel
{
    use HasFactory;
    protected $guarded = [];

    const TYPE_STATIC_NETWORK = 'Estatico';
    const TYPE_POOLL_NETWORK = 'Pool';

    public function network_ip()
    {
        return $this->hasMany(NetworkIp::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)){
            return $query->where(function ($query) use ($search, $columns){
                foreach ($columns as $value){
                    $query->orWhere($value,'like','%'.$search.'%');
                }
            });
        }
    }

    public function scopeIsPool($query)
    {
        $query->where('type_of_use', self::TYPE_POOLL_NETWORK);
    }

    public function getRequestAndStoreMethod()
    {
        $request = new NetworkCreateRequest();
        $storeMethod = 'App\Http\Controllers\Module\Network\NetworkController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod
        ];
    }
}

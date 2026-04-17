<?php

namespace App\Models;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Requests\module\network\NetworkIpCreateRequest;
use App\Http\Traits\Models\NetworkIp\NetworkIpTrait;
use App\Http\Traits\Models\NetworkIp\ScopeNetworkIp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetworkIp extends BaseModel
{
    use HasFactory, NetworkIpTrait, ScopeNetworkIp;

    protected $guarded = [];

    protected $appends = ['icon_for_host_category'];

    public function getIconForHostCategoryAttribute()
    {
        return $this->networkIpGetIconForHostCategory($this->host_category);
    }

    public function network()
    {
        return $this->belongsTo('App\Models\Network');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function use_by_client_internet_service()
    {
        return $this->belongsTo('App\Models\ClientInternetService', 'used_by');
    }

    public function getRequestAndStoreMethod()
    {
        $request = new NetworkIpCreateRequest();
        $storeMethod = 'App\Http\Controllers\Module\Network\NetworkIpController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod
        ];
    }

    // FUNCTIONS
}

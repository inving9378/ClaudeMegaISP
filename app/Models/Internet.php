<?php

namespace App\Models;

use App\Http\Requests\module\plan\InternetCreateRequest;
use App\Models\Interface\PackageInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Internet extends BaseModel implements PackageInterface
{
    use HasFactory;
    protected $guarded = [];

    const MULTIPLE_RELATIONS = [
        'partners' => 'partners',
        'types_of_billing' => 'billings',
        'rates_to_change' => 'plan_internet_client'
    ];

    public function billings()
    {
        return $this->morphToMany(
            TypeBilling::class,
            'plan_billing',
            'plan_type_billings'
        )->withTimestamps();
    }

    public function partners()
    {
        return $this->morphToMany(
            Partner::class,
            'partner_module',
            'partner_module'
        )->withTimestamps();
    }

    public function bundles()
    {
        return $this->morphToMany(
            Bundle::class,
            'plan_bundle',
            'plan_bundles'
        )->withTimestamps();
    }

    public function plan_internet_client()
    {
        return $this->belongsToMany(
            Internet::class,
            'change_plan_internet_clients',
            'internet_id',
            'tarifa_internet_id'
        );
    }

    public function client_internet_services()
    {
        return $this->hasMany('App\Models\ClientInternetService');
    }

    public function client_custom_services()
    {
        return $this->hasMany('App\Models\ClientCustomService');
    }

    public function client_bundle_services()
    {
        return $this->hasMany('App\Models\ClientBundleService');
    }

    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)) {
            return $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        }
    }

    public function getRequestAndStoreMethod()
    {
        $request = new InternetCreateRequest();
        $storeMethod = 'App\Http\Controllers\Module\Plan\InternetController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod
        ];
    }

    public function getRepository()
    {
        return \App\Http\Repository\InternetRepository::class;
    }
}

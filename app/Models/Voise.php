<?php

namespace App\Models;

use App\Http\Requests\module\plan\VozCreateRequest;
use App\Models\Interface\PackageInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voise extends BaseModel implements PackageInterface
{
    use HasFactory;
    protected $guarded = [];

    const MULTIPLE_RELATIONS = [
        'partners' => 'partners',
        'types_of_billing' => 'billings',
        'rates_to_change' => 'plan_voz_client'
    ];

    public function billings(){
        return $this->morphToMany(
            TypeBilling::class,
            'plan_billing',
            'plan_type_billings'
        )->withTimestamps();
    }

    public function partners(){
        return $this->morphToMany(
            Partner::class,
            'partner_module',
            'partner_module'
        )->withTimestamps();
    }

    public function bundles(){
        return $this->morphToMany(
            Bundle::class,
            'plan_bundle',
            'plan_bundles'
        )->withTimestamps();
    }

    public function plan_voz_client(){
        return $this->belongsToMany(
            Voise::class,
            'change_plan_voz_clients',
            'voz_id',
            'tarifa_voz_id'
        );
    }

    public function client_voz_services(){
        return $this->hasMany('App\Models\ClientVozService');
    }

    public function client_bundle_services(){
        return $this->hasMany('App\Models\ClientBundleService');
    }

    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)){
            return $query->where(function ($query) use ($search, $columns){
                foreach (collect($columns)->filter(function ($value){
                    return $value != 'action';
                })->toArray() as $value){
                    $query->orWhere($value,'like','%'.$search.'%');
                }
            });
        }
    }

    public function getRequestAndStoreMethod()
    {
        $request = new VozCreateRequest();
        $storeMethod = 'App\Http\Controllers\Module\Plan\VozController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod
        ];
    }

    public function getRepository()
    {
        return \App\Http\Repository\VoiseRepository::class;
    }
}

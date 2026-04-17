<?php

namespace App\Models;

use App\Http\Requests\module\plan\CustomCreateRequest;
use App\Models\Interface\PackageInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Custom extends BaseModel implements PackageInterface
{
    use HasFactory;
    protected $guarded = [];

    const MULTIPLE_RELATIONS = [
        'partners' => 'partners',
        'types_of_billing' => 'billings',
        'rates_to_change' => 'plan_custom_client'
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

    public function plan_custom_client(){
        return $this->belongsToMany(
            Custom::class,
            'plan_custom_client',
            'custom_id',
            'tarifa_custom_id'
        );
    }

    public function client_custom_services(){
        return $this->hasMany('App\Models\ClientCustomService');
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
        $request = new CustomCreateRequest();
        $storeMethod = 'App\Http\Controllers\Module\Plan\CustomController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod
        ];
    }

    public function getRepository()
    {
        return \App\Http\Repository\CustomRepository::class;
    }
}

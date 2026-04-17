<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyInformation extends Model
{
    use HasFactory;

    public static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->created_by = auth()->user() ? auth()->user()->id : 0;
            $obj->updated_by = auth()->user() ? auth()->user()->id : 0;
        });
        static::updating(function ($obj) {
            $obj->updated_by = auth()->user() ? auth()->user()->id : 0;
        });
    }


    protected $fillable = [
        'company_name',
        'company_postal_code',
        'country',
        'colony_id',
        'state_id',
        'municipality_id',
        'email',
        'atention_client_phone',
        'rfc',
        'iva',
        'bank_name',
        'bank_account',
        'cominion_partner',
        'created_by',
        'updated_by',
        'logo',
        'url_logo',
        'url_portal'
    ];

    protected $appends = ['state_name','colony_name','municipality_name'];


    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function colony()
    {
        return $this->belongsTo(Colony::class);
    }

    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    public function getStateNameAttribute()
    {
        $state = $this->state()->first();
        if ($state) {
            return $this->state()->first()->name;
        }
        return null;
    }

    public function getColonyNameAttribute()
    {
        $colony = $this->colony()->first();
        if ($colony) {
            return $this->colony()->first()->name;
        }
        return null;
    }


    public function getMunicipalityNameAttribute()
    {
        $municipality = $this->municipality()->first();
        if ($municipality) {
            return $this->municipality()->first()->name;
        }
        return null;
    }
}

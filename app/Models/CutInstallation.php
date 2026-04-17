<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CutInstallation extends BaseModel
{
    use HasFactory;
    protected $table = 'cut_installations';

    protected $fillable = [
        'service_amount',
        'installation_cost',
        'warranty_cost',
        'constance',
        'activated',
        'box_id',
        'client_id',
        'technical_id',
        'branch_id',
        'comments',
        'created_by'
    ];

    protected $appends = ['loading', 'branch_str', 'client_str', 'technical_str'];

    protected $casts = [
        'activated' => 'boolean'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->created_by = auth()->user()->id;
        });
    }

    public function box()
    {
        return $this->belongsTo(CutBox::class, 'box_id');
    }

    public function client()
    {
        return $this->belongsTo(ClientMainInformation::class, 'client_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function technical()
    {
        return $this->belongsTo(User::class, 'technical_id');
    }

    public function branch()
    {
        return $this->belongsTo(Sucursal::class, 'branch_id');
    }

    public function getLoadingAttribute()
    {
        return false;
    }

    public function getBranchStrAttribute()
    {
        return $this->branch()->first()->name ?? null;
    }

    public function getClientStrAttribute()
    {
        return sprintf(
            '%d - %s',
            $this->client->client_id,
            $this->client->client_name_with_fathers_names
        );
    }

    public function getTechnicalStrAttribute()
    {
        return $this->technical()->first()->name ?? null;
    }
}

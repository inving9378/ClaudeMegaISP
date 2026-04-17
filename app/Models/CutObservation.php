<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CutObservation extends BaseModel
{
    use HasFactory;
    protected $table = 'cuts_observations';

    protected $fillable = [
        'comment',
        'created_by',
        'box_id'
    ];

    protected $appends = ['created_by_str', 'lines', 'loading'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->created_by = auth()->user()->id;
        });
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function box()
    {
        return $this->belongsTo(CutBox::class, 'box_id');
    }

    public function getCreatedByStrAttribute()
    {
        return $this->createdByUser->getClientNameWithFathersNamesAttribute();
    }

    public function getLinesAttribute()
    {
        return 2;
    }

    public function getLoadingAttribute()
    {
        return false;
    }
}

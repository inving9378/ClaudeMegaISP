<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClientPlanPromotion extends BaseModel
{
    use HasFactory;

    protected $fillable = ['client_id', 'before_download_profile', 'current_download_profile', 'before_upload_profile', 'current_upload_profile', 'end_at'];

    protected $casts = [
        'created_at' => 'date',
        'end_at' => 'date'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeCaduced($query)
    {
        return $query->whereRaw('end_at < CURDATE()');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

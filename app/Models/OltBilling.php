<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltBilling extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $fillable = [
        'name',
        'status',
        'end_subscription',
        'last_synced_at'
    ];

    protected $appends = ['last_synced_at_humans', 'end_subscription_days'];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function getLastSyncedAtHumansAttribute()
    {
        return $this->last_synced_at->diffForHumans();
    }

    public function getEndSubscriptionDaysAttribute()
    {
        $end = Carbon::createFromFormat('d-M-Y', $this->end_subscription);
        return Carbon::today()->diffInDays($end, false);
    }
}

<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoProjectActivity extends Model
{
    protected $table = 'talento_project_activities';
    public $timestamps = false;

    protected $fillable = ['project_id', 'activity_type_id', 'planned_quantity', 'location_notes', 'created_by'];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'created_at'       => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(TalentoProject::class, 'project_id');
    }

    public function activityType()
    {
        return $this->belongsTo(TalentoActivityType::class, 'activity_type_id');
    }

    public function reports()
    {
        return $this->hasMany(TalentoProjectActivityReport::class, 'project_activity_id');
    }

    /** Sum of approved/capped quantities already reported */
    public function approvedTotal(): float
    {
        return (float) $this->reports()
            ->whereIn('status', ['approved', 'capped'])
            ->sum('approved_quantity');
    }

    /** Remaining capacity (anti-encimar guard) */
    public function remaining(): float
    {
        return max(0.0, (float)$this->planned_quantity - $this->approvedTotal());
    }

    /** Progress percentage 0-100 */
    public function progressPct(): float
    {
        if ((float)$this->planned_quantity <= 0) return 0.0;
        return round(min(100.0, $this->approvedTotal() / (float)$this->planned_quantity * 100), 1);
    }
}

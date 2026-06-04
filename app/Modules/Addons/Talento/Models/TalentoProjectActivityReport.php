<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TalentoProjectActivityReport extends Model
{
    protected $table = 'talento_project_activity_reports';
    public $timestamps = false;

    protected $fillable = [
        'project_activity_id', 'reported_by', 'quantity',
        'report_date', 'status', 'approved_quantity', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'approved_quantity' => 'decimal:4',
        'report_date'       => 'date',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public function projectActivity()
    {
        return $this->belongsTo(TalentoProjectActivity::class, 'project_activity_id');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function participants()
    {
        return $this->hasMany(TalentoActivityReportParticipant::class, 'report_id');
    }
}

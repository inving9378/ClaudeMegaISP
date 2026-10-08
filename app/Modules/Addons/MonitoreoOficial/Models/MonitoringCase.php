<?php

namespace App\Modules\Addons\MonitoreoOficial\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringCase extends Model
{
    protected $table = 'monitoring_cases';

    protected $fillable = [
        'source_id', 'name', 'authority', 'case_number', 'registration_number',
        'reference_number', 'class_code', 'current_legal_status', 'monitoring_status',
        'metadata', 'is_demo', 'last_checked_at', 'next_check_at', 'last_change_at', 'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_demo' => 'boolean',
        'last_checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'last_change_at' => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(MonitoringSource::class, 'source_id');
    }

    public function documents()
    {
        return $this->hasMany(MonitoringCaseDocument::class, 'case_id')->orderByDesc('id');
    }
}

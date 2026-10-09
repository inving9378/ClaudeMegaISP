<?php

namespace App\Modules\Addons\MonitoreoOficial\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringCaseDocument extends Model
{
    protected $table = 'monitoring_case_documents';

    protected $fillable = [
        'case_id', 'original_filename', 'stored_path', 'file_hash',
        'file_size', 'mime_type', 'notes', 'uploaded_by',
        'ai_summary', 'ai_change_summary', 'ai_has_relevant_change', 'ai_analyzed_at', 'ai_error',
    ];

    protected $casts = [
        'ai_has_relevant_change' => 'boolean',
        'ai_analyzed_at' => 'datetime',
    ];

    public function case()
    {
        return $this->belongsTo(MonitoringCase::class, 'case_id');
    }

    public function uploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}

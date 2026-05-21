<?php

namespace App\Modules\Addons\SmartImportExport\Models;

use App\Models\BaseModel;

class ImportExportLog extends BaseModel
{
    protected $table = 'import_export_logs';

    protected $fillable = [
        'type',
        'filename',
        'format',
        'status',
        'modules_selected',
        'fields_selected',
        'ai_analysis',
        'output_path',
        'job_id',
        'records_processed',
        'records_failed',
        'error_message',
        'encrypted',
        'admin_user',
    ];

    protected $casts = [
        'modules_selected'  => 'array',
        'fields_selected'   => 'array',
        'ai_analysis'       => 'array',
        'encrypted'         => 'boolean',
        'records_processed' => 'integer',
        'records_failed'    => 'integer',
    ];

    public function markRunning(?string $jobId = null): void
    {
        $this->update(['status' => 'running'] + ($jobId ? ['job_id' => $jobId] : []));
    }

    public function markCompleted(array $extra = []): void
    {
        $this->update(['status' => 'completed'] + $extra);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $message,
        ]);
    }
}

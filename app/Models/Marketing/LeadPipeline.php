<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadPipeline extends Model
{
    protected $table = 'marketing_lead_pipeline';

    protected $fillable = [
        'lead_id', 'pipeline_id', 'stage_id', 'position', 'entered_stage_at', 'exited_stage_at',
    ];

    protected $casts = [
        'entered_stage_at' => 'datetime',
        'exited_stage_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeploymentLog extends Model
{
    protected $table = 'deployment_logs';

    protected $fillable = [
        'release_id', 'triggered_by', 'status', 'steps', 'payload',
        'started_at', 'finished_at', 'duration_seconds',
        'error_message', 'rollback_to_version',
    ];

    protected $casts = [
        'steps'       => 'array',
        'payload'     => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function release()
    {
        return $this->belongsTo(Release::class);
    }

    public function triggeredBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'triggered_by');
    }

    /**
     * Actualiza un paso por su key y persiste el JSON de steps.
     * Usado desde DeploymentService para reflejar progreso en tiempo real.
     */
    public function updateStep(string $key, array $attrs): void
    {
        $steps = $this->steps ?? [];
        foreach ($steps as &$step) {
            if ($step['key'] === $key) {
                $step = array_merge($step, $attrs);
                break;
            }
        }
        unset($step);
        $this->steps = $steps;
        $this->saveQuietly();
    }
}

<?php

namespace App\Modules\Core\ModuleManager\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationLog extends Model
{
    protected $table = 'migration_logs';

    protected $fillable = [
        'module_slug',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'status',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost_usd' => 'decimal:6',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** Claude Sonnet pricing (per million tokens, as of 2026-05). */
    public const COST_INPUT_PER_M = 3.0;
    public const COST_OUTPUT_PER_M = 15.0;

    public static function computeCost(int $inputTokens, int $outputTokens): float
    {
        return round(
            ($inputTokens / 1_000_000) * self::COST_INPUT_PER_M
            + ($outputTokens / 1_000_000) * self::COST_OUTPUT_PER_M,
            6
        );
    }
}

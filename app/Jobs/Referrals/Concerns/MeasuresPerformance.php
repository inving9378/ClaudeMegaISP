<?php

namespace App\Jobs\Referrals\Concerns;

use Illuminate\Support\Facades\DB;
use Throwable;

trait MeasuresPerformance
{
    private float $startTime;
    private int   $recordsProcessed = 0;

    protected function startMeasure(): void
    {
        $this->startTime = microtime(true);
    }

    protected function countProcessed(int $count): void
    {
        $this->recordsProcessed += $count;
    }

    protected function recordSuccess(array $context = []): void
    {
        $this->writeMetric('success', $context);
    }

    protected function recordFailure(Throwable $e, array $context = []): void
    {
        $this->writeMetric('failed', array_merge($context, ['error' => $e->getMessage()]));
    }

    private function writeMetric(string $status, array $context): void
    {
        $durationMs = (int) round((microtime(true) - ($this->startTime ?? microtime(true))) * 1000);

        try {
            DB::table('referral_job_metrics')->insert([
                'job_class'          => class_basename(static::class),
                'status'             => $status,
                'duration_ms'        => $durationMs,
                'records_processed'  => $this->recordsProcessed,
                'context'            => empty($context) ? null : json_encode($context),
                'ran_at'             => now(),
            ]);
        } catch (\Throwable) {
            // Telemetry must never break the job
        }
    }
}

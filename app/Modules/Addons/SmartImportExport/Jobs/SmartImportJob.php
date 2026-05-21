<?php

namespace App\Modules\Addons\SmartImportExport\Jobs;

use App\Modules\Addons\SmartImportExport\Models\ImportExportLog;
use App\Modules\Addons\SmartImportExport\Services\SmartImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmartImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public string $jobId,
        public array $datasets,
        public array $options = [],
        public ?int $userId = null,
        public ?int $logId = null,
    ) {}

    public static function statusKey(string $jobId): string
    {
        return 'smart_import:status:' . $jobId;
    }

    public static function setStatus(string $jobId, array $payload): void
    {
        Cache::put(self::statusKey($jobId), $payload, now()->addHours(6));
    }

    public static function getStatus(string $jobId): array
    {
        return Cache::get(self::statusKey($jobId)) ?? [
            'state'    => 'unknown',
            'progress' => 0,
            'log'      => [],
        ];
    }

    public function handle(SmartImportService $service): void
    {
        $totalTables = max(1, count($this->datasets));
        $processed = 0;
        $log = [];
        $perTable = [];

        self::setStatus($this->jobId, [
            'state'    => 'running',
            'progress' => 0,
            'log'      => ['Iniciando importación...'],
            'tables'   => array_keys($this->datasets),
        ]);

        foreach ($this->datasets as $table => $rows) {
            $log[] = "Procesando `{$table}` (" . count($rows) . " registros)...";
            self::setStatus($this->jobId, [
                'state'    => 'running',
                'progress' => (int) round(($processed / $totalTables) * 100),
                'log'      => $log,
                'tables'   => array_keys($this->datasets),
                'current'  => $table,
            ]);

            try {
                $summary = $service->executeImport(
                    [$table => $rows],
                    [$table => $this->options[$table] ?? []]
                );
                $perTable[$table] = $summary[$table] ?? ['imported' => 0, 'skipped' => 0, 'errors' => 0];
                $log[] = sprintf(
                    "✓ `%s`: %d importados, %d omitidos, %d errores",
                    $table,
                    $perTable[$table]['imported'] ?? 0,
                    $perTable[$table]['skipped'] ?? 0,
                    $perTable[$table]['errors'] ?? 0
                );
            } catch (Throwable $e) {
                Log::error('SmartImportJob table error: ' . $e->getMessage());
                $log[] = "✗ `{$table}`: error " . $e->getMessage();
                $perTable[$table] = ['imported' => 0, 'skipped' => count($rows), 'errors' => count($rows)];
            }

            $processed++;
        }

        $totals = array_reduce($perTable, function ($carry, $row) {
            $carry['imported'] += $row['imported'] ?? 0;
            $carry['skipped']  += $row['skipped'] ?? 0;
            $carry['errors']   += $row['errors'] ?? 0;
            return $carry;
        }, ['imported' => 0, 'skipped' => 0, 'errors' => 0]);

        self::setStatus($this->jobId, [
            'state'     => 'completed',
            'progress'  => 100,
            'log'       => array_merge($log, ['Importación finalizada.']),
            'tables'    => array_keys($this->datasets),
            'per_table' => $perTable,
            'totals'    => $totals,
        ]);

        $this->updateLog([
            'status'            => 'completed',
            'records_processed' => $totals['imported'] ?? 0,
            'records_failed'    => $totals['errors'] ?? 0,
        ]);
    }

    public function failed(Throwable $e): void
    {
        self::setStatus($this->jobId, [
            'state'    => 'failed',
            'progress' => 0,
            'log'      => ['Importación abortada: ' . $e->getMessage()],
            'error'    => $e->getMessage(),
        ]);

        $this->updateLog([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }

    private function updateLog(array $attrs): void
    {
        if (!$this->logId) {
            return;
        }
        $log = ImportExportLog::find($this->logId);
        if ($log) {
            $log->update($attrs);
        }
    }
}

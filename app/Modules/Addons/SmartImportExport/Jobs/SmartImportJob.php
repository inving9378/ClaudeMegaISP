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

    /**
     * El job NO carga $datasets en su payload (eso causaba OOM al serializar
     * dumps grandes en Queue::createPayload, aún con QUEUE_CONNECTION=sync).
     * En su lugar recibe el token y rehidrata desde el cache dentro de handle().
     */
    public function __construct(
        public string $jobId,
        public string $token,
        public array $options = [],
        public ?int $userId = null,
        public ?int $logId = null,
    ) {}

    private static function analysisCacheKey(string $token): string
    {
        return 'smart_import:analysis:' . $token;
    }

    public static function executionCacheKey(string $jobId): string
    {
        return 'smart_import:execute:' . $jobId;
    }

    public static function storeExecutionContext(string $jobId, array $payload): void
    {
        Cache::put(self::executionCacheKey($jobId), $payload, now()->addHours(6));
    }

    public static function getExecutionContext(string $jobId): ?array
    {
        $payload = Cache::get(self::executionCacheKey($jobId));
        return is_array($payload) ? $payload : null;
    }

    public static function forgetExecutionContext(string $jobId): void
    {
        Cache::forget(self::executionCacheKey($jobId));
    }

    public static function statusKey(string $jobId): string
    {
        return 'smart_import:status:' . $jobId;
    }

    public static function setStatus(string $jobId, array $payload, ?int $logId = null): void
    {
        Cache::put(self::statusKey($jobId), $payload, now()->addHours(6));
        self::persistStatus($jobId, $payload, $logId);
    }

    public static function getStatus(string $jobId): array
    {
        $persisted = self::persistedStatus($jobId);
        if ($persisted) {
            return $persisted;
        }

        return Cache::get(self::statusKey($jobId)) ?? [
            'state'    => 'unknown',
            'progress' => 0,
            'log'      => [],
        ];
    }

    public function handle(SmartImportService $service): void
    {
        // Salvaguarda: parseo + intersect_key sobre 226 tablas puede picar fuerte.
        // 2G es holgado y se libera al terminar el request.
        ini_set('memory_limit', '2G');

        $cacheKey = self::analysisCacheKey($this->token);
        $analysis = Cache::get($cacheKey);
        if (!$analysis) {
            throw new \RuntimeException('Token de análisis expirado o no encontrado: ' . $this->token);
        }
        $analysisFile = $analysis['file'] ?? null;

        // Rehidratar el mapa de columnas del DUMP en el service — sanitizeRow
        // lo necesita para schema-drift-safe array_combine cuando el INSERT
        // del dump no trae lista de columnas (mysqldump default).
        $service->setDumpColumns($analysis['dump_columns'] ?? []);
        $service->setSourceTableSchemas($analysis['source_tables'] ?? []);
        $service->setActingUserId($this->userId);
        $format = $analysis['format'] ?? null;
        $analysisTables = array_values(array_unique(array_column($analysis['report'] ?? [], 'table')));

        if (in_array($format, ['sql', 'zip'], true)) {
            $tablesForStatus = $analysisTables;
            $totalTables = max(1, count($tablesForStatus));
            $processed = 0;
            $log = [];

            self::setStatus($this->jobId, [
                'state'    => 'running',
                'progress' => 0,
                'log'      => ['Iniciando importación...'],
                'tables'   => $tablesForStatus,
            ], $this->logId);

            try {
                $executor = $format === 'zip'
                    ? [$service, 'executeZipImportFromAnalysis']
                    : [$service, 'executeSqlImportFromAnalysis'];

                $perTable = call_user_func(
                    $executor,
                    $analysis,
                    $this->options,
                    function (string $table) use (&$log, &$processed, &$totalTables, &$tablesForStatus): void {
                        if (!in_array($table, $tablesForStatus, true)) {
                            $tablesForStatus[] = $table;
                            $totalTables = max($totalTables, count($tablesForStatus));
                        }
                        $log[] = "Procesando `{$table}`...";
                        self::setStatus($this->jobId, [
                            'state'    => 'running',
                            'progress' => (int) round(($processed / max(1, $totalTables)) * 100),
                            'log'      => $log,
                            'tables'   => $tablesForStatus,
                            'current'  => $table,
                        ], $this->logId);
                        $processed++;
                    }
                );

                foreach ($tablesForStatus as $table) {
                    $row = $perTable[$table] ?? ['imported' => 0, 'skipped' => 0, 'errors' => 0];
                    $log[] = sprintf(
                        "✓ `%s`: %d importados, %d omitidos, %d errores",
                        $table,
                        $row['imported'] ?? 0,
                        $row['skipped'] ?? 0,
                        $row['errors'] ?? 0
                    );
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
                    'tables'    => $tablesForStatus,
                    'per_table' => $perTable,
                    'totals'    => $totals,
                ], $this->logId);

                $this->updateLog([
                    'status'            => 'completed',
                    'records_processed' => $totals['imported'] ?? 0,
                    'records_failed'    => $totals['errors'] ?? 0,
                ]);

                return;
            } finally {
                if ($analysisFile) {
                    $service->cleanup($analysisFile);
                }
                Cache::forget($cacheKey);
            }
        }

        $datasets = $service->loadDatasetsForExecution($analysis);

        $totalTables = max(1, count($datasets));
        $processed = 0;
        $log = [];
        $perTable = [];

        self::setStatus($this->jobId, [
            'state'    => 'running',
            'progress' => 0,
            'log'      => ['Iniciando importación...'],
            'tables'   => array_keys($datasets),
        ], $this->logId);

        try {
            foreach ($datasets as $table => $rows) {
                $log[] = "Procesando `{$table}` (" . count($rows) . " registros)...";
                self::setStatus($this->jobId, [
                    'state'    => 'running',
                    'progress' => (int) round(($processed / $totalTables) * 100),
                    'log'      => $log,
                    'tables'   => array_keys($datasets),
                    'current'  => $table,
                ], $this->logId);

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
                'tables'    => array_keys($datasets),
                'per_table' => $perTable,
                'totals'    => $totals,
            ], $this->logId);

            $this->updateLog([
                'status'            => 'completed',
                'records_processed' => $totals['imported'] ?? 0,
                'records_failed'    => $totals['errors'] ?? 0,
            ]);
        } finally {
            // Idempotente: el archivo temporal y el cache se limpian aún si handle() falló.
            if ($analysisFile) {
                $service->cleanup($analysisFile);
            }
            Cache::forget($cacheKey);
        }
    }

    public function failed(Throwable $e): void
    {
        self::setStatus($this->jobId, [
            'state'    => 'failed',
            'progress' => 0,
            'log'      => ['Importación abortada: ' . $e->getMessage()],
            'error'    => $e->getMessage(),
        ], $this->logId);

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

    private static function persistStatus(string $jobId, array $payload, ?int $logId = null): void
    {
        $log = $logId
            ? ImportExportLog::find($logId)
            : ImportExportLog::findByJobId($jobId);

        if (!$log) {
            return;
        }

        $log->storeRuntimeStatus($payload);
    }

    private static function persistedStatus(string $jobId): ?array
    {
        $log = ImportExportLog::findByJobId($jobId);
        if (!$log) {
            return null;
        }

        $runtimeStatus = $log->runtimeStatus();
        if ($runtimeStatus) {
            return $runtimeStatus;
        }

        $stateMap = [
            'pending'   => 'queued',
            'queued'    => 'queued',
            'running'   => 'running',
            'completed' => 'completed',
            'failed'    => 'failed',
        ];

        $state = $stateMap[$log->status] ?? null;
        if (!$state) {
            return null;
        }

        $payload = [
            'state'    => $state,
            'progress' => $state === 'completed' ? 100 : 0,
            'log'      => [],
        ];

        if ($state === 'completed') {
            $payload['totals'] = [
                'imported' => (int) ($log->records_processed ?? 0),
                'skipped'  => 0,
                'errors'   => (int) ($log->records_failed ?? 0),
            ];
        }

        if ($state === 'failed' && $log->error_message) {
            $payload['error'] = $log->error_message;
            $payload['log'] = ['Importación abortada: ' . $log->error_message];
        }

        return $payload;
    }
}

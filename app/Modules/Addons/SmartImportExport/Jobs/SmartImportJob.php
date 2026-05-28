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
        public bool $truncateBefore = false,
    ) {}

    private static function analysisCacheKey(string $token): string
    {
        return 'smart_import:analysis:' . $token;
    }

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
        // Salvaguarda: parseo + intersect_key sobre 226 tablas puede picar fuerte.
        // 2G es holgado y se libera al terminar el request.
        set_time_limit(0);
        ini_set('memory_limit', '8912M');

        $cacheKey = self::analysisCacheKey($this->token);
        $analysis = Cache::get($cacheKey);
        if (!$analysis) {
            throw new \RuntimeException('Token de análisis expirado o no encontrado: ' . $this->token);
        }
        $datasets = $analysis['datasets'] ?? [];
        $analysisFile = $analysis['file'] ?? null;

        // Rehidratar el mapa de columnas del DUMP en el service — sanitizeRow
        // lo necesita para schema-drift-safe array_combine cuando el INSERT
        // del dump no trae lista de columnas (mysqldump default).
        $service->setDumpColumns($analysis['dump_columns'] ?? []);

        // Rehidratar el DDL completo de cada tabla — validateMapEntry() lo usa
        // para crear automáticamente tablas que no existen en la BD destino.
        $service->setDumpDDL($analysis['dump_ddl'] ?? []);

        // Setup único de sesión bulk: ordena tablas por FK, drop triggers,
        // precarga NOT NULL cols, activa FK_CHECKS=0 + unique_checks=0.
        // Se hace UNA sola vez aquí en lugar de repetirlo por cada tabla.
        $service->beginBulkSession($datasets);

        $totalTables = max(1, count($datasets));
        $processed = 0;
        $log = [];
        $perTable = [];

        self::setStatus($this->jobId, [
            'state'    => 'running',
            'progress' => 0,
            'log'      => ['Iniciando importación...'],
            'tables'   => array_keys($datasets),
        ]);

        try {
            foreach ($datasets as $table => $rows) {
                $log[] = "Procesando `{$table}` (" . count($rows) . " registros)...";
                self::setStatus($this->jobId, [
                    'state'    => 'running',
                    'progress' => (int) round(($processed / $totalTables) * 100),
                    'log'      => $log,
                    'tables'   => array_keys($datasets),
                    'current'  => $table,
                ]);

                try {
                    $summary = $service->executeImport(
                        [$table => $rows],
                        [$table => $this->options[$table] ?? []],
                        $this->truncateBefore
                    );
                    $perTable[$table] = $summary[$table] ?? ['imported' => 0, 'skipped' => 0, 'errors' => 0];
                    $entry = sprintf(
                        "✓ `%s`: %d importados, %d omitidos, %d errores",
                        $table,
                        $perTable[$table]['imported'] ?? 0,
                        $perTable[$table]['skipped'] ?? 0,
                        $perTable[$table]['errors'] ?? 0
                    );
                    if (!empty($perTable[$table]['backup'])) {
                        $entry .= " | backup → {$perTable[$table]['backup']}";
                    }
                    $log[] = $entry;
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
            ]);

            $this->updateLog([
                'status'            => 'completed',
                'records_processed' => $totals['imported'] ?? 0,
                'records_failed'    => $totals['errors'] ?? 0,
            ]);
        } finally {
            // Restaurar triggers y variables MySQL sin importar si el import falló.
            $service->endBulkSession();
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

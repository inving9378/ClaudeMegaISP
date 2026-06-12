<?php

namespace App\Console\Commands\Active;

use App\Modules\Addons\SmartImportExport\Models\ImportExportLog;
use App\Modules\Addons\SmartImportExport\Services\SmartImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AnalyzeSmartImportCommand extends Command
{
    protected $signature = 'smart-import:analyze {token : UUID del análisis pendiente}';
    protected $description = 'Analiza en background un archivo de Smart Import previamente subido';

    public function handle(SmartImportService $service): int
    {
        set_time_limit(0);
        ini_set('memory_limit', '8912M');

        $token      = (string) $this->argument('token');
        $pendingKey = 'smart_import:pending:' . $token;
        $errorKey   = 'smart_import:analysis_error:' . $token;
        $pending    = Cache::get($pendingKey);

        if (!$pending) {
            $this->error("Token no encontrado o expirado: {$token}");
            return self::FAILURE;
        }

        $storedPath = storage_path(SmartImportService::STORAGE_DIR . '/' . $pending['file']);

        if (!file_exists($storedPath)) {
            $this->fail($token, $pending, 'Archivo no encontrado en disco.', $errorKey, $pendingKey);
            return self::FAILURE;
        }

        // analyzeFile() espera un UploadedFile; el modo test=true omite la
        // validación "fue este archivo subido por HTTP?" para poder usar un
        // archivo que ya está en disco.
        $fakeFile = new UploadedFile($storedPath, $pending['file'], null, null, true);

        try {
            $analysis = $service->analyzeFile($fakeFile);
        } catch (Throwable $e) {
            report($e);
            $this->fail($token, $pending, $e->getMessage(), $errorKey, $pendingKey);
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        // analyzeFile genera su propio UUID interno y mueve el archivo a ese
        // nombre; el cache de análisis se guarda bajo el TOKEN ORIGINAL para
        // que el frontend pueda hacer polling con el mismo token que recibió.
        $analysisForCache = $analysis;
        $analysisForCache['datasets'] = ['migrations' => $analysis['datasets']['migrations'] ?? []];
        Cache::put('smart_import:analysis:' . $token, $analysisForCache, now()->addHours(6));
        Cache::forget($pendingKey);

        if ($logId = ($pending['log_id'] ?? null)) {
            $log = ImportExportLog::find($logId);
            if ($log) {
                $payload = $this->sanitize([
                    'token'      => $token,
                    'format'     => $analysis['format'],
                    'report'     => $analysis['report'],
                    'total_rows' => $analysis['total_rows'],
                ]);
                $log->update(['ai_analysis' => $payload]);
            }
        }

        $this->info("Análisis completado para token {$token}.");
        return self::SUCCESS;
    }

    private function fail(string $token, array $pending, string $message, string $errorKey, string $pendingKey): void
    {
        Cache::put($errorKey, $message, now()->addHour());
        Cache::forget($pendingKey);

        if ($logId = ($pending['log_id'] ?? null)) {
            ImportExportLog::find($logId)?->markFailed($message);
        }
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->sanitize($v), $value);
        }
        if (!is_string($value)) {
            return $value;
        }
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        $enc = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        $converted = mb_convert_encoding($value, 'UTF-8', $enc ?: 'Windows-1252');
        return mb_check_encoding($converted, 'UTF-8') ? $converted : mb_scrub($value, 'UTF-8');
    }
}

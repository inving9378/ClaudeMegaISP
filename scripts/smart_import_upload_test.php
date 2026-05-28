<?php

use App\Modules\Addons\SmartImportExport\Controllers\ImportExportController;
use App\Modules\Addons\SmartImportExport\Jobs\SmartImportJob;
use App\Modules\Addons\SmartImportExport\Models\ImportExportLog;
use App\Modules\Addons\SmartImportExport\Services\SmartImportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$startedAt = microtime(true);
$logPath = storage_path('logs/laravel.log');
$logOffset = is_file($logPath) ? filesize($logPath) : 0;
$tracePath = storage_path('logs/smart-import-upload-test-' . date('Ymd-His') . '.jsonl');

$args = parseArguments($argv);
if (!isset($args['file'])) {
    usage();
    exit(1);
}

$filePath = realpath($args['file']);
if (!$filePath || !is_file($filePath)) {
    line('ERROR', 'Archivo no encontrado: ' . $args['file']);
    exit(1);
}

$execute = (bool) ($args['execute'] ?? false);
$executeInline = (bool) ($args['execute-inline'] ?? false);
$mode = (string) ($args['mode'] ?? SmartImportService::GLOBAL_MODE_SMART);
$pollSeconds = max(1, (int) ($args['poll'] ?? 2));
$timeoutSeconds = max(0, (int) ($args['timeout'] ?? 0));
$tableModes = parseTableModes((string) ($args['table-mode'] ?? ''));

line('START', 'Smart Import upload test');
line('FILE', $filePath . ' (' . number_format(filesize($filePath)) . ' bytes)');
line('MODE', $mode);
line('EXECUTE', $execute ? 'controller/background' : ($executeInline ? 'inline job' : 'no'));
line('LOG_OFFSET', (string) $logOffset);
line('TRACE_FILE', $tracePath);

try {
    $uploadResult = callUpload($filePath);
    traceEvent('upload_response', $uploadResult);
    printUploadSummary($uploadResult);

    if (!($uploadResult['success'] ?? false)) {
        line('STOP', 'Upload falló; no se ejecuta import.');
        printNewLogLines($logPath, $logOffset);
        exit(2);
    }

    $token = (string) ($uploadResult['token'] ?? '');
    $logId = (int) ($uploadResult['log_id'] ?? 0);
    $report = is_array($uploadResult['report'] ?? null) ? $uploadResult['report'] : [];

    line('UPLOAD_OK', 'token=' . $token . ' log_id=' . $logId . ' tables=' . count($report) . ' total_rows=' . ($uploadResult['total_rows'] ?? 'n/a'));
    printReportSummary($report);

    if ($executeInline) {
        executeInline($token, $logId, $mode, $tableModes);
    } elseif ($execute) {
        executeViaController($token, $mode, $tableModes, $pollSeconds, $timeoutSeconds);
    }

    printLatestLog($logId);
    printNewLogLines($logPath, $logOffset);

    line('DONE', sprintf('Finalizado en %.2fs', microtime(true) - $startedAt));
} catch (Throwable $e) {
    line('EXCEPTION', get_class($e) . ': ' . $e->getMessage());
    line('TRACE', $e->getFile() . ':' . $e->getLine());
    printNewLogLines($logPath, $logOffset);
    exit(3);
}

function callUpload(string $filePath): array
{
    $uploadedFile = new UploadedFile(
        $filePath,
        basename($filePath),
        null,
        null,
        true
    );

    $request = Request::create('/configuracion/smart-import/upload', 'POST', [], [], [
        'file' => $uploadedFile,
    ]);

    /** @var ImportExportController $controller */
    $controller = app(ImportExportController::class);
    $response = $controller->upload($request);

    return decodeJsonResponse($response->getContent(), $response->getStatusCode());
}

function executeViaController(string $token, string $mode, array $tableModes, int $pollSeconds, int $timeoutSeconds): void
{
    $request = Request::create('/configuracion/smart-import/execute', 'POST', [
        'token' => $token,
        'options' => [
            'global_mode' => $mode,
            'table_modes' => $tableModes,
        ],
    ]);

    /** @var ImportExportController $controller */
    $controller = app(ImportExportController::class);
    $response = $controller->execute($request);
    $payload = decodeJsonResponse($response->getContent(), $response->getStatusCode());
    traceEvent('execute_response', $payload);
    printJson('EXECUTE_RESPONSE', $payload);

    if (!($payload['success'] ?? false) || empty($payload['job_id'])) {
        return;
    }

    $jobId = (string) $payload['job_id'];
    $started = time();
    do {
        $status = SmartImportJob::getStatus($jobId);
        traceEvent('status', [
            'job_id' => $jobId,
            'status' => compactStatus($status),
        ]);
        printStatus($jobId, $status);

        $state = (string) ($status['state'] ?? 'unknown');
        if (in_array($state, ['completed', 'failed'], true)) {
            return;
        }

        if ($timeoutSeconds > 0 && (time() - $started) >= $timeoutSeconds) {
            line('TIMEOUT', 'Se detuvo el polling; el proceso puede seguir en background. job_id=' . $jobId);
            return;
        }

        sleep($pollSeconds);
    } while (true);
}

function executeInline(string $token, int $logId, string $mode, array $tableModes): void
{
    $jobId = (string) Str::uuid();
    SmartImportJob::setStatus($jobId, [
        'state' => 'queued',
        'progress' => 0,
        'log' => ['Importación inline encolada por script de prueba.'],
    ], $logId ?: null);

    if ($logId > 0) {
        ImportExportLog::find($logId)?->markRunning($jobId);
    }

    line('INLINE_JOB', 'job_id=' . $jobId);

    $job = new SmartImportJob(
        jobId: $jobId,
        token: $token,
        options: [
            'global_mode' => $mode,
            'table_modes' => $tableModes,
        ],
        userId: auth()->id(),
        logId: $logId ?: null,
    );

    $job->handle(app(SmartImportService::class));
    $status = SmartImportJob::getStatus($jobId);
    traceEvent('status', [
        'job_id' => $jobId,
        'status' => compactStatus($status),
    ]);
    printStatus($jobId, $status);
}

function decodeJsonResponse(string $content, int $status): array
{
    $payload = json_decode($content, true);
    if (!is_array($payload)) {
        return [
            'success' => false,
            'status' => $status,
            'raw' => $content,
        ];
    }

    $payload['_http_status'] = $status;
    return $payload;
}

function printReportSummary(array $report): void
{
    $rows = [];
    foreach (array_slice($report, 0, 20) as $item) {
        $rows[] = [
            'table' => $item['table'] ?? null,
            'records' => $item['records'] ?? null,
            'mode' => $item['mode'] ?? null,
            'descriptor_source' => $item['descriptor_source'] ?? null,
            'identity_source' => $item['identity_source'] ?? null,
            'warnings' => $item['warnings'] ?? [],
        ];
    }

    printJson('REPORT_TOP_20', $rows);
}

function printUploadSummary(array $payload): void
{
    printJson('UPLOAD_RESPONSE', [
        '_http_status' => $payload['_http_status'] ?? null,
        'success' => $payload['success'] ?? false,
        'message' => $payload['message'] ?? null,
        'log_id' => $payload['log_id'] ?? null,
        'token' => $payload['token'] ?? null,
        'format' => $payload['format'] ?? null,
        'total_rows' => $payload['total_rows'] ?? null,
        'report_tables' => is_array($payload['report'] ?? null) ? count($payload['report']) : null,
        'errors' => $payload['errors'] ?? null,
    ]);
}

function printStatus(string $jobId, array $status): void
{
    $totals = $status['totals'] ?? null;
    $line = sprintf(
        'job_id=%s state=%s progress=%s current=%s',
        $jobId,
        $status['state'] ?? 'unknown',
        $status['progress'] ?? 0,
        $status['current'] ?? '-'
    );

    if (is_array($totals)) {
        $line .= sprintf(
            ' imported=%d skipped=%d errors=%d',
            $totals['imported'] ?? 0,
            $totals['skipped'] ?? 0,
            $totals['errors'] ?? 0
        );
    }

    line('STATUS', $line);
}

function printLatestLog(int $logId): void
{
    if ($logId <= 0) {
        return;
    }

    $log = ImportExportLog::find($logId);
    if (!$log) {
        line('IMPORT_LOG', 'No existe log_id=' . $logId);
        return;
    }

    $payload = [
        'id' => $log->id,
        'filename' => $log->filename,
        'format' => $log->format,
        'status' => $log->status,
        'job_id' => $log->job_id,
        'records_processed' => $log->records_processed,
        'records_failed' => $log->records_failed,
        'error_message' => $log->error_message,
        'created_at' => (string) $log->created_at,
        'updated_at' => (string) $log->updated_at,
    ];

    traceEvent('import_log', $payload);
    printJson('IMPORT_LOG', $payload);
}

function printNewLogLines(string $logPath, int $offset): void
{
    if (!is_file($logPath)) {
        line('LARAVEL_LOG', 'No existe ' . $logPath);
        return;
    }

    $size = filesize($logPath);
    if ($size <= $offset) {
        line('LARAVEL_LOG', 'Sin líneas nuevas.');
        return;
    }

    $handle = fopen($logPath, 'rb');
    if (!$handle) {
        return;
    }

    fseek($handle, $offset);
    $count = 0;
    line('LARAVEL_LOG_BEGIN', 'offset=' . $offset . ' bytes=' . ($size - $offset));
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        echo abbreviateLogLine($line) . PHP_EOL;
        $count++;
        if ($count >= 300) {
            line('LARAVEL_LOG_TRUNCATED', 'Mostradas 300 líneas nuevas; revisa el archivo para el resto.');
            break;
        }
    }
    fclose($handle);
    line('LARAVEL_LOG_END', 'lines=' . $count);
}

function abbreviateLogLine(string $line): string
{
    $line = preg_replace('/\\s+/', ' ', $line) ?? $line;

    if (preg_match('/^(\\[[^\\]]+\\]).*(SmartImport[^:]+\\[[^\\]]+\\]: SQLSTATE\\[[^\\]]+\\]: [^(]+)/', $line, $match)) {
        return $match[1] . ' ' . trim($match[2]);
    }

    if (strlen($line) > 500) {
        return substr($line, 0, 500) . '...';
    }

    return $line;
}

function parseArguments(array $argv): array
{
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--')) {
            $arg = substr($arg, 2);
            if (str_contains($arg, '=')) {
                [$key, $value] = explode('=', $arg, 2);
                $args[$key] = $value;
            } else {
                $args[$arg] = true;
            }
            continue;
        }

        $args['file'] ??= $arg;
    }

    return $args;
}

function parseTableModes(string $raw): array
{
    if ($raw === '') {
        return [];
    }

    $modes = [];
    foreach (explode(',', $raw) as $pair) {
        if (!str_contains($pair, ':')) {
            continue;
        }

        [$table, $mode] = array_map('trim', explode(':', $pair, 2));
        if ($table !== '' && $mode !== '') {
            $modes[$table] = ['mode' => $mode];
        }
    }

    return $modes;
}

function printJson(string $label, mixed $payload): void
{
    line($label, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: 'null');
}

function traceEvent(string $event, mixed $payload): void
{
    global $tracePath;

    $row = [
        'ts' => date('c'),
        'event' => $event,
        'payload' => $payload,
    ];

    file_put_contents(
        $tracePath,
        (json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}') . PHP_EOL,
        FILE_APPEND
    );
}

function compactStatus(array $status): array
{
    return [
        'state' => $status['state'] ?? null,
        'progress' => $status['progress'] ?? null,
        'current' => $status['current'] ?? null,
        'tables_count' => is_array($status['tables'] ?? null) ? count($status['tables']) : null,
        'totals' => $status['totals'] ?? null,
        'error' => $status['error'] ?? null,
        'log_tail' => is_array($status['log'] ?? null) ? array_slice($status['log'], -10) : [],
    ];
}

function line(string $label, string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $label . ': ' . $message . PHP_EOL;
}

function usage(): void
{
    echo <<<'TXT'
Usage:
  php scripts/smart_import_upload_test.php /path/to/dump.zip [options]

Options:
  --mode=smart|force_source|skip_existing
  --execute               Ejecuta usando el flujo real del controlador y proceso background.
  --execute-inline        Ejecuta el SmartImportJob en el mismo proceso para depurar.
  --poll=2                Segundos entre status polling cuando se usa --execute.
  --timeout=0             Segundos máximos de polling; 0 = sin timeout.
  --table-mode=a:smart,b:force_source

Examples:
  php scripts/smart_import_upload_test.php storage/app/smart_import/test.sql
  php scripts/smart_import_upload_test.php /tmp/dump.zip --execute --timeout=120
  php scripts/smart_import_upload_test.php /tmp/dump.sql --execute-inline --mode=smart

TXT;
}

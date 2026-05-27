<?php
/**
 * Importa TODAS las tablas del ZIP de una sola pasada y reporta resultados.
 * Uso: php test_import_all.php [zip]
 */

$root = __DIR__;
require $root . '/vendor/autoload.php';
$app    = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Addons\SmartImportExport\Services\SmartImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

function out(string $msg): void { echo $msg . PHP_EOL; }
function sep(): void { echo str_repeat('─', 70) . PHP_EOL; }
function fail(string $msg): never { echo "\n[ERROR FATAL] {$msg}\n"; exit(1); }

// ── Resolver ZIP ──────────────────────────────────────────────────────────────
$zipArg  = $argv[1] ?? null;
$zipPath = $zipArg
    ? (str_starts_with($zipArg, '/') ? $zipArg : $root . '/' . $zipArg)
    : (glob($root . '/*.zip')[0] ?? null);
if (!$zipPath || !file_exists($zipPath)) fail("No encontré ningún *.zip");

sep();
out("ZIP     : " . basename($zipPath));
out("Fecha   : " . date('Y-m-d H:i:s'));
sep();

// ── 1. Parsear ZIP ────────────────────────────────────────────────────────────
out("Paso 1: Parseando ZIP...");
$tmp = sys_get_temp_dir() . '/smartimport_all_' . uniqid() . '.zip';
copy($zipPath, $tmp);

$service  = app(SmartImportService::class);
$analysis = $service->analyzeFile(new UploadedFile($tmp, basename($zipPath), null, null, true));
$datasets    = $analysis['datasets']    ?? [];
$dumpColumns = $analysis['dump_columns'] ?? [];
$service->setDumpColumns($dumpColumns);

$tables = array_keys($datasets);
sort($tables);
out(sprintf("  %d tablas encontradas en el ZIP", count($tables)));
sep();

// ── 2. Ejecutar import de todas las tablas ────────────────────────────────────
out("Paso 2: Ejecutando importación (action=replace, sin truncate)...");
out("");

$options = [];
foreach ($tables as $t) {
    $options[$t] = ['action' => 'replace'];
}

$summary = $service->executeImport($datasets, $options, false);

// ── 3. Reporte ────────────────────────────────────────────────────────────────
sep();
$totalImported = 0;
$totalSkipped  = 0;
$totalErrors   = 0;
$withErrors    = [];
$skipped       = [];   // tablas no mapeadas

foreach ($tables as $table) {
    $r = $summary[$table] ?? ['imported' => 0, 'skipped' => 0, 'errors' => 0];
    $totalImported += $r['imported'] ?? 0;
    $totalSkipped  += $r['skipped']  ?? 0;
    $totalErrors   += $r['errors']   ?? 0;

    if (!empty($r['reason'])) {
        $skipped[$table] = $r['reason'];
    } elseif (($r['errors'] ?? 0) > 0) {
        $withErrors[$table] = $r;
    }
}

out(sprintf("TOTALES: %d importados | %d omitidos | %d errores | %d tablas",
    $totalImported, $totalSkipped, $totalErrors, count($tables)));
sep();

// Tablas con errores
if ($withErrors) {
    out("TABLAS CON ERRORES (" . count($withErrors) . "):");
    foreach ($withErrors as $t => $r) {
        out(sprintf("  ✗ %-45s imp=%d skip=%d err=%d",
            $t, $r['imported'], $r['skipped'], $r['errors']));
    }
    sep();
} else {
    out("OK — ninguna tabla con errores de inserción.");
    sep();
}

// Tablas omitidas (no mapeadas en TABLE_MODULE_MAP)
if ($skipped) {
    out("TABLAS NO MAPEADAS (" . count($skipped) . ") — omitidas:");
    foreach ($skipped as $t => $reason) {
        out(sprintf("  · %-45s %s", $t, $reason));
    }
    sep();
}

// Todas las tablas — detalle completo
out("DETALLE COMPLETO:");
out(sprintf("  %-45s %8s %8s %8s", 'tabla', 'imp', 'skip', 'err'));
out(str_repeat('─', 70));
foreach ($tables as $t) {
    $r = $summary[$t] ?? ['imported' => 0, 'skipped' => 0, 'errors' => 0];
    $flag = ($r['errors'] ?? 0) > 0 ? ' ✗' : (isset($r['reason']) ? ' ·' : '');
    out(sprintf("  %-45s %8d %8d %8d%s",
        $t, $r['imported'] ?? 0, $r['skipped'] ?? 0, $r['errors'] ?? 0, $flag));
}
sep();

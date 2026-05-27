<?php

/**
 * Test de importación SmartImport — tabla configurable (default: modules)
 *
 * Corre directamente contra la BD del .env, sin migrate:fresh.
 *
 * Uso:
 *   php test_import_modules.php
 *   php test_import_modules.php modules
 *   php test_import_modules.php bundles
 *   php test_import_modules.php modules dump-otro.zip
 */

// ─── Bootstrap Laravel ───────────────────────────────────────────────────────
$root = __DIR__;
require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Addons\SmartImportExport\Services\SmartImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

// ─── Argumentos ──────────────────────────────────────────────────────────────
$table   = $argv[1] ?? 'modules';
$zipArg  = $argv[2] ?? null;

// ─── Helpers ─────────────────────────────────────────────────────────────────
function out(string $msg): void { echo $msg . "\n"; }
function sep(): void { echo str_repeat('─', 60) . "\n"; }
function fail(string $msg): never { echo "\n[ERROR FATAL] {$msg}\n"; exit(1); }

// ─── Resolver ZIP ────────────────────────────────────────────────────────────
if ($zipArg) {
    $zipPath = str_starts_with($zipArg, '/') ? $zipArg : $root . '/' . $zipArg;
} else {
    $zips = glob($root . '/*.zip');
    if (!$zips) fail("No encontré ningún *.zip en la raíz del proyecto.");
    $zipPath = $zips[0];
}

if (!file_exists($zipPath)) fail("ZIP no encontrado: {$zipPath}");

sep();
out("Tabla   : {$table}");
out("ZIP     : " . basename($zipPath));
sep();

// ─── 1. Parsear ZIP (copia temporal para no mover el original) ───────────────
out("Paso 1: Parseando ZIP...");

$tmp = sys_get_temp_dir() . '/smartimport_' . uniqid() . '.zip';
copy($zipPath, $tmp);

$fakeFile = new UploadedFile($tmp, basename($zipPath), 'application/zip', null, true);
$service  = app(SmartImportService::class);

$analysis    = $service->analyzeFile($fakeFile);
$datasets    = $analysis['datasets']    ?? [];
$dumpColumns = $analysis['dump_columns'] ?? [];

if (!array_key_exists($table, $datasets)) {
    $found = implode(', ', array_keys($datasets));
    fail("La tabla '{$table}' no está en el ZIP.\nTablas encontradas: {$found}");
}

$rows = array_values($datasets[$table]);
out(sprintf("  Filas en ZIP: %d", count($rows)));

// ─── 2. Estado actual en BD ──────────────────────────────────────────────────
out("\nPaso 2: Leyendo BD actual...");

if (!Illuminate\Support\Facades\Schema::hasTable($table)) {
    fail("La tabla '{$table}' no existe en la BD destino.");
}

$dbCount = DB::table($table)->count();
$dbMaxId = DB::table($table)->max('id') ?? 0;
$dbMinId = DB::table($table)->min('id') ?? 0;
$dbIds   = DB::table($table)->pluck('id')->map(fn($v) => (int)$v)->toArray();

out(sprintf("  Filas en BD : %d | id_min: %d | id_max: %d", $dbCount, $dbMinId, $dbMaxId));

// ─── 3. Análisis de colisiones ───────────────────────────────────────────────
out("\nPaso 3: Analizando colisiones por PK...");

$zipIds = [];
foreach ($rows as $row) {
    if (is_array($row) && isset($row['id'])) {
        $zipIds[] = (int)$row['id'];
    }
}
sort($zipIds);

$zipMinId   = $zipIds ? min($zipIds) : null;
$zipMaxId   = $zipIds ? max($zipIds) : null;
$collisions = array_intersect($zipIds, $dbIds);
$newIds     = array_diff($zipIds, $dbIds);

out(sprintf("  ZIP ids     : %d total | id_min: %s | id_max: %s",
    count($zipIds), $zipMinId ?? 'N/A', $zipMaxId ?? 'N/A'));
out(sprintf("  Colisiones  : %d (ids que ya existen en BD)", count($collisions)));
out(sprintf("  Nuevos      : %d (ids que no existen en BD)", count($newIds)));

if (count($collisions) > 0) {
    $preview = array_slice($collisions, 0, 15);
    out("  Ids colision: [" . implode(', ', $preview) . (count($collisions) > 15 ? '...' : '') . "]");
}

// ─── 4. Ejecutar el import ───────────────────────────────────────────────────
out("\nPaso 4: Ejecutando importación (action=replace)...");

$service->setDumpColumns($dumpColumns);

try {
    $summary = $service->executeImport(
        [$table => $rows],
        [$table => ['action' => 'replace']],
        false  // sin truncate
    );
} catch (Throwable $e) {
    fail("executeImport lanzó excepción: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

$result = $summary[$table] ?? [];

// ─── 5. Resultado ────────────────────────────────────────────────────────────
sep();
out("RESULTADO:");
out(sprintf("  importados : %d", $result['imported'] ?? 0));
out(sprintf("  omitidos   : %d", $result['skipped']  ?? 0));
out(sprintf("  errores    : %d", $result['errors']   ?? 0));

if (!empty($result['reason'])) {
    out("  razón      : " . $result['reason']);
}

// ─── 6. Diagnóstico si hay errores ──────────────────────────────────────────
$errors = $result['errors'] ?? 0;
if ($errors > 0) {
    sep();
    out("DIAGNOSTICO:");
    out("  Errores        : {$errors}");
    out("  Colisiones PK  : " . count($collisions));
    out("  conflict_keys para '{$table}': []  ← vacío, no detecta duplicados por 'id'");
    out("  → Las filas con ids existentes se intentaron INSERT → duplicate key 1062");
    out("  → Fix: usar upsert/updateOrInsert por PK cuando conflict_keys está vacío");

    // Ver en los logs de Laravel el último error
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $logLines = file($logFile, FILE_IGNORE_NEW_LINES);
        $relevant = array_filter($logLines, fn($l) => str_contains($l, $table) || str_contains($l, 'SmartImport'));
        $relevant = array_slice(array_values($relevant), -10);
        if ($relevant) {
            out("\nÚltimas líneas de log relevantes:");
            foreach ($relevant as $line) {
                out("  " . substr($line, 0, 200));
            }
        }
    }
} else {
    sep();
    out("OK — importación sin errores.");
}

sep();
out("Estado BD tras import:");
$afterCount = DB::table($table)->count();
$afterMaxId = DB::table($table)->max('id') ?? 0;
out(sprintf("  Filas: %d | id_max: %d", $afterCount, $afterMaxId));
sep();

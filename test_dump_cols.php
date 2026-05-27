<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Addons\SmartImportExport\Services\SmartImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

$zips = glob(__DIR__ . '/*.zip');
$tmp  = sys_get_temp_dir() . '/si_dc_' . uniqid() . '.zip';
copy($zips[0], $tmp);

$service  = app(SmartImportService::class);
$analysis = $service->analyzeFile(new UploadedFile($tmp, basename($zips[0]), null, null, true));

$tables = ['payments', 'transactions', 'tickets', 'olt_zones', 'documentation_submenus', 'packages'];

foreach ($tables as $t) {
    $dumpCols  = $analysis['dump_columns'][$t] ?? [];
    $localCols = Schema::getColumnListing($t);
    $rows      = $analysis['datasets'][$t] ?? [];
    $first     = $rows[0] ?? null;

    echo "=== $t ===\n";
    echo "  Dump cols  : " . implode(', ', $dumpCols) . "\n";
    echo "  Local cols : " . implode(', ', $localCols) . "\n";
    echo "  Rows in ZIP: " . count($rows) . "\n";

    // Mostrar primera fila normalizada
    if ($first !== null) {
        if (is_array($first) && array_is_list($first) && $dumpCols) {
            $first = array_combine($dumpCols, array_slice($first, 0, count($dumpCols)));
        }
        echo "  id en fila : " . ($first['id'] ?? 'NO TIENE id') . "\n";
    }
    echo "\n";
}

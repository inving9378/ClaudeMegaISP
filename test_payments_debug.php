<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Addons\SmartImportExport\Services\SmartImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$zips = glob(__DIR__ . '/*.zip');
$tmp  = sys_get_temp_dir() . '/si_pay_' . uniqid() . '.zip';
copy($zips[0], $tmp);

$service  = app(SmartImportService::class);
$analysis = $service->analyzeFile(new UploadedFile($tmp, basename($zips[0]), null, null, true));

$rows      = $analysis['datasets']['payments'] ?? [];
$dumpCols  = $analysis['dump_columns']['payments'] ?? [];
$service->setDumpColumns($analysis['dump_columns'] ?? []);

echo "Rows in ZIP: " . count($rows) . PHP_EOL;
echo "Dump cols  : " . implode(', ', $dumpCols) . PHP_EOL;
echo PHP_EOL;

// Simular normalizeRows con primeras 5 filas
$destCols = Schema::getColumnListing('payments');
echo "Dest cols  : " . implode(', ', $destCols) . PHP_EOL;
echo PHP_EOL;

// Revisar primeras 5 filas crudas
foreach (array_slice($rows, 0, 5) as $i => $row) {
    $islist = is_array($row) && array_is_list($row);
    echo "Fila $i raw tipo: " . ($islist ? "LISTA (".count($row)." vals)" : "ASOC") . PHP_EOL;
    if ($islist && $dumpCols && count($dumpCols) === count($row)) {
        $assoc = array_combine($dumpCols, $row);
        echo "  id normalizado: " . $assoc['id'] . PHP_EOL;
    } elseif (!$islist) {
        echo "  id: " . ($row['id'] ?? 'null') . PHP_EOL;
    } else {
        echo "  MISMATCH: dumpCols=" . count($dumpCols) . " row=" . count($row) . PHP_EOL;
    }
}

echo PHP_EOL;
// Verificar que buildExistingIndex encuentre las filas
$sampleIds = [];
foreach (array_slice($rows, 0, 10) as $row) {
    if ($dumpCols && is_array($row) && array_is_list($row)) {
        $row = array_combine($dumpCols, array_slice($row, 0, count($dumpCols)));
    }
    if (isset($row['id'])) $sampleIds[] = $row['id'];
}
echo "IDs muestra del dump: " . implode(', ', $sampleIds) . PHP_EOL;
$found = DB::table('payments')->whereIn('id', $sampleIds)->pluck('id')->toArray();
echo "IDs encontrados en BD: " . implode(', ', $found) . PHP_EOL;
echo "Coincidencias: " . count($found) . "/" . count($sampleIds) . PHP_EOL;
echo PHP_EOL;

// Correr el import de payments con solo 20 filas
echo "Ejecutando import de 20 filas de payments (replace)..." . PHP_EOL;
$service->setDumpColumns($analysis['dump_columns'] ?? []);
$result = $service->executeImport(
    ['payments' => array_slice($rows, 0, 20)],
    ['payments' => ['action' => 'replace']],
    false
);
print_r($result['payments'] ?? []);

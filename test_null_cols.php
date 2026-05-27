<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = ['payments', 'inventory_items', 'packages', 'users', 'olt_zones', 'olt_type_onus',
           'documentation_submenus', 'crm_lead_information', 'bundles'];

foreach ($tables as $t) {
    $cols = DB::select(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL
         AND EXTRA NOT LIKE '%auto_increment%'",
        [$t]
    );
    if ($cols) {
        echo "$t: " . implode(', ', array_column($cols, 'COLUMN_NAME')) . PHP_EOL;
    }
}
echo "Done\n";

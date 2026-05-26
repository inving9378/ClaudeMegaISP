<?php

namespace App\Modules\Addons\SmartImportExport\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SmartExportService
{
    /**
     * Módulos exportables. Cada uno engloba un conjunto de tablas y declara
     * las columnas sensibles que se censuran/omiten para no exfiltrar
     * tokens, contraseñas o tarjetas.
     */
    public const EXPORT_MODULES = [
        'clientes' => [
            'label'     => 'Clientes',
            'icon'      => 'fas fa-users',
            'tables'    => ['clients', 'client_main_informations', 'client_additional_informations', 'client_internet_services', 'client_voz_services', 'client_custom_services', 'client_bundle_services'],
            'sensitive' => ['password', 'token', 'remember_token'],
        ],
        'finanzas' => [
            'label'     => 'Finanzas',
            'icon'      => 'fas fa-dollar-sign',
            'tables'    => ['invoices', 'invoice_items', 'payments', 'payment_details', 'payment_accounts'],
            'sensitive' => ['card_number', 'cvv', 'iban'],
        ],
        'planes' => [
            'label'     => 'Planes',
            'icon'      => 'fas fa-layer-group',
            'tables'    => ['packages', 'internets', 'voises', 'customs', 'bundles'],
            'sensitive' => [],
        ],
        'tickets' => [
            'label'     => 'Tickets',
            'icon'      => 'fas fa-ticket-alt',
            'tables'    => ['tickets', 'ticket_threads'],
            'sensitive' => [],
        ],
        'vendedores' => [
            'label'     => 'Vendedores',
            'icon'      => 'fas fa-user-tie',
            'tables'    => ['sellers', 'seller_types', 'seller_status', 'commissions', 'commission_details'],
            'sensitive' => ['password'],
        ],
        'inventario' => [
            'label'     => 'Inventario',
            'icon'      => 'fas fa-boxes',
            'tables'    => ['inventory_items', 'inventory_item_types', 'inventory_stores', 'inventory_movements'],
            'sensitive' => [],
        ],
        'red' => [
            'label'     => 'Gestión de Red',
            'icon'      => 'fas fa-network-wired',
            'tables'    => ['routers', 'networks', 'network_ips'],
            'sensitive' => ['password', 'api_token'],
        ],
        'crm' => [
            'label'     => 'CRM',
            'icon'      => 'fas fa-handshake',
            'tables'    => ['crms', 'crm_main_informations', 'crm_lead_informations'],
            'sensitive' => [],
        ],
        'usuarios' => [
            'label'     => 'Usuarios',
            'icon'      => 'fas fa-user-shield',
            'tables'    => ['users', 'roles', 'permissions'],
            'sensitive' => ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],
        ],
    ];

    public const STORAGE_DIR = 'app/smart_export';

    public function getModulesWithCount(): array
    {
        $result = [];
        foreach (self::EXPORT_MODULES as $key => $cfg) {
            $tables = [];
            $totalRows = 0;
            foreach ($cfg['tables'] as $table) {
                if (!Schema::hasTable($table)) {
                    $tables[] = ['name' => $table, 'count' => 0, 'exists' => false];
                    continue;
                }
                $count = (int) DB::table($table)->count();
                $totalRows += $count;
                $tables[] = ['name' => $table, 'count' => $count, 'exists' => true];
            }
            $result[] = [
                'key'        => $key,
                'label'      => $cfg['label'],
                'icon'       => $cfg['icon'],
                'tables'     => $tables,
                'total_rows' => $totalRows,
                'sensitive'  => $cfg['sensitive'],
            ];
        }
        return $result;
    }

    public function generate(array $modules, string $format, bool $stripSensitive = true): array
    {
        $modules = array_values(array_filter($modules, fn ($m) => isset(self::EXPORT_MODULES[$m])));
        if (empty($modules)) {
            throw new \InvalidArgumentException('Debe seleccionar al menos un módulo válido.');
        }

        $dir = storage_path(self::STORAGE_DIR);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0775, true, true);
        }

        $token = (string) Str::uuid();
        $base = 'export-' . now()->format('Ymd-His') . '-' . substr($token, 0, 8);

        switch ($format) {
            case 'sql':
                $filename = $base . '.sql';
                $contents = $this->exportToSQL($modules, $stripSensitive);
                file_put_contents($dir . '/' . $filename, $contents);
                break;
            case 'json':
                $filename = $base . '.json';
                $contents = $this->exportToJSON($modules, $stripSensitive);
                file_put_contents($dir . '/' . $filename, $contents);
                break;
            case 'xlsx':
                $filename = $base . '.xlsx';
                $this->exportToExcel($modules, $stripSensitive, $dir . '/' . $filename);
                break;
            default:
                throw new \InvalidArgumentException('Formato no soportado: ' . $format);
        }

        $this->registerToken($token, $filename);

        return [
            'token'    => $token,
            'filename' => $filename,
            'format'   => $format,
            'size'     => filesize($dir . '/' . $filename) ?: 0,
            'modules'  => $modules,
        ];
    }

    public function exportToSQL(array $modules, bool $stripSensitive = true): string
    {
        $sql  = "-- MegaNet Smart Export\n";
        $sql .= "-- Generado: " . now()->toDateTimeString() . "\n";
        $sql .= "-- Módulos: " . implode(', ', $modules) . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($modules as $moduleKey) {
            $cfg = self::EXPORT_MODULES[$moduleKey];
            $sensitive = $stripSensitive ? $cfg['sensitive'] : [];
            $sql .= "-- ============================================\n";
            $sql .= "-- Módulo: {$cfg['label']}\n";
            $sql .= "-- ============================================\n\n";
            foreach ($cfg['tables'] as $table) {
                if (!Schema::hasTable($table)) {
                    $sql .= "-- (omitido: tabla `{$table}` no existe)\n";
                    continue;
                }
                $sql .= $this->dumpTableSQL($table, $sensitive);
            }
        }

        $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }

    public function exportToJSON(array $modules, bool $stripSensitive = true): string
    {
        $payload = [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'modules'      => $modules,
            ],
            'data' => [],
        ];

        foreach ($modules as $moduleKey) {
            $cfg = self::EXPORT_MODULES[$moduleKey];
            $sensitive = $stripSensitive ? $cfg['sensitive'] : [];
            foreach ($cfg['tables'] as $table) {
                if (!Schema::hasTable($table)) continue;
                $payload['data'][$table] = DB::table($table)->get()->map(function ($row) use ($sensitive) {
                    $arr = (array) $row;
                    foreach ($sensitive as $col) {
                        if (array_key_exists($col, $arr)) unset($arr[$col]);
                    }
                    return $arr;
                })->all();
            }
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function exportToExcel(array $modules, bool $stripSensitive, string $absolutePath): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($modules as $moduleKey) {
            $cfg = self::EXPORT_MODULES[$moduleKey];
            $sensitive = $stripSensitive ? $cfg['sensitive'] : [];
            foreach ($cfg['tables'] as $table) {
                if (!Schema::hasTable($table)) continue;
                $rows = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
                if (empty($rows)) continue;
                $columns = array_diff(array_keys($rows[0]), $sensitive);
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle(Str::limit($table, 28, ''));

                $sheet->fromArray(array_values($columns), null, 'A1');
                $r = 2;
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($columns as $col) {
                        $values[] = $row[$col] ?? null;
                    }
                    $sheet->fromArray($values, null, 'A' . $r);
                    $r++;
                }
            }
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $spreadsheet->createSheet()->setTitle('vacio');
        }
        $spreadsheet->setActiveSheetIndex(0);
        $writer = SpreadsheetIOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($absolutePath);
    }

    public function resolveToken(string $token): ?string
    {
        return Cache::get($this->tokenKey($token));
    }

    public function consumeToken(string $token): void
    {
        Cache::forget($this->tokenKey($token));
    }

    /* ===================== Helpers privados ===================== */

    private function dumpTableSQL(string $table, array $sensitive): string
    {
        $rows = DB::table($table)->get();
        if ($rows->isEmpty()) {
            return "-- `{$table}`: sin registros\n\n";
        }
        $first = (array) $rows->first();
        $columns = array_diff(array_keys($first), $sensitive);
        $columnList = '`' . implode('`, `', $columns) . '`';
        $sql = "-- `{$table}` (" . count($rows) . " registros)\n";
        $batchSize = 200;
        foreach (array_chunk($rows->all(), $batchSize) as $chunk) {
            $values = [];
            foreach ($chunk as $row) {
                $row = (array) $row;
                $cellValues = [];
                foreach ($columns as $col) {
                    $cellValues[] = $this->quoteSqlValue($row[$col] ?? null);
                }
                $values[] = '(' . implode(', ', $cellValues) . ')';
            }
            $sql .= "INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $values) . ";\n";
        }
        return $sql . "\n";
    }

    private function quoteSqlValue($value): string
    {
        if ($value === null) return 'NULL';
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_int($value) || is_float($value)) return (string) $value;
        return "'" . str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", "\\n", "\\r"], (string) $value) . "'";
    }

    private function registerToken(string $token, string $filename): void
    {
        Cache::put($this->tokenKey($token), $filename, now()->addHours(2));
    }

    private function tokenKey(string $token): string
    {
        return 'smart_export:token:' . $token;
    }
}

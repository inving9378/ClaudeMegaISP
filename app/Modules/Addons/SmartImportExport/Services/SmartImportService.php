<?php

namespace App\Modules\Addons\SmartImportExport\Services;

use App\Models\Bundle;
use App\Models\Client;
use App\Models\Custom;
use App\Models\Internet;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Network;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Seller;
use App\Models\Ticket;
use App\Models\User;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IAAdaptadorFactory;
use App\Modules\Core\CRM\Models\Crm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use Throwable;

class SmartImportService
{
    /**
     * Counts exactos por tabla del último parseSql() — incluso si truncamos
     * el dataset cargado en RAM. analyzeFile() los usa para 'records' real.
     * @var array<string, int>
     */
    private array $lastRowCounts = [];

    /**
     * Mapa de tabla → módulo lógico + modelo Eloquent que la administra.
     * El frontend usa esto para enseñar el reporte y resolver conflictos.
     */
    public const TABLE_MODULE_MAP = [
        'clients'           => ['module' => 'Clientes',    'model' => Client::class,         'conflict_keys' => ['email', 'phone', 'full_name']],
        'crms'              => ['module' => 'CRM',         'model' => Crm::class,            'conflict_keys' => ['email', 'phone', 'full_name']],
        'invoices'          => ['module' => 'Finanzas',    'model' => Invoice::class,        'conflict_keys' => ['folio', 'client_id']],
        'payments'          => ['module' => 'Finanzas',    'model' => Payment::class,        'conflict_keys' => ['folio', 'client_id']],
        'packages'          => ['module' => 'Planes',      'model' => Package::class,        'conflict_keys' => ['title']],
        'internets'         => ['module' => 'Planes',      'model' => Internet::class,       'conflict_keys' => ['title']],
        'customs'           => ['module' => 'Planes',      'model' => Custom::class,         'conflict_keys' => ['title']],
        'bundles'           => ['module' => 'Planes',      'model' => Bundle::class,         'conflict_keys' => ['title']],
        'tickets'           => ['module' => 'Tickets',     'model' => Ticket::class,         'conflict_keys' => ['folio']],
        'sellers'           => ['module' => 'Vendedores',  'model' => Seller::class,         'conflict_keys' => ['email', 'phone']],
        'inventory_items'   => ['module' => 'Inventario',  'model' => InventoryItem::class,  'conflict_keys' => ['serial', 'sku']],
        'networks'          => ['module' => 'Red',         'model' => Network::class,        'conflict_keys' => ['network']],
        'routers'           => ['module' => 'Red',         'model' => Router::class,         'conflict_keys' => ['ip', 'title']],
        'users'             => ['module' => 'Usuarios',    'model' => User::class,           'conflict_keys' => ['email']],
    ];

    /** Carpeta temporal donde se persiste el archivo durante el flujo. */
    public const STORAGE_DIR = 'app/smart_import';

    public function analyzeFile(UploadedFile $file): array
    {
        $dir = storage_path(self::STORAGE_DIR);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0775, true, true);
        }

        $token = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $storedName = $token . '.' . $extension;
        $file->move($dir, $storedName);
        $path = $dir . '/' . $storedName;

        // Reset por-llamada — parseSql lo llena
        $this->lastRowCounts = [];

        $format = $this->detectFormat($extension, $path);
        $datasets = match ($format) {
            'sql'   => $this->parseSql($path),
            'json'  => $this->parseJson($path),
            'csv'   => $this->parseCsv($path),
            'xlsx'  => $this->parseSpreadsheet($path),
            'zip'   => $this->parseZip($path),
            default => [],
        };

        $report = [];
        foreach ($datasets as $table => $rows) {
            $rows = array_values($rows);
            $info = self::TABLE_MODULE_MAP[$table] ?? null;
            // Count exacto del archivo (puede ser > count($rows) si truncamos en RAM)
            $exactCount = $this->lastRowCounts[$table] ?? count($rows);
            $report[] = [
                'table'     => $table,
                'module'    => $info['module'] ?? 'Sin clasificar',
                'model'     => $info['model'] ?? null,
                'records'   => $exactCount,
                'sample'    => array_slice($rows, 0, 3),
                'known'     => $info !== null,
                'truncated' => $exactCount > count($rows),
            ];
        }

        $totalRows = !empty($this->lastRowCounts)
            ? array_sum($this->lastRowCounts)
            : array_sum(array_map('count', $datasets));

        return [
            'token'      => $token,
            'file'       => $storedName,
            'format'     => $format,
            'datasets'   => $datasets,
            'report'     => $this->sanitizeUtf8($report),
            'total_rows' => $totalRows,
        ];
    }

    public function detectConflicts(array $datasets): array
    {
        $conflicts = [];
        foreach ($datasets as $table => $rows) {
            $info = self::TABLE_MODULE_MAP[$table] ?? null;
            if (!$info || !Schema::hasTable($table)) {
                continue;
            }
            $keys = $info['conflict_keys'] ?? [];
            $tableConflicts = [];

            foreach ($rows as $index => $row) {
                $existing = $this->findExisting($table, $keys, $row);
                if ($existing) {
                    $tableConflicts[] = [
                        'index'    => $index,
                        'incoming' => $row,
                        'existing' => $existing,
                        'matched'  => $this->matchedKeys($keys, $row, $existing),
                    ];
                }
            }

            if (!empty($tableConflicts)) {
                $conflicts[$table] = $tableConflicts;
            }
        }
        return $conflicts;
    }

    public function resolveWithAI(array $conflicts): array
    {
        $proveedor = IAProveedor::where('activo', true)->orderBy('id')->first();
        if (!$proveedor) {
            return ['error' => 'No hay proveedor de IA activo. Configure uno en /ia/configuracion.'];
        }

        try {
            $adaptador = IAAdaptadorFactory::crear($proveedor);
        } catch (Throwable $e) {
            return ['error' => 'No se pudo iniciar el adaptador IA: ' . $e->getMessage()];
        }

        $resultados = [];
        foreach ($conflicts as $table => $items) {
            foreach ($items as $item) {
                $prompt = $this->buildConflictPrompt($table, $item);
                try {
                    $respuesta = $adaptador->enviarMensaje([], $prompt, [], 'Eres un asistente experto en migración de datos.');
                    $resultados[$table][$item['index']] = $this->parseAIRecommendation($respuesta['texto'] ?? '');
                } catch (Throwable $e) {
                    Log::warning('SmartImport IA: ' . $e->getMessage());
                    $resultados[$table][$item['index']] = [
                        'accion' => 'omitir',
                        'razon'  => 'Sin respuesta de IA (' . $e->getMessage() . '). Se sugiere omitir por seguridad.',
                    ];
                }
            }
        }
        return $resultados;
    }

    public function executeImport(array $datasets, array $options = []): array
    {
        $summary = [];
        foreach ($datasets as $table => $rows) {
            $info = self::TABLE_MODULE_MAP[$table] ?? null;
            if (!$info || !class_exists($info['model'])) {
                $summary[$table] = ['imported' => 0, 'skipped' => count($rows), 'errors' => 0, 'reason' => 'tabla_no_mapeada'];
                continue;
            }
            $modelClass = $info['model'];
            $defaultAction = $options[$table]['action'] ?? 'skip';
            $perRow = $options[$table]['conflicts'] ?? [];
            $imported = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($rows as $index => $row) {
                try {
                    $existing = $this->findExisting($table, $info['conflict_keys'] ?? [], $row);
                    $action = $existing ? ($perRow[$index] ?? $defaultAction) : 'insert';

                    if ($action === 'skip') {
                        $skipped++;
                        continue;
                    }
                    if ($action === 'replace' && $existing) {
                        $modelClass::query()->whereKey($existing[$modelClass::make()->getKeyName()])
                            ->update($this->sanitizeRow($row, $modelClass));
                        $imported++;
                        continue;
                    }
                    if ($action === 'duplicate') {
                        unset($row[$modelClass::make()->getKeyName()]);
                    }
                    $modelClass::create($this->sanitizeRow($row, $modelClass));
                    $imported++;
                } catch (Throwable $e) {
                    Log::warning('SmartImport row error [' . $table . ']: ' . $e->getMessage());
                    $errors++;
                }
            }
            $summary[$table] = compact('imported', 'skipped', 'errors');
        }
        return $summary;
    }

    public function cleanup(string $storedName): void
    {
        $path = storage_path(self::STORAGE_DIR . '/' . $storedName);
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    /* ===================== Helpers privados ===================== */

    private function detectFormat(string $extension, string $path): string
    {
        $byExt = ['sql' => 'sql', 'json' => 'json', 'csv' => 'csv', 'xlsx' => 'xlsx', 'xls' => 'xlsx', 'zip' => 'zip'];
        if (isset($byExt[$extension])) {
            return $byExt[$extension];
        }
        $head = @file_get_contents($path, false, null, 0, 512) ?: '';
        if (str_starts_with($head, "PK\x03\x04")) return 'zip';
        if (Str::startsWith(ltrim($head), ['{', '['])) return 'json';
        if (stripos($head, 'INSERT INTO') !== false || stripos($head, 'CREATE TABLE') !== false) return 'sql';
        return 'csv';
    }

    private function parseZip(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extensión PHP zip no está disponible en este servidor.');
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($path);
        if ($opened !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo ZIP (código ' . $opened . ').');
        }

        $extractDir = storage_path(self::STORAGE_DIR . '/extracted-' . Str::uuid());
        if (!File::exists($extractDir)) {
            File::makeDirectory($extractDir, 0775, true, true);
        }
        $zip->extractTo($extractDir);
        $zip->close();

        $datasets = [];
        try {
            foreach (File::allFiles($extractDir) as $extracted) {
                $ext = strtolower($extracted->getExtension());
                $sub = match ($ext) {
                    'sql'         => $this->parseSql($extracted->getPathname()),
                    'json'        => $this->parseJson($extracted->getPathname()),
                    'csv'         => $this->parseCsv($extracted->getPathname()),
                    'xlsx', 'xls' => $this->parseSpreadsheet($extracted->getPathname()),
                    default       => [],
                };
                foreach ($sub as $table => $rows) {
                    $datasets[$table] = array_merge($datasets[$table] ?? [], $rows);
                }
            }
        } finally {
            File::deleteDirectory($extractDir);
        }

        return $datasets;
    }

    /**
     * Reemplaza bytes inválidos UTF-8 por su equivalente convertido para evitar
     * JsonEncodingException al persistir el report en ImportExportLog->ai_analysis.
     * Pasa strings por mb_convert_encoding UTF-8→UTF-8 (descarta secuencias rotas).
     */
    private function sanitizeUtf8(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->sanitizeUtf8($v);
            }
            return $out;
        }
        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        return $value;
    }

    /**
     * Parser SQL en STREAMING con FSM (finite state machine) carácter-por-carácter.
     *
     * Por qué el approach previo fallaba con dumps gigantes:
     *   El parser anterior acumulaba el statement entero en RAM hasta encontrar
     *   `;` (fin de statement). Para mysqldump `--extended-insert` con un solo
     *   INSERT de cientos de MB ("INSERT INTO t VALUES (1,'a'),(2,'b'),...;"),
     *   el buffer excedía MAX_STATEMENT_BYTES (100 MB) y se DESCARTABA completo,
     *   dejando $rowCounts en cero — exactamente el bug "report:[]" reportado.
     *
     * Solución actual:
     *   Procesar carácter por carácter manteniendo dos estados principales:
     *     - $currentTable === null  → buscando "INSERT INTO tabla VALUES"
     *     - $currentTable !== null  → dentro de VALUES, consumiendo tuplas (...)
     *   Cada tupla `(...)` cerrada se procesa y CUENTA al instante, sin esperar
     *   al `;` final. El buffer en RAM se mantiene mínimo (solo lo necesario
     *   para parsear el header del INSERT y la tupla actual).
     *
     * Cap por tabla: MAX_ROWS_PER_TABLE filas en memoria para sample/conflictos.
     * Counts exactos siguen llegando a $lastRowCounts incluso si truncamos.
     * Hard limit MAX_LINES_FOR_ANALYSIS (iteraciones del scanner) para acotar
     * tiempo de respuesta en uploads enormes; el resto del archivo queda
     * en disco para que executeImport pueda re-procesarlo.
     */
    private const MAX_ROWS_PER_TABLE       = 10000;
    private const STREAM_CHUNK_BYTES       = 65536;              // 64 KB por fread
    private const MAX_INSERT_HEADER_BYTES  = 16384;              // INSERT INTO ... VALUES rara vez excede 16 KB
    private const MAX_SCAN_BYTES           = 500 * 1024 * 1024;  // 500 MB de scan máximo (no leer archivos absurdos)

    private function parseSql(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return [];
        }

        $datasets       = [];
        $rowCounts      = [];

        // Estado FSM
        $currentTable   = null;   // ?string — tabla del INSERT actual, null fuera de un INSERT
        $currentColumns = [];     // string[] — columnas del INSERT actual
        $header         = '';     // buffer mientras buscamos "INSERT INTO ... VALUES"
        $tuple          = '';     // buffer de la tupla actual cuando estamos dentro de VALUES
        $depth          = 0;      // profundidad de paréntesis (siempre 0 o 1 en SQL bien formado)
        $inString       = false;  // dentro de string '...'
        $escape         = false;  // próximo char está escapado
        $scanned        = 0;      // bytes totales escaneados (para MAX_SCAN_BYTES)

        try {
            while (!feof($handle) && $scanned < self::MAX_SCAN_BYTES) {
                $chunk = fread($handle, self::STREAM_CHUNK_BYTES);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $scanned += strlen($chunk);
                $chunkLen = strlen($chunk);

                for ($i = 0; $i < $chunkLen; $i++) {
                    $c = $chunk[$i];

                    if ($currentTable === null) {
                        // ─── ESTADO: BUSCANDO HEADER "INSERT INTO ... VALUES" ───
                        $header .= $c;

                        // Trim agresivo: solo nos importa la parte donde vive INSERT
                        if (strlen($header) > self::MAX_INSERT_HEADER_BYTES) {
                            // Si llevamos 16 KB sin ver "INSERT INTO ... VALUES" en una línea
                            // posiblemente estamos en CREATE TABLE / dump de schema. Conservar
                            // los últimos bytes para no perder un INSERT que esté empezando.
                            $header = substr($header, -2048);
                        }

                        if ($c === 'S' || $c === 's') {
                            // Posible fin de "VALUES" — intentar match del header completo
                            if (preg_match(
                                '/INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?\s*\(([^)]+)\)\s*VALUES\s*$/is',
                                $header,
                                $m
                            )) {
                                $currentTable   = $m[1];
                                $currentColumns = array_map(
                                    fn ($x) => trim($x, " `\t\n\r"),
                                    explode(',', $m[2])
                                );
                                $header   = '';
                                $tuple    = '';
                                $depth    = 0;
                                $inString = false;
                                $escape   = false;
                            }
                        }
                        continue;
                    }

                    // ─── ESTADO: DENTRO DE VALUES — consumiendo tuplas (...) ───
                    if ($escape) { $tuple .= $c; $escape = false; continue; }
                    if ($c === '\\') { $tuple .= $c; $escape = true; continue; }
                    if ($c === "'") { $inString = !$inString; $tuple .= $c; continue; }

                    if (!$inString) {
                        if ($c === '(') {
                            if ($depth === 0) { $tuple = ''; }
                            else              { $tuple .= $c; }
                            $depth++;
                            continue;
                        }
                        if ($c === ')') {
                            $depth--;
                            if ($depth === 0) {
                                // Tupla cerrada — procesarla
                                $values = $this->parseSqlTuple($tuple);
                                if (count($values) === count($currentColumns)) {
                                    $rowCounts[$currentTable] = ($rowCounts[$currentTable] ?? 0) + 1;
                                    if ($rowCounts[$currentTable] <= self::MAX_ROWS_PER_TABLE) {
                                        $datasets[$currentTable][] = array_combine($currentColumns, $values);
                                    }
                                }
                                $tuple = '';
                                continue;
                            }
                            $tuple .= $c;
                            continue;
                        }
                        if ($c === ';' && $depth === 0) {
                            // Fin del INSERT — volver a estado "buscando header"
                            $currentTable   = null;
                            $currentColumns = [];
                            $tuple          = '';
                            $header         = '';
                            continue;
                        }
                    }

                    if ($depth > 0) {
                        $tuple .= $c;
                    }
                    // bytes entre tuplas (`,`, espacios, `\n`) se ignoran
                }
            }
        } finally {
            fclose($handle);
        }

        // Acumular counts exactos. Importante usar += porque parseZip() puede llamar
        // parseSql() para múltiples .sql del ZIP, y analyzeFile() ya resetea al inicio.
        foreach ($rowCounts as $table => $count) {
            $this->lastRowCounts[$table] = ($this->lastRowCounts[$table] ?? 0) + $count;
        }

        return $datasets;
    }

    /**
     * Stub conservado por si algún test/legacy lo invoca. La detección de fin
     * de statement ahora vive directamente en el FSM de parseSql().
     */
    private function findStatementEnd(string $buffer, int $startFrom = 0): int|false
    {
        $len = strlen($buffer);
        $inString = false;
        $escape = false;
        for ($i = 0; $i < $len; $i++) {
            $c = $buffer[$i];
            if ($escape) { $escape = false; continue; }
            if ($c === '\\') { $escape = true; continue; }
            if ($c === "'") { $inString = !$inString; continue; }
            if (!$inString && $c === ';' && $i >= $startFrom) {
                return $i;
            }
        }
        return false;
    }

    /**
     * Legacy: procesa un statement completo. Reemplazado por el FSM de parseSql();
     * conservado por compat — no se usa en el flujo actual.
     */
    private function processSqlStatement(string $statement, array &$datasets, array &$rowCounts): void
    {
        $stripped = ltrim($statement);
        $stripped = preg_replace('/^(--[^\n]*\n|\/\*.*?\*\/|\s+)+/s', '', $stripped) ?? $stripped;
        if (stripos($stripped, 'INSERT') !== 0) {
            return;
        }
        if (!preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?\s*\(([^)]+)\)\s*VALUES\s*(.+);\s*$/is', $stripped, $m)) {
            return;
        }
        $table       = $m[1];
        $columns     = array_map(fn ($c) => trim($c, " `\t\n\r"), explode(',', $m[2]));
        $valuesBlob  = rtrim($m[3]);
        $rows = $this->splitSqlTuples($valuesBlob);
        foreach ($rows as $tuple) {
            $values = $this->parseSqlTuple($tuple);
            if (count($values) !== count($columns)) {
                continue;
            }
            $rowCounts[$table] = ($rowCounts[$table] ?? 0) + 1;
            if (($rowCounts[$table] ?? 0) <= self::MAX_ROWS_PER_TABLE) {
                $datasets[$table][] = array_combine($columns, $values);
            }
        }
    }

    private function splitSqlTuples(string $blob): array
    {
        $tuples = [];
        $depth = 0;
        $current = '';
        $inString = false;
        $escape = false;
        $len = strlen($blob);
        for ($i = 0; $i < $len; $i++) {
            $c = $blob[$i];
            if ($escape) { $current .= $c; $escape = false; continue; }
            if ($c === '\\') { $current .= $c; $escape = true; continue; }
            if ($c === "'" ) { $inString = !$inString; $current .= $c; continue; }
            if (!$inString) {
                if ($c === '(') { if ($depth === 0) { $current = ''; } else { $current .= $c; } $depth++; continue; }
                if ($c === ')') { $depth--; if ($depth === 0) { $tuples[] = $current; $current = ''; continue; } else { $current .= $c; continue; } }
            }
            if ($depth > 0) $current .= $c;
        }
        return $tuples;
    }

    private function parseSqlTuple(string $tuple): array
    {
        $values = [];
        $len = strlen($tuple);
        $i = 0;
        $current = '';
        $inString = false;
        while ($i < $len) {
            $c = $tuple[$i];
            if ($inString) {
                if ($c === '\\' && $i + 1 < $len) { $current .= $tuple[$i + 1]; $i += 2; continue; }
                if ($c === "'") { $inString = false; $i++; continue; }
                $current .= $c; $i++; continue;
            }
            if ($c === "'") { $inString = true; $i++; continue; }
            if ($c === ',') { $values[] = $this->castSqlValue(trim($current)); $current = ''; $i++; continue; }
            $current .= $c; $i++;
        }
        if ($current !== '' || $tuple !== '') {
            $values[] = $this->castSqlValue(trim($current));
        }
        return $values;
    }

    private function castSqlValue(string $raw)
    {
        if ($raw === '') return null;
        if (strcasecmp($raw, 'NULL') === 0) return null;
        if (is_numeric($raw)) return $raw + 0;
        return trim($raw, "'");
    }

    private function parseJson(string $path): array
    {
        $data = json_decode(file_get_contents($path) ?: 'null', true);
        if (!is_array($data)) return [];
        $datasets = [];
        $isAssoc = array_keys($data) !== range(0, count($data) - 1);
        if ($isAssoc) {
            foreach ($data as $table => $rows) {
                if (is_array($rows)) {
                    $datasets[$table] = array_values(array_filter($rows, 'is_array'));
                }
            }
            return $datasets;
        }
        $datasets['unknown'] = $data;
        return $datasets;
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) return [];
        $header = fgetcsv($handle);
        if (!$header) { fclose($handle); return []; }
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) continue;
            $rows[] = array_combine($header, $row);
        }
        fclose($handle);
        $table = $this->guessTableFromHeader($header) ?? 'unknown';
        return [$table => $rows];
    }

    private function parseSpreadsheet(string $path): array
    {
        $reader = SpreadsheetIOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $datasets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            if (count($rows) < 2) continue;
            $header = array_map(fn ($h) => trim((string) $h), $rows[0]);
            $tableName = strtolower($sheet->getTitle());
            $tableKey = isset(self::TABLE_MODULE_MAP[$tableName]) ? $tableName : ($this->guessTableFromHeader($header) ?? $tableName);
            $records = [];
            for ($i = 1, $n = count($rows); $i < $n; $i++) {
                if (count($rows[$i]) !== count($header)) continue;
                $records[] = array_combine($header, $rows[$i]);
            }
            if (!empty($records)) {
                $datasets[$tableKey] = $records;
            }
        }
        return $datasets;
    }

    private function guessTableFromHeader(array $header): ?string
    {
        $needle = array_map('strtolower', $header);
        foreach (self::TABLE_MODULE_MAP as $table => $info) {
            $keys = $info['conflict_keys'] ?? [];
            $hits = 0;
            foreach ($keys as $key) {
                if (in_array($key, $needle, true)) $hits++;
            }
            if ($hits >= max(1, (int) floor(count($keys) / 2))) {
                return $table;
            }
        }
        return null;
    }

    private function findExisting(string $table, array $keys, array $row): ?array
    {
        if (empty($keys)) return null;
        $query = DB::table($table);
        $hasFilter = false;
        foreach ($keys as $key) {
            if (!empty($row[$key]) && Schema::hasColumn($table, $key)) {
                $query->orWhere($key, $row[$key]);
                $hasFilter = true;
            }
        }
        if (!$hasFilter) return null;
        $found = $query->first();
        return $found ? (array) $found : null;
    }

    private function matchedKeys(array $keys, array $row, array $existing): array
    {
        $matched = [];
        foreach ($keys as $key) {
            if (isset($row[$key], $existing[$key]) && (string) $row[$key] === (string) $existing[$key]) {
                $matched[] = $key;
            }
        }
        return $matched;
    }

    private function sanitizeRow(array $row, string $modelClass): array
    {
        $instance = new $modelClass();
        $table = $instance->getTable();
        $columns = Schema::getColumnListing($table);
        return array_intersect_key($row, array_flip($columns));
    }

    private function buildConflictPrompt(string $table, array $item): string
    {
        $incoming = json_encode($item['incoming'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $existing = json_encode($item['existing'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $matched = implode(', ', $item['matched'] ?: ['—']);
        return "Conflicto en tabla `{$table}` (coincide por: {$matched}).\n\n"
            . "REGISTRO EXISTENTE:\n{$existing}\n\n"
            . "REGISTRO ENTRANTE:\n{$incoming}\n\n"
            . "Responde EXACTAMENTE en este formato JSON (sin texto adicional):\n"
            . '{"accion":"omitir|reemplazar|duplicar","razon":"breve explicación en español"}';
    }

    private function parseAIRecommendation(string $texto): array
    {
        if (preg_match('/\{[^{}]*"accion"[^{}]*\}/s', $texto, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data) && isset($data['accion'])) {
                $accion = strtolower($data['accion']);
                if (!in_array($accion, ['omitir', 'reemplazar', 'duplicar'], true)) {
                    $accion = 'omitir';
                }
                return [
                    'accion' => $accion,
                    'razon'  => $data['razon'] ?? 'Sin justificación devuelta por la IA.',
                ];
            }
        }
        return [
            'accion' => 'omitir',
            'razon'  => Str::limit($texto, 280),
        ];
    }
}

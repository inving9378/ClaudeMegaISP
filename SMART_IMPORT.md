# Smart Import/Export — Documentación completa del módulo

Módulo: `app/Modules/Addons/SmartImportExport/`
Ruta base: `/configuracion/smart-import`

---

## Índice de clases

1. [SmartImportService](#1-smartimportservice) — Motor principal (3661 líneas, 106 métodos)
2. [SmartImportJob](#2-smartimportjob) — Job de background (351 líneas)
3. [SmartExportService](#3-smartexportservice) — Exportación a SQL/JSON/XLSX (297 líneas)
4. [SmartImportModelDiscovery](#4-smartimportmodeldiscovery) — Auto-descubrimiento de modelos Eloquent (186 líneas)
5. [SmartImportTableResolver](#5-smartimporttableresolver) — Resolución tabla → modelo/modo (170 líneas)
6. [ImportExportController](#6-importexportcontroller) — Controlador HTTP (438 líneas)
7. [ImportExportLog](#7-importexportlog) — Modelo de bitácora (86 líneas)

---

## 1. SmartImportService

**Archivo:** `app/Modules/Addons/SmartImportExport/Services/SmartImportService.php`

Servicio central de importación. Maneja análisis de archivos, parsing SQL streaming, resolución de conflictos, inserción batch, normalización de FKs, reparación de auto_increment, y detección de schema drift.

---

### Constantes

| Constante | Valor | Propósito |
|---|---|---|
| `STORAGE_DIR` | `'app/smart_import'` | Directorio donde se almacenan archivos subidos |
| `MAX_ROWS_PER_TABLE` | `10000` | Máximo de filas en RAM durante el análisis (preview) |
| `STREAM_CHUNK_BYTES` | `65536` (64KB) | Tamaño de chunk de lectura del parser FSM |
| `MAX_INSERT_HEADER_BYTES` | `16384` (16KB) | Buffer máximo para detectar "INSERT INTO ... VALUES" |
| `MAX_SCAN_BYTES` | `524288000` (500MB) | Límite de bytes escaneados en análisis |
| `BULK_CHUNK_SIZE` | `500` | Filas por chunk en operaciones bulk |
| `GLOBAL_MODE_FORCE_SOURCE` | `'force_source'` | Reemplazar tabla completa |
| `GLOBAL_MODE_SKIP_EXISTING` | `'skip_existing'` | Omitir tablas existentes |
| `GLOBAL_MODE_SMART` | `'smart'` | Merge inteligente con upsert |
| `STRICT_MODEL_CLASSES` | `[Balance::class, Client::class, ...]` | Modelos que requieren paso por Eloquent (no bulk) |
| `TABLE_MODULE_MAP` | `~226 entries` | Mapa legacy de tabla → módulo/modelo/modo/conflict_keys |

---

### Métodos públicos

#### `analyzeFile(UploadedFile $file): array`
Punto de entrada del análisis. Recibe el archivo subido, lo mueve a `storage/app/smart_import/{uuid}.{ext}`, resetea el estado, detecta formato (sql/zip), parsea, construye el reporte, y retorna el análisis completo con token, datasets, dump_columns, source_tables, report y total_rows.

#### `loadDatasetsForExecution(array $analysis): array`
Rehidrata datasets desde el archivo en disco durante la ejecución asíncrona. Vuelve a parsear el SQL desde el archivo (no desde el cache en RAM) para evitar OOM. Retorna datasets completos (sin límite de filas).

#### `executeSqlImportFromAnalysis(array $analysis, array $options, ?callable $onTableStarted): array`
Ejecuta importación para formato SQL. Construye plan, prepara tablas (crea faltantes, sincroniza columnas), ejecuta streaming con spool-to-disk, repara auto_increment y audita FKs al final. El callback `$onTableStarted` se dispara por cada tabla para actualizar progreso.

#### `executeZipImportFromAnalysis(array $analysis, array $options, ?callable $onTableStarted): array`
Igual que `executeSqlImportFromAnalysis` pero extrae el ZIP a temp, procesa cada `.sql` interno, y limpia al final.

#### `detectConflicts(array $datasets, array $options): array`
Escanea datasets contra la BD destino para detectar conflictos por tabla. Usa `batchFindExisting()` para detectar duplicados en chunks. Retorna items de conflicto con fila entrante, existente, y keys coincidentes. Limitado por `detail_limit_per_table` (default 50).

#### `resolveWithAI(array $conflicts): array`
Resuelve conflictos usando el módulo de IA del sistema. Construye un prompt por conflicto, llama al adaptador de IA (vía `IAAdaptadorFactory`), parsea la respuesta JSON con `parseAIRecommendation()`. Retorna acción sugerida (omitir/reemplazar/duplicar) por conflicto.

#### `executeImport(array $datasets, array $options): array`
Motor de inserción para datasets en memoria (usado por el path legacy y como fallback). Itera tablas, resuelve descriptor, detecta conflictos, decide bulk vs row-by-row según `canUseBulkPath()`. Soporta acciones: insert, replace (update), skip, duplicate.

#### `cleanup(string $storedName): void`
Elimina el archivo temporal de `storage/app/smart_import/`.

---

### Métodos de preparación de ejecución

#### `buildExecutionPlan(array $analysis, array $options): array`
Construye el plan de importación: ordena tablas topológicamente, asigna modo (global override por tabla), resuelve descriptor por tabla, y marca `target_exists_before`.

#### `normalizeGlobalMode(?string $mode): string`
Normaliza el modo a uno de los 3 válidos, default `smart`.

#### `initializeExecutionSummary(array $plan): array`
Crea estructura de summary por tabla con contadores (imported, skipped, errors, mode, created, added_columns, etc.).

#### `preparePlannedTables(array &$plan, array &$summary): void`
Prepara las tablas antes de insertar: crea tablas faltantes desde CREATE TABLE del dump, sincroniza columnas faltantes vía ALTER TABLE, trunca tablas existentes si el modo es `force_source`. Marca tablas como disabled si no se pudieron crear.

#### `createTargetTableFromSource(string $table, array $sourceSchema): bool`
Crea una tabla en la BD destino usando el CREATE TABLE del dump (sin FKs). Retorna true si se creó exitosamente.

#### `buildCreateSqlWithoutForeignKeys(string $table, array $sourceSchema): ?string`
Toma el CREATE TABLE del dump y remueve las definiciones FOREIGN KEY. Retorna SQL limpio o null si no hay schema.

#### `syncMissingTargetColumns(string $table, array $sourceSchema): array`
Ejecuta `ALTER TABLE ... ADD COLUMN` para cada columna que existe en el dump pero no en la BD destino. Registra columnas agregadas en el summary.

#### `clearExistingTable(string $table): void`
Ejecuta `TRUNCATE TABLE` (o DELETE si no se puede truncar por FKs).

#### `withForeignKeyChecksDisabled(callable $callback): mixed`
Ejecuta un callback con `FOREIGN_KEY_CHECKS=0`. Soporta anidación (profundidad de desactivación). Restaura al nivel anterior al salir.

#### `notifyEmptyPlannedTables(array $plan, array &$startedTables, ?callable $onTableStarted): void`
Dispara `$onTableStarted` para tablas que están en el plan pero nunca recibieron filas (para que el frontend sepa que existen).

#### `repairAutoIncrementsForSummary(array $plan, array &$summary): void`
Post-procesamiento: para cada tabla que recibió inserts con IDs explícitos, repara `AUTO_INCREMENT` al `MAX(id)+1`.

#### `repairAutoIncrement(string $table): ?array`
Consulta `MAX(id)` y ejecuta `ALTER TABLE ... AUTO_INCREMENT = N`. Retorna null si la tabla no tiene columna `id`.

#### `currentAutoIncrementValue(string $table): ?int`
Lee el valor actual de AUTO_INCREMENT desde `information_schema.TABLES`.

#### `auditForeignKeysForSummary(array $plan, array &$summary): void`
Post-procesamiento: para cada tabla con FKs en destino, verifica que no haya referencias huérfanas. Registra advertencias.

#### `auditForeignKey(string $table, array $foreignKey): ?array`
Verifica una FK específica contando registros que referencien valores inexistentes.

#### `quoteIdentifier(string $identifier): string`
Envuelve un identifier SQL en backticks escapados.

---

### Métodos de parsing de formatos

#### `detectFormat(string $extension, string $path): string`
Detecta formato por extensión (sql/zip) o por contenido (magic bytes PK para zip, presencia de INSERT/CREATE para sql). Lanza excepción si no reconoce.

#### `parseZip(string $path, ?int $sqlRowStoreLimit): array`
Extrae ZIP a directorio temporal, parsea cada archivo `.sql` con `parseSql()`, mergea datasets, limpia temp. Retorna `[table => [row, ...]]`.

#### `parseSql(string $path, ?int $rowStoreLimit): array`
Wrapper que delega en `scanSqlFile()`.

#### `scanSqlFile(string $path, ?int $rowStoreLimit, ?callable $onAcceptedRow, ?int $maxScanBytes): array`
**Parser FSM (Finite State Machine) en streaming.** Es el corazón del sistema. Lee el archivo en chunks de 64KB y procesa carácter por carácter con dos estados principales:

1. **Buscando header** (`$currentTable === null`): Acumula bytes buscando el regex `/INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?\s*(?:\(([^)]+)\))?\s*VALUES\s*$/is`. Cuando encuentra match, cambia a estado "dentro de VALUES".

2. **Consumiendo tuplas** (`$currentTable !== null`): Dentro de VALUES, rastrea profundidad de paréntesis, strings escapados, y cada tupla `(...)` cerrada se procesa inmediatamente (sin esperar al `;` final). Esto evita que INSERTs gigantes de mysqldump con `--extended-insert` consuman RAM.

Cada tupla se parsea con `parseSqlTuple()`. Si el INSERT tiene lista de columnas, se combina por nombre; si no (mysqldump default), se guarda como array indexado.

Pre-pass: ejecuta `extractDumpTableSchemas()` antes de escanear para tener el mapa de columnas del dump.

Al finalizar: acumula `$lastRowCounts` con conteos exactos incluso si se truncó el dataset en RAM.

El callback `$onAcceptedRow` se usa durante ejecución para spool-to-disk.

#### `spoolFileNameForTable(string $table): string`
Genera nombre de archivo spool seguro (reemplaza caracteres especiales + sha1).

#### `readRowsFromSpool(string $path): \Generator`
Lee archivo spool línea por línea, decodifica base64+unserialize, y yield cada fila con su índice. Usa Generator para no cargar todo en RAM.

#### `findStatementEnd(string $buffer, int $startFrom): int|false`
Método legacy/stub para encontrar `;` final. Ya no se usa en el flujo actual (el FSM maneja fin de statement con el estado). Conservado por compatibilidad.

#### `processSqlStatement(string $statement, array &$datasets, array &$rowCounts): void`
Método legacy. Procesa un statement SQL completo. Reemplazado por el FSM. Conservado por compatibilidad.

#### `splitSqlTuples(string $blob): array`
Divide un blob de valores SQL como `(1,'a'),(2,'b')` en tuplas individuales respetando strings y paréntesis anidados.

#### `parseSqlTuple(string $tuple): array`
Parsea una tupla SQL `1,'abc',NULL` en un array de valores PHP. Maneja strings escapados, NULLs, numéricos.

#### `castSqlValue(string $raw, bool $quoted): mixed`
Castea un valor SQL a PHP: string quoteado → string, NULL → null, numérico → int/float, todo lo demás → string.

#### `parseJson(string $path): array`
Parsea archivo JSON. Si es objeto (tabla → rows), lo usa directamente. Si es array, lo pone bajo `unknown`.

#### `parseCsv(string $path): array`
Parsea CSV: primera fila como header, resto como rows. Intenta adivinar la tabla con `guessTableFromHeader()`.

#### `parseSpreadsheet(string $path): array`
Parsea Excel/Spreadsheet con PhpSpreadsheet. Cada hoja es una tabla. Usa `guessTableFromHeader()` si el nombre de hoja no está en el map.

#### `guessTableFromHeader(array $header): ?string`
Intenta adivinar el nombre de tabla comparando los conflict_keys del header contra el mapa de conflict_key_hints de todas las tablas conocidas.

---

### Métodos de detección y resolución de conflictos

#### `findExisting(string $table, array $keys, array $row): ?array`
Busca una fila existente que coincida con alguna conflict_key. Query con OR Where. Retorna primer match o null.

#### `batchFindExisting(string $table, array $keys, array $rows): array`
**Optimización O(R) → O(1).** Encuentra existentes para TODAS las filas en UNA sola query. Colecta valores únicos por conflict_key, ejecuta `WHERE key1 IN (...) OR key2 IN (...)`, indexa resultados en memoria, y matchea cada fila entrante contra el índice. Retorna `[rowIndex => existingRow]`.

#### `hasConflictValue(array $row, string $key): bool`
Verifica que una fila tenga un valor no-nulo y no-vacío para una key.

#### `resolveConflictKeys(string $table, ?array $mapped): array`
Wrapper que retorna solo los keys desde `resolveConflictIdentity()`.

#### `resolveConflictIdentity(string $table, ?array $mapped): array`
Resuelve las columnas de identidad para upsert en orden de prioridad:
1. Si `identity_priority = 'override'` → intenta conflict_keys explícitos primero
2. Infiere desde índices UNIQUE de la tabla destino (`SHOW INDEX`)
3. Si no hay preferencia override → intenta conflict_keys explícitos
4. Usa `id` si existe en columnas del dump
5. Retorna `[]` si no hay nada

#### `resolveExplicitConflictIdentity(string $table, ?array $mapped): ?array`
Valida que los conflict_keys explícitos existan tanto en dump como en destino. Filtra con `filterUsableConflictKeys()`.

#### `filterUsableConflictKeys(string $table, array $keys): array`
Filtra keys que existan simultáneamente en las columnas del dump y en las columnas de la tabla destino. Si alguna no está en destino, retorna `[]` (no se puede usar upsert).

#### `prepareRowsForConflictDetection(string $table, array $rows): array`
Prepara filas para detección aplicando `sanitizeRow()` a cada una.

#### `inferConflictKeysFromTarget(string $table): array`
Lee `SHOW INDEX FROM` y retorna columnas del PRIMARY KEY (o primer UNIQUE index) como candidatas para conflict_keys.

#### `hasColumnCached(string $table, string $column): bool`
Verifica si una columna existe en la tabla destino, con cache en `$hasColumnCache`.

#### `shouldScanSqlRowsForTable(string $table): bool`
Determina si una tabla del dump debe ser escaneada: si existe en source schemas (tiene CREATE TABLE) o si existe en BD destino.

#### `targetTableExistsCached(string $table): bool`
Cache de `Schema::hasTable()`.

#### `matchedKeys(array $keys, array $row, array $existing): array`
Retorna las keys cuyos valores coinciden entre fila entrante y existente.

---

### Métodos de sanitización y normalización

#### `sanitizeRow(array $row, string $table): array`
**Manejo de schema drift.** Limpia una fila para inserción:

1. Si la fila es **indexada** (array_is_list), mapea posición → nombre de columna usando:
   - Columnas del DUMP (extraídas del CREATE TABLE) — autoritativo, maneja drift
   - Columnas del DESTINO — solo si cardinalidad coincide exactamente
   - Si no se puede mapear → `[]` (error)
2. Filtra con `array_intersect_key()` contra columnas destino: descarta columnas que ya no existen en BD, y las columnas nuevas de BD quedan NULL

#### `injectAuditColumns(string $table, array $row, bool $isUpdate): array`
Auto-llena `created_at`/`updated_at`/`created_by`/`updated_by` para inserts raw que no pasan por Eloquent. Usa cache de metadata de tabla para detectar qué columnas existen.

#### `prepareModelWriteRow(string $table, array $row): array`
Prepara una fila para escritura vía modelo Eloquent: sanitiza + normaliza FKs.

#### `prepareRawImportRow(string $table, array $row): array`
Prepara una fila para raw import: solo sanitiza.

#### `prepareDirectWriteRow(string $table, array $row, bool $isUpdate, bool $normalizeForeignKeys): array`
Prepara fila para escritura directa vía DB::table(): sanitiza + inyecta audit columns + normaliza FKs (opcional).

#### `normalizeForeignKeysForRow(string $table, array $row): array`
Normaliza FKs para una fila individual (delega en normalizeForeignKeysForRows).

#### `normalizeForeignKeysForRows(string $table, array $rows): array`
**Normalización masiva de FKs.** Para cada columna FK en la tabla destino:
1. Colecta valores de todas las filas para esa columna
2. Consulta existencia masiva con `referencedValuesExistenceMap()` (una query por tabla referenciada)
3. Reemplaza valores inválidos: sentinel (`0`, `'0'`) → NULL (si nullable) o fallback configurado
4. Usa cache `$referencedValueExistsCache` para evitar consultas repetidas

#### `foreignKeyNormalizationEnabled(): bool`
Lee `config('smart_import.foreign_key_normalization.enabled')`.

#### `isBlankForeignKeyValue(mixed $value): bool`
True si null o cadena vacía.

#### `isInvalidForeignKeySentinel(mixed $value): bool`
True si el valor está en la lista de sentinel (default `[0, '0']`).

#### `invalidForeignKeySentinelValues(): array`
Lee `config('smart_import.foreign_key_normalization.invalid_sentinel_values')`.

#### `replacementForInvalidForeignKey(array $foreignKey): array`
Decide el reemplazo: si nullable → null, si no → busca fallback configurado.

#### `foreignKeyFallbackValue(string $referencedTable, string $referencedColumn): array`
Resuelve valor fallback para FK no-nullable. Soporta estrategias:
- `'acting_user_or_first_existing'`: intenta el usuario actual, luego el primer registro existente
- `'first_existing'`: primer registro por orden
- Valor escalar fijo: verifica que exista

Usa cache en `$foreignKeyFallbackValueCache`.

#### `referencedValueExistsCached(string $referencedTable, string $referencedColumn, mixed $value): bool`
Verifica si un valor referenciado existe en la tabla padre, con cache.

#### `referencedValuesExistenceMap(string $referencedTable, string $referencedColumn, array $values): array`
Consulta existencia de múltiples valores FK en UNA query con `WHERE IN`. Cachea resultados individualmente.

#### `referencedValueCacheKey(string $referencedTable, string $referencedColumn, mixed $value): string`
Genera clave de cache para existencia de FK (`tabla\0columna\0valor`).

---

### Métodos de inserción batch

#### `canUseBulkPath(array $info): bool`
Determina si una tabla puede usar inserción batch. True si: modo `raw`, o modo `model` con clase no estricta (no en `STRICT_MODEL_CLASSES`).

#### `executeBulkImportTable(string $table, array $info, array $rows, array $existingMap, string $defaultAction, array $perRow): array`
Ejecuta importación batch para una tabla. Itera filas y decide por acción:
- `skip`: omite
- `replace`: acumula en updateRows (con PK) o ejecuta `updateExistingRowByConflictKeys()`
- `duplicate`: remueve PK y acumula en insertRows
- `insert`: acumula en insertRows
Flushea batches con `flushBulkInsertRows()` + `flushBulkUpdateRows()`.

#### `executeRawMergeTable(string $table, array $info, array $rows): array`
Ejecuta merge inteligente para modo raw/smart. Separa filas: las que tienen todas las conflict_keys van a `flushBulkMergeRows()` (upsert), las que no tienen keys van a `flushBulkInsertRows()`.

#### `rawMergeKeys(string $table, array $info): array`
Resuelve keys para merge raw: primero conflict_keys explícitos, luego inferidos de índices UNIQUE de destino, luego `id` del dump, o `[]`.

#### `rowHasAllKeys(array $row, array $keys): bool`
Verifica que una fila tenga valores no-vacíos para todas las keys.

#### `flushBulkInsertRows(string $table, array $rows, int &$errors, bool $normalizeForeignKeys): int`
Inserta filas en chunks de `BULK_CHUNK_SIZE` (500). Si el chunk falla, hace **fallback fila por fila**. Normaliza FKs antes de insertar. Retorna count de insertados.

#### `flushBulkMergeRows(string $table, array $rows, array $keys, int &$errors): int`
Ejecuta `DB::table()->upsert()` en chunks. Agrupa por column signature antes de upsert. Fallback fila por fila si el chunk falla. Retorna count.

#### `flushBulkUpdateRows(string $table, string $pk, array $rows, int &$errors, bool $normalizeForeignKeys): int`
Ejecuta `DB::table()->upsert()` con PK como key de matching, actualizando solo columnas no-PK. Fallback a fila por fila. Retorna count.

#### `groupRowsByColumnSignature(array $rows): array`
Agrupa filas por el conjunto exacto de columnas que poseen (sorted, pipe-joined). Previene que upserts masivos conviertan columnas ausentes en NULL sobre filas existentes con diferentes signatures.

#### `normalizeRowsForBatch(string $table, array $rows, bool $normalizeForeignKeys): array`
Normaliza filas para batch: ordena columnas según el orden de la tabla destino, rellena con null las columnas ausentes. Opcionalmente normaliza FKs.

#### `mergeImportSummary(array $carry, array $chunk): array`
Acumula imported/skipped/errors entre chunks.

---

### Métodos de inserción individual (row-by-row)

Útiles para tablas estrictas (con observers, jobs, lógica de dominio).

#### `validateMapEntry(string $table, ?array $info): ?string`
Valida que la entrada sea ejecutable. Retorna null si OK, o string con razón de skip.

#### `pkOf(string $table, array $info): string`
Retorna el nombre de la PK: `getKeyName()` del modelo si mode=model, `'id'` si raw.

#### `insertRow(string $table, array $info, array $row): void`
Insert ramificado: mode=model → `Model::create()`; mode=raw → `DB::table()->insert()`.

#### `updateRow(string $table, array $info, mixed $existingId, array $row): void`
Update ramificado: mode=model → `Model::query()->whereKey()->update()`; mode=raw → `DB::table()->where(pk)->update()`.

#### `updateExistingRowByConflictKeys(string $table, array $keys, array $row): void`
Actualiza una fila usando conflict_keys como WHERE, sin conocer la PK. Útil cuando no se tiene el ID pero sí las keys.

---

### Métodos de análisis y reporte

#### `extractDumpTableSchemas(string $path): array`
**Pre-pass del archivo SQL.** Escanea en streaming (chunks 64KB) buscando `CREATE TABLE` con regex. Por cada tabla extrae: columnas con tipos, key definitions, foreign keys, dependencias, SQL completo, table_options. El buffer se mantiene en 256KB máximo. Retorna `[table => {table, columns, column_definitions, key_definitions, foreign_key_definitions, dependencies, create_sql, table_options}]`.

#### `resetAnalysisState(): void`
Resetea todos los caches internos entre análisis.

#### `buildAnalysisReport(array $datasets): array`
Construye el reporte que se envía al frontend. Por cada tabla detectada (de CREATE TABLE o de datasets): resuelve descriptor vía `importDescriptor()`, compara columnas fuente vs destino (detecta faltantes/extra), genera warnings (tabla_destino_inexistente, sin_create_table, columnas_faltantes_extra, etc.), incluye sample de 3 filas, conteo exacto de `lastRowCounts`. Ordena por dependencias topológicas.

#### `importDescriptor(string $table): array`
Resuelve metadatos de una tabla: usa `SmartImportTableResolver::resolve()` con entrada legacy del `TABLE_MODULE_MAP`, luego enriquece con conflict_keys resueltos via `resolveConflictIdentity()`.

#### `sortReportByDependencies(array $report): array`
Ordena el reporte por orden topológico de dependencias FK.

#### `tableResolver(): SmartImportTableResolver`
Lazy-init del resolver de tablas.

#### `topologicalSortTables(array $tables): array`
**Ordenamiento topológico (Kahn).** Construye grafo de dependencias FK, luego ordena las tablas para que las padres se procesen antes que las hijas. Maneja ciclos (los añade al final).

#### `dependenciesForTable(string $table): array`
Combina dependencias del source schema (del CREATE TABLE del dump) + dependencias FK de la BD destino. Excluye auto-referencias.

#### `targetForeignKeyDependencies(string $table): array`
Extrae tablas referenciadas por FKs de la tabla en la BD destino, con cache.

#### `targetForeignKeyMetadata(string $table): array`
Consulta `information_schema.KEY_COLUMN_USAGE` para obtener metadatos de FKs: columna, tabla referenciada, columna referenciada, nullable. Cache por tabla.

---

### Métodos misceláneos

#### `setDumpColumns(array $dumpColumns): void`
Inyecta el mapa de columnas del dump (rehidratación desde cache en job async).

#### `setSourceTableSchemas(array $schemas): void`
Inyecta schemas fuente (rehidratación desde cache).

#### `setActingUserId(?int $userId): void`
Establece el usuario que ejecuta la importación (para audit columns). Ejecuta `Auth::onceUsingId()` para contexto Eloquent.

#### `disableForeignKeyChecks(): void`
Desactiva `FOREIGN_KEY_CHECKS` en la conexión actual.

#### `restoreForeignKeyChecks(): void`
Reactiva `FOREIGN_KEY_CHECKS` al nivel anterior.

#### `sanitizeUtf8(mixed $value): mixed`
Limpia strings con bytes UTF-8 inválidos vía `mb_convert_encoding` para evitar `JsonEncodingException` al persistir en DB.

#### `reindexDatasetsWithOffsets(array $datasets, array &$rowIndexes): array`
Reindexa datasets con índices numéricos secuenciales.

#### `tableMetadata(string $table): array`
Cache de metadata de tabla: columnas, columns_flip, presencia de created_at/updated_at/created_by/updated_by. Usa `Schema::getColumnListing()`.

#### `buildConflictPrompt(string $table, array $item): string`
Construye prompt para IA con el conflicto: tabla, keys coincidentes, JSON de registro existente y entrante. Pide respuesta JSON con `{"accion":"omitir|reemplazar|duplicar","razon":"..."}`.

#### `parseAIRecommendation(string $texto): array`
Parsea la respuesta de la IA extrayendo el JSON con regex. Si no encuentra JSON válido, default a `omitir`. Normaliza acción a español.

---

## 2. SmartImportJob

**Archivo:** `app/Modules/Addons/SmartImportExport/Jobs/SmartImportJob.php`

Job que se ejecuta en background vía `php artisan smart-import:run {jobId}`.

### Constructor

`__construct(string $jobId, string $token, array $options, ?int $userId, ?int $logId)`

NO carga datasets en el payload (evita OOM al serializar). Recibe solo el token para rehidratar desde cache.

### Métodos públicos estáticos (gestión de cache + status)

#### `analysisCacheKey(string $token): string`
Retorna `'smart_import:analysis:' . $token`.

#### `executionCacheKey(string $jobId): string`
Retorna `'smart_import:execute:' . $jobId`.

#### `storeExecutionContext(string $jobId, array $payload): void`
Guarda contexto de ejecución en cache (6h).

#### `getExecutionContext(string $jobId): ?array`
Recupera contexto de ejecución desde cache.

#### `forgetExecutionContext(string $jobId): void`
Elimina contexto de cache.

#### `statusKey(string $jobId): string`
Retorna `'smart_import:status:' . $jobId`.

#### `setStatus(string $jobId, array $payload, ?int $logId): void`
Escribe status en cache Y persiste en DB vía `persistStatus()`.

#### `getStatus(string $jobId): array`
Lee status: primero intenta desde DB (persistedStatus), si no hay, desde cache. Esto permite sobrevivir a expiración de cache.

### Métodos de instancia

#### `handle(SmartImportService $service): void`
Método principal.
1. Memory limit a 2G
2. Rehidrata análisis desde cache
3. Inyecta dump_columns y source_tables en el service (para schema-drift-safe mapping)
4. Ejecuta según formato:
   - **SQL/ZIP**: streaming con spool-to-disk via `executeSqlImportFromPath()`
   - **Otros**: carga datasets en memoria via `loadDatasetsForExecution()` + `executeImport()`
5. Trackea progreso por tabla (callback `$onTableStarted`)
6. Al finalizar: persiste totals en DB, limpia archivo temporal y cache
7. En `finally`: siempre limpia archivo y cache

#### `failed(Throwable $e): void`
Maneja fallo del job: setea status `failed` en cache + DB con el mensaje de error.

### Métodos privados estáticos

#### `persistStatus(string $jobId, array $payload, ?int $logId): void`
Guarda el runtime_status en el JSON `ai_analysis` del registro `ImportExportLog`.

#### `persistedStatus(string $jobId): ?array`
Recupera runtime_status desde DB. Si no hay runtime_status, infiere estado desde los campos `status`, `records_processed`, `records_failed`.

### Métodos privados de instancia

#### `updateLog(array $attrs): void`
Actualiza el registro `ImportExportLog` con nuevos atributos.

---

## 3. SmartExportService

**Archivo:** `app/Modules/Addons/SmartImportExport/Services/SmartExportService.php`

Servicio de exportación a SQL/JSON/XLSX.

### Constantes

#### `EXPORT_MODULES`
Define 9 módulos exportables: clientes, finanzas, planes, tickets, vendedores, inventario, red, crm, usuarios. Cada uno lista tablas y columnas sensibles a censurar (passwords, tokens, card numbers).

#### `STORAGE_DIR`
`'app/smart_export'`

### Métodos públicos

#### `getModulesWithCount(): array`
Retorna todos los módulos con conteo de registros por tabla y verificación de existencia. Usado por el frontend para mostrar disponibilidad.

#### `generate(array $modules, string $format, bool $stripSensitive): array`
Genera archivo de exportación:
- SQL: genera INSERT statements por módulo
- JSON: estructura con meta + data
- XLSX: crea Excel con una hoja por tabla
Registra token de descarga en cache (2h). Retorna `{token, filename, format, size, modules}`.

#### `exportToSQL(array $modules, bool $stripSensitive): string`
Genera SQL con `INSERT INTO` en batches de 200 filas. Columnas sensibles son omitidas. Incluye `SET FOREIGN_KEY_CHECKS=0` al inicio.

#### `exportToJSON(array $modules, bool $stripSensitive): string`
Genera JSON estructurado con meta (fecha, módulos) y data (tabla → rows). Columnas sensibles eliminadas.

#### `exportToExcel(array $modules, bool $stripSensitive, string $absolutePath): void`
Genera Excel con PhpSpreadsheet. Una hoja por tabla. Columnas sensibles excluidas.

#### `resolveToken(string $token): ?string`
Resuelve token de descarga a nombre de archivo desde cache.

#### `consumeToken(string $token): void`
Elimina token de cache (consumo one-time).

### Métodos privados

#### `dumpTableSQL(string $table, array $sensitive): string`
Genera INSERTs en batches de 200 filas para una tabla.

#### `quoteSqlValue($value): string`
Escapa valor SQL: NULL → 'NULL', bool → 0/1, numérico → string, string → escapado con comillas.

#### `registerToken(string $token, string $filename): void`
Guarda token → filename en cache por 2h.

#### `tokenKey(string $token): string`
Retorna `'smart_export:token:' . $token`.

---

## 4. SmartImportModelDiscovery

**Archivo:** `app/Modules/Addons/SmartImportExport/Services/SmartImportModelDiscovery.php`

Auto-descubre modelos Eloquent en el códigobase para resolver tabla → clase.

### Métodos públicos

#### `tableModelMap(): array`
Retorna `[table => firstModelClass]`. Usa `tableModelsMap()` y toma el primer modelo de cada tabla.

#### `tableModelsMap(): array`
Escanea todos los archivos PHP en `app/Models/` y `app/Modules/**/Models/`. Por cada archivo:
1. Extrae namespace + class name con regex
2. Verifica que sea subclase de `Model` y no abstracta
3. Instancia sin constructor via Reflection, llama a `getTable()`
4. Agrupa `[table => [class1, class2, ...]]`
Cachea resultado.

#### `modelForTable(string $table, ?string $preferred): ?string`
Retorna el modelo para una tabla. Si hay preferido y está en la lista, lo usa. Si no, el primero.

#### `modelsForTable(string $table): array`
Retorna todos los modelos que mapean a una tabla.

#### `moduleLabelForModel(?string $modelClass): ?string`
Deriva etiqueta de módulo desde el namespace: `App\Modules\{Module}\...` → nombre del módulo en formato headline.

#### `hasTable(string $table): bool`
True si hay al menos un modelo para la tabla.

### Métodos privados

#### `modelFiles(): array`
Busca archivos PHP en paths configurados (`config('smart_import.model_discovery.paths')`). Filtra solo archivos dentro de directorios `Models/`. Retorna paths ordenados.

#### `classFromFile(string $path): ?string`
Extrae `namespace + class` del contenido del archivo PHP con regex. Retorna FQCN o null.

---

## 5. SmartImportTableResolver

**Archivo:** `app/Modules/Addons/SmartImportExport/Services/SmartImportTableResolver.php`

Resuelve una tabla a su descriptor de importación: módulo, modelo, modo, conflict_keys.

### Métodos públicos

#### `resolve(string $table, ?array $legacyRule): array`
Resuelve descriptor para una tabla. Jerarquía de 3 niveles:
1. **Config overrides** (`config('smart_import.overrides')`): prioridad máxima
2. **Legacy map** (`TABLE_MODULE_MAP`): el mapa hardcodeado de ~226 tablas
3. **Auto-discovery**: modelos descubiertos por `SmartImportModelDiscovery`

Retorna: `{known, module, model, mode (raw|model), raw_mode, has_model, strict_model, descriptor_source, model_candidates, conflict_rule, normalizers, warnings}`.

#### `hasDescriptor(string $table, ?array $legacyRule): bool`
True si la tabla tiene override, modelo descubierto, o entrada legacy.

#### `conflictKeyHints(array $legacyMap): array`
Recolecta conflict_keys de overrides + legacy map. Útil para `guessTableFromHeader()`.

### Métodos privados

#### `overrideFor(string $table): array`
Lee `config('smart_import.overrides')` para la tabla.

#### `resolveModel(string $table, array $override, ?array $legacyRule): ?string`
Resuelve modelo: override → legacy → auto-discovery → null.

#### `descriptorSource(string $table, array $override, ?string $model, ?array $legacyRule): string`
Identifica fuente del descriptor: `'override'`, `'model_discovery'`, `'legacy_map'`, o `'schema'`.

#### `descriptorWarnings(string $descriptorSource, bool $hasModel, string $mode, array $modelCandidates): array`
Genera warnings: `sin_modelo_asociado`, `sin_regla_explicita`, `modelo_disponible_pero_forzado_raw`, `multiples_modelos_para_tabla`.

#### `moduleLabelFromTable(string $table): string`
Deriva etiqueta de módulo desde el prefijo del nombre de tabla (ej: `client_` → "Client").

---

## 6. ImportExportController

**Archivo:** `app/Modules/Addons/SmartImportExport/Controllers/ImportExportController.php`

Controlador HTTP que maneja los endpoints de import/export.

### Import (10 métodos)

#### `importIndex()`
Renderiza vista `smart-import.blade.php` que monta el componente Vue `<smart-import>`.

#### `upload(Request $request)`
`POST /configuracion/smart-import/upload`
1. Valida archivo: required, file, extensions:sql,zip, max 2GB
2. Crea `ImportExportLog` con status `pending`
3. Llama a `$importService->analyzeFile()`
4. Guarda análisis en cache por 6h y persiste en `ai_analysis` del log
5. Retorna `{token, format, report[], total_rows}`

#### `preview(Request $request)`
`POST /configuracion/smart-import/preview`
Recupera análisis por token del cache. Retorna reporte, conflicts vacío, supported_modes.

#### `execute(Request $request)`
`POST /configuracion/smart-import/execute`
1. Recupera análisis por token
2. Busca o crea `ImportExportLog`
3. Genera UUID jobId
4. Inicializa status en cache: `{state: "queued", progress: 0}`
5. Guarda contexto de ejecución en cache
6. Llama a `launchSmartImportProcess()` (lanza proceso background)
7. Retorna `{job_id, log_id}`

#### `status(string $jobId)`
`GET /configuracion/smart-import/status/{jobId}`
Retorna status desde `SmartImportJob::getStatus()`.

### Export (4 métodos)

#### `exportIndex()`
Renderiza vista `smart-export.blade.php`.

#### `modules()`
`GET /configuracion/smart-export/modules`
Retorna módulos con conteos via `SmartExportService::getModulesWithCount()`.

#### `generate(Request $request)`
`POST /configuracion/smart-export/generate`
Valida módulos/format/strip_sensitive, genera export, registra en log, retorna token + download_url.

#### `download(string $token)`
`GET /configuracion/smart-export/download/{token}`
Resuelve token, descarga archivo, elimina después de enviar, consume token.

### History (3 métodos)

#### `historyIndex()`
Renderiza vista `import-export-history.blade.php`.

#### `history(Request $request)`
`GET /configuracion/smart-import-export/history`
Retorna logs filtrados por tipo (import/export), limitados a 200.

#### `downloadFromLog(int $id)`
`GET /configuracion/smart-import-export/log/{id}/download`
Descarga export completado desde el registro de log.

#### `destroyLog(int $id)`
`DELETE /configuracion/smart-import-export/log/{id}`
Elimina archivo físico + registro DB.

### Helpers privados

#### `cacheKey(string $token): string`
Retorna `'smart_import:analysis:' . $token`.

#### `launchSmartImportProcess(string $jobId): void`
Lanza proceso background: `nohup php artisan smart-import:run {jobId} > log 2>&1 &`. Resuelve PHP CLI binary con `PhpExecutableFinder`.

#### `resolvePhpCliBinary(): string`
Busca PHP CLI: primero con `PhpExecutableFinder`, luego `PHP_BINDIR . '/php'`.

#### `resolveAdminUser(): string`
Retorna `login_user`, `email`, o `'admin'` como identificador del usuario que ejecuta.

#### `sanitizeForJson(mixed $value): mixed`
Limpia strings con encoding no UTF-8 para evitar `JsonEncodingException`. Detecta encoding (ISO-8859-1, Windows-1252) y reconvierte. Último recurso: `mb_scrub()`.

---

## 7. ImportExportLog

**Archivo:** `app/Modules/Addons/SmartImportExport/Models/ImportExportLog.php`

Modelo Eloquent para la tabla `import_export_logs`. Extiende `BaseModel`.

### Atributos fillable
`type`, `filename`, `format`, `status`, `modules_selected`, `fields_selected`, `ai_analysis`, `output_path`, `job_id`, `records_processed`, `records_failed`, `error_message`, `encrypted`, `admin_user`.

### Casts
`modules_selected` → array, `fields_selected` → array, `ai_analysis` → array, `encrypted` → boolean, `records_processed` → integer, `records_failed` → integer.

### Métodos públicos

#### `markRunning(?string $jobId): void`
Actualiza status a `running`, opcionalmente asigna job_id.

#### `markCompleted(array $extra): void`
Actualiza status a `completed`, mergea datos extra.

#### `markFailed(string $message): void`
Actualiza status a `failed` + error_message.

#### `storeRuntimeStatus(array $payload): void`
Guarda runtime_status embebido en el JSON `ai_analysis` bajo la key `runtime_status`.

#### `runtimeStatus(): ?array`
Extrae runtime_status del JSON `ai_analysis`.

#### `findByJobId(string $jobId): ?self`
Busca el último log por job_id.

---

## Rutas

**Archivo:** `routes.php`

Todas bajo `configuracion/` con middleware `['web', 'auth', 'check_route_permission']`.

| Método | URL | Controller Method | Permiso |
|---|---|---|---|
| GET | `/configuracion/smart-import` | `importIndex` | `smart_import_view` |
| POST | `/configuracion/smart-import/upload` | `upload` | `smart_import_execute` |
| POST | `/configuracion/smart-import/preview` | `preview` | `smart_import_execute` |
| POST | `/configuracion/smart-import/execute` | `execute` | `smart_import_execute` |
| GET | `/configuracion/smart-import/status/{jobId}` | `status` | `smart_import_execute` |
| GET | `/configuracion/smart-export` | `exportIndex` | `smart_export_view` |
| GET | `/configuracion/smart-export/modules` | `modules` | `smart_export_view` |
| POST | `/configuracion/smart-export/generate` | `generate` | `smart_export_execute` |
| GET | `/configuracion/smart-export/download/{token}` | `download` | `smart_export_execute` |
| GET | `/configuracion/smart-import-export` | `historyIndex` | `smart_import_view` `smart_export_view` |
| GET | `/configuracion/smart-import-export/history` | `history` | `smart_import_view` `smart_export_view` |
| DELETE | `/configuracion/smart-import-export/log/{id}` | `destroyLog` | `smart_import_view` `smart_export_view` |
| GET | `/configuracion/smart-import-export/log/{id}/download` | `downloadFromLog` | `smart_export_execute` |

---

## Configuración

**Archivo:** `config/smart_import.php`

```php
return [
    'model_discovery' => [
        'paths' => [app_path('Models'), app_path('Modules')],
    ],
    'strict_tables' => [/* 41 tablas que requieren paso por Eloquent */],
    'foreign_key_normalization' => [
        'enabled' => true,
        'invalid_sentinel_values' => [0, '0'],
        'fallbacks' => ['users.id' => 'acting_user_or_first_existing'],
    ],
    'overrides' => [
        'permissions' => ['module' => 'Administracion', 'mode' => 'raw', 'conflict_keys' => ['name', 'guard_name']],
        // ... más overrides
    ],
];
```

---

## Comando CLI

**Archivo:** `app/Console/Commands/Active/RunSmartImportCommand.php`

```
php artisan smart-import:run {jobId}
```

Rehidrata contexto desde cache, instancia `SmartImportJob`, ejecuta `handle()`. No pasa por el queue — es un proceso directo lanzado por `nohup`.

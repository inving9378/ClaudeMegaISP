# Módulo Smart Import/Export (SmartImportExport)

> `app/Modules/Addons/SmartImportExport/` · slug `addon-smart-import-export` · módulo **addon** (activo).

## 0. En simple
Es la herramienta que sube o baja de golpe un montón de datos del sistema (clientes, pagos, planes...) desde un archivo, en vez de capturarlos uno por uno a mano, y deja un registro de cada vez que se usó.

## 1. Qué es
Addon de **configuración** (`/configuracion/smart-import`, `/configuracion/smart-export`, `/configuracion/smart-import-export`) que permite **importar** un dump SQL o ZIP completo hacia la base de datos activa con un motor de merge inteligente por identidad, y **exportar** una selección de módulos del sistema a SQL/JSON/XLSX. Todas las corridas (import y export) quedan en una **bitácora persistente** (`import_export_logs`).

## 2. Para qué sirve
- **A quien migra/restaura datos** (Irving, soporte): traer un dump de otra instalación (por ejemplo de producción) sin pisar lo que ya existe en destino — el modo SMART decide, tabla por tabla, si algo se inserta, se actualiza o se conserva.
- **A quien necesita sacar un respaldo parcial**: exportar solo Clientes, Finanzas, Planes, etc. a un archivo descargable, opcionalmente censurando columnas sensibles (contraseñas, tokens, tarjetas).
- **A cualquiera que audite qué se importó/exportó y cuándo**: el historial unificado (`/configuracion/smart-import-export`) muestra estado, errores y permite volver a descargar o borrar cada operación.

## 3. Cómo funciona

### 3.1 Piezas de datos
- **`import_export_logs`** (`ImportExportLog`) — una fila por operación de import o export: tipo, archivo, formato, estado (`pending→running→completed/failed`), módulos/campos seleccionados, `ai_analysis` (JSON: análisis del dump + estado de ejecución en vivo), ruta de salida, usuario que la disparó.
- Archivos de trabajo en `storage/app/smart_import/` (dumps subidos, efímeros) y `storage/app/smart_export/` (archivos generados, servidos por token y borrados tras la descarga).
- **`config/smart_import.php`** — resolución dinámica: qué tablas se tratan `strict` (pasan por el modelo Eloquent completo, con sus observers), overrides de módulo/`conflict_keys`/`identity_priority` por tabla, y fallbacks de normalización de FKs.

### 3.2 Flujo de importación (asíncrono, sin `queue:work`)
`ImportExportController` (`upload → analysisStatus → preview → execute → status`):
1. **`upload`** — valida el archivo (`.sql`/`.zip`, máx. 2 GB), lo mueve a `storage/app/smart_import/`, crea el `ImportExportLog` en `pending` y lanza en background (vía `exec(... nohup ... &)`, **no** una cola Laravel) el comando `smart-import:analyze {token}` (`AnalyzeSmartImportCommand`), que corre `SmartImportService::analyzeFile()`: detecta el formato, parsea el dump (streaming, tabla por tabla) y arma un reporte (tablas, columnas, conteo de filas) cacheado bajo `smart_import:analysis:{token}`.
2. **`analysisStatus`** — polling del frontend hasta que el análisis esté listo (`ready`/`analyzing`/`failed`/`not_found`).
3. **`preview`** — devuelve el reporte del análisis y los modos globales soportados: `force_source` (todo gana el origen), `skip_existing` (todo se conserva si ya existe) y `smart` (merge por identidad, el modo recomendado).
4. **`execute`** — encola el jobId de ejecución (contexto en cache, **no** el dataset completo — evita OOM al serializar dumps grandes) y lanza en background el comando `smart-import:run {jobId}` (`RunSmartImportCommand`), que instancia `SmartImportJob` (`implements ShouldQueue`, pero se ejecuta como **proceso CLI one-shot**, no vía `queue:work`) y llama `handle()` directamente. El job procesa tabla por tabla, reporta progreso (`smart_import:status:{jobId}`, también persistido en `ImportExportLog->ai_analysis.runtime_status`) y al final sincroniza la tabla `migrations` del dump con la de destino.
5. **`status`** — polling del progreso/consola en vivo desde el frontend.

### 3.3 El motor de merge (`SmartImportService`, modo SMART)
- **`TABLE_MODULE_MAP`** (~230 tablas) mapea tabla → módulo cosmético + modelo Eloquent (o `mode=>'raw'` para tablas sin modelo, insertadas vía `DB::table()`) + `conflict_keys` (columnas que definen "es el mismo registro"). `SmartImportTableResolver` + `SmartImportModelDiscovery` complementan este mapa **descubriendo modelos dinámicamente** por `getTable()` sobre `app/Models` y `app/Modules/**/Models`, para tablas no listadas a mano.
- **Identidad por tabla**: la mayoría mergea por su **PRIMARY KEY** (preserva el `id` del destino/dump — necesario porque tablas hijas referencian ese id). Un grupo pequeño de tablas de **catálogo** (`permissions`, `roles`, `system_settings`, `migrations`…, marcadas `identity_priority=>'override'` en `config/smart_import.php`) mergea por **llave de negocio** (p.ej. `name+guard_name`) y descarta el `id` del dump al hacer el upsert — evita el 1062 de `ON DUPLICATE KEY` documentado en `CHANGELOG-merge-consistency.md`. Consecuencia conocida y aceptada: las pivotes del dump (`role_has_permissions`, etc.) pueden traer ids viejos sin remapear.
- **`STRICT_MODEL_CLASSES`** / `config('smart_import.strict_tables')` — tablas con lógica de dominio embebida en observers/eventos del modelo (`Client`, `Payment`, `Task`, `Mikrotik`, `InventoryItem`, …) se importan creando/actualizando **instancias reales del modelo** (dispara sus observers) en vez de INSERT masivo, para no dejar el sistema en un estado inconsistente.
- **`SECURITY_CATALOG_TABLES`** (`permissions`, `roles`, pivotes RBAC, `users`, `system_users`) — por defecto se fuerzan a `skip_existing` salvo que el usuario elija otro modo explícitamente para esa tabla, para no pisar credenciales/roles de cuentas vivas con datos de otra instalación.
- Maneja **schema drift** (dump más viejo/nuevo que la BD destino): extrae el orden real de columnas del `CREATE TABLE`/`INSERT` del dump y hace `array_intersect_key` contra la BD destino en vez de asumir que las columnas calzan 1 a 1.
- Corre con `FOREIGN_KEY_CHECKS=0` durante la importación y repara auto-increments/FKs huérfanas al final.

### 3.4 Flujo de exportación (síncrono)
`SmartExportService::EXPORT_MODULES` define ~9 módulos exportables (Clientes, Finanzas, Planes, Tickets, Vendedores, Inventario, Red, CRM, Usuarios), cada uno con su lista de tablas y sus **columnas sensibles** a censurar (`password`, `token`, `card_number`, `two_factor_secret`…). `generate()` arma el archivo (SQL con `INSERT` en lotes, JSON con metadata, o XLSX con `PhpSpreadsheet`, una hoja por tabla) en `storage/app/smart_export/`, registra un token de descarga de 2 h (`Cache`) y el `ImportExportLog` correspondiente. `download` sirve el archivo por token y lo borra tras enviarlo (`deleteFileAfterSend`); `downloadFromLog` permite re-descargar desde el historial mientras el archivo siga en disco.

### 3.5 Historial unificado
`/configuracion/smart-import-export` lista todas las filas de `import_export_logs` (import + export), con borrado (`destroyLog`, elimina también el archivo de salida si existe) y re-descarga de exports completados.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Rutas** (`web`+`auth`+`check_route_permission`, prefijo `/configuracion`): `smart-import/*` (upload, analysis-status, preview, execute, status), `smart-export/*` (modules, generate, download), `smart-import-export/*` (history, log/{id}/download, log/{id} DELETE).
- **Permisos**: `smart_import_view`, `smart_import_execute`, `smart_export_view`, `smart_export_execute`.
- **Comandos artisan** (invocados internamente por el controller vía proceso en background, no pensados para cron): `smart-import:analyze {token}`, `smart-import:run {jobId}`.
- **Bitácora persistente** `import_export_logs` — consumible por cualquier auditoría interna que quiera saber qué se importó/exportó y cuándo.

**Consume**
- **`config/smart_import.php`** — overrides de merge, tablas `strict`, fallbacks de normalización de FKs.
- **Modelos Eloquent de prácticamente todo el sistema** (núcleo + addons: MegaFamilia, IA, WhatsAppAgent, CRM, Configuración…) — es, por diseño, el módulo con más superficie de acoplamiento del repo: cualquier módulo que agregue una tabla relevante para import/export debe sumarse a `TABLE_MODULE_MAP`/`SmartExportService::EXPORT_MODULES` o quedará fuera (o caerá a modo `raw` genérico vía descubrimiento dinámico de modelos).
- **`PhpOffice\PhpSpreadsheet`** — generación/lectura de `.xlsx`.
- **Disco `local`** (`storage/app/smart_import/`, `storage/app/smart_export/`) — almacenamiento efímero de dumps subidos y archivos generados.
- **`User::systemBot()` no aplica aquí** — el autor de cada operación se resuelve del usuario autenticado (`login_user`/email) que la disparó, guardado en `ImportExportLog->admin_user`.

> _Nota de nombres: no confundir con el sistema legado de import/export por módulo (`ModuleRepository::MODULES_FOR_IMPORT`, `Module::getRequestAndStoreMethod()`) usado por varios CRUDs del núcleo para su propio import/export puntual — es un mecanismo aparte, sin relación con este addon. No hay entrada en el registro de contratos inter-módulos (`docs/contratos/`) para SmartImportExport al momento de esta doc._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._

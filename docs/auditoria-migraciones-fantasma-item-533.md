# Auditoría de migraciones fantasma vigentes — item #533

Continuación de #531 (que ya identificó y explicó la causa raíz del drift). Este documento es el
**entregable aprobado por Irving** para #533: auditoría tabular READ-ONLY de las migraciones
fantasma que "muerden hoy" (timestamp `>= 2026_07`), commiteada para que Irving decida caso por
caso qué hacer con cada una. **No se tocó ni el esquema ni la tabla `migrations` de dev.**

## 1. Causa raíz (heredada de #531, sigue vigente)

Los ~10 worktrees del circuito comparten la **misma BD de dev**. Cuando un worker corre
`php artisan migrate` desde su worktree, Laravel aplica **todas** las migraciones pendientes que
encuentra en el filesystem de ESE worktree (incluyendo archivos que solo existen en la rama local
del worker) y escribe la fila en la tabla `migrations` **compartida**. Si esa rama nunca se integra
a `main` (se abandona, se re-escala, pierde la carrera del merge serializado, etc.), la fila queda
en `migrations` con `migration = 'Ran'` pero **sin archivo correspondiente en `main`** → migración
fantasma. Cualquier migración futura que dependa de ella (`->after()`, índice, FK, o un `SELECT`/
`UPDATE` de datos) revienta el `migrate` de producción en el punto de no retorno
(`fail_deploy_no_rollback`), porque el pipeline de deploy **no hace rollback** de una migración que
ya corrió a medias.

## 2. Medición (2026-08-07, read-only, dev)

Cruce de la tabla `migrations` contra **todos** los directorios de migración del repo
(`database/migrations`, `database/migrations_old`, `app/Modules/*/*/migrations`; excluye
`vendor/`):

| Métrica | Valor |
|---|---|
| Archivos de migración en disco | 965 |
| Filas totales en `migrations` (con duplicados) | 3,076 |
| Nombres de migración **distintos** referenciados en `migrations` | 1,302 |
| Fantasmas (nombre distinto sin archivo) | **339** |
| Fantasmas — filas totales si se cuentan con duplicados | 923 |
| **Fantasmas con timestamp `>= 2026_07`** (las que muerden hoy) | **46** |

**Nota aparte (no es objeto de este item, se deja registrada):** de los 1,302 nombres distintos,
**878 tienen más de una fila** en `migrations` (2,652 filas "de más" en total). Es la misma causa
raíz de arriba: varios worktrees corriendo `migrate` en paralelo contra la BD compartida insertan
más de una fila para la misma migración. No afecta la reconciliación de las 46 de abajo (no tiene
duplicados internos), pero es señal de que la tabla `migrations` de dev en sí necesita una limpieza
de higiene — **queda fuera de alcance de #533**, es candidato a item aparte.

Las **46 vigentes** son las 48 originales de la descripción del item, menos las 2 anclas ya
repuestas por el hotfix del 2026-08-06 (`471e89e7` + `eb1f6eff` + `b7ae96bd`):
`add_reporte_dual_to_roadmap_items` y `add_decision_metadata_to_roadmap_items`.

## 3. Recuperables desde alguna rama del circuito (9 de 46)

Búsqueda `git log --all --source` por nombre de archivo sobre las 599 ramas locales/remotas.
Archivo **intacto**, solo falta el cherry-pick a `main` (mismo patrón que aplicó el hotfix):

| Migración | Commit | Rama | Item de origen | Estado del item |
|---|---|---|---|---|
| `2026_07_12_090000_add_password_hash_to_client_main_information` | `93c6e821` | `circuito/item-251-*` | #251 | **`completado`** ⚠️ ver nota |
| `2026_07_12_140000_create_module_contracts_tables` | `a1daad57` | `circuito/item-308-*` | #308 | `requiere_irving` |
| `2026_07_12_140100_seed_module_contracts_initial_candidates` | `32915c52` | `circuito/item-308-*` | #308 | `requiere_irving` |
| `2026_07_14_190000_create_client_cfdi_invoices_table` | `38f7e7bd` | `circuito/item-117-*` | #117 | `aprobado_irving` |
| `2026_07_15_120000_drop_unique_payment_id_from_client_cfdi_invoices` | `9006aac0` | `circuito/item-117-*` | #117 | `aprobado_irving` |
| `2026_08_04_120000_create_parental_otp_codes_table` | `099cfd0b` | `circuito/item-496-*` | #496 | `aprobado_irving` |
| `2026_08_04_120000_create_portal_password_otps_table` | `e05f85dc` | `circuito/item-473-*` | #473 | `aprobado_irving` |
| `2026_08_04_205645_add_permisos_testscriptcontroller` | `e8a1f47b` | `circuito/item-505-*` | #505 | `aprobado_irving` |
| `2026_08_06_150000_create_module_aliases_table` | `27b405b6` | `circuito/item-526-*` | #526 | `aprobado_irving` |

⚠️ **Hallazgo fuera de lo esperado — item #251:** su `estado_aprobacion` ya está en `completado`
(rol/seguridad: contraseñas de cliente en texto plano), pero `merge_commit` está **vacío** y su
migración **nunca llegó a `main`** (sigue solo en la rama `circuito/item-251-*`, de ahí que aparezca
aquí como fantasma). En dev la columna `client_main_information.password_hash` **sí existe** (la
migración corrió), pero puede haber quedado cubierta por el trabajo posterior de migración a
bcrypt vía `PasswordService` (ver memoria del proyecto). **No se tocó nada** — se deja señalado
para que Irving confirme si #251 quedó obsoleto por el trabajo de bcrypt o si su commit realmente
falta en `main`.

Los otros 7 items de origen siguen `pending` en la Hoja de Ruta con sus ramas vivas — es decir, la
migración "recuperable" no es un archivo huérfano suelto: es una rama de trabajo todavía en curso
que aún no se integró.

## 4. Sin archivo en ninguna rama (37 de 46)

Clasificadas por patrón de nombre + verificación **en vivo** contra el esquema de dev
(`information_schema` / `Schema::hasColumn`). "Existe" confirma que el efecto de la migración sí
quedó en el esquema (aunque el archivo se perdió); "NO existe" es señal de alerta — o el nombre
inferido no es el real, o el cambio nunca cuajó, o fue revertido por otro commit.

### 4a. Agregan columna(s) — 21

| Migración | Tabla | Columna inferida | ¿Tabla existe? | ¿Columna existe? |
|---|---|---|---|---|
| `2026_07_11_220000_add_modelo_to_circuito_ejecuciones` | circuito_ejecuciones | modelo | SÍ | SÍ |
| `2026_07_12_000000_add_unique_clave_rastreo_to_reported_payments` | reported_payments | clave_rastreo (índice unique) | SÍ | ver nota¹ |
| `2026_07_12_090000_add_modelo_to_circuito_ejecuciones` | circuito_ejecuciones | modelo | SÍ | SÍ (dup. de arriba) |
| `2026_07_12_100000_add_phone_app_brand_to_fleet_devices` | fleet_devices | phone_app_brand | SÍ | NO |
| `2026_07_12_140000_add_modelo_to_circuito_ejecuciones` | circuito_ejecuciones | modelo | SÍ | SÍ (dup. de arriba) |
| `2026_07_14_150000_add_link_code_to_parental_devices` | parental_devices | link_code | SÍ | SÍ |
| `2026_07_14_170000_add_idempotency_key_to_recurring_charge_attempts` | recurring_charge_attempts | idempotency_key | SÍ | SÍ |
| `2026_07_14_210000_add_unique_invoice_attempt_to_recurring_charge_attempts` | recurring_charge_attempts | invoice_attempt (índice unique) | SÍ | ver nota¹ |
| `2026_07_15_000000_add_branch_diff_cache_to_roadmap_items` | roadmap_items | branch_diff_cache | SÍ | NO² |
| `2026_07_15_170000_add_supervisor_eta_to_roadmap_items` | roadmap_items | supervisor_eta | SÍ | NO |
| `2026_07_15_180000_add_idempotency_lock_to_recurring_charge_attempts` | recurring_charge_attempts | idempotency_lock | SÍ | NO |
| `2026_08_04_120000_add_avg_speed_to_internet_consumptions_table` | internet_consumptions | avg_speed | SÍ | NO |
| `2026_08_04_143100_add_resolver_bandeja_metadata_to_roadmap_items` | roadmap_items | resolver_bandeja_metadata | SÍ | NO |
| `2026_08_04_160000_add_pin_lockout_to_parental_profiles` | parental_profiles | pin_lockout | SÍ | NO |
| `2026_08_04_210000_add_resolver_sugerencia_to_roadmap_items` | roadmap_items | resolver_sugerencia | SÍ | SÍ |
| `2026_08_05_000000_add_rating_to_tickets` | tickets | rating | SÍ | SÍ |
| `2026_08_05_180000_add_pdf_xml_paths_to_invoices_table` | invoices | pdf_xml_paths | SÍ | NO |
| `2026_08_05_190000_add_pin_lockout_to_parental_devices` | parental_devices | pin_lockout | SÍ | NO |
| `2026_08_05_211500_add_speed_columns_to_internet_consumptions` | internet_consumptions | speed_columns (varias) | SÍ | ver nota³ |
| `2026_08_06_100000_add_approval_workflow_to_client_fiscal_data` | client_fiscal_data | approval_workflow | SÍ | NO |
| `2026_08_06_150000_add_resolver_bandeja_metadata_to_roadmap_items` | roadmap_items | resolver_bandeja_metadata | SÍ | NO (dup. de arriba) |

¹ Los `add_unique_*_to_*` casi seguro agregan un **índice único** sobre una columna ya existente,
no una columna nueva llamada literalmente `unique_...` — el nombre de columna en la tabla es un
parseo automático del nombre de archivo, no una lectura del código (el código no existe). Requiere
`SHOW INDEX` puntual si se decide reconstruir.
² `roadmap_items` sí tiene campos afines (`branch`, `merge_commit`, `branch_diff_status`,
`branch_files_count`, `branch_ahead_count`, `branch_has_content`, `branch_checked_at`) — es
probable que esta migración fantasma haya sido **superada** por una migración posterior (con
archivo real) que ya cubre esa función con otro nombre de columna.
³ Mismo caso que ¹: "columnas de velocidad" plural, el parseo de un solo nombre no aplica.

### 4b. Crean tabla — 10

| Migración | Tabla inferida | ¿Existe en dev? |
|---|---|---|
| `2026_07_12_100000_create_reglas_operacion_table` | reglas_operacion | SÍ |
| `2026_07_12_130000_create_payment_idem_tokens_table` | payment_idem_tokens | SÍ |
| `2026_07_14_000000_create_reglas_operacion_table` | reglas_operacion | SÍ (dup. de arriba) |
| `2026_07_14_120000_create_portal_otp_codes_table` | portal_otp_codes | SÍ |
| `2026_08_04_150000_create_megafamilia_otps_table` | megafamilia_otps | SÍ |
| `2026_08_04_210000_create_roadmap_module_aliases` | roadmap_module_aliases | SÍ |
| `2026_08_04_990000_create_client_fiscal_data_requests_table` | client_fiscal_data_requests | SÍ |
| `2026_08_05_190000_create_parental_notification_tokens_table` | parental_notification_tokens | SÍ |
| `2026_08_05_210000_create_parental_child_service_links_table` | parental_child_service_links | SÍ |
| `2026_08_06_120000_create_megafamilia_login_otp_codes_table` | megafamilia_login_otp_codes | SÍ |

Las 10 tablas existen tal cual en dev → **reconstruible por `SHOW CREATE TABLE`** si Irving decide
reponerlas (ver PASO 3 de la propuesta original de #531).

### 4c. Datos / lógica / permisos y roles (no son DDL de esquema) — 6

| Migración | Qué parece hacer (por nombre) | Verificado en vivo |
|---|---|---|
| `2026_07_12_090100_mark_pruebasdev_default_instance` | marca una fila (`pruebasdev` como instancia default) | no verificado — requiere saber la tabla destino |
| `2026_07_12_100100_create_reglas_editar_permission` | crea el permiso `reglas.editar` | **SÍ existe** (`permissions.id=760`) |
| `2026_07_14_120000_reconcile_fleet_subscriptions_model_d` | reconcilia datos de suscripciones de Flotas (Modelo D) | no verificado — es lógica, no una fila puntual |
| `2026_07_14_150000_flotas_fase6_modelo_d_tiers` | siembra tiers de Flotas Fase 6 Modelo D | no verificado |
| `2026_07_15_150000_seed_megafamilia_premium_contratable_service` | siembra el servicio contratable premium de MegaFamilia | no verificado |
| `2026_07_15_170100_create_supervisor_role` | crea el rol `supervisor` | **SÍ existe** (`roles.id=31`) |

Estas son las más peligrosas de reconstruir "a ciegas": no son estructura, son **datos/lógica** —
si el nombre no basta para saber exactamente qué filas/valores sembraron, reescribirlas requiere
inspeccionar el estado vivo de cada módulo caso por caso (exactamente lo que #531 ya señaló para
las 6 equivalentes que identificó).

## 5. Conclusión y siguiente paso

Este documento cierra el **PASO 1** del plan de #531/#533 (clasificar las 48/46: recuperables vs.
perdidas). Conforme a la opción aprobada por Irving para este item (auditar primero, sin tocar
esquema ni tabla `migrations`, y escalar para decisión caso por caso), **no se ejecutó ninguna
reposición, cherry-pick ni reescritura** — eso es materia de una decisión posterior de Irving sobre
cada fila (o grupo) de las tablas de arriba: cuáles cherry-pickear desde su rama, cuáles reconstruir
por esquema, cuáles reescribir desde cero, y cuáles simplemente documentar y dejar así.

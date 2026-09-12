# Fase 3a — Mapa completo de puntos de escritura de `seller_id` en `client_main_information`

Item #9990962 (sub-item de #9990878, precondición de Fase 3b #9990963). Investigación READ-ONLY,
sin cambios de código de negocio. Completa el mapeo que ya venía avanzado en la descripción del
propio item, resolviendo los 3 puntos que quedaban pendientes: el path real de SmartImportExport,
el endpoint de edición de vendedor desde la ficha, y cuál clase `ClientMainInformation` usa cada uno.

## Los 4 callsites reales confirmados

| # | Callsite | Mecanismo | Clase Eloquent (si aplica) | ¿Dispara observer? |
|---|----------|-----------|------------------------------|---------------------|
| 1 | `ClientController::store()` → `clientCreateClientMainInformation($request)` (1 arg) → `ClientTrait::clientCreateClientMainInformation` (`app/Http/Traits/Models/Client/ClientTrait.php:27-40`) → `$this->client_main_information()->create(...)` | Eloquent | `App\Modules\Core\Clientes\Models\ClientMainInformation` (**MODULAR**) | Sí, si se observa la clase MODULAR |
| 2 | `ClientController::importData()` → `clientCreateClientMainInformation($request, $model)` (2 args, propio del controller, `app/Modules/Core/Clientes/Controllers/ClientController.php:175-203`) | RAW — `DB::table('client_main_information')->insertGetId($input)` | — (sin clase) | **No** — necesita parche explícito |
| 3 | SmartImportExport, modo **SMART** (default, el documentado en CLAUDE.md como el merge real) → `executeRawMergeTable()` → `flushBulkMergeRows()` (`app/Modules/Addons/SmartImportExport/Services/SmartImportService.php:3511-3620`) | RAW — `DB::table($table)->upsert($normalized, $keys, $updateColumns)` | — (sin clase, ver nota abajo) | **No** — necesita parche explícito |
| 4 | Edición de un cliente existente (pestaña "Contrato" de `InformationClientCrud.vue`, incl. reasignar vendedor) → `POST /cliente/update/{id}` → `ClientInformationController::update()` (`app/Modules/Core/Clientes/Controllers/ClientInformationController.php:34-123`) → `$this->saveSingleRelationIfExist('App\Models\Client', $client, collect($input))` (`app/Http/Traits/SingleRelationTrait.php:18-43`) → `App\Models\ClientMainInformation::find($id)->update($input)` | Eloquent | `App\Models\ClientMainInformation` (**LEGACY**) | Sí, si se observa la clase LEGACY |

### Detalle del callsite 4 (el que faltaba ubicar)

`Client::SINGLE_RELATIONS['ClientMainInformation']` (`app/Modules/Core/Clientes/Models/Client.php:77-87`)
define `relation_name=client_main_information`. `saveSingleRelationIfExist()` construye
`$modelToSave = 'App\Models\\' . $modelName` → siempre resuelve a la clase bajo `App\Models\`
(legacy), nunca a la modular, sin importar desde qué controller se invoque. Verificado que
`ClientInformationController::update()` es el único consumidor de `saveSingleRelationIfExist`
para clientes y es la ruta real detrás del formulario CRUD genérico de la ficha
(`dataForm.data.submit("post", /cliente/${props.action})` en `InformationClientCrud.vue:672`,
ruteado por `Route::post('/update/{id}', [ClientInformationController::class, 'update'])` en
`app/Modules/Core/Clientes/routes.php:59`).

### Detalle del callsite 3 (SmartImportExport)

`TABLE_MODULE_MAP['client_main_information']` declara `'model' => ClientMainInformation::class`
(que en ese archivo importa la clase **LEGACY**, `App\Models\ClientMainInformation`), pero esa
metadata solo se usa para `pkOf()`/`insertRow()`/`updateRow()` — el camino de **REPLACE**
(`GLOBAL_MODE_FORCE_SOURCE` o `SKIP_EXISTING` sin destino previo). El modo real documentado en
CLAUDE.md ("Modo SMART = upsert por identidad", la única ruta que corre en la práctica para
`client_main_information`, tabla identidad-por-PK) va por `executeRawMergeTable()` →
`flushBulkMergeRows()`, que **siempre** usa `DB::table($table)->upsert(...)` sin importar el
`mode` declarado en el mapa — 100% RAW, sin instanciar ninguna clase Eloquent, sin observers.

## HALLAZGO CRÍTICO (nuevo, verificado empíricamente) — las 2 clases NO comparten observers

`app/Models/ClientMainInformation.php` es un proxy que **extiende**
`App\Modules\Core\Clientes\Models\ClientMainInformation` (mismo patrón que `App\Models\Client`).
La intuición sería que, por herencia, un observer registrado en la clase padre (modular) también
cubre a la clase hija (legacy) — **eso es FALSO para eventos de modelo Eloquent**.

Eloquent registra y dispara eventos de modelo con el nombre de evento
`"eloquent.{$event}: " . static::class` (`HasEvents::registerModelEvent()` y
`HasEvents::fireModelEvent()`, `vendor/laravel/framework/.../Concerns/HasEvents.php:184` y `:216`),
y `static::class` usa **late static binding sobre la clase concreta en tiempo de ejecución**, no
la clase donde se llamó `observe()`. Verificado en tinker (dentro de una transacción con
rollback, sin persistir nada):

```
Modular::observe(...) registrado → guardar vía instancia LEGACY → observer modular: NO se dispara
Legacy::observe(...) registrado  → guardar vía instancia MODULAR → observer legacy: NO se dispara
```

**Consecuencia directa para Fase 3b (#9990963):** el texto de #9990963 dice "enganchar SOLO a la
que de verdad usan los callsites Eloquent" asumiendo que hay una única clase Eloquent en juego.
Esta fase confirma que **hay DOS clases Eloquent distintas en dos callsites distintos** (modular
en alta nueva, legacy en edición de ficha) y que un observer en solo una de ellas deja SIN doble
escritura al otro flujo — silenciosamente, sin error, exactamente el riesgo que la propia
"ADVERTENCIA DE DUPLICACIÓN DE CLASE" del item anticipaba. La implementación de #9990963 debe
registrar el observer explícitamente en **ambas** clases (`App\Modules\Core\Clientes\Models\ClientMainInformation::observe(...)`
y `App\Models\ClientMainInformation::observe(...)`), además de los 2 parches raw (callsites 2 y 3).

## Cobertura del backfill (ya verificada, sin cambios en esta fase)

`client_main_information`: 5611 filas totales, 4195 con `seller_id`. De esas, 1856 ya con
`colaborador_id` poblado = 100% de lo resoluble (`identidad:backfill-colaborador-id --dry-run`
sobre las 2339 restantes devuelve "resueltas: 0"). Las 2339 sin match son estructuralmente
huérfanas (apuntan a `seller_id` de usuarios admin sin fila en `talento_colaboradores`; el caso
dominante es `user_id=8`, Irving en dev, con 2127 filas). El criterio ">=99%" de q3 se cumple
como "100% de lo resoluble", no como cobertura total del histórico — la propia descripción del
item confirma que el criterio de éxito real son los registros nuevos tras activar el flag, no el
histórico.

## Sin cambio de código de negocio

Esta fase es investigación pura (nivel B, autorizada por el revisor como "auditorías y mapas
READ-ONLY"). El trabajo de implementación (feature flag, servicio de resolución, observer en
ambas clases, parches raw en los callsites 2 y 3) sigue en Fase 3b (#9990963, `requiere_irving`).

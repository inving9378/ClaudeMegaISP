# Item #9990634 — CRM: declaración vs. realidad en 3 endpoints de `documentos-huerfanos` (RESUELTO — hallazgo obsoleto, sin acción)

## Qué reportaba el item

El Motor de Auditoría Continua (#559) marcó estos 3 endpoints declarados en
`app/Modules/Core/CRM/module.json` como "no resuelven a ninguna ruta registrada":

- `GET /crm/documentos-huerfanos`
- `GET /crm/documentos-huerfanos/csv`
- `GET /crm/documentos-huerfanos/data`

El propio item pedía comparar contra `php artisan route:list` para decidir si corregir el
`module.json` (caso b: se construyó con otra ruta) o el código (caso a: nunca se construyó).

## Investigación

Las 3 rutas **ya existen, registradas exactamente con el mismo método y path que declara
`module.json`**, desde el commit `12cd859c` (2026-09-04 13:39:40 -0600, item circuito #9990244):

```
app/Modules/Core/CRM/routes.php:44-46
    Route::get('/documentos-huerfanos', [CrmOrphanDocumentController::class, 'index']);
    Route::get('/documentos-huerfanos/data', [CrmOrphanDocumentController::class, 'data']);
    Route::get('/documentos-huerfanos/csv', [CrmOrphanDocumentController::class, 'csv']);
```

registradas bajo `Route::middleware(['web','auth','check_route_permission'])->prefix('crm')`.

Ese commit es **anterior** a la creación de este item (2026-09-08 12:56), así que no es un caso de
"la ruta se agregó después del escaneo": ya existía cuando el auditor corrió.

**Verificado hoy, punto por punto:**

1. `php artisan route:list --path=crm | grep huerf` → las 3 rutas aparecen registradas
   (`crm/documentos-huerfanos`, `crm/documentos-huerfanos/csv`, `crm/documentos-huerfanos/data`),
   método GET|HEAD.
2. Controller `CrmOrphanDocumentController` tiene los 3 métodos (`index`, `data`, `csv`) que
   `routes.php` referencia.
3. Vista `app/Modules/Core/CRM/views/documentos_huerfanos.blade.php` existe.
4. Permiso `crm_document_view_huerfanos` (el que declara `module.json` para los 3 endpoints)
   existe en la tabla `permissions` (id=807) — no es un permiso fantasma.
5. Reproducido el **matching exacto** que usa el detector
   (`AuditorService::detSpecDesalineada()` + `indiceMetodoUri()`/`claveRuta()`) en tinker contra
   `Route::getRoutes()` real: las 3 claves `GET /crm/documentos-huerfanos(/data|/csv)` se
   encuentran **FOUND**, ninguna queda `MISSING`.
6. Corrido el comando real del motor, `php artisan circuito:auditor --modulo=CRM --forzar
   --detalle` → **"Ningún módulo tiene gaps nuevos"**, cero items que se crearían. Es exactamente
   el DoD que el propio item pedía ("el gap ya no aparece si se vuelve a correr
   `php artisan circuito:auditor --dry`").

## Conclusión

El hallazgo no es reproducible en el estado actual del código: declaración (`module.json`) y
realidad (`routes.php` + controller + vista + permiso) **ya dicen lo mismo**. No hay ni "endpoint
sin construir" (caso a) ni "ruta con otro path" (caso b) que corregir.

No se determinó con certeza la causa exacta de por qué el auditor lo marcó el 2026-09-08 (posible
condición de carrera del escaneo contra un checkout/worktree que en ese instante no tenía el
commit `12cd859c`/`7485a3f2` del 2026-09-04, o un artefacto puntual del ciclo de auditoría) — no
es relevante para el cierre: lo que importa es que hoy, con el código real, el gap no existe.

**Sin cambio de código** — nada que construir ni que corregir en `module.json` ni en `routes.php`.

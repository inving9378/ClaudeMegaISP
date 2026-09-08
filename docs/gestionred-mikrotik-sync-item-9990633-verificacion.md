# Item #9990633 — GestionRed: /red/mikrotik-sync del menú "no resuelve a ninguna ruta" (RESUELTO — premisa incorrecta / gap ya cerrado antes de la auditoría)

## Hallazgo del auditor

El Motor de Auditoría Continua (#559) creó este item el 2026-09-08 12:56 con el detector
`detEnlacesRotos` (`AuditorService::detEnlacesRotos()`): según su reporte, `GestionRed/module.json`
ofrece `/red/mikrotik-sync` en la sección `menu`, pero ninguna ruta GET registrada la atiende → 404.

## Verificación

El código real de `main` contradice el hallazgo. La ruta existe desde el commit `d566f759`
("feat(gestion-red): endpoint read-only + reintento manual de sync Mikrotik (#677)"), fechado
**2026-08-28 22:06** — 11 días **antes** de que el auditor creara este item:

```
Route::prefix('mikrotik-sync')->group(function () {
    Route::get('/', [MikrotikSyncController::class, 'index']);
    Route::get('/api/servicios', [MikrotikSyncController::class, 'servicios']);
    Route::post('/api/servicios/{tipo}/{id}/reintentar', [MikrotikSyncController::class, 'reintentar']);
});
```

Confirmado en esta vuelta, contra el `main` actual:

1. `php artisan route:list | grep mikrotik-sync` → `GET|HEAD red/mikrotik-sync` registrada, apuntando
   a `MikrotikSyncController@index`.
2. Prueba directa del mismo mecanismo que usa el detector
   (`RouteFacade::getRoutes()->match(Request::create('/red/mikrotik-sync', 'GET'))`) → **matchea**
   la ruta `red/mikrotik-sync` sin excepción.
3. `MikrotikSyncController::index()` existe, lintea limpio (`php -l`) y devuelve una vista real
   (`view('addon-gestion-red::mikrotik-sync.index')`); el archivo
   `app/Modules/Addons/GestionRed/views/mikrotik-sync/index.blade.php` existe.
4. El permiso `mikrotik_sync_view_dashboard` (el que gatea el link del menú) existe en la tabla
   `permissions`.
5. **DoD del propio item** ("el gap ya no aparece si se vuelve a correr `php artisan
   circuito:auditor --dry`"): se corrió `php artisan circuito:auditor --modulo=GestionRed --forzar`
   (dry-run por default) y el único gap que reporta hoy para GestionRed es uno completamente
   distinto — `null_safety` en `OLTsProvisionController.php` — no el enlace de mikrotik-sync.
   Invocando `detEnlacesRotos('GestionRed', ...)` directo por reflexión el resultado es un arreglo
   **vacío**: el detector no encuentra ningún enlace roto en el módulo.

## Causa del falso positivo

No se pudo determinar con certeza por qué el auditor lo marcó el 2026-09-08 si el fix llevaba 11
días en `main` — la hipótesis más plausible es que la corrida del auditor que generó este item
ocurrió en un proceso/worker que había booteado el framework **antes** de que ese commit llegara a
la copia de trabajo que ese proceso tenía montada (mismo tipo de staleness que motiva el
`queue:restart` tras cada deploy en este proyecto), pero no hay forma de confirmarlo
retroactivamente y no cambia el veredicto: verificado con el código real de HOY, el gap no existe.

## Conclusión

**Sin cambio de código.** La ruta, el controller, la vista y el permiso ya estaban completos y
funcionando antes de que este item se creara. Se cierra documentando la verificación, mismo patrón
que otros items "RESUELTO — premisa incorrecta" de este repo (#37, #75, #103, #414, #733, #741,
#753, #9990003, #9990353).

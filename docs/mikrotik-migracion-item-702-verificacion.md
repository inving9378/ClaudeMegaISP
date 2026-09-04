# Verificación migración Mikrotik — item #702 (RESUELTO — premisa incorrecta)

El item #702 pedía una migración aditiva sobre `routers` para trackear el último estado de
conexión Mikrotik (`mikrotik_connectivity_status` boolean + `mikrotik_status_changed_at`
timestamp), como **sub-item prerequisito** de un hermano "699b" que se encargaría de escribir
y leer esas columnas (detección offline→online en `mikrotik:sync-ping`).

**Esa premisa ya no aplica.** Investigación (2026-08-28):

## Lo que ya existe en `main`

El propio item padre, **#699 ("Mikrotik: Fase 1 — detección de disponibilidad")**, fue
resuelto directamente por Irving en un solo commit ya mergeado a `main`:

```
4f77e867 feat(gestion-red): trackea disponibilidad de router Mikrotik (Fase 1 #699)
```

Ese commit trae **ambas piezas** que #702 y su hermano #703 iban a construir por separado:

1. **Migración** `database/migrations/2026_08_28_950000_add_mikrotik_last_status_to_routers_table.php`
   — aditiva, con guard `Schema::hasColumn`, sin `drop` en `down()` (misma convención que pedía
   #702). Agrega:
   - `routers.mikrotik_last_status` (`string(10)`, nullable) — equivalente funcional a lo que
     #702 llamaba `mikrotik_connectivity_status`, pero como string `'up'`/`'down'` en vez de
     boolean.
   - `routers.mikrotik_status_changed_at` (`timestamp`, nullable) — **nombre idéntico** al que
     pedía #702.
2. **Lógica de escritura/detección** en
   `app/Console/Commands/Active/SyncPingMonitoring.php::trackRouterAvailability()` — ya lee y
   escribe esas columnas en cada corrida de `mikrotik:sync-ping`, y loguea la transición
   offline→online. Esto es exactamente el trabajo que #702 delegaba a "699b" (que nunca se creó
   como item separado — #699 lo absorbió directo).

Verificado en la BD de dev (`Schema::getColumnListing('routers')`): `mikrotik_last_status` y
`mikrotik_status_changed_at` presentes.

## Por qué no se agrega la columna `mikrotik_connectivity_status`

`grep` de `mikrotik_connectivity_status` en todo el repo (`app/`, `resources/`, `routes/`,
`config/`, `database/`) → **cero resultados**: ningún consumidor la espera con ese nombre.
El semáforo de conectividad que el item quería (último estado conocido + cuándo cambió) ya
está cubierto por `mikrotik_last_status`/`mikrotik_status_changed_at`, ya en uso real. Agregar
una segunda columna boolean con semántica duplicada sería un campo muerto desde el día uno
(MINIMALISMO — sin consumidores no se agrega schema nuevo).

`Router::$fillable` no requiere cambio: `trackRouterAvailability()` escribe con `$router->update([...])`
directo sobre columnas ya migradas, no depende de `fill()`/mass-assignment sobre este par.

## Nota para el hermano #703

#703 ("Mikrotik Fase 1b: detectar transición offline→online en mikrotik:sync-ping y loguearla")
también parece ya cubierto por el mismo commit `4f77e867` (`trackRouterAvailability()` ya
detecta y loguea offline→online). No se toca #703 aquí (fuera de mi alcance — un solo item por
dueño); queda para que su propio ejecutor o el revisor lo verifique y cierre si aplica.

**Sin cambio de código funcional.** Solo esta nota de verificación.

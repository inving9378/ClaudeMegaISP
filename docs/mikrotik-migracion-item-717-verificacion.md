# Verificación migración Mikrotik — item #717 (RESUELTO — premisa incorrecta, 4ta vez)

El item #717 es sub-item de seguimiento de **#715** (mismo alcance exacto: agregar
`routers.mikrotik_connectivity_status` boolean nullable + `routers.mikrotik_status_changed_at`
timestamp nullable, migración aditiva con guard `hasColumn`). Es la **4ta** vez que este mismo
alcance atómico se re-encola con la misma premisa: `#702 → #708 → #715 → #717`, cada uno creado
para sortear un falso positivo de `circuito:cabida` sobre el anterior, sin que la premisa
técnica cambiara entre medio.

**Re-verificado en esta vuelta (2026-08-29), sin cambios desde entonces:**

## Lo que ya existe en `main`

`4f77e867 feat(gestion-red): trackea disponibilidad de router Mikrotik (Fase 1 #699)`, ya
mergeado, trae:

1. **Migración** `database/migrations/2026_08_28_950000_add_mikrotik_last_status_to_routers_table.php`
   — aditiva, guard `Schema::hasColumn`, sin `drop` en `down()` (misma convención que pide #717).
   - `routers.mikrotik_last_status` (`string(10)`, nullable) — equivalente funcional a lo que
     #717 llama `mikrotik_connectivity_status`, como string `'up'`/`'down'` en vez de boolean.
   - `routers.mikrotik_status_changed_at` (`timestamp`, nullable) — **nombre idéntico** al que
     pide #717.
2. **Lógica de escritura** en
   `app/Console/Commands/Active/SyncPingMonitoring.php::trackRouterAvailability()` — ya lee y
   escribe ambas columnas en cada corrida de `mikrotik:sync-ping`.

Confirmado en la BD de dev en esta vuelta (`Schema::getColumnListing('routers')`):
`id,title,type_of_nas,vendor_model,location_id,physical_address,ip_host,nas_ip,secret_radius,
pool,authorization_accounting,status,mikrotik_last_status,mikrotik_status_changed_at,created_at,
updated_at` — presentes `mikrotik_last_status` y `mikrotik_status_changed_at`, ninguna columna
`mikrotik_connectivity_status`.

## Por qué no se agrega la columna `mikrotik_connectivity_status`

`grep -rn "mikrotik_connectivity_status" app/ resources/ routes/ config/ database/` → **cero
resultados** (idéntico a #702/#708/#715). Ningún consumidor espera esa columna. El semáforo de
conectividad que pide el item (último estado conocido + cuándo cambió) ya está cubierto y en uso
real por `mikrotik_last_status`/`mikrotik_status_changed_at`. Agregar una segunda columna boolean
con semántica duplicada sería campo muerto desde el día uno (MINIMALISMO — sin consumidores no se
agrega schema nuevo).

## Corte del bucle (esta vez, a propósito)

Las tres vueltas anteriores (#702, #708, #715) documentaron la misma conclusión pero cada una
dejó la puerta abierta a que un `circuito:cabida` con falso positivo generara un sub-item
hermano con el mismo alcance exacto, perpetuando el ciclo. **Esta vuelta NO crea un sub-item de
seguimiento** — no hay nada pendiente que dividir: la conclusión técnica es estable desde #702
(2026-08-28) y no depende del tamaño de la tarea, así que un 5to reintento llegaría a la misma
respuesta. #717 se cierra como hoja final de la cadena.

**Sin cambio de código funcional.** Solo esta nota de verificación.

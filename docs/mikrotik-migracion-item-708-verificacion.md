# Verificación migración Mikrotik — item #708 (RESUELTO — premisa incorrecta)

El item #708 es sub-item de seguimiento de **#702** (mismo alcance exacto: agregar
`routers.mikrotik_connectivity_status` boolean nullable + `routers.mikrotik_status_changed_at`
timestamp nullable, migración aditiva con guard `hasColumn`). #708 se creó a las 17:24 del
2026-08-28; #702 se investigó y cerró a las 17:53 del mismo día documentando que la premisa ya
no aplicaba — ver `docs/mikrotik-migracion-item-702-verificacion.md`. #708 quedó con la premisa
vieja porque nació antes de esa investigación.

**Re-verificado en esta vuelta (2026-08-29), sin cambios desde entonces:**

## Lo que ya existe en `main`

`4f77e867 feat(gestion-red): trackea disponibilidad de router Mikrotik (Fase 1 #699)`, ya
mergeado, trae:

1. **Migración** `database/migrations/2026_08_28_950000_add_mikrotik_last_status_to_routers_table.php`
   — aditiva, guard `Schema::hasColumn`, sin `drop` en `down()` (misma convención que pide #708).
   - `routers.mikrotik_last_status` (`string(10)`, nullable) — equivalente funcional a lo que
     #708 llama `mikrotik_connectivity_status`, como string `'up'`/`'down'` en vez de boolean.
   - `routers.mikrotik_status_changed_at` (`timestamp`, nullable) — **nombre idéntico** al que
     pide #708.
2. **Lógica de escritura** en
   `app/Console/Commands/Active/SyncPingMonitoring.php::trackRouterAvailability()` — ya lee y
   escribe ambas columnas en cada corrida de `mikrotik:sync-ping`.

Confirmado en la BD de dev en esta vuelta (`Schema::getColumnListing('routers')`):
`mikrotik_last_status` y `mikrotik_status_changed_at` presentes, `id/title/.../status/
mikrotik_last_status/mikrotik_status_changed_at/created_at/updated_at` — ninguna columna
`mikrotik_connectivity_status`.

## Por qué no se agrega la columna `mikrotik_connectivity_status`

`grep -rn "mikrotik_connectivity_status" app/ resources/ routes/ config/ database/` → **cero
resultados**, igual que en #702. Ningún consumidor espera esa columna. El semáforo de
conectividad que pide el item (último estado conocido + cuándo cambió) ya está cubierto y en
uso real por `mikrotik_last_status`/`mikrotik_status_changed_at`. Agregar una segunda columna
boolean con semántica duplicada sería campo muerto desde el día uno (MINIMALISMO — sin
consumidores no se agrega schema nuevo).

## Nota sobre el duplicado #715

El item #715 (creado por `wt-5` como sub-item de #708 para sortear un falso positivo de
`circuito:cabida`, con "el mismo alcance completo") describe **exactamente el mismo trabajo**
y por lo tanto llegará a la misma conclusión cuando se ejecute: no hay código que agregar. No se
tocó #715 aquí (un item por dueño) — queda para que su propio ejecutor o el revisor lo cierre
citando este documento.

**Sin cambio de código funcional.** Solo esta nota de verificación.

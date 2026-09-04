# Item #210 — verificación: "El latido sobrevive al proceso que lo lanzó y renueva el lease de un item abandonado"

## Resumen

El item #210 (creado 2026-08-25, durante la auditoría de #191 Fase 3) documentó DOS fallos
medidos del propio Circuito CC:

1. El **pool continuo** abandona el item anterior (`#191`) sin limpiar `worker_sid`/`estado_aprobacion`
   al saltar al siguiente (`#184`) — la propia terminal creyó haberlo liberado y no fue así.
2. El **latido** (`circuito:vivo --watch`, proceso en background lanzado por `vuelta.sh`) no muere
   con su padre: sobrevive reparentado a `init` (PPID=1) y sigue renovando el lease del item
   abandonado, blindándolo indefinidamente frente al reaper.

Pedía DECIDIR: si el latido debe morir con su padre (trap/cleanup en `vuelta.sh`, o que
`circuito:vivo --watch` verifique su PPID), y si el pool continuo debe liberar el item anterior
antes de reclamar el siguiente.

## Verificación (2026-09-04)

Ambos fallos ya están corregidos por trabajo POSTERIOR e independiente, verificado contra el
código real de `main` en este worktree:

### Fallo 2 (la causa raíz medida) — corregido por **#640** (commit ya en `main`)

`RoadmapCircuitoService::renovarLease()` (antes de #640) ejecutaba:

```sql
UPDATE roadmap_items SET claimed_at = now() WHERE worker_sid = ? AND estado_aprobacion = 'en_progreso'
```

sin filtrar por `id` — así que el latido de una vuelta NUEVA (trabajando `#184`) renovaba el
`claimed_at` de **cualquier** item `en_progreso` con el mismo `worker_sid`, incluido el abandonado
`#191`. #640 (sub-item de seguimiento de #188, que citó a #210 explícitamente como causa raíz)
acotó el `UPDATE` por el `current_item` que `liveBeat()` ya calcula (parseado del log de la
vuelta activa):

```php
// RoadmapCircuitoService::renovarLease() (líneas ~1292-1306)
DB::table('roadmap_items')
    ->where('worker_sid', $sid)
    ->where('estado_aprobacion', 'en_progreso')
    ->when($currentItemId !== null, fn ($q) => $q->where('id', $currentItemId))
    ->update(['claimed_at' => now()]);
```

Con esto, en cuanto el pool continuo salta de `#191` a `#184`, el latido deja de tocar el
`claimed_at` de `#191` — se enfría de inmediato, en vez de quedar blindado indefinidamente.
Verificado en el propio cierre de #640 (2026-08-28) con test de regresión + `php -l`, y con los
huérfanos reales medidos (`#100`/`#86`) autorresolviéndose sin reconciliación manual.

### Fallo 1 (el pool abandona el item sin liberar) — cubierto por el reaper existente + #640

No se "arregló" haciendo que el pool libere explícitamente el item anterior antes de reclamar el
siguiente (esa opción se descartó implícitamente a favor de una más robusta): con el fallo 2 ya
corregido, el `claimed_at` del item abandonado deja de renovarse desde el instante mismo del
abandono, así que `circuito:reap-stuck` — que YA exige que **ambas** señales (`claimed_at` y
`updated_at`) estén frías (`#507` sub-paso 3) — lo detecta y re-encola dentro de su ventana de
gracia normal (`--minutes=25` por defecto; antes esta ventana JAMÁS se cumplía porque el latido
mantenía `claimed_at` fresco a perpetuidad). Es una corrección más general que "liberar antes de
avanzar": cubre el abandono por pool continuo Y cualquier otro camino que deje un item
`en_progreso` sin cerrar, sin necesitar lógica nueva en `vuelta.sh`.

Refuerzos adicionales ya en `main`, todos independientes y anteriores/posteriores a #640:

- **`#927`** — trap `soltar_claim_huerfano` (EXIT) + manejo de `RC != 0` en `ejecutar_una`
  (`deploy/circuito/vuelta.sh:140-278`): cualquier fin anormal de la vuelta suelta el reclamo del
  item que estaba trabajando, en vez de dejarlo pegado.
- **`#215`** (`circuito:cortar-vuelta`) — corte MANUAL seguro: mata por PGID (grupo de procesos)
  usando el registro propio del circuito (`RegistroPids`, identidad PID+starttime), así el
  heartbeat `--watch` (mismo grupo que `vuelta.sh`) cae CON la vuelta, y verifica aparte que no
  sobrevive ningún proceso del grupo. Reemplazó el patrón inseguro `pkill -f` que causó el
  incidente original del 2026-08-25 (el propio `pkill -f` se automató a sí mismo).
- **`#704`** (`JarvisVigilarCommand::medirReclamos()`) — familia de detección "RECLAMOS": alerta
  activamente (sin corregir) si `claimed_at` se renueva sin que `updated_at` avance, si hay
  `en_progreso` sin `worker_sid`, o si el `worker_sid` no tiene proceso vivo respaldándolo
  (cruzado contra `RegistroPids`). Es la red de vigilancia para cualquier variante del patrón que
  #210 documentó, más allá del caso puntual ya cerrado.

## Conclusión

El defecto MEDIDO por #210 (heartbeat blindando indefinidamente el lease de un item abandonado)
ya no puede ocurrir: la renovación está acotada al item que la vuelta trabaja de verdad. El peor
caso ahora es una ventana acotada (~25 min por defecto) hasta que el reaper lo re-encola, en vez
de un bloqueo indefinido. El corte manual seguro (`circuito:cortar-vuelta`) y la detección activa
(`#704`) cubren los escenarios operativos restantes. La pregunta "A DECIDIR" del item original
(¿el latido debe morir con su padre vía trap/PPID?) quedó resuelta por una vía más robusta: en vez
de depender de que el proceso muera limpio (frágil en bash para hijos en background), se quitó el
efecto dañino de que siguiera vivo. **Sin cambio de código de negocio en esta vuelta** — la
verificación confirma que el trabajo ya está hecho y mergeado en `main`.

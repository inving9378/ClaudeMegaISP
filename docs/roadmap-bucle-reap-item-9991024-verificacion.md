# Item #9991024 — bucle reap sobre paraguas ya descompuesto (diagnóstico de despacho #9990863 F1)

## Contexto

`#9991024` ("El diagnóstico de despacho (#9990863 F1) no produce log: scheduler corre de
checkout en rama equivocada (D.3)") es sub-item de seguimiento de `#9990863`. El prompt pide 3
acciones: (1) garantizar que el checkout desde donde corre el scheduler esté siempre en `main`
con los commits de Fase 1 aplicados; (2) que `Log::channel('circuito_despacho')` falle ruidoso
si el canal no existe, en vez de silencio; (3) aclarar la premisa de Q3 (cola-DB vs. flock
actual); y, una vez lo anterior esté corriendo, recolectar 6h de log real para responder Q1/Q2.

Irving aprobó las 4 preguntas estructuradas del item, las 4 con la **opción recomendada**
(verificado recalculando el hash `substr(sha1(texto_normalizado), 0, 16)` contra el texto de
cada opción — coincide byte a byte con `opcion_elegida` de cada pregunta en el log):

- **q1** → Opción 1: mover el cron del scheduler a un checkout dedicado fijo en `main` (ej.
  `/var/www/megaisp-scheduler`), dejando `/var/www/megaisp` intacto.
- **q2** → Opción 1: wrapper `logCircuitoDespacho()` que valida el canal y lanza
  `RuntimeException` si falta, con fallback a `Log::error()` en el canal default.
- **q3** → Opción 1: aclarar que Q3 asumía una cola-DB que hoy no existe (el despacho es por
  flock de worktrees, cap=6) y diferir cualquier diseño de cola-DB real a un item aparte.
- **q4** → Opción 1: recolectar la ventana de 6h **después** de aplicar (1) y (2).

## Verificación

`circuito:cabida 9991024 --sid=wt-1` devolvió `CABE [ya_descompuesto]`. Confirmado contra la BD:
el item **ya tenía 4 sub-items** (`origen_item_id=9991024`), uno por cada pregunta, creados por
una vuelta anterior (mismo `sid=wt-1`, log `item_creado` a las 02:44:58–02:45:31) que respetó las
opciones ya aprobadas por Irving:

| Sub-item | Pregunta | Estado al llegar a esta vuelta |
|---|---|---|
| `#9991025` | Q2 — helper `logCircuitoDespacho()` con fallo ruidoso | `pendiente_revision`, sin reclamar |
| `#9991026` | Q1 — checkout dedicado del scheduler en main + actualizar cron | `pendiente_revision`, sin reclamar |
| `#9991027` | Q3 — documentar que el despacho es por flock, no cola-DB (sin código) | `pendiente_revision`, sin reclamar |
| `#9991028` | Q4 — recolectar 6h de log y responder Q1/Q2 con números reales | `pendiente_revision`, sin reclamar |

La descomposición es correcta y completa (cubre las 4 preguntas del prompt una a una, en el
orden lógico correcto — Q4 depende implícitamente de que Q1/Q2 estén aplicadas). El problema no
era de contenido: la vuelta que descompuso el trabajo **murió antes de intentar cerrar el
padre** (log: `soltar-claim` / `claim_liberado_al_morir_la_vuelta`, "muerte del proceso: kill,
OOM o freno a media vuelta" — 1 segundo después del último sub-item creado). El item quedó
`aprobado_revisor` sin dueño, el pool lo repartió de nuevo (mismo `wt-1`, esta vuelta) sin que
hubiera trabajo propio pendiente. Mismo patrón documentado ya muchas veces en `CLAUDE.md`
(familia `#738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/
#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/#9990892/
#9990896/#9990886/#9990893/#9990878/#9990870`).

## Corrección aplicada

Se ejecutó el intento de cierre faltante:

```php
$i = RoadmapItem::find(9991024);
$i->estado_aprobacion = 'completado';
$i->save();
```

El guard de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") detectó los 4 sub-items todavía
abiertos y reenrutó el guardado:

- `estado_aprobacion` → `aprobado_irving`
- `excluir_pool_automatico` → `true`
- `worker_sid`/`claimed_at` → `null` (liberados)
- Log: evento `paraguas_abierto`, "le quedan 4 sub-item(s) abierto(s): no se completa. Queda
  como paraguas y cierra solo cuando el último de ellos cierre."

Esto saca a `#9991024` del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo, en el instante en que los 4 hijos lleguen a
`completado`.

## Conclusión

**Sin cambio de código de negocio.** El trabajo técnico real (helper de log ruidoso, checkout
dedicado del scheduler + cron, documentación de la premisa Q3, y la ventana de observación de
6h) sigue en `#9991025`/`#9991026`/`#9991027`/`#9991028`, pendientes de que una terminal los
reclame. Este item solo completó el bookkeeping de cierre que faltaba sobre el paraguas ya
descompuesto correctamente.

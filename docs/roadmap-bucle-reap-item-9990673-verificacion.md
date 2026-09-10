# Item #9990673 — F2 ReleasePreflight (modo reporte) — bucle reap sobre paraguas ya descompuesto

## Síntoma

`#9990673` ("F2 — ReleasePreflight en modo reporte: validar antes de emitir, sin frenar aún") se
reclamó de nuevo pese a que su propio log ya mostraba una decisión completa de descomposición.

## Causa

Una vuelta previa (`wt-1`, 2026-09-09 18:30) ya hizo lo correcto: corrió `circuito:cabida` (NO
CABE) y descompuso el trabajo en 4 sub-items encadenados por `depende_de`:

- **F2a** (`#9990680`) — base reusable `ReconcileReleasesCommand --json` + `ReleaseChangelogService::coverage()`
- **F2b** (`#9990681`) — `ReleasePreflightService` con los 9 chequeos + comando `release:preflight`
- **F2c** (`#9990682`) — engancharlo en `DeploymentService::run()` como paso no bloqueante
- **F2d** (`#9990683`) — calibración contra las últimas 5 versiones publicadas, cierra el padre

Pero esa vuelta murió antes de intentar **cerrar** al padre (log: `soltar-claim` /
`claim_liberado_al_morir_la_vuelta`, "La vuelta de wt-1 terminó sin cerrar el item"). El guard de
paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") solo aparca un item descompuesto
(`aprobado_irving` + `excluir_pool_automatico=true`) cuando algo **intenta activamente**
`estado_aprobacion = 'completado'` y detecta hijos abiertos. Sin ese intento, `#9990673` quedó
`aprobado_revisor` disponible de nuevo, el pool lo repartió sin que hubiera trabajo propio que
hacer — mismo patrón documentado repetidas veces en `CLAUDE.md` (#738/#745/#830/#816/#818/#848/
#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962/#9990554/#9990549/#9990624/
#9990650).

## Verificación

Los 4 hijos (`origen_item_id=9990673`) siguen intactos, todos `pendiente_revision`, sin reclamar
(`worker_sid` vacío) y sin `merge_commit` — la descomposición original seguía siendo correcta,
nadie más la tocó desde entonces.

## Corrección

Esta vuelta ejecutó el intento de cierre faltante (`estado_aprobacion='completado'`). El guard lo
reenrutó a `aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el
log, "le quedan 4 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre
en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando F2a, F2b, F2c y F2d cierren los
cuatro.

**Sin cambio de código de negocio** — el trabajo técnico real de ReleasePreflight sigue en
#9990680/#9990681/#9990682/#9990683, pendientes de que una terminal los reclame (respetando el
orden de `depende_de`).

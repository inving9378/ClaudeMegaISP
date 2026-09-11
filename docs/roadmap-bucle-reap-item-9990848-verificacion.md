# Item #9990848 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

`#9990848` (Fase 3b de `#9990836`: el listado de Clientes muestre `serie_equipo` en formato
corto + indicador de `serie_equipo_origen` en la columna "Modem Serie") es sub-item de
`#9990836`. Fue escalado por el DES-TRABE de Opus (`ANTI-LOOP: el ejecutor ya corrió este item 2×
y NO lo ejecutó`) y reaprobado por Irving con sus 3 preguntas estructuradas resueltas (q1: badge
por color de origen; q2: últimos 8 caracteres del serial con tooltip; q3: filtro por origen fuera
de alcance).

## Qué hizo la vuelta anterior (correcto)

Una vuelta previa (`wt-1`, 2026-09-11 17:30) corrió `circuito:cabida` → NO CABE
(`ya_timeouteo_antes`) e investigó a fondo el mecanismo de render real (SELECT dinámico en
`ClientDatatableHelper`, bypass Blade tipo `status_smart`, catálogo `column_datatable_modules`
independiente) antes de descomponer. Descompuso correctamente en:

- **#9990851** — Fase 3b-backend: SELECT + payload de `serie_equipo`/`serie_equipo_origen` en el
  listado de Clientes (columna `modem_sn`). Estado: `pendiente_revision`, sin reclamar.
- **#9990852** — Fase 3b-frontend: renderizar `serie_equipo` (últimos 8 caracteres) + badge de
  origen en la columna "Modem Serie", depende de #9990851. Estado: `pendiente_revision`, sin
  reclamar.

Sin código propio en esa vuelta (la investigación fue de solo lectura).

## Qué faltó (el bug de la familia)

Esa vuelta **nunca intentó cerrar** al padre `#9990848` tras descomponerlo. El log muestra que el
proceso murió justo después de escribir la decisión (`soltar-claim` / `claim_liberado_al_morir_la_
vuelta`, mismo timestamp `17:30:11`), sin ejecutar el `estado_aprobacion = 'completado'` que
dispara el guard de paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS"). Sin ese intento, el item
volvió a `aprobado_revisor`/`aprobado_irving` con `excluir_pool_automatico=false` y el pool lo
repartió de nuevo (a esta misma terminal `wt-1`) sin trabajo propio pendiente — mismo síntoma que
#738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/
#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836, documentado extensamente
en `CLAUDE.md`.

## Verificación en esta vuelta

- `#9990851` y `#9990852` siguen intactos, `pendiente_revision`, sin reclamar (`worker_sid`
  vacío, `merge_commit` vacío) — la descomposición original seguía siendo correcta, nadie más la
  tocó.
- No había cambios de código pendientes en el worktree (`git status` limpio, sin rama propia
  creada — la vuelta anterior no llegó a crear rama, la investigación fue puramente de lectura).

## Corrección aplicada

Se ejecutó el intento de cierre faltante: `estado_aprobacion = 'completado'`. El guard de
paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo reenrutó automáticamente a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log: "le
quedan 2 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando `#9990851` y `#9990852` cierren los
dos.

## Sin cambio de código de negocio

El trabajo técnico real (SELECT+payload de `serie_equipo`/`serie_equipo_origen` en el backend, y
el render con badge+últimos 8 caracteres en el frontend) sigue en `#9990851`/`#9990852`,
pendientes de que una terminal los reclame.

# Item #9990458 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

Mismo patrón ya documentado repetidamente en `CLAUDE.md` (#738/#745/#830/#816/#818/#848/#852/#905/
#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962/#9990494): un item paraguas que ya fue
descompuesto correctamente en sub-items, pero cuya sesión nunca intentó el cierre-contra-el-guard
para parquearlo (o completarlo) — así que quedó vivo para que el reaper lo re-encolara y el pool lo
repartiera de nuevo sin trabajo propio pendiente.

## Item #9990458

"MR-22 Fase 2 — Panel de capas encendibles + render dependiente de zoom" — sub-item de seguimiento
de #958. Una vuelta anterior (`wt-5`, 2026-09-07 09:29) ya construyó e integró a `main` el panel de
capas (checkboxes OLT/Troncales/Mufas/NAPs/Drops/Clientes/Postes/Cobertura) + el listener de zoom
(drops≥16, clientes≥17) + el estado inicial según la decisión q3 de Irving — ese trabajo quedó
registrado en el propio `merge_commit` de este item (`da0185b4d5...`).

Al integrarse, el guard de paraguas detectó que el item ya se había descompuesto en 3 sub-items y
lo reenrutó a `aprobado_irving` (parqueado, no completado):

- **#9990492** — Fase 2a: panel de capas para OLT/Troncales/Mufas/NAPs/Postes (elementos ya
  renderizados). **`completado`**, mergeado (`fa77ab9b...`), archivado.
- **#9990493** — Fase 2b: capa de Clientes real + umbral de zoom 17 (reactivar código comentado o
  reconstruir). **`completado`**, mergeado (`d84f0ab4...`).
- **#9990494** — Fase 2c: capas de Drops y Cobertura (no existen hoy como capas geográficas reales;
  requería decisión de diseño). Escalado a Irving, resuelto, y a su vez descompuesto en 4
  sub-sub-items propios (#9990539/#9990540/#9990542/#9990543 — backend Drops, backend Cobertura,
  render read-only, edición visual). Una vuelta posterior (documentada en
  `docs/roadmap-bucle-reap-item-9990494-verificacion.md`) ya ejecutó su propio cierre-intento:
  quedó `aprobado_irving` + `excluir_pool_automatico=true` (parqueado como paraguas de sus 4 hijos),
  pero el merge que lo llevó ahí lo clasificó como "backend, sin efecto visible" y lo
  **auto-archivó** (`archivado_at` seteado por `MergeRunner::markMerged()`).

## Qué pasó

1. La vuelta que descompuso #9990458 en sus 3 hijos (2026-09-07 09:29-09:30) **nunca intentó
   cerrar** al padre después. El guard de paraguas sí se disparó en su momento (evento
   `paraguas_abierto` en el log, "le quedan 3 sub-item(s) abierto(s)"), parqueándolo
   correctamente — pero de ahí en adelante nadie volvió a intentar el cierre aunque los 3 hijos
   fueran cerrando uno a uno.
2. `jarvis-ya-decidido` procesó un brief pendiente del propio item dos veces (08:46 y 16:55) y lo
   devolvió a `aprobado_revisor` cada vez ("la decisión ya estaba tomada y el item seguía retenido
   sin que faltara nadie"), sin verificar si sus sub-items ya habían cerrado.
3. El scheduler lo repartió de nuevo (esta vez a `wt-4`) sin que hubiera trabajo propio de código
   pendiente — el trabajo de UI de #9990458 ya estaba mergeado desde el 07 de septiembre por la
   mañana.

## Verificación hecha en esta vuelta

- Los 3 hijos directos (`origen_item_id=9990458`) existen y están resueltos por su propia
  bandera de cierre: #9990492 y #9990493 en `completado`; #9990494 en `aprobado_irving` pero con
  `archivado_at` seteado.
- `RoadmapItem::subItemsAbiertos()` filtra por `whereNull('archivado_at')` ADEMÁS del estado — por
  eso #9990494, aunque no está en `completado`, no cuenta como "abierto" para efectos del padre
  (ya está fuera del radar de la Torre, archivado tras su propio merge backend). Verificado en
  tinker: `$item->tieneSubItemsAbiertos()` para #9990458 devuelve **`false`** ahora mismo.
- Esto es consistente con el precedente inmediato (#9990494 sobre sus propios hijos): un
  sub-paraguas archivado no bloquea a su padre aunque técnicamente le queden nietos por cerrar —
  ese trabajo remanente (drops/cobertura reales) sigue su propio ciclo de vida bajo #9990494,
  desacoplado de si #9990458 puede darse por completado en su propio alcance (panel + render por
  zoom, que sí está 100% construido y mergeado).
- La rama `circuito/item-9990458-mr-22-fase-2-panel-de-capas-encendible` ya tenía su
  `merge_commit` propio desde el primer merge (`da0185b4d5...`); esta vuelta solo agrega este
  documento sobre la misma rama para completar el cierre-intento.

## Corrección aplicada

Se ejecuta el intento de cierre faltante (`estado_aprobacion = 'completado'`). Dado que
`tieneSubItemsAbiertos()` ya es `false`, el guard de paraguas NO lo reenruta esta vez: el item
cierra de verdad.

## Sin cambio de código de negocio

El trabajo de UI de MR-22 Fase 2 (panel de capas + render dependiente de zoom) ya estaba
construido y mergeado desde antes de este item. El trabajo remanente de fondo — capas reales de
Drops y Cobertura (tablas, CRUD, render, edición visual) — sigue su propio ciclo bajo #9990494 →
#9990539/#9990540/#9990542/#9990543, independiente del cierre de #9990458.

# Item #9990494 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

Mismo patrón ya documentado repetidamente en `CLAUDE.md` (#738/#745/#830/#816/#818/#848/#852/#905/
#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962): un item paraguas que ya fue
descompuesto correctamente en sub-items, pero cuya sesión nunca intentó el cierre-contra-el-guard
para parquearlo — así que quedó vivo para que el reaper lo re-encolara y el pool lo repartiera de
nuevo sin trabajo propio pendiente.

## Item #9990494

"MR-22 Fase 2c — Capas de Drops y Cobertura: no existen hoy como capas geográficas" — sub-item de
seguimiento de #9990458, escalado a Irving con 4 preguntas estructuradas (modelado de Drops,
modelado de Cobertura, quién captura y cuándo, alcance backend-only vs. UI completa). Irving
aprobó las 4 opciones recomendadas (2026-09-07 15:17).

## Qué pasó

1. Un primer intento (timeout `max_turns`, 2026-09-07 10:04) no dejó commits en la rama —
   escaló sin avance.
2. Una vuelta posterior (`wt-2`, 2026-09-07 15:25) hizo el trabajo correcto: siguiendo las
   decisiones ya tomadas por Irving (q1-q4), descompuso el ítem en 4 sub-items:
   - **#9990539** — Backend Drops: tabla `network_drops` (punto lat/lng) + modelo + CRUD.
   - **#9990540** — Backend Cobertura declarada: tabla `coverage_areas` (polígono manual) + CRUD.
   - **#9990542** — Render read-only de ambas capas en el mapa Leaflet (depende de las 2 anteriores).
   - **#9990543** — Fase 2d (edición visual + captura automática al activar cliente), pospuesta
     a propósito por la propia decisión de Irving en q4.
3. Esa vuelta **nunca intentó cerrar** al padre (#9990494) tras crear los sub-items. Sin ese
   intento, el guard de paraguas del modelo (`RoadmapItem.php` bloque "(2b) PARAGUAS") nunca se
   disparó, y el item quedó "vivo" para el reaper.
4. `reaper-rapido` detectó el slot `wt-2` libre (nadie corriendo ahí) y re-encoló el item huérfano
   a `aprobado_irving` (`reap_count=1`, 2026-09-07 16:48:03).
5. El pool lo repartió de nuevo — esta vez a `wt-1` — sin que hubiera trabajo propio pendiente.

## Verificación hecha en esta vuelta

- Los 4 sub-items (`origen_item_id=9990494`) siguen intactos: #9990539 y #9990540 en
  `requiere_irving`, #9990542 en `aprobado_revisor`, #9990543 en `requiere_irving` — ninguno
  reclamado (`worker_sid` vacío). La descomposición original seguía siendo correcta; nadie más la
  tocó.
- La rama `circuito/item-9990494-mr-22-fase-2c-capas-de-drops-y-cobertu` existe pero es un
  **ancestro de `main`** (`git merge-base --is-ancestor` = true) — 0 commits propios. Confirma que
  el primer intento (timeout sin avance) no dejó código que integrar.

## Corrección aplicada

Se ejecutó el intento de cierre faltante:

```php
$i = RoadmapItem::find(9990494);
$i->estado_aprobacion = 'completado';
$i->save();
```

El guard de paraguas lo reenrutó automáticamente a `aprobado_irving` +
`excluir_pool_automatico=true`, liberando `worker_sid`/`claimed_at`. Esto lo saca del pool/reaper
hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando los 4
sub-items (#9990539, #9990540, #9990542, #9990543) cierren.

## Sin cambio de código de negocio

El trabajo técnico real de MR-22 Fase 2c/2d (tablas `network_drops`/`coverage_areas`, CRUDs,
render read-only en el mapa, edición visual) sigue en los 4 sub-items, pendientes de que una
terminal los reclame (#9990542 ya está `aprobado_revisor`, listo para tomarse primero por ser
dependiente de los otros dos).

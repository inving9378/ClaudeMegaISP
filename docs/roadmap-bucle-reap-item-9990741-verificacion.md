# Item #9990741 — bucle reap sobre paraguas ya descompuesto (verificación)

## Síntoma

El item #9990741 ("MR flujo animado Fase 3 — guardas de rendimiento + botón congelar animación",
sub-item de seguimiento de #9990733) fue escalado por anti-loop (DES-TRABE de Opus: "el ejecutor
ya corrió este item 2× y NO lo ejecutó"), reaprobado por Irving con sus 3 preguntas resueltas
(q1 auto-congelado FPS<30/2s o >200 nodos; q2 botón toggle en toolbar con localStorage; q3
persistencia por localStorage, sin BD), y devuelto a la cola por `jarvis-ya-decidido`.

Una vuelta previa (`wt-4`, 2026-09-10 20:20) ya hizo lo correcto:
1. Corrió `circuito:cabida 9990741` → NO CABE (el item ya había timeouteado antes por max_turns
   sin commits).
2. Descompuso el trabajo en 3 sub-items por sub-fase técnica, sin tocar código del padre:
   - **#9990759** — Fase 3a: viewport culling (pausar animación de enlaces fuera de pantalla).
   - **#9990760** — Fase 3b: estado "congelado" unificado (guard de zoom<15 + pausa por pestaña
     oculta + botón manual con localStorage; decisiones q2/q3 ya aprobadas por Irving).
   - **#9990761** — Fase 3c: auto-congelado por rendimiento (FPS<30/2s o >200 nodos; decisión q1
     ya aprobada por Irving; depende de 3b).

Pero esa vuelta **nunca intentó cerrar** el item padre (#9990741) tras crear los sub-items — el
log solo registra `soltar-claim` ("La vuelta de wt-4 terminó sin cerrar el item... se libera el
reclamo y vuelve a la cola como aprobado_revisor"). Sin ese intento de cierre, el guard de
paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") nunca se disparó, así que el item
volvió al pool y fue reclamado de nuevo (esta vez, otra vez, por `wt-4`) sin que hubiera trabajo
propio pendiente — mismo síntoma que la larga familia de items documentada en `CLAUDE.md`
(#738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/
#962/#9990554/#9990549/#9990624/#9990650/#9990733/#9990740, entre otros).

## Verificación

Se confirmó contra la BD real de dev que los 3 sub-items siguen intactos, sin reclamar:

| Item | Título | Estado | worker_sid |
|------|--------|--------|------------|
| #9990759 | Fase 3a — viewport culling | `pendiente_revision` | (vacío) |
| #9990760 | Fase 3b — estado congelado unificado | `pendiente_revision` | (vacío) |
| #9990761 | Fase 3c — auto-congelado por rendimiento | `pendiente_revision` | (vacío) |

La descomposición original seguía siendo correcta — nadie más la tocó desde que se creó.

## Corrección

Esta vuelta ejecutó el intento de cierre faltante:

```php
$i = RoadmapItem::find(9990741);
$i->estado_aprobacion = 'completado';
$i->save();
```

El guard de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo reenrutó automáticamente a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log: "le
quedan 3 sub-item(s) abierto(s)"), sacándolo del pool/reaper. Queda así hasta que el hook de
cierre en cascada (`RoadmapItem.php`, ~459-491) lo complete solo cuando #9990759, #9990760 y
#9990761 cierren los tres.

## Sin cambio de código de negocio

El trabajo técnico real (viewport culling, estado congelado unificado con botón manual,
auto-congelado por rendimiento) sigue en #9990759/#9990760/#9990761, pendientes de que una
terminal los reclame. Esta vuelta solo repara el bookkeeping del paraguas — no toca
`LeafletMapRed.vue`, `mapared-enlace-fibra.css` ni ningún archivo de aplicación.

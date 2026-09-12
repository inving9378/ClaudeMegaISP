# Item #9990857 — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

## Contexto

`#9990857` ("CIRC-02a Diagnóstico: cuántas respuestas de Irving se han perdido — SOLO LECTURA")
es un item de tipo `manual`, `origen_item_id=9990856`, nivel de riesgo A.

Mismo patrón ya documentado repetidas veces en `CLAUDE.md` (familia #738/#745/#830/#816/#818/
#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962/#9990554/#9990549/
#9990624/#9990650/#9990807/#9990826/#9990836, entre otros): una vuelta previa (`wt-3`,
2026-09-11 18:18) ya hizo lo correcto —

- Corrió `circuito:cabida` → NO CABE.
- Descompuso el trabajo en dos sub-items reales:
  - **#9990872** — Fase 1: extraer y clasificar items `requiere_irving` con comentario humano
    posterior (grupos a/b + métricas de b).
  - **#9990873** — Fase 2: detectar respuestas de Irving sobreescritas en `comentarios_claude` +
    redactar el reporte final en `docs/`.
- Dejó el registro de la decisión (`circuito:reportar --tipo=decision`) en `comentarios_claude`.

Pero esa vuelta **nunca intentó cerrar** al padre — el log solo registra
`soltar-claim`/`claim_liberado_al_morir_la_vuelta` un minuto después (18:19:17), señal de que el
proceso murió (kill/OOM/freno a media vuelta) antes de ejecutar el `estado_aprobacion=completado`
que dispara el guard de paraguas. El item quedó como `aprobado_revisor` sin worker, el pool lo
volvió a repartir (a `wt-1`, esta vuelta) como `en_progreso` sin que hubiera trabajo propio
pendiente de picar.

## Verificación hecha esta vuelta

- Leído el item completo por tinker: confirma la descomposición y el comentario de decisión de
  `wt-3` intactos, sin código propio del padre por hacer.
- Verificados los dos hijos (`origen_item_id=9990857`):
  - `#9990872` → `pendiente_revision`, sin `worker_sid`, intacto.
  - `#9990873` → `pendiente_revision`, sin `worker_sid`, intacto.
- `git status`/`git branch --show-current` en el worktree: limpio, sin rama creada — no había
  nada que integrar.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'`). El guard de
paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detectó y reenrutó
automáticamente:

```
{"ts":"...","por":"paraguas","evento":"paraguas_abierto",
 "motivo":"Este item se descompuso y le quedan 2 sub-item(s) abierto(s): no se completa.
 Queda como paraguas y cierra solo cuando el último de ellos cierre.",
 "subitems_abiertos":2}
```

Resultado: `estado_aprobacion=aprobado_irving`, `excluir_pool_automatico=true`,
`worker_sid=NULL`. Queda fuera del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando **#9990872** y **#9990873** cierren los dos.

## Sin cambio de código de negocio

El trabajo técnico real (el diagnóstico de solo lectura sobre respuestas de Irving perdidas) sigue
en #9990872 (Fase 1, sin dependencias, ejecutable ya) y #9990873 (Fase 2, depende de la Fase 1),
pendientes de que una terminal los reclame.

# Item #9990878 — Identidad unificada Fase 3 (doble escritura colaborador_id) — bucle reap sobre paraguas ya descompuesto

## Contexto

Mismo patrón documentado repetidamente en `CLAUDE.md` (familia #738/#745/#830/#816/#818/#848/
#852/#905/#878-torre/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962/#9990554/#9990549/
#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/#9990892/#9990896/#9990886/#9990893):
un item paraguas que ya fue descompuesto correctamente en sub-items reales, pero cuya vuelta
murió (timeout / límite de cuenta / kill) **antes de intentar el cierre** sobre el propio
padre. Sin ese intento, el guard de paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS",
~398-429) nunca se dispara, el item se queda `en_progreso` colgado, el reaper lo re-encola
como huérfano, y el pool lo vuelve a repartir sin que quede trabajo propio por hacer.

## Historia del item

`#9990878` (Fase 3 de identidad unificada: doble escritura de `colaborador_id` junto a
`seller_id`) nació como sub-item de seguimiento de `#9990778`, fue escalado por el revisor
(negocio/identidad) y por el DES-TRABE (Opus) con un brief de 4 preguntas; Irving aprobó las
4 con la opción recomendada (respuestas fijadas en `preguntas[].opcion_elegida`).

Tras varios timeouts por `max_turns` y varios eventos de `limite_cuenta` (sin contar como
timeout), una vuelta de `wt-5` (2026-09-11 20:16) hizo el trabajo correcto:

1. Corrió `circuito:cabida` → NO CABE (ya había timeouteado 2 veces sin commits).
2. Investigó a fondo: confirmó por hash sha1 que las 4 preguntas de Irving eligieron la
   opción recomendada, y encontró que `client_main_information` se escribe por **al menos 2
   vías distintas** (Eloquent en alta normal vs `DB::table` raw en import) — invalidando la
   premisa de `q1` de que un observer "no requiere tocar callsites".
3. Descompuso el trabajo real en 4 sub-items:
   - **#9990962** — Fase 3a: mapear TODOS los puntos de escritura reales de `seller_id`
     (Eloquent vs raw).
   - **#9990963** — Fase 3b: implementar la doble escritura (observer + parches raw + feature
     flag).
   - **#9990964** — Fase 3c: comando `identidad:verificar-consistencia` + KPI card.
   - **#9990965** — Fase 3d: activar el flag en dev + validación end-to-end + cerrar
     `#9990878`.

Pero el comentario de esa vuelta se corta a media oración (texto truncado en
`comentarios_claude`, "...Descompuse en 4 sub-items: #9990962... #9990963") — el proceso murió
antes de terminar de escribir el reporte y, sobre todo, antes de **intentar cerrar** al padre.
El log solo registra:

```
[reaper] worker murió/timeout con el item en en_progreso (hace 25 minutos) →
re-encolado (intento 1/3), vuelve a aprobado_irving.
```

## Verificación en esta vuelta (wt-3)

Consulté los 4 hijos (`origen_item_id=9990878`):

| id | título | estado_aprobacion | status | worker_sid | merge_commit |
|----|--------|--------------------|--------|------------|--------------|
| #9990962 | Fase 3a — mapear callsites | `completado` | `done` | — | `e1e5d647...` |
| #9990963 | Fase 3b — doble escritura | `en_progreso` | `pending` | `wt-5` | — |
| #9990964 | Fase 3c — comando + KPI | `en_progreso` | `pending` | `wt-5` | — |
| #9990965 | Fase 3d — activar flag + verificar + cerrar padre | `aprobado_revisor` | `pending` | — | — |

La descomposición original seguía siendo correcta: #9990962 ya cerró y mergeó; #9990963 y
#9990964 están **activamente reclamados por `wt-5`** (aislamiento #334, no se tocan); #9990965
sigue sin reclamar, correctamente bloqueado hasta que 3b/3c avancen.

## Corrección aplicada

Ejecuté el intento de cierre faltante sobre `#9990878`
(`estado_aprobacion = 'completado'`). El guard de paraguas lo detectó (tiene 3 sub-items
abiertos: #9990963, #9990964, #9990965) y lo reenrutó automáticamente a:

```json
{"estado_aprobacion":"aprobado_irving","excluir_pool_automatico":true,"worker_sid":null,"claimed_at":null}
```

Esto lo saca del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`, no tocado) lo complete solo cuando #9990963, #9990964 y #9990965
cierren los tres.

## Sin cambio de código de negocio

El trabajo técnico real de la Fase 3 (mapeo de callsites ya cerrado; doble escritura,
comando de consistencia y activación/verificación final) sigue en
#9990963/#9990964 (reclamados por `wt-5`) y #9990965 (pendiente de que #9990963/#9990964
avancen), sin que esta vuelta tocara ninguno de ellos.

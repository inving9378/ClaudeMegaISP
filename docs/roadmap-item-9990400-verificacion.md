# Item #9990400 — Rama e integración del doc de verificación de #9990384 (RESUELTO — ya ejecutado bajo #9990397)

**Fecha de verificación:** 2026-09-06 (wt-3)

## Contexto

`#9990400` es un sub-item de seguimiento del paraguas `#9990397` ("Escribir doc de verificación de
#9990384 + commit + rama + integrar"). Su spec asumía un camino secuencial: esperar a que el sub-item
hermano `#9990399` ("Escribir y commitear `docs/roadmap-flota-parada-item-9990384-verificacion.md`")
terminara de commitear el doc, y entonces — sobre ese commit — crear la rama de `#9990397`, integrar,
poblar `reporte_coloquial`/`enlace_revision` y marcar `#9990397` como `completado`.

## Lo que realmente pasó (verificado contra BD y git reales)

Ese camino nunca hizo falta: una vuelta anterior (log de `#9990397`, entrada `[2026-09-06 11:40 ·
wt-3 · cierre]`) escribió el doc, lo commiteó, creó la rama `circuito/item-9990397-escribir-doc-de-
verificacion-de-9990384` y la integró **directamente bajo el propio `#9990397`**, sin pasar por
`#9990399`. Confirmado en el repo:

- `git log --oneline -- docs/roadmap-flota-parada-item-9990384-verificacion.md` → commit `625f9320`
  ("Docs: verificación item #9990384 (flota parada) — 2/3 bloqueadores en bandeja de Irving, 1
  resuelto solo"), ya en `main`.
- Commit de integración `8b4f0c3f` ("Integra circuito #9990397 ... a main") también en `main`.
- `#9990397.merge_commit = 8b4f0c3feaa283cf9ad1198037894dd7842d433a`, con `reporte_coloquial` y
  `enlace_revision` ya poblados (deep-link a `/releases` → Hoja de ruta → item #9990384).

Es decir: los pasos (1)-(5) que `#9990400` describía como su tarea **ya están hechos**, todos, sobre
el item padre `#9990397`. No queda nada que ejecutar por ese lado.

## Por qué `#9990397` sigue sin `completado`

El guard de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") no completa un item descompuesto
mientras le queden sub-items abiertos. Al momento del merge de `#9990397` (11:41:03) seguían abiertos
sus dos hijos de seguimiento: `#9990399` (aún `en_progreso`, reclamado por `wt-2`) y este mismo
`#9990400`. El log de `#9990397` registra dos veces el evento `paraguas_abierto` por esa razón. Esto
es exactamente el patrón "carrera de timing" ya documentado repetidas veces en este repo (ver
`CLAUDE.md` — familia de items #733/#741/#753/#9990003/#9990353): el trabajo real se completó antes
de que los sub-items de seguimiento terminaran de cerrar el ciclo administrativo.

`#9990397` se completará solo, vía el hook de cierre en cascada (`RoadmapItem.php:459-491`), en
cuanto el ÚLTIMO de sus dos hijos abiertos cierre. `#9990399` sigue en curso bajo `wt-2` — no se toca
aquí (un item = un dueño).

## Nota sobre `circuito:cabida`

`circuito:cabida 9990400` devolvió `NO CABE [historico_excede_umbral]` (histórico ~492s vs. umbral
480s para el módulo Roadmap/Circuito CC nivel B). Se decidió proceder de todos modos en esta misma
vuelta, sin descomponer: el trabajo restante, una vez verificado que los pasos operativos ya estaban
hechos, es una acción de bookkeeping atómica (este doc + cerrar el item), no una implementación nueva
que arriesgue quedarse a medias. Descomponerlo habría delegado a una vuelta futura exactamente la
misma verificación ya realizada aquí. Decisión registrada vía `circuito:reportar --tipo=decision`.

## Conclusión

`#9990400` no requiere ninguna acción de código adicional. Se cierra documentando que su objetivo
(rama + integración + reporte + cierre del doc de verificación de `#9990384`) ya se cumplió bajo el
item padre `#9990397`, y que la única pieza pendiente (cierre final de `#9990397`) depende de que
`#9990399` termine su propio ciclo — fuera del alcance de este item.

# Item #9990395 — bucle reap sobre paraguas ya descompuesto (y sub-paraguas anidado) — se completa el cierre-intento faltante

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910
(ver CLAUDE.md), aquí con una variante: el paraguas está anidado dos niveles y uno de los dos
hijos directos ya terminó su trabajo real pero también quedó atrapado en el mismo patrón.

## Qué pedía el item

#9990395 (sub-item de seguimiento de #9990384, "flota parada") pedía: escribir
`docs/roadmap-flota-parada-item-9990384-verificacion.md`, commitearlo, integrarlo, y cerrar
#9990384 con el estado real de sus 3 bloqueadores.

## Qué pasó

Una vuelta previa de esta misma terminal (`wt-1`, 2026-09-06 11:34) ya hizo lo correcto: en vez de
repetir el trabajo, lo descompuso en dos sub-items — **#9990397** ("escribir doc + commit + rama +
integrar") y **#9990398** ("cerrar #9990384", con `depende_de=[9990397]`) — pero **nunca intentó
cerrar** #9990395 después de crearlos. El item quedó `en_progreso` colgado con el reclamo de esa
vuelta; el reaper lo devolvió a `aprobado_revisor` (`reap_count=1`), y el pool lo repartió de
nuevo (a esta misma terminal) sin que hubiera trabajo propio que hacer — el trabajo real ya vivía
en los hijos.

**Nivel adicional de anidamiento encontrado esta vuelta:** #9990397 sí completó su trabajo real
— escribió el doc, lo commiteó, y lo integró a `main` (`merge_commit=8b4f0c3feaa283cf9ad1198037894dd7842d433a`,
confirmado con `git diff` contra el archivo en disco: contenido idéntico). Pero **otra sesión**
(`wt-2`, 11:38) además creó dos sub-items redundantes de #9990397 — **#9990399** ("escribir y
commitear el doc", reclamado por `wt-2`) y **#9990400** ("rama e integración del doc", reclamado
por `wt-3`, depende de #9990399) — describiendo el MISMO trabajo que #9990397 ya había hecho por
sí mismo unos minutos antes. Cuando #9990397 intentó cerrarse, el guard de paraguas lo encontró
con esos 2 hijos abiertos y lo parqueó igual (`aprobado_irving` + `excluir_pool_automatico=true`),
aunque su propio trabajo ya estaba en `main`.

## Verificado esta vuelta

- `docs/roadmap-flota-parada-item-9990384-verificacion.md` existe en `main` desde el commit
  `8b4f0c3f`, contenido correcto (los 3 bloqueadores, 2 en bandeja de Irving + 1 resuelto solo).
- #9990397: `aprobado_irving`, `merge_commit` presente, `excluir_pool_automatico=true` — parqueado
  correctamente como paraguas de #9990399/#9990400.
- #9990398 (cierra #9990384): `aprobado_revisor`, sin reclamar, con `depende_de=[9990397]` fijado
  a mano por una vuelta anterior que verificó la precondición antes de tiempo — sigue siendo
  válido ahora que #9990397 ya tiene `merge_commit`.
- #9990399/#9990400: `en_progreso`, reclamados por `wt-2`/`wt-3` respectivamente — describen
  trabajo que #9990397 ya hizo. Quedan fuera de alcance de este item (no son míos, un item = un
  dueño); su propia vuelta o el reaper los resolverá cuando corresponda.

## Corrección

Esta vuelta ejecuta el intento de cierre faltante de #9990395: como todavía tiene 2 sub-items
abiertos (#9990397, #9990398), el guard de paraguas lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true`, sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #9990397 y #9990398 cierren a su vez.

**Sin cambio de código de negocio** — el trabajo real (doc de verificación + cierre de #9990384)
ya está hecho en #9990397 (mergeado) y listo para tomarse en #9990398 (aprobado, sin reclamar,
precondición ya satisfecha).

# Item #9990364 — consolidar los 3 escenarios y cerrar #909 (RESUELTO — la consolidación ya no hacía falta)

## Contexto

`#9990364` nació (junto a `#9990362` y `#9990363`) de un intento de descomposición de `#990` que
una vuelta previa de esta misma terminal abandonó a medio camino. Cuando otra vuelta retomó `#990`,
hizo la verificación end-to-end **directamente** (los 3 escenarios: sano / falla+`--auto-revert` /
falla sin `--auto-revert`, ver `docs/circuito-verificar-vuelta-item-990-verificacion.md`) en vez de
usar los 3 sub-items ya creados — esos quedaron redundantes frente al trabajo real. `#9990362` y
`#9990363` ya se cerraron como "reconfirmaciones" (mismos resultados, documentados aparte). Este
item es el tercero y último de esa camada.

## Lo que el spec pedía vs. lo que ya estaba hecho

El spec de `#9990364` pedía tomar los 3 resultados y **escribir en `#909`** su
`reporte_coloquial`/`comentarios_claude`, con la advertencia explícita de que si no se poblaba ahí
`#909` cerraría con reporte vacío. Verificado contra el estado real: **`#909` ya no tiene el
reporte vacío** — otra vuelta (`wt-1`, ver el log de `#909`) ya ejecutó el "intento de cierre
faltante" sobre el paraguas `#909` y le pobló `reporte_coloquial`+`comentarios_claude`+
`enlace_revision` (apuntando a `#988`/`#989`/`#990` como sus sub-items reales), dejándolo
`aprobado_irving`+`excluir_pool_automatico=true`, aparcado hasta que sus 3 hijos cierren. Y el
propio `#990` concluye en su documento de verificación: *"`#909`... se completa solo vía el hook de
cierre en cascada... sin necesidad de tocar sus campos manualmente"*. Es decir: la pieza que este
item existía para evitar (un `#909` vacío) ya no puede pasar — se resolvió por una vía distinta
antes de que este item se ejecutara.

Duplicar el detalle de los 3 escenarios dentro de `#909` (que ya enlaza a `#990` para el detalle
completo) sería solo ruido — se dejó como está, sin tocar los campos de `#909`.

## Los 3 puntos de verificación pedidos por el spec

1. **Ramas de prueba locales de la familia:** revisado `git branch -a` contra `circuito/item-990-*`
   y `prueba/*` relacionadas. Las dos ramas `circuito/item-990-*` (`...-verificacion` y
   `...-bug-reanudarcolisiones...`) son las ramas REALES de trabajo de `#990`, ya mergeadas a
   `main` — no son "de prueba" (no se borran, es el historial normal de items cerrados, igual que
   cientos de otras ramas `circuito/item-*` ya mergeadas que siguen en el repo). La única rama
   genuinamente descartable que sobrevivía era `prueba/verificar-vuelta-escenario1`: apunta
   exactamente al commit ya mergeado `bc062f0d` (sin commits propios, sin checkout en ningún
   worktree — confirmado con `git worktree list`), así que se borró con `git branch -D`. La
   hermana `prueba/verificar-vuelta-escenario3-wt3` (mencionada en el log de `#990`) ya no existía
   — alguien más la limpió antes.
2. **Items de roadmap huérfanos de la prueba del escenario 3:** revisado. La corrida real de
   `circuito:consultar` del escenario 3 (test de `#9990363`, 16:39) reusó `--item=990` y sólo
   sobrescribió los campos `consulta_supervisor`/`consulta_opciones`/`consulta_respuesta` **del
   propio `#990`** — no creó ningún item nuevo (confirmado: `#990` ya tiene su propia nota
   `nota_prueba_controlada` documentándolo, y un listado de todos los items creados desde las 16:00
   no muestra ningún huérfano de esta familia aparte de los ya conocidos `9990362/63/64`).
3. **`main` limpio:** confirmado — los commits que llegaron a `main` de esta familia son todos
   documentales/reales (fix + docs de `#990`, docs de reconfirmación de `#9990362`/`#9990363`),
   ninguno con contenido de falla inyectada de prueba (esos vivieron solo en ramas descartables ya
   borradas, según documenta el propio `docs/circuito-verificar-vuelta-item-990-verificacion.md`).

## Conclusión

Sin cambio de código de negocio. Este item cierra: (a) documentando que su tarea principal
(poblar `#909`) ya no aplicaba por resuelta en otra vía, y (b) con los 3 puntos de verificación de
limpieza confirmados (1 rama de prueba huérfana encontrada y borrada). Al completarse este item —
el último de los 3 hijos de `#990` — el hook de cierre en cascada debe completar `#990`
automáticamente, y ese cierre a su vez debe completar `#909` (sus 3 hijos `#988`/`#989`/`#990`
quedarían todos `completado`).

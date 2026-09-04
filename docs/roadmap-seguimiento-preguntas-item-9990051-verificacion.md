# Item #9990051 — Seguimiento de las 2 preguntas sin resolver de #1000003 (verificación)

## Contexto

`#1000003` ("Circuito CC #911/#912 Fase 1a — Inventario BD, Cache y Colas por terminal") se cerró
el 2026-09-03 18:25 con el entregable ya hecho (`docs/circuito-estado-compartido-item-911-fase-a-bd-cache-colas.md`,
commit `ade2235c`), pero su brief estructurado (`preguntas[]`) seguía marcando 2 preguntas
`requiere_irving` sin `opcion_elegida` en el instante en que el generador de seguimientos leyó el
item (mismo minuto del cierre) → nació `#9990051` como seguimiento automático.

Irving respondió ambas preguntas el mismo día a las 18:45 (log de `#9990051`, `estado=aprobado_irving`,
`respuestas={q1:aba5200e55945f8b, q2:dc786fa1bea52023}`):

- **q1** — "¿Cómo abordar el inventario...?" → **Opción 1** (recomendada): reporte de solo lectura,
  sin modificar configs.
- **q2** — "¿Qué alcance de terminales cubrir?" → **Opción 1** (recomendada): solo las
  terminales/worktrees activos del Circuito CC (ejecutores del pool), sin el checkout principal.

## Verificación: el trabajo ya hecho coincide con lo que Irving eligió

Se releyó `docs/circuito-estado-compartido-item-911-fase-a-bd-cache-colas.md` (commit `ade2235c`,
en `main`, working tree limpio — confirmado con `git log`/`git status` sobre el archivo):

- Es **solo lectura**: "ningún comando de esta vuelta modificó código funcional, configuración ni
  datos; todo lo de abajo es verificación directa contra el filesystem, los `.env` de las 6
  terminales, `config()` en runtime y `docs/bitacora-sesiones.md`" (línea 3-5) → cumple q1.
- El alcance de terminales está declarado explícitamente: **"las 6 `wt-N` (`wt-1`…`wt-6`), que es
  el pool de ejecutores del circuito — no se auditó el checkout principal `/var/www/megaisp` por
  fuera de leer su config de Supervisor (ese es el consumidor de colas, no un worktree del pool)"**
  (línea 44-46) → cumple q2 al pie de la letra (Opción 1: solo terminales del pool, sin el checkout
  principal).

Ambas preguntas del brief de `#9990051` ya tienen la respuesta que Irving quería reflejada en el
entregable real, producido por la misma sesión que cerró `#1000003`. Es el mismo patrón de carrera
que `#733`/`#741`/`#9990003`: el generador de seguimientos leyó `preguntas[]` sin `opcion_elegida`
en el instante del cierre, antes de que existiera necesidad de que Irving decidiera nada nuevo — el
trabajo ya alineaba con la opción recomendada en ambos casos.

## Alcance de #9990051 vs. items hermanos

Los sub-items hermanos de la Fase 1 (`#1000004` Fase 1b, `#1000005` Fase 1c) también están
`completado`. `#1000006` (Fase 1d — consolidación final) sigue `aprobado_irving`, pendiente de
tomarse — pero es un item aparte, fuera del alcance de este seguimiento (que solo cubre las 2
preguntas de `#1000003`).

## Conclusión

Nada pendiente de decidir ni de construir para `#9990051`. **Sin cambio de código** — el documento
que resuelve ambas preguntas ya está en `main` desde antes de que este item se creara.

# Diagnóstico item #1009 — ¿el "Canal de Respuesta" y el "Motor Auditor" están implementados o solo documentados?

Item de **investigación** (sin cambios de código). Verifica la premisa de la skill `circuito-cc`,
que lista el Canal de Respuesta y el Motor Auditor auto-encadenado como "pendientes de ejecutar"
(puntos 5 y 6 de sus pendientes).

## 1. Columna `tipo` en `roadmap_items` — existe en BD, pero huérfana de código

La columna **sí existe** en la BD compartida de dev (migración
`2026_08_20_161800_add_tipo_to_roadmap_items`, corrida en batch 703). Pero el commit que la crea
(`3019db09`) vive **solo** en la rama `circuito/item-842-item-canal-de-respuesta-de-cc-hacia`,
**nunca mergeada a main**. En `main`, `RoadmapItem` no tiene ningún `TIPOS`, `fillable` ni cast que
la use — la columna está ahí pero **100% sin consumir**.

Verificado: de 703 items, **0** tienen `tipo != 'manual'` (el default de la migración). Nadie ha
escrito nunca `tipo='respuesta'`, `'auditoria'` ni `'hallazgo'`.

## 2. `hallazgo_firma` / `auditoria_ciclo` — no existen en ninguna parte

`git log --all -S"hallazgo_firma"` / `-S"auditoria_ciclo"` no encuentran ningún commit que las
cree. El dedupe real del auditor usa **`auditor_fingerprint`** (migración `2026_08_08_190000`),
que cumple el mismo rol con otro nombre.

## 3. Observer "por completado" — no existe, y es decisión deliberada

Item **#837** ("Motor Auditor auto-encadenado") pedía exactamente ese observer. Se cerró
**completado** con premisa incorrecta: el motor ya existía bajo **#559**, enganchado dentro de
`circuito:scheduler` con gating por cola+intervalo (más seguro que disparar en cada completado).
Ver `docs/motor-auditor-item-837-resolucion.md` + `docs/motor-auditoria.md`.

## 4. Comando `roadmap:auditar` — existe con otro nombre

`circuito:auditor` (`AuditorCommand.php`), dry-run por default. No existe `roadmap:auditar` tal
cual lo nombra la skill.

## 5. El Canal de Respuesta en sí (item nuevo `tipo='respuesta'`, `requiere_irving`) — NO existe

Este es el hallazgo central. **#842** ("Canal de Respuesta de CC hacia el supervisor") es el item
que construiría esto. Estado real: `aprobado_irving`, pero **huérfano** — `worker_sid=null`,
`claimed_at=null`, `reap_count=1`. La única terminal que lo tocó (wt-1, 2026-08-20) hizo **solo**
la columna `tipo` (para desbloquearse a sí misma vía #837), dejando el resto — el mecanismo que de
verdad crea el item hijo visible cuando un worker cierra con una pregunta sin resolver — **sin
construir**.

Lo que **sí existe y funciona hoy** es `roadmap_item_reports` (tabla append-only, creada
2026-08-08, **1551 filas reales** — incluye `tipo='respuesta'` con 17 filas y `'escalacion'` con
35), consumible vía `circuito:reportar --tipo=respuesta`. Cubre la mitad del problema (el reporte
ya no se pisa como pasa con `comentarios_claude`), pero **no** cubre la otra mitad que #842
imaginaba: aparecer como un **item nuevo en el tablero** que exige decisión explícita de Irving.

**Exposición real medida:** 461 items `completado` tienen `comentarios_claude` poblado — reporte
potencialmente sobreescribible si alguien vuelve a escribir ese campo (aunque ya no es la única vía:
`roadmap_item_reports` existe desde el 08-08 para lo que se reporte de ahí en adelante).

## Conclusión

El Canal de Respuesta está **a medio construir**, no completamente ausente: columna huérfana +
item dueño (#842) aprobado pero sin nadie trabajándolo. No corresponde tocar #842 aquí (no es este
item, y "un item = un dueño"). Este documento y el reporte `tipo='respuesta'` adjunto al item #1009
(`roadmap_item_reports` #1572) son la entrega de esta investigación — queda para que Irving decida
si vale la pena retomar #842 tal cual se concibió, o si `roadmap_item_reports` (que se construyó
DESPUÉS del spec original de #842 y ya cubre el caso de uso principal) lo vuelve innecesario.

## 2026-10-26 16:00 — Torre: por qué el circuito no avanzaba, limpieza de la Hoja de ruta y Árbol con «Seguimiento»

Pedido de Irving en sesión: contar lo pendiente, dejar Hechos/Pendientes/Cancelados limpios,
investigar por qué nadie da seguimiento a los pendientes y arreglar la pestaña Árbol.

**Diagnóstico [DEV, 14:56].** 789 items: 608 hechos, 7 cancelados, 174 abiertos. 6 terminales
libres y `circuito:scheduler --dry` = 0 despachables. Reparto de los abiertos:
- 64 esperan a otro item (casi todo UNI-T, encadenado a #868, que espera merge).
- 27 tienen el trabajo terminado y esperan el merge de Irving (incluye #625, seguimiento de JARVIS).
- 23 aprobados pero fuera de la cola: aprobar no quita `excluir_pool_automatico`; hace falta
  «Quitar el freno» y el aviso 422 no se ve.
- 18 proyectos esperando a sus sub-items; 15 decisiones; 6 esperas de insumo.
- 8 pausados (7 de UNI-T por ramas heredadas + #123 absorbido por #893).
- 1 aprobado por revisor con el revisor apagado (#626, fase 2 del seguimiento de JARVIS).
- Varias decisiones sin opciones: los briefs fallaron por la llave de IA de DEV.

**Limpieza aplicada [DEV].** Respaldo previo en
`storage/app/circuito/backup-roadmap_items-limpieza-hechos-*.json`. #36 y #650 → Cancelados
(ya estaban rechazado/cancelado). #21, #30, #99, #100, #108, #126, #585, #586, #640 → Hechos:
la vuelta verificó que ya estaban resueltos y solo dejó una nota de bitácora en su rama, que no
se mezcla (la de #585 trae además un commit heredado de otro item). #78 in_progress → pending.

**Código (rama `torre/arbol-seguimiento`).**
- Árbol legible en modo oscuro: `dark_mode.scss` pinta de blanco todo `<span>`; globos y
  contadores pasan a `<b>` con paleta propia.
- Cada módulo muestra todos sus items vivos, también los que cuelgan de un proyecto de otro módulo.
- Botón «Seguimiento» (`SeguimientoItemService`): por qué no avanza, quién lo mueve, preguntas
  con la recomendada y «Meter al circuito» (aprueba, quita freno, urgente, disparo).
- `circuito:rama` ignora ramas `circuito/item-<id>-*` cuyos commits son todos anteriores al
  alta del item (ids reusados).
- Pruebas: `SeguimientoItemArbolTest` y `RamaItemHeredadaTest` (6/6 en `megaisp_test`). A
  `megaisp_test` le faltaban 8 migraciones aditivas de roadmap; se aplicaron solo ahí.
  `DependenciaGateDespachoTest`, `StatusResincronizaAlEscalarTest` y `CierreHuecoRebotaTest`
  fallan igual en `main` (previo, no relacionado).

**Pendiente tras el merge.** `npm run prod` + `route:clear` en el checkout principal y reanudar
los 7 items de UNI-T pausados por rama heredada (#872, #876, #879, #881, #882, #920, #929).

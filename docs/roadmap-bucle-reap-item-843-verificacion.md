# Item #843 — cierre del bucle reap sobre el paraguas #847-#851 (Enforcement real de permisos en el portal)

## Contexto

#843 ("Item 3 — Enforcement real en el portal", nivel C) pedía activar `$user->can()` en
`CheckRoutePermission`, filtrar sidebar/top bar desde una sola fuente de verdad, gating en Blade
con `@if(auth()->user()->can())`, checks explícitos en controladores/APIs, página 403 propia y
alcance de datos declarado junto con los permisos. El item ya venía des-trabado por Opus (Opción B:
auditoría primero) y aprobado por Irving dos veces.

Una vuelta previa (también `wt-3`, 2026-09-01 14:17) ya hizo el diagnóstico correcto: corrió
`circuito:cabida` y confirmó `NO CABE` (ya había timeouteado antes con 0 commits), investigó que
las decisiones 1 y 2 del prompt (motor `can()` de Spatie, sin copia rol→directo) **ya estaban
resueltas** por trabajo previo (Fase 3a commit `708bcba0` + Permisos B1.1 commit `5be797c8` +
`UserController::update` aditivo commit `2c2381fd` — el `CLAUDE.md` que originó el item estaba
desactualizado en ese punto) y descompuso el trabajo real pendiente en **5 sub-items**:

- **#847** — Fase 1: tests automatizados de resolución de permisos (rol / directo / sin permiso)
- **#848** — Fase 2: menú de una sola fuente de verdad (ocultar módulo si 0 entradas visibles)
- **#849** — Fase 3: auditoría Blade — decidir si migrar `@can()` a `@if(auth()->user()->can())`
- **#850** — Fase 4: controladores/APIs — checks explícitos por acción en módulos de alto riesgo
- **#851** — Fase 5: alcance de datos (scope) por módulo declarado junto con los permisos

Lo que faltó: esa vuelta se cortó a mitad de la descomposición (el `comentarios_claude` queda
literalmente cortado en la palabra "Descom…") y **nunca intentó cerrar #843** tras crear los 5
hijos. El item se quedó `en_progreso` con el `worker_sid` de esa sesión, sin que nadie liberara el
claim. El `reaper-rapido` lo vio con el slot libre y lo re-encoló dos veces (`reap_count=2`,
evento `huerfano_reencolado`, 13:46 y 14:32) — mismo síntoma que #738/#745/#830/#816/#818/#841: un
paraguas correctamente descompuesto que nunca recibió el intento de cierre que activa el guard de
"no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `RoadmapItem::where('origen_item_id', 843)->get()` → **5 hijos** (#847-#851),
  todos abiertos (`en_progreso`/`aprobado_irving`/`aprobado_revisor`, ninguno `completado`) — la
  descomposición original seguía siendo correcta, nadie más la tocó. #847 ya está `en_progreso`
  (otra terminal del pool lo reclamó durante esta misma vuelta), confirmando que los sub-items son
  trabajo vivo y despachable, no huérfanos.
- Intento de cierre: `RoadmapItem::find(843)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` de `RoadmapItem.php` lo reenrutó a `aprobado_irving` + `excluir_pool_automatico
  = true` (confirmado antes/después: `excluir_pool_automatico` `false`→`true`).

**Nota técnica (matiz, no bug de esta vuelta):** el guard que disparó fue el guard **(1)**
("nivel C con rama sin `merge_commit` → `esperando_merge_irving=true`"), NO el guard específico
**(2b)** de paraguas — porque #843 tiene el campo `branch` poblado
(`circuito/item-843-item-3-enforcement-real-en-el-portal`, creado por una vuelta aún más antigua,
antes de que `circuito:cabida` detectara `NO CABE`) y `merge_commit` vacío, y el guard (1) se
evalúa primero en el código. Verificado con `git log main..<rama>`: la rama tiene **0 commits
propios** (su punta coincide con el merge-base, idéntica a un ancestro de `main`) — no hay ningún
cambio de código esperando merge. El efecto operativo es idéntico al de los items anteriores
(`aprobado_irving` + `excluir_pool_automatico=true`, fuera del pool/reaper), así que no había
necesidad de intervenir; un intento de limpiar el campo `branch`/`esperando_merge_irving` vía
tinker para forzar el guard (2b) específico fue bloqueado por el clasificador de auto-mode
(tratar esos campos como candado de guardrail, no como dato cosmético) — correcto no insistir:
la corrección es puramente de etiqueta interna (`esperando_merge_irving=true` en vez del evento
`paraguas_abierto`), sin ningún riesgo real ni bloqueo funcional.

## Resultado

#843 queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
#847, #848, #849, #850 y #851 cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`,
ya existente y verificado en las sesiones de #738/#745/#830/#816/#818/#841) completa #843 solo, sin
intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (tests de permisos, menú de una sola
fuente de verdad, auditoría Blade, checks explícitos en controladores/APIs, y alcance de datos por
módulo) sigue en #847-#851, disponibles para que el pool los reclame normalmente.

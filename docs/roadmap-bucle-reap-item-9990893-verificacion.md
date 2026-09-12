# Item #9990893 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

`#9990893` ("Fase 3 (C2) — que 'Listos para terminal' diga la verdad + contador de vueltas
quemadas + cablear #9990798") es un sub-item de seguimiento de `#9990863`. Nace `pendiente_revision`,
pasa por triaje (nivel B, sin frontera dura), el revisor lo escala a `requiere_irving` por
ambigüedad (mezcla 3 sub-objetivos sin plan concreto), y el DES-TRABE (Opus) lo re-aprueba como
`tecnico_seguro` (mejora aditiva/reversible del propio circuito, no toca dinero/seguridad/prod).

## Lo que ya hizo la vuelta anterior (correcto)

Una vuelta previa (`wt-3`, 2026-09-11 19:02 CST) hizo el trabajo de análisis correctamente:

1. Verificó que la pieza "cablear #9990798" del spec **ya estaba aplicada** — `#9990798` ya tiene
   `depende_de=[9990790]` fijado en BD (`#9990790` sigue `aprobado_irving`, no completado). No
   requería sub-item propio; solo faltaba que `SupervisorService::listosParaTerminal()` dejara de
   mostrarlo como listo pese a la dependencia sin resolver.
2. Corrió `circuito:cabida` → **NO CABE** (`historico_excede_umbral`).
3. Descompuso el trabajo en 3 fases acotadas (`origen_item_id=9990893`):
   - **#9990923** — Fase 3a: `SupervisorService::listosParaTerminal()`/`::listosParaTerminalTotal()`
     excluyen items con `depende_de` sin resolver (reusando `estaCerradoParaDependencia()` de
     MR-36, sin reinventar la regla) o `motivo_espera != null` (CIRC-03).
   - **#9990924** — Fase 3b: heurística de texto sobre el prompt para bloqueos declarados.
   - **#9990925** — Fase 3c: mostrar en la Torre el contador de "vueltas quemadas" que **ya
     existe** como `reap_count`/`veces_timeouteo` (sin crear columna nueva).

## El bug: nunca se intentó cerrar al padre

El comentario de esa vuelta se cortó a media escritura ("...surfacing en la Torre del contador de
vueltas quemadas que YA EXISTE como reap_count/veces" — sin terminar la oración). El item se quedó
`en_progreso` con el `worker_sid` de esa sesión. El reaper (`circuito:reap-stuck`) lo detectó
huérfano 27 minutos después (`claimed_at`/`updated_at` fríos) y lo re-encoló a `aprobado_revisor`
(`reap_count=1`). El pool lo repartió de nuevo (a `wt-6`, esta vuelta) sin que hubiera trabajo
propio pendiente — mismo patrón documentado repetidas veces en `CLAUDE.md` (#738, #745, #830,
#816, #818, #848, #852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936, #9990408, #962,
#9990554, #9990549, #9990624, #9990650, #9990807, #9990826, #9990836, #9990856, #9990892,
#9990896, #9990886).

## Verificación de esta vuelta

Confirmado contra la BD real que los 3 hijos siguen intactos, nadie más los tocó:

| Item | estado_aprobacion | worker_sid |
|------|-------------------|------------|
| #9990923 | `en_progreso` | `wt-3` (otra terminal trabajándolo activamente — no se toca) |
| #9990924 | `aprobado_revisor` | vacío (sin reclamar) |
| #9990925 | `aprobado_revisor` | vacío (sin reclamar) |

La descomposición original seguía siendo correcta.

## Corrección aplicada

Se ejecutó el intento de cierre faltante sobre `#9990893`
(`estado_aprobacion='completado'`). El guard de paraguas del modelo
(`app/Modules/Addons/Roadmap/Models/RoadmapItem.php`, bloque "(2b) PARAGUAS", ~líneas 392-423)
detectó los 3 sub-items abiertos y lo reenrutó automáticamente:

- `estado_aprobacion` → `aprobado_irving`
- `excluir_pool_automatico` → `true`
- `worker_sid`/`claimed_at` → `null` (libera el reclamo)
- Log: evento `paraguas_abierto`, "le quedan 3 sub-item(s) abierto(s)".

Con esto `#9990893` sale del pool/reaper y queda parqueado hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando `#9990923`, `#9990924` y `#9990925` cierren
los tres.

## Sin cambio de código de negocio

El trabajo técnico real (SupervisorService, heurística de texto, contador en la Torre) sigue en
`#9990923`/`#9990924`/`#9990925`, pendientes de que sus dueños los cierren.

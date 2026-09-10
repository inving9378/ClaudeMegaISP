# Item #9990686 — cierre del bucle reap sobre el paraguas #9990691/#9990692 (F5a — eje "versiones sin publicar")

## Contexto

#9990686 es el sub-item de seguimiento de #9990676 (Fase F5a: agregar el eje "versiones sin
publicar" a `AuditorService` con dedupe, tipo=hallazgo).

Una vuelta previa (también `wt-3`, 2026-09-10 08:59) ya hizo el trabajo de triage correcto:

1. Leyó el item, ya re-aprobado por DES-TRABE (Opus) tras un falso positivo del Revisor por la
   palabra "deploy" (mención del nombre del módulo, no acción de despliegue).
2. Corrió `circuito:cabida 9990686` → **NO CABE** (histórico ~597s).
3. En vez de picar código directo, descompuso el trabajo en dos sub-items secuenciales:
   - **#9990691** (F5a-1 — extraer `ReconcileReleasesCommand::reconciliar()` reusable).
   - **#9990692** (F5a-2 — agregar `AuditorService::ejeVersionesSinPublicar()` + tipo='hallazgo'
     en `crear()`, depende de la posición 1).
4. Reportó la decisión (`circuito:reportar --tipo=decision`) y terminó la vuelta con
   `ejecuto=false`.

Esa parte fue correcta. Lo que faltó: **esa vuelta nunca intentó cerrar #9990686** (ni a
`completado` ni de ninguna otra forma). El item se quedó `en_progreso` con el `worker_sid` de esa
sesión, sin que nadie liberara el claim. El evento `soltar-claim` (proceso muerto: kill, OOM o
freno a media vuelta) lo devolvió a `aprobado_revisor`, y el pool lo volvió a repartir (a la misma
terminal `wt-3`) sin que hubiera trabajo propio que hacer — el trabajo real ya estaba
correctamente delegado a #9990691/#9990692 — mismo síntoma que la familia de items #738/#745/
#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962/
#9990554/#9990549/#9990624/#9990650: un paraguas correctamente descompuesto que nunca recibió el
intento de cierre que activa el guard de "no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: ambos hijos (`origen_item_id = 9990686`) existen intactos:
  - **#9990691** — "F5a-1 — extraer ReconcileReleasesCommand::reconciliar() reusable",
    `estado_aprobacion=pendiente_revision`, `worker_sid=null`, sin `merge_commit` — sigue abierto.
  - **#9990692** — "F5a-2 — AuditorService::ejeVersionesSinPublicar() + tipo=hallazgo en
    crear()", `estado_aprobacion=pendiente_revision`, `worker_sid=null`, sin `merge_commit` —
    sigue abierto (además depende en orden de #9990691).
- `circuito:cabida 9990686 --sid=wt-3` → `CABE [ya_descompuesto]` (no re-descompone; solo dice
  "intenta cerrar, el guard de paraguas decide").
- Intento de cierre: `RoadmapItem::find(9990686)->estado_aprobacion = 'completado'; ->save();` →
  el guard `saving` (bloque "(2b) PARAGUAS", `RoadmapItem.php` ~301-326) lo reenrutó
  automáticamente a `aprobado_irving` + `excluir_pool_automatico=true`, y agregó al log el evento
  `paraguas_abierto` ("le quedan 2 sub-item(s) abierto(s): no se completa. Queda como paraguas y
  cierra solo cuando el último de ellos cierre"). Confirmado leyendo `$item->log` tras el save.
- Se liberó el claim (`worker_sid=null`, `claimed_at=null`) para sacar el item del pool/reaper de
  inmediato, sin esperar al próximo ciclo de reap.

## Resultado

#9990686 queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta
que #9990691 y #9990692 cierren ambos — en ese momento el hook `saved`
(`RoadmapItem.php:459-491`, ya existente y verificado en las sesiones anteriores de esta misma
familia) completa #9990686 solo, sin intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (extraer `reconciliar()` reusable de
`ReconcileReleasesCommand` y agregar el eje `ejeVersionesSinPublicar()` en `AuditorService` con
tipo='hallazgo') sigue en #9990691 y #9990692, pendientes de que una terminal los reclame.

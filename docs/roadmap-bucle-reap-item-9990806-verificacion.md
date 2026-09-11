# Item #9990806 — bucle reap sobre paraguas ya descompuesto, con backend real sin integrar (reapertura de acuses al versionar plantilla)

## Contexto

#9990806 (sub-item de seguimiento de #9990792) implementa la decisión de Irving sobre 3
preguntas estructuradas: q1 (Opción 2, reapertura **selectiva** por tipo de cambio
mayor/menor), q2 (Opción 1, conservar histórico inmutable de lo firmado antes de reabrir) y q3
(Opción 1, notificación in-app + badge). Una vuelta previa (`wt-6`, 2026-09-11 14:26-14:27) ya
había hecho el trabajo correcto: implementó y commiteó en la rama del item el **núcleo del
mecanismo** —`AcuseReopeningService` (snapshot append-only en
`talento_employee_document_reaperturas` + limpia el estado vigente + regenera el HTML),
columna `tipo_cambio` (enum `mayor`/`menor`, default `mayor`) en
`talento_document_template_versions`, y el enganche ÚNICO en
`TemplateVersionService::createVersion()` (dispara la reapertura solo si `tipo_cambio==='mayor'`
y solo para plantillas `tipo='acuse'`, nunca `firma`) — verificó que compilaba (`php -l`) y que
migraba limpio contra la BD compartida de dev. Corrió `circuito:cabida` → **NO CABE**
(`ya_timeouteo_antes`) y descompuso el resto en:

- **#9990817** — verificación end-to-end con datos sintéticos (rollback).
- **#9990818** — notificación in-app al colaborador afectado (implementa la decisión q3 ya
  aprobada por Irving).

Explícitamente dejó **fuera de alcance** de este item la "UI de publicación de plantillas con
selector mayor/menor" — hoy no existe ninguna pantalla para publicar versiones de plantillas
(`createVersion()` se invoca programáticamente); construir esa pantalla es una feature aparte.

## Qué pasó después (el bucle)

Esa vuelta nunca intentó **cerrar** al padre tras descomponerlo — murió a media escritura del
comentario de decisión (texto cortado en `comentarios_claude`, terminaba en "Descompuse el t").
El log del item muestra la secuencia típica de esta familia:

1. `timeout:reanudado` (max_turns, con 2 commits reales en la rama → reanudación 1 de 2).
2. `soltar-claim` (`wt-6`) — el proceso murió sin cerrar el item; se liberó el reclamo y volvió
   a la cola como `aprobado_irving`.
3. El pool volvió a repartir #9990806 (esta vez a `wt-3`) sin que hubiera trabajo NUEVO por
   descomponer — la descomposición ya estaba hecha y los 2 sub-items ya existían intactos.

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990733/#9990803: la descomposición fue
correcta, pero el "intento de cierre" que dispara el guard de paraguas nunca se ejecutó.

## Diferencia con la mayoría de los precedentes: había código real SIN integrar

A diferencia de la mayoría de esos items (donde el padre ya no tenía trabajo propio pendiente,
solo bookkeeping), aquí la rama `circuito/item-9990806-...` traía **2 commits de código real y
ya verificado** (`8e99e79c` + `389e3a77`) que **nunca se habían integrado a main**
(`merge_commit` seguía `null`). Antes de intentar el cierre, esta vuelta:

1. Re-verificó el diff completo de los 6 archivos tocados (modelo, servicio nuevo, hook en
   `TemplateVersionService`, 2 migraciones) contra `main` — coincide exactamente con las
   decisiones q1/q2 de Irving (selectivo mayor/menor; snapshot inmutable antes de limpiar el
   estado vigente).
2. Corrió `php -l` sobre el contenido real de la rama (extraído vía `git show branch:archivo`,
   sin poder hacer `git checkout` porque el worktree `wt-6` seguía con esa rama activa —
   aislamiento #334) — limpio en los 6 archivos.
3. Confirmó contra la BD compartida de dev que las dos migraciones (`tipo_cambio` +
   `talento_employee_document_reaperturas`) ya estaban aplicadas (registradas en la tabla
   `migrations`, batches 672/674) — el `php artisan migrate` que corrió `wt-6` sí había
   surtido efecto real, solo que el código nunca llegó a `main`.
4. Confirmó que las clases/métodos referenciados (`TalentoEmployeeDocumentSignature`,
   `TalentoDocumentTemplate::signatureSlots()`, `EmployeeDocumentPackageService::regenerateOne()`)
   ya existen en `main` sin cambios — sin dependencias rotas.
5. Corrió `php artisan circuito:integrar 9990806` para encolar el merge del trabajo YA
   verificado (en vez de dejarlo varado indefinidamente detrás de un padre parqueado).

El runner on-box procesó la cola casi de inmediato: mergeó la rama a `main`
(`ca689dc9`, merge de `e201526d` + `389e3a77`) y su propio intento de cerrar el item a
`completado` (`MergeRunner::markMerged()`) fue interceptado por el mismo guard de paraguas del
modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") — quedó `aprobado_irving` +
`excluir_pool_automatico=true`, con `merge_commit` ya poblado. Es decir: el "intento de cierre
faltante" en este caso lo disparó el propio `MergeRunner`, no un `tinker` manual, y con el
beneficio extra de que el backend real quedó entregado en `main` en vez de seguir varado.

## Verificación de esta vuelta

Confirmado contra la BD real tras el merge: los 2 hijos (#9990817, #9990818) siguen intactos,
`pendiente_revision`, sin `worker_sid` (sin reclamar) — la descomposición original seguía
siendo correcta, nadie más la tocó. `git merge-base --is-ancestor` confirma que el merge commit
`ca689dc9` es ancestro de `main`; el diff del merge trae exactamente los 6 archivos esperados
(246 inserciones, 4 eliminaciones netas por el cambio de firma de `createVersion()`).

## Corrección aplicada

Se integró el código ya verificado (`circuito:integrar 9990806`), que el runner mergeó a `main`
y cuyo intento automático de cierre el guard de paraguas reenrutó a `aprobado_irving` +
`excluir_pool_automatico=true`, liberando `worker_sid`/`claimed_at` y sacándolo del pool/reaper
hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando
#9990817 y #9990818 cierren los dos.

**Backend del mecanismo de reapertura selectiva ya en `main`** (commit `ca689dc9`) — el trabajo
que falta (verificación E2E con datos sintéticos y notificación in-app) sigue en
#9990817/#9990818, pendientes de que una terminal los reclame.

# Item #9990826 — bucle reap sobre paraguas ya descompuesto (verificación)

## Qué pedía el item

"Reapertura de acuses — implementar y correr el test E2E (Feature, DB de test)": implementar
`tests/Feature/Modules/Talento/ReaperturaAcusesTest.php` contra la lógica ya construida y en
`main` (`AcuseReopeningService::reopenForNewVersion()`, `TemplateVersionService::createVersion()`,
`EmployeeDocumentPackageService::regenerateOne()`), con happy path, caso negativo y 2 casos borde.

## Qué encontró esta vuelta

El item llegó ya `en_progreso` (reclamado para wt-2). Al leer su historial:

1. Una vuelta previa (`wt-4`, 2026-09-11 15:39) corrió `circuito:cabida` → **NO CABE**
   (`ya_timeouteo_antes`).
2. En vez de picar código a ciegas, investigó a fondo el código real ya en `main`: leyó
   `AcuseReopeningService.php` (línea 36: corta si `template->tipo !== 'acuse'`; líneas 40-42:
   solo toma documentos `status='completo'` de ese `template_id`), `TemplateVersionService.php`
   (`createVersion()` dispara la reapertura solo con `tipoCambio='mayor'`; `createTemplate()`
   crea la v1 con `tipoCambio='menor'` automático), y las migraciones reales de
   `talento_colaboradores` (única columna sin default: `user_id`) y
   `talento_employee_document_signatures` (sin FK a `signature_slots`, migración
   `2026_09_09_140100`).
3. Dejó la receta **completa** (setup exacto con los nombres de columna reales, 1 fila de
   `talento_document_template_signature_slots` para ejercitar la rama multi-slot, asserts del
   happy path, caso negativo con `tipoCambio='menor'`, borde (i) acuse ya reabierto y borde (ii)
   template de tipo distinto) en el sub-item **#9990834** ("Reapertura de acuses — implementar y
   pasar ReaperturaAcusesTest.php (happy path + negativo + 2 bordes)"), justo para que el
   próximo ejecutor no tuviera que re-investigar nada de esto.

Pero esa vuelta **murió a media escritura** de su comentario de decisión (el texto quedó cortado
en `comentarios_claude`: "...para que el proxi") **sin intentar cerrar** al padre #9990826. El
log del item solo registra el evento `soltar-claim` de `wt-4` ("La vuelta de wt-4 terminó sin
cerrar el item... se libera el reclamo y vuelve a la cola como aprobado_revisor"). Sin ese
intento de cierre, Irving volvió a aprobar el item (`aprobado_irving`, 15:34:57 — antes incluso
de que el `soltar-claim` de wt-4 se registrara a las 15:39:22, por la carrera de timing habitual
de estos casos) y el pool lo repartió de nuevo (a mí, wt-2) sin que hubiera trabajo propio
pendiente — exactamente el mismo patrón "bucle reap sobre paraguas ya descompuesto" documentado
en `CLAUDE.md` para #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/
#910/#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807 y otros.

## Verificación hecha antes de tocar nada

- `AcuseReopeningService.php` y `TemplateVersionService.php` siguen existiendo en
  `app/Modules/Addons/Talento/Services/` — la spec de #9990834 no quedó huérfana de un revert
  posterior.
- #9990834 (`origen_item_id=9990826`) sigue intacto: `pendiente_revision`, `worker_sid=null`,
  sin reclamar — la descomposición original de `wt-4` seguía siendo correcta, nadie más la tocó.
- La rama `circuito/item-9990826-reapertura-de-acuses-implementar-y-cor` no tenía commits propios
  (`git log main..HEAD` vacío) — consistente con el `commits_rama:0` del timeout previo de este
  mismo item: `wt-4` nunca llegó a escribir código, solo la decisión + el sub-item.
- Regla dura del prompt de ejecución: "tu item #9990826 ya fue RECLAMADO para ti. NO toques otros
  items" → no se implementó el contenido de #9990834 bajo el claim de #9990826.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion='completado'` sobre #9990826). El
guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo reenrutó
automáticamente a `aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto`
en el log, "le quedan 1 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de
cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990834 cierre.

**Sin cambio de código de negocio.** El trabajo técnico real (implementar y correr
`tests/Feature/Modules/Talento/ReaperturaAcusesTest.php` con la receta ya documentada) sigue en
#9990834, pendiente de que el revisor lo tríe y una terminal lo reclame.

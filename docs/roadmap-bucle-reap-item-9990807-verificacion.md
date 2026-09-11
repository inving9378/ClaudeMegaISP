# Item #9990807 — Pantalla del colaborador (pendientes/firmados) + tablero de pendientes para Irving + endpoints API-first — bucle reap sobre paraguas ya descompuesto

## Contexto

Misma familia de bug documentada repetidamente en `CLAUDE.md` (#738, #745, #830, #816, #818,
#848, #852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936, #9990408, #962, #9990554,
#9990549, #9990624, #9990650, entre otros): un item se descompone correctamente en sub-items,
pero nadie ejecuta el intento de cierre que dispara el guard de paraguas (`RoadmapItem.php`
bloque "(2b) PARAGUAS"), así que el item se queda colgado hasta que el reaper lo re-encola y el
pool lo vuelve a repartir sin que haya trabajo propio que hacer.

## Lo que ya se hizo bien (sesión previa)

`#9990807` (sub-item de seguimiento de `#9990792`) pedía tres piezas grandes: (1) endpoints
API-first para que el colaborador liste sus documentos (pendientes/firmados) y descargue/firme
desde el Portal de Talento, (2) la pantalla del colaborador que consume esos endpoints, y (3) un
tablero admin para Irving con antigüedad del pendiente por colaborador/documento.

El revisor lo había escalado por ambigüedad de alcance/UX/permisos; el DES-TRABE (Opus) lo
re-aprobó (aditivo/reversible, sin frontera dura). Una vuelta previa (`wt-1`, 2026-09-11 14:21) ya
hizo lo correcto: corrió el análisis de cabida, resolvió las 6 preguntas estructuradas con la
opción recomendada de cada una (registrado en `comentarios_claude`: q1 vista única con tabs
Pendientes/Firmados/Todos + KPI cards; q2 tablero admin = tabla agrupada por colaborador; q3
tablero solo-lectura + botón "recordar"; q4 endpoints internos bajo `/talento/portal/*` y
`/talento/api/colaboradores/*`, sesión web + permisos) y descompuso el trabajo en 3 sub-items:

- **#9990813** — "Endpoints API-first self-scoped del Portal de Colaborador para documentos
  (pendientes/firmados/descarga/firmar)".
- **#9990814** — "Pantalla del colaborador (Pendientes/Firmados/Todos) en el Portal de Talento".
- **#9990816** — "Tablero admin para Irving: qué colaborador tiene qué documento pendiente (con
  antigüedad + recordar)".

Antes de descomponer, esa misma vuelta dejó un commit propio en la rama del item
(`4095fcc8`, autor Irving MegaISP): extrae dos helpers reusables —
`Talento/Support/SignatureImageInput::resolve()` (decodificar/validar imagen de firma) y
`Talento/Support/SignatureSlotStatus::describir()` (estado de firma por documento) — pensados
explícitamente "para el nuevo endpoint self-scoped del portal" (mensaje del commit), sin tocar el
controlador admin ya aprobado (`TalentoEmployeeDocumentController`).

## Por qué se quedó colgado

Justo después de escribir la decisión de descomposición en `comentarios_claude`, esa vuelta
agotó sus turnos (`max_turns`); como la rama tenía 1 commit (avance real), se reanudó una vez
(`reanudacion 1 de 2`) en vez de escalar — pero nadie volvió a intentar el cierre del padre. El
siguiente evento fue `reaper-rapido` (2026-09-11 14:42:03): "el slot wt-1 está libre (ninguna
vuelta corriendo ahí): reclamo huérfano" → re-encolado como `aprobado_revisor` (`reap_count=1`).
El pool lo repartió de nuevo, esta vez otra vez a `wt-1` (esta vuelta).

## Verificación del estado real (esta vuelta)

```
9990807 (yo) | en_progreso        | excl=false | sid=wt-1 | branch con 1 commit (4095fcc8)
  9990813    | requiere_irving    | origen_item_id=9990807 | sin reclamar
  9990814    | requiere_irving    | origen_item_id=9990807 | sin reclamar
  9990816    | requiere_irving    | origen_item_id=9990807 | sin reclamar
```

Los 3 hijos siguen intactos, sin reclamar (`worker_sid` vacío) — la descomposición original
seguía siendo correcta, nadie más la tocó.

## Corrección aplicada

A diferencia de la mayoría de los precedentes (donde el commit sobrante de la rama del padre se
deja sin mergear, "inerte" hasta que alguien lo redescubra), aquí el commit de helpers es
reusable y de bajo riesgo (2 archivos nuevos, sin efecto por sí mismos), así que esta vuelta
primero **encoló su merge** (`php artisan circuito:integrar 9990807`) antes de cerrar el
paraguas, para que `SignatureImageInput`/`SignatureSlotStatus` estén disponibles en `main` cuando
alguien reclame `#9990813`. El runner on-box lo mergeó de inmediato (commit `1f65a01a`,
verificado `git show --stat` + los dos archivos presentes en `main`).

Después se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'`). El guard
de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detectó y reenrutó:

```
{"por":"paraguas","evento":"paraguas_abierto",
 "motivo":"Este item se descompuso y le quedan 3 sub-item(s) abierto(s): no se completa. Queda
           como paraguas y cierra solo cuando el último de ellos cierre.",
 "subitems_abiertos":3}
```

Resultado final verificado: `estado_aprobacion=aprobado_irving`, `excluir_pool_automatico=true`,
`merge_commit=1f65a01ab9e6022a3cad389f78993b7ba5abacd4`, `archivado_at` poblado (backend puro, sin
UI — `revision_ui=false`), `worker_sid`/`claimed_at` limpiados. Queda sacado del pool/reaper hasta
que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990813,
#9990814 y #9990816 cierren los tres.

## Sin cambio de código de negocio propio

El trabajo técnico real (endpoints self-scoped, pantalla del colaborador, tablero admin) sigue en
#9990813/#9990814/#9990816, pendientes de que una terminal los reclame. El único código que
aterrizó en esta vuelta es el merge del commit ya existente de la sesión anterior (helpers de
firma), sin picar código nuevo.

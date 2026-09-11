# Item #9990803 — bucle reap sobre paraguas ya descompuesto (buscador de Clientes v2)

## Contexto

#9990803 ("Clientes: el buscador del listado debe buscar únicamente en las columnas visibles
de cada administrador") es un item nivel B, con 4 preguntas estructuradas ya resueltas por
Irving el 2026-09-11. Una vuelta anterior (`wt-4`, misma terminal) ya hizo el trabajo correcto
en su primer intento: ejecutó la **Fase 0 (auditoría read-only)** completa — documentada en
`comentarios_claude` — confirmando entre otras cosas que la persistencia de columnas visibles
(D1) YA vive en `column_datatable_modules`/`user_column_datatable_modules` (sin tabla nueva) y
que el SN de ONT vive en `olt_onus.sn` con cardinalidad 1:N real respecto al cliente (18
clientes con más de una fila). Commiteó la **Fase 1a** (`20233bff`, config
`clientes_busqueda.php` + `ClienteSearchService`) en la rama del item. Corrió
`circuito:cabida` → **NO CABE** (`ya_timeouteo_antes`) y descompuso el resto del trabajo en 3
sub-items:

- **#9990811** — Fase 1b: wiring backend del buscador v2 detrás del flag.
- **#9990812** — Fase 2+3: frontend/UX (placeholder dinámico, nota en el modal, botón de
  búsqueda ampliada).
- **#9990815** — Fase 4: medición p50/p95, criterios de aceptación y encendido del flag en dev.

## Qué pasó después (el bucle)

Esa vuelta nunca intentó **cerrar** al padre tras descomponerlo. El log del item muestra:

1. `timeout:reanudado` (max_turns) — la vuelta agotó turnos, pero como la rama tenía 1 commit
   real se reanudó como `aprobado_irving` (reanudación 1 de 2).
2. `soltar-claim` — el proceso de la siguiente reanudación murió a media vuelta (kill/OOM/freno)
   sin cerrar el item; se liberó el reclamo y volvió a la cola como `aprobado_irving`.
3. El pool volvió a repartir #9990803 (esta vez otra vez a `wt-4`) sin que hubiera trabajo
   propio pendiente — la Fase 0 y la Fase 1a ya estaban hechas y los 3 sub-items ya existían.

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990733: la descomposición fue correcta,
pero el "intento de cierre" que dispara el guard de paraguas nunca se ejecutó.

## Verificación de esta vuelta

Confirmado contra la BD real: los 3 hijos (`origen_item_id=9990803`) siguen intactos,
`pendiente_revision`, sin `worker_sid` (sin reclamar) — la descomposición original seguía
siendo correcta, nadie más la tocó. La rama del item (`circuito/item-9990803-...`) sigue con
el único commit de Fase 1a, sin cambios adicionales.

## Corrección aplicada

Se ejecuta el intento de cierre faltante (`estado_aprobacion = 'completado'`). El guard de
paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detecta y reenruta
automáticamente a `estado_aprobacion=aprobado_irving` + `excluir_pool_automatico=true`,
liberando `worker_sid`/`claimed_at` y sacándolo del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990811, #9990812 y #9990815
cierren los tres.

**Sin cambio de código de negocio** — el trabajo técnico real del buscador v2 (wiring backend,
frontend/UX, medición y cierre) sigue en #9990811/#9990812/#9990815, pendientes de que una
terminal los reclame.

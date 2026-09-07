# Item #955 — MR-19 Carta de empalme por caja (vista + PDF) — bucle reap sobre paraguas ya descompuesto

## Contexto

`#955` (MR-19, sub-item de la épica MAPA DE RED `#936`) pedía un diagrama por elemento contenedor
mostrando bandeja por bandeja qué hilo entra, con qué se fusiona y a dónde sale, con los colores
del catálogo, exportable a PDF (DoD: generar la carta de una mufa real de Tultitlán y que un
técnico la siga sin explicación adicional).

Irving ya había resuelto las 4 preguntas estructuradas del item (todas la opción 1/recomendada:
DomPDF, MVP sin loose-tube/reservas, colores del catálogo tal cual, landscape) en dos aprobaciones
sucesivas (`2026-09-07 09:06` y `2026-09-07 15:17`), tras un primer timeout por `max_turns` sin
commits.

## Qué se encontró

Una vuelta previa (`wt-1`, 2026-09-07 17:03) ya hizo el trabajo correcto de análisis:

- Corrió `circuito:cabida` → **NO CABE** (el item ya había timeouteado 2 veces sin commits).
- Investigó la base ya construida por MR-12: `mapared_empalmes` / `MapaRedEmpalme` /
  `MapaRedHilo`, `EmpalmesController::existentes`.
- Confirmó que DomPDF (`barryvdh/laravel-dompdf`) ya está en `composer.json` — no hay que
  instalarlo.
- Descifró las 4 decisiones de Irving vía hash de opciones.
- Descompuso el trabajo en dos sub-items (`origen_item_id=955`):
  - **`#9990565`** — Fase 1 (Backend): query agrupada por bandeja + export PDF.
  - **`#9990566`** — Fase 2 (Frontend): botón/dialog "Carta de empalme" + verificar el DoD con
    la mufa real de Tultitlán.

Pero el proceso **murió a media escritura** del comentario de decisión (el texto de
`comentarios_claude` queda cortado en `"...y #9990566 (Fase 2 fronten"`) antes de intentar
**cerrar** el item padre. El log solo registra `soltar-claim` / `claim_liberado_al_morir_la_vuelta`
a las `17:03:32` — mismo instante del corte — y el item volvió a la cola como `aprobado_irving`
sin nadie que completara el intento de cierre. El pool lo repartió de nuevo (esta vez a `wt-5`, con
`veces_timeouteo=2`).

Este es el mismo patrón documentado repetidamente en `CLAUDE.md` (items #738, #745, #830, #816,
#818, #848, #852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936, #9990408, #962, entre
otros): el guard de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") solo aparca un item
descompuesto cuando algo **intenta activamente** `estado_aprobacion = 'completado'` y detecta
hijos abiertos. Sin ese intento, el item queda `en_progreso` colgado, el reaper lo re-encola, y el
pool lo reparte de nuevo sin que haya trabajo propio pendiente.

## Verificación

Consulta directa a la BD confirmó que ambos sub-items siguen intactos, sin reclamar, tal como los
dejó la vuelta anterior:

```json
[
  {"id": 9990565, "title": "MR-19 Fase 1 — Backend: query de carta de empalme + export PDF (DomPDF)",
   "estado_aprobacion": "pendiente_revision", "worker_sid": null, "branch": null, "merge_commit": null},
  {"id": 9990566, "title": "MR-19 Fase 2 — Frontend: botón/dialog Carta de empalme + verificar DoD con mufa real de Tultitlán",
   "estado_aprobacion": "pendiente_revision", "worker_sid": null, "branch": null, "merge_commit": null}
]
```

La descomposición original seguía siendo correcta — nadie más la tocó desde entonces.

## Corrección aplicada

Esta vuelta ejecutó el intento de cierre faltante:

```php
$i = RoadmapItem::find(955);
$i->estado_aprobacion = "completado";
$i->save();
```

El guard de paraguas lo reenrutó, como es esperado, a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `flags` en el log, `excluir_pool_automatico` antes=false
→ después=true), liberando `worker_sid`/`claimed_at` y sacándolo del pool/reaper hasta que el hook
de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando `#9990565` y `#9990566`
cierren los dos.

## Sin cambio de código de negocio

El trabajo técnico real de MR-19 (query agrupada por bandeja + export PDF DomPDF, y el
botón/dialog frontend con verificación del DoD contra una mufa real de Tultitlán) sigue en
`#9990565` (backend, `pendiente_revision`) y `#9990566` (frontend, `pendiente_revision`),
pendientes de que una terminal los reclame.

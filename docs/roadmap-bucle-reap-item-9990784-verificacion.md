# Item #9990784 — Convenio prellenado desde plantilla — bucle reap sobre paraguas ya descompuesto

## Contexto

#9990784 (sub-item de #9990792, Expediente digital del colaborador — 9A) pedía generar el
Convenio Individual de Comisiones de un colaborador reusando el motor de plantillas existente de
Talento (`TalentoDocumentTemplate` + `TemplateVersionService` + slots de firma), no un generador
nuevo. El revisor lo escaló por el término "comisión" (falso positivo de denylist); el DES-TRABE
de Opus lo reaprobó (`tecnico_seguro`, item aditivo que reusa infraestructura existente); Irving
resolvió sus 4 preguntas estructuradas (todas con la opción recomendada).

## Qué ya se había hecho

Una vuelta previa (`wt-1`, 2026-09-11 23:17-23:33) ya hizo lo correcto: detectó que el propio
spec se contradice a sí mismo (pide que el contenido "refleje las reglas del reglamento aprobado
(#0)" pero `docs/reglamento-ventas-comisiones.md` **no existe** — confirmado por #9990775 — y
#9990790 sigue sin resolver esperando el texto fuente de Irving; el propio sibling #9990775 dejó
escrito que #9990784 "debe seguir bloqueado" hasta entonces), corrió `circuito:cabida` (NO CABE,
ya había timeouteado por `max_turns` sin commits) y descompuso el trabajo respetando esa
contradicción en 3 sub-items concretos (`origen_item_id=9990784`):

- **#9991021** — plantilla + placeholders + slots de firma (andamiaje técnico, **sin** contenido
  de negocio).
- **#9991022** — botón de generación en el expediente del colaborador (`depende_de` implícito de
  #9991021).
- **#9991023** — contenido real de las cláusulas (esquema/%/condiciones), **bloqueado a
  propósito** con `depende_de=[9990790]` hasta que exista el reglamento fuente.

Pero esa vuelta murió sin intentar **cerrar** al padre — el log solo registra `soltar-claim`
("La vuelta de wt-1 terminó sin cerrar el item (muerte del proceso: kill, OOM o freno a media
vuelta)"), y el item volvió a la cola como `aprobado_revisor` sin nadie liberando el trabajo real
— mismo síntoma que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/
#910/#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/
#9990892/#9990896/#9990886/#9990893/#9990878/#9990870 y el resto de la familia documentada en
`CLAUDE.md`. Irving volvió a aprobar el item (2026-09-11 23:28) y una segunda vuelta lo timeouteó
de nuevo por `max_turns` sin commits, hasta llegar reclamado a esta vuelta (`wt-1`,
2026-09-12 05:34).

## Verificación de esta vuelta

Estado real de los 3 hijos (`origen_item_id=9990784`), todos intactos y sin reclamar:

| Item | Título | Estado |
|------|--------|--------|
| #9991021 | Plantilla + placeholders + slots de firma (andamiaje) | `pendiente_revision` |
| #9991022 | Botón de generación en el expediente | `pendiente_revision` |
| #9991023 | Contenido real de las cláusulas — BLOQUEADO hasta #9990790 | `pendiente_revision` (`depende_de=[9990790]`) |

La descomposición original seguía siendo correcta y respeta la contradicción del spec (no se
inventó contenido de negocio); nadie más la tocó.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'`). El guard de
paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo reenrutó automáticamente:

```json
{
  "ts": "2026-09-11T23:34:38-06:00",
  "por": "consola:tinker",
  "flags": {"excluir_pool_automatico": {"antes": false, "despues": true}},
  "estado": "aprobado_irving",
  "decision": "flags"
}
```

Resultado: `estado_aprobacion=aprobado_irving`, `excluir_pool_automatico=true`,
`worker_sid=null`, `claimed_at=null` — sacándolo del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9991021, #9991022 y #9991023 cierren
los tres.

## Sin cambio de código de negocio

El trabajo técnico real (andamiaje de plantilla, botón en el expediente, contenido de cláusulas)
sigue en #9991021/#9991022 (`pendiente_revision`, listos para triaje) y #9991023 (bloqueado a
propósito por `depende_de=[9990790]` hasta que Irving entregue el texto fuente del reglamento).

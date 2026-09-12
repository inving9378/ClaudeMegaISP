# Item #9990947 — CIRC-05 pieza B: bucle reap sobre paraguas ya descompuesto

## Contexto

#9990947 (sub-item de seguimiento de #9990888, CIRC-05) pedía 4 endurecimientos pequeños y
aditivos sobre la creación externa de items del roadmap (límite de profundidad, auditoría con
scope, canal de respuesta obligatorio, deprecación documental de `comentarios_claude`). El
revisor lo escaló por tocar frontera de seguridad/contratos externos; el DES-TRABE de Opus armó
un brief de 4 preguntas que Irving aprobó (todas con la opción recomendada).

## Qué ya se había hecho

Una vuelta previa (`wt-1`, 2026-09-11 20:15) corrió `circuito:cabida` (NO CABE,
`ya_timeouteo_antes`) y descompuso el trabajo real en 4 sub-items concretos, con archivo/línea
exactos:

- **#9990958** (1/4) — Límite de profundidad en `RoadmapIntakeService::crear()`.
- **#9990959** (2/4) — Auditoría con etiqueta de scope en `audit()`.
- **#9990960** (3/4) — Anexar el bloque de canal de respuesta al prompt en la creación externa.
- **#9990961** (4/4) — Marcar `comentarios_claude` como deprecado en el manual servido.

Pero esa vuelta terminó sin intentar **cerrar** al padre ("Sin código propio en esta vuelta").
El log muestra que el reaper detectó el worker muerto/timeouteado con el item aún `en_progreso`
(26 minutos) y lo re-encoló a `aprobado_irving` (`reap_count=1`) — mismo síntoma que
#738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962
y el resto de la familia documentada en `CLAUDE.md`.

## Verificación de esta vuelta

`circuito:cabida 9990947` devolvió `CABE [ya_descompuesto]` — confirma que no hay que
re-descomponer. Estado real de los 4 hijos (`origen_item_id=9990947`):

| Item | Título | Estado |
|------|--------|--------|
| #9990958 | (1/4) Límite de profundidad | `completado` |
| #9990959 | (2/4) Auditoría con scope | `completado` |
| #9990960 | (3/4) Canal de respuesta en el prompt | `aprobado_irving` (sin reclamar) |
| #9990961 | (4/4) Deprecar `comentarios_claude` en el manual | `aprobado_revisor` (sin reclamar) |

La descomposición original seguía siendo correcta y el trabajo real ya avanzó (2 de 4 piezas
cerradas); nadie más la tocó.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'`). El guard de
paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo reenrutó automáticamente:

```json
{
  "ts": "2026-09-11T20:44:15-06:00",
  "por": "paraguas",
  "evento": "paraguas_abierto",
  "motivo": "Este item se descompuso y le quedan 2 sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el último de ellos cierre.",
  "subitems_abiertos": 2
}
```

Resultado: `estado_aprobacion=aprobado_irving`, `excluir_pool_automatico=true`,
`worker_sid=null`, `claimed_at=null` — sacándolo del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990960 y #9990961 cierren los dos.

## Sin cambio de código de negocio

El trabajo técnico real (límite de profundidad, scope de auditoría, canal de respuesta,
deprecación documental) sigue en #9990960 (`aprobado_irving`, listo para reclamarse) y #9990961
(`aprobado_revisor`, pendiente de que el revisor lo tríe), ambos sin dueño hoy.

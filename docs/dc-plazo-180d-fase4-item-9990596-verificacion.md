# DC plazo 180d hábiles — Fase 4 (alertas) — item #9990596 — verificación 2026-09-08

## Contexto

Item #9990596, sub-item de seguimiento de #9990576 (decisión de Irving en #9990550 q5). Su propio
spec establece una precondición explícita antes de implementar la Fase 4 (alertas por proximidad
al vencimiento del plazo de 180 días hábiles):

> "NO reintentar este sub-item hasta confirmar que al menos 1 empresa tiene `fecha_inicio_plazo`
> no nula (capturada por un usuario real desde la UI de Fase 3b)."

Las Fases 1-3 ya están mergeadas a `main`:
- Fase 1: columna `dc_empresas.fecha_inicio_plazo` (migración `2026_09_07_220000_add_fecha_inicio_plazo_a_dc_empresas.php`).
- Fase 2: cálculo de días hábiles en `CompletitudService`.
- Fase 3a: endpoint `PUT /documentacion-corporativa/api/empresa/plazo` en `ExpedienteController` (commit `4e0ae213`).
- Fase 3b: UI de captura + KPI en `DcExpediente.vue` (commits `0076d485`/`6a7ef615`).

## Verificación (esta vuelta, 2026-09-08)

```
DcEmpresa::count()                                  = 1
DcEmpresa::whereNotNull('fecha_inicio_plazo')->count() = 0
```

La única fila existente en `dc_empresas` en dev tiene `nombre = null` y `fecha_inicio_plazo = null`
— ni siquiera es una empresa real capturada (parece fila placeholder/semilla), mucho menos una con
el plazo capturado desde la UI de Fase 3b.

**La precondición del item sigue sin cumplirse.** Nadie ha capturado `fecha_inicio_plazo` para
ninguna empresa real todavía.

## Resolución

Sin cambio de código: implementar la Fase 4 (alertas por proximidad al vencimiento) ahora
construiría lógica de notificación sobre datos hipotéticos — exactamente lo que el propio item
prohíbe ("violando la regla de no anticipar sin necesidad real"). Se cierra este sub-item
documentando que la condición sigue sin cumplirse; cuando alguien capture `fecha_inicio_plazo`
para al menos una empresa real desde `/documentacion-corporativa`, se debe crear un nuevo item que
retome el plan ya definido en #9990576 (umbral configurable, notificación vía WhatsApp existente,
revisar alcance de `CompletitudService::responsables()`, job/schedule diario con guard
anti-duplicado).

## Dónde se captura la fecha (para cuando exista el dato real)

UI: `/documentacion-corporativa` → ficha de expediente de la empresa → campo "Fecha de inicio del
plazo" (Fase 3b, `DcExpediente.vue`). Endpoint: `PUT /documentacion-corporativa/api/empresa/plazo`
(`ExpedienteController::actualizarPlazo`).

# Verificación — Item #9990837: bucle reap sobre paraguas ya descompuesto

## Contexto

`#9990837` ("Fase 4 (#9990833): reporte de discrepancias SN captura-manual vs. OLT") es un
sub-item de seguimiento de `#9990833`. Mismo patrón documentado repetidamente en `CLAUDE.md`: un
item que ya fue correctamente descompuesto en sub-items por una vuelta previa, pero esa vuelta
murió sin intentar **cerrar** al padre, dejándolo colgado para que el pool lo repartiera de nuevo
sin trabajo propio pendiente.

## Cronología real (según el `log` del item)

1. `2026-09-11 15:44` — `wt-5` crea `#9990837` como sub-item de seguimiento de `#9990833`.
2. `2026-09-11 15:46` — Válvula de contexto lee "facturar" como MENCIÓN (no acción), pero la
   política retiene la frontera dura "dinero" aunque sea mención (decisión de Irving, #9990210) →
   nivel B.
3. `2026-09-11 15:48` — Revisor autoriza con confianza alta, pero la política de la Torre no
   permite ese nivel para autopilot → queda `requiere_irving`.
4. `2026-09-11 15:51` — Irving aprueba (`aprobado_irving`), resolviendo las 4 preguntas
   estructuradas (q1 recomendada: vista web + CSV; q4 recomendada: permiso nuevo
   `red.discrepancias.ver`).
5. `2026-09-11 17:05` — Timeout por `max_turns` sin commits en la rama → escalado a
   `requiere_irving` ("DES-TRABE (Opus)" marca ANTI-LOOP: el ejecutor ya lo corrió 1× sin
   ejecutarlo).
6. `2026-09-11 17:25` — Irving vuelve a aprobar.
7. `2026-09-11 17:29` — La vuelta de **`wt-2`** (una sesión previa) corre `circuito:cabida` (NO
   CABE) y descompone correctamente el trabajo en:
   - **`#9990849`** (Fase 4a: `DiscrepanciaSnService` con las 3 categorías exactas del spec
     original — no-normaliza / difiere-de-OLT / en-OLT-sin-cliente-activo —, endpoints paginados +
     export CSV + permiso `red.discrepancias.ver`, documentando la trampa de NO comparar contra
     `serie_equipo_norm` porque ese campo ya prioriza el valor de la OLT sobre la captura manual).
   - **`#9990850`** (Fase 4b: pantalla Vue con 3 tabs/secciones + botón exportar, depende de 4a).
   Deja registrada la decisión en `comentarios_claude` antes de descomponer.
8. La vuelta muere sin intentar cerrar al padre (`claim_liberado_al_morir_la_vuelta`, "muerte del
   proceso: kill, OOM o freno a media vuelta"). El item vuelve a la cola como `aprobado_revisor`.
9. `2026-09-11 23:30` — El pool reparte `#9990837` de nuevo, otra vez a `wt-2` (esta vuelta).

## Verificación de que la descomposición sigue vigente

```
9990849: estado_aprobacion=pendiente_revision worker_sid=NULL origen_item_id=9990837
9990850: estado_aprobacion=pendiente_revision worker_sid=NULL origen_item_id=9990837
```

Ambos sub-items siguen intactos, sin reclamar por ninguna otra terminal, con specs completos y
correctos (campos reales verificados contra migraciones — `client_additional_information.modem_sn`,
`olt_onus.sn`/`client_id`, la trampa de `serie_equipo_norm` ya documentada, el criterio exacto
`ClientMainInformation::STATE_ACTIVE`) — la descomposición original seguía siendo correcta, nadie
más la tocó.

## Corrección aplicada

Se ejecutó el intento de cierre faltante:

```php
$i = RoadmapItem::find(9990837);
$i->estado_aprobacion = "completado";
$i->save();
```

El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detectó y lo
reenrutó automáticamente:

```
estado_aprobacion=aprobado_irving excluir_pool_automatico=true
worker_sid=NULL claimed_at=NULL
```

## Efecto

`#9990837` queda fuera del pool automático y del reaper (no vuelve a repartirse sin trabajo propio
pendiente) hasta que `#9990849` y `#9990850` cierren ambos. El hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo completará solo en ese momento.

## Sin cambio de código de negocio

El trabajo técnico real (servicio de discrepancias SN + endpoints + permiso + pantalla con 3 tabs)
sigue en `#9990849`/`#9990850`, pendientes de que una terminal los reclame.

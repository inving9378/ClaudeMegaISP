# Item #9990938 — bucle reap sobre paraguas ya descompuesto (Fase 3a-i depende_de)

## Contexto

`#9990938` (sub-item de `#9990923`) pedía que `SupervisorService::listosParaTerminal()` y
`::listosParaTerminalTotal()` (`app/Modules/Addons/Roadmap/Services/SupervisorService.php`,
líneas 137-163) dejen de mostrar/contar items con `depende_de` sin resolver, reusando el criterio
MR-36 ya existente en `RoadmapCircuitoService` (`dependenciasCerradas()` /
`estaCerradoParaDependencia()`).

## Qué encontró esta vuelta

Una vuelta previa (`wt-5`, 2026-09-11 19:25) ya hizo lo correcto:

1. Corrió `circuito:cabida 9990938` → NO CABE (`histórico_excede_umbral`).
2. Descompuso el trabajo en dos fases secuenciales:
   - **#9990943** — Fase A: método público `filtrarConDependenciasCerradas()` en
     `RoadmapCircuitoService` (dueño del criterio MR-36, reusable por lote).
   - **#9990944** — Fase B: consumidor — wire en `SupervisorService::listosParaTerminal()`/
     `::listosParaTerminalTotal()`, con `depende_de=[9990943]` para no ejecutarse antes de tiempo.
3. Terminó sin código propio en esa vuelta.

Pero esa vuelta **nunca intentó cerrar** al padre (`#9990938`) — quedó `en_progreso` colgado. El
reaper lo detectó huérfano y lo re-encoló a `aprobado_revisor` (`reap_count=1`), y entre medio el
item quedó atrapado además en una racha larga de eventos `limite_cuenta_detectado` (la cuenta de
Claude sin límite de sesión, ~90 reintentos entre 19:52 y 20:09) más dos `soltar-claim` por muerte
de proceso (`wt-2`, `wt-1`, `wt-4`) — ninguno de esos ciclos intentó el cierre, solo lo devolvieron
a la cola sin trabajo propio pendiente. Mismo síntoma que #738/#745/#830/#816/#818/#848/#852/#905/
#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/
#9990807/#9990826/#9990836/#9990856/#9990892/#9990896/#9990886/#9990893/#9990923.

## Verificación

Consultados ambos hijos directamente en la BD antes de tocar nada:

- `#9990943` — `estado_aprobacion=completado`, sin `worker_sid` (mergeado; es la Fase A, dueño del
  criterio reusable).
- `#9990944` — `estado_aprobacion=en_progreso`, reclamado por `wt-2` (**con dueño, no se toca** —
  regla "un item = un dueño"), `depende_de=[9990943]` (correcto: su dependencia ya cerró).

Ambos intactos, `origen_item_id=9990938` — la descomposición original seguía siendo correcta,
nadie más la tocó.

## Corrección

Se ejecutó el intento de cierre faltante:

```php
$i = RoadmapItem::find(9990938);
$i->estado_aprobacion = 'completado';
$i->save();
```

El guard de paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenrutó automáticamente a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log),
liberando `worker_sid`/`claimed_at`. Queda fuera del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando `#9990944` cierre (su única
dependencia, `#9990943`, ya está mergeada).

## Resultado

**Sin cambio de código de negocio** — el trabajo técnico real (wire del criterio MR-36 en
`SupervisorService`) sigue en `#9990944`, activamente reclamado por `wt-2`.

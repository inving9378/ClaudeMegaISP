# Item #9990923 — bucle reap sobre paraguas ya descompuesto (Fase 3a SupervisorService)

## Contexto

`#9990923` (sub-item de `#9990893`) pedía que `SupervisorService::listosParaTerminal()` y
`::listosParaTerminalTotal()` excluyan items con `depende_de` sin resolver (reusando el mismo
criterio MR-36 de `RoadmapCircuitoService`) y con `motivo_espera` activo.

## Qué encontró esta vuelta

Una vuelta previa (`wt-3`, 2026-09-11 19:19) ya hizo lo correcto:

1. Corrió `circuito:cabida 9990923` → NO CABE (`historico_excede_umbral`).
2. Descompuso el trabajo en:
   - **#9990938** — Fase 3a-i: regla `depende_de` (expone `filtrarConDependenciasCerradas()` en
     `RoadmapCircuitoService`, reusando `estaCerradoParaDependencia()` de MR-36).
   - **#9990939** — Fase 3a-ii: regla `motivo_espera` (`whereNull` mecánico).
   - Regla (c) del padre (heurística de texto) quedó fuera a propósito — ya tenía sub-item
     hermano dedicado.
3. Terminó sin código propio en esa vuelta.

Pero esa vuelta **nunca intentó cerrar** al padre (`#9990923`) — quedó `en_progreso` colgado con
su `worker_sid`. El reaper lo detectó huérfano (worker muerto/timeout, 26 minutos) y lo
re-encoló a `aprobado_revisor` (`reap_count=1`). El pool lo repartió de nuevo (a `wt-4`, esta
vuelta) sin que hubiera trabajo propio pendiente — mismo síntoma que #738/#745/#830/#816/#818/
#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962/#9990554/#9990549/
#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/#9990892/#9990896/#9990886/#9990893.

## Verificación

Consultados ambos hijos directamente en la BD antes de tocar nada:

- `#9990938` — `en_progreso`, reclamado por `wt-5` (**con dueño, no se toca** — regla "un item =
  un dueño").
- `#9990939` — `aprobado_revisor`, sin reclamar.

Ambos intactos, `origen_item_id=9990923` — la descomposición original seguía siendo correcta,
nadie más la tocó.

## Corrección

Se ejecutó el intento de cierre faltante:

```php
$i = RoadmapItem::find(9990923);
$i->estado_aprobacion = 'completado';
$i->save();
```

El guard de paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenrutó automáticamente a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le
quedan 2 sub-item(s) abierto(s)"), liberando `worker_sid`/`claimed_at`. Queda fuera del
pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo
cuando `#9990938` y `#9990939` cierren los dos.

## Resultado

**Sin cambio de código de negocio** — el trabajo técnico real (filtrado de `depende_de` y
`motivo_espera` en `SupervisorService`) sigue en `#9990938`/`#9990939`, pendientes de que sus
dueños los cierren.

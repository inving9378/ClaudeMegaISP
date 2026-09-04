# Item #9990269 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

Mismo patrón documentado repetidamente en `CLAUDE.md` (familia #738/#745/#830/#816/#818/#848/#852/
#905/#878/#906/#907/#924/#9990012/#917): una vuelta previa (`wt-6`, 2026-09-04 09:17) evaluó
correctamente el item, encontró que NO CABÍA en una vuelta (histórico ~573s) y además que su
bloqueante real (Fase 1b-ii, #9990267 = detección de ciclos vía `DependenciaGate::tieneCiclo()`)
seguía sin mergear a `main` — solo 1b-i (`--depende-de`+`position`, #9990266) ya estaba mergeado.

Descompuso el trabajo en:
- **#9990280** — Fase 1c-i-a: tests de camino feliz, posición inválida y regresión (sin ciclo).
  **Ejecutable ya** (no depende de 1b-ii).
- **#9990281** — Fase 1c-i-b: test de ciclo. **Bloqueado** hasta que 1b-ii mergee.

Pero esa vuelta nunca intentó **cerrar** al padre #9990269 — el guard de paraguas del modelo
(`RoadmapItem.php`, bloque "(2b) PARAGUAS") solo aparca un item descompuesto (`aprobado_irving` +
`excluir_pool_automatico`) cuando algo intenta activamente `estado_aprobacion = 'completado'` y
detecta hijos abiertos. Sin ese intento, #9990269 se quedó en `en_progreso` sin nadie liberando el
claim; el reaper rápido lo vio con el slot `wt-6` libre y lo re-encoló a `aprobado_revisor`
(`reap_count=1`), y el pool lo repartió de nuevo sin que hubiera trabajo propio que hacer.

## Verificación (esta vuelta)

- `#9990280`: `estado_aprobacion=aprobado_revisor`, `worker_sid=` (vacío), `origen_item_id=9990269`
  — intacto, sin reclamar.
- `#9990281`: `estado_aprobacion=aprobado_revisor`, `worker_sid=` (vacío), `origen_item_id=9990269`
  — intacto, sin reclamar.

La descomposición original seguía siendo correcta; nadie más la tocó.

## Corrección

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'`). El guard de paraguas
lo reenrutó automáticamente:

```
estado_aprobacion final: aprobado_irving
excluir_pool_automatico: true
```

Log añadido:

```json
{"por":"paraguas","evento":"paraguas_abierto",
 "motivo":"Este item se descompuso y le quedan 2 sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el último de ellos cierre.",
 "subitems_abiertos":2}
```

Esto saca a #9990269 del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #9990280 y #9990281 cierren.

## Sin cambio de código de negocio

El trabajo técnico real (tests de integración de `SubItemCommand --depende-de`) sigue en #9990280
(ejecutable ya) y #9990281 (bloqueado hasta que la Fase 1b-ii, #9990267/#9990278/#9990279, mergee
la detección de ciclos).

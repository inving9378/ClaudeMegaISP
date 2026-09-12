# Item #9990892 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

`#9990892` ("Fase 2 (C1) — exentar documentación del detector de colisiones en vuelo +
simulación histórica") es sub-item de seguimiento de `#9990863` (Fase 1). El propio item exige,
como precondición, no tocar código hasta confirmar con evidencia real (`>=15 min` acumulados en
`storage/logs/circuito-despacho-*.log`, canal `circuito_despacho`) que "colisión" es causa
frecuente de terminales ociosas.

## Qué encontró la vuelta anterior (18:46, `wt-6`)

Antes de picar código corrió `circuito:cabida 9990892`, que devolvió **NO CABE** (`histórico`).
Investigó la precondición y confirmó que el canal `circuito_despacho` (recién mergeado por
`#9990863` Fase 1, ~5 min antes) **aún no tenía ningún archivo de log** — la precondición del
propio item ("`>=15 min` reales acumulados") no se cumplía todavía. En vez de picar código a
ciegas, descompuso el trabajo en 3 sub-items (`origen_item_id=9990892`):

- **#9990896** — Fase 2a: confirmar con evidencia real si colisión es causa frecuente de
  terminales ociosas.
- **#9990897** — Fase 2b: implementar la exención de docs en `footprintDeRama()`/
  `footprintEnVivo()`.
- **#9990898** — Fase 2c: simulación histórica antes de activar la exención de docs.

Pero esa vuelta **nunca intentó cerrar** al padre. El log de `#9990892` solo registra el evento
`reaper` (`huerfano_reencolado`, "worker murió/timeout con el item en en_progreso (hace 27
minutos)", `reap_count=1`) — el proceso murió/timeouteó antes de ejecutar el intento de cierre
que dispara el guard de paraguas, y el reaper lo devolvió a `aprobado_revisor`. El pool lo
repartió de nuevo (esta vuelta, `wt-6`) sin que hubiera trabajo propio pendiente: la
descomposición ya existía.

## Verificación de esta vuelta

Confirmado contra la BD real que los 3 sub-items siguen intactos y ninguno fue tocado por otra
sesión:

| id | título | estado_aprobacion | worker_sid | notas |
|----|--------|-------------------|------------|-------|
| #9990896 | Fase 2a — confirmar evidencia real | `aprobado_irving` | — | **Ya resuelto en una vuelta posterior** (ver `docs/roadmap-bucle-reap-item-9990896-verificacion.md`): mismo bucle reap, un nivel más abajo — sigue bloqueado por falta de tiempo real, parqueado como paraguas propio con su hijo **#9990916** (Fase 2a reintento) abierto. |
| #9990897 | Fase 2b — implementar exención de docs | `aprobado_revisor` | — | Sin reclamar, listo para tomarse. |
| #9990898 | Fase 2c — simulación histórica antes de activar | `aprobado_irving` | — | Sin reclamar. |

`circuito:cabida 9990892 --sid=wt-6` en esta vuelta devolvió **`CABE [ya_descompuesto]`** —
confirma que no hay que re-descomponer, no que haya trabajo nuevo.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'`) sobre `#9990892`.
El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo reenrutó
automáticamente:

```
evento: paraguas_abierto
motivo: "Este item se descompuso y le quedan 2 sub-item(s) abierto(s): no se completa.
         Queda como paraguas y cierra solo cuando el último de ellos cierre."
estado final: aprobado_irving + excluir_pool_automatico=true
```

(El conteo de 2 en vez de 3 es coherente con que `#9990896` ya está también parqueado como
paraguas de su propio hijo — el guard cuenta abiertos según su propio criterio interno, no se
tocó esa lógica.)

Con esto `#9990892` sale del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando `#9990897` y `#9990898` (y, en cascada,
`#9990896`→`#9990916`) cierren.

## Conclusión

Mismo patrón documentado repetidamente en `CLAUDE.md` (familia de items #738/#745/#830/#816/
#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962/#9990554/#9990549/
#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/#9990896): la descomposición previa era
correcta, solo faltaba el acto de intentar cerrar al padre para que el guard automático lo
parqueara y dejara de generar ciclos ociosos de reaper→pool→timeout.

**Sin cambio de código de aplicación** — el trabajo técnico real (implementar la exención de
docs en el detector de colisiones y validarla con simulación histórica) sigue en
`#9990897`/`#9990898`, y la precondición de evidencia sigue en `#9990896`→`#9990916`.

# Item #9990886 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

`#9990886` ("CIRC-03: Bandeja de decisiones real — separar espera decisión de espera insumo
material") es sub-item de seguimiento de `#9990874`, y depende de `#9990881` (CIRC-01).

## Qué encontró la vuelta anterior (18:50, `wt-6`)

Antes de picar código corrió `circuito:cabida 9990886`, que devolvió **NO CABE**. Descompuso el
trabajo en 3 fases secuenciales (`origen_item_id=9990886`):

- **#9990904** — Fase A: migración aditiva `motivo_espera` en `roadmap_items`.
- **#9990905** — Fase B: clasificar los 8 items conocidos con `motivo_espera` + barrido de más
  candidatos.
- **#9990906** — Fase C: Torre — separar la bandeja en "Esperan tu decisión" vs. "Esperan un
  insumo tuyo" + ajustar métricas.

Pero esa vuelta **nunca intentó cerrar** al padre. El log de `#9990886` solo registra el evento
`reaper` (`huerfano_reencolado`, "worker murió/timeout con el item en en_progreso (hace 25
minutos)", `reap_count=1`) — el proceso murió/timeouteó antes de ejecutar el intento de cierre
que dispara el guard de paraguas, y el reaper lo devolvió a `aprobado_revisor`. El pool lo
repartió de nuevo (esta vuelta, `wt-3`) sin que hubiera trabajo propio pendiente: la
descomposición ya existía y, de hecho, ya avanzó bastante.

## Verificación de esta vuelta

Confirmado contra la BD real:

| id | título | estado_aprobacion | worker_sid | notas |
|----|--------|-------------------|------------|-------|
| #9990904 | Fase A — migración aditiva `motivo_espera` | `aprobado_irving` | — | Ya cerrada en código (branch mergeado, `merge_commit=a94fd956`) pero **parqueada como paraguas propio**: se descompuso a su vez en #9990914 (`completado`) y #9990915 (`en_progreso`, reclamado por `wt-4` — no se toca, tiene dueño). |
| #9990905 | Fase B — clasificar los 8 items + barrido | `completado` | — | Cerrada y mergeada (`merge_commit=ea6705a8`, ver `git log`: "Integra circuito #9990905 ... a main"). |
| #9990906 | Fase C — Torre: 2 listas + métricas | `aprobado_irving` | — | Sin reclamar, sin sub-items propios, lista para tomarse. |

`circuito:cabida 9990886 --sid=wt-3` no fue necesario re-correr: la descomposición ya existía y
uno de los 3 hijos incluso ya cerró — es evidencia directa de que el trabajo sigue avanzando por
los sub-items correctos.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'`) sobre `#9990886`.
El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo reenrutó
automáticamente:

```
evento: paraguas_abierto
motivo: "Este item se descompuso y le quedan 2 sub-item(s) abierto(s): no se completa.
         Queda como paraguas y cierra solo cuando el último de ellos cierre."
estado final: aprobado_irving + excluir_pool_automatico=true, worker_sid liberado
```

Los 2 abiertos son `#9990904` (paraguas propio, pendiente de que cierre `#9990915`) y `#9990906`
(sin reclamar). Con esto `#9990886` sale del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando ambos cierren (y, en cascada,
`#9990904`→`#9990915`).

## Conclusión

Mismo patrón documentado repetidamente en `CLAUDE.md` (familia de items #738/#745/#830/#816/
#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/#962/#9990554/#9990549/
#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/#9990892/#9990896): la descomposición
previa era correcta, solo faltaba el acto de intentar cerrar al padre para que el guard
automático lo parqueara y dejara de generar ciclos ociosos de reaper→pool→timeout.

**Sin cambio de código de negocio** — el trabajo real de CIRC-03 (exponer `motivo_espera` en el
modelo con verificación e2e, y la Torre con las 2 listas separadas) sigue en
`#9990915`/`#9990906`, pendientes de que sus dueños los cierren.

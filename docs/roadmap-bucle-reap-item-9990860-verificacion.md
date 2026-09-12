# Item #9990860 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

`#9990860` ("Bandeja de decisiones: distinguir el item que espera una decisión del que
espera un insumo material de Irving") es el mismo patrón documentado repetidas veces en
`CLAUDE.md` (familia #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/
#917/#910/#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/
#9990856/#9990896): un item paraguas correctamente descompuesto por una vuelta previa, que
nunca llegó a intentar su propio cierre, y que el reaper de huérfanos re-encoló una y otra vez
sin que hubiera trabajo propio pendiente.

Fue escalado por el Revisor (falso positivo por la palabra "factura" en la denylist propia,
no en la frontera dura de Jarvis) y desatascado por el DES-TRABE (Opus), categoría
`tecnico_seguro`. Irving aprobó la Opción 1 recomendada (`q1`, hash `e355da336dce6f50`):
autorizar el plan completo (migración aditiva `motivo_espera` + split visual de la bandeja en
la Torre + clasificar los 8 items nombrados + barrido con tope de 15).

## Lo que ya hizo la vuelta previa (wt-2, 2026-09-11 18:53)

Corrió `circuito:cabida` (implícito por el log de decisión) y descompuso el trabajo en 4 fases,
cada una como sub-item propio (`origen_item_id=9990860`):

- **#9990910** — migración aditiva `motivo_espera` en `roadmap_items` + proyección en
  `COLUMNAS_BANDEJA`. Estado: `aprobado_irving`, sin reclamar.
- **#9990911** — backfill de `motivo_espera` para los 10 items ya identificados por Irving.
  Estado: `aprobado_irving`, sin reclamar.
- **#9990912** — Torre: bandeja partida en dos listas ("Esperan tu decisión" / "Esperan un
  insumo"). Estado: `aprobado_revisor`, sin reclamar.
- **#9990913** — barrido de `aprobado_irving` por texto de bloqueo (candidatos adicionales a
  `motivo_espera`, tope 15). Estado: `aprobado_irving`, sin reclamar.

Pero esa vuelta nunca intentó **cerrar** el padre — el log solo muestra la decisión de
descomponer, y el reaper (`reaper-rapido`) detectó el slot `wt-2` libre y re-encoló #9990860
a `aprobado_irving` (`reap_count=1`, evento `huerfano_reencolado`). El pool lo repartió de
nuevo (a `wt-4`, esta vuelta) sin que hubiera trabajo propio que hacer.

## Verificación de esta vuelta

- `circuito:cabida 9990860 --sid=wt-4` → `CABE [ya_descompuesto]` (confirma que la
  descomposición ya existe y no hay que rehacerla).
- Los 4 hijos (`origen_item_id=9990860`) siguen intactos, ninguno reclamado
  (`worker_sid` vacío) — la descomposición original seguía siendo correcta, nadie más la
  tocó.

## Corrección

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'`). El guard de
paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo reenrutó automáticamente:

```
estado_aprobacion: aprobado_irving (no completado)
excluir_pool_automatico: true
worker_sid: null
```

Log agregado:

```json
{
  "evento": "paraguas_abierto",
  "motivo": "Este item se descompuso y le quedan 4 sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el último de ellos cierre.",
  "subitems_abiertos": 4
}
```

`#9990860` queda fuera del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo, cuando #9990910, #9990911, #9990912 y #9990913
cierren los cuatro.

## Sin cambio de código de negocio

El trabajo técnico real (migración `motivo_espera`, backfill de los 10 items, split de la
bandeja en la Torre, barrido de candidatos adicionales) sigue en
#9990910/#9990911/#9990912/#9990913, pendientes de que una terminal los reclame.

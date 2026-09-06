# Item #910 — bucle reap sobre paraguas ya descompuesto (Torre 24/7 Pieza 5d)

## Contexto

#910 pide medir honestamente la ocupación de la flota antes/después y el ratio señal/ruido
del barrido (Pieza 5d de #904), pero **depende** de que las Piezas 5a (#907), 5b (#908) y 5c
(#909) estén mergeadas a `main` y corriendo un tiempo. El propio spec del item es explícito:
"si alguna sigue abierta, esta pieza espera... no forzar".

## Historial de espera legítima

El item pasó por varias vueltas (wt-3, wt-2, wt-1) que correctamente verificaron dependencias
y **NO ejecutaron** porque 5b/5c seguían sin mergear o incompletas, liberando el claim y
reagendando cada vez (`agendado_para` +6h/+12h/+24h). Esto es exactamente el comportamiento que
pide el spec — no es el bug.

## El bug real: descomposición sin intento de cierre

El 2026-09-06 06:12 un timeout por `max_turns` (sin commits) escaló el item a
`requiere_irving`. El mecanismo "DES-TRABE (Opus)" lo re-aprobó a las 06:16 señalando
"ANTI-LOOP: el ejecutor ya corrió este item 5× y NO lo ejecutó" y pidiendo re-especificarlo o
ejecutarlo.

Una vuelta siguiente (wt-1, 2026-09-06 06:20) hizo lo correcto: reverificó que #907/#908/#909
YA estaban `completado`+mergeados (908 vía sus sub-items #985/#986/#987) desde 2026-09-03
~17:55, y — sin tocar código — descompuso la medición en 2 sub-items secuenciales:

- **#9990393** — inventario de items del barrido (#908) + snapshots de ocupación actuales
  (documentando que no hay snapshot histórico "antes" persistido).
- **#9990394** — ratio útil/ruido del barrido + reporte final honesto y cierre de #910.

Pero el proceso **murió a media escritura** del comentario de decisión (el texto en
`comentarios_claude` queda cortado en "...ratio util/ruido" sin cerrar la oración) — nunca
llegó a intentar el `estado_aprobacion = 'completado'` que dispara el guard de paraguas. El
log solo registra `claim_liberado_al_morir_la_vuelta` ("La vuelta de wt-1 terminó sin cerrar el
item: muerte del proceso"), y el pool lo repartió de nuevo sin trabajo propio que hacer — mismo
síntoma que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917.

## Verificación de esta vuelta

Confirmado que #9990393 y #9990394 existen intactos, `pendiente_revision`, `worker_sid` vacío
(nadie los reclamó todavía) y `origen_item_id=910` — la descomposición original seguía siendo
correcta, nadie más la tocó:

```
9990393 estado=pendiente_revision worker_sid=
9990394 estado=pendiente_revision worker_sid=
```

`circuito:cabida 910` devolvió `CABE [ya_descompuesto]`.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'`). El guard de
paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detectó y reenrutó automáticamente:

```json
{"ts":"2026-09-06T06:21:40-06:00","por":"paraguas","evento":"paraguas_abierto",
 "motivo":"Este item se descompuso y le quedan 2 sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el último de ellos cierre.",
 "subitems_abiertos":2}
```

Resultado: `estado_aprobacion=aprobado_irving`, `excluir_pool_automatico=true`. #910 queda
fuera del pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo
complete solo cuando #9990393 y #9990394 cierren.

## Sin cambio de código de negocio

El trabajo técnico real (inventario del barrido, snapshots de ocupación, ratio útil/ruido,
reporte final) sigue en #9990393 y #9990394, pendientes de que una terminal los reclame.

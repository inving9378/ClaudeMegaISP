# Item #9990605 — Fase 1 puente Vendedores→Talento (comisiones espejo): bucle reap sobre paraguas ya descompuesto

## Contexto

`#9990605` ("Fase 1 — Puente de escritura en paralelo Vendedores→Talento, comisiones espejo, sin
tocar el pago vivo") es la misma familia de bug documentada repetidamente en `CLAUDE.md` (#738,
#745, #830, #816, #818, #848, #852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936,
#9990408, #962, #9990549, #9990554, entre otros): un item se descompone correctamente en
sub-items, pero nadie ejecuta el intento de cierre que dispara el guard de paraguas
(`RoadmapItem.php` bloque "(2b) PARAGUAS"), así que el item se queda `en_progreso` colgado hasta
que el reaper/soltar-claim lo re-encola y el pool lo vuelve a repartir sin que haya trabajo propio
que hacer.

## Lo que ya se hizo bien (sesión previa)

El item nació como sub-item de seguimiento de `#9990452` (migración de comisiones de Vendedores a
Talento). Tocó la frontera dura de dinero (válvula: `termino=dinero`, `veredicto=accion`) y fue
escalado a Irving vía DES-TRABE (Opus) con un brief de 5 preguntas estructuradas (mecanismo del
puente, estrategia de esquema, control de activación, validación del espejo, alcance histórico).
Irving aprobó las 5 el 2026-09-08 10:36:45, todas con la opción recomendada (Opción 1 en cada una:
Observer/listener aditivo, tablas nuevas espejo, feature flag en config, comando de reconciliación
diaria, forward-only sin backfill).

La vuelta `wt-1` (2026-09-08 10:39, antes de esta) corrió `circuito:cabida` (NO CABE, histórico
~3472s) y descompuso el trabajo respetando esas 5 decisiones (`origen_item_id=9990605`):

- **#9990609** — "Fase 1a — Esquema: tabla espejo `talento_comisiones_espejo` + flag config" →
  `pendiente_revision`, sin reclamar. Nivel B, inerte (solo esquema + flag OFF).
- **#9990610** — "Fase 1b — Observer que escribe el espejo tras cada comisión de vendedor +
  resolución de calendario a PayWeek" → `requiere_irving`, sin reclamar. Nivel C (consultar a
  Thomas antes de escribir contra datos reales, por la desalineación de calendarios Domingo→Sábado
  vs. PayWeek Sáb 18:00→Sáb 18:00 que el propio item señala como riesgo a resolver primero).
- **#9990611** — "Fase 1c — Comando de reconciliación diaria Vendedores vs Talento (espejo de
  comisiones)" → `pendiente_revision`, sin reclamar. Nivel B, solo-lectura.

## Por qué se quedó colgado

Esa vuelta escribió la decisión de descomposición en `comentarios_claude`, pero el proceso murió a
media escritura del comentario final ("Nota de diseño: el q2 de Irving e" — texto cortado) antes de
intentar el cierre del padre. El log lo confirma: el evento inmediato siguiente es
`soltar-claim`/`claim_liberado_al_morir_la_vuelta` ("La vuelta de wt-1 terminó sin cerrar el item...
Se libera el reclamo y vuelve a la cola como aprobado_revisor"). Sin un intento de
`estado_aprobacion='completado'` de por medio, el guard de paraguas nunca se disparó — el item
volvió a `aprobado_revisor`, fue reclamado de nuevo (`wt-1`, esta vuelta) sin que quedara trabajo
propio pendiente.

## Verificación del estado real (esta vuelta)

```
9990605 (yo) | en_progreso     | excl=false | sid=wt-1
  9990609    | pendiente_revision | sin reclamar (Fase 1a: esquema + flag)
  9990610    | requiere_irving    | sin reclamar (Fase 1b: observer + calendario, nivel C)
  9990611    | pendiente_revision | sin reclamar (Fase 1c: reconciliación diaria)
```

Los 3 hijos (`origen_item_id=9990605`) están intactos, ninguno tocado por esta vuelta — la
descomposición original sigue siendo correcta y cubre las 5 decisiones ya aprobadas por Irving
(q1-q5).

## Corrección aplicada

Se ejecuta el intento de cierre faltante sobre `#9990605` (`estado_aprobacion='completado'`). El
guard de paraguas del modelo lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (log
`paraguas_abierto`, "le quedan 3 sub-item(s) abierto(s)"), liberando `worker_sid`/`claimed_at` y
sacándolo del pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo
complete solo cuando #9990609, #9990610 y #9990611 cierren los tres.

**Sin cambio de código de negocio.** El trabajo real del puente Vendedores→Talento (esquema espejo,
observer con resolución de calendario, comando de reconciliación) sigue en
#9990609/#9990610/#9990611, pendientes de reclamo/aprobación. #9990610 en particular sigue
requiriendo, antes de escribir el primer registro contra datos reales, la consulta a Thomas que el
propio item padre ya exigía (nivel C, frontera de dinero).

# Item #9990554 — MR-17 Fase 3 (endpoint + botón "¿Quién depende de esto?" + panel de impacto): bucle reap sobre paraguas ya descompuesto

## Contexto

`#9990554` ("MR-17 Fase 3 — Endpoint + boton Quien depende de esto + panel de impacto + verificar
DoD") es la misma familia de bug documentada repetidamente en `CLAUDE.md` (#738, #745, #830, #816,
#818, #848, #852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936, #9990408, #962,
#9990549, entre otros): un item se descompone correctamente en sub-items, pero nadie ejecuta el
intento de cierre que dispara el guard de paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS"), así
que el item se queda `en_progreso` colgado hasta que el reaper lo re-encola y el pool lo vuelve a
repartir sin que haya trabajo propio que hacer.

## Lo que ya se hizo bien (sesión previa)

El item nació como sub-item de seguimiento de `#953` (DoD del padre: cortar virtualmente el
troncal `T-TULTITLAN-FO96-1` debe devolver clientes + monto mensual en riesgo). Fue escalado
inicialmente por el DES-TRABE de Opus ("ANTI-LOOP: el ejecutor ya corrió este item 2× y NO lo
ejecutó") por tener un brief con 3 preguntas estructuradas (implementación del endpoint+panel,
alcance de "dependientes" a mostrar, y cómo verificar el DoD). Irving aprobó las 3 preguntas el
2026-09-07 22:33, todas con la opción recomendada (endpoint BFS descendente + panel lateral
jerárquico; nodos hijos + clientes + servicios con totales; verificación por checklist manual).

La vuelta `wt-3` (2026-09-07 22:37) investigó a fondo el código real (no solo el spec: confirmó
que `RedGraphService::fanOutDesde` ya soporta cable/hilo/puerto/splitter/nap/mufa, Fase 1 #9990552,
y que `MapaRedEnlaceMrrService::calcular` ya resuelve cliente+MRR, Fase 2 #9990553) y descompuso
correctamente el trabajo (`origen_item_id=9990554`):

- **#9990593** — "MR-17 Fase 3a — Endpoint GET /mapa-red/api/impacto (backend)" → `requiere_irving`,
  sin reclamar.
- **#9990594** — "MR-17 Fase 3b — Boton '¿Quien depende de esto?' + panel de impacto (frontend)" →
  `en_progreso` en `wt-2` (tiene dueño — no se toca, aislamiento #334).
- **#9990595** — "MR-17 Fase 3c — Verificar DoD del item padre #953 con datos sinteticos" →
  `requiere_irving`, sin reclamar.

## Por qué se quedó colgado

Justo después de escribir la decisión de descomposición en `comentarios_claude`, esa vuelta
terminó sin intentar el cierre del padre ("Sin codigo propio en esta vuelta"). El log lo confirma:
el único evento posterior es `reaper-rapido` ("el slot wt-3 está libre... reclamo huérfano →
re-encolado, intento 1/3, vuelve a aprobado_irving"). Sin un intento de
`estado_aprobacion='completado'` de por medio, el guard de paraguas nunca se disparó — el item
terminó reclamado de nuevo (`wt-3`, esta vuelta) sin que quedara trabajo propio pendiente.

## Verificación del estado real (esta vuelta)

```
9990554 (yo) | en_progreso     | excl=false | sid=wt-3
  9990593    | requiere_irving | sin reclamar (Fase 3a: endpoint backend)
  9990594    | en_progreso     | wt-2        (Fase 3b: botón + panel frontend — con dueño)
  9990595    | requiere_irving | sin reclamar (Fase 3c: verificar DoD)
```

Los 3 hijos (`origen_item_id=9990554`) están intactos, ninguno tocado por esta vuelta — la
descomposición original sigue siendo correcta.

## Corrección aplicada

Se ejecuta el intento de cierre faltante sobre `#9990554` (`estado_aprobacion='completado'`). El
guard de paraguas del modelo lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (log
`paraguas_abierto`, "le quedan 3 sub-item(s) abierto(s)"), liberando `worker_sid`/`claimed_at` y
sacándolo del pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo
complete solo cuando #9990593, #9990594 y #9990595 cierren los tres.

**Sin cambio de código de negocio.** El trabajo real de MR-17 Fase 3 (endpoint de impacto, botón +
panel jerárquico en `ElementSidePanel.vue`, y verificación del DoD del padre #953 con datos
reales o sintéticos) sigue en #9990593/#9990594/#9990595, pendientes de aprobación/reclamo.

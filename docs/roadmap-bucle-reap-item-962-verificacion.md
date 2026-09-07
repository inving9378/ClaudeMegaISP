# Item #962 — MR-26 Cobertura comercial derivada de la infraestructura: bucle reap sobre paraguas ya descompuesto

## Contexto

`#962` ("MR-26 — Cobertura comercial derivada de la infraestructura") es la misma familia de bug
documentada repetidamente en `CLAUDE.md` (#738, #745, #830, #816, #818, #848, #852, #905, #878,
#906, #907, #924, #9990012, #917, #910, #936, #9990408, entre otros): un item se descompone
correctamente en sub-items, pero nadie ejecuta el intento de cierre que dispara el guard de
paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS"), así que el item se queda `en_progreso`
colgado hasta que el reaper lo re-encola y el pool lo vuelve a repartir sin que haya trabajo
propio que hacer.

## Lo que ya se hizo bien (sesión previa)

Una vuelta anterior descompuso correctamente el trabajo en 4 sub-items directos
(`origen_item_id=962`), siguiendo las 3 piezas del prompt original (motor de cobertura, endpoint
de consulta, capa de sectores inalámbricos) más una fase de UI/DoD final:

- **#9990522** — "Fase 1 — motor de cobertura: NAPs con puertos libres, radio configurable, capa
  GeoJSON en vivo" → `completado`, merge `9681a91f36a3b895fc3fc3f56291bd802642c740`.
- **#9990523** — "Fase 2 — endpoint de consulta de cobertura por coordenada + NAP más cercana" →
  `requiere_irving`, sin reclamar.
- **#9990524** — "Fase 3 — capa de sectores inalámbricos (azimut/apertura/alcance/altura),
  importable, sin simulador" → `requiere_irving`, sin reclamar.
- **#9990525** — "Fase 4 — UI: capa Cobertura comercial + capa Sectores inalámbricos + buscador de
  dirección, DoD final" → `en_progreso`, reclamado por `wt-3`.

## Historial del propio #962 (por qué se quedó colgado)

El log del item muestra el ciclo completo: un `DES-TRABE (Opus)` lo escaló por anti-loop
(2026-09-06), Irving lo re-aprobó y **quitó `excluir_pool_automatico`** como parte de la directiva
`destapado_mapa` ("construir el mapa completo sin pacing"), un timeout por `max_turns` sin commits
lo volvió a escalar, Irving lo re-aprobó otra vez, y desde entonces se repitió el patrón exacto de
esta familia: `soltar-claim` (muerte de proceso en `wt-3` y en `wt-1`), varios
`limite_cuenta_detectado`, y finalmente `reaper-rapido` lo re-encoló como huérfano
(`reap_count=1`). En ningún punto de ese historial hay un evento `paraguas_abierto` — es decir,
**nunca se intentó cerrarlo** pese a que la descomposición en los 4 sub-items ya existía desde
antes de la mayoría de esos ciclos.

## Verificación del estado real (esta vuelta)

```
962 (yo)      | en_progreso     | excl=false | sid=wt-2
  9990522     | completado      | merge=9681a91f (Fase 1: motor de cobertura)
  9990523     | requiere_irving | sin reclamar (Fase 2: endpoint de consulta)
  9990524     | requiere_irving | sin reclamar (Fase 3: sectores inalámbricos)
  9990525     | en_progreso     | sid=wt-3 (Fase 4: UI + DoD final)
```

La descomposición original sigue siendo correcta: 3 de los 4 hijos siguen abiertos, ninguno tocado
por esta vuelta (aislamiento #334 — el ítem con trabajo real activo, #9990525, ya tiene dueño en
`wt-3` y no se toca).

## Corrección aplicada

Se ejecuta el intento de cierre faltante sobre `#962` (`estado_aprobacion='completado'`). El guard
de paraguas del modelo lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (log
`paraguas_abierto`, "le quedan 3 sub-item(s) abierto(s)"), liberando `worker_sid`/`claimed_at` y
sacándolo del pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo
complete solo cuando #9990523, #9990524 y #9990525 cierren los tres.

**Sin cambio de código de negocio.** El trabajo real de MR-26 (endpoint de consulta por
coordenada, capa de sectores inalámbricos, UI + DoD con dirección real de Tultitlán) sigue en
#9990523 y #9990524 (`requiere_irving`, pendientes de aprobación) y #9990525 (`en_progreso` en
`wt-3`).

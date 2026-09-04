# Item #9990110 — Repro concurrente (2 procesos PHP reales) del cascade sobre #32 + resultados

**Fecha:** 2026-09-03/04 · **Worktree:** wt-1 · **Continuación de:** #9990109 (Fase 2b-i, harness
construido, commits `24007b71` instrumentación + `6422b476` scripts, mergeados a `main` en
`3bd0de0f` mientras se preparaba este item). Precedido por #9990076 (Fase 1, secuencial mismo
proceso — NO reprodujo, ver `docs/roadmap-cascade-repro-secuencial-item-9990076-verificacion.md`).

## Objetivo

Determinar si el bug real de #32 (`estado_aprobacion='completado'` con `nivel_riesgo='C'`, `branch`
propia y `merge_commit` vacío) puede reproducirse con **concurrencia real entre dos procesos PHP
del sistema operativo** (no solo secuencial-mismo-proceso, ya descartado en Fase 1) cerrando los dos
hijos de un mismo paraguas casi simultáneamente.

## Método

Harness de #9990109 (`storage/app/repro9990109-{setup,common,a,b,cleanup}.php`), reusado tal cual —
este item NO modificó su lógica, solo lo orquestó y lo corrió repetidamente:

1. `repro9990109-setup.php` crea el fixture sintético — mismo padre nivel-C sin merge_commit que la
   Fase 1 (`aprobado_irving`, `excluir_pool_automatico`, `esperando_merge_irving`, `branch` propia) +
   DOS hijos con `origen_item_id`=padre, ambos `aprobado_revisor` (abiertos). Escribe
   `repro9990109-fixture.json` con los ids reales y la ruta de un lock file.
2. `repro9990109-a.php` / `-b.php`, cada uno como **proceso `php artisan tinker` independiente**
   (PID de SO propio), hacen polling de 2ms sobre el lock file compartido y, en cuanto aparece, cada
   uno hace `find()` fresco de SU hijo + `estado_aprobacion='completado'` + `->save()`.
3. Orquestador bash temporal (`storage/app/repro9990109-run-once.sh`, no commiteado — ver limpieza):
   lanza A y B en background, espera (polling del `.out` de cada uno) a que AMBOS impriman "esperando
   lock" (confirma que ambos ya están bloqueados en el `while(!file_exists($lockFile))`), y **recién
   entonces** hace `touch` del lock — maximiza el overlap real en wall-clock en vez de arranques
   secuenciales del propio orquestador.
4. Instrumentación temporal de #9990109 ya presente en `RoadmapItem.php` (guard(1) en `saving()` +
   hook de cascada en `saved()`), con PID en cada línea — permite atribuir cada entrada del log al
   proceso A o B exacto.
5. **10 corridas** (dentro del rango 5-10 sugerido por el item), cada una con fixture nuevo +
   `repro9990109-cleanup.php` entre corridas (0 filas `[REPRO9990109]` residuales en ningún punto).

## Resultado — NO SE REPRODUJO, pese a confirmar concurrencia real (doble-disparo genuino)

Resumen agregado de las 10 corridas (20 cierres de hijo, uno por A y uno por B en cada corrida):

| Métrica | Valor |
|---|---|
| Corridas totales | 10 |
| Cierres de hijo (`hijo cerrado`) | 20 (2 por corrida) |
| Gap entre `lock_visto_at` de A y B | 0.8 ms – 20 ms (overlap real confirmado en las 10) |
| Cierres que vieron `tieneSubItemsAbiertos()==false` (dispararon el intento de cascada) | 14 |
| Corridas con **un solo** disparo de cascada (el 2º hijo en terminar ve al 1º ya cerrado) | 6 |
| Corridas con **doble disparo genuino** (A **y** B, cada uno por su cuenta, vieron `false` y ambos intentaron cerrar el padre) | **4** (padres sintéticos 9990169, 9990172, 9990175, 9990187) |
| `guard1 DISPARO — reroute a aprobado_irving` | 14 de 14 (100%) |
| Padre terminó en `estado_aprobacion='completado'` (el bug) | **0 de 10** |
| Padre terminó en `aprobado_irving` (correcto) | **10 de 10** |

Traza de una corrida con doble-disparo real (padre sintético 9990169, ambos PID casi simultáneos):

```
[…] cascade saved() hijo cerrado {"pid":989320,"hijo_id":9990170,"padre_id":9990169,"padre_tiene_subitems_abiertos":false}
[…] cascade saved() hijo cerrado {"pid":989321,"hijo_id":9990171,"padre_id":9990169,"padre_tiene_subitems_abiertos":false}
[…] cascade saved() DISPARA cierre del padre {"pid":989320,"padre_id":9990169}
[…] guard1 ENTRADA {"pid":989320,"item_id":9990169,"cierreParaguas":true,"merge_commit":null,"nivel_riesgo":"C","branch":"circuito/item-test-9990109"}
[…] guard1 DISPARO — reroute a aprobado_irving {"pid":989320,"item_id":9990169}
[…] cascade saved() DISPARA cierre del padre {"pid":989321,"padre_id":9990169}
[…] guard1 ENTRADA {"pid":989321,"item_id":9990169,"cierreParaguas":true,"merge_commit":null,"nivel_riesgo":"C","branch":"circuito/item-test-9990109"}
[…] guard1 DISPARO — reroute a aprobado_irving {"pid":989321,"item_id":9990169}
```

**Ambos procesos (A y B) leyeron `tieneSubItemsAbiertos()==false` de forma independiente** — es
decir, cada uno, en el instante de su propio `saved()`, ya veía al hermano cerrado en BD (la
ventana de 0.8-20 ms entre `lock_visto_at` de A y B fue suficiente para que el UPDATE del primero en
llegar a MySQL ya fuera visible para el `find()` del segundo, pero NO suficiente para que ninguno
descartara el intento — ambos decidieron "soy el último, debo cerrar el paraguas"). Esto **sí es**
una ventana de carrera real (dos procesos de SO distintos, ambos disparando el mismo efecto sobre el
mismo padre casi al mismo tiempo) — pero en los 4 casos, **cada proceso hizo su propio `find()` fresco
del padre dentro de su propia cascada, y guard(1) evaluó ESE objeto en memoria — no compartido entre
procesos** — con los mismos 3 campos invariantes del fixture (`merge_commit=null`,
`nivel_riesgo='C'`, `branch` no vacío), así que **guard(1) atrapó a los dos, cada uno por
separado, sin excepción**.

## Por qué guard(1) es robusto a esta carrera específica (y qué carrera NO cubre este experimento)

Guard(1) (`RoadmapItem.php:272-289`) decide sobre atributos que el harness mantiene **invariantes**
durante todo el experimento: `merge_commit` nunca deja de ser `null`, `nivel_riesgo` nunca deja de
ser `'C'`, `branch` nunca se vacía. Cada proceso hace su propio `find()` fresco (lee la fila real de
MySQL en el momento de su `saved()`), así que sin importar el orden de llegada de las dos escrituras
concurrentes de los hijos, **ambas lecturas del padre ven los mismos 3 campos** → guard(1) siempre
dispara. La carrera SÍ existe (dos intentos de cierre casi simultáneos, confirmados con timestamps),
pero **no es una carrera sobre los campos que guard(1) verifica** — es una carrera sobre CUÁNTOS
procesos deciden intentar cerrar, no sobre CON QUÉ DATOS lo intentan.

Este harness (como el de la Fase 1) **no** cubre el escenario en que `merge_commit`/`nivel_riesgo`/
`branch` cambian DURANTE la ventana de carrera (p. ej. un `MergeRunner::performMerge` real
escribiendo `merge_commit` en el mismo instante en que la cascada intenta cerrar el paraguas) — ese
es un tercer escenario, no probado aquí ni en la Fase 1, y **sigue sin descartarse**.

## Conclusión

Con **Fase 1 (secuencial, mismo proceso — #9990076)** y **Fase 2 (concurrente, 2 procesos de SO
reales, con doble-disparo genuino confirmado — este item)** ambas sin reproducir, **la causa raíz
exacta del incidente de #32 queda SIN CONFIRMAR al 100%**. Se descartaron dos rutas plausibles
(repetición-en-mismo-proceso, y la carrera "ambos hijos disparan casi a la vez sobre un padre con
campos invariantes"), pero no se descarta un tercer escenario — cambio concurrente de
`merge_commit`/`nivel_riesgo`/`branch` justo durante la ventana de cierre — ni la hipótesis de
escritura cruda fuera del modelo (esa sí quedó descartada aparte, ver #9990093).

**Recomendación para #9990013 (diseño del fix defensivo):** dado que ninguna causa exacta quedó
confirmada, el fix debe ser **genérico y no depender del mecanismo exacto de la carrera**. Dos
opciones razonables, análogas al candado atómico que ya usa el claim del pool
(`RoadmapCircuitoService.php` ~línea 2789):

1. `SELECT … FOR UPDATE` sobre la fila del padre dentro del hook de cascada (`saved()`), antes de
   leer `tieneSubItemsAbiertos()` y antes de decidir cerrar — serializa a nivel de fila cualquier
   intento concurrente, sin importar cuántos procesos lo disparen ni qué campo cambie a mitad de
   camino.
2. Un `UPDATE roadmap_items SET estado_aprobacion='completado', … WHERE id=? AND
   estado_aprobacion='aprobado_irving' AND merge_commit IS NULL... ` atómico (condición en el
   `WHERE`, no en un `if` de PHP después de un `find()`) — solo UN proceso puede ganar la carrera del
   propio UPDATE; los demás afectan 0 filas y no hacen nada.

Cualquiera de las dos cierra la clase completa de carreras (incluida la NO probada de
`merge_commit` cambiando a mitad de camino), sin necesitar confirmar el mecanismo exacto del
incidente de #32.

## Limpieza realizada (obligatoria antes de cerrar)

- Instrumentación temporal `Log::debug()` de #9990109 retirada de `RoadmapItem.php` (guard(1) en
  `saving()` y hook de cascada en `saved()`) — `git diff` del archivo contra `main` queda vacío.
- Scripts commiteados por #9990109 (`storage/app/repro9990109-{setup,common,a,b,cleanup}.php`)
  borrados de la rama de este item — ya cumplieron su propósito (Fase 2b-ii era su único
  consumidor).
- Orquestador temporal de este item (`storage/app/repro9990109-run-once.sh`, nunca commiteado)
  borrado.
- Fixtures sintéticos: **0 filas `[REPRO9990109]%` en `roadmap_items`** verificado tras la última
  corrida (la corrida #10 terminó con su propio `cleanup.php`, y se confirmó con un count() aparte
  antes de escribir este documento). IDs usados a lo largo del experimento (todos borrados):
  padres 9990133, 9990163, 9990166, 9990169, 9990172, 9990175, 9990178, 9990181, 9990184, 9990187;
  hijos 9990134-9990135, 9990164-9990165, 9990167-9990168, 9990170-9990171, 9990173-9990174,
  9990176-9990177, 9990179-9990180, 9990182-9990183, 9990185-9990186, 9990188-9990189.
- Archivos de estado del harness (`repro9990109-fixture.json`, `-go.lock`, `-a.out`, `-b.out`)
  borrados (gitignoreados, no se commitearon nunca).

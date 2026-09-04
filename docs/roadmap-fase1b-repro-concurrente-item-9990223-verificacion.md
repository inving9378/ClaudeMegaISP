# Item #9990223 — Fase 1b: repro con 2 procesos PHP reales (RESUELTO — ya completado por cadena paralela)

**Estado: sin re-ejecutar el experimento.** El objetivo de este item (instrumentar `RoadmapItem.php`
con `Log::debug` en guard(1) y en el hook de cascada, reproducir con **dos procesos PHP del sistema
operativo reales** el bug de `#32`, y si reproduce reportar la causa a `#9990013`) **ya fue cumplido
por una cadena de sub-items paralela**, con un resultado más preciso que el que este item habría
producido repitiendo el mismo método.

## Contexto — dos cadenas atacando el mismo incidente

`#9990076` (Fase 1, secuencial mismo-proceso — no reprodujo) tuvo **dos continuaciones
independientes**, creadas por sesiones distintas sin verse entre sí:

1. **La cadena de este item:** `#9990076` → `#9990077` (Fase 1a, repeticiones adicionales en el
   mismo proceso — tampoco reprodujo) → **`#9990223`** (este item, Fase 1b: pedía repro con 2
   procesos reales).
2. **La cadena paralela:** `#9990076` → `#9990099` (hipótesis del mecanismo exacto) → `#9990111`
   (Fase 2a: repro con 2 procesos PHP reales, con `DB::listen()` capturando el SQL real y lectura
   final con conexión fresca vía `DB::purge()`) → `#9990112` (Fase 2b: interpreta el resultado y
   deja la spec de fix lista para `#9990013`).

Existe además una TERCERA cadena (`#9990088→#9990094→#9990109→#9990110`) que también corrió un
harness de 2 procesos reales, pero sobre un escenario distinto (los dos HIJOS de un paraguas
cerrando casi simultáneo, con los campos del padre invariantes) — concluyó **sin reproducir** (guard
(1) atajó los 14 intentos de cierre sin excepción en 10 corridas), y su propia conclusión ya
señalaba que el escenario NO probado era "un `MergeRunner::performMerge` real escribiendo
`merge_commit` en el mismo instante en que la cascada intenta cerrar el paraguas" — exactamente el
escenario que `#9990111` sí probó y en el que sí reprodujo.

## Resultado ya confirmado por `#9990111`/`#9990112` (no repetido aquí)

Mecanismo exacto, con SQL real capturado (`docs/roadmap-repro-fase2a-cascade-guard-item-9990111-verificacion.md`):
un guard de `static::saving()` en `RoadmapItem.php` que **revierte un atributo a su valor original**
(guard (1) líneas 272-289, guard (2b) PARAGUAS líneas 301-332 — verificado en este item que siguen
en esas líneas exactas en `main`, sin cambios desde la Fase 2a) es vulnerable a que Eloquent **omita
esa columna del `UPDATE`** cuando, en la copia en memoria de ESE proceso, el valor revertido
coincide con lo que ese proceso cargó originalmente (`getDirty()` vacío para esa columna) — sin
importar lo que otro proceso concurrente ya haya escrito en la fila real. Escenario reproducido:
proceso **S** carga una copia stale (`merge_commit=null`) y espera; proceso **M** (simula
`MergeRunner`) carga después, fija `merge_commit`+`estado_aprobacion=completado` y guarda con éxito;
S despierta, su guard(1) revierte sobre la copia stale → coincide con su propio `getOriginal()` →
su `UPDATE` **omite** la columna `estado_aprobacion` → el `completado` legítimo de M sobrevive sin
que nada lo detecte. Exactamente el síntoma de `#32`.

`#9990112` ya evaluó 4 variantes de fix y dejó la recomendación (UPDATE directo y acotado a las
columnas revertidas de cada guard, vía `DB::table('roadmap_items')->where('id', $item->id)
->update([...])`) escrita en `comentarios_claude` de `#9990013`.

## Verificación hecha en este item (sin re-ejecutar el repro)

- Confirmado contra la BD real: `#9990013` (`requiere_irving`) ya trae la nota cruzada de
  `#9990112` (2026-09-04 06:20:33) con el mecanismo confirmado y la recomendación de fix — el paso
  "reportar la causa a #9990013" que pedía el spec de este item **ya está hecho**.
- Confirmado que `#9990076/#9990099/#9990111/#9990112/#9990094/#9990109/#9990110` están todos
  `completado`/`status=done` con `merge_commit` real — ya integrados a `main`, no es trabajo en una
  rama huérfana.
- Confirmado con `git diff` que `RoadmapItem.php` en `main` **no cambió** en guard(1)/guard(2b)
  desde la Fase 2a (el único diff entre el punto de partida de este worktree y `main` es el guard
  no relacionado de `#9990206`, ids explícitos) — el fix de `#9990013` sigue **pendiente de
  implementar**, sigue siendo responsabilidad de ese item, no de este.

## Por qué no se repite el experimento

Repetir el harness de 2 procesos (con o sin el escenario asimétrico tipo `MergeRunner`) sobre el
mismo guard, con el mismo código sin cambios desde la Fase 2a, produciría el mismo resultado que
`#9990111` ya capturó con más rigor (SQL real vía `DB::listen()`, lectura con conexión fresca). Es
el mismo patrón que motivó `#753` (cadena de seguimientos repetidos sobre la misma pregunta): sin
información nueva, una tercera/cuarta corrida del mismo experimento no cambia la conclusión ni
ayuda a `#9990013` a decidir — sólo consume una vuelta. La causa ya está confirmada con evidencia
más fuerte que la que este item habría producido, y ya llegó a donde tenía que llegar.

## Alcance de este item

Sin cambio de código — `RoadmapItem.php` no se tocó. Item cerrado documentando que su tarea ya fue
cumplida por la cadena paralela `#9990076→#9990099→#9990111→#9990112`, con el resultado ya dentro
de `#9990013`.

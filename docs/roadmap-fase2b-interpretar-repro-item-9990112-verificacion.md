# Item #9990112 — Fase 2b: interpretar el resultado del repro (#9990111) y actuar

**CASO A aplicado: bug reproducido.** #9990111 (Fase 2a) CONFIRMÓ el mecanismo exacto con evidencia
SQL real (`DB::listen()` + lectura con conexión fresca vía `DB::purge()`). Evidencia completa en
`docs/roadmap-repro-fase2a-cascade-guard-item-9990111-verificacion.md`. Este item no re-ejecuta el
repro: solo interpreta el resultado y deja la spec de fix lista para #9990013, tal como pedía su
alcance ("NO implementar el fix en este item").

## Mecanismo confirmado (resumen, ver #9990111 para el detalle + SQL capturado)

Cualquier guard de `static::saving()` en `RoadmapItem.php` que **revierte un atributo mutándolo de
vuelta a su valor original** (ej. guard (1) líneas 272-289, `estado_aprobacion` → `aprobado_irving`;
guard (2b) PARAGUAS líneas 301-332, misma reversión) es vulnerable a que Eloquent **omita esa
columna del `UPDATE`** cuando, en la copia en memoria de ESE proceso, el valor revertido coincide
con lo que ese proceso cargó originalmente (`getDirty()` vacío para esa columna) — sin importar lo
que otro proceso concurrente ya haya escrito en la fila real.

Escenario reproducido (variante 3 de #9990111, la única de las 3 que reprodujo el síntoma real de
`#32`): proceso **S** carga una copia stale (`merge_commit=null`) y espera; proceso **M** carga
después, fija `merge_commit` + `estado_aprobacion=completado` y guarda con éxito (`UPDATE` real
incluye `estado_aprobacion`). S despierta, intenta su propio cierre "naive" sobre su copia stale →
guard(1) evalúa `empty($item->merge_commit)` sobre la copia de S (sigue `null` en su memoria) →
revierte a `aprobado_irving`, que coincide con el `getOriginal()` de S → **el `UPDATE` de S omite
la columna `estado_aprobacion`** — S "cree" haber protegido el item pero su UPDATE nunca la toca.
El `completado` legítimo de M sobrevive intacto sin que nada lo detecte: exactamente el síntoma de
`#32`.

## Recomendación de fix para #9990013 (evaluación de variantes)

Se evaluaron las variantes ya sugeridas en el spec original de `#9990099`:

1. **UPDATE directo de las columnas revertidas** (recomendada) — dentro de cada guard que revierte
   un atributo, además de mutar el atributo en memoria (se conserva, para que el resto del request
   vea el valor correcto), emitir un `DB::table('roadmap_items')->where('id', $item->id)->update([...])`
   explícito con exactamente las columnas que ese guard revierte (`estado_aprobacion`,
   `esperando_merge_irving`, `excluir_pool_automatico`, `status`, `decision_resuelta` para guard(1);
   `estado_aprobacion`, `status`, `excluir_pool_automatico`, `worker_sid`, `claimed_at`, `log` para
   guard(2b)). No depende de que Eloquent detecte el cambio como dirty — escribe sí o sí. Riesgo
   bajo: acotado a las columnas ya listadas explícitamente en cada guard, no toca el resto del save.
   Cuidado de implementación: el `UPDATE` directo debe ir DESPUÉS de que el guard determine el valor
   final (no antes), y el `log` debe serializarse con `json_encode()` (columna cast `array` en el
   modelo, pero `DB::table()` no aplica casts).
2. **Forzar dirty con una reasignación intermedia** (`$item->estado_aprobacion = null;` antes de
   fijar el valor real) — descartada: depende de que ningún cast/mutator/observer reaccione mal a un
   `null` transitorio en memoria, y no es más simple que la opción 1.
3. **`syncOriginal()`/`forceFill()`** — descartada: cambia qué considera Eloquent "original" para
   TODO el resto del save de ese registro (incluye otras columnas que si están legítimamente dirty),
   mayor superficie de efectos colaterales que un `UPDATE` acotado a las columnas del guard.
4. **`SELECT ... FOR UPDATE` / transacción con lock** (recomendación genérica que dejó la cadena
   paralela #9990012→…→#9990110, que no logró confirmar el mecanismo exacto) — ataca el síntoma raíz
   (lectura stale) en vez de la omisión de columna, pero requiere envolver el flujo completo de
   `find()`+guards+`save()` en una transacción con lock en TODOS los call-sites que pueden cerrar un
   item, mayor blast radius que un `UPDATE` acotado dentro del guard mismo. Válida como defensa en
   profundidad futura, pero no es la recomendación mínima para este bug ya confirmado con mecanismo
   preciso.

**Recomendación final: variante 1** (UPDATE directo, acotado a las columnas de cada guard). Es la
misma que ya proponían `#9990099` y el propio spec de `#9990112`, y es la más simple/segura de las
evaluadas: no cambia el comportamiento de otros guards, no depende de heurísticas de dirty-tracking,
y el candado de regresión ya especificado en `#9990013` (`ParaguasNivelCSinMergeNoSeCuelaTest.php`,
reproduciendo la secuencia S/M confirmada aquí) puede verificarla directamente comparando el
`estado_aprobacion` leído con una conexión fresca tras ambos `save()`.

## Nota sobre la cadena paralela (#9990012→…→#9990110)

Esa cadena, que investiga el MISMO incidente `#32` y es de la que `#9990013` depende formalmente
(`origen_item_id=924`), concluyó **inconclusa** (`#9990110`: 10 corridas, guard(1) atajó los 14
intentos sin excepción, causa exacta sin confirmar al 100%, recomendación genérica de
`SELECT...FOR UPDATE`). La cadena de este item (`#9990076→#9990099→#9990111→#9990112`) SÍ confirmó
el mecanismo exacto con evidencia SQL reproducible. Se deja esta nota + la recomendación concreta
en `comentarios_claude` de `#9990013` para que, al retomarlo, no tenga que re-investigar ni decidir
entre las dos conclusiones divergentes: la de esta cadena es la más precisa y viene con SQL
capturado.

## Alcance de este item

Solo interpretación + documentación, tal como exigía el spec ("NO implementar el fix en este ítem").
Sin cambios a `RoadmapItem.php` ni a ningún archivo de código de negocio. `#9990013` sigue siendo el
responsable de implementar el fix + su test de regresión.

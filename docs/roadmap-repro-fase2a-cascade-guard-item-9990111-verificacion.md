# Item #9990111 — Fase 2a: repro de 2 procesos PHP concurrentes (columna omitida del UPDATE por no-dirty)

**Estado: bug CONFIRMADO** en el escenario asimétrico. Continuación de #9990076 (Fase 1, repro
secuencial mismo-proceso, NO reprodujo) y #9990099 (hipótesis del mecanismo exacto).

## Método

Comando Artisan temporal (`repro:fase2-cascade`, borrado al cerrar este item — no queda en
`main`) con 3 modos: `--crear[-sin-hijo]` (fixture sintético: padre nivel-C, `branch` puesto,
`merge_commit=null`, `estado_aprobacion=aprobado_irving`, `esperando_merge_irving=true`,
`excluir_pool_automatico=true` — replica el estado real de #32 antes del incidente), `--proceso=X`
(cada modo lanzado como proceso PHP **separado**, con su propia conexión a MySQL, sincronizado por
archivos de barrera en `/tmp`) y `--verificar-y-limpiar` (lee con `DB::purge()` desde una conexión
fresca, reporta el estado final, borra por id explícito). `DB::listen()` capturó el SQL real de
cada UPDATE para ver qué columnas entraron al `SET`.

Se corrieron 3 variantes:

### 1) Simétrica (2 intentos de cierre "naive" idénticos, sin merge)

Ambos procesos cargan el fixture, hacen `estado_aprobacion='completado'` y `save()`. guard(1)
revierte ambos en memoria de vuelta a `aprobado_irving` — coincide con el `getOriginal()` de
**cada uno** (ambos partieron del mismo valor), así que **ninguno** de los dos UPDATE incluye
`estado_aprobacion`/`esperando_merge_irving`/`excluir_pool_automatico`/`status` en el `SET` (solo
`decision_resuelta`+`log`+`updated_at`). Confirma el mecanismo de omisión de columna, pero el
resultado final es `aprobado_irving` (correcto) — el bug NO se reprodujo a nivel de estado final
porque ninguno de los dos procesos legítimamente escribe `completado`.

### 2) Asimétrica con hijo abierto (hallazgo colateral)

Fixture CON un hijo sin cerrar. Un proceso M simula MergeRunner (setea `merge_commit` ANTES de
`estado_aprobacion='completado'`, con lo que la condición `empty($item->merge_commit)` de guard(1)
es falsa y ese guard no revierte). Pero como el hijo sigue abierto, dispara guard **(2b) PARAGUAS**
en su lugar — y ese guard tiene la MISMA estructura (revierte a `aprobado_irving`, que vuelve a
coincidir con el original de M) → **también** omite la columna. Resultado final: `aprobado_irving`
(bug no reproducido aquí), pero confirma que la omisión por no-dirty no es exclusiva de guard(1):
aplica a cualquier guard de este archivo que revierta un atributo a su valor original.

### 3) Asimétrica SIN hijo — aísla guard(1) puro → **BUG REPRODUCIDO**

Fixture sin hijo (guard 2b no puede disparar). Proceso **S** carga el fixture **primero** (copia
stale, `merge_commit=null` en memoria) y espera. Proceso **M** carga después, setea `merge_commit`
+ `estado_aprobacion='completado'`, guarda (bypassa guard(1) legítimamente, `UPDATE` real incluye
`estado_aprobacion='completado'`) y libera a S. S entonces intenta su cierre "naive"
(`estado_aprobacion='completado'` sobre su copia stale) → guard(1) evalúa `empty($item->merge_commit)`
sobre la copia stale de S (**null**, sigue vacía en su memoria) → revierte a `aprobado_irving`,
que coincide con el `getOriginal()` de S → **el UPDATE de S NO incluye `estado_aprobacion` en el
SET** (SQL capturado, sin esa columna). El propio log de S dice "estado_aprobacion en memoria tras
save(): aprobado_irving" — S **cree** que protegió el item.

**Estado final verificado con conexión fresca: `completado`.** El "revert" de S nunca se persistió;
el `completado` legítimo de M sobrevivió intacto y S no lo detectó ni lo corrigió — exactamente el
síntoma de #32 (dos procesos "creyendo" cosas distintas del mismo registro, uno de ellos con una
reversión que no llega a la fila real).

## SQL relevante (variante 3, la que reproduce)

```
[PROC-S] UPDATE roadmap_items SET decision_resuelta=?, log=?, updated_at=? WHERE id=?
         -- (sin estado_aprobacion, sin status, sin esperando_merge_irving, sin excluir_pool_automatico)
[PROC-M] UPDATE roadmap_items SET status=?, estado_aprobacion=?, merge_commit=?, log=?, completed_at=?, updated_at=? WHERE id=?
         -- (sí incluye estado_aprobacion='completado')
```

## Conclusión para Fase 2b (decisión de fix — fuera de alcance de este item)

Mecanismo CONFIRMADO: cualquier guard de `RoadmapItem.php` que revierta un atributo mutándolo de
vuelta a su valor original (guard(1) línea ~284, guard(2b) línea ~311) es vulnerable a que Eloquent
omita esa columna del `UPDATE` si, en la copia en memoria de ESE proceso, el valor revertido
coincide con lo que ese proceso cargó originalmente — sin importar lo que otro proceso concurrente
ya haya escrito en la fila real. El propio `#9990099` ya proponía el fix más simple y siempre
correcto: que el guard persista su reversión vía `UPDATE` directo (`DB::table(...)->where('id',...)
->update([...])`) en lugar de sólo mutar el atributo del modelo, para no depender de que Eloquent
la detecte como dirty. Queda para quien tome Fase 2b decidir e implementar ese fix — este item se
limitó a confirmar el mecanismo, tal como pedía su alcance.

## Nota de coordinación

Al ejecutar este item se encontró que la MISMA investigación (repro de 2 procesos concurrentes del
mismo guard sobre el mismo incidente #32) también está en curso por otra cadena de sub-items:
#9990094 → #9990109 (harness construido) → #9990110 (`en_progreso`, pendiente de ejecutar+documentar).
Ambas cadenas de seguimiento (#9990076→#9990099→**#9990111** y #9990088→#9990094→#9990109→#9990110)
llegaron independientemente a la misma pregunta. Este item ya produjo el resultado empírico
definitivo (bug confirmado + mecanismo preciso); si #9990110 llega a ejecutar su propio harness,
debería converger en la misma conclusión — si no, vale la pena comparar ambos hallazgos.

## Limpieza

Comando temporal `app/Console/Commands/Active/ReproFase2CascadeCommand.php` **borrado** en este
mismo commit (no queda en `main`, salvo que el revisor decida conservarlo como herramienta de
diagnóstico — no se hizo esa decisión aquí, por default se retira). Los 3 fixtures sintéticos
(`[REPRO-FASE2]...`, ids autoincrementales efímeros) y sus hijos fueron borrados por id explícito
al terminar cada corrida; verificado `0` filas con `title LIKE '[REPRO-FASE2]%'` al cierre. Los
archivos de barrera en `/tmp/fase2_*` también se limpiaron.

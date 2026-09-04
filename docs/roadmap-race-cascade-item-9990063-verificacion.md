# Item #9990063 — Repro con transacción+rollback de la carrera del cascade sobre #32

**Continuación de #9990012** (forense estático) y del incidente real de **#32** (nivel C, cerró
`completado` con su propia rama y sin `merge_commit`, corregido a mano en #883/2026-09-03). Este
item pedía EJECUTAR (no solo leer) un repro instrumentado dentro de `DB::transaction()` con
rollback garantizado, para confirmar el mecanismo exacto que esquivó el guard(1) de
`app/Modules/Addons/Roadmap/Models/RoadmapItem.php`.

## Método

Dos experimentos, cada uno en su propia `DB::beginTransaction()` con `DB::rollBack()` en `finally`
(ningún dato sintético quedó en la BD de dev — verificado con un `count()` posterior a los dos
experimentos: `0` filas `[TEST-9990063]`). En vez de instrumentar `Log::debug` dentro del guard(1)
(la vía que sugería el spec original), se usó **introspección directa por snapshot**: tras cada
`save()` del hijo, se recarga el padre con `find()` fresco y se registran sus columnas críticas
(`estado_aprobacion`, `merge_commit`, `nivel_riesgo`, `branch`, conteo de `paraguas_cerrado` en su
`log`). Decisión registrada vía `circuito:reportar --tipo=decision`: es equivalente en poder de
diagnóstico, no deja instrumentación temporal que remover del código fuente, y dentro de una
transacción con rollback no hace falta tocar `storage/logs/laravel.log`.

Ambos experimentos crean un padre sintético (nivel_riesgo=C, branch seteado, merge_commit=null,
estado_aprobacion=aprobado_irving, esperando_merge_irving=true, excluir_pool_automatico=true —
mismo estado que #32 antes del incidente) y un hijo (`origen_item_id` = padre), y reproducen
lo que hace el hook de cascada real: `find()` del hijo → mutación → `save()`.

## Experimento 1 — repetición secuencial pura (3 saves del mismo hijo ya cerrado)

| Paso | estado_aprobacion (padre) | merge_commit | nivel_riesgo | branch | `paraguas_cerrado` en log |
|---|---|---|---|---|---|
| Antes | aprobado_irving | null | C | circuito/item-test-x | 0 |
| Save #1 hijo (→ completado) | aprobado_irving | null | C | circuito/item-test-x | 1 |
| Save #2 hijo (solo log, `estado_aprobacion` NO dirty) | aprobado_irving | null | C | circuito/item-test-x | 2 |
| Save #3 hijo (ídem) | aprobado_irving | null | C | circuito/item-test-x | 3 |

**Hallazgo colateral confirmado:** el hook `saved()` de cascada
(`RoadmapItem.php` ~línea 495-510) **no tiene guard de idempotencia** — se re-dispara en **cualquier**
`save()` posterior del hijo ya cerrado (chequea el VALOR de `estado_aprobacion`/`status`, no si
cambiaron en ESTE save), así que cualquier save trivial del hijo (p. ej. `circuito:reportar`
anotando una nota) reintenta cerrar al padre. Esto explica exactamente las **3 entradas
`paraguas_cerrado` duplicadas** en el log real de #32 (18:51:24 / 18:51:32 / 18:52:04) — no fueron 3
"cierres exitosos", fueron 3 intentos (el log se escribe SIEMPRE antes de que el guard(1) evalúe).

**Hallazgo negativo (igual de importante):** con las columnas protegidas del padre estables en las
3 pasadas, **guard(1) se sostiene las 3 veces** y revierte correctamente a `aprobado_irving`. Esto
**descarta** que el bug esté en la condición booleana de guard(1) en sí, o que la sola repetición
sea suficiente para corromperlo.

## Experimento 2 — ventana de carrera real (escritura externa entre dos intentos)

Mismo montaje. Entre el intento 1 (guard sostenido) y el intento 2, se simula un escritor EXTERNO
concurrente con una escritura **raw** (`DB::table('roadmap_items')->update(['branch' => ''])`, sin
pasar por Eloquent/eventos — exactamente lo que vería un `find()` que cae justo en esa ventana en
un entorno con **N terminales del circuito trabajando en paralelo sobre la misma BD**, que es
precisamente cómo opera este sistema). Después del intento 2 se restaura `branch` y se dispara un
intento 3.

| Paso | estado_aprobacion (padre) | merge_commit | nivel_riesgo | branch |
|---|---|---|---|---|
| Antes | aprobado_irving | null | C | circuito/item-test-x |
| Intento 1 (estado sano) | aprobado_irving | null | C | circuito/item-test-x |
| **Escritura externa:** `branch=''` | — | — | — | — |
| **Intento 2 (branch vacío en la ventana)** | **completado** | **null** | **C** | **''** |
| Escritura externa: `branch` restaurado | — | — | — | — |
| Intento 3 (branch ya restaurado) | completado *(sin cambio)* | null | C | circuito/item-test-x |

**Bypass reproducido exactamente**, con la MISMA firma que el estado real corrupto de #32:
`estado_aprobacion=completado`, `merge_commit=null`, `nivel_riesgo=C`. Causa: la condición de
guard(1) —

```php
if ($item->isDirty('estado_aprobacion')
    && $item->estado_aprobacion === 'completado'
    && ! $item->cierreManualIrving
    && empty($item->merge_commit)
    && $item->nivel_riesgo === 'C'
    && ! empty($item->branch)) {   // ← con branch vacío en el snapshot, esta condición es FALSA
```

se evalúa contra el snapshot que trajo el `static::find($item->origen_item_id)` propio del hook de
cascada (`RoadmapItem.php` ~línea 508-509), **sin lock ni re-lectura** dentro de una transacción que
abarque lectura+decisión+escritura. Si CUALQUIERA de las tres columnas que vigila (`branch`,
`merge_commit`, `nivel_riesgo`) está en un valor "malo" justo en el instante de ESE `find()`
concreto —sin importar por qué ni por cuánto tiempo—, el guard no dispara y `completado` se escribe
sin revertir. Y **no se autocorrige**: el intento 3 (con `branch` ya restaurado) no vuelve a
intentar el cascade, porque el hook de cascada solo actúa si `$padre->estado_aprobacion ===
'aprobado_irving'` — una vez que sale de ese estado, ningún cierre posterior de hermanos lo
reevalúa.

## Escritor real confirmado que deja `branch` en null

`app/Http/Controllers` → `RoadmapController.php:2283` (flujo de rechazo **"reciclar"** de
`integracionRechazo`): `$item->branch = null; ... $item->estado_aprobacion = 'pendiente_revision';
... $item->save();`. Es la única escritura real en el código que vacía `branch`. No se pudo
confirmar (ni el forense estático de #9990012 lo logró, por falta de rastro en el log de #32) que
haya sido *esta* escritura específica la que golpeó a #32 esa noche — lo que este item entrega es
el **mecanismo general reproducible** (TOCTOU sin lock en el guard), válido para cualquier escritor
—presente o futuro— que toque `branch`/`merge_commit`/`nivel_riesgo` del padre sin coordinarse con
el hook de cascada. El propio diseño multi-terminal del circuito (varios ejecutores en paralelo
sobre la misma fila, como documenta este mismo prompt de ejecución) hace esta ventana
estructuralmente alcanzable, no solo teórica.

## Recomendación para #9990013 (NO aplicada aquí — spec para ese item)

El fix debe darle atomicidad **lectura + decisión + escritura** al guard, en el hook de cascada
(`RoadmapItem.php` ~línea 508-509: `$padre = static::find($item->origen_item_id);`):

- Envolver la lectura del padre + el guard + el `save()` en una `DB::transaction()` con
  `static::where('id', $item->origen_item_id)->lockForUpdate()->first()` en vez de `find()` plano,
  así ninguna escritura externa (incluida "reciclar") puede intercalarse entre el chequeo de
  guard(1) y la escritura de `completado`.
- Complementario, no sustituto: cerrar el hallazgo colateral del Experimento 1 (idempotencia del
  hook `saved()` de cascada — hoy se re-dispara en cada save posterior del hijo ya cerrado) reduce
  el número de intentos y por lo tanto la superficie de la ventana de carrera, pero **no es la causa
  raíz** (Experimento 1 lo descarta: la repetición sola, sin escritura externa concurrente, no
  corrompe nada).

## Verificación de limpieza

Ambas transacciones de prueba se revirtieron (`DB::rollBack()` en `finally`, incluso en el camino
`catch`). Verificado tras correr los dos experimentos:
`RoadmapItem::where('title','like','%TEST-9990063%')->count()` → `0`. Sin datos sintéticos ni
huella en la BD de dev.

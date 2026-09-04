# Item #9990279 — Verificación manual de detección de ciclos en `circuito:sub-item`

Fase 1b-ii-b (sub-item de #9990267). Depende de la Fase 1b-ii-a (#9990278, ya mergeada en
`main` — commits `5f5ab695`+`060002f6`), que cableó `DependenciaGate::tieneCiclo()`/`caminoCiclo()`
dentro de `SubItemCommand::handle()`.

## Qué se probó

Prueba manual (no PHPUnit — eso es la Fase 1c aparte) contra `circuito:sub-item` real, vía
`Artisan::call()` en tinker sobre un padre de prueba descartable, con los 3 casos pedidos:

1. **Ciclo directo (2 nodos)** — se crea un hijo3 que depende de hijo2 (sin ciclo aún, éxito). Se
   fuerza por edición directa (fuera del gate) que hijo2 pase a depender de hijo3, armando el ciclo
   2↔3. Se intenta crear un hijo4 con `--depende-de=2`: el comando lo **rechaza** (`exit=1`),
   **no crea fila** (conteo antes/después idéntico) y el mensaje trae el camino correcto:
   `La posición 4 dependería circularmente: 2 -> 3 -> 2`.
2. **Ciclo indirecto (3 nodos)** — se crea una cadena lineal hijoA(sin deps)→hijoB(depende de
   A)→hijoC(depende de B): los 3 se crean **sin error** (este mismo paso cubre también el caso
   "sin ciclo" del punto 5 del spec). Se fuerza que hijoA pase a depender de hijoC, cerrando el
   ciclo A→C→B→A. Se intenta crear un hijoD con `--depende-de=A`: el comando lo **rechaza**
   (`exit=1`), **no crea fila**, mensaje con el camino completo: `La posición 7 dependería
   circularmente: 4 -> 6 -> 5 -> 4`.
3. **Caso sin ciclo** — cubierto por la cadena lineal del punto anterior: las 3 creaciones
   sucesivas (A, B, C) se completan normalmente sin ningún falso positivo.

Limpieza: se borraron los 6 hijos de prueba + el padre (`RoadmapItem::where('origen_item_id',
$padre->id)->delete()` + `RoadmapItem::where('id', $padre->id)->delete()`). Conteo global de
`roadmap_items` idéntico antes/después (1094 → 1094), cero residuo confirmado por query directa
(`title LIKE '[PRUEBA%'` → 0 filas). Corrido dos veces de forma independiente, mismo resultado
determinista las dos veces.

Nota aparte (no es un fallo): el proceso `php artisan tinker <script>.php` termina con exit code 1
en ambas corridas — es el código de salida del **último** `Artisan::call()` del script (el intento
`hijoD`, que **debe** fallar a propósito), no un error del script ni de la verificación. Las 13
aserciones inline (`PASS`/`FAIL`) confirmaron `PASS` en las dos corridas.

## Resultado

**Sin regresión.** Los 3 escenarios se comportan exactamente como especifica #9990278: ciclo
directo rechazado con camino correcto, ciclo indirecto de 3 pasos rechazado con camino correcto,
cadena lineal sin ciclo se crea sin fricción. No hubo que reparar nada en `SubItemCommand.php` ni
en `DependenciaGate.php` — **sin cambio de código de negocio**.

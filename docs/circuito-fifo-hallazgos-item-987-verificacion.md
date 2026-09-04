# Item #987 — Torre 24/7 · Pieza 5b — FASE 3: despacho FIFO de los hallazgos del barrido

Sub-item de #908 (Pieza 5b). El propio texto del item ya traía el hallazgo clave: el pool
**ya** despacha en FIFO por creación a los items sin `priority` explícita, vía el tie-break
final de `RoadmapItem::scopeOrdenCola()`/`criteriosOrdenCola()`
(`app/Modules/Addons/Roadmap/Models/RoadmapItem.php:1767-1826`): `position ASC, id ASC`. El
trabajo de esta fase era (1) verificarlo con una prueba real, (2) decidir si conviene hacerlo
explícito asignando `position` a los hallazgos, y (3) documentar que una `priority` puesta a
mano por Irving adelanta a todo lo demás — comportamiento vigente y deseado, no un bug.

## (1) Verificación — FIFO entre hallazgos de módulos distintos

Ni `RoadmapIntakeService::crear()` ni `App\Modules\Addons\Roadmap\Console\SubItemCommand`
(la ruta real por la que el barrido de FASE 2b crea sus hallazgos) tocan la columna
`position` — queda en su default de columna (`0`). Con `position` empatada en 0, el
desempate real es `id ASC`, que en una tabla con auto-incremento simple **es** el orden de
creación.

Prueba ejecutada en `tinker` (dentro de una transacción con `rollBack()` al final — no dejó
residuo en la BD de dev): se crearon 3 items "hallazgo" en 3 módulos distintos
(`ModuloA-987test`, `ModuloB-987test`, `ModuloC-987test`), sin `priority` ni `position`
explícitas, y se llamó a `RoadmapCircuitoService::ejecutablesParalelo([], 10)`:

```
IDs creados en orden: 9990026,9990027,9990028
Orden devuelto por ejecutablesParalelo(): 9990026,9990027,9990028
FIFO CONFIRMADO: orden de salida == orden de creación
```

La serialización por módulo (`ejecutablesParalelo()`, bloque 2549-2554 de
`RoadmapCircuitoService.php`, que salta módulos ya tomados por otra terminal en vuelo) **no
se tocó ni se ve afectada** — al ser 3 módulos distintos y sin nada en vuelo, el FIFO decidió
el orden dentro de esa regla, no en su lugar. Confirma exactamente lo que pedía el item.

## (2) Decisión — NO se asigna `position` explícita a los hallazgos

**Decisión: dejar `position` en su default (0) para los hallazgos del barrido — no aplicar
el patrón `max(position)+1 WHERE status='pending'` de `RoadmapController.php:2688`.**

Motivo: ese patrón es el que usa el endpoint de **alta manual** ("Agregar item" en la Torre)
para empujar el nuevo item al fondo de la sub-lista de items *ya* re-posicionados a mano
(los que se reordenaron por drag-and-drop u otra vía). Como la inmensa mayoría del pool vive
en `position=0` (nadie los reposiciona), aplicar ese mismo patrón a un hallazgo le asignaría
`position >= 1`, y como el orden es `position ASC`, eso lo mandaría **detrás** de todo el
pool en `position=0` — el efecto contrario al que busca esta fase ("no quedarse al final de
la fila para siempre"). El id ya da FIFO determinista y verificado entre hallazgos (y entre
cualquier item en `position=0`) sin ese riesgo. Aplicando ESTABILIDAD/MINIMALISMO: no se
toca el mecanismo de creación de hallazgos (`RoadmapIntakeService`/`SubItemCommand`) para
esto — es opcional según el propio texto del item ("opcional si (1) ya basta"), y (1) ya
quedó confirmado con prueba real.

## (3) Documentado — prioridad manual adelanta a los hallazgos

Prueba ejecutada (misma técnica, transacción + rollback): 3 items creados en orden
(`ModuloX`, `ModuloY`, `ModuloZ`), el último (`ModuloZ`) con `priority='alta'` y los otros
dos sin prioridad:

```
IDs creados en orden: 9990029,9990030,9990031 (el 3ro tiene priority=alta)
Orden devuelto: 9990031,9990029,9990030
CONFIRMADO: el de prioridad alta se adelanta pese a ser el más nuevo
```

Esto es el criterio `'prioridad'` de `criteriosOrdenCola()` (línea 1802-1807), que se evalúa
**antes** que `'antigüedad'` (el del FIFO). Es comportamiento vigente y deseado: si Irving
(o el revisor) le pone `priority=alta` a mano a un hallazgo, se adelanta a toda la fila —
no es un bug de esta fase, es la jerarquía de criterios que el propio `criteriosOrdenCola()`
ya declara y explica solo (`explicarOrdenCola()`).

## Cambio de código

Se agregó un comentario en `criteriosOrdenCola()` (entrada `'antigüedad'`,
`RoadmapItem.php`) documentando este hallazgo in situ, para que quien lea el mecanismo de
orden no necesite este doc aparte para entender por qué el FIFO de hallazgos ya funciona sin
cola dedicada. **Sin cambio de comportamiento** — es solo el comentario.

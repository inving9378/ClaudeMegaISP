# Item #935 — Detector A jQuery sin `.off()`: verificación en vivo (RESUELTO — 0 hallazgos hoy, fix ya aplicado)

## Pedido

Sub-item de seguimiento de #899 (hermano de #934). Con `detJquerySinOff()` ya mergeado a
`main` (#934, `docs/circuito-detector-jquery-off-item-934-verificacion.md`), pedía: (1) correr
el dry-run completo y contar cuántos items **nuevos** generaría el tipo `jquery_sin_off`; (2)
abrir 3 hallazgos al azar y confirmar manualmente archivo:línea + ausencia real de `.off()`;
(3) confirmar que el agrupamiento colapsa los 63 archivos en un número pequeño de items (no
63); (4) confirmar que el cap por ciclo se respeta; (5) ajustar la heurística si aparecían
falsos positivos; (6) NO correr `--apply` salvo decidir activar el detector de verdad; (7)
reportar cuántos hallazgos reales existen hoy y dejar el flag en `true` si la verificación
salió limpia.

## Hallazgo — el problema real que el detector medía ya fue corregido por #983

Entre que se escribió el spec de #899 (2026-09-03 14:15, commit `051549bd`, midió 63 archivos
sin `.off()`) y esta verificación, **otro item del mismo día** —
`fix(circuito#983): namespacear + limpiar 63 componentes Vue con $(document).on() global`
(commit `f92ce65e`, ya en `main` antes de que esta terminal empezara)— corrigió los 63
componentes: cada `$(document).on(...)` quedó namespaceado (`"click" + ns`) y cada componente
llama `$(document).off(ns)` dentro de `onUnmounted`. Item #983 en la Hoja de Ruta está
`completado`, `merge_commit` real, con `reporte_coloquial` describiendo exactamente ese fix.

Esto ya lo había notado #934 de pasada; #935 lo reverifica en vivo, punto por punto del spec:

### 1) Dry-run completo — 0 nuevos de tipo `jquery_sin_off`

```
php artisan circuito:auditor --forzar --detalle
```

Escaneó 30 módulos, "Roadmap / Circuito CC" reporta **2 gaps, 2 ya existen, 0 nuevos**
(los 2 preexistentes son `spec_declaracion_incompleta` y `env_runtime` —
`AuditorService.php:581` y `:872` — ninguno es `jquery_sin_off`). "ITEMS QUE SE CREARÍAN (cap
10): Ninguno." Repetido acotado al módulo (`--modulo="Roadmap / Circuito CC" --detalle`) tras
crear la rama desde el `main` más reciente: mismo resultado, 0 nuevos.

### 2) 3 hallazgos al azar — no hay ninguno NUEVO que abrir (0 candidatos vivos)

Como el detector ya no encuentra candidatos (todos los 63 ya tienen `.off()`), no hay
hallazgos "nuevos" que abrir para confirmar archivo:línea. En su lugar se verificó lo
equivalente y más útil: tomar 3 archivos al azar de los 63 que **antes** disparaban el
detector y confirmar que el fix de #983 es real, no solo un `merge_commit` sin contenido:

```
shuf -n 3 <lista de 63 archivos con "$(document).on(">
```

Muestra: `MethodOfPaymentListar.vue`, `IftListar.vue`, `NetworkListar.vue`. Los 3 tienen el
patrón correcto namespaceado:

```
import { onMounted, reactive, ref, onUnmounted, getCurrentInstance } from "vue";
...
onUnmounted(() => {
    $(document).off(ns);
});
...
$(document).on("click" + ns, ".uil-pen-modal", function () { ... });
```

### 3) Agrupamiento — colapsa a 1 item, no a 63

Verificado de dos formas independientes:
- **Código**: `detJquerySinOff()` (`AuditorService.php:1206-1265`) usa una sola
  `'clave' => 'jquery-sin-off:lote'` y arma un solo gap con la lista de todos los archivos
  afectados en su `detalle` — nunca un gap por archivo.
- **Empírico**: cuando el detector SÍ tenía candidatos (antes del fix de #983), generó
  exactamente **1 item** en la Hoja de Ruta —#983, título
  *"Circuito: 63 componente(s) Vue con `$(document).on()` global sin su `.off()`
  (memory leak SPA)"*— no 63. Confirma que el criterio "un item por módulo/grupo" funciona
  como se diseñó.

### 4) Cap por ciclo — respetado (trivial, 0 nuevos)

`config('circuito.auditor.cap_por_ciclo')` se aplica en `ciclo()` sin cambio de código; con 0
candidatos nuevos no hay nada que recorte. No requiere ajuste.

### 5) Heurística — sin falsos positivos, sin cambio

Los 63 archivos de referencia tienen hoy `.off()` real dentro de `onUnmounted` (no un
`.off()` decorativo en otro contexto que engañaría al detector) — confirmado en los 3
muestreados y en el conteo agregado (`grep -c` de `$(document).off(` sobre los 63 archivos =
63/63). No se encontró ningún caso tipo "tiene `.off()` en otro método pero no limpia este
handler" que ameritara endurecer la heurística. `TextTemplate.vue`/`ContractTemplate.vue`
(citados como patrón de referencia en el propio detalle del detector) están fuera del árbol
escaneado (`resources/js/shared/`, no `resources/js/components/`) — no aplica revisarlos aquí.

### 6) No se corrió `--apply`

Todas las corridas de este item fueron dry-run (`circuito:auditor --forzar --detalle` y
`--modulo=... --detalle`, sin `--apply`).

### 7) Cierre — flag ya estaba en `true`, sin cambio de código

`config/circuito.php:1016`: `'jquery_sin_off' => (bool) env('CIRCUITO_AUDITOR_D_JQUERYOFF', true)`
— default `true` desde que #899 lo creó. La verificación salió limpia (0 falsos positivos, 0
hallazgos pendientes, agrupamiento correcto, cap respetado) → el detector se deja **activo tal
cual está**, no requiere tocar el flag ni el código.

## Respuesta a "cuántos hallazgos reales existen hoy"

**Cero.** Universo de referencia: 63 archivos `.vue` bajo `resources/js/components/` con
`$(document).on(...)`; los 63 ya tienen su `.off()` namespaceado correspondiente (corregidos
por #983, `f92ce65e`, ya en `main`). El detector, corriendo hoy, agruparía esto en **0 items**
porque no queda ningún componente afectado — el ciclo detector→item→fix (#899→#983) ya se
cerró solo, antes de que esta verificación arrancara.

## Sin cambio de código

Ni `AuditorService.php` ni `config/circuito.php` se tocaron — la verificación confirmó que
ambos ya están correctos y que el problema que el detector vigilaba ya no existe. Este archivo
es el único artefacto de esta sesión.

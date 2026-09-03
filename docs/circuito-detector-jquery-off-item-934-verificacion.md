# Item #934 — Detector A jQuery sin `.off()`: `detJquerySinOff()` (RESUELTO — ya implementado)

## Pedido

Sub-item de seguimiento de #899: implementar `detJquerySinOff()` en `AuditorService.php`
(escanea `resources/js/components/**/*.vue` en busca de `$(document).on(...)` sin su
`.off(...)` correspondiente en el mismo archivo) + registrar el flag de config
`config('circuito.auditor.detectores')`, siguiendo el contrato investigado en la
description de #899.

## Hallazgo

El propio item #899 ya cerró el ciclo completo (investigación + implementación) en su commit
`051549bd` (`feat(circuito#899): detector de $(document).on() sin .off() en componentes Vue`),
**ya en `main`** antes de que #934 llegara a esta terminal. Verificado línea por línea contra
los 7 puntos del spec de #934:

1. **Método privado** `detJquerySinOff(string $modulo): array`
   (`AuditorService.php:1206`) — sin parámetro `$dir` porque siguió la opción (b) del punto 4
   (cross-cutting, mismo patrón que `detSinClasificar()`), que el propio spec de #934 marcaba
   como preferida si (a) resultaba más compleja.
2. **Flag registrado** en `config/circuito.php:1016`:
   `'jquery_sin_off' => (bool) env('CIRCUITO_AUDITOR_D_JQUERYOFF', true)` — mismo patrón que
   los otros 7 detectores (nombre de flag/env distinto al sugerido en el spec —
   `jquery_off`/`CIRCUITO_AUDITOR_D_JQUERY_OFF` — pero funcionalmente idéntico, aditivo y
   consistente con la convención real del archivo).
3. **Enganchado en `detectarGaps()`** (`AuditorService.php:868-870`):
   `if (($det['jquery_sin_off'] ?? true)) { $gaps = array_merge($gaps, $this->detJquerySinOff($modulo)); }`
4. **Gotcha resuelto** — eligió la opción (b): ancla TODOS los hallazgos a
   `'Roadmap / Circuito CC'`, devolviendo `[]` para el resto de módulos
   (`AuditorService.php:1208-1210`), igual que `detSinClasificar()`.
5. **Heurística exacta pedida**: candidato si el archivo contiene `$(document).on(` Y
   NO contiene NINGÚN `$(document).off(` (`AuditorService.php:1223-1235`). Verificado con un
   escaneo aparte (`RecursiveIteratorIterator` sobre `resources/js/components`, mismo criterio
   línea por línea) que la heurística coincide con el código real.
6. **Un item por lote** (no por archivo): `'clave' => 'jquery-sin-off:lote'`, un solo gap que
   agrupa todos los archivos afectados citando `archivo:Lnn` de cada uno + el conteo total
   (`AuditorService.php:1244-1264`), igual que `detAndamiaje()`.
7. **`clase` = `'mecanico'`** (`AuditorService.php:1252`), sin manejar la frontera dura de
   Jarvis dentro del detector (ya la aplica `detectarGaps()` después, como marcaba el spec).

## Por qué la verificación del spec no reproduce hoy un gap "en vivo"

El spec de #934 pedía que `circuito:auditor --modulo="Roadmap / Circuito CC" --detalle`
"muestre al menos 1 gap tipo jquery_off con cita archivo:línea real". Al momento de commitear
el detector (#899, `051549bd`, 2026-09-03 14:15), el propio mensaje de commit reporta
"63 archivos detectados" sin `.off()`. Pero **un commit posterior en el mismo día**,
`f92ce65e` (`fix(circuito#983): namespacear + limpiar 63 componentes Vue con
$(document).on() global`), ya corrigió los 63 archivos (agregó su `.off()` namespaceado). Es
decir: el detector cumplió su propósito — encontró el problema real, el problema real ya se
arregló — y por eso hoy reporta 0 hallazgos de este tipo. No es un defecto del detector.

Verificado en esta sesión:
- `php -l app/Modules/Addons/Roadmap/Services/AuditorService.php` y `config/circuito.php` →
  sin errores de sintaxis.
- `php artisan circuito:auditor --modulo="Roadmap / Circuito CC" --detalle` corre limpio
  (Gaps=2: `env_runtime` + `spec_declaracion_incompleta`, ambos ya existen como items; 0
  `jquery_sin_off` porque no hay candidatos vivos).
- Escaneo manual (`grep`/script PHP standalone) sobre los 63 archivos que tienen
  `$(document).on(` en `resources/js/components`: **los 63 ya tienen también
  `$(document).off(`** en el mismo archivo → confirma que el estado real del código coincide
  exactamente con lo que el detector reporta (0 candidatos), y que la heurística del detector
  es correcta (no hay falsos negativos evidentes en la muestra).

## Cierre

Sin cambio de código — el detector, el flag y el enganche ya están completos y correctos en
`main` desde `051549bd`. Este item se cierra documentando la verificación. El sub-item
hermano **#935** ("verificar en vivo, medir volumen y confirmar 3 citas al azar") sigue su
curso aparte; su alcance también quedó cubierto por lo ya hecho en el propio commit de #899
(que reporta las 3 citas al azar confirmadas), pero no se tocó aquí — es un item distinto con
otro dueño.

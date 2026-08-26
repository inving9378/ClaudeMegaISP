# Auditoría APIs jQuery Bootstrap 4 legacy — item #111

El item #111 pedía auditar y migrar llamadas jQuery-estilo-plugin de Bootstrap 4
(`.collapse()`, `.tab()`, `.tooltip()`, `.popover()`, `.dropdown()`) a la API nativa de
Bootstrap 5 (`window.bootstrap.<Componente>.getOrCreateInstance(el)`), siguiendo el patrón
del fix de `.modal()` aplicado en 2026-06-03.

## Resultado de la auditoría (2026-08-26): **cero ocurrencias**

Búsqueda exhaustiva en `resources/` y `app/` (Vue, Blade, JS — excluyendo `public/` que son
librerías vendor sin tocar):

```bash
grep -rnE '\b(collapse|tab|tooltip|popover|dropdown)\s*\(' \
  --include='*.js' --include='*.vue' --include='*.blade.php' --include='*.php' resources app
```

Único match: un comentario en `TorreConfigPanel.vue` que contiene la palabra "tab" dentro de
una frase, no una llamada. **No existe ninguna llamada real `$(...).collapse()`,
`.tab()`, `.tooltip()`, `.popover()` ni `.dropdown()` en el código de la aplicación.**

## Por qué no hay nada que migrar

El sistema solo carga `bootstrap.bundle.min.js` (standalone, con Popper, sin dependencia de
jQuery) — confirmado en `app/Modules/Core/Layout/views/vendor-scripts.blade.php:14` y en las
páginas de error (403/404/sin-modulos). Collapse, Tab, Tooltip, Popover y Dropdown se usan en
el codebase **solo vía atributos `data-toggle`/`data-bs-toggle`** (59 archivos), que Bootstrap
5 auto-inicializa sin ninguna llamada JS manual — no hay superficie para el bug descrito
("jQuery silencia la llamada a un método inexistente").

La migración BS4→BS5 de 2026-06-03 solo dejó pendiente el patrón `.modal()` (ver hallazgo
abajo), no estas otras cinco APIs.

## Hallazgo relacionado (fuera del alcance de #111, registrado como sub-item)

Durante la auditoría se encontraron **~90 llamadas `$(...).modal("show"|"hide")`** (jQuery
plugin style) que NO fueron migradas por el fix de 2026-06-03 — ese fix solo tocó los
archivos con el bug de backdrop reportado, no todo el codebase. Mismo root cause potencial
(no hay plugin jQuery de Bootstrap cargado, solo `bootstrap.bundle.min.js`): si esas llamadas
dependen de un handler jQuery inexistente, fallarían en silencio igual que el bug original.
Queda registrado como sub-item propio para auditar caso por caso (algunos pueden tener
fallback por atributo `data-bs-toggle` en el botón disparador y no ser bugs reales; otros son
llamadas puramente programáticas sin ese fallback).

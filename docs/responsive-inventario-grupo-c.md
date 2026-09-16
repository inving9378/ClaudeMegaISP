# Inventario responsive — Grupo C: Mapa de Red, Talento, Flotas

Item roadmap #9991187 (sub-item de #9991184, Fase 2 — inventario real de responsive). **Auditoría
SOLO LECTURA**, no se corrigió ni se tocó código de ningún módulo. La Fase 1 (header/topbar
global, #9991183) ya está cerrada y no se repite aquí.

## Metodología

Este entorno no tiene acceso a un navegador para renderizar la app y capturar screenshots reales
(el propio item además pide expresamente **SIN screenshots**). El inventario se hizo por
**análisis estático del código real servido**: plantillas Vue/Blade + hojas de estilo, grepeando
reglas `@media`, clases de grid (Bootstrap `col-*` / Quasar `col-*`), `flex-wrap`, `overflow-x` y
posicionamiento `fixed`/`absolute` en los componentes que arma cada pantalla. Los hallazgos
marcados aquí son deterministas (confirmables con `grep`/lectura directa del archivo citado); no
sustituyen una verificación visual en navegador a 1920/1366/768/375px, que sigue pendiente.

Resoluciones objetivo del item: **1920 · 1366 · 768 · 375**.

---

## Mapa de Red (`/mapa-red`)

Ruta real: módulo `app/Modules/Addons/MapaRed/` → `MapaRedController::index()` →
`app/Modules/Addons/MapaRed/views/index.blade.php` (`@extends('core-layout::master')`, o sea
corre dentro del layout admin con header/sidebar globales). ⚠️ Existe una ruta vieja gemela en
`app/Modules/Addons/GestionRed/routes.php:124` (`fn() => view('meganet.module.olts.mapa-red')`)
que **no** es la usada — se descartó de este inventario.

Componentes: `resources/js/components/module/mapared/LeafletMapRed.vue` (3164 líneas) +
`resources/js/components/module/mapared/components/others/ElementSidePanel.vue` (662 líneas, el
panel lateral que aparece al hacer clic en un elemento del mapa).

| Vista / ruta | Resolución | Qué se rompe | Severidad | Causa raíz |
|---|---|---|---|---|
| Panel lateral de elemento (`ElementSidePanel.vue`) | 375 / 768 (aplica igual a 1366/1920) | El panel (`.element-side-panel{position:fixed;top:0;height:100vh;z-index:2000}`) ocupa el 100% del alto de viewport sin descontar la altura del header/topbar global del layout admin — se dibuja por encima de él en vez de debajo, en vez de respetar el espacio del header fijo | media | CSS fijo `top:0`/`height:100vh` sin `padding-top`/`calc()` que reste la altura real del header (el fix de header responsive de #9991183 no cubrió este panel, que vive en otro módulo) |
| Controles/leyenda flotante sobre el mapa (`LeafletMapRed.vue`) | 375 | El archivo completo (3164 líneas) no tiene **ninguna** regla `@media` (grep confirmado, 0 coincidencias) — cualquier control flotante posicionado sobre el canvas Leaflet (toolbar de capas, leyenda, botones propios de zoom/filtro) no tiene adaptación declarada para pantallas angostas | media | ausencia total de breakpoints en el componente principal del mapa |

**Nota positiva (no es hallazgo):** los 3 diálogos modales del componente (líneas ~260/313/353)
sí usan `max-width: 80vw`/`90vw` junto al ancho fijo, así que se adaptan correctamente a móvil. La
tabla de salud dentro del panel lateral (`.element-side-panel__salud-table`) ya tiene
`overflow-x:auto`.

**Pendiente de confirmar visualmente** (no descartable solo por código): si el `z-index:2000` del
panel realmente gana o pierde contra el header fijo del admin en cada resolución, y si la toolbar
de capas/filtros del mapa (más difícil de ubicar por ser controles Leaflet inyectados en runtime)
se sale de pantalla en 375px.

---

## Talento — Portal de Colaborador (`/talento/portal`)

App independiente del admin (guard `web`, permiso `portal.colaborador`), shell propio en
`app/Modules/Addons/Talento/views/portal/shell.blade.php`, servida como assets estáticos
versionados en `public/talento-portal/app.js` (1418 líneas) + `public/talento-portal/portal.css`
(91 líneas) — **no** pasa por el build de Mix del resto del sistema. Pantallas auditadas: Mi día
(check-in/OTs), Mi dinero (Cuenta/Desglose/Fondo/Préstamos) y Mi material (custodia de equipos).

| Vista / ruta | Resolución | Qué se rompe | Severidad | Causa raíz |
|---|---|---|---|---|
| Todo el portal (`portal.css` + `.tp-page`/`.q-page` en `app.js`) | 1920 / 1366 | `portal.css` no tiene **ninguna** regla `@media` (0 coincidencias en 91 líneas, grep confirmado) ni el contenedor de página fija un `max-width` — en escritorio grande las tarjetas (`.tp-card`), listas y botones `class="full-width"` se estiran de borde a borde del viewport, con líneas de texto y controles anormalmente anchos | media | portal diseñado mobile-first (columna única, botones `full-width`) sin contrapartida de ancho máximo para breakpoints de escritorio |
| Pestañas de "Mi material" (`materialEstadoTab` línea 1144, `materialTab` línea 1152 de `app.js`) | 375 | Los `q-tabs` se declaran con `:breakpoint="0"`, que en Quasar desactiva el colapso a menú/dropdown en pantallas angostas — quedan siempre en modo fila con scroll horizontal en vez de un selector compacto | baja | prop `breakpoint="0"` fuerza el modo "nunca dropdown" sin importar el ancho real del viewport |

**Nota (housekeeping, no es un "se rompe" visible):** `portal.css` define `.tp-footer` (nav
inferior tipo app, líneas 54-58 y 68) pero esa clase **no aparece en ningún lado de la plantilla
real** de `app.js` (grep confirmado) — es CSS muerto de un patrón de navegación anterior,
reemplazado por el `q-drawer` lateral actual (sidebar componible, SP3a). No afecta el render de
hoy; se deja anotado por si alguien reactiva el patrón sin darse cuenta de que ya no se usa.

**Lo que sí está bien resuelto:** el `q-drawer` usa `show-if-above` (colapsa a overlay por debajo
del breakpoint `md` de Quasar = 1023.98px, visible siempre en 1366/1920) + el botón hamburguesa
tiene `class="lt-md"` (solo visible <1024px) — el patrón de sidebar responsivo en sí es correcto
en 375/768 vs 1366/1920.

---

## Flotas (`/flotas` dashboard + ficha de vehículo)

**Criterio de evaluación:** Flotas usa Bootstrap 5 + Leaflet, stack deliberadamente distinto del
resto del sistema (Quasar) — decisión de arquitectura ya tomada y documentada (ver CLAUDE.md,
sección Flotas). Se evalúa aquí por comportamiento visual observable en el código, no contra los
breakpoints de Quasar del resto del admin.

### Dashboard (`FleetDashboard.vue`, `/flotas`)

Bien resuelto, sin hallazgos de severidad relevante: `.flt-dash-header`/`.flt-dash-actions` usan
`flex-wrap: wrap` (los 4 botones de acción se acomodan en varias líneas en vez de desbordar), las
5 tarjetas de métricas usan `col-6 col-lg` / `col-12 col-lg` (2 por fila en móvil/tablet, una fila
completa en desktop), y trae un `@media (max-width: 991px)` propio que oculta la columna de
ubicación (`.flt-veh-loc`) de la lista de vehículos para no forzar scroll horizontal en tablet.

### Ficha de vehículo (`FleetVehicleShow.vue`, `/flotas/{id}`)

Cero reglas `@media` en las 424 líneas del componente (grep confirmado) — a diferencia del
dashboard, aquí sí hay hallazgos:

| Vista / ruta | Resolución | Qué se rompe | Severidad | Causa raíz |
|---|---|---|---|---|
| Barra de pestañas (`<ul class="nav nav-tabs flt-tabs">`, línea 85) — 8 pestañas: Información, Asignación, Mantenimientos, Documentos, Combustible, Tracking GPS, Historial, Fotos | 375 / 768 | `.nav-tabs` de Bootstrap 5 es `display:flex; flex-wrap:wrap` por defecto y el componente no agrega `flex-nowrap` + `overflow-x:auto`, ni un selector compacto — en 375px las 8 pestañas (ícono + texto) no caben en una fila y se parten en 3-4 renglones, empujando el contenido de la pestaña activa hacia abajo | alta | falta contenedor con scroll horizontal (o colapso a dropdown) para la barra de pestañas; el propio componente ya tiene un patrón de "más opciones" (dropdown `moreOpen`, líneas 42-52, usado para Fotos/Historial/Eliminar en el header) que no se reutilizó aquí |
| Barra fija inferior (`.flt-fixed-bar`, template línea 140 / CSS línea 417) — botones "Eliminar" y "Volver a Flotas" | 375 | `padding: 12px 24px` fijo (no se reduce en móvil); con solo 2 botones probablemente no desborda, pero el padding lateral fijo de 24px a cada lado resta proporcionalmente más ancho útil cuanto más angosto es el viewport | baja | falta un `@media` que reduzca el padding lateral en viewports angostos |

**Nota positiva:** el contenedor general (`.flt-show-wrap{max-width:1200px;margin:0 auto}`) sí
limita el ancho en desktop grande (a diferencia del portal de Talento), y el header de la ficha
(`.flt-header`) usa `min-width:0` en la columna central + `flex-wrap:wrap` en badges/info rápida,
lo que evita desbordamiento aunque el nombre del vehículo sea largo.

**Pendiente de confirmar visualmente:** cuántos renglones exactos ocupa la barra de 8 pestañas en
375px real, y si el salto de línea dentro de cada `<a class="nav-link">` corta algún ícono/label.

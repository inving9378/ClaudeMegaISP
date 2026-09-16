# Inventario responsive — Grupo A: Dashboard, Clientes, CRM, Vendedores

> **SOLO LECTURA.** Auditoría estática de código (Blade + Vue + `<style>`), sin ejecutar
> Playwright/navegador (no había credenciales de prueba disponibles en esta vuelta). Ningún
> archivo de la aplicación fue modificado — este documento es el único entregable.
>
> Fase 1 (header/topbar global) ya está cerrada y mergeada (item #9991183, commit `df7207a4`):
> **no se reportan** hallazgos de header/avatar/logo, solo el contenido propio de cada vista.
>
> Breakpoints de referencia (`docs/patron-responsive-medussa.md`): móvil **< 640px**, tablet
> **640–1023px**, desktop **≥ 1024px**. El sidebar del layout colapsa a `max-width: 992px`
> (Bootstrap) — a partir de 640px ya no hay sidebar visible.

---

## Resumen de severidad

| Vista | Alta | Media | Baja |
|---|---|---|---|
| Dashboard | 0 | 0 | 1 |
| Clientes | 0 | 1 | 0 |
| Clientes (ficha) | 1 | 0 | 0 |
| CRM | 0 | 3 | 1 |
| Vendedores | 2 | 1 | 0 |

---

## Dashboard (`/dashboard`)

Componente: `resources/js/components/module/dashboard/Dashboard.vue` (sin `<style>` propio) +
`TarjetTicket.vue` + `CardStats.vue` + `CardTextDashboard.vue`. Usa grid Bootstrap 5 puro
(`col-md-3`/`col-sm-12 col-md-6`), sin media queries propias.

| Vista/Ruta | Resolución afectada | Qué se rompe | Severidad | Causa raíz (archivo:línea) |
|---|---|---|---|---|
| `/dashboard` — fila de tarjetas KPI (Tickets por estado) | tablet (640–1023px) | Las 4 tarjetas (`min-height:200px`) usan `col-md-3 col-xl-3` sin un paso intermedio para tablet (ej. `col-sm-6`): en 768px caen directo a 4 columnas de ~180px de ancho en vez de 2 columnas más cómodas, como indica el patrón del proyecto para grids en tablet. No hay overflow ni texto cortado — es apretado, no roto. | baja | `resources/js/components/module/tickets/component/TarjetTicket.vue:2` |

**Sin hallazgos adicionales:** en móvil (`<640px`) Bootstrap 5 stapea las columnas a 100% por
defecto (regla base `.row > *{width:100%}` fuera de cualquier `@media`, verificada contra
`public/assets/css/bootstrap.css`), así que las tarjetas KPI y los bloques `CardStats`
(`col-sm-12 col-md-6`) se apilan correctamente sin desbordar. `CardTextDashboard` es texto plano
sin ancho fijo, sin riesgo.

---

## Clientes

### Listado (`/cliente/listar`)

Componente: `resources/js/components/module/client/DatatableClient.vue` (sin `<style>` propio,
`q-table` de Quasar).

| Vista/Ruta | Resolución afectada | Qué se rompe | Severidad | Causa raíz (archivo:línea) |
|---|---|---|---|---|
| `/cliente/listar` — barra de filtros superior | móvil (<640px) | El selector de rango "Fecha de Corte" (`VueDatePicker`) tiene un ancho fijo `width: 350px` inline. En un viewport de 375px, restando el padding de `.q-pa-md`+`q-card` (~32-48px), el control por sí solo ya casi no cabe (350px sobre ~330-340px disponibles), desbordando ese segmento de la barra de filtros aunque el contenedor sea `flex-wrap`. El selector "Estado" (`SelectFilter.vue`) también fija `width: 200px` al `<div>` interno, sumando presión al mismo renglón. | media | `resources/js/components/module/client/DatatableClient.vue:34-44` (`style="width: 350px"`); `resources/js/components/module/client/helpers/SelectFilter.vue:8` (`style="width: 200px"`) |

**Sin hallazgos adicionales:** la tabla en sí no necesita `overflow-x` manual — Quasar aplica
`overflow:auto` vía `.q-table__middle.scroll` (verificado en `quasar.prod.css`), así que las
columnas configurables desbordan con scroll horizontal nativo, no rompen el layout de la página.

### Ficha de cliente (`/cliente/editar/{id}`)

Componente raíz: `resources/js/components/module/client/ClientCrud.vue`.

| Vista/Ruta | Resolución afectada | Qué se rompe | Severidad | Causa raíz (archivo:línea) |
|---|---|---|---|---|
| `/cliente/editar/{id}` — barra de pestañas (Información / Documentos / Servicios / Facturación / Estadísticas / Promociones + pestañas de addons) | móvil (<640px) y tablet (640-1023px) | `<q-tabs align="justify" :breakpoint="0">` fuerza a Quasar a repartir TODAS las pestañas en el ancho disponible sin activar su modo scroll/flechas (ese modo solo se activa cuando el ancho del contenedor es menor al `breakpoint`; con `breakpoint=0` nunca se activa). Con 6+ pestañas en 375-768px, cada una recibe un ancho muy angosto — etiquetas como "Estadísticas"/"Facturación"/"Promociones" quedan comprimidas/recortadas y el área de toque de cada pestaña se reduce, dificultando tocar la correcta. | alta | `resources/js/components/module/client/ClientCrud.vue:2-9` |

**Nota positiva:** la pestaña "Información" (`InformationClientCrud.vue`) ya está bien resuelta
para responsive — usa `grid-template-columns: 1fr` en `@media (max-width: 991px)` (línea 832) y
`flex-shrink:0` en el avatar del header. El problema está en la barra de pestañas del padre
(`ClientCrud.vue`), no en el contenido de "Información".

---

## CRM

*(Auditado por un subagente en paralelo — mismo método y breakpoints.)*

### Listado (`/crm/listar`)

| Vista/Ruta | Resolución afectada | Qué se rompe | Severidad | Causa raíz (archivo:línea) |
|---|---|---|---|---|
| `/crm/listar` — fila de acciones de la tabla | móvil (<640px) | El contenedor del menú "⋮" + buscador (y cualquier botón futuro vía prop `buttons`/`header-extra`) no tiene `flex-wrap`: los elementos se comprimen en una sola línea en vez de apilarse. Con la config actual (sin `buttons`) el riesgo es bajo-medio (solo 2 elementos); se agrava en cuanto el módulo use el slot `header-extra` con más botones (ya lo usa para "Documentos huérfanos"). | media | `resources/js/components/module/crm/CrmDatatable.vue:84` — comparar con el patrón correcto en `resources/js/components/base/shared/Datatable.vue:34` (`flex-wrap`) |

### Detalle/edición (`/crm/editar/{id}`)

| Vista/Ruta | Resolución afectada | Qué se rompe | Severidad | Causa raíz (archivo:línea) |
|---|---|---|---|---|
| `/crm/editar/{id}` — tarjetas "Información Principal" / "Datos geográficos" | tablet (640-1023px) y desktop <1200px | Usan `col-xl-6` **sin clase base** (`col-12`/`col-md-*`). Bootstrap solo asigna `width:50%` a `.col-xl-6` dentro de `@media (min-width:1200px)`; por debajo de esa medida el div no tiene ancho asignado y el navegador lo dimensiona por contenido en vez de apilar a 100% o partir 50/50. | media | `resources/js/components/module/crm/InformationCrmCrud.vue:47` y `:93` |
| `/crm/editar/{id}` — campo "ID" | móvil (<576px) | `col-sm-12 col-md-3`/`col-sm-12 col-md-6` sin `col-12` base: por debajo de 576px no hay ancho asignado (mismo patrón de clases Bootstrap incompletas). Impacto puntual bajo (label+valor cortos), pero es el patrón que se repite en otros campos del formulario. | baja | `resources/js/components/module/crm/InformationCrmCrud.vue:57` y `:62` |
| `/crm/editar/{id}` — campos dinámicos tipo texto (`ComponentFormDefault` → `InputText.vue`) | móvil (<640px) y tablet | El default es `col-6` (50% de ancho) **sin ninguna regla responsiva**, aplicado siempre (fuera de cualquier `@media`). Cualquier campo que no declare `class_col:'full'` en su config queda a mitad de ancho incluso en celular. No se pudo verificar la config real de cada campo en BD (catálogo data-driven, fuera del alcance de solo-lectura sin acceso a datos) — el defecto de código es cierto, su alcance real en CRM específicamente no se pudo confirmar al 100%. | media | `resources/js/shared/InputText.vue:3-7` |

**Sin hallazgos:** la tabla de `CrmDatatable.vue` scrollea horizontalmente igual que la de
Clientes (Quasar nativo); la pestaña "Documentos" (`DocumentCrmCrud.vue`, usa `Datatable.vue`
base) sí tiene `flex-wrap` correcto.

---

## Vendedores

*(Auditado por un subagente en paralelo — mismo método y breakpoints. Dos sub-áreas: listado
admin `/sellers/seller` y panel de vendedor `/vendedores/*`.)*

| Vista/Ruta | Resolución afectada | Qué se rompe | Severidad | Causa raíz (archivo:línea) |
|---|---|---|---|---|
| Panel de vendedor → Facturación → sub-pestaña "Comisiones" (`/vendedores/{id}/seguimiento-vendedor/{seller_id}`, `/vendedores/seguimiento-me/`) | móvil (<640px) y tablet (640-1023px) | `q-splitter` divide "Ventas de la Casa" / "Ventas adicionales" en 2 paneles lado a lado (40%/60%) sin volverse vertical en ningún breakpoint. En 375px de viewport quedan columnas de ~150px/~225px con nombres de cliente, botón "Crear pago" y badges de estado comprimidos. | alta | `resources/js/components/module/vendors/billing/components/invoice/CommissionsList.vue:21-26` (`<q-splitter>` sin prop `horizontal` ni binding a `$q.screen`) |
| Listado de vendedores (`/vendedores/`) — barra de herramientas de la tabla | móvil (<640px) | Igual patrón que el que #9991075 corrigió en `Sales.vue`/`ListCustomers.vue`, pero en un archivo hermano que ese fix no tocó: el contenedor del botón de columnas + fullscreen + buscador es `d-flex justify-content-end` **sin `flex-wrap`**, y el buscador tiene `margin-left:16px` fijo sin `min-width`, se comprime a casi 0. | alta | `resources/js/components/module/vendors/VendedorListar.vue:31-63` |
| Listado de vendedores (mismo archivo/ruta) — encabezado de la tarjeta | móvil (<640px) | El header ("Listado de vendedores" + botón "Agregar Vendedor") usa `justify-content: space-between` inline sin `flex-wrap`: si título + botón no caben en una línea, se comprimen en vez de bajar a la siguiente. | media | `resources/js/components/module/vendors/VendedorListar.vue:5-16` |

**Sin hallazgos adicionales confirmados en:** `SellerListar.vue` (usa `Datatable.vue` base, ya
responsive), `Menu.vue` (`.tc-tabs` ya tiene `flex-wrap`), `Dashboard.vue`/`TarjetCard.vue`
(mismo patrón Bootstrap 5 que el Dashboard general, se apila correctamente en móvil aunque sin
paso intermedio de tablet — cosmético, no roto), `InformationSeller.vue` (ya tiene manejo
responsive extenso con `clamp()` y media queries propias), gráficas ApexCharts (responsive por
default de la librería).

**Aclaración sobre #9991075:** ese fix (commit `d5d4523d`) corrigió columnas Bootstrap y filas de
botones en `Sales.vue`/`ListCustomers.vue`/`CommissionsList.vue` — verificado sin regresión. Lo
que dejó fuera, en el mismo `CommissionsList.vue`, es el `q-splitter` (un widget de Quasar
distinto al patrón de columnas que se corrigió entonces).

---

## Limitaciones generales de esta auditoría

1. **100% estática** — no se ejecutó Playwright/navegador en ninguna de las 4 vistas (no había
   credenciales de prueba confirmadas como disponibles en esta vuelta de solo-lectura); todos los
   hallazgos se basan en lectura de código + comportamiento documentado/verificado de Bootstrap 5
   y Quasar contra el CSS **realmente servido** (`public/assets/css/bootstrap.css`,
   `public/plugins/quasar/css/quasar.prod.css`), no contra `node_modules`.
2. En CRM, el alcance real del hallazgo de `col-6` default en campos dinámicos depende de la
   configuración de catálogo en BD (`class_col` por campo), no verificable en esta pasada
   solo-lectura sin acceso a datos.
3. En Vendedores, el comportamiento de `q-tabs` en `Panel.vue`/`billing/index.vue` se asume con
   el modo scroll/flechas estándar de Quasar (no se marcó como hallazgo por no poder confirmarlo
   sin renderizar) — es el punto de menor certeza de esa sección.
4. No se revisaron a profundidad todos los sub-componentes de cada ficha/tab (ej. sub-tabs de
   Documentos/Servicios/Facturación en Clientes, o `DebtComponent`/`PaymentsComponent` en
   Vendedores) — se priorizó el layout general de cada vista sobre una cobertura exhaustiva de
   cada pantalla anidada.

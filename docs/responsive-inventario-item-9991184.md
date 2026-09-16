# Inventario responsive — Consolidado (item #9991184, Fase 2)

> **SOLO LECTURA.** Documento de fusión de los 3 inventarios parciales producidos por auditoría
> estática de código (Blade + Vue + `<style>` + CSS realmente servido), sin ejecutar
> Playwright/navegador. Ningún archivo de la aplicación fue modificado por ninguna de las 3
> auditorías ni por esta fusión — es el único entregable de la Fase 2.
>
> Fuentes fusionadas (se conservan como evidencia de trabajo, este documento es la referencia
> para las fases de corrección):
> - `docs/responsive-inventario-grupo-a.md` — Dashboard, Clientes, CRM, Vendedores (item #9991185)
> - `docs/responsive-inventario-grupo-b.md` — Facturación/Finanzas, Tickets, Inventario (item #9991186)
> - `docs/responsive-inventario-grupo-c.md` — Mapa de Red, Talento (Portal de Colaborador), Flotas (item #9991187)
>
> Fase 1 (header/topbar global) ya está cerrada y mergeada (item #9991183, commit `df7207a4`):
> **no se reportan aquí** hallazgos de header/avatar/logo, solo el contenido propio de cada vista.
>
> Breakpoints de referencia (`docs/patron-responsive-medussa.md`): móvil **< 640px**, tablet
> **640–1023px**, desktop **≥ 1024px**. El sidebar del layout colapsa a `max-width: 992px`
> (Bootstrap). Bootstrap servido confirmado **v5.0.2** (`public/assets/css/bootstrap.css:11`).
> Resoluciones objetivo evaluadas: **1920 · 1366 · 768 · 375**.

---

## Regla de fusión aplicada

Los 24 hallazgos individuales de los 3 grupos se consolidaron en **14 hallazgos**: cuando el
mismo patrón técnico (misma causa raíz) se repetía en vistas de distintos módulos, se fusionó en
**una sola entrada con la lista de todas las ubicaciones afectadas**, en vez de repetir el mismo
diagnóstico N veces. 5 hallazgos son fusiones (cubren 15 de los 24 originales); los 9 restantes
quedaron como hallazgos únicos sin patrón repetido en otra vista.

| Origen | Hallazgos originales | Alta | Media | Baja |
|---|---|---|---|---|
| Grupo A (Dashboard/Clientes/CRM/Vendedores) | 10 | 3 | 5 | 2 |
| Grupo B (Facturación/Tickets/Inventario) | 8 | 2 | 3 | 3 |
| Grupo C (Mapa de Red/Talento/Flotas) | 6 | 1 | 3 | 2 |
| **Total original** | **24** | **6** | **11** | **7** |
| **Total consolidado (este documento)** | **14** | **6** | **4** | **4** |

---

## Hallazgos — Severidad ALTA

| # | Hallazgo (causa raíz) | Ubicaciones afectadas | Qué se rompe | Severidad |
|---|---|---|---|---|
| 1 | **Clases de grid Bootstrap sin clase base `col-12`/`col-sm-*`** — la regla base de Bootstrap 5 `.row > *{width:100%}` solo deja de aplicar cuando el elemento SÍ trae una clase `.col-*` que cubra el breakpoint actual; si el markup solo declara clases para breakpoints altos (`col-xl-*`, `col-xxl-*`) sin una clase base (`col-12`, `col-sm-*`) que cubra los breakpoints bajos, el elemento queda **sin ancho asignado** por debajo del breakpoint más bajo declarado — y si el `.row` padre es `display:flex` (Quasar/algunos layouts), los hijos se comprimen lado a lado en vez de apilarse a 100%. | • Tickets detalle (`/tickets/ver/{id}`) — layout raíz 2 columnas, `VerTicket.vue:2-16` — móvil <576px, **alta**: columnas comprimidas lado a lado e ilegibles<br>• Tickets detalle — mismo layout, `VerTicket.vue:3,16` — tablet/laptop 576–1399px, **media**: el split de escritorio nunca se activa (recién entra a 1400px), se ve todo apilado incluso en laptop 1366×768<br>• Tickets detalle — título/autor de cada mensaje del thread, `Ticket.vue:45` + `TicketResponse.vue:42` — móvil, **media** (consecuencia secundaria del hallazgo anterior: el `calc(100% - 110px)` del contenedor queda con poco espacio al estar mal dimensionado el padre)<br>• CRM detalle (`/crm/editar/{id}`) — tarjetas "Información Principal"/"Datos geográficos", `InformationCrmCrud.vue:47,93` — tablet/desktop <1200px, **media**: `col-xl-6` sin base, sin ancho hasta 1200px<br>• CRM detalle — campo "ID", `InformationCrmCrud.vue:57,62` — móvil <576px, **baja**: impacto puntual (label+valor cortos) | Ver detalle por ubicación en la columna anterior — el patrón se repite en 3 vistas de 2 módulos distintos (Tickets, CRM) | **alta** (peor caso) |
| 2 | **Falta `flex-wrap` en contenedor flex de toolbar/header** — el contenedor usa `d-flex`/`justify-content-*` sin `flex-wrap: wrap`, así que cuando el contenido no cabe en una línea se comprime en vez de bajar a la siguiente. | • Vendedores listado (`/vendedores/`) — barra de herramientas (columnas + fullscreen + buscador), `VendedorListar.vue:31-63` — móvil <640px, **alta**: además `margin-left:16px` fijo sin `min-width` en el buscador, se comprime a casi 0<br>• Vendedores listado — encabezado de la tarjeta ("Listado de vendedores" + botón "Agregar Vendedor"), `VendedorListar.vue:5-16` — móvil, **media**<br>• CRM listado (`/crm/listar`) — fila de acciones de la tabla (menú "⋮" + buscador), `CrmDatatable.vue:84` — móvil, **media**: riesgo bajo-medio hoy, se agrava si el módulo usa más botones vía slot `header-extra` (ya lo usa para "Documentos huérfanos") | Elementos de la barra se comprimen/traslapan en vez de acomodarse en varias líneas | **alta** (peor caso) |
| 3 | **`q-tabs` de Quasar con `:breakpoint="0"`** — ese prop desactiva por completo el modo scroll/dropdown que Quasar activa normalmente cuando el contenedor es más angosto que el breakpoint; con `breakpoint="0"` nunca se activa, sin importar el ancho real. | • Ficha de cliente (`/cliente/editar/{id}`) — barra de pestañas (Información/Documentos/Servicios/Facturación/Estadísticas/Promociones + addons, 6+ pestañas), `ClientCrud.vue:2-9` — móvil y tablet, **alta**: etiquetas comprimidas/recortadas, área de toque reducida<br>• Talento — Portal de Colaborador, pestañas de "Mi material", `public/talento-portal/app.js:1144,1152` — móvil 375px, **baja**: quedan en fila con scroll horizontal en vez de selector compacto (menos pestañas, impacto menor) | Barra de pestañas no colapsa a modo compacto/scroll pese a que el viewport es angosto | **alta** (peor caso) |
| 4 | **Grid Bootstrap anidado con `class_col` vacío (doble/triple anidamiento fracciona el ancho)** — `InventoryItemStockListar.vue` anida `.row.col-9` → `.col-6` externo → `SelectComponentWithCheckbox.vue` que **también** es `.col-6` interno (porque el `class_col` que llega del catálogo está vacío en vez de `'full'`). El wrapper externo no lleva `partial-class-field`, así que no lo alcanza el único rescate responsivo del sistema (`@media max-width:500px{.col-6.partial-class-field{flex:0 0 100%}}`, `resources/sass/_modals.scss:51-58`). | • Inventario (`/inventory/inventory_item_stock`) — barra de filtros (Tipo de Artículo/Almacén/Asignado a), `InventoryItemStockListar.vue:3-4,22,40` + `SelectComponentWithCheckbox.vue:2-7` — móvil 375px, **alta**: cada select en ~50% de un contenedor ya angosto, label+control apretados<br>• misma barra de filtros — tablet 768px, **media**: el rescate a 500px no aplica, riesgo de recorte de etiquetas<br>• misma barra de filtros — desktop 1366/1920px, **baja (nota)**: visualmente aceptable por coincidencia de espacio, pero el markup sigue estructuralmente incorrecto | Barra de filtros comprimida en fracciones de 50%×50%×75% en vez de apilar en móvil | **alta** (peor caso) |
| 5 | Panel de vendedor → Facturación → sub-pestaña "Comisiones" (`/vendedores/{id}/seguimiento-vendedor/{seller_id}`, `/vendedores/seguimiento-me/`) — `q-splitter` divide "Ventas de la Casa"/"Ventas adicionales" en 2 paneles lado a lado (40%/60%) sin volverse vertical en ningún breakpoint (sin prop `horizontal` ni binding a `$q.screen`). | `resources/js/components/module/vendors/billing/components/invoice/CommissionsList.vue:21-26` | En 375px quedan columnas de ~150px/~225px con nombre de cliente, botón "Crear pago" y badges comprimidos | **alta** |
| 6 | Ficha de vehículo (`/flotas/{id}`) — barra de pestañas (`<ul class="nav nav-tabs flt-tabs">`, 8 pestañas: Información/Asignación/Mantenimientos/Documentos/Combustible/Tracking GPS/Historial/Fotos) sin `flex-nowrap`+`overflow-x:auto` ni colapso a dropdown (el componente ya tiene un patrón de "más opciones" reutilizable — dropdown `moreOpen`, líneas 42-52 — que no se aplicó a esta barra). *Síntoma similar al hallazgo #3 (barra de pestañas que no cabe en móvil), pero causa técnica distinta: Bootstrap `.nav-tabs` vs. prop `breakpoint` de Quasar — no se fusionan.* | `FleetVehicleShow.vue:85` | En 375px las 8 pestañas (ícono+texto) no caben en una fila y se parten en 3-4 renglones, empujando el contenido de la pestaña activa hacia abajo | **alta** |

---

## Hallazgos — Severidad MEDIA

| # | Hallazgo (causa raíz) | Ubicaciones afectadas | Qué se rompe | Severidad |
|---|---|---|---|---|
| 7 | **Componente/hoja de estilos sin NINGUNA regla `@media`** (grep confirmado: 0 coincidencias) — arquitectura sin puntos de quiebre declarados en absoluto. | • Mapa de Red (`/mapa-red`) — controles/leyenda flotante sobre el canvas Leaflet, `LeafletMapRed.vue` (3164 líneas, 0 `@media`) — móvil 375px: cualquier control flotante (toolbar de capas, leyenda, zoom/filtro) sin adaptación declarada<br>• Talento — Portal de Colaborador, todo el portal, `public/talento-portal/portal.css` (91 líneas, 0 `@media`) — desktop 1920/1366px: tarjetas/listas/botones `full-width` se estiran de borde a borde del viewport (portal diseñado mobile-first, sin contrapartida de ancho máximo) | Ver detalle por ubicación — mismo defecto técnico (ausencia total de `@media`), manifestado en direcciones opuestas (uno angosto, otro ancho) | **media** |
| 8 | Clientes — listado (`/cliente/listar`) — barra de filtros superior: el selector "Fecha de Corte" (`VueDatePicker`) tiene `width: 350px` inline y el selector "Estado" (`SelectFilter.vue`) fija `width: 200px` al `<div>` interno; en 375px, restando el padding de `.q-pa-md`+`q-card`, el primero ya casi no cabe solo, sumando presión al mismo renglón. | `DatatableClient.vue:34-44` (`style="width: 350px"`); `SelectFilter.vue:8` (`style="width: 200px"`) | Desborda ese segmento de la barra de filtros en móvil, aunque el contenedor sea `flex-wrap` | **media** |
| 9 | CRM — detalle/edición (`/crm/editar/{id}`) — campos dinámicos tipo texto (`ComponentFormDefault` → `InputText.vue`): el default es `col-6` (50% de ancho) **sin ninguna regla responsiva**, aplicado siempre fuera de cualquier `@media`. No se pudo verificar la config real de cada campo en BD (catálogo data-driven) — el defecto de código es cierto, su alcance real en CRM no se confirmó al 100%. | `InputText.vue:3-7` | Cualquier campo sin `class_col:'full'` queda a mitad de ancho incluso en celular | **media** |
| 10 | Mapa de Red (`/mapa-red`) — panel lateral de elemento (`ElementSidePanel.vue`, `.element-side-panel{position:fixed;top:0;height:100vh;z-index:2000}`) ocupa el 100% del alto del viewport sin descontar la altura del header/topbar global del layout admin — se dibuja por encima en vez de respetar su espacio (el fix de header responsive de #9991183 no cubrió este panel, que vive en otro módulo). Pendiente de confirmar visualmente si el `z-index:2000` gana o pierde contra el header fijo en cada resolución. | `ElementSidePanel.vue` | El panel puede tapar o quedar tapado por el header fijo del admin | **media** |

---

## Hallazgos — Severidad BAJA

| # | Hallazgo (causa raíz) | Ubicaciones afectadas | Qué se rompe | Severidad |
|---|---|---|---|---|
| 11 | Dashboard (`/dashboard`) — fila de tarjetas KPI (Tickets por estado): usa `col-md-3 col-xl-3` sin un paso intermedio para tablet (ej. `col-sm-6`); en 768px cae directo a 4 columnas de ~180px en vez de 2 columnas más cómodas. No hay overflow ni texto cortado — apretado, no roto. | `TarjetTicket.vue:2` | Tarjetas KPI visualmente apretadas en tablet | **baja** |
| 12 | Facturación — listado de facturas (`/finanzas/facturas`) — el breakpoint del fix responsivo propio del componente es `@media (max-width: 575.98px)` en vez de 639px (el corte "móvil" real del proyecto); `.dt-search{min-width:200px}` queda fuera de esa media query. Impacto bajo: el contenedor ya tiene `flex-wrap`, en el peor caso los controles pasan a 2 líneas, sin overflow. | `resources/js/components/base/shared/Datatable.vue:1053-1073` | Transición menos prolija entre 576-639px (componente compartido por varias tablas del sistema, incluida `/finanzas/invoices`) | **baja** |
| 13 | Inventario (`/inventory/inventory_item_stock`) — barra de herramientas superior: la clase `justify-between` **no existe en Bootstrap** (`public/assets/css/bootstrap.css` solo define `.justify-content-between`; `justify-between` es nomenclatura de Tailwind) → no-op, el `d-flex` cae a `justify-content:flex-start` por defecto. | `InventoryItemStockListar.vue:2` | Bloque de filtros + botones "Realiza un Movimiento"/"Agregar" quedan pegados a la izquierda en vez de distribuirse en extremos (desktop, sin overflow) | **baja** |
| 14 | Flotas — ficha de vehículo (`/flotas/{id}`) — barra fija inferior (`.flt-fixed-bar`, botones "Eliminar"/"Volver a Flotas"): `padding: 12px 24px` fijo no se reduce en móvil; con solo 2 botones probablemente no desborda, pero el padding lateral resta proporcionalmente más ancho útil cuanto más angosto es el viewport. | `FleetVehicleShow.vue` línea 140 (template) / línea 417 (CSS) | Menos ancho útil disponible en la barra fija en 375px | **baja** |

---

## Deuda de plataforma señalada por el inventario (no un hallazgo puntual)

El patrón `class_col==='full'?'col-12':'col-6 partial-class-field'` (ver hallazgo #4) está
repetido en **~29 componentes compartidos** de `resources/js/shared/` (usados por ~60 CRUDs del
sistema, no solo Inventario), y el único rescate responsivo es el media query a `max-width:500px`
de `_modals.scss` — un umbral **menor** al breakpoint móvil documentado del proyecto (<640px).
Existe una franja 500–639px donde un campo `partial` sigue en `col-6` fijo. El hallazgo #4 es un
caso puntual agravado (doble anidamiento fuera de cualquier modal); un `modal-lg`/`modal-xl` con
un campo `partial` sin tope de ancho propio quedaría expuesto en esa misma franja en cualquier
otro módulo del sistema.

---

## Vistas auditadas sin hallazgos relevantes (ya resueltas correctamente)

Para no restar tiempo a la fase de corrección, se deja constancia breve de lo que **ya está bien**
y no requiere trabajo: Dashboard (tarjetas `CardStats`/`CardTextDashboard` apilan sin desbordar),
Clientes — tabla del listado (scroll horizontal nativo de Quasar) y pestaña "Información" de la
ficha (ya usa `grid-template-columns:1fr` en `@media max-width:991px`), CRM — tabla y pestaña
"Documentos" (usan `Datatable.vue` base con `flex-wrap` correcto), Vendedores — `SellerListar.vue`,
`Menu.vue`, `Dashboard.vue`/`TarjetCard.vue`, `InformationSeller.vue` y gráficas ApexCharts,
Facturación — captura de pago (grid apila 100% por regla base Bootstrap 5) y Facturas Proforma,
Tickets — listado abierto y los 4 modales del detalle, Inventario — los 3 modales del listado
(`InventoryItemCrud.vue`, `InventoryIncrementDecrementStock.vue`, `MediaItem.vue`), Mapa de Red —
los 3 diálogos modales (`max-width:80vw`/`90vw`) y la tabla de salud (`overflow-x:auto`), Talento
Portal — el patrón de `q-drawer` responsivo del sidebar (`show-if-above`+botón `lt-md`), Flotas —
dashboard completo (`FleetDashboard.vue`, `flex-wrap` + grid escalonado + `@media max-width:991px`
propio) y el contenedor/header de la ficha de vehículo (`max-width:1200px`, `min-width:0`).

---

## Limitaciones generales (consolidadas de los 3 grupos)

1. **100% estática** — ninguna de las 3 auditorías ejecutó Playwright/navegador (no había
   credenciales de prueba confirmadas como disponibles en esas vueltas de solo-lectura); todos los
   hallazgos se basan en lectura de código + comportamiento documentado/verificado de Bootstrap 5
   (v5.0.2 confirmado) y Quasar contra el CSS/JS **realmente servidos**
   (`public/assets/css/bootstrap.css`, `public/plugins/quasar/css/quasar.prod.css`,
   `public/plugins/quasar/js/quasar.umd.prod.js`, `public/css/app.css` compilado, `public/talento-portal/*`),
   no contra `node_modules`.
2. **CRM** — el alcance real del hallazgo #9 (`col-6` default en campos dinámicos) depende de la
   configuración de catálogo en BD (`class_col` por campo), no verificable en esa pasada
   solo-lectura sin acceso a datos.
3. **Vendedores** — el comportamiento de `q-tabs` en `Panel.vue`/`billing/index.vue` se asumió con
   el modo scroll/flechas estándar de Quasar (no se marcó como hallazgo por no poder confirmarlo
   sin renderizar) — punto de menor certeza de esa sección.
4. **Inventario** — se hizo una única consulta de solo-lectura vía `php artisan tinker` contra la
   BD de dev para confirmar el `class_col` real persistido de los campos DB-driven del módulo
   `InventoryItem` (no se puede saber de forma fiable solo por grep en migraciones incrementales) —
   no se escribió nada en la BD.
5. **Tickets** — no se auditaron a profundidad los 4 modales del detalle más allá de un grep
   dirigido (sin coincidencias del patrón de bug conocido).
6. **`/finanzas/notificaciones-pendientes`** — no se pudo auditar (404 preexistente, ya documentado
   en `CLAUDE.md` como bug conocido, ajeno a esta auditoría).
7. **Clientes/Vendedores/Inventario** — no se revisaron a profundidad todos los sub-componentes de
   cada ficha/tab (ej. sub-tabs de Documentos/Servicios/Facturación en Clientes, `DebtComponent`/
   `PaymentsComponent` en Vendedores, tabs de "artículos por cliente/vendedor" que viven en esos
   módulos pero no en Inventario propiamente) — se priorizó el layout general sobre cobertura
   exhaustiva de cada pantalla anidada.
8. **Mapa de Red** — pendiente de confirmar visualmente si el `z-index:2000` del panel lateral
   realmente gana o pierde contra el header fijo del admin en cada resolución, y si la toolbar de
   capas/filtros del mapa (controles Leaflet inyectados en runtime, difíciles de ubicar por grep)
   se sale de pantalla en 375px.
9. **Flotas** — pendiente de confirmar visualmente cuántos renglones exactos ocupa la barra de 8
   pestañas en 375px real, y si el salto de línea dentro de cada `<a class="nav-link">` corta algún
   ícono/label.

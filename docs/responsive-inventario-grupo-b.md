# Inventario responsive — Grupo B: Facturación/Finanzas, Tickets, Inventario

> **SOLO LECTURA.** Auditoría estática de código (Blade + Vue + `<style>` + CSS realmente servido),
> sin ejecutar Playwright/navegador (no había credenciales de prueba disponibles en esta vuelta).
> Ningún archivo de la aplicación fue modificado — este documento es el único entregable.
> Mismo método y formato que el Grupo A (`docs/responsive-inventario-grupo-a.md`).
>
> Fase 1 (header/topbar global) ya está cerrada y mergeada (item #9991183): **no se reportan**
> hallazgos de header/avatar/logo, solo el contenido propio de cada vista.
>
> Breakpoints de referencia (`docs/patron-responsive-medussa.md`): móvil **< 640px**, tablet
> **640–1023px**, desktop **≥ 1024px**. El sidebar del layout colapsa a `max-width: 992px`
> (Bootstrap) — a partir de 640px ya no hay sidebar visible. Bootstrap servido confirmado **v5.0.2**
> (`public/assets/css/bootstrap.css:11`), cuya regla base `.row > *{width:100%}` hace que cualquier
> `.col-md-*`/`.col-lg-*`/`.col-xxl-*` **sin** clase base `col-12`/`col-sm-*` siga apilando a 100% en
> viewports por debajo de su propio breakpoint — salvo cuando el ancho real disponible para el `.row`
> ya está acotado por un contenedor angosto (un modal `~500px`, por ejemplo), caso en el que un
> `col-6` interno sí puede quedar apretado aunque nominalmente "apile".

---

## Resumen de severidad

| Vista | Alta | Media | Baja |
|---|---|---|---|
| Facturación — Listado (`/finanzas/facturas`) | 0 | 0 | 1 |
| Facturación — Captura de pago (`/finanzas/captura-pago`) | 0 | 0 | 0 |
| Facturación — Facturas Proforma (`/finanzas/invoices`, nota secundaria) | 0 | 0 | 0 |
| Tickets — Listado (`/tickets/abiertos`) | 0 | 0 | 0 |
| Tickets — Detalle (`/tickets/ver/{id}`) | 1 | 2 | 0 |
| Inventario — Listado + modales (`/inventory/inventory_item_stock`) | 1 | 1 | 2 |

---

## Facturación / Finanzas

### Listado de facturas (`/finanzas/facturas`)

Componente: vista `resources/views/meganet/module/finance/invoice/index.blade.php` →
`FinanceInvoiceController::index()` → monta `<Datatable>`
(`resources/js/components/base/shared/Datatable.vue`), componente genérico compartido por otras
tablas del sistema (incluida `/finanzas/invoices`, ver nota secundaria abajo).

| Vista/Ruta | Resolución afectada | Qué se rompe | Severidad | Causa raíz (archivo:línea) |
|---|---|---|---|---|
| `/finanzas/facturas` — barra de herramientas de la tabla | 576–639px (zona "móvil" del proyecto, <640px, que no coincide con el corte `sm` de Bootstrap que usa el componente) | El breakpoint del fix responsivo propio del componente es `@media (max-width: 575.98px)` en vez de 639px, y `.dt-search{min-width:200px}` queda fuera de esa media query. Impacto bajo: el contenedor padre ya tiene `flex-wrap`, así que en el peor caso los controles pasan a 2 líneas — no hay overflow ni corte de contenido, solo una transición menos prolija que por debajo de 576px. | baja | `resources/js/components/base/shared/Datatable.vue:1053-1073` |

**Sin hallazgos adicionales:** la tabla es `q-table` de Quasar (no HTML/DataTables crudo) — Quasar
aplica `.q-table__middle.scroll{overflow:auto}` (verificado contra
`public/plugins/quasar/css/quasar.prod.css`), así que las 8-9 columnas típicas de facturación
(folio/fecha/cliente/concepto/subtotal/IVA/total/estado/acciones) hacen scroll horizontal nativo sin
desbordar el layout de la página. Los anchos fijos en celdas (`width:'20px'` para `id`,
`max-width:'250px'` para el resto, `Datatable.vue:192,207-210`) son el mecanismo intencional que
fuerza ese scroll en vez de comprimir columnas ilegibles (texto con `lines="1"`/ellipsis, no
desborda). La toolbar superior ya trae `flex-wrap` correcto (líneas 26 y 38). La clase `max-w-200`
usada en los `<q-th>` no tiene ninguna regla CSS definida en el proyecto — es inerte, sin efecto de
layout.

### Captura de pago (`/finanzas/captura-pago`)

Componente: `app/Modules/Addons/Payments/views/captura-pago.blade.php` → monta
`<manual-payment-capture>` → `resources/js/components/module/finance/ManualPaymentCapture.vue`.

**Sin hallazgos:** el grid (`col-md-4` para monto/fecha/método, `col-md-6` para clave/titular/banco/
cuenta, `col-12` para comprobante — líneas 49-91) apila a 100% de ancho por defecto en las 4
resoluciones evaluadas porque Bootstrap 5 (confirmado v5.0.2) define `.row > *{width:100%}` como
regla base independiente de la clase `.col-*` que traiga el hijo, y `.col-md-4`/`.col-md-6` solo se
activan dentro de `@media (min-width:768px)` — a 375px no hay ningún div "sin ancho asignado" (a
diferencia de Bootstrap 4, donde esa combinación sí estaría rota). Sin anchos fijos en px que puedan
desbordar (único `max-width:900px` es un tope superior en `.mpc-wrap`, línea 216, no un mínimo). El
dropdown de búsqueda de cliente (`position-absolute w-100`, `max-height:260px;overflow:auto`) está
contenido dentro de su padre `position-relative`. No hay campos condicionales por método de pago en
esta pantalla (ese patrón, `PAYMENT_METHOD_FIELDS`, vive en un componente distinto —
`ClientCrudPayment.vue`, modal "Crear Gasto" de la ficha de cliente, fuera de este alcance) — los 7
campos son siempre los mismos, sin riesgo de que aparecer/desaparecer rompa el grid.

### Notificaciones pendientes (`/finanzas/notificaciones-pendientes`)

**No evaluable — 404 preexistente**, ya documentado en `CLAUDE.md` como bug conocido (el link del
sidebar de Finanzas apunta a una ruta sin registrar). Confirmado con
`grep -rn "notificaciones-pendientes" routes/ app/Modules/Addons/*/routes.php` → 0 resultados. No se
reporta como hallazgo de responsividad.

### Nota secundaria — Facturas Proforma (`/finanzas/invoices`)

Ruta viva adyacente descubierta durante la investigación (entrada separada del sidebar, gate
`finance_view_invoices`, distinta de "Facturas"/`finance_view_billing`), no era el objetivo del
item pero se auditó de paso por reutilizar el mismo `<Datatable>` compartido: mismo análisis de
arriba (sin hallazgo). Su fila de filtros (`InvoiceListar.vue`) usa `col-md-6` sin base explícita en
la primera fila (segura por la regla `.row > *` de Bootstrap 5, igual que en captura de pago) y
`col-12 col-md-6 col-lg-3` correctamente escalonado en la segunda fila. Sin hallazgos.

---

## Tickets

### Listado de tickets abiertos (`/tickets/abiertos`)

Componente: `resources/views/meganet/module/tickets/opened.blade.php` → monta `<Datatable>`
(mismo componente genérico que Facturación).

**Sin hallazgos:** `opened.blade.php` solo pasa `module/model/add/list/filters` al `Datatable`
genérico (sin `select_filter` ni `multipleFilters`), así que el único contenido real es
`Breadcrumb` (ya usa `col-12`, patrón correcto) + la tabla `q-table` de Quasar. Mismo mecanismo de
scroll nativo que en Facturación (`q-table__middle.scroll{overflow:auto}`, verificado contra
`quasar.prod.css`). `Datatable.vue` ya trae su propio fix responsivo (líneas 1049-1074,
`@media max-width:575.98px`) que reacomoda la toolbar y el buscador. Toolbar con `flex-wrap`
correcto. No hay anchos fijos en px que desborden — el único `width:200px` del módulo de filtros
(`SelectFilter.vue:8`) ni siquiera se renderiza aquí, porque `opened.blade.php` no pasa
`select_filter`.

### Detalle de un ticket (`/tickets/ver/{id}`)

Componente raíz: `resources/js/components/module/tickets/VerTicket.vue`.

| Vista/Ruta | Resolución afectada | Qué se rompe | Severidad | Causa raíz (archivo:línea) |
|---|---|---|---|---|
| `/tickets/ver/{id}` — layout raíz de 2 columnas (ficha de cliente + thread) | móvil (<576px) | El split usa `col-sm-12 col-xxl-3` (ficha) / `col-sm-12 col-xxl-9` (thread) **sin clase base `col-12`**. `.col-sm-12` solo existe dentro de `@media (min-width:576px)` (Bootstrap 5 servido) — por debajo de 576px ningún grid class aplica, y como el `.row` padre es `display:flex`, ambos `<div>` quedan como flex items sin ancho definido: en vez de apilarse a ancho completo, el navegador intenta acomodarlos lado a lado y los comprime en columnas angostas/ilegibles. | **alta** | `resources/js/components/module/tickets/VerTicket.vue:2-16` |
| `/tickets/ver/{id}` — mismo layout de 2 columnas | tablet y laptop (576–1399px, cubre tanto 768px como 1366px) | El split de escritorio (sidebar 25% / thread 75%) **nunca se activa** en este rango: solo `col-sm-12` está vigente (`width:100%`, ambos paneles apilados), porque `.col-xxl-3`/`.col-xxl-9` recién entran en `@media (min-width:1400px)`. En 1366×768 (laptop estándar, una de las 4 resoluciones evaluadas) el usuario ve la ficha del cliente apilada arriba del thread en vez del diseño lado-a-lado intencional. No hay overflow ni texto cortado, pero el diseño de 2 columnas solo se ve en monitores ≥1400px. | media | `resources/js/components/module/tickets/VerTicket.vue:3,16` |
| `/tickets/ver/{id}` — título/autor de cada mensaje del thread | móvil (<576px), secundario al hallazgo anterior | `.comment-title-wrapper` usa `style="width: calc(100% - 110px)"` — escala con el contenedor (no es un px fijo, no rompe por sí solo), pero al quedar el contenedor del thread mal dimensionado por el hallazgo de arriba, ese `calc()` puede terminar con muy poco espacio, comprimiendo título/fecha del mensaje. Sin media query que lo compense (`_ticket.scss` tiene 0 ocurrencias de `@media`). | media | `resources/js/components/module/tickets/component/Ticket.vue:45` y `resources/js/components/module/tickets/component/TicketResponse.vue:42` (mismo patrón duplicado) |

**Sin hallazgos adicionales:** los modales del detalle (`TicketModalEdit.vue`,
`TicketSatisfactionModal.vue`, `CrearTareaModal.vue`, `TicketNewThread.vue`) son formularios/listas
verticales simples — grep exhaustivo de anchos fijos, grid Bootstrap, `d-flex`, `q-splitter`/
`q-tabs`/`breakpoint` no encontró ninguna coincidencia; el patrón de bug ya conocido en el proyecto
(`breakpoint=0` de `ClientCrud.vue`, `q-splitter` sin volverse vertical de `CommissionsList.vue`) no
está presente en Tickets. **Nota positiva:** `.message-pre` de `TicketResponse.vue` usa
`white-space: pre-wrap; word-wrap: break-word`, evitando desborde horizontal por mensajes largos o
cadenas sin espacios.

---

## Inventario

Alcance confirmado: **no existe** una ruta GET de "ficha"/detalle dedicada para un artículo de stock
(`app/Modules/Addons/Inventario/routes.php:53-67` — solo `index`/`add`/`change_stock`/`update`/
`destroy`/`table`/`get_items_by_*`/`accept_item_by_movement`/`reject_item_by_movement`/
`get_media_by_item`/`upload_media`/`delete_media`). El "detalle" de un artículo son **3 modales
Bootstrap** montados dentro del propio listado (`InventoryItemStockListar.vue:90-168`):
`#crudinventoryitem` (crear/editar), `#modalchange_item_store` (mover artículo) y `#modal_media_item`
(galería de fotos). La **custodia** solo se expone en esta UI admin como filtro "Asignado a"
(`user_id`) en la barra de filtros del listado — no hay panel de custodia por artículo aquí (el
panel de custodia self-scoped real vive en el Portal de Colaborador — "Mi material" —, fuera de
alcance de este módulo admin).

Componente raíz: `resources/js/components/module/inventory/inventory_item_stock/
InventoryItemStockListar.vue`.

| Vista/Ruta | Resolución afectada | Qué se rompe | Severidad | Causa raíz (archivo:línea) |
|---|---|---|---|---|
| `/inventory/inventory_item_stock` — barra de filtros (Tipo de Artículo / Almacén / Asignado a) | móvil (375px) | Los 3 selects quedan doblemente anidados en grid Bootstrap sin variante `col-12`/`col-sm-*`: `.row.col-9` (75%) → `.col-6` externo (50% de eso) → raíz interna de `SelectComponentWithCheckbox` que **también** es `.col-6` (50% de eso, porque `class_col` llega vacío en vez de `'full'`). El wrapper externo no lleva la clase `partial-class-field`, así que no lo alcanza el único media query de rescate del sistema (`@media max-width:500px{.col-6.partial-class-field{flex:0 0 100%}}`, `resources/sass/_modals.scss:51-58`, compilado en `public/css/app.css:2415`). Cada select termina en ~50% de un contenedor ya angosto — label + control `select2` apretados en pocas decenas de px. | **alta** | `resources/js/components/module/inventory/inventory_item_stock/InventoryItemStockListar.vue:3-4,22,40` anidando `resources/js/shared/SelectComponentWithCheckbox.vue:2-7` |
| `/inventory/inventory_item_stock` — misma barra de filtros | tablet (768px) | Mismo anidamiento doble; el rescate a 500px no aplica (768>500). Hay ancho de sobra en el viewport (sidebar ya colapsado ≤992px), pero el grid interno igual lo reparte en fracciones de 50%×50%×75%, con riesgo de recorte de las etiquetas de los selects. | media | mismas líneas que arriba |
| `/inventory/inventory_item_stock` — misma barra de filtros | desktop (1366px / 1920px) | El mismo anidamiento produce, por coincidencia de espacio disponible, anchos de ~200-300px por select — visualmente aceptable, sin overflow ni recorte real. Se deja constancia de que el markup sigue siendo estructuralmente incorrecto aunque no se note en desktop. | baja (nota) | mismas líneas que arriba |
| `/inventory/inventory_item_stock` — barra de herramientas superior | desktop (1366px / 1920px, donde sobra espacio horizontal) | La clase `justify-between` **no existe en Bootstrap** (`public/assets/css/bootstrap.css` solo define `.justify-content-between`; `justify-between` es nomenclatura de Tailwind) → no-op, el `d-flex` cae a `justify-content:flex-start` por defecto y el bloque de filtros + botones "Realiza un Movimiento"/"Agregar" quedan pegados a la izquierda en vez de distribuirse en extremos. No causa overflow, solo desalineación visual respecto a lo pretendido. | baja | `resources/js/components/module/inventory/inventory_item_stock/InventoryItemStockListar.vue:2` |

**Sin hallazgos adicionales:** la tabla (`Datatable.vue`, `q-table` de Quasar) tiene el mismo scroll
horizontal nativo y fix responsivo ya descritos arriba. El modal `#crudinventoryitem`
(`InventoryItemCrud.vue`) — confirmado contra la BD de dev que los 13 campos del módulo
`InventoryItem` tienen `class_col='full'` → se renderizan `col-12` (apilados) en las 4 resoluciones,
sin riesgo. El modal `#modalchange_item_stock` (`InventoryIncrementDecrementStock.vue`) usa
`class_col:'full'` con `class_field:'col-sm-12 col-md-9'`/`class_label:'col-sm-12 col-md-3'`, patrón
responsivo correcto con base `col-sm-12`. La galería `#modal_media_item` (`MediaItem.vue`, grid
`col-md-4` sin `col-12` explícito) es segura bajo Bootstrap 5 por la misma regla base `.row > *`
descrita arriba — 1 columna en móvil, 3 desde tablet, sin overflow en ninguna resolución (el modal es
`modal-xl`, con topes de 800px/1140px desde 992px/1200px). El modal `#modalchange_item_store`
(`InventoryMovementAll.vue`) sí usa el patrón `col-6` (`class_col:'partial'`), pero el modal
contenedor no tiene modificador de tamaño (tope real ~500px), así que el rescate a 500px del sistema
SÍ alcanza a estos campos, y a partir de 576px un 50/50 de un contenedor de 500px sigue dando
~200-230px por campo — no se rompe en ninguna de las 4 resoluciones evaluadas.

**Nota estructural (deuda de plataforma, no un hallazgo de este módulo):** el patrón
`class_col==='full'?'col-12':'col-6 partial-class-field'` está repetido en ~29 componentes
compartidos de `resources/js/shared/` (usados por ~60 CRUDs del sistema, no solo Inventario), y el
único rescate responsivo es el media query a `max-width:500px` de `_modals.scss` — un umbral **menor**
al breakpoint móvil documentado del proyecto (<640px). Existe una franja 500-639px donde un campo
`partial` sigue en `col-6` fijo. En Inventario esto no rompe nada porque los modales afectados están
acotados a ~500px de ancho propio, pero un modal `modal-lg`/`modal-xl` con un campo `partial` sin ese
tope quedaría expuesto en esa franja. El hallazgo de severidad **alta** de este módulo (barra de
filtros del listado, arriba) es un caso puntual de ese mismo patrón estructural, agravado por el doble
anidamiento `col-9 > col-6 > col-6` fuera de cualquier modal.

---

## Limitaciones generales de esta auditoría

1. **100% estática** — no se ejecutó Playwright/navegador en ninguna de las 6 vistas auditadas (no
   había credenciales de prueba confirmadas como disponibles en esta vuelta de solo-lectura); todos
   los hallazgos se basan en lectura de código + comportamiento documentado/verificado de Bootstrap 5
   (confirmado v5.0.2) y Quasar contra el CSS/JS **realmente servidos**
   (`public/assets/css/bootstrap.css`, `public/plugins/quasar/css/quasar.prod.css`,
   `public/plugins/quasar/js/quasar.umd.prod.js`, `public/css/app.css` compilado), no contra
   `node_modules`.
2. En Inventario se hizo una única consulta de solo-lectura vía `php artisan tinker` contra la BD de
   dev para confirmar el `class_col` real persistido de los campos DB-driven del módulo
   `InventoryItem` (no se puede saber de forma fiable solo por grep en migraciones incrementales) —
   no se escribió nada en la BD.
3. En Tickets no se auditaron a profundidad los 4 modales del detalle más allá de un grep dirigido
   (sin coincidencias del patrón de bug conocido) — se priorizó el layout raíz de la pantalla sobre
   una cobertura exhaustiva de cada modal anidado.
4. En Inventario quedaron fuera de alcance (mencionados solo de pasada) los tabs de "artículos por
   cliente/vendedor" (`resources/js/components/module/client/document/inventory_items/index.vue` y
   el equivalente de `sellers/`), porque viven en los módulos Clientes/Vendedores, no en Inventario
   propiamente.
5. La ruta `/finanzas/notificaciones-pendientes` no se pudo auditar (404 preexistente, ya documentado
   en `CLAUDE.md` como bug conocido, ajeno a esta auditoría).

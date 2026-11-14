# Patrón de referencia: módulo de Clientes (para alinear Marketing)

Estudio de solo lectura, 13-nov-2026 (agente-frontend, Fase 2). Instrucción de Irving vía Henry: Marketing debe verse
como **Clientes** (`/cliente` y `/cliente/listar`). Aquí queda lo que usa Clientes, para aplicarlo después **sin tocar
nada de Clientes ni componentes compartidos**. Capturas de referencia (vista previa en `megaisp_test`, sin clientes):
`/tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/fase2/frontend/ref/`
(`cliente-listar-1440.png`, `cliente-dashboard-1440.png`, `cliente-crear-1440.png`).

## 1. Archivos

| Pieza | Archivo |
|---|---|
| Rutas | `app/Modules/Core/Clientes/routes.php` (`/cliente` → `DashboardController@index`, `/cliente/listar` → `ClientController@index`) |
| Vistas Blade | `resources/views/meganet/module/client/index.blade.php` (listado), `dashboard.blade.php`, `add.blade.php`, `edit.blade.php` |
| Listado (Vue) | `resources/js/components/module/client/DatatableClient.vue` (1182 líneas; propio de Clientes, carga por servidor) |
| Dashboard (Vue) | `resources/js/components/module/client/DashboardClient.vue` |
| Breadcrumb | `resources/js/components/base/shared/Breadcrumb.vue` (registrado en el root de `app.js`, se usa desde Blade) |
| Tabla compartida genérica | `resources/js/components/base/shared/Datatable.vue` (atada a `POST /{module}/table`: **no** sirve para las API de Marketing sin modificarla) |
| Filtros de la tabla | `resources/js/components/base/shared/SelectFilter.vue`, `FilterDataTable.vue` |
| Submenú lateral | `resources/views/module-sidebar/clientes.blade.php` |
| Listados largos (#1342) | clase global `tabla-listado` (`resources/sass/base/quasar/_tabla_listado.scss`, ya compilada en `public/css/app.css`) + `tituloSiCorta` de `resources/js/helpers/tablaListado.js` |

## 2. Breadcrumb

En la **vista Blade**, primera línea de `@section('content')`, fuera del componente de la pantalla:

```blade
<Breadcrumb :list=[{title:"Pagina"},{title:"Cliente",active:"active"}]></Breadcrumb>
```

- Marcado que pinta (`Breadcrumb.vue`): `div.row > div.col-12 > div.page-title-box.d-sm-flex.align-items-center >
  div.page-title-right > ol.breadcrumb.m-0 > li.breadcrumb-item[.active]`. Un item con `a` se pinta como enlace.
- Se ve arriba a la izquierda del contenido, «Pagina › Cliente», texto gris pequeño.
- Para Marketing: `[{title:"Página"},{title:"Marketing",a:"/marketing"},{title:"<Sección>",active:"active"}]` en cada
  vista de `app/Modules/Addons/Marketing/views/**` (antes del tag de la pantalla). Clientes escribe «Pagina» sin acento;
  en Marketing va «Página» (todo en español correcto).

## 3. Tarjeta del listado (título y filtros en la misma fila)

`DatatableClient.vue`:

```html
<div class="q-pa-md">
  <q-card>
    <q-table :title="list" … no-data-label="No hay elementos para mostrar"
             :rows-per-page-label="'Elementos por página'" class="q-pa-sm">
      <template v-slot:top="props">
        <div class="d-flex flex-wrap gap-2 mb-2">
          <div class="text-h6">{{ list }}</div>          <!-- «Listado de Clientes» -->
          <VueDatePicker placeholder="Fecha de Corte" style="width: 350px" … />
          <select-filter name="…estado" … />              <!-- «Estado  [Seleccionar…]» -->
          <select-filter label="Tipo de cliente" … />
          <a class="btn btn-success waves-effect waves-light" :href="`/${module}/crear`">{{ add }}</a>
        </div>
        <div class="d-flex ms-auto align-items-center">
          <q-btn flat round dense icon="mdi-dots-vertical">…menú: Ver columnas, Actualizar, Exportar…</q-btn>
          <q-input class="dt-search mb-0" dense outlined placeholder="Buscar" … />
        </div>
      </template>
      …
```

- **Una sola tarjeta** (`q-card`, fondo blanco, sombra de Quasar, esquinas 4 px) que contiene título, filtros y tabla.
- **Título**: Quasar `text-h6` (1.25 rem ≈ 20 px, peso 500), dentro de la tarjeta, sin ícono.
- **Filtros en la misma fila** que el título (`d-flex flex-wrap gap-2`), con **ancho fijo** (selects ~200 px, fecha 350 px)
  y etiqueta a la izquierda («Estado», «Tipo de cliente»). Nunca a todo lo ancho.
- **Búsqueda** a la derecha (`ms-auto`), `q-input dense outlined` con placeholder «Buscar», y a su izquierda un
  `q-btn flat round dense` de tres puntos con las acciones de la tabla.
- **Botón de alta**: Bootstrap `btn btn-success waves-effect waves-light` (verde del sistema).

## 4. Tabla

- `q-table` de Quasar dentro de la `q-card`, clase `q-pa-sm`, encabezados por slot `header` con `q-th` (texto oscuro,
  negritas, ~12 px, **sin mayúsculas**), filas por slot `body`.
- Textos en español por props: `no-data-label="No hay elementos para mostrar"`, `rows-per-page-label="Elementos por
  página"`, `selected-rows-label` en español. (Quasar está en inglés por defecto: sin estas props sale «No data
  available», «Rows per page», «1-10 of 50».) Faltan también `no-results-label`, `loading-label` y `pagination-label`.
- Paginación al pie con las opciones de `rowPerPageOptions`; modo oscuro con `:dark="darkMode"`.
- Para Marketing: `DatatableClient` no es reutilizable (está atado a clientes y a carga por servidor) y `Datatable.vue`
  tampoco sin modificarla → se replica el **marcado y las props** en `kit/MktTablaEjecutiva.vue` y se le agrega la clase
  global `tabla-listado` + `@mouseover="tituloSiCorta"` (#1342: corta con «…», tooltip con el texto completo, columna
  de acciones fija a la derecha).

## 5. Fichas y formularios

- `cliente/crear` (`add.blade.php`): Bootstrap `card` con `card-header` «Cliente» (≈15 px, negritas), formulario en
  dos columnas «etiqueta a la izquierda : campo», pie con botones Bootstrap `btn btn-secondary` («Atras») y
  `btn btn-info` (acción principal), centrados.
- `cliente` (dashboard): Bootstrap `card` con encabezado «👤 Clientes» y `list-group` de indicadores con su número a la
  derecha (enlaces en azul del sistema).

## 6. Submenú lateral

`resources/views/module-sidebar/clientes.blade.php`:

```blade
<li>
    <a href="javascript: void(0);" class="has-arrow">
        <i data-feather="user-check"></i>
        <span data-key="t-cliente">{{ $item->sidebar_label ?? 'Clientes' }}</span>
    </a>
    <ul class="sub-menu" aria-expanded="false">
        <li><a href="{{ url('/cliente/listar') }}">
            <span data-key="t-cliente-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
        </a></li>
        …
```

- Ícono del grupo con **Feather** (`data-feather`); cada hijo con `<small><i class="fa fa-fw fa-…"></i></small>` (Font
  Awesome 5) **dentro del `span` con `data-key`**. Permiso por hijo con `@can`/`can()`.
- El submenú de Marketing (Fase 2, §2.11) ya usa el mismo marcado (`small > i.fa.fa-fw`) desde `module.json`; falta solo
  poner el `data-key` en el `span` (usar `t-marketing-<slug>` para no chocar con las claves viejas `t-mkt-*` que la
  prueba exige que ya no existan).

## 7. Botones y colores

- Clientes usa **Bootstrap**: `btn btn-success` (crear), `btn btn-info` (acción principal de formulario),
  `btn btn-secondary` (volver), `btn-sm btn-warning` (avisos). Tamaño `.btn`: letra 0.8125 rem, relleno .47rem .75rem,
  esquinas .25 rem, sin mayúsculas, sin sombra.
- En Marketing los `q-btn` del kit son de Quasar (`unelevated no-caps color="primary"`, más altos y con ícono). Para
  igualarlos sin tocar CSS global: regla en `kit/MktRaiz.vue` (scoped, `:deep(.q-btn…)`) con las medidas de `.btn`,
  y usar los colores del sistema (`positive` ≈ success para crear, `primary`, `negative`).
- Colores: los de `bootstrap.min.css` (`--bs-primary #5156be`, `--bs-success #2ab57d`, `--bs-info #4ba6ef`,
  `--bs-secondary #74788d`), que ya son los tokens del kit (`kit/tokens.js`).

## 8. Diferencias actuales de Marketing contra Clientes (lo que pidió Irving)

1. Sin breadcrumb.
2. Título grande (24 px, peso 600) con cuadro de ícono de color, fuera de tarjeta; Clientes: 20 px/500 dentro de la tarjeta.
3. Filtros en fila propia a todo lo ancho (Plantillas de video, Cola de renders) en vez de junto al título.
4. Botones de Quasar con medidas propias; Clientes usa `.btn` de Bootstrap.
5. Tablas con encabezado en MAYÚSCULAS sobre fondo gris; Clientes: encabezado oscuro sin mayúsculas. Algunos textos de
   Quasar salen en inglés si falta la prop en español.
6. Esquinas de 8 px en tarjetas; Clientes 4 px.

## 9. Plan que se había empezado (detenido por Henry a las ~14:50 y deshecho)

Se pausó para que un agente de calidad visual entregue antes la lista de fallas. Lo que se había probado en el kit
(revertido; quedó igual que al cierre de las 14:15):

- `MktEncabezadoPantalla`: pintar `mkt-navegacion-modulo` arriba y una tarjeta blanca (sombra de q-card, 4 px) con
  título `text-h6`, slot nuevo `#filtros` en la misma fila (campos de 200 px, búsqueda de 240 px con clase
  `mkt-filtro--busqueda`, todo a lo ancho solo en móvil), `#acciones` a la derecha y **slot por defecto = cuerpo de la
  misma tarjeta** (para que cada listado sea una sola tarjeta como Clientes). Cada pantalla quitaría su propio
  `<mkt-navegacion-modulo />`.
- `MktRaiz`: `--mkt-radio` a 4 px y medidas de `.btn` para los `q-btn` no redondos.
- Pendiente de ese plan: `MktTablaEjecutiva` (props en español de Clientes, `tabla-listado`, encabezado sin mayúsculas),
  breadcrumb en las vistas Blade, `data-key` en el submenú, y mover a `#filtros` los filtros de Plantillas, Cola, Leads,
  Formularios, Publicación (fila), Pruebas A/B, Voces y Campañas 360.

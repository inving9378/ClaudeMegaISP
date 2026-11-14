# Marketing IA — propuesta de diseño unificado con Clientes (agente-diseno, 13-nov-2026)

Corrige la lista de fallas de `fase-2-revision-visual.md` (22 pantallas, 13 de 15 puntos). Referencia única: Clientes
(Listado `/cliente/listar` y ficha `/cliente/editar/{id}`), solo lectura. Se arma en el kit para que todas las pantallas
lo hereden; no se toca nada de Clientes, `app.js`, layout, CSS global ni componentes compartidos.

## 1. Tipo de pantalla ↔ equivalente de Clientes

| Tipo | Pantallas de Marketing | Se arma como… (Clientes) |
|---|---|---|
| Lista | Campañas 360, Plantillas, Fila de producción, Fila de publicación, Leads, Formularios de captura, Pruebas A/B, Conversaciones (bandeja) | **Listado de Clientes**: breadcrumb → UNA tarjeta (q-card 4 px) con título `text-h6` 20 px/500, filtros de 200 px en la misma fila, botón verde de alta, y la tabla dentro de la misma tarjeta con menú de tres puntos + «Buscar» arriba a la derecha y «Elementos por página» al pie |
| Ficha | Ficha de campaña 360, Ficha de lead | **Ficha de cliente**: breadcrumb «Página › Marketing › … › nombre» → UNA fila de pestañas tipo carpeta (39 px, sin íconos) → tarjeta de encabezado (avatar, nombre · número, chip de estado, línea de datos, cifras y botones a la derecha) → bloques con cabecera de color |
| Formulario | Nueva / editar campaña, Generar, Video por nicho, Publicar, Nuevo formulario, Brand Kit, Voces, Canales | **Alta / ficha de Cliente**: tarjeta con título; bloques con cabecera de color; botones Bootstrap al pie (Guardar azul, Volver gris) |
| Tablero | Panel, Publicación › Resumen | **Tablero de Clientes**: tarjeta con título y bloques; cifras en recuadros como las de la ficha (`.cic-stat`) |

## 2. Navegación (puntos 12–14)

- **Menú lateral** = índice de las 7 secciones (ya aprobado en §2.11). Clic en «Marketing» abre **directo** el Panel
  (`/marketing`) y despliega el submenú (`module-sidebar/marketing.blade.php`; si el grupo ya estaba abierto se queda
  abierto). Los hijos llevan `data-key="t-marketing-<slug>"` como Clientes.
- **Dentro de la pantalla, UN SOLO grupo de pestañas**: el de las sub-secciones de la sección activa (Reels y videos:
  Plantillas · Generar · Video por nicho · Fila de producción; Publicación: Resumen · Publicar · Fila de publicación;
  Leads: Leads · Conversaciones · Formularios de captura; Configuración: Brand Kit · Voces · Canales de publicación ·
  Integraciones), con el aspecto de las pestañas de la ficha de Cliente. Se retira la barra de 6–7 secciones con
  íconos (era la segunda barra apilada y la que se cortaba a 375 px). Panel, Campañas 360 y Pruebas A/B no llevan
  pestañas, igual que el Listado de Clientes.
- La ficha de campaña usa ese mismo componente de pestañas (Estrategia, Reels, Video, Facebook, Instagram, Anuncios,
  WhatsApp) **arriba** de la tarjeta de encabezado, como la ficha de Cliente.
- Las pestañas nunca llevan flechas: si no caben, pasan a otra línea (375 px).

## 3. Kit (lo heredan todas)

| Pieza | Qué hace ahora |
|---|---|
| `MktRaiz` | Margen `q-pa-md` (16 px) como el Listado de Clientes; esquinas 4 px; oscuro igual que Clientes (`#1a1a1a`, bloques `#1d1d21`); `provide('mktTema')` para que q-card/q-table usen `:dark`; rojo y verde de Quasar → los del sistema |
| `MktEncabezadoPantalla` | Pestañas de la sección arriba + q-card con título `text-h6` (sin ícono ni cuadro azul `#0066CC`, sin subtítulo), slot `#filtros` en la misma fila, `#acciones` a la derecha; el slot por defecto es el cuerpo de la misma tarjeta |
| `MktNavegacionModulo` | Solo las sub-secciones de la sección activa, como pestañas de carpeta |
| `MktPestanas` (nuevo) | Pestañas de carpeta copiadas de `ClientCrud.vue` (`.cc-folder-tabs`); enlaces o v-model; se acomodan en otra línea |
| `MktFichaEncabezado` (nuevo) | Tarjeta de encabezado de la ficha de Cliente (`.cic-header-card`) |
| `MktTarjetaPanel` | Tarjeta q-card (4 px, sombra de Quasar); con `tono` + `icono-fa` = bloque de la ficha de Cliente (12 px, cabecera de color) |
| `MktTablaEjecutiva` | Props de la tabla de Clientes: «Elementos por página», «No hay elementos para mostrar», paginación «1-10 de 50», encabezados sin mayúsculas ni fondo, filas de 48 px, `tabla-listado` + `tituloSiCorta`, menú de tres puntos y «Buscar» |
| `MktBoton` (nuevo) | Botón Bootstrap del sistema (38 px, sin mayúsculas): crear verde `btn-success`, guardar azul `btn-info`, eliminar rojo `btn-danger`, volver `btn-secondary`, secundario `btn-outline-secondary` |
| `MktInsigniaEstado` | Chip de estado de la ficha de Cliente (`.estado-chip` + `.estado-tono--*` del sistema) |
| `MktTarjetaKpi` | Recuadro de cifra de la ficha de Cliente (`.cic-stat`); el monto ya no se parte en dos líneas |

Decisión de color: «guardar» va en azul `btn-info` (#4ba6ef) como la acción principal del alta de Cliente; el
índigo de Quasar (`color="primary"`) ya no se usa en botones de texto de Marketing.

## 4. Reglas para cada pantalla

1. Blade: primera línea de `@section('content')` = `<Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"<pestaña>","active":true}]'></Breadcrumb>`.
2. Quitar `<mkt-navegacion-modulo />` de la pantalla (lo pinta el encabezado). Título = nombre de su pestaña del menú.
3. Filtros al slot `#filtros` del encabezado; el listado principal dentro del encabezado (una sola tarjeta).
4. Botones de texto → `mkt-boton`; botones de ícono → `q-btn flat round dense` sin color.
5. Textos en español sin jerga: Puntuación (no Score), Volver a calificar, clips de fondo (no B-roll), textos (no
   copies), videos (no renders), nada de nombres de variables; fechas `dd/mm/aaaa`.
6. Nada de colores propios: paleta del sistema o tonos copiados de Clientes.

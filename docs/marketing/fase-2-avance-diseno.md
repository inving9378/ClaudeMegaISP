# Fase 2 — avance de agente-diseno (diseño unificado con Clientes)

Rama `feat/marketing-360-equipo`, worktree `/var/www/megaisp-henry-marketing`. Sin commits.

## 13-nov — arranque
- Leídos: rol, `patron-clientes.md`, `fase-2-revision-visual.md` (22 pantallas, 13/15 puntos), `agente-calidad-visual.md`, §2.11–§2.13.
- Propuesta escrita: `docs/marketing/diseno-propuesta.md` (tipo de pantalla ↔ Clientes, navegación, kit, reglas).
- Kit rehecho: `MktRaiz` (margen q-pa-md, 4 px, oscuro de Clientes, provide del tema, rojo/verde del sistema),
  `MktEncabezadoPantalla` (q-card con título text-h6 + #filtros en la misma fila + cuerpo), `MktNavegacionModulo`
  (solo sub-secciones, como pestañas de carpeta), `MktTablaEjecutiva` (props de Clientes, tabla-listado, tres puntos y
  Buscar), `MktTarjetaPanel` (q-card / bloque de ficha con cabecera de color), `MktTarjetaKpi` (cifra de ficha),
  `MktInsigniaEstado` (estado-chip del sistema), `MktEstadoVacio`. Nuevos: `MktPestanas`, `MktFichaEncabezado`, `MktBoton`.
- Blade: breadcrumb «Página › Marketing › …» en las 23 vistas con pantalla.
- Menú lateral: «Marketing» con `href` al Panel (abre y despliega; si ya estaba abierto no se cierra) y `data-key` por hijo.
- Pantallas repartidas en 4 forks que solo escriben (Campañas 360 · Reels y videos + Pruebas A/B · Leads · Panel,
  Publicación y Configuración). Vista previa en 127.0.0.1:8096 contra `megaisp_test` con datos PREV-F2 / PREV-VIS.

## 13-nov — pantallas armadas
- Los 4 forks terminaron (Campañas 360, Reels y videos + Pruebas A/B, Leads, Panel/Publicación/Configuración):
  títulos = nombre de su pestaña, filtros en la fila del título, listados en una sola tarjeta, fichas con
  pestañas de carpeta → tarjeta de encabezado → bloques con cabecera de color, botones del sistema, textos sin jerga.
- `compiler-sfc` limpio en todos los .vue; `npx mix` (desarrollo) compilado sin errores.
- Primera ronda de capturas propias en `<scratchpad>/diseno/capturas/`. Ajuste: en Leads los filtros empujaban «Crear
  lead» a otra línea → el bloque de título y filtros se acomoda por dentro y las acciones quedan arriba a la derecha;
  filtros cortos de 160 px (`mkt-filtro--corto`).

## 13-nov — cierre de agente-diseno (pendiente de la revisión de agente-calidad-visual)
- Segunda compilación limpia. Capturas propias de las 22 pantallas + Clientes (1440/375, claro/oscuro) en
  `<scratchpad>/diseno/capturas/`: breadcrumb en las 22, título de 20 px dentro de tarjeta, pestañas de 39 px sin
  flechas (79 px a 375 px, en dos líneas, sin cortes), desborde 0 en todas.
- Menú lateral verificado en navegador: desde `/cliente/listar`, clic en «Marketing» → `/marketing` con el submenú
  abierto; desde `/marketing/leads` (abierto) → `/marketing` y sigue abierto.
- Ajustes tras capturas: tablas vacías vuelven a mostrar su estado vacío; Campañas 360 filtrada sin resultados también;
  la tarjeta de encabezado de ficha es `.card` como la de Cliente; ayudas de campos legibles en oscuro.
- Pruebas: `PantallasMarketingTest` + `PantallasCampanas360Test` → 15 pasan (187 aserciones).
- Datos de vista previa borrados de `megaisp_test`; servidor 8096 apagado.
- Sin resolver (no es diseño): en `megaisp_test` no existen los permisos `view-marketing-campaigns`, `test-voices` ni
  `manage-integrations` → «Video por nicho», «Voces» e «Integraciones» no salen en sus pestañas y esas pantallas dan
  403 en sus API. El catálogo del backend (`CatalogosCampana360.php`) aún dice «Copy de Facebook/Instagram».

## 13-nov — correcciones de la 2.ª revisión
- Tablas (kit): ocupan todo el ancho de la tarjeta (también vacías); la última columna solo queda fija con sombra si es
  la de acciones. Menú de tres puntos separado en `MktMenuTabla` (reutilizable en la fila del título).
- Leads: título, filtros (150 px, «Buscar»), «Crear lead» y tres puntos en UNA fila; nombre largo con «…» y título
  completo al pasar el mouse; iniciales legibles en oscuro (tokens de avatar de la ficha de Cliente).
- Voces: casillas que se acomodan en varias líneas sin encimarse; «Estándar» en lugar de «Standard» (solo al mostrar).
- Integraciones: ya no es pestaña; es el enlace «Abrir Integraciones» (ícono de enlace externo, otra pestaña del
  navegador) a la derecha de las pestañas de Configuración. Se resolvió en `MktNavegacionModulo` (toda sub-sección fuera
  de /marketing se pinta como enlace), SIN tocar `module.json`: así sigue filtrada por `manage-integrations` en la API
  y no se rompen `NavegacionTest` (líneas 93 y 107) ni `NavegacionBackendTest` (línea 41), que esperan `/integraciones`.
- Generar: si solo hay plantillas de «Guion por escenas», explica que se usan desde Campañas 360 (con botón).
- Mensajes de la API en inglés: `mensajeDeError` y los avisos de Video por nicho / Canales ya no los muestran.
- Compilado, recapturado (13 pantallas, claro/oscuro, 1440/375, sin desborde), pruebas 15/15 + navegación, datos y
  permisos temporales de `megaisp_test` borrados, servidor apagado.

## 13-nov — un solo botón principal + Público simplificado (ajustes de Henry)
- `MktEstadoVacio` ya no pinta botones (solo texto) y `MktTablaEjecutiva` ya no reexpone `vacio-accion`; se quitaron
  los 49 botones de estados vacíos/error de las pantallas. Lo indispensable subió al encabezado: «Generar con IA» /
  «Generar piezas» (ficha de campaña), «Generar muestras» (Voces), «Generar video» (Generar); «Volver a intentar» de
  la estrategia va en la cabecera de su bloque. Botones por tarjeta (Copiar, Producir, Validar, Subir logo, Agregar
  campo) pasan a secundarios.
- Asistente 360, paso «Público»: solo «Tipo de público»; edad, nichos, zonas e intereses fuera de la pantalla, de
  «Revisar», de la ficha y del envío a la API. «Termina» avisa en su campo si queda antes de «Empieza» y muestra el
  422 `fecha_fin`.
- Compilado; `PantallasMarketingTest` + `PantallasCampanas360Test` 15/15; servidor apagado; datos y permisos
  temporales borrados de `megaisp_test`.

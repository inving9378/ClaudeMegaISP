# Fase 2 — revisión visual de Marketing contra Clientes (agente-calidad-visual)

13-nov-2026. Revisión de solo lectura: no se corrigió nada. Rama `feat/marketing-360-equipo`, JS de desarrollo ya compilado.
Vista previa propia en `127.0.0.1:8097` contra `megaisp_test`, con datos ficticios (campañas «PREV-F2 …», leads y formulario
«PREV-VIS …», cliente «PREV-VIS Cliente PRUEBA DEMO»). Todo eso se borró al terminar.

**Veredicto: NO SE CIERRA.** En las 22 pantallas fallan 13 de los 15 puntos. Solo pasa el 12 (submenú lateral). El 3 pasa
o no aplica en la mayoría.

Capturas: `/tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/visual/fase-2/`.
Hay una carpeta por pantalla con `marketing-{1440,375}-{claro,oscuro}.png`. Las de referencia de Clientes están en
`clientes-listar/`, `clientes-ficha/`, `clientes-panel/` y `clientes-alta/`. Cada carpeta trae también
`medidas-*.json` con lo que se midió de cada captura. El índice lado a lado es `fase-2/indice.html`.

## Pares usados

| Marketing | Se compara con |
|---|---|
| Listas (02, 07, 10, 13, 14, 16, 17, 19) | Listado de Clientes `/cliente/listar` |
| Fichas (04, 05, 15) y configuración (20–22) | Ficha de cliente `/cliente/editar/{id}` |
| Formularios (03, 06, 08, 09, 12, 18) | Alta de cliente `/cliente/crear` |
| Tableros (01, 11) | `/cliente` (tablero de Clientes) |

Cómo se ve Clientes (la referencia):
- **Listado:** arriba la ruta «Pagina › Cliente». Debajo, UNA tarjeta blanca de esquinas de 4 px. Dentro van el título
  «Listado de Clientes» (`text-h6`, 20 px, peso 500), los filtros en la misma fila, el buscador, el menú de tres puntos y
  la tabla. Los encabezados de la tabla no van en mayúsculas ni llevan fondo. Al pie dice «Elementos por página».
- **Ficha:** ruta «Dashboard › Cliente › nombre». Debajo, UNA fila de pestañas tipo carpeta (39 px de alto, sin íconos).
  Después una tarjeta de encabezado con avatar, nombre, estado, datos clave y botones a la derecha. Abajo, bloques en
  dos columnas con cabecera de color.
- **Botones:** Bootstrap `.btn` de 38 px, sin ícono.

## 1. Tabla pantalla × punto

✅ pasa · ❌ falla · — no aplica (la pantalla no tiene ese elemento).
La columna 13 es un solo defecto del menú lateral: se marca en todas las filas porque afecta la entrada a todas.

| # | Pantalla (URL) | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01 | Panel `/marketing` | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 02 | Campañas 360 lista | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 03 | Nueva campaña | ❌ | ❌ | — | — | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 04 | Ficha de campaña (Estrategia) | ❌ | ❌ | — | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 05 | Ficha de campaña (Reels) | ❌ | ❌ | — | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 06 | Editar campaña | ❌ | ❌ | — | — | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 07 | Reels › Plantillas | ❌ | ❌ | ❌ | — | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 08 | Reels › Generar | ❌ | ❌ | — | — | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 09 | Reels › Video por nicho* | ❌ | ❌ | — | — | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 10 | Reels › Fila de producción | ❌ | ❌ | ❌ | — | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 11 | Publicación › Resumen | ❌ | ❌ | — | — | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 12 | Publicación › Publicar* | ❌ | ❌ | — | — | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 13 | Publicación › Fila | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 14 | Leads › Leads | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 15 | Ficha de lead | ❌ | ❌ | — | — | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 16 | Leads › Conversaciones | ❌ | ❌ | ❌ | — | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 17 | Leads › Formularios de captura | ❌ | ❌ | — | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 18 | Nuevo formulario | ❌ | ❌ | — | — | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 19 | Pruebas A/B | ❌ | ❌ | — | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 20 | Configuración › Brand Kit | ❌ | ❌ | — | — | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 21 | Configuración › Voces* | ❌ | ❌ | — | — | — | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| 22 | Configuración › Canales | ❌ | ❌ | — | — | — | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| — | Menú lateral (`00-menu-lateral/`) | | | | | | | | | | | | ✅ | ❌ | | |

\* 09, 12 y 21 muestran «No tienes acceso a esta sección». En `megaisp_test` el rol `super-administrator` no tiene
`view-marketing-campaigns`, `test-voices`, `view-publishing-dashboard` ni `manage-integrations`. Por eso solo se calificó
el marco de esas pantallas (encabezado, barra, colores), no su contenido. Hay que repetirlas con un usuario que tenga
esos permisos.

**Fallas por punto (de 22 pantallas):**

| Punto | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Fallas | 22 | 22 | 5 | 8 | 20 | 22 | 22 | 2 | 8 | 22 | 22 | 0 | 1 (menú, afecta a todas) | 22 | 22 |

## 2. Detalle de cada falla

Las fallas que se repiten en todas las pantallas se describen una sola vez. Los archivos son rutas desde
`resources/js/components/module/marketing/` salvo que se diga otra cosa. Son el lugar probable de la corrección, para
frontend.

### P1 — Breadcrumb (las 22)
- **Qué se ve:** ninguna pantalla tiene ruta arriba. La página empieza directo con el ícono y el título.
- **Clientes:** «Pagina › Cliente» arriba a la izquierda, en gris pequeño (`ol.breadcrumb`). En la ficha:
  «Dashboard › Cliente › PREV-VIS … - 10607».
- **Captura:** `02-campanas360-lista/marketing-1440-claro.png` contra `clientes-listar/clientes-1440-claro.png`.
- **Archivo:** todas las vistas de `app/Modules/Addons/Marketing/views/**/*.blade.php`. Falta el
  `<Breadcrumb :list=…>` antes del componente (`patron-clientes.md` §2).

### P2 — Tarjeta y título (las 22)
- **Qué se ve:** el título mide 24 px con peso 600 y va fuera de cualquier tarjeta. A su izquierda lleva un cuadro
  azul brillante de 48 px con ícono, y debajo un subtítulo gris. El contenido va en contenedores propios (`card=[]`:
  ninguna pantalla usa `.q-card` ni `.card`). Las esquinas miden 8 px.
- **Clientes:** el título va DENTRO de la tarjeta blanca y no lleva ícono. En el listado mide 20 px con peso 500
  («Listado de Clientes»); en el alta, 15 px en la cabecera de tarjeta. Las esquinas miden 4 px.
- **Captura:** cualquier `marketing-1440-claro.png`. El recorte del encabezado está en
  `../comp/r-c360ficha.png` y `../comp/r-plant.png`.
- **Archivos:** `kit/MktEncabezadoPantalla.vue` (`.mkt-encabezado__titulo`, `__icono`) y `kit/MktRaiz.vue`
  (`--mkt-radio`).

### P3 — Filtros en una fila (5)
- **07 Plantillas:** «Buscar plantilla…», «Categoría» y «Formato» van en su propia fila a todo lo ancho, debajo de las
  pestañas y fuera de tarjeta. En Clientes los filtros van junto al título, con ancho fijo. Archivo:
  `MarketingVideoTemplatesView.vue`.
- **14 Leads:** el título «3 leads» está en una fila. Abajo, en otra fila, van el buscador, Estado, Fuente, Desde y
  Hasta, ocupando todo el ancho. Archivo: `MarketingLeadsView.vue`.
- **02 Campañas 360:** a 1440 px pasa. A 375 px el grupo «Todas · Borrador · Activa · Pausada · Terminada · Cancelada»
  se corta en «Cance». Captura: `02-campanas360-lista/marketing-375-claro.png`. Archivo:
  `campanas360/MarketingCampanas360Lista.vue`.
- **10 Fila de producción:** a 375 px el selector «Estado» queda de unos 40 px y su texto choca con la flecha
  («Estad▾»). Archivo: `MarketingVideoQueueView.vue`.
- **16 Conversaciones:** a 375 px los chips «Todas · Sin leer · Mías · Con bot · Sin bot · Cerradas» se cortan en
  «S». Archivo: `MarketingConversationsView.vue` (`.mkt-bandeja__chip`).

### P4 — Tabla (8: 01, 02, 04, 05, 13, 14, 17, 19)
- **Qué se ve:** encabezados en MAYÚSCULAS (`text-transform: uppercase`, 12 px) sobre franja gris `#f8f8fb`. No hay
  buscador ni menú de tres puntos. El pie dice «Filas por página» y «1–3 de 3». Las filas miden de 54 a 67 px en
  listas; en Clientes, 48 px. En 02 y 19 no hay paginación.
- **Clientes:** encabezados oscuros sin mayúsculas y sin fondo. Arriba a la derecha, menú de tres puntos y «Buscar».
  Al pie, «Elementos por página».
- **Captura:** `14-leads/marketing-1440-claro.png` (recorte en `../comp/r-leads.png`) contra
  `clientes-listar/clientes-1440-claro.png`.
- **Archivos:** `kit/MktTablaEjecutiva.vue` (líneas 14, 140 y 179: «Filas por página» y `uppercase`). Las tablas
  propias están en `campanas360/MarketingCampanas360Lista.vue`, `campanas360/PanelEstrategia.vue`,
  `campanas360/TarjetaGuion.vue`, `dashboard/PanelCampanasActivas.vue`, `publishing/PublicationQueueView.vue`,
  `MarketingLeadFormsView.vue` y `PilotCampaignsView.vue`. Falta la clase `tabla-listado` (#1342).

### P5 — Botones (20)
- **Qué se ve:** son botones Quasar de 36 px con ícono Material, en morado `#5156be`. Hay de tres clases mezclados:
  con relleno, con borde y planos. Lo mismo pasa en «Nueva campaña», «Crear lead», «Nuevo formulario», «Nuevo piloto»,
  «Guardar», «Activar», «Editar datos», «Cancelar campaña» y «Eliminar».
- **Clientes:** Bootstrap `.btn` de 38 px sin ícono. Para crear usa verde `btn-success`, para guardar `btn-info` o
  primario, y para volver `btn-secondary`.
- **Captura:** `04-campanas360-ficha/marketing-1440-claro.png` contra `clientes-ficha/clientes-1440-claro.png`
  («Tareas», «Soporte», «Salvar Cliente»).
- **Archivos:** `kit/MktRaiz.vue` (medidas de `.q-btn`) y cada pantalla (`color`, `icon`).

### P6 — Colores fuera de la paleta (22)
- **Todas:** el cuadro del ícono del título es `#0066CC` (rgb 0,102,204), que es el azul de la marca Meganet y no está
  en la paleta del sistema. Archivo: `kit/MktEncabezadoPantalla.vue` / `kit/tokens.js`.
- **04, 05, 17, 18:** «Eliminar» y los botones de borrar usan `#C10015`, el rojo «negative» de Quasar. El rojo del
  sistema es `#fd625e`.
- **14, 15:** insignias de estado en `#c98a14` y `#2f8fdc`, que tampoco son de la paleta (`kit/MktInsigniaEstado.vue`).
  En `ConversationListItem.vue` hay además `#0066cc`.
- **19:** el texto técnico se pinta en rosa `#e83e8c`, el color de `<code>` de Bootstrap.

### P7 — Espaciado (22)
- **Qué se ve:** la pantalla se arma en tres franjas separadas: encabezado con ~48 px arriba, barra de secciones a
  ~30 px y contenido a ~24 px. La primera tarjeta empieza unos 210 px debajo de la barra superior.
- **Clientes:** la ruta y debajo la tarjeta, con `q-pa-md` de 16 px. La tarjeta empieza unos 110 px debajo.
- **Captura:** `../comp/02-campanas360-lista-1440-claro.png` (lado a lado).
- **Archivos:** `kit/MktEncabezadoPantalla.vue`, `kit/MktNavegacionModulo.vue` y `kit/MktRaiz.vue`.

### P8 — Alineación (2)
- **07 Plantillas:** en los selectores «Categoría» y «Formato» el texto va pegado al borde izquierdo, mientras que el
  buscador de al lado sí tiene margen interno. Captura: `../comp/r-plant.png`.
- **14 Leads:** pasa lo mismo con «Estado» y «Fuente». Además, «Desde» y «Hasta» son campos de fecha del navegador,
  con otra altura y otro estilo. Captura: `../comp/r-leads.png`.

### P9 — Español (8)
- **14, 15:** encabezado y dato «SCORE». En 15, el botón «Re-calificar». En 14, los campos de fecha muestran
  «dd/mm/yyyy». Archivos: `MarketingLeadsView.vue`, `MarketingLeadDetail.vue` y `LeadScoreBadge.vue`.
- **19:** se ve a la vista el nombre técnico `MARKETING_PILOT_CAMPAIGN_SEND_ENABLED` dentro del aviso. Archivo:
  `PilotCampaignsView.vue`.
- **05, 20:** «B-roll» en «Video de apoyo (B-roll)» y en «Pexels (B-roll)». Archivos: `campanas360/TarjetaGuion.vue` y
  `MarketingBrandKitView.vue`. «Brand Kit» se dejó como está porque es el nombre aprobado por Henry en §2.11.
- **08, 10:** «renders», «Cola de renders» y «Ver cola de renders». Archivos: `MarketingVideoGeneratorView.vue` y
  `MarketingVideoQueueView.vue`.
- **02:** «copies» en el subtítulo («guiones de video, copies, anuncios…»). Archivo:
  `campanas360/MarketingCampanas360Lista.vue`.
- **Nombres que no coinciden entre la pestaña y el título:** también desorientan.
  - «Video por nicho» → «Campaña con IA» (09, `MarketingCampaignGeneratorView.vue`).
  - «Fila de producción» → «Cola de renders» (10).
  - «Formularios de captura» → «Formularios web» (17, `MarketingLeadFormsView.vue`).
  - «Pruebas A/B» → «Piloto A/B por correo» (19).

### P10 — Nada encimado ni cortado (22, todas a 375 px)
- **Todas:** a 375 px la barra de secciones de Marketing se corta en los dos lados sin flecha ni aviso. Se lee «anel»,
  «s 360», «deos», «Cont» o «Pr». Las medidas marcan elementos `a.mkt-nav__item` hasta en x = 570 en una pantalla de
  375 px. La página no se desborda a lo ancho (desborde 0), pero el texto queda cortado. Archivo:
  `kit/MktNavegacionModulo.vue`.
- **02:** en las tarjetas de celular, la insignia de estado se encima sobre la etiqueta «GENERACIÓN CON IA»
  («GENERACIÓN CON ●Sin generar»). Además, el filtro de estado se corta en «Cance». Captura: `../comp/r-lista375.png`.
- **10:** el selector «Estado» queda aplastado. **16:** los chips se cortan. **18:** el texto de ayuda de un campo
  sale del borde (x = 395).
- **04, 05 (1440 px):** el dato «$15,000.00 MXN» se parte en dos líneas dentro de su tarjeta. No está cortado, pero
  queda desigual frente a las otras tres tarjetas.

### P11 — Modo oscuro (22)
- **Qué se ve:** todo se lee. Sin embargo, las tarjetas de Marketing son gris medio `#313533` y las de Clientes casi
  negras `rgb(26,26,26)`. Las tablas llevan una franja de encabezado más clara. «Eliminar» pierde el rojo y queda
  blanco. La barra de secciones es otro bloque gris claro.
- **Clientes:** tarjetas casi negras, tabla sin franja y ruta en gris.
- **Captura:** `../comp/d-lista.png` y `04-campanas360-ficha/marketing-1440-oscuro.png`.
- **Archivos:** `kit/MktRaiz.vue` y `kit/tokens.js` (`fondoTarjeta` oscuro `#313533`).

### P12 — Submenú lateral: ✅
- Mismo marcado que Clientes: ícono Feather en el grupo, `small > i.fa.fa-fw` en cada hijo, sangría de 52.8 px, letra
  de 13.6 px y el activo en morado (blanco en oscuro). Captura: `00-menu-lateral/marketing-1440-claro.png` contra
  `clientes-1440-claro.png`.
- Observaciones que no cuentan como falla:
  - a los hijos les falta `data-key` (`patron-clientes.md` §6);
  - con este usuario no aparece «Publicación», por falta de `view-publishing-dashboard` en la base de prueba.

### P13 — Entrada directa al Panel (1 defecto que afecta a todas)
- **Qué se ve:** clic en «Marketing» del menú lateral → la URL sigue en `/cliente/listar` y no se abre el Panel. El
  enlace es `javascript: void(0)` con clase `has-arrow`, así que solo despliega o pliega la lista. Medido en
  `00-menu-lateral/medidas-menu.json` (`clicMarketing`). Captura: `00-menu-lateral/clic-marketing-1440-claro.png`.
- **Archivo:** `resources/views/module-sidebar/marketing.blade.php` (el `<a>` padre).

### P14 — Pestañas compactas (22)
- **Dos barras apiladas (10 pantallas: 07, 08, 10, 14, 15, 16, 17, 18, 20, 22):** arriba va la barra de secciones con
  6 secciones con ícono (42 px de alto). Debajo, otra barra de sub-secciones (38 px). En total son 88 px de
  navegación. Irving pidió UN solo grupo.
- **Ficha de campaña (04, 05):** primero va la barra de secciones del módulo y, más abajo, las pestañas de la ficha
  (Estrategia, Reels, Video, Facebook, Instagram, Anuncios, WhatsApp), de 56 px con el ícono encima del texto. En la
  ficha de cliente hay una sola fila de pestañas tipo carpeta de 39 px, sin íconos.
- **Sin pestaña activa (09, 11, 12, 13, 21):** con este usuario la sección o sub-sección no sale en la barra, por
  falta de permiso, aunque la pantalla sí abre. El usuario no ve en qué sección está y no aparecen las pestañas
  Resumen · Publicar · Fila ni Voces.
- **Todas a 375 px:** la barra se corta sin flechas (ver P10).
- **Archivos:** `kit/MktNavegacionModulo.vue` y `campanas360/MarketingCampana360Ficha.vue` (`q-tabs`).

### P15 — Organización como la ficha de Cliente (22)
- **Qué se ve:**
  - en todas, el orden es encabezado suelto → barra de secciones → contenido;
  - en las fichas (04, 05, 15) el encabezado no es tarjeta y los bloques no llevan cabecera de color;
  - en la ficha de campaña las pestañas van debajo de los indicadores (KPI) y no arriba.
- **Clientes:** ruta → una fila de pestañas → tarjeta de encabezado (avatar, nombre, estado, datos clave, botones a la
  derecha) → bloques en dos columnas con cabecera de color.
- **Captura:** `../comp/r-c360ficha.png` contra `../comp/r-ficha.png`.
- **Archivos:** `campanas360/MarketingCampana360Ficha.vue`, `MarketingLeadDetail.vue`, `MarketingBrandKitView.vue` y
  `kit/MktEncabezadoPantalla.vue`.

## 3. Notas para QA (no son visuales)
- **08 Generar** dice «No hay plantillas disponibles», pero 07 Plantillas muestra 2. En la consola hay respuestas 403
  en 03, 08, 09, 12 y 21.
- Las fallas de permiso de 09, 12 y 21 salen de la base de prueba. Hay que repetir la revisión con un usuario que tenga
  `view-marketing-campaigns`, `test-voices` y `view-publishing-dashboard`.

## 4. Veredicto
**NO SE CIERRA.** Para aprobar, los 15 puntos tienen que pasar en las 22 pantallas. Tras las correcciones de frontend se
vuelven a capturar y calificar todas, no solo las que fallaron.

---

# Segunda revisión (13-nov-2026, tras la corrección de agente-diseno)

Se recapturó y recalificó TODO desde cero. No se usaron las capturas de agente-diseno.
- **Pantallas:** las 22 de Marketing más **23 Integraciones** (`/integraciones`, la pestaña «Integraciones» de
  Configuración).
- **Capturas:** 1440/375 × claro/oscuro, lado a lado con Clientes. Las fichas de campaña y de lead se compararon contra
  la FICHA de un cliente.
- **Usuario de vista previa:** `mkt_prev_admin`, con TODOS los permisos del módulo. En `megaisp_test` faltaban siete
  permisos y se crearon temporalmente solo para ese usuario: `view-marketing-campaigns`, `test-voices`,
  `manage-integrations`, `create-marketing-campaigns`, `delete-marketing-campaigns`, `manage-marketing-niches` y
  `regenerate-marketing-variants`. Además se le dieron `view-publishing-dashboard` y `publish-content`, que ya existían.
  Todo se borró al final. Así se pudieron calificar Video por nicho, Publicar, Voces e Integraciones.
- **Capturas e índice:** `/tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/visual/fase-2-r2/`
  (117 capturas). Índice: `fase-2-r2/indice.html`. Los recortes citados están en `../comp2/`.

## Tabla completa (2.ª revisión)

✅ pasa · ❌ falla · — no aplica.

| # | Pantalla | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01 | Panel | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 02 | Campañas 360 lista | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 03 | Nueva campaña | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 04 | Ficha de campaña (Estrategia) | ✅ | ✅ | — | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 05 | Ficha de campaña (Reels) | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 06 | Editar campaña | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 07 | Reels › Plantillas | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 08 | Reels › Generar | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 09 | Reels › Video por nicho | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 10 | Reels › Fila de producción | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 11 | Publicación › Resumen | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 12 | Publicación › Publicar | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 13 | Publicación › Fila | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 14 | Leads › Leads | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| 15 | Ficha de lead | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 16 | Leads › Conversaciones | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 17 | Leads › Formularios de captura | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 18 | Nuevo formulario | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 19 | Pruebas A/B | ✅ | ✅ | — | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 20 | Configuración › Brand Kit | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 21 | Configuración › Voces | ✅ | ✅ | — | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 22 | Configuración › Canales | ✅ | ✅ | — | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 23 | Configuración › Integraciones (módulo Hub) | ❌ | ❌ | — | — | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |

**Fallas por punto (23 pantallas):**

| Punto | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Fallas | 1 | 1 | 1 | 9 | 0 | 0 | 1 | 0 | 2 | 3 | 2 | 0 | 0 | 1 | 1 |

Primera revisión: 13 de 15 puntos fallaban en todas las pantallas.

## Lo que ya pasa
- **Breadcrumb y título (P1, P2):** «Página › Marketing › …» en las 22 pantallas de Marketing. Título de 20 px dentro de
  una tarjeta blanca de 4 px.
- **Botones (P5):** Bootstrap del sistema. Crear en verde; guardar y acción principal en `btn-info`; volver en gris;
  eliminar en rojo del sistema.
- **Colores (P6):** solo de la paleta del sistema, más los de `estado-chip` y `cic-card` de la ficha de cliente.
- **Modo oscuro (P11):** tarjetas `rgb(26,26,26)`, igual que Clientes.
- **Submenú (P12):** ahora con `data-key` por hijo. «Publicación» aparece con el permiso.
- **Entrada directa (P13):** clic en «Marketing» → `/marketing` con el submenú abierto. Se probó desde
  `/cliente/listar` (2.3 s) y desde `/marketing/leads` (1.9 s). Captura:
  `fase-2-r2/00-menu-lateral/clic-marketing-desde-listar.png`.
- **Pestañas (P14):** UN solo grupo, tipo carpeta como la ficha de cliente, de 39 px y sin flechas. A 375 px pasan a
  dos líneas sin cortarse. La barra de secciones con íconos desapareció.
- **Fichas (P15):** las de campaña y lead quedan como la de cliente: ruta → pestañas → tarjeta de encabezado con datos
  clave y botones → bloques con cabecera de color.
- **Textos de la API:** se revisaron 26 GET de Marketing. Las etiquetas salen en español («Texto para
  Facebook/Instagram», «Guion de Reel», estados, objetivos).

## Fallas que quedan

**P4 — Tabla (9: 01, 02, 04, 10, 13, 14, 17, 19, 21)**
- **Tabla angosta:** en tablas de pocas columnas, la tabla solo ocupa parte de la tarjeta y deja un hueco blanco a la
  derecha. Ejemplos:
  - «Papel de cada canal» en la ficha de campaña ocupa la mitad;
  - Formularios de captura ocupa unos 3/4;
  - Leads deja ~200 px vacíos;
  - en Voz por nicho pasa lo mismo.
- **Tablas vacías:** en Panel › Campañas activas, Fila de producción, Fila de publicación y Pruebas A/B, los
  encabezados se amontonan a la izquierda (≈400 px de 1100).
- **Raya vertical:** en Campañas 360 («Videos listos») y en «Papel de cada canal» («Frecuencia») aparece una raya
  vertical con sombra antes de la última columna. Es la columna fija de acciones de `tabla-listado`, pero ahí la
  última columna es un dato, no acciones. En el Listado de Clientes la tabla ocupa todo el ancho.
- **Capturas:** `../comp2/r04b.png`, `../comp2/r17.png`, `../comp2/r10.png`, `../comp2/r14.png`, `../comp2/r19.png`,
  `../comp2/r-lista.png`.
- **Archivo probable:** `kit/MktTablaEjecutiva.vue`, que tiene la clase `tabla-listado` sin ancho completo y marca como
  fija la última columna aunque no sea de acciones.

**P3 y P7 — Leads (14)**
- El título «Leads» queda solo en la primera línea y los filtros bajan a la segunda.
- El menú de tres puntos queda solo en una tercera línea, con un hueco grande arriba de la tabla.
- En Clientes, título y filtros comparten fila. Captura: `../comp2/r14.png`. Archivo: `MarketingLeadsView.vue`
  (`mkt-filtro--corto` y el bloque de acciones).

**P10 — Cortado o encimado (3)**
- **14 Leads:**
  - el nombre largo se corta a media palabra («…con un nombre ba») sin «…»;
  - el texto del buscador se corta en «Buscar nombre, correo, teléfo».
- **21 Voces:** a 1440 px las dos columnas de casillas se enciman («…profesional (neutra» queda debajo de «echo — …»;
  «(m**ohnyx**…»). A 375 px se corta «…cuenta historias (masculin». Captura: `../comp2/r21.png`. Archivo:
  `VoiceComparatorView.vue`.
- **23 Integraciones:** a 375 px el botón «+ Nueva integración» se sale por la derecha (x = 417).

**P11 — Modo oscuro (2)**
- **14 Leads:** los avatares con iniciales («PT», «PC», «PU») quedan como círculos blancos y las letras no se ven.
  Captura: `../comp2/r14d.png`. Archivo: `MarketingLeadsView.vue` (avatar).
- **23 Integraciones:** el título «Integration Hub» sale azul oscuro sobre negro y casi no se lee. Captura:
  `../comp2/r23d.png`.

**P9 — Español (2)**
- **21 Voces:** el modelo dice «Standard» en las casillas y en la tabla de voz por nicho. Debería decir «Estándar».
  «alloy», «nova», etc. son nombres de voces y se aceptan.
- **23 Integraciones:** el título dice «Integration Hub».

**P1, P2, P14, P15 — 23 Integraciones**
- Al tocar la pestaña «Integraciones» de Configuración se sale del módulo a la pantalla del Hub:
  - no tiene ruta;
  - el título va fuera de tarjeta con cuadro de ícono;
  - desaparecen las pestañas de Configuración y aparecen otras pestañas de otro estilo, subrayadas.
- Es otro módulo (`app/Modules/Addons/Hub`): no se toca desde Marketing. Henry decide si la pestaña abre el Hub en otra
  pestaña del navegador, si se marca como enlace externo o si se acepta así.

## Notas (no son fallas de esta lista)
- Los campos Quasar de Marketing tienen 5 px de margen interno, igual que los filtros del Listado de Clientes. La ficha
  y el alta de cliente usan campos Bootstrap de 12 px. No se cuenta como falla porque Clientes usa ambos.
- **Para QA (funcional):** «Generar» sigue diciendo «No hay plantillas disponibles» mientras Plantillas muestra 2.
- **API:** cuando falta un permiso, `/api/marketing/niches` (y cualquier ruta con `permission:`) responde en inglés
  «User does not have the right permissions.» (mensaje del paquete Spatie). Solo lo ve un usuario sin el permiso.
- En producción, varios permisos del módulo no existen en `megaisp_test`: `view-marketing-campaigns`, `test-voices`,
  `manage-integrations`, `create-/delete-marketing-campaigns`, `manage-marketing-niches` y
  `regenerate-marketing-variants`. Hay que confirmar que existan en dev y prod. Si no, esas pestañas y pantallas no
  salen o dan 403.

## Veredicto (2.ª revisión)
**NO SE CIERRA, pero falta poco.** De las 22 pantallas de Marketing, 13 pasan los 15 puntos. Lo que queda:
- P4: ancho y encabezados de tablas, en 9 pantallas;
- Leads: fila de filtros, corte de nombre/buscador y avatares en oscuro;
- Voces: casillas encimadas y «Standard»;
- Integraciones: es del módulo Hub y necesita decisión de Henry.

---

# Tercera revisión (13-nov-2026, tras las correcciones de la 2.ª revisión)

Se recapturaron y recalificaron las 22 pantallas desde cero, sin usar las capturas de agente-diseno.
- **Capturas:** 1440/375 × claro/oscuro, lado a lado con Clientes. Las fichas de campaña y de lead se compararon contra
  la FICHA de un cliente.
- **Usuario de vista previa:** el mismo, con los mismos permisos temporales (7 creados más 2 existentes, solo para
  `mkt_prev_admin`, bajo `flock`, borrados al final).
- **Integraciones:** ya no es pestaña. Se calificó el BOTÓN «Abrir Integraciones» dentro de Configuración. La pantalla
  del Hub es de otro módulo y no se califica.
- **Capturas e índice:** `/tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/visual/fase-2-r3/`
  (113 capturas). Índice: `fase-2-r3/indice.html`. Recortes en `../comp3/`.

## Tabla completa (3.ª revisión)

✅ pasa · — no aplica (la pantalla no tiene ese elemento).

| # | Pantalla | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01 | Panel | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 02 | Campañas 360 lista | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 03 | Nueva campaña | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 04 | Ficha de campaña (Estrategia) | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 05 | Ficha de campaña (Reels) | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 06 | Editar campaña | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 07 | Reels › Plantillas | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 08 | Reels › Generar | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 09 | Reels › Video por nicho | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 10 | Reels › Fila de producción | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 11 | Publicación › Resumen | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 12 | Publicación › Publicar | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 13 | Publicación › Fila | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 14 | Leads › Leads | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 15 | Ficha de lead | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 16 | Leads › Conversaciones | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 17 | Leads › Formularios de captura | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 18 | Nuevo formulario | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 19 | Pruebas A/B | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 20 | Configuración › Brand Kit (+ botón «Abrir Integraciones») | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 21 | Configuración › Voces (+ botón) | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 22 | Configuración › Canales (+ botón) | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| — | Menú lateral (`fase-2-r3/00-menu-lateral/`) | | | | | | | | | | | | ✅ | ✅ | | |

**Fallas por punto: 0 en los 15 puntos.**

## Verificación de lo que fallaba en la 2.ª revisión
- **P4 Tablas:**
  - ocupan todo el ancho de la tarjeta, también vacías (Panel › Campañas activas, Fila de producción, Fila de
    publicación, Pruebas A/B);
  - la raya fija solo aparece antes de «Acciones»;
  - «Papel de cada canal» llena el bloque.

  Recortes: `../comp3/r04b.png`, `../comp3/r10.png`, `../comp3/r01.png`.
- **Leads (P3/P7/P10/P11):**
  - título, filtros, «Crear lead» y tres puntos van en UNA fila;
  - el nombre largo termina en «…»;
  - el buscador dice «Buscar» completo;
  - en oscuro las iniciales se leen.

  Recortes: `../comp3/r14.png` y `../comp3/d1.png`.
- **Voces (P9/P10):** las casillas van una por renglón sin encimarse (a 375 px el texto largo baja de línea, sin
  cortarse) y dice «Estándar».
- **Botón «Abrir Integraciones»** (Brand Kit, Voces y Canales; 1440 y 375 px):
  - es Bootstrap `btn btn-outline-secondary btn-sm` de 28 px, a la derecha de las pestañas en escritorio y debajo de
    ellas en celular;
  - lleva el ícono `fa-external-link-alt`, que existe en el CSS servido (`\f35d`), y tiene `target="_blank"`
    `rel="noopener"`;
  - el clic abre `/integraciones` en una pestaña nueva y la de Marketing se queda;
  - se ve en claro y en oscuro.
- **Menú lateral (P12/P13):** clic en «Marketing» → `/marketing` con el submenú abierto. Desde `/cliente/listar`
  tarda 2.0 s; desde `/marketing/leads`, 1.7 s. Los 7 hijos llevan `data-key`, ícono FA y sangría de 52.8 px, igual
  que Clientes.
- **Textos:** no queda inglés en las 22 pantallas (88 capturas revisadas). La API de Marketing responde en español
  (26 GET; `/niches` ya responde 200 con el permiso).
- **Desborde a 375 px:** 0 en todas. Las medidas marcan dos elementos fuera de la pantalla, pero ninguno se ve cortado:
  - en el Panel, un ícono a x = 381 que queda bajo el recorte de la tarjeta;
  - en Nuevo formulario, el texto de ayuda dentro de un campo que se recorta solo (x = 399).

## Notas (no son fallas)
- En algunas capturas aparece un triángulo morado en la esquina superior derecha. Es el indicador de carga del
  sistema (pace), que se capturó mientras la página terminaba de cargar.
- Siguen pendientes, fuera de lo visual:
  - confirmar que en dev y prod existan los permisos del módulo que faltaban en `megaisp_test`;
  - la pantalla del Hub tiene su propio título en inglés («Integration Hub»), pero es de otro módulo.

## Veredicto (3.ª revisión)
**APROBADA.** Los 15 puntos pasan en las 22 pantallas de Marketing y en el menú lateral, a 1440 y 375 px, en claro y
oscuro.

---

# Cuarta revisión (13-nov-2026, ajustes de Henry)

Cambios que se revisan:
1. Un solo botón de acción principal por pantalla, arriba a la derecha. Los estados vacíos o de error solo llevan texto
   (punto 5 actualizado).
2. En Nueva campaña, el paso «Público» solo pide «Tipo de público» (sin edad, nicho, zona ni interés, tampoco en
   «Revisar» ni en la ficha). «Termina» no puede quedar antes de «Empieza» y el aviso sale en su campo.

Método y alcance:
- Se recapturaron las 22 pantallas desde cero, con el mismo método y los mismos permisos temporales (7 creados más 2
  existentes, solo `mkt_prev_admin`, bajo `flock`, borrados al final).
- Se sumó como estado extra la ficha de una campaña sin generar (`23-extra-ficha-campana-vacia`).
- El asistente se probó en el navegador (`24-asistente-prueba/`).
- Capturas e índice: `/tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/visual/fase-2-r4/`
  (115 capturas). Índice: `fase-2-r4/indice.html`. Recortes en `../comp4/`.

## Prueba del asistente (en navegador)
- **Paso «Público»** (Editar campaña): el único campo es «Tipo de público *». En el paso no aparecen «Edad», «Nicho»,
  «Zona» ni «Interés».
- **Paso «Presupuesto y fechas»:** con Empieza 20/12/2026 y Termina 10/12/2026, al tocar «Siguiente»:
  - el campo «Termina» se pone en rojo y debajo dice «La fecha de fin no puede ser anterior a la de inicio.»;
  - el asistente no avanza.

  Con Termina 30/12/2026 el error se quita y pasa a «Revisar». ✅
- **«Revisar»:** no menciona edad, nicho, zona ni interés. ✅
- **Ficha › Datos de la campaña:** muestra Objetivo, Tipo de público, Oferta, Plan, Precio al mes, Condiciones, Vigencia,
  Canales y Presupuesto, sin edad, nicho, zona ni interés. ✅
- **Nueva campaña:** no deja saltar al paso 2 sin terminar el 1 (comportamiento esperado).
- **Falla visual encontrada:** ver P10 abajo.

## Tabla completa (4.ª revisión)

| # | Pantalla | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01 | Panel | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 02 | Campañas 360 lista | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 03 | Nueva campaña | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 04 | Ficha de campaña (Estrategia) | ✅ | ✅ | — | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 05 | Ficha de campaña (Reels) | ✅ | ✅ | — | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 06 | Editar campaña | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 07 | Reels › Plantillas | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 08 | Reels › Generar | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 09 | Reels › Video por nicho | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 10 | Reels › Fila de producción | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 11 | Publicación › Resumen | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 12 | Publicación › Publicar | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 13 | Publicación › Fila | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 14 | Leads › Leads | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 15 | Ficha de lead | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 16 | Leads › Conversaciones | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 17 | Leads › Formularios de captura | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 18 | Nuevo formulario | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 19 | Pruebas A/B | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 20 | Configuración › Brand Kit | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 21 | Configuración › Voces | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 22 | Configuración › Canales | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| (extra) | Ficha de campaña sin generar | ✅ | ✅ | — | — | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| — | Menú lateral | | | | | | | | | | | | ✅ | ✅ | | |

**Fallas por punto (22 pantallas):** P5 = 2 (04, 05; también el estado extra de la ficha sin generar) · P10 = 2 (03, 06,
el mismo asistente) · los otros 13 puntos = 0.

## Punto 5 pantalla por pantalla (botón principal relleno, medido a 1440 y 375 px)

| Pantalla | Botón principal relleno | ¿Arriba a la derecha? |
|---|---|---|
| Panel | «Nueva campaña» | sí |
| Campañas 360 | «Nueva campaña» | sí |
| Plantillas | «Generar video» | sí |
| Generar | «Generar video» | sí |
| Fila de producción | «Nuevo video» | sí |
| Resumen y Fila de publicación | «Nueva publicación» | sí |
| Leads | «Crear lead» | sí |
| Formularios | «Nuevo formulario» | sí |
| Pruebas A/B | «Nuevo piloto» | sí |
| Nuevo formulario | «Guardar» | sí |
| Brand Kit | «Guardar cambios» | sí |
| Voces | «Generar muestras» | sí |
| Ficha de lead | «Volver a calificar» (los demás, con borde) | sí |

- **Sin botón principal** (correcto, porque solo muestran estado vacío): Publicar, Conversaciones y Canales.
- **Estados vacíos y de error:** 0 botones dentro de ellos en todas las pantallas, medido.
- **Asistentes** (Nueva/Editar campaña y Video por nicho): «Siguiente» va al pie del paso, como «Atrás»/«Buscar
  Cliente» en el alta de Cliente. Se aceptó así porque es la navegación del paso. Si Henry lo quiere arriba, es un
  cambio aparte.

## Fallas que quedan

**P5 — Ficha de campaña (04, 05 y ficha sin generar):** hay DOS botones principales rellenos en el encabezado.
- **Qué se ve:** «Activar» (azul relleno) y «Eliminar» (rojo relleno). Si la campaña no está generada, «Generar con
  IA» (azul) y «Eliminar» (rojo). Junto a ellos están «Editar datos» y «Cancelar campaña» con borde. Los botones van
  después de las cifras, a media tarjeta, no alineados a la derecha.
- **Clientes:** la ficha de cliente tiene un solo botón relleno («Salvar Cliente») a la derecha; «Tareas» y «Soporte»
  van con borde.
- **Arreglo esperado:** «Eliminar» con borde (`btn-outline-danger`) y los botones alineados a la derecha.
- **Captura:** `../comp4/r04.png`. Archivo probable: `campanas360/MarketingCampana360Ficha.vue` (encabezado de la
  ficha) o `kit/MktFichaEncabezado.vue`.

**P10 — Asistente de campaña (03, 06), paso «Presupuesto y fechas»:**
- **1440 px:** el aviso «La fecha de fin no puede ser anterior a la de inicio.» ocupa dos líneas y la segunda («a la
  de inicio») queda cortada por la línea de abajo del paso.
- **375 px:** el «MXN» a la derecha de «Presupuesto total» se sale del campo y se corta.
- **Capturas:** `24-asistente-prueba/editar-paso5-fin-antes-de-inicio-1440.png` (recorte `../comp4/r-fecha-z.png`) y
  `24-asistente-prueba/editar-paso5-fin-antes-de-inicio-375.png`.
- **Archivo probable:** `campanas360/PasoPresupuestoFechas.vue` (alto del bloque de mensajes del campo y sufijo MXN).

## Veredicto (4.ª revisión)
**NO SE CIERRA.** Quedan dos detalles:
- la ficha de campaña tiene dos botones rellenos («Eliminar» debe ir con borde);
- en el paso de fechas el aviso se corta a la mitad, y en celular el «MXN» también.

Todo lo demás pasa en las 22 pantallas: un solo botón principal, estados vacíos solo con texto, y «Público» con un solo
campo y la validación de fechas funcionando.

# Marketing IA — Guía visual (estilo ejecutivo / panel directivo)

Dueño: Henry · Fase 0 · 2026-11-12 · Aplica a **toda** pantalla nueva o rediseñada del módulo Marketing.
Las piezas viven en `resources/js/components/module/marketing/kit/` y se registran globalmente desde
`resources/js/components/module/marketing/registrar.js` (app.js ya lo llama; no se vuelve a tocar app.js).

---

## 1. Principios

1. **Lo importante arriba.** Encabezado → KPIs → paneles/tablas → detalle. Quien abre la pantalla entiende
   el estado del negocio en 3 segundos sin hacer scroll.
2. **Una sola paleta.** Colores del sistema LM + el acento de la marca activa. Ninguna pantalla inventa
   colores propios.
3. **Calma visual.** Tarjetas blancas con borde fino, sombra mínima, mucho aire. Nada de gradientes, banners
   de color sólido ni íconos decorativos de relleno.
4. **Todo estado tiene diseño**: cargando, vacío, error y sin permiso. Ninguna pantalla queda en blanco.
5. **Funciona a 375 px** sin scroll horizontal de la página.

---

## 2. Color

### 2.1 Paleta base — sistema LM (verificada en el CSS servido)

Comprobado el 12-nov con `grep` en `public/assets/css/bootstrap.min.css` (variables `--bs-*`):

| Variable del kit | Valor | Origen | Uso |
|---|---|---|---|
| `--mkt-primario` | `#5156be` | `--bs-primary` | botones principales, enlaces, ícono de KPI |
| `--mkt-exito` | `#2ab57d` | `--bs-success` | activo, aprobado, sube |
| `--mkt-info` | `#4ba6ef` | `--bs-info` | informativo (en texto se usa `#2f8fdc` por contraste) |
| `--mkt-aviso` | `#ffbf53` | `--bs-warning` | atención (en texto se usa `#c98a14` por contraste) |
| `--mkt-peligro` | `#fd625e` | `--bs-danger` | error, baja, destructivo |
| `--mkt-neutro` | `#74788d` | `--bs-secondary` | inactivo, sin dato |
| `--mkt-borde` | `#e9e9ef` | `--bs-light` (y borde de `.card`) | bordes y divisores |
| `--mkt-texto` | `#343a40` | `--bs-dark` | texto principal |
| `--mkt-texto-suave` | `#74788d` | `--bs-secondary` | etiquetas, subtítulos, datos secundarios |
| `--mkt-fondo-tarjeta` | `#ffffff` | `.card{background-color:#fff}` | fondo de tarjetas y diálogos |
| `--mkt-fondo-suave` | `#f8f8fb` | derivado (fondo de encabezado de tabla) | thead, chips de código |

Fuente: `"IBM Plex Sans", sans-serif` (`--bs-body-font-family` del mismo CSS) → `--mkt-fuente`.

### 2.2 Acento de marca (D6)

Cada empresa tiene `color_primario` / `color_secundario` (`marketing_empresas`). El kit lo recibe por
`<mkt-raiz :color-marca="…">` y lo expone como `--mkt-marca`. **Meganet**: `#0066CC` (primario) /
`#FF6600` (secundario), los mismos del Brand Kit (`marketing_settings`, grupo `brand`).

`--mkt-marca` se usa **solo como acento**: ícono del encabezado, filete superior de diálogos, marca de fila
elegida, muestra de color de la empresa. Botones, estados y gráficas siguen la paleta LM (así una marca con
color chillón no rompe la legibilidad de la pantalla). Si la empresa no tiene color válido, se usa `#0066CC`.

### 2.3 Modo oscuro

El sistema cambia `body[data-layout-mode=dark]`. `MktRaiz` lo observa (MutationObserver) y aplica
`mkt-raiz--oscuro`, que redefine (valores de `app.min.css` / `bootstrap.min.css` servidos):

| Variable | Oscuro | Origen |
|---|---|---|
| `--mkt-fondo-tarjeta` | `#313533` | `body[data-layout-mode=dark] .card{background-color:#313533}` |
| `--mkt-borde` | `#3b403d` | mismo selector, `border-color` |
| `--mkt-texto` | `#ced4da` | `body[data-layout-mode=dark]{color:#ced4da}` |
| `--mkt-texto-suave` | `#858d98` | `body[data-layout-mode=dark] .text-muted` |
| `--mkt-fondo-suave` | `#2a2e2c` | derivado |

Nunca escribir colores fijos en una pantalla: siempre `var(--mkt-…)`, así el modo oscuro sale gratis.

### 2.4 Gráficas (fases siguientes)

ApexCharts / Chart.js no leen variables CSS: importar `COLORES` y `SERIE_GRAFICAS` de `kit/tokens.js`.
Orden fijo de series: primario, éxito, info, aviso, peligro, neutro. Ejes y rejilla en `--mkt-borde`,
etiquetas en `--mkt-texto-suave`, sin fondos de color ni 3D.

---

## 3. Espacio, forma y tipografía

- **Espaciado en múltiplos de 8 px**: `--mkt-esp-1` 8 · `--mkt-esp-2` 16 · `--mkt-esp-3` 24 · `--mkt-esp-4` 32.
  - Entre bloques de la pantalla: 24 px (16 px en móvil).
  - Relleno de tarjeta: 16 px vertical × 24 px horizontal (16 × 16 en móvil).
  - Entre KPIs: 16 px (8 px en móvil).
- **Forma**: radio `--mkt-radio` 8 px; borde 1 px `--mkt-borde`; sombra `--mkt-sombra` (casi imperceptible;
  ninguna en oscuro).
- **Tipografía** (IBM Plex Sans):

| Elemento | Tamaño / alto de línea | Peso |
|---|---|---|
| Título de pantalla (h1) | 24/32 (20/28 en móvil) | 600 |
| Título de panel (h2) | 16/24 | 600 |
| Valor de KPI | 28/36, cifras tabulares | 600 |
| Texto de tabla | 14 | 400 (nombre principal 600) |
| Etiqueta / subtítulo | 13–14 | 400–500, `--mkt-texto-suave` |
| Encabezado de tabla | 12, MAYÚSCULAS, +0.02em | 600, `--mkt-texto-suave` |

---

## 4. Estructura de una pantalla

```html
<mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
  <mkt-encabezado-pantalla titulo="…" subtitulo="…" icono="campaign" con-selector-empresa @contexto="…">
    <template #acciones="{ contexto }"> <q-btn unelevated no-caps color="primary" … /> </template>
  </mkt-encabezado-pantalla>

  <section class="…kpis">  <!-- grid 4 columnas ≥1024, 2 columnas <1024 -->
    <mkt-tarjeta-kpi etiqueta="Leads del mes" :valor="…" formato="numero" :variacion="12.5" icono="person_add" />
  </section>

  <mkt-tarjeta-panel titulo="…" subtitulo="…">
    <template #acciones>…</template>
    <mkt-tabla-ejecutiva :columnas="…" :filas="…" :cargando="…" vacio-titulo="…" vacio-texto="…">
      <template #body-cell-estado="props">…</template>
    </mkt-tabla-ejecutiva>
  </mkt-tarjeta-panel>
</mkt-raiz>
```

- **Encabezado**: ícono en cuadro de 48 px con el color de marca, título, subtítulo; a la derecha el selector
  de empresa y las acciones. Una sola acción primaria (relleno); las secundarias `outline` o `flat`.
- **KPIs**: máximo 4 por fila; etiqueta corta (≤ 3 palabras), valor grande, variación vs. periodo anterior
  con flecha y color (verde sube / rojo baja). Sin datos → `—`, nunca `0` inventado.
- **Tablas**: pocas columnas, la primera identifica la fila (en negritas), números alineados a la derecha,
  acciones al final. Columnas secundarias con `ocultarEnMovil: true`.

### Kit disponible

| Tag | Para qué |
|---|---|
| `mkt-raiz` | variables, modo oscuro y arreglo Bootstrap. Raíz de toda pantalla **y de todo diálogo** |
| `mkt-encabezado-pantalla` | encabezado; emite `contexto` y lo pasa al slot `acciones` |
| `mkt-selector-empresa` | empresa activa (chip si es una, `q-select` si son varias; al cambiar recarga) |
| `mkt-tarjeta-kpi` | indicador con formato `numero` / `mxn` / `porcentaje` y variación |
| `mkt-tarjeta-panel` | tarjeta con título, acciones, cuerpo y pie |
| `mkt-tabla-ejecutiva` | `q-table` con estilo, estado de carga y vacío; reexpone `body-cell-*`; slot `vacio-accion` |
| `mkt-estado-vacio` | estado vacío / error / sin permiso con ícono, texto y acción |
| `mkt-insignia-estado` | píldora de estado: `exito`, `aviso`, `peligro`, `info`, `neutro`, `primario` |
| `formato.js` | `formatoMXN`, `formatoNumero`, `formatoPorcentaje`, `formatoFecha`, `mensajeDeError`, `ROLES_EMPRESA` |
| `tokens.js` | colores para gráficas |

---

## 5. Estados obligatorios

| Estado | Cómo se ve | Pieza |
|---|---|---|
| **Cargando** | esqueletos (`q-skeleton`) con la forma del contenido; nunca un spinner a pantalla completa | `cargando` en KPI / panel / tabla |
| **Vacío** | ícono en círculo, título que dice qué falta, texto que dice qué hacer, botón de acción si el usuario puede | `mkt-estado-vacio` / `vacio-*` de la tabla |
| **Error** | ícono `cloud_off`, título «No se pudo cargar …», el `mensaje` del backend, botón «Reintentar» | `mkt-estado-vacio` |
| **Sin permiso** | ícono `lock`, explicación de qué se necesita y a quién pedirlo; **sin botones de acción** | `mkt-estado-vacio` |
| **Error de una acción** | aviso (`q-banner`, `role="alert"`) dentro de la tarjeta o del diálogo, con el `mensaje` del backend; los errores 422 se pintan en su campo | — |
| **Éxito de una acción** | toast corto (`helpers/toastMsg.js → message()`) que dice qué cambió | — |

Mientras una petición de guardado está en curso el botón lleva `:loading` (evita doble clic) y el diálogo no se
cierra. `$q.notify` **no** está instalado en este app: usar `message()`.

---

## 6. Responsive (375 px)

- Breakpoints: `< 600` móvil, `< 1024` tableta.
- Acomodo propio con **CSS grid/flex y clases `mkt-*`**, nunca `row`/`col-*` (son de Bootstrap y Quasar a la vez).
- En móvil: encabezado apilado, acciones a todo el ancho, KPIs en 2 columnas, columnas secundarias ocultas,
  diálogos a `calc(100vw - 32px)` con botones a todo el ancho.
- La tabla puede desplazarse **dentro** de sí misma; la página nunca.

---

## 7. Íconos

- **`q-btn` / `q-icon` / kit**: Material Icons por nombre (`icon="add"`, `"domain"`, `"groups"`, `"edit"`).
  La fuente servida es `public/plugins/quasar/extras/material-icons/`.
- **Font Awesome** solo fuera de Quasar (Blade, `<i class="fa …">`, `module.json`), siempre **FA5** y validado contra
  el CSS servido antes de usarlo:
  ```bash
  grep -o "fa-NOMBRE:before{content:[^}]*}" public/assets/css/icons.min.css
  ```
  Sin regla `content` = botón vacío. Nada de nombres FA4 con `-o`.

---

## 8. Formato de datos

- Dinero: `formatoMXN(1234.5)` → `$1,234.50 MXN`. Nulo → `—`.
- Números: `formatoNumero` (es-MX, separador de miles). Porcentajes: `formatoPorcentaje(12.34)` → `12.3 %`.
- Fechas: `formatoFecha(iso)` → `12 nov 2026` (zona America/Mexico_City); `{ conHora: true }` agrega la hora.
- Texto de IA o de usuarios: **nunca** `v-html`; siempre interpolación `{{ }}`.

---

## 9. Reglas técnicas (no negociables)

1. **Todo `<style>` es `scoped`.** Un `<style>` sin scoped se inyecta en toda la app (ya rompió modales).
   Variables en la clase raíz `.mkt-raiz`, nunca en `:root`.
2. **Bootstrap `.row > *`** (ancho 100 % + padding de gutter) rompe los internos de Quasar. `MktRaiz` lo neutraliza
   para todo lo que contiene. Lo que Quasar dibuja **fuera** de la raíz (opciones de `q-select`, menús) se arregla
   en el slot `option` con un `q-item` de clase propia y la misma regla scoped (ver `MktSelectorEmpresa.vue`).
3. **Diálogos**: el contenido de `q-dialog` se teletransporta fuera de la pantalla → envolverlo en su propio
   `<mkt-raiz>` dentro del `q-card` para heredar variables, modo oscuro y arreglo de Bootstrap.
4. Acciones según permisos: el botón **no aparece** si no se puede (capacidades del contexto o `v-hasPermission`).
5. `console.*` se borra en el build de producción; si un error debe verse, `window.console.error`.
6. Sin librerías nuevas. Disponibles: Quasar 2.10 (lista cerrada en app.js), ApexCharts (`vue3-apexcharts`),
   Chart.js, FullCalendar, drag & drop nativo.
7. Pantallas nuevas: registrar el tag en `registrar.js` (no en app.js).

---

## 10. Fase 1 — piezas nuevas (2026-11-13)

> **Cambio de alcance (Henry, 13-nov): solo Meganet.** `mkt-encabezado-pantalla` sigue cargando el contexto
> (`con-selector-empresa` → emite `contexto` con `capacidades` y color) pero **no muestra** el selector; para volver a
> mostrarlo existe la prop `mostrar-selector` (por defecto `false`). «Empresas y equipo» no se ofrece en la barra ni en
> botones (la pantalla existe, oculta).

### 10.1 Navegación del módulo — `mkt-navegacion-modulo`

- Va en **todas** las pantallas, dentro de `<mkt-raiz>`, **debajo del encabezado**. Sin props.
- Lee `GET /api/marketing/navegacion` (orden de `module.json → menu[0].children`, ya filtrado por permiso). Marca el item
  cuya URL —o la de uno de sus `sub`— coincide **más largo** con la ruta actual; si el item activo trae `sub`, pinta una
  segunda fila de pestañas.
- A 375 px se desplaza **dentro de sí misma** (la página no). Si la petición falla, no pinta nada.
- Enlaces `<a href>` normales (la navegación SPA los toma).

### 10.2 Diálogo — `mkt-dialogo`

```html
<mkt-dialogo v-model="abierto" titulo="Nuevo lead" :guardando="guardando" :ancho="560">
  …campos…
  <template #acciones>
    <q-btn flat no-caps label="Cancelar" :disable="guardando" @click="abierto = false" />
    <q-btn unelevated no-caps color="primary" label="Guardar" :loading="guardando" :disable="guardando" @click="guardar" />
  </template>
</mkt-dialogo>
```

- Trae su propia `<mkt-raiz>` (variables, modo oscuro, arreglo de Bootstrap): **no** se envuelve otra vez.
- `guardando` bloquea cerrar (ni Esc ni clic fuera). Filete superior con el color de marca.
- Móvil: `calc(100vw - 32px)` y botones a todo el ancho. Para confirmar un borrado: botón `color="negative"`.
- Reemplaza a `$q.dialog` / `$q.notify`, que **no** están instalados en este app (avisos: `message()`).

### 10.3 Gráfica de embudo (Dashboard)

- ApexCharts barra horizontal `distributed`, colores `COLORES_EMBUDO` de `kit/tokens.js` (Nuevo neutro → Contactado
  info → Calificado aviso → En negociación primario → Ganado éxito) y opciones base `opcionesBaseGrafica(oscuro)`.
- Al lado, la **misma información en texto** (etapa, total, % del mes) para lectura rápida y lectores de pantalla; la
  gráfica lleva `aria-hidden`. Pie del panel: total, perdidos y archivados. Sin leads → estado vacío, nunca barras en 0.

### 10.4 Estado «no encontrado»

- Una ficha por id que responde **404** (no existe o ya se eliminó) muestra `mkt-estado-vacio` con ícono `search_off`,
  título **«No encontrado»** y botón para volver a la lista. Nunca pantalla en blanco ni datos de otro registro.
- **403** → ícono `lock`, sin botones de acción. **Error** → `cloud_off` + mensaje del backend + «Reintentar».

### 10.5 Ayudantes nuevos (`kit/formato.js`, `kit/tokens.js`, solo agregados)

| Función | Para qué |
|---|---|
| `formatoMoneda(v, moneda)` | MXN → `formatoMXN`; otra → `$1,234.50 USD` (sin convertir) |
| `ESTADOS_LEAD`, `etiquetaEstadoLead`, `tonoEstadoLead` | etiqueta y tono de insignia del estado del lead |
| `mesesRecientes(n)` | selector de mes (mes actual y anteriores, zona America/Mexico_City) |
| `erroresPorCampo(e)` | 422 → `{ campo: mensaje }` para `:error-message` |
| `puedePermiso(this.$store, p)` | ocultar botones según permiso Spatie (true mientras el store no carga) |
| `iniciales`, `esSinPermiso`, `esNoEncontrado` | avatares y ramas de error |
| `COLORES_EMBUDO`, `opcionesBaseGrafica(oscuro)` | gráficas con la paleta del kit |

---

## 11. Fase 2 — Campañas 360 (2026-11-13)

Piezas en `resources/js/components/module/marketing/campanas360/`. Catálogos y límites espejo del contrato en
`catalogos360.js` (el backend manda: cualquier 422 gana sobre la revisión local); sondeo en `sondeo.js`.

### 11.1 Asistente por pasos

- `q-stepper` plano dentro de un `mkt-tarjeta-panel`: **Objetivo → Público → Oferta → Canales → Presupuesto y fechas →
  Revisar**. En móvil, `contracted` (solo íconos). Debajo de cada título, una línea de ayuda que dice qué se pide.
- Opciones con peso (objetivo, canales) como **tarjetas seleccionables** (ícono en cuadro de 40 px, título, ayuda), no como
  radios sueltos. Seleccionada = borde y fondo tenue en `--mkt-primario` + `check_circle`.
- Navegación en el pie del panel: «Atrás» (flat) · espacio · «Guardar borrador» (outline) · «Siguiente» / «Generar con IA»
  (relleno). En móvil, botones a todo el ancho.
- Validación en línea por paso al pulsar «Siguiente»; los 422 del backend se pintan en su campo y llevan al primer paso
  con error (el paso se marca en rojo). Montos con prefijo `$` y sufijo `MXN`; el `hint` muestra el monto con `formatoMXN`.
- **Guardar** en cualquier paso: la primera vez crea (POST) y la URL pasa a `/{id}/editar` (`history.replaceState`), así
  recargar no duplica. «Revisar» resume cada bloque con su botón «Cambiar» y lista las piezas que preparará la IA.

### 11.2 Tarjeta de guion y editor de escenas

- **Tarjeta** (`TarjetaGuion`): título «Guion de Reel A», subtítulo con título del guion · formato · duración; acciones
  Editar · Regenerar · **Producir en Video MVM** (relleno). Cuerpo: gancho y **tabla de escenas** (#, duración, toma/visual,
  texto en pantalla en negritas, voz en off, música/sonido) con fila de total. En móvil cada escena es una tarjeta
  «etiqueta: valor».
- **Editor** (`EditorGuionEscenas`, `mkt-dialogo` de 960 px, persistente): barra de resumen arriba (escenas y total contra el
  límite; verde dentro, rojo fuera), una tarjeta por escena con subir/bajar/quitar, contadores de caracteres por tipo de
  visual (`broll` 60, `texto` 18, `cta` 24, `logo` sin texto), avisos en ámbar (voz en off larga) y errores en rojo. «Agregar
  escena» inserta antes del cierre. Guardar se bloquea mientras haya errores locales.

### 11.3 Estado de producción de video

| Estado | Insignia | Qué más se ve |
|---|---|---|
| En cola | `neutro` «En cola» | barra en 0 + «Esperando turno en el procesador de videos…» |
| Produciendo | `info` «Produciendo» | barra indeterminada + «Armando escenas, voz y música…» |
| Listo | `exito` «Listo» | `<video controls preload="none">` con la miniatura como `poster` (9:16 angosto, 16:9 ancho) |
| Error | `peligro` «Error» | banner con el mensaje corto del backend + «Puedes volver a producirlo» |
| Demorado | (el de su estado) | banner ámbar `hourglass_bottom`: «Sigue en cola: el procesador de videos no está corriendo. Avísale a Irving.» |
| Sin voz | `aviso` «Sin voz» | nota: revisar que la IA de voz esté asignada |

- «Producir» se deshabilita mientras ese guion tenga un video en cola o produciendo. El diálogo precarga voz y música del
  guion y muestra formato (1080 × 1920 / 1920 × 1080), escenas y duración; un 422 `guion_invalido` lista los errores por escena.
- La ficha consulta `…/progreso` cada 5 s solo mientras haya algo generando, en espera, en cola o produciendo; se pausa con la
  pestaña oculta y se detiene a los 30 min con aviso y botón «Revisar ahora».

### 11.4 WhatsApp: solo texto

- Banner informativo fijo arriba de los mensajes: «Solo texto: estos mensajes no se envían desde LM. Cópialos y úsalos donde
  corresponda.» Cada mensaje en burbuja con su uso (Difusión, Seguimiento, Respuesta a quien pregunta).
- Acciones: **Copiar** (relleno), Editar, Regenerar. **Nunca** un botón de enviar ni nada conectado a un gateway.

### 11.5 Otros

- Piezas en pestañas por canal (`q-tabs` con flechas en móvil); un punto `info` en la pestaña si algo está generando y
  `negative` si algo falló. Copies A/B lado a lado; anuncios con vista tipo anuncio y contadores `largo / tope` (rojo si se pasa).
- Reparto del presupuesto: barra horizontal ApexCharts (`SERIE_GRAFICAS`, `opcionesBaseGrafica`) + la misma información en
  tabla (canal, %, monto MXN, total = presupuesto). Gráfica `aria-hidden`.
- «IA no configurada» (422 `ia_no_configurada`) → `mkt-estado-vacio` con ícono `smart_toy` y el mensaje del backend; no se
  reintenta solo.
- Ayudante nuevo: `formatoDuracion(seg)` en `kit/formato.js` → `27.6 s`, `1 min 15 s`.

### 11.6 Menú de Marketing (§2.11, aprobado por Henry el 13-nov)

- **Una sola fuente**: `module.json → menu[0].children`. De ahí salen el **submenú lateral**
  (`resources/views/module-sidebar/marketing.blade.php`) y la **barra interna** (`mkt-navegacion-modulo`), en el mismo
  orden. Reordenar o renombrar = editar module.json; no se escribe el menú a mano en ningún Blade.
- Orden: **Panel · Campañas 360 · Reels y videos · Publicación · Leads · Pruebas A/B · Configuración**. Contenido
  (Fase 3) y Analítica (Fase 5) se agregan a module.json cuando existan.
- Pestañas (`sub`) solo en la barra interna: Reels y videos (Plantillas · Generar · Video por nicho · Fila de producción),
  Publicación (Resumen · Publicar · Fila de publicación), Leads (Leads · Conversaciones · Formularios de captura),
  Configuración (Brand Kit · Voces · Canales de publicación · Integraciones). Una pestaña con `permission` se oculta a quien
  no lo tiene. Cada sección de primer nivel pide el mismo permiso que exige la pantalla que abre (Reels y videos
  `view-video-templates`, Publicación `view-publishing-dashboard`, Leads `marketing.leads.view`, Configuración
  `marketing.brand_kit.configure`); Panel, Campañas 360 y Pruebas A/B solo piden la regla de acceso de la Fase 1.
- El submenú lateral **solo se pinta** si `EquipoMarketing::puedeEntrar(usuario)` (activo + equipo fijado + fila «admin»
  activa en Meganet). Íconos del lateral en `icono_fa` (FA5 validados contra `icons.min.css`); los de la barra en
  `icono_material`.

## 12. Diseño unificado con Clientes (§2.13, 13-nov) — MANDA sobre las secciones 3, 4, 10.1 y 11.6

Referencia única: Clientes (Listado `/cliente/listar` y ficha `/cliente/editar/{id}`), solo lectura. Detalle por tipo de
pantalla en `docs/marketing/diseno-propuesta.md`. Lo que cambia respecto de lo anterior:

- **Breadcrumb** en cada vista Blade, antes del componente: `<Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},…,{"title":"<pestaña>","active":true}]'></Breadcrumb>`.
- **Encabezado** (`mkt-encabezado-pantalla`): ya no hay cuadro de ícono (#0066CC) ni subtítulo; es la q-card del
  Listado de Clientes con título `text-h6` (20 px / 500), filtros en la misma fila (`#filtros`, campos de 200 px,
  búsqueda `mkt-filtro--busqueda` de 240 px), acciones a la derecha (`#acciones`) y la tabla en el cuerpo (slot por
  defecto). El título es el nombre de la pestaña del menú.
- **Navegación**: las 7 secciones viven en el submenú lateral; «Marketing» abre el Panel. En la pantalla hay UN solo
  grupo de pestañas tipo carpeta (`mkt-pestanas`, copia de `.cc-folder-tabs` de la ficha de Cliente): las
  sub-secciones de la sección activa, que pinta el propio encabezado. Nunca flechas: pasan a otra línea.
- **Ficha** (campaña, lead): `mkt-pestanas` → `mkt-ficha-encabezado` (avatar, nombre · #id, estado, datos, cifras,
  botones) → bloques `mkt-tarjeta-panel` con `tono` (lila, azul, verde, crema, oliva, salmon, rosa, gris) e `icono-fa`.
- **Tabla** (`mkt-tabla-ejecutiva`): «Elementos por página», «No hay elementos para mostrar», «1-10 de 50», encabezados
  sin mayúsculas ni fondo, filas de 48 px, `tabla-listado` + `tituloSiCorta`, tres puntos y «Buscar».
- **Botones** (`mkt-boton`, Bootstrap `.btn` 38 px): crear verde, guardar/principal azul (`btn-info`), eliminar rojo,
  volver gris, secundario con borde. Los solo-ícono, `q-btn flat round dense` sin color.
- **Estado**: `mkt-insignia-estado` = `.estado-chip` del sistema. **Cifras**: `mkt-tarjeta-kpi` = `.cic-stat` de la ficha.
- **Esquinas** 4 px (q-card) y 12 px solo en bloques de ficha; **oscuro** igual que Clientes (`#1a1a1a`, bloques `#1d1d21`).

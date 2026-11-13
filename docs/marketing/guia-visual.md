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

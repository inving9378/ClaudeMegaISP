## 2026-09-18 — Talento/Asistencia: tema Torre + fix del checkbox "Solo flagged"

**Pedido de Irving:** adelantar trabajo del día siguiente — modificar los estilos de
Asistencia para que se adapten al tema Torre ya aplicado en Órdenes de Trabajo,
Compensación y Liquidaciones Semanales.

**Cambios (`resources/js/components/module/talento/TalentoAsistencia.vue`):**
- Raíz envuelta en `tc-wrap`/`tc-dark` (igual patrón que los otros 3 módulos de
  Talento) + `tc-card`/`tc-cardhead`/`tc-h1`.
- Botones de rango "Hoy"/"Esta semana": de `btn-primary`/`btn-outline-secondary`
  (Bootstrap genérico) a `tc-btn-info`/`tc-btn-seg` (toggle activo/inactivo).
- Badge "N flagged", flag por fila, pill de "Abierta/Cerrada/Flagged" (estado abierto
  sin check-out), pill de tipo de día y pill de geocerca: todos migrados a `tc-status`
  con sus variantes (`is-warn`/`is-info`/`is-ok`/`is-bad`/`is-slate`).
- Botón "Ver" de cada fila → `tc-btn tc-btn-info` (mismo patrón que Liquidaciones).
- Select de estado y de tipo de día → clase `tc-select` (chevron propio).
- Modal de detalle: header con tinte de aviso cuando el registro está flagged
  (antes `bg-warning-subtle`, transparente en oscuro — mismo hallazgo ya
  documentado en Liquidaciones con `bg-warning-subtle`/`bg-light`) → nueva clase
  local `tc-modal-header-warn`. Botón "Guardar" → `tc-btn-ok`, "+ Agregar" extensión
  → `tc-btn-seg`. El panel "Agregar extensión" (`card bg-light`) → nueva clase local
  `asis-subpanel` (`background: var(--tc-bg2)`, mismo hallazgo de `.bg-light`).
- Fila flagged: antes `table-warning` (Bootstrap, no theme-aware) → clase local
  `asis-row-flag` con el mismo tinte ámbar que `.tc-sellerbar--warn`.
- `fullName()` agregado (mismo patrón que Órdenes/Compensación/Liquidaciones — el
  nombre solo con `att.colaborador?.user?.name` puede confundir colaboradores que
  comparten nombre de pila).
- **Fix de paso, no solo estilo:** `statusBadge()` devolvía un string de HTML
  (`<span class="badge...">Abierta</span>`) que el template interpolaba con `{{ }}`
  (sin `v-html`) — se veía el `<span>` literal como texto en pantalla. Reemplazado
  por `openStatusVariant()`/`openStatusLabel()` + `tc-status`, igual que el resto de
  pills de la pantalla.

**Bug funcional real encontrado en vivo (reportado por Irving durante la prueba):**
el badge "N flagged" (`loadFlaggedCount()`) cuenta TODO el historial sin filtro de
fecha, pero el checkbox "Solo flagged" se combinaba con el rango de fecha vigente
(`from`/`to`, default "Hoy") — si los registros flagged reales caían fuera de ese
rango (como en los datos de prueba: 23/jun y 05/jun, no "hoy"), el checkbox no
mostraba nada aunque el badge dijera "2 flagged". Corregido: al activar el checkbox
se limpia `from`/`to` (coherente con lo que el badge ya cuenta, sin rango); al
desactivarlo, vuelve a "Hoy" (`onFlaggedChange()`).

**Verificado con Playwright** (login real, `/talento/asistencia`): claro y oscuro,
con datos reales de la BD de dev — pills, botón "Ver", fila resaltada, modal de
detalle (header de aviso + panel de extensión + selects), y el checkbox "Solo
flagged" mostrando los 2 registros reales tras el fix. `grep` confirmó que ambos
cambios (theme + fix) llegaron al bundle compilado antes de las capturas.

**Corrección tras feedback de Irving (mismo día, segunda vuelta):** el fix anterior
(limpiar fechas solo al marcar "Solo flagged") enmascaraba el problema real en vez
de resolverlo — la vista por defecto seguía siendo "Hoy", así que sin datos del
día de hoy en la BD la lista salía vacía SIEMPRE (flagged y no-flagged por igual),
dando la impresión de que los flagged estaban ocultos "a propósito". Corregido de
raíz: nuevo botón **"Todos"** (tercer toggle junto a Hoy/Esta semana) que pasa a
ser el estado inicial por defecto (`range: 'all'`, `filters.from/to: ''`) — la
lista ahora muestra TODO por defecto (flagged mezclado con el resto, tal como se
esperaba), y "Solo flagged" vuelve a ser un filtro simple y compositivo
(`@change="load"`) que actúa sobre lo que ya se esté viendo, sin tocar fechas por
detrás. Verificado con Playwright: vista por defecto muestra las 5 filas de
prueba (2 flagged con su tinte, 3 normales); marcar el checkbox sobre esa misma
vista narrows a los 2 flagged sin alterar los campos de fecha (que siguen vacíos).

**Commit:** ver historial (`fix/feat` en `TalentoAsistencia.vue`), pusheado a `main`.

## 2026-09-21 — Talento Fase B, lote 2 (campo): tema Torre + 2 fixes en Academia

**Contexto:** continuación del plan `ethereal-fluttering-simon.md`, Fase B. Segundo
lote temático: Campo, Sitios, Rutas, MapaVivo, Dispositivos — 1580 líneas en total,
mismo método que el lote 1 (5 agentes en paralelo, receta de 10 pasos, verificación
centralizada después).

**Resultado por pantalla:**
- **Campo** (549 líneas): 9 tarjetas, 9 botones, 12 badges (incluye `statusColor()`
  reescrito preservando el mapeo semántico original — `completed`→ámbar
  "pendiente de validar" vs `validated`→verde "estado final", NO es un bug, es el
  flujo de negocio real). 4 selects. Gaps: slots de media/firma (`bg-success-subtle`/
  `bg-light`) y el stepper (`bg-light text-muted border` en estado pendiente) →
  clases locales `.tc-slot-filled`/`.tc-slot-empty`/`.tc-step-pending`.
- **Sitios** (280 líneas): 6 botones, 2 badges, 2 selects. Mapa Leaflet vive dentro
  del modal de alta — fuera del `tc-card` de listado, sin conflicto de overflow.
  Gap: placeholder de carga del mapa → `.tc-map-placeholder`.
- **Rutas** (356 líneas): 8 botones, 4 badges + `statusColor()` remapeado. Vista de
  detalle (mapa + panel lateral con scroll propio) dejada FUERA de cualquier
  `tc-card` a propósito — evita el conflicto `overflow:hidden` vs panel scrollable.
  2 gaps resueltos (`bg-light` del mapa, `bg-warning-subtle` de la tarjeta de
  desvíos).
- **MapaVivo** (186 líneas): decisión de diseño correcta — mapa+panel lateral
  formaban visualmente una sola caja antes de Torre, así que se envolvió TODO en
  un único `tc-card p-0` con `overflow:visible` (no dos tarjetas separadas), para
  no romper el layout original. 1 botón, 2 badges. Gap: placeholder de carga.
- **Dispositivos** (209 líneas): 4 botones, 4 badges. Sin gaps. La sección de
  "Dispositivos vinculados" no tenía tarjeta antes — se construyó de cero.

**Pedido de Irving durante el lote (atendido en paralelo, archivo distinto a los
del lote 2 — sin conflicto):**
- **Franja blanca en Academia**: hallazgo real — el tema Torre cubría `.card`/
  `.card-header`/`.card-body` pero NO `.card-footer` (donde vive el botón "Ver
  curso" de cada tarjeta de curso). Corregido en el archivo GLOBAL
  `_torre-theme.scss` (no un parche local) — beneficia a cualquier pantalla que
  use `.card-footer`, no solo Academia.
- **Pills de departamento sin diferenciar**: "técnicos" y "general" mostraban
  ambos `is-slate` (gris). Se agregó `departmentVariant(dept)` — mapa fijo para
  departamentos conocidos (técnicos→`is-info` azul, general→`is-accent` teal) +
  fallback determinista (hash del texto) para cualquier departamento nuevo que se
  dé de alta a futuro, para que nunca colisionen dos plazas distintas por "no
  estar en la lista".

**Verificado:**
- Barrido `grep "badge bg-\|class=\"badge\""` sobre los 5 archivos del lote → 0
  resultados.
- `npm run dev` compiló limpio (lote 2 + fix global de `.card-footer` + fix de
  Academia).
- Playwright real (usuario `david_marsal`), las 6 pantallas (5 del lote + Academia
  re-verificada): todas cargan sin errores nuevos, `.tc-wrap` presente, capturas
  confirman franja blanca eliminada y pills de departamento visualmente distintos.

Rama `feat/talento-fase-b-torre-lote2-campo`, mergeada a `main`.

## 2026-09-21 — Talento Fase B, lote 5 (catálogos/dashboard): tema Torre — CIERRA LA FASE B

**Contexto:** continuación del plan `ethereal-fluttering-simon.md`, Fase B. Quinto
y último lote temático: Dashboard, Cajas, Embajadores, Artículos vendedor, Ventas,
Proyectos — 1949 líneas en total, mismo método que los lotes anteriores (6
agentes en paralelo, receta de 10 pasos, verificación centralizada después).

**Resultado por pantalla:**
- **Dashboard** (313 líneas): 4 tarjetas KPI (colaboradores activos, asistencia,
  órdenes, alertas), 4 tabs (Producción diaria/Mi panel/Calculadora de pago/Mi
  equipo), 4 selects, 1 botón. Gaps: los 4 íconos de KPI (`bg-*-subtle`) →
  clases locales `tdb-icon-*`; fila resaltada del simulador de pago
  (`table-success`) → `.tdb-row-highlight`. Hallazgo fuera de alcance (NO se
  tocó, es un bug preexistente sin relación con estilos): el ternario de la
  barra de progreso de "Cuota semanal" está mal escrito como string literal en
  vez de expresión — queda anotado para revisión aparte.
- **Cajas** (236 líneas — fotos de custodia de herramientas para bono de salud,
  NO dinero, concepto distinto a la caja de Vendedores): 3 botones, 2 badges, 2
  tarjetas nuevas (Baselines/Log de bonos). Sin gaps.
- **Embajadores** (211→237 líneas): 6 badges, 2 botones. Gap: 2 headers de modal
  con `bg-success/info-subtle` (con `!important`, pelean con la regla del theme)
  → clases locales `tae-chead-ok`/`tae-chead-info`. **Hallazgo de backend,
  preexistente y fuera de alcance de esta migración de estilos**: las columnas
  "Es Embajador"/"Referidos"/"Comisiones acumuladas" muestran "—" para todos los
  colaboradores porque `TalentoEmbajadoresController::embajadorData()` consulta
  `clients.name`, columna que no existe en esa tabla (el nombre vive en
  `client_main_information` según la convención ya documentada del proyecto) →
  las 20 peticiones fallan con 500, atrapadas por `.catch(() => null)` así que
  la pantalla no se rompe, solo pierde ese dato. No se tocó el controller (fuera
  de alcance de esta tarea) — queda como hallazgo para decisión de Irving.
- **Artículos vendedor** (107 líneas): 2 botones de paginación. Sin badges, sin
  gaps.
- **Ventas** ("Mis ventas" self-scoped, 237 líneas): 1 botón, 0 badges. Gap: 3
  íconos de KPI (`bg-*-subtle`) → clases locales `tv-icon-*`.
- **Proyectos** (845 líneas, el más grande del lote): 15 botones, 4 badges, 3
  selects, 2 tarjetas. Gaps: header de "Corredor de línea" (`bg-light`) →
  convertido a `tc-cardhead`; fila de desvío enfocado (`table-warning`) →
  `.tp-row-focused`.

**Verificado:**
- Barrido `grep "badge bg-\|class=\"badge\""` sobre los 6 archivos → 0
  resultados.
- `npm run dev` compiló limpio.
- Playwright real (usuario `david_marsal`), las 6 pantallas, claro y oscuro:
  todas cargan con `.tc-wrap` presente y sin errores de consola nuevos
  atribuibles a esta migración. Se encontró un error real de backend en
  Embajadores (arriba, `clients.name` inexistente) — confirmado preexistente
  (el diff de este lote no toca ese controller ni esas líneas del componente) y
  gracefully degradado (la pantalla no se rompe). Capturas confirman contraste
  correcto en ambos modos.

Rama `feat/talento-fase-b-torre-lote5-catalogos`, mergeada a `main`.

---

**CIERRE DE LA FASE B:** con este lote quedan las 25 pantallas admin de Talento
con el tema Torre aplicado (lotes 1-5: personal, campo, documentos, calidad,
catálogos/dashboard). Pendiente del plan: Fase C (caja diaria de efectivo),
Fase D (comisiones), Fase E (verificación funcional de lo que se solapa con
Vendedores).

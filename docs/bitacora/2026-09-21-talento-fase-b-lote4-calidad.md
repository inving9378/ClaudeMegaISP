## 2026-09-21 — Talento Fase B, lote 4 (calidad): tema Torre

**Contexto:** continuación del plan `ethereal-fluttering-simon.md`, Fase B. Cuarto
lote temático: Calidad, Penalizaciones, Configuración de evidencias, Roadmap —
1805 líneas en total, mismo método que los lotes anteriores (4 agentes en
paralelo, receta de 10 pasos, verificación centralizada después).

**Resultado por pantalla:**
- **Calidad** (677 líneas): 14 botones, 6 badges (incluye `resultColor()`
  reescrito), 6 selects, 2 tabs (Inspecciones/Catálogo). Se reescribió el
  paginador propio (una fila de `btn btn-xs` numerados) a markup real de
  Bootstrap (`nav`/`ul.pagination`/`page-link`) para heredar el estilo del tema en
  vez de quedar aparte. Gap real: las miniaturas del catálogo de estándares
  (`background:#f8f9fa`/`bg-light`, con y sin foto) se veían planas en oscuro →
  clase local `.tc-imgbox` con `var(--tc-bg2)`.
- **Penalizaciones** (861 líneas, la más grande del lote): 18 botones (incluye
  "Aplicar penalización"/"Confirmar decisión" en rojo/ámbar sólido — semántica
  punitiva), 13 badges, 8 selects, 3 tabs (Penalizaciones/Apelaciones/Catálogo de
  tipos). A diferencia del criterio de lotes previos, aquí SÍ se convirtieron los
  botones dentro de los 5 modales — decisión deliberada por consistencia visual
  con el resto del lote, ya que los 6 archivos migrados justo antes (Credenciales,
  Academia, Escalafón, Niveles, Finiquito, Expediente de documentos) ya lo hacían
  así. Gap: 6 usos de `bg-light`/fondo inline `#f8f9fa` (contenedor de imagen) →
  clase local `.pen-bg-neutral`.
- **Configuración de evidencias** (190 líneas): matriz de checkboxes tipo de
  evidencia × tipo de OT, sin tarjeta previa — se construyó desde cero. 1 botón
  ("Reintentar"), 6 badges (incluye los pequeños indicadores dBm/Varias/Firma/
  Justif. dentro de cada fila). Sin gaps.
- **Roadmap** (77 líneas, solo lectura): grid de tarjetas de fases del propio
  proyecto Talento. 2 badges (`badgeClass()`/`statusBadge()` reescritos). Sin
  botones, tablas ni selects — es una vista informativa.

**Verificado:**
- Barrido `grep "badge bg-\|class=\"badge\""` sobre los 4 archivos → 0
  resultados.
- `npm run dev` compiló limpio.
- Playwright real (usuario `david_marsal`), las 4 pantallas, claro y oscuro:
  todas cargan sin errores nuevos en consola (los únicos 404 son el logo
  faltante preexistente, ya documentado en lotes anteriores), `.tc-wrap`
  presente en las 4, capturas confirman contraste correcto en ambos modos
  (tablas, tabs, matriz de checkboxes, tarjetas de fase, miniaturas de
  catálogo con el gap ya resuelto).

Rama `feat/talento-fase-b-torre-lote4-calidad`, mergeada a `main`.

## 2026-09-21 — Talento Fase B, lote 3 (documentos): tema Torre

**Contexto:** continuación del plan `ethereal-fluttering-simon.md`, Fase B. Tercer
lote temático: Credenciales, Finiquito, Paquete de documentos, Documentos
pendientes, Expediente de documentos — 2503 líneas en total, mismo método que los
lotes 1 y 2 (5 agentes en paralelo, receta de 10 pasos, verificación centralizada
después).

**Resultado por pantalla:**
- **Credenciales** (644 líneas): 15 botones, 6 badges (incluye 2 métodos de color
  `credStatusColor`/`fundStatusColor` reescritos), 3 tarjetas, 4 selects. Gaps: un
  `bg-light` en una tarjeta-resumen del modal "Autorizar fondo" → clase local
  `.tc-neutral-bg`; y un hallazgo real — la clase `.table-warning-row` (fila que
  resalta "necesita autorización") **no tenía CSS propio en ningún lugar del
  repo**, nunca funcionó → reemplazada por `.tc-row-pending` con fondo ámbar real.
- **Finiquito** (526 líneas): 7 botones (incluye "Cerrar finiquito" en rojo sólido),
  7 badges, 2 tarjetas, 4 selects. Gap: filas `table-success`/`table-danger`
  (crédito/débito del desglose) → clases locales `.finiquito-row-credit`/
  `.finiquito-row-debit`.
- **Paquete de documentos** (442 líneas): sin estructura de tarjeta previa (usaba
  clases propias `.pkg-docs__*`), se envolvió en `tc-card`/`tc-cardhead`. 6
  botones, 3 badges, 1 select. Sin gaps nuevos — el resto de estilos propios usa
  un sistema de tokens distinto que ya funciona en ambos modos.
- **Documentos pendientes** (258 líneas): 3 botones ("Recordar" en azul info), 2
  badges (incluye contador dinámico por colaborador y badge de días pendientes
  reescrito). Gap: 3 círculos de icono de los KPIs usaban `bg-*-subtle` (planos e
  invisibles en oscuro) → clases locales `.tdp-icon-info/-warn/-bad`.
- **Expediente de documentos** (633 líneas): 9 botones, 1 badge (estado del
  documento). Decisión de diseño: **sin `tc-card` propio** — el componente se
  monta sin encabezado propio, embebido en un modal de `TalentoColaboradores.vue`
  Y en una tarjeta ya provista por `InformationSeller.vue` (vendedores); envolverlo
  en su propio `tc-card` habría dado "caja dentro de caja" en ambos consumidores.

**Verificado:**
- Barrido `grep "badge bg-\|class=\"badge\""` sobre los 5 archivos → 0 resultados.
- `npm run dev` compiló limpio.
- Playwright real (usuario `david_marsal`), las 5 pantallas, claro y oscuro:
  Credenciales, Finiquito y Documentos pendientes tienen URL propia
  (`/talento/credenciales`, `/talento/finiquito`, `/talento/documentos-pendientes`);
  Paquete de documentos vive en `/talento/expediente/paquetes` (no
  `/talento/paquete-documentos` como se asumió al principio); Expediente de
  documentos no tiene URL propia — se probó abriendo el modal "Documentos" desde
  `/talento/` (lista de Colaboradores). Las 5 cargan sin errores nuevos en consola
  (los únicos 404 son un logo faltante preexistente, sin relación), `.tc-wrap`
  presente en las 5, capturas confirman contraste correcto en ambos modos (tablas,
  botones, badges, KPIs con iconos de color, modal).

Rama `feat/talento-fase-b-torre-lote3-documentos`, mergeada a `main`.

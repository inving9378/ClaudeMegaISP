## 2026-10-08 13:10 — "Mi calidad", primer dashboard por rol en el Portal de Colaborador (Fase 1 de 4)

### Contexto

Irving llamó para pedir dashboards distintos por tipo de colaborador (Mostrador, Vendedor,
Técnico Interno, Técnico Externo), cada uno viendo solo sus propias métricas — explícito: "no le
voy a poner que el técnico vea lo de ventas... o que el técnico vea lo de los clientes". Pasó
también un mockup (`Panel de Dashboards y Roles Meganet.png`) y un spec largo generado con
ChatGPT. El mockup mostraba una pantalla "Selecciona tu perfil" para elegir el rol a la entrada —
**rechazado explícitamente por el usuario**: "lo que debe hacer es según el rol mostrar el
dashboard con los datos de cada uno, no mostrar una vista preguntando el rol porque de esa forma
puede que un técnico pueda ver cosas de un mostrador". Decisión final: el dashboard se compone
solo, automáticamente, según los roles reales de la persona — cero selector manual.

Se construyó **un dashboard primero** (de los 4) para validar el patrón visual real de MegaISP
antes de repetirlo: "Mi calidad" para Técnico Interno/Externo, porque ya tenía casi todos sus
datos reales listos (`DashboardService::dailyProduction()`, la misma fuente que ya grafica el
dashboard admin).

### Qué se construyó

- `DashboardService::calidadTecnico(int $colaboradorId, int $dias=180)` — reusa
  `dailyProduction()` tal cual (cero cálculo nuevo de garantías), suma trabajos
  asignados/completados y calcula `% sin incidencias` = completados ÷ (completados + garantías
  no pagables).
- `Actor::esTecnico()` (deriva de `trackPrincipal()`, ya no puede desincronizarse de él) + nueva
  entrada `calidad` en el catálogo de `sections()` — aparece SOLO para TECNICO/TECNICO_PLANTA/
  TECNICO_INSTALADOR.
- Endpoint `GET /talento/portal/mi-calidad` (`PortalTecnicoController::miCalidad`), self-scoped
  por el `Actor` de la sesión — nadie puede pedir los datos de otro.
- Pestaña nueva en `public/talento-portal/app.js` (el Portal es UMD a mano, sin build propio):
  2 tarjetas KPI, donut Chart.js ("Calidad de servicio"), línea Chart.js ("Garantías — últimos N
  días") y lista de trabajos recientes — mismo patrón visual que "Mi material" (`q-card flat
  bordered class="tp-card"`, sin tablas HTML).

### 2 bugs reales encontrados en la verificación de navegador (no solo tinker)

**Bug 1 — Chart.js servía el build equivocado.** `public/chart.js/chart.js` (copiado por
`webpack.mix.js` desde `node_modules/chart.js/dist/chart.js`) resultó ser el build **ESM puro**
(con `import`/`export` reales) — nadie lo había usado nunca como `<script>` plano: el dashboard
admin importa Chart.js vía webpack (`import Chart from 'chart.js/auto'`), así que ese archivo
copiado era peso muerto sin consumidor real. Al cargarlo como script clásico en el Portal
(que sí necesita un global `window.Chart`, al no tener build propio), el navegador tronaba con
`Uncaught SyntaxError: Cannot use import statement outside a module`. Corregido apuntando
`webpack.mix.js` y el archivo servido al build **UMD** real (`chart.umd.min.js`, que sí define
`window.Chart`).

**Bug 2 — el gráfico se dibujaba contra un `<canvas>` que todavía no existía.** La función
`dibujarCalidadCharts()` corría ANTES de bajar `cargandoCalidad.value` — en ese instante el
template seguía mostrando el spinner (`v-if="cargandoCalidad"`), y el bloque con los `<canvas>`
(`v-else-if="calidad"`) todavía no estaba montado en el DOM, así que `document.getElementById(...)`
devolvía `null` y el gráfico nunca se creaba (ni se reintentaba después). Corregido: ahora se baja
`cargandoCalidad` primero (lo que hace que Vue monte el bloque con los canvas), y recién después,
con `nextTick()`, se dibuja.

Ambos se encontraron con Playwright contra un navegador real (mismo método ya usado ayer para las
reglas de desempeño) — con solo `tinker` ninguno de los dos se habría visto, porque los números
del backend eran correctos desde el principio.

### Verificación

- `tinker`: números de `calidadTecnico()` coinciden exactamente con `dailyProduction()` para el
  mismo colaborador (mismo dato, dos caminos).
- Navegador real (Playwright, Chromium local): usuario técnico de prueba (rol TECNICO + ficha
  activa + 3 OTs sintéticas: 2 instalaciones validadas + 1 garantía no pagable) → "Mi calidad"
  aparece en el menú, KPIs "3 / 3", donut 75%/25% verde/rojo, línea con el pico de la garantía en
  la fecha correcta, lista de trabajos recientes con el texto real. Modo oscuro probado aparte:
  colores y texto legibles (los colores del gráfico se leen de las variables CSS del tema en el
  momento de dibujar).
- Usuario Mostrador puro de prueba → "Mi calidad" NO aparece en el menú, y un `GET` directo al
  endpoint devuelve `403 Unauthorized` (doble candado: nav + backend).
- Usuarios y OTs de prueba borrados al final, confirmado por conteo en cero.

### Fuera de alcance de esta vuelta

Mostrador, Vendedor y Técnico Externo (con mapa) — fases 2-4, mismo patrón ya validado acá. La
pantalla admin de "matriz de permisos" tampoco se tocó. Pendiente sin decidir (señalado, no
resuelto): hoy todo técnico en dev también carga el rol Vendedor y por lo tanto ve "Mis
prospectos" en el Portal — es una decisión de producto de Irving, no algo para cambiar sin que él
lo revise junto con el mockup.

### Estado

Commit `ff6dd3ac` en la rama `feature/talento-portal-mi-calidad`, **sin mergear a `main` todavía**
(pendiente de que Irving revise/confirme, igual que el resto de cambios de esta sesión).

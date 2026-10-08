## 2026-10-08 13:45 — "Mi progreso" del Vendedor, segundo dashboard por rol en el Portal (Fase 2 de 4)

### Contexto

Continuación directa de "Mi progreso" del técnico (Fase 1, mergeada hoy). Mismo patrón: el
dashboard se compone solo según el rol real de la persona, sin selector manual.

### Hallazgo clave: ya existía casi todo, solo le faltaba el candado de permiso

Investigando antes de escribir código, apareció `TalentoVentas.vue` + `GET /talento/api/mis-ventas`
(`TalentoVentasController::misVentas`) — una pantalla de "Mis ventas" **ya construida, ya
self-scoped** (resuelve el vendedor por `Actor::for(auth()->user())->seller()`, nunca por un id
que llegue del cliente) y **ya reusando el mismo motor** del dashboard admin de Vendedores
(`StaticsController`). El problema: el permiso `talento.ventas.view` que la protege **solo lo
tenían `super-administrator` y `DESARROLLADOR`** — ningún vendedor real podía abrir esa pantalla,
ni la de admin ni (si hubiera existido) una del Portal. Se corrigió con una migración aditiva que
se lo da al rol `Vendedor` (`givePermissionTo`, nunca `syncRoles`).

Con eso resuelto, "Mi progreso" del vendedor en el Portal no necesitó **ningún** cálculo nuevo:
solo una pestaña nueva que llama al mismo endpoint que ya existía y pinta sus datos con el estilo
del Portal (tarjetas, listas, sin tablas HTML).

### El candado que de verdad importaba: técnico con Vendedor de arrastre

Ya se sabía (documentado en la Fase 1) que **todo técnico en dev también carga el rol Vendedor**.
Si esta pestaña nueva se hubiera gateado por `Actor::seller()` (como ya hace, desde antes, "Mis
prospectos"), un técnico la habría visto también — justo lo que Irving pidió evitar. Se gateó en
cambio por `Actor::esVendedorTrack()` (nuevo, `trackPrincipal() === 'vendedor'`), que ya resuelve
"técnico" como track principal para cualquiera con rol técnico, sin importar qué más cargue. Mismo
mecanismo, mismo nombre de pestaña ("Mi progreso") para ambos casos — nunca se muestran a la vez,
porque el track de una persona solo puede ser uno.

### Qué se construyó

- Migración `2026_10_08_133812_grant_talento_ventas_view_to_vendedor_role` — otorga
  `talento.ventas.view` al rol `Vendedor` (aditiva, con `down()` simétrico).
- `Actor::esVendedorTrack()` — análogo a `esTecnico()`, deriva de `trackPrincipal()`.
- Nueva entrada en el catálogo de `sections()`: tab `mis_ventas`, label "Mi progreso" (mismo texto
  que la del técnico, icono distinto), gateada por `esVendedorTrack()`.
- Pestaña nueva en `public/talento-portal/app.js`: 3 KPIs (ventas / prospectos / perdidas), barra
  Chart.js de ventas+prospectos por fecha, lista "Ventas por medio", lista "Prospectos por
  estado", comparativo mes actual vs. mes anterior — todo reusando la respuesta de
  `/talento/api/mis-ventas` tal cual, sin transformarla.

### Verificación

- Vendedor puro de prueba (rol Vendedor, sin ficha técnica) → "Mi progreso" aparece en el menú,
  carga el endpoint real (`talento.ventas.view` ya concedido), estado vacío se ve limpio (0 en
  todo, mensajes "Sin ventas/prospectos registrados todavía", sin errores).
- Mismo endpoint interceptado con datos realistas (sin tocar la base de datos — ninguna venta/
  prospecto falso se creó) para confirmar visualmente la gráfica de barras, las listas con
  porcentaje y el comparativo mensual: todo pintó correcto con los números esperados.
- Técnico con rol Vendedor de arrastre (el caso real que motivó el cuidado) → en su menú aparece
  **un solo** "Mi progreso" (el del técnico, con los datos de garantías/calidad); el de ventas NO
  aparece — confirmado contando las apariciones del texto y listando los ítems reales del menú.
- Usuarios y filas de prueba (users, talento_colaboradores, sellers) borrados al final, confirmado
  por conteo en cero. Ninguna venta/prospecto/cliente real se tocó.

### Fuera de alcance de esta vuelta

Cuotas semanales (la pieza de "Vendedor" que mencionó Irving en la llamada, ligada a las reglas de
desempeño semanal ya construidas hace 2 días) — hoy no existe una vista self-service de "cómo voy
contra mi cuota de la semana"; construirla es trabajo nuevo de verdad (no una reutilización como
esta pestaña), queda para una fase aparte si se decide. Mostrador y Técnico Externo con mapa —
fases 3 y 4, mismo patrón.

### Estado

Rama `feature/talento-portal-mis-ventas`, sin mergear a `main` todavía.

## 2026-10-08 14:20 — "Mi progreso" del Mostrador, tercer dashboard por rol en el Portal (Fase 3 de 4)

### Contexto

Continuación directa de técnico (Fase 1) y vendedor (Fase 2). A diferencia de esas dos, para
Mostrador **no existía ninguna pantalla self-service previa que reutilizar** — se construyó
agregación nueva, aunque sobre datos que ya existían (pagos capturados, tickets asignados).

### Qué mide "Mi progreso" para un Mostrador

Se investigó el permiso real del rol (`Mostrador` en dev tiene acceso a CRM, clientes, tickets,
captura de pagos — mucho más amplio que técnico/vendedor) y se decidió medir lo más
inequívocamente **propio** de su trabajo diario, con columnas que ya identifican autoría:

- **Pagos que capturó** — `payments.add_by = user_id` (el mismo criterio de autoría que ya usa
  todo el sistema, `Payment::user()`; es justo la pantalla "Registrar pago reportado" que ya usan
  Diana/Ariana, documentada en `CLAUDE.md`).
- **Tickets que tiene asignados** — `tickets.assigned_to = user_id`, `estado='Nuevo'`/`'Cerrado'`
  (mismo criterio que ya usa el admin de Tickets, `TicketController::opened()`/`closed()`).

### Bug de seguridad encontrado y corregido ANTES de que tocara un navegador

Al cargar `payments.add_by` también cargué (de forma inocente, para resolver el nombre del
cliente) la relación `client_main_information` completa — y esa relación trae **columnas
sensibles: la contraseña del cliente en texto plano, email, teléfono, dirección completa**. Se
detectó al probar el método en `tinker` (antes de escribir una sola línea de frontend) viendo el
JSON real que iba a salir. Corregido: la lista de "pagos recientes" ahora pasa por un whitelist
explícito (`id`, `amount`, `created_at`, el nombre ya resuelto, el método de pago) — nunca se
serializa la relación completa. Ningún dato sensible llegó a exponerse (se atrapó en la propia
verificación con `tinker`, antes del primer request HTTP).

### Qué se construyó

- `DashboardService::progresoMostrador(int $userId, int $dias=30)` — agregación nueva (pagos
  hoy/período/monto, serie diaria de pagos, tickets abiertos/cerrados, listas de pagos y tickets
  recientes).
- Endpoint `GET /talento/portal/mi-progreso-mostrador`, gateado por `payments_capture_manage`
  (permiso que el rol Mostrador **ya tenía** — sin migración esta vez).
- `Actor::esMostradorTrack()` + nueva entrada `mi_mostrador` en `sections()`, mismo label "Mi
  progreso" que técnico/vendedor, mutuamente excluyente por `trackPrincipal()`.
- Pestaña nueva en el Portal: 3 KPIs (pagos hoy / tickets abiertos / monto capturado), barra de
  pagos por día, lista de pagos recientes (cliente, método, monto), lista de tickets abiertos con
  insignia de prioridad (mismo mapeo 1=Urgente/2=Alta/3=Normal/4=Baja que usa
  `AssignedTicket.vue` en el admin — se verificó contra ese archivo para no inventar uno propio).

### Verificación

- Mostrador de prueba (rol Mostrador, ficha activa, sin pagos/tickets reales) → "Mi progreso"
  aparece en el menú, estado vacío real se ve limpio (0 en todo, listas con su mensaje vacío, sin
  errores de consola nuevos).
- Mismo endpoint interceptado con datos realistas (sin tocar la base de datos) → KPIs, barra,
  listas y las 3 insignias de prioridad (Urgente/Normal/Baja) pintan correctas.
- Mostrador+Vendedor de prueba (caso real posible: alguien con ambos roles) → el track principal
  resuelve "mostrador" primero (mismo orden de prioridad que ya se usaba para técnico), así que
  ve **un solo** "Mi progreso" — el de mostrador, nunca el de ventas.
- Usuarios y fichas de prueba borrados al final, confirmado por conteo en cero. Ninguna fila de
  `payments`/`tickets` reales se tocó ni se creó.

### Fuera de alcance de esta vuelta

Técnico Externo con mapa — Fase 4, pendiente (requiere más investigación: cajas/distribución en
la vía pública, posible enganche con el módulo Mapa de Red).

### Estado

Rama `feature/talento-portal-mostrador`, apilada sobre `feature/talento-portal-mis-ventas` (que a
su vez está sobre `main` ya con la Fase 1 mergeada) — sin mergear ninguna de las dos todavía.

## 2026-11-10 — VoIP: ticket al colgar, historial de llamadas con grabaciones y rendimiento por trabajador

**Pedido de Irving:** (1) a Mostrador y a todo el que atiende llamadas, al terminar una llamada
llevarlo al listado de tickets para que registre una; si el número no es de un cliente, poder darlo
de alta al momento y, al guardarlo, ir a hacer su ticket; (2) historial de llamadas contestadas y
grabadas con quién contestó y cuánto duró; (3) en `/voip`, un dashboard con eso y el rendimiento de
los trabajadores.

**Decisiones de Irving:** al colgar → listado general de tickets; si no es cliente → solo «alta de
cliente» (no prospecto); aplica a Mostrador, SUPERVISOR_MOSTRADOR y a quien tenga su extensión en un
grupo de timbrado/cola; dashboard y grabaciones **solo administradores**.

Rama `feat/voip-dashboard-tickets`.

### Qué se hizo

| Archivo | Qué |
|---|---|
| `VoIP/Services/HistorialLlamadasService.php` | junta el CDR (`voip_llamadas`) por `linkedid`: dirección, quién contestó (extensión → usuario), espera, duración, grabación, a quién le timbró sin que nadie contestara; cliente por los últimos 10 dígitos (misma regla que la ficha del mini-teléfono); resumen, por día y por trabajador con «con ticket» (ticket levantado por esa persona en los 15 min después de colgar) |
| `VoIP/Controllers/DashboardLlamadasController.php` | `/voip` (sin permiso → Troncales, como antes), `/voip/dashboard/data`, `/voip/dashboard/llamadas` (filtros y páginas), `/voip/grabaciones/{uniqueid}` (escuchar con salto o `?descargar=1`; solo archivos dentro de la carpeta de grabaciones) |
| `VoIP/migrations/2026_11_10_120000_seed_voip_dashboard_permissions.php` | `voip.dashboard.view` y `voip.grabaciones.escuchar` → super-administrator, DESARROLLADOR, Administrador (aditiva) |
| `VoIP/Controllers/MiTelefonoController.php` | `credenciales()` devuelve `ticket_al_colgar` |
| `VoIP/module.json`, `routes.php`, `views/dashboard/index.blade.php` | permisos, endpoints, menú «Dashboard», vista |
| `resources/js/components/module/voip/VoipDashboard.vue` (+ `app.js`) | tarjetas, gráfica por día, rendimiento, historial con reproductor |
| `resources/js/components/megavoz/MegaVozTelefono.vue` | al colgar una llamada contestada con un número externo: «Te llevo a Tickets en 5 s» [Ir ahora] [Quedarme]; si no es cliente, «Dar de alta cliente» |
| `resources/js/components/module/client/AddClientCrud.vue` | `?telefono=` precarga el teléfono; `?despues=ticket` lleva a `/tickets/crear/{id}` al guardar |
| `tests/Unit/VoIP/HistorialLlamadasNormalizarTest.php` | 8 pruebas puras de `normalizar()` |

### Por qué del CDR
Lo escribe Asterisk al colgar: siempre al día. `voip_queue_log` (de `/voip/kpis`) depende de un
importador del scheduler, que en un servidor sin cron no corre. El tablero de KPIs no se tocó.

### Verificado (dev)
- `HistorialLlamadasNormalizarTest` 8/8 (encontró que PHP volvía entero «1003»: corregido).
- Contra el CDR real de dev: 78 llamadas en ~160 ms; separa asistente / no atendidas / internas.
- Endpoints en transacción que se deshace (con la migración aplicada dentro): Administrador ve el
  tablero; `demo_mostrador` (solo Mostrador) recibe 403 en tablero y grabaciones; rango > 93 días
  se recorta; `uniqueid` con `../` → 404; `ticket_al_colgar` true para Mostrador, false para un
  Vendedor sin extensión en grupo. Permisos al terminar: 0 (rollback).
- Escuchar una grabación no se pudo probar desde la terminal: la carpeta es del grupo `asterisk` y
  la lee `www-data`, no `meganet`. Queda para la revisión en el navegador.

### Límites conocidos
- La redirección solo existe en el mini-teléfono del navegador; un teléfono de escritorio no puede
  mover la pantalla (sus llamadas sí salen en historial y rendimiento).
- En dev no hay ninguna llamada de la cola contestada por un agente: ese caso se cubrió con pruebas
  sobre la forma estándar del CDR de Asterisk.
- Dev tiene `voip_*` de producción desde la carga de hoy: el «quién contestó» puede no corresponder.
- En producción no hay Asterisk todavía: el tablero muestra «la central no está instalada».

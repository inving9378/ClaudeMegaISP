# Módulo Tickets

> Mesa de ayuda y soporte técnico del ISP. `app/Modules/Addons/Tickets/` · slug `addon-tickets` · addon activo.

**En simple:** es el buzón donde los clientes reportan sus problemas y el equipo de soporte los atiende, los resuelve y los cierra sin perder el historial.

## 1. Qué es
Sistema de **mesa de ayuda / tickets de soporte**: registra incidencias y solicitudes de clientes, las lleva por el ciclo **abierto → cerrado → papelera (reciclados)**, con hilo de mensajes por ticket y un dashboard de KPIs.

## 2. Para qué sirve
Le da al equipo de **soporte/mostrador** un lugar único para atender problemas de clientes (fallas de servicio, dudas, solicitudes): crear el ticket, asignarlo a un responsable, conversar en su hilo, cambiar su estado y cerrarlo, sin perder el histórico. El **dashboard** resume la carga (cuántos abiertos, asignados a mí, por estado) para priorizar el trabajo del día.

## 3. Cómo funciona
- **Modelo:** `App\Models\Ticket` (tabla `tickets`); campos clave `estado`, `customer_lead` (responsable asignado), `client_id`. El hilo de mensajes vive en su propia tabla, gestionada por `TicketThreadController` (mensajes padre/hijo de un ticket).
- **Controllers** (`app/Modules/Addons/Tickets/Controllers/`):
  - `DashboardController` — pantalla `/tickets`: KPIs por estado, "asignados a mí" y "asignados a".
  - `TicketController` — CRUD y vistas de las bandejas: `opened` (abiertos), `closed` (cerrados), `trash` (papelera), `create`/`store`, `edit`/`update`, `ver`, `destroy`, `table` (DataTable server-side vía `TicketsDatatableHelper`), + endpoints AJAX (`get-ticket-by-id`, `get-time-lapsed`, `set-status-ticket-by-id`, `get-data-client`).
  - `TicketThreadController` — hilo del ticket: `store`/`update` de mensajes, `getTicketThreadById`, `getParentTicketById`, `getChildTicketById`.
- **Repositorio:** `App\Http\Repository\TicketRepository` (queries no triviales de KPIs/listados).
- **Rutas:** todas bajo `/tickets/*`, protegidas por `['web','auth','check_route_permission']` (autorización por URL con spatie/permission).
- **Ciclo de vida:** un ticket nace *abierto* → se atiende en su hilo → se *cierra* → puede mandarse a *papelera* y, con permiso, eliminarse en definitiva. Cada etapa tiene sus propios permisos de ver/crear/editar/eliminar/exportar.

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas web** `/tickets`, `/tickets/abiertos`, `/tickets/cerrados`, `/tickets/reciclados`, `/tickets/crear`, `/tickets/ver/{id}`, + endpoints POST de datos (tabla, estado, hilo, KPIs).
- **16 permisos** `ticket_{view,add,edit,delete,export}_{open,close,recycling}` + `ticket_view_dashboard` (definidos en `module.json`).
- **Menú** en el sidebar (Dashboard / Abiertos / Cerrados / Papelera).

**Consume**
- **Clientes** (`client_id`) — a quién pertenece el ticket (módulo Core/Clientes).
- **Usuarios** (`customer_lead` = responsable asignado) — para asignación y "asignados a mí".
- **Notificaciones** — `notificationsReadMarked` marca como leídas las notificaciones del ticket.
- **Config de campos/columnas** — `config('constants.model.Ticket.FIELDS'/'DATATABLE_FIELDS')` para el formulario y la DataTable.

> _Servicios compartidos únicos: este módulo no monta WhatsApp/IA/Mapas propios; si en el futuro notifica por WhatsApp debe usar el gateway único `WhatsAppGateway` (ver #308 / contratos inter-módulos)._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._

## 2026-09-24 07:00 — MegaVoz Fase 4 (ventana emergente) + Fase 5 (tablero de KPIs)

Continuación directa con David tras cerrar Fase 3 (cola + registro + grabación,
retención confirmada en 90 días). Instrucción: "sigue con lo que falta de voip".

### Fase 4 — ventana emergente al contestar/marcar

Backend nuevo `MiTelefonoController::ficha()` (`GET /voip/mi-telefono/ficha/{numero}`)
**reusa dos piezas que ya existían sin ningún consumidor**:
- `IABotCustomerService::identifyCustomer()` — match de teléfono contra
  `client_main_information` (tolerante a formato, últimos 10 dígitos) + servicio
  activo. Estaba escrito de cara a la IA de Fase 6, nunca conectado a nada.
- `ClientInformationController::getClientWithBalance()`/`getClientTicketsOpen()` —
  las mismas que alimentan la ficha del cliente en el admin.

Nada de queries paralelas nuevas. Sin permiso `voip.*` propio, mismo criterio que
`disponibilidad()` (Fase 2): solo llega ahí quien ya tiene teléfono asignado.

Frontend: `MegaVozTelefono.vue` dispara la búsqueda al identificar la sesión
entrante o al marcar saliente, **solo si el remoto parece un número externo real**
(≥7 dígitos) — una llamada interna entre compañeros no dispara ruido de "no
identificado". Botón "Crear ticket" abre `/tickets/crear/{id}` en **pestaña
nueva** (`target="_blank"`) — nunca en la misma pestaña: `spa-nav.js` remonta el
Vue de la página entera al navegar, y eso mataría la llamada en curso.

Verificado con el cliente de prueba real (id 6810, "FRANCISCO AGUILAR", tel.
5510757244): nombre, saldo (-$4,937), servicio "GAMER 100" y tickets abiertos
(0) resueltos correctamente end-to-end.

### Fase 5 — tablero de KPIs

Importa `/var/log/asterisk/queue_log` (log NATIVO de `app_queue`, no realtime)
a tabla propia `voip_queue_log` — vive en la conexión del **app** (`megaisp`),
no en `asterisk_rt`, porque aquí quien escribe es un comando propio
(`megavoz:importar-queue-log`, cada 5 min, tail por offset de bytes, detecta
rotación de log), nunca Asterisk directo — al revés que `voip_llamadas` (CDR),
que sí la escribe Asterisk.

Por qué hace falta esto y `voip_llamadas` no basta: el CDR ve la llamada
completa (cliente→cola→agente) pero no desglosa qué pasó DENTRO de la cola
cuando varios miembros timbran a la vez (estrategia ringall) — `queue_log` sí,
evento por evento (ENTERQUEUE/CONNECT/COMPLETEAGENT/COMPLETECALLER/ABANDON).

`MegaVozKpiService` calcula: llamadas entraron/atendidas/abandonadas, %
abandono, espera promedio (ponderada entre conectadas y abandonadas), duración
promedio de plática, y desglose por agente (llamadas atendidas + tiempo total +
promedio). Todo sobre el vocabulario real de eventos de Asterisk, nada
inventado. Pantalla nueva `/voip/kpis`, permiso `voip.kpis.view`.

**Verificado con datos reales**: antes de limpiar los eventos que dejaron las
pruebas de Fase 3, el tablero mostró 9 entradas / 9 abandonos / 100% abandono /
5s de espera promedio — coincide exacto con los números crudos del log
(`ABANDON|1|1|2`, `|1|1|8`, `|1|1|7`… promedio real ≈5). Confirma que el
cálculo es correcto, no solo que no truena.

**Nota documentada en la propia UI**: el KPI "llamadas al respaldo UCM" del
plan original de Fase 5 YA NO APLICA — con la regla de oro revisada de Fase 3
(el asistente siempre contesta primero) no existe ningún salto a un UCM
externo que medir; todo pasa por esta misma cola.

### Gotcha de esta sesión (para la próxima vez que se toque VoIP)

`app/Modules/Addons/VoIP/routes.php` **NO usa** `check_route_permission` ni
`config/route_permission.php` — a diferencia de casi todo el resto del sistema.
El permiso se checa INLINE dentro de cada método del controller
(`auth()->user()->can('voip.xxx.view')`), calcado del patrón que ya usaba
`TroncalController`. Se agregó por error una entrada a `route_permission.php`
para `voip.kpis.view` antes de notar esto — revertida antes de commitear.

### Pendiente

- Fase 7 (anuncios/avisos/corte por zona) y Fase 6 (IA de voz real) —
  siguientes del plan, ninguna arrancada.
- Datos de prueba (llamadas de Fase 3, eventos de `voip_queue_log`) limpiados
  antes de cerrar — el tablero de KPIs y el registro de llamadas quedan en
  cero, listos para que David los pruebe con llamadas reales.

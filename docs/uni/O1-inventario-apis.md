# UNI-T O1 — Inventario de APIs de Talento, MegaFamilia y portal

Generado de `php artisan route:list --json` el 2026-10-26 [código]. Solo rutas y nombres técnicos. Base para O2.

## Hallazgos

- [código] Dos contratos sobre la misma lógica de campo: `TalentoMobileApiController`/`TalentoMobileEquipoController` (Sanctum) y `PortalTecnicoController` (sesión web).
- [código] Formatos de error distintos: app móvil `{message}` (12 usos); portal técnico mezcla `{message}` (20) y `{error}` (13).
- [DEV] 7 tokens `talento-mobile`, los 7 sin vencimiento.
- Auth: Sanctum = Bearer; portales = sesión (guard `web` o `cliente`); `LogApiMobileAccess` audita las APIs móviles.

## App de Talento (campo) (62 rutas)

| Método | URI | Acción | Autenticación |
|---|---|---|---|
| GET | `talento/api/app/branding` | TalentoMobileApiController@appBranding | Pública / sin guard |
| GET | `talento/api/app/download-qr` | TalentoMobileEquipoController@downloadQr | Sanctum (Bearer) |
| GET | `talento/api/app/latest` | TalentoMobileApiController@latestRelease | Pública / sin guard |
| POST | `talento/api/asistencia/checkin` | TalentoMobileApiController@checkin | Sanctum (Bearer) |
| POST | `talento/api/asistencia/checkout` | TalentoMobileApiController@checkout | Sanctum (Bearer) |
| GET | `talento/api/asistencia/hoy` | TalentoMobileApiController@asistenciaHoy | Sanctum (Bearer) |
| POST | `talento/api/auth/login` | TalentoMobileApiController@login | Pública / sin guard |
| POST | `talento/api/auth/logout` | TalentoMobileApiController@logout | Sanctum (Bearer) |
| GET | `talento/api/compensacion/semana` | TalentoMobileApiController@compensacionSemana | Sanctum (Bearer) |
| POST | `talento/api/devices/token` | TalentoMobileApiController@registerDeviceToken | Sanctum (Bearer) |
| GET | `talento/api/embajador/resumen` | TalentoMobileApiController@embajadorResumen | Sanctum (Bearer) |
| GET | `talento/api/geocodificar` | TalentoMobileApiController@geocodificar | Sanctum (Bearer) |
| GET | `talento/api/health` | TalentoMobileApiController@health | Pública / sin guard |
| GET | `talento/api/me` | TalentoMobileApiController@me | Sanctum (Bearer) |
| GET | `talento/api/ots/historial` | TalentoMobileApiController@otHistorial | Sanctum (Bearer) |
| GET | `talento/api/ots/hoy` | TalentoMobileApiController@otsHoy | Sanctum (Bearer) |
| GET | `talento/api/ots/{id}` | TalentoMobileApiController@otShow | Sanctum (Bearer) |
| POST | `talento/api/ots/{id}/completar` | TalentoMobileApiController@completarOT | Sanctum (Bearer) |
| POST | `talento/api/ots/{id}/evidencia` | TalentoMobileApiController@otEvidencia | Sanctum (Bearer) |
| POST | `talento/api/ots/{id}/incidencia` | TalentoMobileApiController@reportarIncidencia | Sanctum (Bearer) |
| POST | `talento/api/ots/{id}/iniciar` | TalentoMobileApiController@iniciarOT | Sanctum (Bearer) |
| PUT | `talento/api/ots/{id}/nota` | TalentoMobileApiController@guardarNota | Sanctum (Bearer) |
| GET | `talento/api/ots/{id}/tipos-evidencia` | TalentoMobileApiController@tiposEvidencia | Sanctum (Bearer) |
| GET | `talento/api/portal/academia` | TalentoMobileEquipoController@academia | Sanctum (Bearer) |
| GET | `talento/api/portal/asistencia` | TalentoMobileEquipoController@asistencia | Sanctum (Bearer) |
| GET | `talento/api/portal/cajas` | TalentoMobileEquipoController@cajas | Sanctum (Bearer) |
| POST | `talento/api/portal/cajas` | TalentoMobileEquipoController@cajaRegistrar | Sanctum (Bearer) |
| GET | `talento/api/portal/calidad` | TalentoMobileEquipoController@calidad | Sanctum (Bearer) |
| GET | `talento/api/portal/compensacion` | TalentoMobileEquipoController@compensacion | Sanctum (Bearer) |
| GET | `talento/api/portal/credencial-pdf-url` | TalentoMobileEquipoController@credencialPdfUrl | Sanctum (Bearer) |
| GET | `talento/api/portal/credencial-plantilla` | TalentoMobileEquipoController@credencialPlantilla | Sanctum (Bearer) |
| GET | `talento/api/portal/credenciales` | TalentoMobileEquipoController@credenciales | Sanctum (Bearer) |
| GET | `talento/api/portal/cursos/{cursoId}` | TalentoMobileEquipoController@cursoDetalle | Sanctum (Bearer) |
| GET | `talento/api/portal/custodia` | TalentoMobileEquipoController@custodia | Sanctum (Bearer) |
| GET | `talento/api/portal/dispositivos` | TalentoMobileEquipoController@dispositivos | Sanctum (Bearer) |
| POST | `talento/api/portal/dispositivos/vincular` | TalentoMobileEquipoController@dispositivoVincular | Sanctum (Bearer) |
| GET | `talento/api/portal/documentos` | TalentoMobileEquipoController@documentos | Sanctum (Bearer) |
| GET | `talento/api/portal/documentos/detalle` | TalentoMobileEquipoController@documentoDetalle | Sanctum (Bearer) |
| POST | `talento/api/portal/documentos/{docId}/completar` | TalentoMobileEquipoController@documentoCompletar | Sanctum (Bearer) |
| POST | `talento/api/portal/documentos/{docId}/firma` | TalentoMobileEquipoController@documentoFirmar | Sanctum (Bearer) |
| GET | `talento/api/portal/documentos/{docId}/firma` | TalentoMobileEquipoController@documentoFirmaImagen | Sanctum (Bearer) |
| GET | `talento/api/portal/equipo` | TalentoMobileEquipoController@miEquipo | Sanctum (Bearer) |
| GET | `talento/api/portal/equipo/penalizaciones` | TalentoMobileEquipoController@equipoPenalizaciones | Sanctum (Bearer) |
| POST | `talento/api/portal/equipo/penalizaciones/{id}/confirmar` | TalentoMobileEquipoController@equipoConfirmarPenalizacion | Sanctum (Bearer) |
| GET | `talento/api/portal/equipo/progreso` | TalentoMobileEquipoController@equipoProgreso | Sanctum (Bearer) |
| GET | `talento/api/portal/examenes/{examId}` | TalentoMobileEquipoController@examenTomar | Sanctum (Bearer) |
| POST | `talento/api/portal/examenes/{examId}/enviar` | TalentoMobileEquipoController@examenEnviar | Sanctum (Bearer) |
| GET | `talento/api/portal/examenes/{examId}/intentos` | TalentoMobileEquipoController@examenIntentos | Sanctum (Bearer) |
| GET | `talento/api/portal/informacion` | TalentoMobileEquipoController@informacion | Sanctum (Bearer) |
| POST | `talento/api/portal/inspecciones` | TalentoMobileEquipoController@inspeccionRegistrar | Sanctum (Bearer) |
| POST | `talento/api/portal/inspecciones/{id}/ia` | TalentoMobileEquipoController@inspeccionAnalizarIa | Sanctum (Bearer) |
| GET | `talento/api/portal/liquidaciones` | TalentoMobileEquipoController@liquidaciones | Sanctum (Bearer) |
| GET | `talento/api/portal/penalizaciones` | TalentoMobileEquipoController@penalizaciones | Sanctum (Bearer) |
| POST | `talento/api/portal/penalizaciones/{id}/apelar` | TalentoMobileEquipoController@apelarPenalizacion | Sanctum (Bearer) |
| GET | `talento/api/portal/progreso` | TalentoMobileEquipoController@progreso | Sanctum (Bearer) |
| GET | `talento/api/portal/proyectos` | TalentoMobileEquipoController@proyectos | Sanctum (Bearer) |
| GET | `talento/api/portal/proyectos-catalogo` | TalentoMobileEquipoController@proyectosCatalogo | Sanctum (Bearer) |
| GET | `talento/api/portal/proyectos-catalogo/{id}` | TalentoMobileEquipoController@proyectoDetalle | Sanctum (Bearer) |
| POST | `talento/api/portal/proyectos-catalogo/{projectId}/actividades/{actId}/reportes` | TalentoMobileEquipoController@proyectoReportar | Sanctum (Bearer) |
| GET | `talento/api/portal/roles-multiples` | TalentoMobileEquipoController@rolesMultiples | Sanctum (Bearer) |
| GET | `talento/api/portal/rutas` | TalentoMobileEquipoController@rutas | Sanctum (Bearer) |
| GET | `talento/api/portal/rutas/{id}` | TalentoMobileEquipoController@rutaDetalle | Sanctum (Bearer) |

## Portal del colaborador / técnico (web) (28 rutas)

| Método | URI | Acción | Autenticación |
|---|---|---|---|
| GET | `talento/portal` | PortalTecnicoController@index | Sesión web + portal.colaborador |
| POST | `talento/portal/asistencia/checkin` | PortalTecnicoController@checkin | Sesión web + portal.colaborador |
| POST | `talento/portal/asistencia/checkout` | PortalTecnicoController@checkout | Sesión web + portal.colaborador |
| GET | `talento/portal/asistencia/hoy` | PortalTecnicoController@asistenciaHoy | Sesión web + portal.colaborador |
| GET | `talento/portal/dinero/cuenta` | PortalTecnicoController@dineroCuenta | Sesión web + portal.colaborador |
| GET | `talento/portal/dinero/desglose` | PortalTecnicoController@dineroDesglose | Sesión web + portal.colaborador |
| GET | `talento/portal/dinero/fondo` | PortalTecnicoController@dineroFondo | Sesión web + portal.colaborador |
| GET | `talento/portal/dinero/prestamos` | PortalTecnicoController@dineroPrestamos | Sesión web + portal.colaborador |
| GET | `talento/portal/documentos` | PortalTecnicoController@documentos | Sesión web + portal.colaborador |
| GET | `talento/portal/documentos/{docId}` | PortalTecnicoController@documentoDetalle | Sesión web + portal.colaborador |
| POST | `talento/portal/documentos/{docId}/firma` | PortalTecnicoController@firmarDocumento | Sesión web + portal.colaborador |
| GET | `talento/portal/documentos/{docId}/pdf` | PortalTecnicoController@documentoPdf | Sesión web + portal.colaborador |
| GET | `talento/portal/equipo/penalizaciones` | PortalTecnicoController@equipoPenalizaciones | Sesión web + portal.colaborador |
| POST | `talento/portal/equipo/penalizaciones/{id}/confirmar` | PortalTecnicoController@equipoConfirmarPenalizacion | Sesión web + portal.colaborador |
| GET | `talento/portal/material` | PortalTecnicoController@material | Sesión web + portal.colaborador |
| GET | `talento/portal/mi-calidad` | PortalTecnicoController@miCalidad | Sesión web + portal.colaborador |
| GET | `talento/portal/mi-equipo-progreso` | PortalTecnicoController@miEquipoProgreso | Sesión web + portal.colaborador |
| GET | `talento/portal/mi-progreso-mostrador` | PortalTecnicoController@miProgresoMostrador | Sesión web + portal.colaborador |
| GET | `talento/portal/ot/{origen}/{id}` | PortalTecnicoController@otDetalle | Sesión web + portal.colaborador |
| POST | `talento/portal/ot/{origen}/{id}/aceptar` | PortalTecnicoController@aceptarOt | Sesión web + portal.colaborador |
| POST | `talento/portal/ot/{origen}/{id}/completar` | PortalTecnicoController@completarOt | Sesión web + portal.colaborador |
| POST | `talento/portal/ot/{origen}/{id}/evidencia` | PortalTecnicoController@subirEvidenciaOt | Sesión web + portal.colaborador |
| POST | `talento/portal/ot/{origen}/{id}/firma` | PortalTecnicoController@guardarFirma | Sesión web + portal.colaborador |
| POST | `talento/portal/ot/{origen}/{id}/iniciar` | PortalTecnicoController@iniciarOt | Sesión web + portal.colaborador |
| GET | `talento/portal/ots/hoy` | PortalTecnicoController@otsHoy | Sesión web + portal.colaborador |
| GET | `talento/portal/prospectos` | PortalTecnicoController@prospectos | Sesión web + portal.colaborador |
| GET | `talento/portal/reaperturas` | PortalTecnicoController@reaperturasPendientes | Sesión web + portal.colaborador |
| GET | `talento/portal/sw.js` | PortalTecnicoController@serviceWorker | Pública / sin guard |

## App MegaFamilia (API móvil) (81 rutas)

| Método | URI | Acción | Autenticación |
|---|---|---|---|
| GET | `api/megafamilia/account` | ApiController@account | Sanctum (Bearer) |
| GET | `api/megafamilia/app-version` | ApiController@appVersion | Pública / sin guard |
| POST | `api/megafamilia/auth/login` | ApiController@login | Pública / sin guard |
| GET | `api/megafamilia/cliente/flotas/geocercas` | ClienteFlotasApiController@geocercas | Sanctum (Bearer) |
| GET | `api/megafamilia/cliente/flotas/plan` | ClienteFlotasApiController@plan | Sanctum (Bearer) |
| GET | `api/megafamilia/cliente/flotas/resumen` | ClienteFlotasApiController@resumen | Sanctum (Bearer) |
| GET | `api/megafamilia/cliente/flotas/tiene-flotas` | ClienteFlotasApiController@tieneFlotas | Sanctum (Bearer) |
| GET | `api/megafamilia/cliente/flotas/vehiculos` | ClienteFlotasApiController@vehiculos | Sanctum (Bearer) |
| GET | `api/megafamilia/cliente/flotas/vehiculos/{id}` | ClienteFlotasApiController@vehiculoDetalle | Sanctum (Bearer) |
| GET | `api/megafamilia/cliente/flotas/vehiculos/{id}/documentos` | ClienteFlotasApiController@documentos | Sanctum (Bearer) |
| GET | `api/megafamilia/cliente/flotas/vehiculos/{id}/eventos-geocerca` | ClienteFlotasApiController@eventosGeocerca | Sanctum (Bearer) |
| GET | `api/megafamilia/cliente/flotas/vehiculos/{id}/historial-posiciones` | ClienteFlotasApiController@historialPosiciones | Sanctum (Bearer) |
| GET | `api/megafamilia/cliente/flotas/vehiculos/{id}/mantenimientos` | ClienteFlotasApiController@mantenimientos | Sanctum (Bearer) |
| GET | `api/megafamilia/conductor/documentos` | ConductorApiController@documentos | Sanctum (Bearer) |
| GET | `api/megafamilia/conductor/eventos-geocerca` | ConductorApiController@eventosGeocerca | Sanctum (Bearer) |
| GET | `api/megafamilia/conductor/geocercas` | ConductorApiController@geocercas | Sanctum (Bearer) |
| GET | `api/megafamilia/conductor/historial-posiciones` | ConductorApiController@historialPosiciones | Sanctum (Bearer) |
| GET | `api/megafamilia/conductor/mantenimientos` | ConductorApiController@mantenimientos | Sanctum (Bearer) |
| POST | `api/megafamilia/conductor/posicion` | ConductorApiController@reportarPosicion | Sanctum (Bearer) |
| GET | `api/megafamilia/conductor/posicion-actual` | ConductorApiController@posicionActual | Sanctum (Bearer) |
| GET | `api/megafamilia/conductor/vehiculo` | ConductorApiController@vehiculo | Sanctum (Bearer) |
| POST | `api/megafamilia/devices/link` | ApiController@linkDevice | Pública / sin guard |
| GET | `api/megafamilia/devices/{id}/rules` | ApiController@deviceRules | Sanctum (Bearer) |
| PUT | `api/megafamilia/devices/{id}/rules` | ApiController@updateDeviceRules | Sanctum (Bearer) |
| POST | `api/megafamilia/embajadores/activate` | EmbajadorApiController@activate | Sanctum (Bearer) |
| GET | `api/megafamilia/embajadores/comisiones` | ApiController@embajadorComisiones | Sanctum (Bearer) |
| GET | `api/megafamilia/embajadores/dashboard` | EmbajadorApiController@dashboard | Sanctum (Bearer) |
| GET | `api/megafamilia/embajadores/link` | EmbajadorApiController@link | Sanctum (Bearer) |
| GET | `api/megafamilia/embajadores/notifications-log` | ApiController@embajadorNotificationsLog | Sanctum (Bearer) |
| GET | `api/megafamilia/embajadores/prospects` | ApiController@embajadorProspectos | Sanctum (Bearer) |
| POST | `api/megafamilia/embajadores/prospects` | ApiController@embajadorStoreProspecto | Sanctum (Bearer) |
| POST | `api/megafamilia/embajadores/prospects/import` | ApiController@embajadorImportProspectos | Sanctum (Bearer) |
| GET | `api/megafamilia/embajadores/prospects/{id}` | ApiController@embajadorGetProspecto | Sanctum (Bearer) |
| PUT | `api/megafamilia/embajadores/prospects/{id}` | ApiController@embajadorUpdateProspecto | Sanctum (Bearer) |
| DELETE | `api/megafamilia/embajadores/prospects/{id}` | ApiController@embajadorDeleteProspecto | Sanctum (Bearer) |
| GET | `api/megafamilia/embajadores/prospects/{id}/followups` | ApiController@embajadorFollowups | Sanctum (Bearer) |
| POST | `api/megafamilia/embajadores/prospects/{id}/followups` | ApiController@embajadorAddFollowup | Sanctum (Bearer) |
| GET | `api/megafamilia/embajadores/recompensas` | ApiController@embajadorRecompensas | Sanctum (Bearer) |
| POST | `api/megafamilia/embajadores/recompensas/{id}/aplicar` | ApiController@embajadorAplicarRecompensa | Sanctum (Bearer) |
| GET | `api/megafamilia/embajadores/red` | ApiController@embajadorRed | Sanctum (Bearer) |
| POST | `api/megafamilia/embajadores/share-log` | EmbajadorApiController@shareLog | Sanctum (Bearer) |
| POST | `api/megafamilia/embajadores/share-masivo` | ApiController@embajadorShareMasivo | Sanctum (Bearer) |
| GET | `api/megafamilia/embajadores/status` | EmbajadorApiController@status | Sanctum (Bearer) |
| GET | `api/megafamilia/embajadores/terms` | EmbajadorApiController@terms | Pública / sin guard |
| GET | `api/megafamilia/facturas` | ApiController@facturas | Sanctum (Bearer) |
| PUT | `api/megafamilia/geofences/{id}` | ApiController@updateProfileGeofence | Sanctum (Bearer) |
| DELETE | `api/megafamilia/geofences/{id}` | ApiController@destroyProfileGeofence | Sanctum (Bearer) |
| GET | `api/megafamilia/hijo/apps-permitidas` | ApiController@hijoAppsPermitidas | Sanctum (Bearer) |
| GET | `api/megafamilia/hijo/logros` | ApiController@hijoLogros | Sanctum (Bearer) |
| POST | `api/megafamilia/hijo/solicitudes` | ApiController@hijoStoreRequest | Sanctum (Bearer) |
| GET | `api/megafamilia/hijo/tareas` | ApiController@hijoTareas | Sanctum (Bearer) |
| POST | `api/megafamilia/hijo/tareas/{id}/completar` | ApiController@completeTask | Sanctum (Bearer) |
| POST | `api/megafamilia/locations` | ApiController@reportLocation | Sanctum (Bearer) |
| GET | `api/megafamilia/pagos` | ApiController@pagos | Sanctum (Bearer) |
| POST | `api/megafamilia/pagos` | ApiController@crearPago | Sanctum (Bearer) |
| GET | `api/megafamilia/pagos/{id}/pdf` | ApiController@pagoPdf | Sanctum (Bearer) |
| GET | `api/megafamilia/payments/clabe` | MobilePaymentController@getClabe | Sanctum (Bearer) |
| POST | `api/megafamilia/payments/notify-transfer` | MobilePaymentController@notifyTransfer | Sanctum (Bearer) |
| GET | `api/megafamilia/profile` | ApiController@profile | Sanctum (Bearer) |
| GET | `api/megafamilia/profiles` | ApiController@profiles | Sanctum (Bearer) |
| POST | `api/megafamilia/profiles` | ApiController@storeProfile | Sanctum (Bearer) |
| GET | `api/megafamilia/profiles/{id}` | ApiController@profileDetail | Sanctum (Bearer) |
| GET | `api/megafamilia/profiles/{id}/devices` | ApiController@profileDevices | Sanctum (Bearer) |
| GET | `api/megafamilia/profiles/{id}/geofences` | ApiController@profileGeofences | Sanctum (Bearer) |
| POST | `api/megafamilia/profiles/{id}/geofences` | ApiController@storeProfileGeofence | Sanctum (Bearer) |
| POST | `api/megafamilia/profiles/{id}/invite` | ApiController@inviteDevice | Sanctum (Bearer) |
| GET | `api/megafamilia/profiles/{id}/location` | ApiController@profileLocation | Sanctum (Bearer) |
| GET | `api/megafamilia/profiles/{id}/tasks` | ApiController@profileTasks | Sanctum (Bearer) |
| POST | `api/megafamilia/requests` | ApiController@storeRequest | Sanctum (Bearer) |
| GET | `api/megafamilia/requests/pending` | ApiController@pendingRequests | Sanctum (Bearer) |
| POST | `api/megafamilia/requests/{id}/respond` | ApiController@respondRequest | Sanctum (Bearer) |
| GET | `api/megafamilia/servicio` | ApiController@servicio | Sanctum (Bearer) |
| GET | `api/megafamilia/sync-status` | ApiController@syncStatus | Sanctum (Bearer) |
| POST | `api/megafamilia/tasks/{id}/complete` | ApiController@completeTask | Sanctum (Bearer) |
| GET | `api/megafamilia/tecnico/ordenes` | ApiController@tecnicoOrdenes | Sanctum (Bearer) |
| PUT | `api/megafamilia/tecnico/ordenes/{id}` | ApiController@updateTecnicoOrden | Sanctum (Bearer) |
| GET | `api/megafamilia/tickets` | ApiController@tickets | Sanctum (Bearer) |
| POST | `api/megafamilia/tickets` | ApiController@storeTicket | Sanctum (Bearer) |
| GET | `api/megafamilia/tickets/{id}` | ApiController@ticketDetail | Sanctum (Bearer) |
| POST | `api/megafamilia/tickets/{id}/attachment` | ApiController@attachTicketPhoto | Sanctum (Bearer) |
| POST | `api/megafamilia/tickets/{id}/rate` | ApiController@rateTicket | Sanctum (Bearer) |

## Portal cliente (web) (226 rutas)

| Método | URI | Acción | Autenticación |
|---|---|---|---|
| GET | `/` | DashboardController@index | Sesión guard cliente |
| GET | `apariencia/imagen/{uuid}` | AparienciaImagenController@mostrar | Pública / sin guard |
| GET | `configuracion/portal-cliente` | AparienciaController@index | Pública / sin guard |
| POST | `configuracion/portal-cliente/borrador` | AparienciaController@guardarBorrador | Pública / sin guard |
| DELETE | `configuracion/portal-cliente/borrador` | AparienciaController@descartarBorrador | Pública / sin guard |
| GET | `configuracion/portal-cliente/estado` | AparienciaController@estado | Pública / sin guard |
| POST | `configuracion/portal-cliente/imagenes` | AparienciaController@subirImagen | Pública / sin guard |
| GET | `configuracion/portal-cliente/imagenes/{uuid}` | AparienciaController@imagen | Pública / sin guard |
| POST | `configuracion/portal-cliente/publicar` | AparienciaController@publicar | Pública / sin guard |
| GET | `configuracion/portal-cliente/versiones` | AparienciaController@versiones | Pública / sin guard |
| POST | `configuracion/portal-cliente/versiones/{version}/restaurar` | AparienciaController@restaurar | Pública / sin guard |
| GET | `configuracion/portal-cliente/vista-previa/{pantalla}` | AparienciaController@vistaPrevia | Pública / sin guard |
| GET | `consumo` | ConsumoController@index | Sesión guard cliente |
| GET | `dashboard` | DashboardController@index | Sesión guard cliente |
| GET | `documentos/{id}` | FacturasController@documento | Sesión guard cliente |
| GET | `embajadores` | EmbajadoresController@index | Sesión guard cliente |
| GET | `facturas` | FacturasController@index | Sesión guard cliente |
| GET | `facturas/{id}` | FacturasController@show | Sesión guard cliente |
| GET | `facturas/{id}/cfdi/pdf` | FacturasController@downloadCfdiPdf | Sesión guard cliente |
| GET | `facturas/{id}/cfdi/xml` | FacturasController@downloadCfdiXml | Sesión guard cliente |
| POST | `facturas/{id}/pagar` | PortalPagoController@cobrar | Sesión guard cliente |
| GET | `flotas` | FlotasController@index | Sesión guard cliente |
| GET | `flotas/alertas` | FlotasAlertasController@index | Sesión guard cliente |
| POST | `flotas/alertas/vista` | FlotasAlertasController@marcarVista | Sesión guard cliente |
| POST | `flotas/alertas/{id}/vista` | FlotasAlertasController@marcarVista | Sesión guard cliente |
| GET | `flotas/api/plan` | FlotasPlanController@show | Sesión guard cliente |
| GET | `flotas/api/reglas` | FlotasReglasController@index | Sesión guard cliente |
| POST | `flotas/api/reglas` | FlotasReglasController@store | Sesión guard cliente |
| PUT | `flotas/api/reglas/{id}` | FlotasReglasController@update | Sesión guard cliente |
| DELETE | `flotas/api/reglas/{id}` | FlotasReglasController@destroy | Sesión guard cliente |
| POST | `flotas/api/reglas/{id}/toggle` | FlotasReglasController@toggle | Sesión guard cliente |
| DELETE | `flotas/combustible/{id}` | FlotasGestionController@destroyFuel | Sesión guard cliente |
| GET | `flotas/contactos` | FlotasContactosController@pagina | Sesión guard cliente |
| POST | `flotas/contactos` | FlotasContactosController@store | Sesión guard cliente |
| PUT | `flotas/contactos/{id}` | FlotasContactosController@update | Sesión guard cliente |
| DELETE | `flotas/contactos/{id}` | FlotasContactosController@destroy | Sesión guard cliente |
| DELETE | `flotas/documentos/{id}` | FlotasGestionController@destroyDocument | Sesión guard cliente |
| GET | `flotas/documentos/{id}/archivo` | FlotasGestionController@downloadDocument | Sesión guard cliente |
| GET | `flotas/geocercas` | FlotasPanelController@geocercas | Sesión guard cliente |
| POST | `flotas/geocercas` | FlotasGeocercasController@store | Sesión guard cliente |
| GET | `flotas/geocercas/nueva` | FlotasPanelController@geocercaForm | Sesión guard cliente |
| GET | `flotas/geocercas/{id}` | FlotasPanelController@geocercaShow | Sesión guard cliente |
| PUT | `flotas/geocercas/{id}` | FlotasGeocercasController@update | Sesión guard cliente |
| DELETE | `flotas/geocercas/{id}` | FlotasGeocercasController@destroy | Sesión guard cliente |
| GET | `flotas/geocercas/{id}/editar` | FlotasPanelController@geocercaForm | Sesión guard cliente |
| DELETE | `flotas/mantenimientos/{id}` | FlotasGestionController@destroyMaintenance | Sesión guard cliente |
| GET | `flotas/mapa` | FlotasPanelController@mapa | Sesión guard cliente |
| GET | `flotas/notificaciones` | FlotasController@notificaciones | Sesión guard cliente |
| GET | `flotas/nuevo` | FlotasController@nuevo | Sesión guard cliente |
| POST | `flotas/privacidad` | FlotasController@aceptarPrivacidad | Sesión guard cliente |
| GET | `flotas/recorrido` | FlotasController@recorrido | Sesión guard cliente |
| GET | `flotas/reglas` | FlotasReglasController@pagina | Sesión guard cliente |
| POST | `flotas/reglas` | FlotasReglasController@store | Sesión guard cliente |
| PUT | `flotas/reglas/{id}` | FlotasReglasController@update | Sesión guard cliente |
| DELETE | `flotas/reglas/{id}` | FlotasReglasController@destroy | Sesión guard cliente |
| POST | `flotas/reglas/{id}/toggle` | FlotasReglasController@toggle | Sesión guard cliente |
| GET | `flotas/tracking` | FlotasController@tracking | Sesión guard cliente |
| POST | `flotas/vehiculos` | FlotasGestionController@storeVehicle | Sesión guard cliente |
| GET | `flotas/vehiculos/{id}` | FlotasGestionController@showVehicle | Sesión guard cliente |
| PUT | `flotas/vehiculos/{id}` | FlotasGestionController@updateVehicle | Sesión guard cliente |
| DELETE | `flotas/vehiculos/{id}` | FlotasGestionController@destroyVehicle | Sesión guard cliente |
| POST | `flotas/vehiculos/{id}/combustible` | FlotasGestionController@storeFuel | Sesión guard cliente |
| POST | `flotas/vehiculos/{id}/documentos` | FlotasGestionController@storeDocument | Sesión guard cliente |
| POST | `flotas/vehiculos/{id}/gps` | FlotasGestionController@storeGps | Sesión guard cliente |
| DELETE | `flotas/vehiculos/{id}/gps` | FlotasGestionController@destroyGps | Sesión guard cliente |
| POST | `flotas/vehiculos/{id}/mantenimientos` | FlotasGestionController@storeMaintenance | Sesión guard cliente |
| GET | `flotas/{id}` | FlotasController@ficha | Sesión guard cliente |
| GET | `hijo-megafamilia/{profile_id}` | HijoMegaFamiliaController@index | Sesión guard cliente |
| GET | `login` | AuthController@showLogin | Pública / sin guard |
| POST | `login` | AuthController@login | Pública / sin guard |
| POST | `logout` | AuthController@logout | Sesión guard cliente |
| GET | `megafamilia` | MegaFamiliaController@index | Sesión guard cliente |
| POST | `megafamilia/asignaciones/{id}/completar` | MegaFamiliaAsignacionesController@completar | Sesión guard cliente |
| POST | `megafamilia/dispositivos` | MegaFamiliaDispositivosController@store | Sesión guard cliente |
| PUT | `megafamilia/dispositivos/{id}` | MegaFamiliaDispositivosController@update | Sesión guard cliente |
| DELETE | `megafamilia/dispositivos/{id}` | MegaFamiliaDispositivosController@destroy | Sesión guard cliente |
| GET | `megafamilia/dispositivos/{id}/edit` | MegaFamiliaDispositivosController@edit | Sesión guard cliente |
| POST | `megafamilia/geocercas` | MegaFamiliaGeofencesController@store | Sesión guard cliente |
| PUT | `megafamilia/geocercas/{id}` | MegaFamiliaGeofencesController@update | Sesión guard cliente |
| DELETE | `megafamilia/geocercas/{id}` | MegaFamiliaGeofencesController@destroy | Sesión guard cliente |
| GET | `megafamilia/geocercas/{id}/edit` | MegaFamiliaGeofencesController@edit | Sesión guard cliente |
| POST | `megafamilia/perfiles` | MegaFamiliaPerfilesController@store | Sesión guard cliente |
| PUT | `megafamilia/perfiles/{id}` | MegaFamiliaPerfilesController@update | Sesión guard cliente |
| DELETE | `megafamilia/perfiles/{id}` | MegaFamiliaPerfilesController@destroy | Sesión guard cliente |
| GET | `megafamilia/perfiles/{id}/edit` | MegaFamiliaPerfilesController@edit | Sesión guard cliente |
| POST | `megafamilia/perfiles/{profile}/app-blocks` | MegaFamiliaBloqueosController@storeApp | Sesión guard cliente |
| DELETE | `megafamilia/perfiles/{profile}/app-blocks/{id}` | MegaFamiliaBloqueosController@destroyApp | Sesión guard cliente |
| POST | `megafamilia/perfiles/{profile}/horarios` | MegaFamiliaHorariosController@store | Sesión guard cliente |
| PUT | `megafamilia/perfiles/{profile}/horarios/{id}` | MegaFamiliaHorariosController@update | Sesión guard cliente |
| DELETE | `megafamilia/perfiles/{profile}/horarios/{id}` | MegaFamiliaHorariosController@destroy | Sesión guard cliente |
| GET | `megafamilia/perfiles/{profile}/horarios/{id}/edit` | MegaFamiliaHorariosController@edit | Sesión guard cliente |
| POST | `megafamilia/perfiles/{profile}/recompensas` | MegaFamiliaGamificacionController@storeRecompensa | Sesión guard cliente |
| DELETE | `megafamilia/perfiles/{profile}/recompensas/{id}` | MegaFamiliaGamificacionController@destroyRecompensa | Sesión guard cliente |
| POST | `megafamilia/perfiles/{profile}/web-blocks` | MegaFamiliaBloqueosController@storeWeb | Sesión guard cliente |
| DELETE | `megafamilia/perfiles/{profile}/web-blocks/{id}` | MegaFamiliaBloqueosController@destroyWeb | Sesión guard cliente |
| POST | `megafamilia/recompensas/{reward_id}/canjear` | MegaFamiliaGamificacionController@canjearRecompensa | Sesión guard cliente |
| GET | `megafamilia/solicitudes` | MegaFamiliaSolicitudesController@index | Sesión guard cliente |
| POST | `megafamilia/solicitudes/{id}/aprobar` | MegaFamiliaSolicitudesController@aprobar | Sesión guard cliente |
| POST | `megafamilia/solicitudes/{id}/rechazar` | MegaFamiliaSolicitudesController@rechazar | Sesión guard cliente |
| POST | `megafamilia/tareas` | MegaFamiliaAsignacionTareasController@store | Sesión guard cliente |
| DELETE | `megafamilia/tareas/{id}` | MegaFamiliaAsignacionTareasController@destroy | Sesión guard cliente |
| GET | `mi-plan` | PlanController@index | Sesión guard cliente |
| POST | `openpay/webhook` | OpenpayWebhookController@handle | Pública / sin guard |
| GET | `pagos` | PagosController@index | Sesión guard cliente |
| GET | `perfil` | PerfilController@index | Sesión guard cliente |
| POST | `perfil/cambiar-password` | PerfilController@cambiarPassword | Sesión guard cliente |
| GET | `portal` | DashboardController@index | Sesión guard cliente |
| GET | `portal/apariencia/imagen/{uuid}` | AparienciaImagenController@mostrar | Pública / sin guard |
| GET | `portal/consumo` | ConsumoController@index | Sesión guard cliente |
| GET | `portal/dashboard` | DashboardController@index | Sesión guard cliente |
| GET | `portal/documentos/{id}` | FacturasController@documento | Sesión guard cliente |
| GET | `portal/embajadores` | EmbajadoresController@index | Sesión guard cliente |
| GET | `portal/facturas` | FacturasController@index | Sesión guard cliente |
| GET | `portal/facturas/{id}` | FacturasController@show | Sesión guard cliente |
| GET | `portal/facturas/{id}/cfdi/pdf` | FacturasController@downloadCfdiPdf | Sesión guard cliente |
| GET | `portal/facturas/{id}/cfdi/xml` | FacturasController@downloadCfdiXml | Sesión guard cliente |
| POST | `portal/facturas/{id}/pagar` | PortalPagoController@cobrar | Sesión guard cliente |
| GET | `portal/flotas` | FlotasController@index | Sesión guard cliente |
| GET | `portal/flotas/alertas` | FlotasAlertasController@index | Sesión guard cliente |
| POST | `portal/flotas/alertas/vista` | FlotasAlertasController@marcarVista | Sesión guard cliente |
| POST | `portal/flotas/alertas/{id}/vista` | FlotasAlertasController@marcarVista | Sesión guard cliente |
| GET | `portal/flotas/api/plan` | FlotasPlanController@show | Sesión guard cliente |
| GET | `portal/flotas/api/reglas` | FlotasReglasController@index | Sesión guard cliente |
| POST | `portal/flotas/api/reglas` | FlotasReglasController@store | Sesión guard cliente |
| PUT | `portal/flotas/api/reglas/{id}` | FlotasReglasController@update | Sesión guard cliente |
| DELETE | `portal/flotas/api/reglas/{id}` | FlotasReglasController@destroy | Sesión guard cliente |
| POST | `portal/flotas/api/reglas/{id}/toggle` | FlotasReglasController@toggle | Sesión guard cliente |
| DELETE | `portal/flotas/combustible/{id}` | FlotasGestionController@destroyFuel | Sesión guard cliente |
| GET | `portal/flotas/contactos` | FlotasContactosController@pagina | Sesión guard cliente |
| POST | `portal/flotas/contactos` | FlotasContactosController@store | Sesión guard cliente |
| PUT | `portal/flotas/contactos/{id}` | FlotasContactosController@update | Sesión guard cliente |
| DELETE | `portal/flotas/contactos/{id}` | FlotasContactosController@destroy | Sesión guard cliente |
| DELETE | `portal/flotas/documentos/{id}` | FlotasGestionController@destroyDocument | Sesión guard cliente |
| GET | `portal/flotas/documentos/{id}/archivo` | FlotasGestionController@downloadDocument | Sesión guard cliente |
| GET | `portal/flotas/geocercas` | FlotasPanelController@geocercas | Sesión guard cliente |
| POST | `portal/flotas/geocercas` | FlotasGeocercasController@store | Sesión guard cliente |
| GET | `portal/flotas/geocercas/nueva` | FlotasPanelController@geocercaForm | Sesión guard cliente |
| GET | `portal/flotas/geocercas/{id}` | FlotasPanelController@geocercaShow | Sesión guard cliente |
| PUT | `portal/flotas/geocercas/{id}` | FlotasGeocercasController@update | Sesión guard cliente |
| DELETE | `portal/flotas/geocercas/{id}` | FlotasGeocercasController@destroy | Sesión guard cliente |
| GET | `portal/flotas/geocercas/{id}/editar` | FlotasPanelController@geocercaForm | Sesión guard cliente |
| DELETE | `portal/flotas/mantenimientos/{id}` | FlotasGestionController@destroyMaintenance | Sesión guard cliente |
| GET | `portal/flotas/mapa` | FlotasPanelController@mapa | Sesión guard cliente |
| GET | `portal/flotas/notificaciones` | FlotasController@notificaciones | Sesión guard cliente |
| GET | `portal/flotas/nuevo` | FlotasController@nuevo | Sesión guard cliente |
| POST | `portal/flotas/privacidad` | FlotasController@aceptarPrivacidad | Sesión guard cliente |
| GET | `portal/flotas/recorrido` | FlotasController@recorrido | Sesión guard cliente |
| GET | `portal/flotas/reglas` | FlotasReglasController@pagina | Sesión guard cliente |
| POST | `portal/flotas/reglas` | FlotasReglasController@store | Sesión guard cliente |
| PUT | `portal/flotas/reglas/{id}` | FlotasReglasController@update | Sesión guard cliente |
| DELETE | `portal/flotas/reglas/{id}` | FlotasReglasController@destroy | Sesión guard cliente |
| POST | `portal/flotas/reglas/{id}/toggle` | FlotasReglasController@toggle | Sesión guard cliente |
| GET | `portal/flotas/tracking` | FlotasController@tracking | Sesión guard cliente |
| POST | `portal/flotas/vehiculos` | FlotasGestionController@storeVehicle | Sesión guard cliente |
| GET | `portal/flotas/vehiculos/{id}` | FlotasGestionController@showVehicle | Sesión guard cliente |
| PUT | `portal/flotas/vehiculos/{id}` | FlotasGestionController@updateVehicle | Sesión guard cliente |
| DELETE | `portal/flotas/vehiculos/{id}` | FlotasGestionController@destroyVehicle | Sesión guard cliente |
| POST | `portal/flotas/vehiculos/{id}/combustible` | FlotasGestionController@storeFuel | Sesión guard cliente |
| POST | `portal/flotas/vehiculos/{id}/documentos` | FlotasGestionController@storeDocument | Sesión guard cliente |
| POST | `portal/flotas/vehiculos/{id}/gps` | FlotasGestionController@storeGps | Sesión guard cliente |
| DELETE | `portal/flotas/vehiculos/{id}/gps` | FlotasGestionController@destroyGps | Sesión guard cliente |
| POST | `portal/flotas/vehiculos/{id}/mantenimientos` | FlotasGestionController@storeMaintenance | Sesión guard cliente |
| GET | `portal/flotas/{id}` | FlotasController@ficha | Sesión guard cliente |
| GET | `portal/hijo-megafamilia/{profile_id}` | HijoMegaFamiliaController@index | Sesión guard cliente |
| GET | `portal/login` | AuthController@showLogin | Pública / sin guard |
| POST | `portal/login` | AuthController@login | Pública / sin guard |
| POST | `portal/logout` | AuthController@logout | Sesión guard cliente |
| GET | `portal/megafamilia` | MegaFamiliaController@index | Sesión guard cliente |
| POST | `portal/megafamilia/asignaciones/{id}/completar` | MegaFamiliaAsignacionesController@completar | Sesión guard cliente |
| POST | `portal/megafamilia/dispositivos` | MegaFamiliaDispositivosController@store | Sesión guard cliente |
| PUT | `portal/megafamilia/dispositivos/{id}` | MegaFamiliaDispositivosController@update | Sesión guard cliente |
| DELETE | `portal/megafamilia/dispositivos/{id}` | MegaFamiliaDispositivosController@destroy | Sesión guard cliente |
| GET | `portal/megafamilia/dispositivos/{id}/edit` | MegaFamiliaDispositivosController@edit | Sesión guard cliente |
| POST | `portal/megafamilia/geocercas` | MegaFamiliaGeofencesController@store | Sesión guard cliente |
| PUT | `portal/megafamilia/geocercas/{id}` | MegaFamiliaGeofencesController@update | Sesión guard cliente |
| DELETE | `portal/megafamilia/geocercas/{id}` | MegaFamiliaGeofencesController@destroy | Sesión guard cliente |
| GET | `portal/megafamilia/geocercas/{id}/edit` | MegaFamiliaGeofencesController@edit | Sesión guard cliente |
| POST | `portal/megafamilia/perfiles` | MegaFamiliaPerfilesController@store | Sesión guard cliente |
| PUT | `portal/megafamilia/perfiles/{id}` | MegaFamiliaPerfilesController@update | Sesión guard cliente |
| DELETE | `portal/megafamilia/perfiles/{id}` | MegaFamiliaPerfilesController@destroy | Sesión guard cliente |
| GET | `portal/megafamilia/perfiles/{id}/edit` | MegaFamiliaPerfilesController@edit | Sesión guard cliente |
| POST | `portal/megafamilia/perfiles/{profile}/app-blocks` | MegaFamiliaBloqueosController@storeApp | Sesión guard cliente |
| DELETE | `portal/megafamilia/perfiles/{profile}/app-blocks/{id}` | MegaFamiliaBloqueosController@destroyApp | Sesión guard cliente |
| POST | `portal/megafamilia/perfiles/{profile}/horarios` | MegaFamiliaHorariosController@store | Sesión guard cliente |
| PUT | `portal/megafamilia/perfiles/{profile}/horarios/{id}` | MegaFamiliaHorariosController@update | Sesión guard cliente |
| DELETE | `portal/megafamilia/perfiles/{profile}/horarios/{id}` | MegaFamiliaHorariosController@destroy | Sesión guard cliente |
| GET | `portal/megafamilia/perfiles/{profile}/horarios/{id}/edit` | MegaFamiliaHorariosController@edit | Sesión guard cliente |
| POST | `portal/megafamilia/perfiles/{profile}/recompensas` | MegaFamiliaGamificacionController@storeRecompensa | Sesión guard cliente |
| DELETE | `portal/megafamilia/perfiles/{profile}/recompensas/{id}` | MegaFamiliaGamificacionController@destroyRecompensa | Sesión guard cliente |
| POST | `portal/megafamilia/perfiles/{profile}/web-blocks` | MegaFamiliaBloqueosController@storeWeb | Sesión guard cliente |
| DELETE | `portal/megafamilia/perfiles/{profile}/web-blocks/{id}` | MegaFamiliaBloqueosController@destroyWeb | Sesión guard cliente |
| POST | `portal/megafamilia/recompensas/{reward_id}/canjear` | MegaFamiliaGamificacionController@canjearRecompensa | Sesión guard cliente |
| GET | `portal/megafamilia/solicitudes` | MegaFamiliaSolicitudesController@index | Sesión guard cliente |
| POST | `portal/megafamilia/solicitudes/{id}/aprobar` | MegaFamiliaSolicitudesController@aprobar | Sesión guard cliente |
| POST | `portal/megafamilia/solicitudes/{id}/rechazar` | MegaFamiliaSolicitudesController@rechazar | Sesión guard cliente |
| POST | `portal/megafamilia/tareas` | MegaFamiliaAsignacionTareasController@store | Sesión guard cliente |
| DELETE | `portal/megafamilia/tareas/{id}` | MegaFamiliaAsignacionTareasController@destroy | Sesión guard cliente |
| GET | `portal/mi-plan` | PlanController@index | Sesión guard cliente |
| POST | `portal/openpay/webhook` | OpenpayWebhookController@handle | Pública / sin guard |
| GET | `portal/pagos` | PagosController@index | Sesión guard cliente |
| GET | `portal/perfil` | PerfilController@index | Sesión guard cliente |
| POST | `portal/perfil/cambiar-password` | PerfilController@cambiarPassword | Sesión guard cliente |
| GET | `portal/recuperar` | AuthController@showRecuperar | Pública / sin guard |
| POST | `portal/recuperar` | AuthController@recuperar | Pública / sin guard |
| GET | `portal/registro` | AuthController@showRegistro | Pública / sin guard |
| POST | `portal/registro` | AuthController@registro | Pública / sin guard |
| GET | `portal/servicios` | MarketplaceController@index | Sesión guard cliente |
| POST | `portal/servicios/interes` | MarketplaceController@registrarInteres | Sesión guard cliente |
| POST | `portal/servicios/megafamilia/activar` | MarketplaceController@activarMegafamilia | Sesión guard cliente |
| POST | `portal/servicios/megafamilia/desactivar` | MarketplaceController@desactivarMegafamilia | Sesión guard cliente |
| GET | `portal/tickets` | TicketsController@index | Sesión guard cliente |
| POST | `portal/tickets` | TicketsController@store | Sesión guard cliente |
| GET | `portal/tickets/{id}` | TicketsController@show | Sesión guard cliente |
| POST | `portal/tickets/{id}/reply` | TicketsController@responder | Sesión guard cliente |
| GET | `recuperar` | AuthController@showRecuperar | Pública / sin guard |
| POST | `recuperar` | AuthController@recuperar | Pública / sin guard |
| GET | `registro` | AuthController@showRegistro | Pública / sin guard |
| POST | `registro` | AuthController@registro | Pública / sin guard |
| GET | `servicios` | MarketplaceController@index | Sesión guard cliente |
| POST | `servicios/interes` | MarketplaceController@registrarInteres | Sesión guard cliente |
| POST | `servicios/megafamilia/activar` | MarketplaceController@activarMegafamilia | Sesión guard cliente |
| POST | `servicios/megafamilia/desactivar` | MarketplaceController@desactivarMegafamilia | Sesión guard cliente |
| GET | `tickets` | TicketsController@index | Sesión guard cliente |
| POST | `tickets` | TicketsController@store | Sesión guard cliente |
| GET | `tickets/{id}` | TicketsController@show | Sesión guard cliente |
| POST | `tickets/{id}/reply` | TicketsController@responder | Sesión guard cliente |

# UNI-06 · Documento 6 del plan maestro: roadmap de implementación por fases

Item #690 · épica UNI · 2026-10-24 · **solo documental** (sin cambios de código, migraciones, permisos ni config).
Cierra el plan maestro. Se apoya en los documentos 01 a 05 y **no repite su detalle**: enlaza a donde está la evidencia.
Contrato: `plan-maestro-unificacion.md` (adjunto de la épica). Conteos = BD de dev; producción no se consultó.
Sin nombres, contactos ni montos por persona.

> **Actualizado tras #691–#694 y #697–#699 (2026-10-26):** se quitan como pendientes de las fases iniciales: modelo de fecha oficial de órdenes (#691), acceso horizontal a tareas (#692/#698), vigencia de token y límite de intentos (#693), 500 de notificaciones (#694) y sus pruebas (#697–#699). Las dependencias que apuntaban a esos items se consideran cumplidas; queda pendiente la conciliación de fechas históricas (requiere decisión de Irving) y el resto de P6 de 01e.

| Doc | Archivo | Aporta a este roadmap |
|---|---|---|
| 01 | [`01-estado-actual.md`](01-estado-actual.md) (+ 01a–01e) | Qué existe, qué falta, qué está roto |
| 02 | [`02-arquitectura.md`](02-arquitectura.md) | Servicios propuestos y dueño de cada dato |
| 03 | [`03-mapa-funcionalidades.md`](03-mapa-funcionalidades.md) | Qué se conserva, adapta, fusiona o retira |
| 04 | [`04-experiencia-usuario.md`](04-experiencia-usuario.md) | Pantallas por perfil y Mi Espacio |
| 05 | [`05-plan-migracion.md`](05-plan-migracion.md) | Fases A–G de integración sin pérdida y comandos D1–D10 |

---

## 1. Principios del roadmap

1. **Nada se implementa hasta que Irving apruebe el plan.** Este documento propone; no crea items ni toca código.
2. **Se parte de lo existente.** Mi Espacio se construye sobre la ficha propia de Talento; no hay login, portal ni tabla central de copias (04 §1; 02 §1).
3. **Todo aditivo y reversible.** Columnas nuevas nulas, tablas auxiliares, vistas; ningún `DROP`, ningún borrado de históricos (05 §1, §9).
4. **Dev primero, producción aparte.** Cada fase cierra y concilia en dev; producción es otra decisión, con bitácora previa (regla permanente de `CLAUDE.md`) y respaldo.
5. **Dinero y permisos son frontera dura.** Las fases marcadas ⚠ (5, 8 y partes de 0, 2 y 7) exigen la confirmación de Irving; el circuito no las decide solo.
6. **Una fase = un conjunto de items chicos**, cada uno con rama propia, verificable y reversible por separado.

### Correcciones en curso aparte (no son trabajo nuevo de este roadmap)

Autorizadas por Irving el 2026-10-24 y ya en la Hoja de ruta. Se citan porque algunas fases dependen de que cierren:

| Item | Corrección | Fase que toca |
|---|---|---|
| #691 | Fechas de órdenes entre calendario y «Mi día» | Fase 0 (fechas) y Fase 3 (agenda) |
| #692 | Tareas u órdenes de otra persona visibles/editables | Fase 0 (acceso horizontal) |
| #693 | Autenticación de la app de Talento (tokens) | Fase 0b (API móvil) |
| #694 | Notificación que devuelve 500 | Fase 6 (notificaciones) |
| #695 (UNI-07) | Actualiza los documentos 01–06 con el resultado de las correcciones | Cierre del plan |

---

## 2. Resumen de fases

| # | Fase | Riesgo | Depende de | Dinero / permisos |
|---|---|---|---|---|
| 0 | Cerrar huecos de acceso, fechas de `tasks`, endurecer API móvil | B ⚠ | Aprobación del plan; #691–#693 | Permisos y auth |
| 1 | Catálogos: departamentos, capacidades, cuadrillas, supervisores | B | 0 | Permisos (capacidad «puede vender») |
| 2 | Identidad de ventas: doble escritura, backfill, atribución versionada | B ⚠ | 1 | Dato comercial |
| 3 | Agenda unificada (índice de lectura + tablas auxiliares) | A–B | 0 (fechas) | No |
| 4 | Orden rápida desde mostrador, plantillas y seguimiento | B | 3 | Gate de evidencias |
| 5 | Libro de incentivos, políticas de comisión y cierre semanal | C ⚠ | 2, 4, decisión de pagado | **Dinero** |
| 6 | Notificaciones internas por evento | A–B | 4 | No |
| 7 | Garantía y calidad a 6 meses | B ⚠ | 4 (y 5 para el efecto económico) | Quita lógica de «no pagable» |
| 8 | Mi Espacio (pantallas) y sustitución visual | B | 3, 4 y lo que cada bloque muestre | Permisos económicos |
| 9 | Retiro controlado | C | 8 + consumidores medidos | Aprobación explícita |

La numeración es de dependencia, no de calendario. **Mi Espacio se construye por bloques a medida que cada fase le entrega datos** (sección 4); la Fase 8 es el cierre visual y el cambio de accesos, no el inicio de las pantallas.

---

## 3. Detalle por fase

Cada fase: objetivo, componentes, archivos estimados, migraciones (aditivas), cruces con otras fases, riesgos, pruebas (de la sección 18 del contrato), criterios de aceptación y condición para empezar la siguiente. Los archivos son **estimados** desde la auditoría; el ejecutor confirma con `grep` antes de editar.

### Fase 0 · Cerrar huecos de acceso, fechas de `tasks` y API móvil

**Objetivo.** Que Mi Espacio nazca sin fugas ni fechas contradictorias (01-estado §1 puntos 2 y 7; 01e §4.2).

**Componentes.**
- 0a. **Acceso horizontal**: comprobar pertenencia (propio / equipo / permiso de supervisión) en calendario y tareas por id de Scheduling, cortes de caja, ficha básica y media de OT. Patrón único `Actor::puedeVer(recurso)` (02 §5.2). Empieza por la prueba que demuestra el hueco.
- 0b. **API móvil**: vigencia de tokens con renovación, `throttle` en login, validar `users.estado = activo`, revocar al cambiar contraseña o dar de baja (02 P8; 01e §7.1). Ver #693.
- 0c. **Fechas de `tasks`**: columnas `datetime` nuevas derivadas de `start_time`/`end_time`/`start_date` (varchar), sin tocar las originales (05 D3-A). Ver #691.
- 0d. **Cierre de admin que evade evidencias**: pasa por el mismo servicio de cierre con permiso explícito y motivo (01d P3); se **prepara** aquí, se completa en la Fase 4.

**Archivos estimados.** Controladores de Scheduling (calendario/tareas), controlador de cortes de caja, `Support/Actor.php` de Talento, controlador de media de OT, rutas y `config/route_permission.php`, `config/sanctum.php`, controlador de login móvil de Talento, migración nueva sobre `tasks`, comando `agenda:normalizar-fechas`.

**Migraciones.** 1 aditiva: columnas `inicio_dt`/`fin_dt` (nulas) en `tasks` o tabla `tasks_fechas_normalizadas`. Reversa: `dropColumn` de las nuevas.

**Cruces.** Todas las demás dependen de 0a. 0c alimenta la Fase 3. 0b condiciona cualquier app futura.

**Riesgos.** `tasks` también alimenta el pago, Tickets, WarRoom y MegaFamilia (02 P1): ensayar la migración en una copia, comparar conteos y `countBillableUnits` antes y después. 0b obliga a re-iniciar sesión en la app actual: comunicar fecha de corte. Controladores con consumidores: grep de consumidores antes de endurecer.

**Pruebas (contrato §18).** Acceso indebido a órdenes ajenas; información privada de empleado; permisos de mostrador; permisos de técnico; cambios de fecha desde el módulo original; compatibilidad con rutas anteriores; regresión de funcionalidades existentes (incl. `countBillableUnits` sin cambio de resultado).

**Aceptación.** Una persona sin permiso recibe 403/404 al pedir por id un registro ajeno en las 4 superficies; 0 tokens sin `expires_at` nuevos; `agenda:normalizar-fechas --dry-run` reporta las filas no interpretables y las explica; conteos de `tasks` idénticos antes y después.

**Para empezar la siguiente.** Aprobación de Irving sobre 0a–0c (Q4, Q6, Q19 de 01-estado) y #691/#692/#693 cerrados.

### Fase 1 · Catálogos de organización

**Objetivo.** Departamentos, capacidades y cuadrillas como dato propio, separados del rol de seguridad (02 §3.2; contrato §1).

**Componentes.** Tablas `talento_departamentos`, `talento_capacidades` (+ pivote colaborador) y cuadrillas; captura de `supervisor_id` (38 de 39 vacíos); capacidad «puede vender» (D4) y `talento:sembrar-departamentos` (D5, que propone desde 9 textos libres, varios de prueba). Pantalla de captura en la ficha administrativa de Talento.

**Archivos estimados.** 3–4 migraciones nuevas, modelos en `app/Modules/Addons/Talento/Models/`, 2 comandos de siembra, controlador y pestaña Vue de «Estructura» en la ficha, `module.json` (permisos).

**Migraciones.** Tablas nuevas y pivote; ninguna columna existente cambia (`role_departments` y niveles/puestos residuales quedan hasta la Fase 9).

**Cruces.** Fase 2 usa «puede vender»; Fase 3 usa departamento/equipo para las vistas; Fase 5 usa departamento como beneficiario de reglas.

**Riesgos.** Dato que solo Irving/RR. HH. pueden capturar; valores de prueba en los textos libres; la capacidad no debe conceder permisos por sí sola (solo habilita ofrecer la función).

**Pruebas.** Usuario con varios roles operativos; permisos de mostrador/técnico (la capacidad no abre rutas); información privada de empleado.

**Aceptación.** Catálogos creados sin duplicar; cada colaborador activo con departamento y supervisor o marcado «sin supervisor» explícito; ningún permiso cambia por crear una capacidad.

**Para empezar la siguiente.** Catálogos aprobados y captura de supervisores iniciada (no necesariamente terminada).

### Fase 2 · Identidad de ventas y atribución

**Objetivo.** Que cada venta quede ligada a un colaborador real, sin sobrescribir historia (01c §1.3; 02 §4.3).

**Componentes.**
- 2a. Encender `IDENTIDAD_DOBLE_ESCRITURA_COLABORADOR` en dev y correr el backfill (D1) con `--dry-run` primero; conciliar con `identidad:verificar-consistencia` (los comandos y el flag ya existen; hoy `colaborador_id` está en 0 de 4,439 clientes con vendedor).
- 2b. Resolver las 8–9 cuentas dueñas de ventas sin colaborador (expediente ligero o «cuenta de sistema» excluida) con la lista entregada solo por conteos.
- 2c. Tabla `ventas_atribuciones` (única por venta activa; reasignar cierra y crea, nunca sobrescribe) y registro de venta por cualquier colaborador con capacidad.
- 2d. Marcar `commissions_details` (566,780 filas huérfanas) como legado: **no se borra** (Q17).

**Archivos estimados.** Migración de `ventas_atribuciones`; `AtribucionVentaService`; ajustes en los puntos de escritura de `seller_id` (Eloquent y `DB::table` en importaciones, ya mapeados en #9990962); comando de conciliación; vista «Mis ventas» (solo lectura, bloque de Mi Espacio).

**Migraciones.** Tabla nueva; `colaborador_id` ya existe (nula). Reversa: apagar flag y vaciar la tabla nueva (no toca `seller_id`).

**Cruces.** Fase 1 (capacidad). Fase 5 consume la atribución como fuente de elegibilidad. Fase 8 muestra Mis Ventas.

**Riesgos.** Dato comercial histórico: el backfill solo llena donde es nulo (confirmar al leer su código; 05 §12). Cuentas sin colaborador pueden reflejar exempleados. Embajador y vendedor sobre el mismo cliente: decisión pendiente (Q15).

**Pruebas.** Atribución de venta a un técnico; venta de empleado no comercial; venta cancelada (no elegible); comisiones históricas pagadas intactas; conservación de registros migrados; prevención de atribución duplicada.

**Aceptación.** Conciliación de `verificar-consistencia` en 0 discrepancias; conteos de `client_main_information` y de ventas idénticos; ninguna fila de `commissions_details` modificada; dos atribuciones activas para la misma venta es imposible por restricción única.

**Para empezar la siguiente.** Conciliación en cero durante un período de pago (05 P4: una semana real con doble escritura).

### Fase 3 · Agenda unificada

**Objetivo.** Un solo lugar donde ver lo que cada persona debe hacer hoy, sin copiar registros (02 §4.1).

**Componentes.** `AgendaService` con contrato de adaptador `{fuente, id_origen, titulo, inicio, fin, responsables[], estado_origen, estado_unificado, enlace}`; adaptadores para `tasks`, OT histórica de Talento y las 67 instalaciones agendadas del CRM; llave `fuente + id_origen` (sin duplicados: una orden ligada a ticket e instalación aparece una vez); tablas auxiliares `agenda_recordatorios`, `agenda_reprogramaciones`, `agenda_participantes`, `orden_ticket_relacion` (D2); detección de choques (avisar y permitir con motivo, parametrizable); vistas individual, equipo y departamento como filtros del mismo servicio.

**Archivos estimados.** `app/Modules/Addons/Talento/Services/Agenda/*` (servicio + 3 adaptadores), 4 migraciones de tablas auxiliares, 1 comando de siembra, endpoints de lectura, componentes Vue `MiAgenda` (bloque de Mi Espacio) y vista de equipo.

**Migraciones.** 4 tablas nuevas. Reversa: eliminarlas (no hay datos de origen en ellas).

**Cruces.** Depende de Fase 0 (fechas y acceso). Fase 4 crea órdenes que la agenda lee. Fase 6 usa recordatorios.

**Riesgos.** Rendimiento: cada adaptador necesita índice por ventana de fechas. Reprogramar debe llamar al servicio de Scheduling, no a `DB::table()->update` (evita observers: 01d §4). Dos orígenes en lectura mientras exista la OT histórica (Q7: histórico de solo lectura).

**Pruebas.** Calendario consolidado sin duplicados; cambios de fecha desde el módulo original se reflejan; reprogramación con motivo; usuario con varios roles; acceso indebido a la agenda de otro; vistas de equipo según alcance.

**Aceptación.** El conteo de elementos de la agenda de una ventana = suma de las fuentes menos los duplicados explicados; editar una fecha en el módulo de origen cambia la agenda sin proceso de sincronización; choque detectado con participantes múltiples.

**Para empezar la siguiente.** Adaptadores probados contra datos reales de dev.

### Fase 4 · Orden rápida, plantillas y seguimiento

**Objetivo.** Generar una orden en pocos clics desde la ficha del cliente y seguirla con los estados del contrato sin sobrescribir los de cada módulo (02 §4.2; 04 §3–4).

**Componentes.** `OrdenService::crear()` que delega en el `store()` actual de Talento/`OrdenTrabajoUnifiedService`; `orden_plantillas` (D10: 13 solicitudes del contrato, mapeadas a los 5 tipos existentes, las nuevas heredando la matriz de evidencias del tipo más parecido); `ticket_id` obligatorio cuando nace de un ticket «Problema»; `orden_estado_historial` (aceptada, en camino, pausada, observada); **gate de evidencias único** con la vía del admin pasando por él; reparto de crédito por `quantity_share` con varios técnicos; pantalla «Seguimiento» para mostrador y supervisor; botón «Crear orden» en la ficha del cliente.

**Archivos estimados.** `OrdenService`, `OrdenPlantilla` (modelo+migración), `orden_estado_historial`, ajustes en `TalentoWorkOrderController`, servicio de cierre, componentes Vue de ficha de cliente y Seguimiento, `config/route_permission.php`.

**Migraciones.** 2 tablas nuevas; sin cambios a `talento_work_orders` ni `tasks`.

**Cruces.** Fase 3 (agenda y choques). Fase 5 recibe el evento «orden validada» como candidato. Fase 7 recibe la fecha de aceptación técnica.

**Riesgos.** El cierre toca el conteo de pago: regresión de `countBillableUnits` y liquidación sin cambio de resultado. Fricción por el gate de evidencias en el cierre del admin. Cuatro objetos sin llave común (01-estado §1 punto 1): la relación se siembra, no se asume.

**Pruebas.** Creación rápida de orden; asignación a técnico; reprogramación; evidencias; cierre técnico; validación supervisada; instalaciones con varios técnicos; no autoaprobación; regresión de funcionalidades existentes.

**Aceptación.** Crear orden desde mostrador en ≤ 8 clics (04 §3.4); no se puede cerrar una orden con evidencias obligatorias faltantes por ninguna ruta salvo permiso explícito con motivo registrado; ningún estado de origen se sobrescribe; conteo de unidades facturables idéntico.

**Para empezar la siguiente.** Gate de evidencias único en servicio y probado.

### Fase 5 · Libro de incentivos, políticas de comisión y cierre semanal ⚠ (dinero)

**Objetivo.** Calcular, autorizar y preparar el pago sin que terminar una actividad pague directamente (contrato §3, §10, §11; 02 §4.4–4.5).

**Componentes.**
- 5a. `talento_incentivo_movimientos` (append-only, `evento_uid` único, estados `estimado → pendiente_revision → autorizado → aplicado_prenomina → pagado`, ramas `rechazado`/`reversado`) e `IncentivoLedgerService`. Los generadores actuales (`LiquidationService`, `HealthBonusService`, `ProjectBonusService`, `PenaltyService`) escriben el libro nuevo **en paralelo** al ledger vigente, que no se altera.
- 5b. Políticas de comisión versionadas (modelos A–D) por persona/puesto/departamento/campaña/producto/vigencia; metas como entidad propia versionada; sembrado D6 desde `commissions_rules` (8). **Sin política no hay comisión.**
- 5c. `talento_cierres` (`abierto → simulado → en_revision → observado → aprobado → bloqueado`), reapertura con permiso, motivo y auditoría; exportación para pre-nómina; sueldos pedidos a la fuente de nómina, no almacenados.
- 5d. Estado «pagado» por confirmación manual con referencia externa (Q16/P5).

**Archivos estimados.** 4–5 migraciones, `IncentivoLedgerService`, `CierreSemanalService`, adaptadores de los 4 generadores, comandos `comisiones:sembrar-politicas` (D6) e `incentivos:sembrar-libro` (D7, **opcional**), controladores y pantallas «Cierre semanal» (Administración) y «Mis incentivos», permisos `incentivos.ver-propios`, `incentivos.ver-equipo`, `cierre.ver`, `cierre.aprobar`.

**Migraciones.** Tablas nuevas; el ledger vigente y `commissions*` no se tocan.

**Cruces.** Consume la atribución (2) y el evento de orden validada (4). Fase 7 puede producir eventos de revisión (nunca descuentos automáticos). Fase 8 muestra Mis Incentivos.

**Riesgos.** Es la fase de mayor riesgo: dinero y reglas aún no definidas (#121, compensación de roles no técnicos). Doble lectura mientras convivan ledger y libro (12 movimientos del ledger vigente: copiarlos o no es decisión de Irving, 05 P1). Tocar `LiquidationService`/`PayWeek` exige no alterar sus resultados.

**Pruebas.** Comisión con mínimo semanal; comisión sobre ventas adicionales; venta cancelada; comisiones históricas pagadas; cálculo de bonificación; aprobación de incentivo (distinto del beneficiario); bloqueo de período; prevención de pago duplicado; conservación de registros migrados; información privada de empleado.

**Aceptación.** Reprocesar el mismo evento no inserta una segunda fila; un movimiento `pagado` no puede actualizarse (solo ajuste/reverso que lo referencia); el cierre bloqueado rechaza cambios; ningún cierre genera pago bancario; la suma por período del libro + ledger vigente coincide con la liquidación de `PayWeek`.

**Para empezar la siguiente.** Irving aprueba reglas económicas y la política de «pagado»; sin eso, la fase no avanza (no es una decisión del circuito).

### Fase 6 · Notificaciones internas

**Objetivo.** Los 13 eventos del contrato §16 sobre la infraestructura que ya existe (02 §4.7).

**Componentes.** Un tipo de notificación por evento sobre `notifications` + `StandardNotification` (leído/no leído ya existe); canales como drivers según el patrón del dispatcher de Flotas (base de datos siempre; correo/WhatsApp solo si el usuario lo activa y fuera de pruebas); `task_notifications` como vista de compatibilidad (D9); consolidar almacenes de token en `push_tokens` (sin emisor aún). Corrección de la notificación que devuelve 500 (#694).

**Archivos estimados.** Clases de notificación por evento, servicio `NotificacionService`, vista SQL de compatibilidad, jobs de recordatorio en `Kernel.php`, ajustes en la campana Vue.

**Migraciones.** 1 vista SQL; opcional columna `fuente`/`id_origen` en `notifications.data` (sin esquema).

**Cruces.** Recordatorios de Fase 3; eventos de Fases 4, 5 y 7.

**Riesgos.** Duplicados entre los 4 almacenes actuales; envío real accidental (se mantienen intercepción de correo y `WHATSAPP_SENDER_ENABLED=false`, sin nuevo camino de salida).

**Pruebas.** Ninguna notificación real sale de dev; un evento = una notificación (sin duplicar); leído/no leído; acceso solo a las propias.

**Aceptación.** Los 13 tipos existen y se disparan desde su evento; `task_notifications` sigue legible; 0 correos/WhatsApp reales en pruebas.

**Para empezar la siguiente.** — (no bloquea a otras fases salvo la 8 en su bloque de notificaciones).

### Fase 7 · Garantía y calidad a 6 meses ⚠

**Objetivo.** Cambiar «ventana = culpa del técnico» por «evento + causa + revisión», sin efecto económico automático (contrato §9; 02 §4.6; 01d §7).

**Componentes.** `talento_garantia_eventos` (instalación original, participantes, una de las 8 causas, resultado de la revisión, responsable, evidencias); inicio de los 6 meses = aceptación técnica (`accepted_at`) con respaldo a la fecha de validación; **apagar** la lógica actual de «no pagable / 0 puntos» y de responsabilidad automática (decisión de Irving, Q13); indicadores de calidad por técnico solo como insumo de revisión.

**Archivos estimados.** `WarrantyWindowService` (ajuste), migración de eventos, formulario de revisión y vista de calidad, retiro de la marca automática en el cálculo de puntos (toca `LiquidationService`).

**Migraciones.** Tabla nueva; se conservan `talento_responsibility_windows` y `talento_warranty_overrides` (no se borran).

**Cruces.** Fase 4 (fecha de aceptación). Fase 5: un evento de revisión puede originar un ajuste que un humano autoriza.

**Riesgos.** Cambia un cálculo de pago existente (apagar el 0 de puntos): dinero; ensayo con comparación de liquidaciones antes y después. Datos de `accepted_at` hoy vacíos.

**Pruebas.** Garantía dentro de seis meses; incidencia ajena a la instalación; instalaciones con varios técnicos; ningún descuento automático; regresión de la liquidación.

**Aceptación.** Una incidencia dentro del período crea un evento sin cambiar puntos ni montos; la causa y el responsable de revisión quedan registrados; las liquidaciones previas son idénticas.

### Fase 8 · Mi Espacio y sustitución visual

**Objetivo.** Entregar la experiencia completa por perfil y mover los accesos de forma gradual (04; 05 §7).

**Componentes.** Grupo «Mi Espacio» de 6 bloques en la ficha propia de Talento (Inicio, Mi Agenda, Mi Trabajo, Mis Resultados, Mis Ventas, Mis Incentivos) con permisos jerárquicos `{modulo}_view_block_*` (convención #538); grupo «Administración» por permiso; pantallas de Mostrador, Técnico, Planta Externa, Supervisor y Administración (04 §2); 7 pantallas a 360 px (04 §5.2); permiso base `portal.colaborador` en el **rol**, no por usuario (Q3). La redirección post-login queda **diferida** por perfil (04 P7).

**Archivos estimados.** Componentes Vue de la ficha, `Actor::sections()`, `sidebar` (declarativo), migración aditiva de permisos por rol, ajustes responsivos.

**Migraciones.** 1 migración de permisos (aditiva, por selector y no por id); sin tablas de datos.

**Cruces.** Cada bloque llega cuando su fase lo alimenta (sección 4). Cambiar permisos de rol es frontera dura.

**Riesgos.** Mezclar lo propio con lo administrativo en una misma pantalla (por eso se separa en dos grupos); quitar accesos antiguos sin autorización (prohibido: contrato §13 Fase F).

**Pruebas.** Usuario con varios roles (secciones combinadas, 04 P8); permisos de mostrador/técnico; información privada de empleado; usabilidad a 360 px; compatibilidad con rutas anteriores; regresión de funcionalidades existentes.

**Aceptación.** Cada perfil ve solo lo suyo y lo que su permiso autoriza; ningún bloque económico visible sin permiso propio; los menús antiguos siguen disponibles hasta la aprobación de Irving.

**Para empezar la siguiente.** Aprobación explícita de Irving tras uso real.

### Fase 9 · Retiro controlado

**Objetivo.** Desactivar pantallas redundantes **sin borrar tablas** (05 §8; 03 §4).

**Componentes.** Retiro de accesos del menú de las pantallas marcadas «retirar después» una vez medidos sus consumidores (rutas, jobs, Vue, APK); redirecciones de compatibilidad; retiro de listeners de comisión comentados tras conciliar (Q2 de 03); plan de reversión escrito antes de cada retiro.

**Migraciones.** Ninguna destructiva.

**Riesgos.** Consumidores externos (APK, enlaces guardados). Por eso es la última fase y cada retiro es un item aparte con su reversión.

**Pruebas.** Compatibilidad de rutas anteriores; regresión de funcionalidades existentes; conservación de registros migrados.

**Aceptación.** Medición sin tráfico durante un período acordado; reversión probada; ninguna tabla histórica eliminada.

---

## 4. Qué se puede trabajar en paralelo y qué no

| Frente | En paralelo con | No puede empezar antes de |
|---|---|---|
| 0a Acceso horizontal | 0b API móvil, 0c Fechas (archivos distintos) | — |
| 1 Catálogos | 0b, 0c | 0a aprobado (para no exponer fugas en pantallas nuevas) |
| 2 Identidad de ventas | 3 (agenda), 6 (notificaciones) | 1 («puede vender») y decisión de las cuentas sin colaborador |
| 3 Agenda | 2, 1 (después de 0c) | 0c (fechas) y 0a |
| 4 Orden rápida | 2, 6 | 3 (agenda y choques) |
| 5 Libro y cierre | — (secuencial, dinero) | 2, 4 y aprobación de reglas |
| 6 Notificaciones | 2, 3, 7 | 4 (eventos de orden) |
| 7 Garantías | 6 | 4; el efecto económico espera a 5 |
| 8 Mi Espacio | Se construye por bloques junto a 2–7 | Cada bloque, su fase de datos; el cierre, aprobación |
| 9 Retiro | — | 8 + medición de consumidores |

Reglas prácticas:
- **Dos items no editan el mismo archivo a la vez**: `LiquidationService`, `TalentoWorkOrderController`, `Actor.php` y `routes.php` de Talento son puntos de colisión; el detector de colisiones del circuito debe ordenarlos (no se paraleliza 4 con 7 sobre el servicio de cierre).
- **Tres fases pueden avanzar a la vez como máximo** (por ejemplo 2, 3 y 6) y la 5 va sola.
- **El bloque de Mi Espacio de una fase no se entrega antes que su servicio**: p. ej. «Mis Incentivos» espera a la 5; sin ella se muestra el estado vacío con texto.

---

## 5. Cobertura de las pruebas del contrato (sección 18)

| Prueba | Fase |
|---|---|
| Usuario con varios roles operativos | 1, 8 |
| Permisos de mostrador / de técnico | 0, 1, 8 |
| Información privada de empleado | 0, 5, 8 |
| Acceso indebido a órdenes ajenas | 0, 3 |
| Calendario consolidado sin duplicados | 3 |
| Cambios de fecha desde el módulo original | 0, 3 |
| Creación rápida de orden / asignación a técnico / reprogramación | 4 (reprogramación también en 3) |
| Evidencias / cierre técnico / validación supervisada | 4 |
| Atribución de venta a un técnico / empleado no comercial | 2 |
| Comisión con mínimo semanal / sobre ventas adicionales | 5 |
| Venta cancelada | 2, 5 |
| Comisiones históricas pagadas | 2, 5 |
| Instalaciones con varios técnicos | 4, 7 |
| Garantía dentro de seis meses / incidencia ajena | 7 |
| Cálculo de bonificación / aprobación de incentivo | 5 |
| Bloqueo de período / prevención de pago duplicado | 5 |
| Conservación de registros migrados | 2, 5, 9 |
| Compatibilidad con rutas anteriores | 0, 8, 9 |
| Regresión de funcionalidades existentes | todas |

Las 29 pruebas del contrato quedan cubiertas. Se **diseñan** en el item de cada fase; ningún documento de esta épica las escribe ni las corre. Los tests nuevos usan base separada (nunca dev/prod) y no ejecutan directorios completos.

---

## 6. Items propuestos para la Hoja de ruta

**No se crean aquí.** Claude Code los da de alta cuando Irving apruebe el plan. Orden = orden de ejecución sugerido. Riesgo A (aditivo, no toca dinero/permisos/auth/prod), B (confirmación de Irving), C (decisión de Irving). Ante la duda, el más restrictivo. Los códigos `IMP-nn` son provisionales.

| Orden | Código | Título | Módulo | Riesgo | Entregable |
|---|---|---|---|---|---|
| 1 | IMP-00a | Prueba del hueco de calendario/tareas por id (solo diagnóstico) | Scheduling | A | Prueba que reproduce la lectura ajena |
| 2 | IMP-00b | Control por fila en Scheduling (calendario y tareas por id) | Scheduling | B | Código + pruebas; ver #692 |
| 3 | IMP-00c | Control por fila en cortes de caja | Finanzas | B ⚠ | Código + pruebas |
| 4 | IMP-00d | Control por fila en ficha básica y media de OT | Talento | B | Código + pruebas |
| 5 | IMP-00e | API móvil: vigencia, `throttle`, usuario activo, revocación | Core/Auth | C ⚠ | Código + fecha de corte; ver #693 |
| 6 | IMP-00f | Normalizar fechas de `tasks` (columnas derivadas) con ensayo en copia | Tareas | B ⚠ | Migración + `agenda:normalizar-fechas` + reporte; ver #691 |
| 7 | IMP-01a | Catálogos `talento_departamentos` y `talento_capacidades` | Talento | A | Tablas + modelos + siembra |
| 8 | IMP-01b | Captura de supervisores y cuadrillas en la ficha | Talento | A | Pestaña «Estructura» |
| 9 | IMP-02a | Encender doble escritura de `colaborador_id` en dev y conciliar | Vendedores | B ⚠ | Reporte de conciliación en cero |
| 10 | IMP-02b | Clasificar cuentas con ventas sin colaborador | Vendedores | B ⚠ | Lista por conteos + decisión de Irving |
| 11 | IMP-02c | `ventas_atribuciones` y `AtribucionVentaService` | Vendedores/Talento | B | Tabla + servicio + pruebas |
| 12 | IMP-03a | `AgendaService` y adaptadores `tasks`/OT histórica | Talento | A | Servicio + pruebas de no duplicado |
| 13 | IMP-03b | Adaptador de instalaciones del CRM y relación orden–ticket (D2) | CRM/Talento | A | Adaptador + comando |
| 14 | IMP-03c | Recordatorios, reprogramaciones, participantes y choques | Talento | B | 3 tablas + lógica |
| 15 | IMP-03d | Bloque «Mi Agenda» y vista de equipo | Talento (UI) | A | Componentes Vue |
| 16 | IMP-04a | `orden_plantillas` (13 tipos) y siembra D10 | Talento | A | Tabla + datos |
| 17 | IMP-04b | `OrdenService::crear()` y botón «Crear orden» en la ficha | Talento/Clientes | B | Servicio + UI |
| 18 | IMP-04c | Gate de evidencias único, incl. cierre del admin | Talento | B ⚠ | Servicio + permiso explícito |
| 19 | IMP-04d | `orden_estado_historial` y pantalla «Seguimiento» | Talento | A | Tabla + UI |
| 20 | IMP-04e | Reparto de crédito con varios técnicos | Talento | C ⚠ | Cambio en conteo de pago, aprobado por Irving |
| 21 | IMP-06a | Notificaciones por evento (13 tipos) y vista de compatibilidad | Core/Notifications | A | Clases + vista |
| 22 | IMP-06b | Consolidar almacenes de token push (sin emisor) | Core | A | Migración de datos de 0 filas |
| 23 | IMP-05a | Libro de incentivos (`evento_uid`, estados) y servicio | Talento | C ⚠ | Tabla + servicio + pruebas |
| 24 | IMP-05b | Adaptar los 4 generadores a escribir el libro en paralelo | Talento | C ⚠ | Cambio + comparación de resultados |
| 25 | IMP-05c | Políticas de comisión versionadas y metas (modelos A–D) | Talento | C ⚠ | Tablas + sembrado D6 |
| 26 | IMP-05d | `CierreSemanalService` y pantalla de cierre | Talento | C ⚠ | Servicio + UI + exportación |
| 27 | IMP-07a | `talento_garantia_eventos` y revisión con causa | Talento | B | Tabla + formulario |
| 28 | IMP-07b | Apagar la marca automática de «no pagable» | Talento | C ⚠ | Cambio + comparación de liquidaciones |
| 29 | IMP-08a | Permiso base por rol y agrupación de la ficha | Talento/Permisos | C ⚠ | Migración de permisos + reorden |
| 30 | IMP-08b | Bloques Inicio, Mi Trabajo, Mis Resultados | Talento (UI) | A | Componentes |
| 31 | IMP-08c | Mis Ventas y Mis Incentivos con permisos propios | Talento (UI) | B ⚠ | Componentes + permisos |
| 32 | IMP-08d | Pantallas de Técnico, Planta Externa y Supervisor a 360 px | Talento (UI) | A | Componentes + pruebas |
| 33 | IMP-09a | Medir consumidores de pantallas «retirar después» | Core | A | Reporte |
| 34 | IMP-09b | Retiro de accesos por pantalla, con reversión | Core | C | Un item por retiro, aprobado por Irving |
| 35 | IMP-10 | Contrato OpenAPI `/v1` de lo nuevo en `docs/contratos/` | Core/API | A | Documento versionado |

Notas:
- **#695 (UNI-07)** ya existe y actualizará estos documentos al final; no se repite aquí.
- Los items de nivel C o ⚠ no se ejecutan en modo automático: requieren a Irving (`CLAUDE.md`, reglas de ejecución).
- Cada item que cambie datos escribe su entrada en `docs/bitacora/` **antes** de correr en dev y, si algún día se corre en producción, antes de tocarla, con respaldo previo.
- El orden puede paralelizarse según la sección 4.

---

## 7. Preguntas para Irving que bloquean el arranque

Consolidadas de los seis documentos, **sin repetir** las 20 de `01-estado-actual` §6, las 10 de `02` §9, las 7 de `03` §7, las 8 de `04` §8 y las 6 de `05` §13: aquí van las **que de verdad impiden empezar**, agrupadas en 12. Si no responde, se toma la recomendación como supuesto, **excepto** las marcadas ⚠, que no se ejecutan sin su decisión. La respuesta a cada una también destraba a las demás que la repiten (se indica el origen).

**B1 · ¿Se aprueba el plan y el orden de fases?** (todas)
- A. Aprobar el plan tal como está y dar de alta los 35 items por oleadas (Fase 0 y 1 primero). *Pro:* arranca en orden; dinero queda para el final. *Contra:* requiere su revisión previa de los seis documentos.
- B. Aprobar solo las Fases 0–4 (sin dinero) y decidir 5–7 después. *Pro:* valor operativo temprano sin tocar pagos. *Contra:* los bloques económicos de Mi Espacio quedan vacíos.
- C. No aprobar aún. *Pro:* más tiempo. *Contra:* los huecos de acceso (Fase 0) siguen abiertos.
- **Recomiendo B** al inicio y reevaluar 5–7 con datos reales.

**B2 · ⚠ ¿Se cierran primero los huecos de acceso horizontal (Scheduling, cortes de caja, ficha, media de OT)?** (01-estado Q4; 02 P4)
- A. Sí, antes de cualquier pantalla nueva. *Pro:* Mi Espacio nace sin fugas. *Contra:* toca controladores con consumidores.
- B. Durante, módulo por módulo. *Contra:* ventana con el hueco abierto.
- **Recomiendo A** para Scheduling y cortes de caja (dinero); B para el resto.

**B3 · ⚠ ¿Se normalizan las fechas de `tasks` con columnas nuevas?** (02 P1; 05 P3; 01-estado Q6)
- A. Sí, derivadas y ensayadas en copia. *Pro:* base confiable de agenda y choques. *Contra:* cambio de esquema en tabla compartida con el pago.
- B. Normalizar al leer en cada adaptador. *Contra:* el defecto persiste en las otras pantallas.
- **Recomiendo A.**

**B4 · ⚠ ¿Se endurece la autenticación móvil ahora, con fecha de corte?** (02 P8; 01-estado Q19; 03 Q6)
- A. Sí. *Pro:* elimina tokens permanentes (31 de 31 sin vencimiento). *Contra:* obliga a re-iniciar sesión en la app actual; frontera dura.
- B. Esperar a la app nueva. *Contra:* los tokens siguen vivos y se incumple la regla de no tokens permanentes.
- **Recomiendo A**, con la APK actual congelada en sus rutas (`/talento/api/*`) y `/v1` solo para lo nuevo.

**B5 · ⚠ ¿Dónde vive el permiso base de Mi Espacio?** (02 P3; 01-estado Q3)
- A. En los roles de campo y oficina (migración aditiva aparte). *Pro:* toda alta lo hereda. *Contra:* toca permisos de roles.
- B. Seguir con la asignación directa por observer (20 cuentas). *Contra:* perpetúa los directos que la Fase 3b de permisos quiere quitar.
- **Recomiendo A.**

**B6 · ⚠ ¿Quién confirma «pagado» y la política de dinero del libro?** (02 P5; 05 P1; 01-estado Q16)
- A. Confirmación manual por un rol de pre-nómina con referencia externa; el libro nuevo empieza vacío y el ledger vigente queda como histórico. *Pro:* cero riesgo sobre lo ya pagado. *Contra:* los reportes del período suman dos fuentes.
- B. Copiar los 12 movimientos vigentes al libro. *Contra:* toca datos de dinero.
- **Recomiendo A.**

**B7 · ⚠ Regla de compensación de roles no técnicos (#121) y fuente de verdad de la comisión por venta.** (01-estado Q14; 01c)
- A. Cerrar #121 antes de la Fase 5; terminar `ventas_*` y dejar el legacy como histórico. *Pro:* una sola fuente. *Contra:* retrasa la Fase 5.
- B. Reactivar los listeners legacy. *Contra:* riesgo de doble cálculo.
- **Recomiendo A**, sin comisión si no hay política asignada.

**B8 · ⚠ Garantía: inicio de los 6 meses y efecto económico.** (01-estado Q13; 02 P9)
- A. Inicio en la aceptación técnica con respaldo a la validación; apagar «no pagable / 0 puntos»; solo evento + causa + revisión. *Pro:* cumple §9. *Contra:* cambia un cálculo existente.
- B. Mantener la lógica actual. *Contra:* contradice el contrato (responsabiliza al técnico automáticamente).
- **Recomiendo A.**

**B9 · ¿Quién captura departamentos, capacidades y supervisores, y cuándo?** (01-estado Q5; 02 P6; 04 P4)
- A. Irving/RR. HH. antes de la pantalla de supervisión. *Pro:* nace con datos. *Contra:* trabajo administrativo previo.
- B. Derivar de puesto/departamento. *Contra:* el departamento hoy es texto libre.
- **Recomiendo A.**

**B10 · ⚠ Cuentas con ventas sin colaborador y encendido de la doble escritura.** (01-estado Q1–Q2; 02 P10; 05 P2)
- A. Confirmar cuáles son personas (lista por conteos) y encender el flag en dev ya. *Pro:* detiene el crecimiento de la deuda. *Contra:* requiere su confirmación fuera del repo.
- B. Esperar. *Contra:* el hueco sigue creciendo.
- **Recomiendo A.**

**B11 · ¿Qué puede ejecutar el circuito solo y qué exige a Irving?**
- A. Niveles A en automático; B con su confirmación; C y todo ⚠ solo con su decisión (regla vigente). *Pro:* coincide con `CLAUDE.md`. *Contra:* más pausas en la Fase 5.
- B. Permitir autopilot hasta B en las Fases 0–4. *Pro:* más rápido. *Contra:* toca permisos y cierre de órdenes sin supervisión.
- **Recomiendo A** para dinero, permisos y autenticación; B queda como opción solo para los items A de las Fases 1, 3 y 6.

**B12 · ¿Se ejecuta algo en producción, y cuándo?** (05 P6)
- A. Nada hasta cerrar y conciliar en dev; luego una fase a la vez con bitácora previa. *Pro:* sin sorpresas. *Contra:* más lento.
- B. Adelantar fases independientes (notificaciones). *Contra:* producción divergente de dev.
- **Recomiendo A.**

Las demás decisiones (embajador vs vendedor, Mapas vs MapaRed, retiro de `Ventas`, plantillas 5 vs 13, orden rápida, tablas vacías, etc.) están en sus documentos y **no bloquean el arranque**: se resuelven cuando su fase llegue.

---

## 8. Qué no se pudo verificar

- **Producción**: todo es dev; ids, conteos y huérfanos pueden diferir. Cada conciliación se repite allá antes de actuar.
- **Estimaciones de archivos y de esfuerzo**: salen de la auditoría de los documentos 01–05; el ejecutor las confirma con `grep` antes de editar. No se midió tiempo de ejecución por item.
- **El resultado de #691–#694**: están en curso; al cerrar pueden cambiar el alcance de las Fases 0, 3 y 6. #695 (UNI-07) lo registra.
- **La idempotencia real de los comandos `identidad:backfill-colaborador-id*`** y la causa de la reescritura masiva de fechas de `tasks` del 2026-10-14 (05 §12): condicionan 2a y 0c.
- **La existencia de consumidores externos** de las pantallas a retirar: se mide en IMP-09a, no se asume.

## 9. Cómo se verificó

Lectura cruzada de los documentos 01 a 05 y del contrato adjunto; las cifras provienen de ellos (BD de dev, solo lectura). No se ejecutó código, migración ni comando que escriba. El documento no contiene nombres de personas, contactos, montos por persona, credenciales ni datos de clientes.

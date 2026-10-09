## 2026-10-08 19:20 — Monitoreo Oficial, Fase 6: avisos por correo, WhatsApp y campana interna

### Contexto

Continuación directa de la Fase 5 (mergeada hoy). "F6 notificaciones" — avisar cuando la IA de
la Fase 5 marca un documento con cambio de fondo (`ai_has_relevant_change = true`).

### Qué se construyó

- `CaseChangeNotificationService`: 3 canales independientes, cada uno best-effort (uno fallando
  no tumba a los demás ni la cola):
  - **Campana interna**: a quien subió el documento y a quien dio de alta el expediente —
    siempre, sin configurar nada (son usuarios reales ya ligados a la tabla).
  - **Correo**: solo si el expediente tiene `notify_email` configurado (campo nuevo, opcional,
    editable en el formulario de alta/edición).
  - **WhatsApp**: solo si tiene `notify_whatsapp` configurado.
- Todo reusa servicios compartidos ya existentes (`Correo::enviar()`, `Servicios::whatsapp()`,
  `StandardNotification`/`StandardMail`) — nada de clientes HTTP ni claves propias.
- `AnalyzeCaseDocumentJob` (Fase 5) dispara la notificación solo cuando el análisis fue exitoso
  Y marcó cambio relevante — nunca por cada documento subido.

### Bug real encontrado probando el flujo completo, no solo revisando código

El payload de la notificación de campana se armaba con una **clase PHP anónima**.
`StandardNotification` implementa `ShouldQueue` — Laravel intenta serializar ese payload para
meterlo en la cola, y las clases anónimas **no se pueden serializar**
(`"Serialization of 'class@anonymous' is not allowed"`). El propio `try/catch` de la campana
atrapaba el error y lo registraba en el log (nunca rompió el job ni la cola), pero el efecto
visible era que **la campana nunca sonaba**, con cero pistas obvias salvo revisar el log con
cuidado.

Se encontró subiendo dos documentos reales con un cambio real y confirmando que, aunque el
análisis marcaba `cambio_relevante=true` correctamente, la tabla `notifications` seguía en cero.
Reproducido aislado en `tinker` para confirmar la causa exacta antes de tocar el código.

**Fix**: `CaseChangeNoticeData`, una clase nombrada en su propio archivo — mismo contenido, ahora
sí serializable.

**Segundo hallazgo operativo (no es un bug de código):** el primer intento de verificar el fix
seguía fallando — la razón era que el worker de colas ya tenía la clase vieja cargada en memoria
(los workers son procesos de larga duración; PHP no recarga clases solo). Hubo que hacer
`php artisan queue:restart` para que recogiera el código nuevo. Buena lección para cualquier otro
cambio a un Job/Notification mientras el worker ya está corriendo.

### Verificación

Flujo real de punta a punta, con el fix ya aplicado y el worker reiniciado: documento con cambio
real → análisis con IA correcto → `cambio_relevante=true` → campana creada con el contenido
exacto ("Monitoreo Oficial: cambio detectado en «EXPEDIENTE PRUEBA FASE 6»" + el resumen real del
cambio). Correo: se intentó el envío real por SMTP sin lanzar ninguna excepción (a una dirección
de prueba no entregable a propósito, para no mandar nada a nadie real). WhatsApp: **no se envió
ningún mensaje real** — se verificó por separado, solo en código, que el proveedor resuelve
correctamente y que `normalizeRecipient()` arma el JID esperado
(`5215512345678@s.whatsapp.net`), sin disparar el envío real — no hay forma segura de probar un
envío real sin mandarle algo a un número de verdad.

Datos de prueba (expedientes, documentos, usuario, notificación, asignación de IA temporal)
borrados al terminar — confirmado por conteo en cero.

### Pendiente (fases siguientes de la épica, fuera de esta Fase 6)

F7 UI (pulir pantallas), F8 validación final de todo el módulo junto.

### Estado

Mergeado directo a `main` (mismo patrón manual del resto del día).

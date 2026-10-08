<!-- Generada por `php artisan asistente:aprender mensajes` desde docs/modulos/mensajes.md, el menú y las pantallas de alta · huella c3cb0d41bb51205d470c03f5c1e4b8e5b210fa8b · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Mensajes
**En pocas palabras:** Módulo para gestionar correos y recordatorios automáticos de cobro.

## Para qué sirve
- Agrupa mensajes recibidos del sistema y recordatorios de cobro.
- Permite el reenvío manual de correos transaccionales a clientes.
- Ofrece un registro de comunicaciones para auditoría y seguimiento.
- Facilita la consulta de estado de correos enviados y pendientes.

## Pantallas
- [Inbox]( /message/inbox) — Muestra los mensajes y recordatorios generados por el sistema.

## Cómo se hace
### Marcar mensajes como leídos
1. Acceder a la bandeja [Inbox]( /message/inbox).
2. Seleccionar los mensajes deseados y utilizar la opción para marcarlos como leídos.

### Reenviar un correo
1. Acceder a la bandeja [Inbox]( /message/inbox).
2. Encontrar el mensaje que deseas reenviar.
3. Hacer clic en el botón para reenviar.

## Qué datos pide
### Alta de mensajes
- **Obligatorios:** Asunto, Tipo, Destinatario, Cuerpo del mensaje, Fecha de envío, Estado
- **Opcionales:** Documento relacionado, Canal

## Quién lo usa
Este módulo es utilizado principalmente por el área de facturación, cobranza y soporte para gestionar comunicaciones con clientes y entre áreas.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.

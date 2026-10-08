<!-- Generada por `php artisan asistente:aprender tickets` desde docs/modulos/tickets.md, el menú y las pantallas de alta · huella dd3aecd008e1f652bdfb97e5a08f2d018d5322e4 · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Tickets (soporte)
**En pocas palabras:** Es el módulo que gestiona las solicitudes de asistencia de los clientes del ISP.

## Para qué sirve
- Registra incidencias y solicitudes de clientes en un solo lugar.
- Permite el seguimiento completo del ciclo de vida de cada ticket.
- Facilita la asignación de tickets a agentes de soporte y la actualización de su estado.
- Proporciona un dashboard con métricas clave sobre el desempeño del equipo.

## Pantallas
- [Dashboard](/tickets) — Muestra resumen de KPIs y listado de tickets.
- [Abiertos](/tickets/abiertos) — Presenta todos los tickets activos que requieren atención.
- [Cerrados](/tickets/cerrados) — Acceso al historial de tickets resueltos.
- [Papelera](/tickets/reciclados) — Visualiza los tickets eliminados lógicamente.

## Cómo se hace
### Crear un ticket
1. Hacer clic en el botón para crear un ticket en el área correspondiente.

### Ver un ticket
1. Acceder a la pantalla [Abiertos](/tickets/abiertos) o [Cerrados](/tickets/cerrados).
2. Seleccionar el ticket que se quiere ver.

## Qué datos pide
### Alta de ticket ([Crear ticket](/tickets/crear))
- **Obligatorios:** Tema, Colonia
- **Opcionales:** Cliente, Escondido, Asignado a:, Mensaje, Teléfono, Teléfono 2, Adjuntar
- **Se llenan solos:** Prioridad (ya viene puesto: Normal), Grupo (ya viene puesto: Cualquier), Tipo (ya viene puesto: Pregunta), Fecha y Hora (ya viene puesto: hoy)

## Quién lo usa
Lo utilizan principalmente los equipos de soporte técnico, atención al cliente y supervisores de operaciones.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.

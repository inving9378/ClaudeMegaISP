<!-- Generada por `php artisan asistente:aprender payments` desde docs/modulos/payments.md, el menú y las pantallas de alta · huella 7056a67861ae2300fd1622d6bb47213cf20ff51a · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Pagos y Conciliaciones
**En pocas palabras:** Maneja la recepción y aplicación de pagos de clientes.

## Para qué sirve
- Permite el cobro automático por transferencia a través de OpenPay.
- Facilita la conciliación de pagos reportados por WhatsApp de forma automatizada.

## Pantallas
- [Registrar pago reportado](/finanzas/captura-pago) — Captura pagos que los clientes reportan manualmente.
- [Cola de Conciliación](/finanzas/conciliacion) — Revisa los comprobantes de pagos recibidos por WhatsApp.
- [Métodos de Pago SPEI](/finanzas/metodos-pago) — Administra los métodos disponibles para recibir pagos.

## Cómo se hace
### Registrar un pago en mostrador
1. En la ficha del cliente, ve a la pestaña **Facturación** y selecciona crear pago.

### Pago que el cliente reportó
1. Ir a la pantalla [Registrar pago reportado](/finanzas/captura-pago).
2. Buscar al cliente por nombre o referencia MEG.
3. Capturar el comprobante de pago.

## Qué datos pide
### Alta de pago reportado ([Registrar pago reportado](/finanzas/captura-pago))
- **Obligatorios:** Client, Monto, Fecha pago, Method of payment
- **Opcionales:** (ninguno)

## Quién lo usa
Lo usan los equipos de cobranza y administración para gestionar pagos de clientes.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.

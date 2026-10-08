<!-- Generada por `php artisan asistente:aprender finanzas` desde docs/modulos/finanzas.md, el menú y las pantallas de alta · huella 181d633916d83cd84b51387fdc98e15892eccd4c · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Finanzas
**En pocas palabras:** Módulo central para la gestión económica del ISP.

## Para qué sirve
- Centraliza la facturación, pagos, transacciones y contabilidad general.
- Permite al personal de cobranza y administración consultar ingresos y egresos de manera efectiva.
- Facilita la creación de comprobantes y el registro de pagos, mejorando la trazabilidad.

## Pantallas
- [Finanzas › Transacciones](/finanzas/transacciones) — Consulta el historial de movimientos económicos.
- [Finanzas › Facturas](/finanzas/facturas) — Gestiona comprobantes emitidos a los clientes.
- [Finanzas › Pagos](/finanzas/pagos) — Registra y consulta cobros recibidos.
- [Finanzas › Facturas Proforma](/finanzas/invoices) — Genera avisos de cobro previos.
- [Finanzas › Contabilidad General](/finanzas/general-accounting) — Ofrece un resumen del estado financiero.

## Cómo se hace
### Registrar un pago en mostrador
1. En la ficha del cliente, ir a la pestaña **Facturación** y crear pago.

### Buscar un cliente para registrar un pago reportado
1. Acceder a [Registrar pago reportado](/finanzas/captura-pago).
2. Buscar al cliente por nombre o su referencia MEG y capturar el comprobante.

### Consultar las facturas proforma
1. Acceder a [Facturas proforma](/finanzas/invoices).

### Consultar listados generales
1. Acceder a [Pagos](/finanzas/pagos), [Facturas](/finanzas/facturas) o [Transacciones](/finanzas/transacciones).

## Qué datos pide
### Alta de ingreso
- **Obligatorios:** Descripción del ingreso, Monto.

### Alta de gasto
- **Obligatorios:** Descripción del gasto, Monto.

### Alta de operación
- **Obligatorios:** Clasificación, Descripción, Monto, Recurrente (OJO NO ESTA FUNCIONAL DE MOMENTO), Fecha.

### Alta de contabilidad
- **Obligatorios:** Nombre, Balance.
- **Opcionales:** (ninguno).

## Quién lo usa
Es utilizado principalmente por el personal administrativo y contable del ISP, como administradores y supervisores de cobranza.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.

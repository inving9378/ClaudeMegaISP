<!-- Generada por `php artisan asistente:aprender clientes` desde docs/modulos/clientes.md, el menú y las pantallas de alta · huella 6a914e1cee48a96f7d2592672aa60b2b6e6c973f · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Clientes
**En pocas palabras:** Gestiona la información integral de los clientes activos del ISP.

## Para qué sirve
- Permite dar de alta nuevos clientes y gestionar su información.
- Facilita la visualización de datos, servicios contratados, pagos y estado del cliente.
- Sirve como punto de consulta para el personal administrativo, técnico y de cobranza.
- Integra las operaciones relacionadas con cada cliente en un solo lugar.

## Pantallas
- [Clientes › Dashboard](/cliente) — Muestra un resumen de los clientes y su estado.
- [Clientes › Crear](/cliente/crear) — Permite registrar un nuevo cliente.
- [Clientes › Listar](/cliente/listar) — Muestra una lista de todos los clientes activos.

## Cómo se hace
### Buscar un cliente
1. Ir a [Lista de clientes](/cliente/listar) y buscar por nombre, número de cliente, teléfono, correo o número de serie del equipo.

### Crear un cliente
1. Acceder a [Crear cliente](/cliente/crear).

### Consultar ficha del cliente
1. Desde la lista de clientes, seleccionar el cliente cuyos datos se desean ver.

## Qué datos pide
### Alta de cliente ([Crear cliente](/cliente/crear))
- **Obligatorios:** Nombre, Apellido Paterno, Apellido Materno, Correo, NIF / Pasaporte, Colonia, Duración del Contrato
- **Opcionales:** S/N (SERIE MODEM), GPON ONT, Potencia en dbm, Contraseña Original, Nomenclatura, Usuario Peliculas, Contraseña Peliculas, Contraseña de Wifi, Teléfono, Reinstalación, Social ID, Comentario, El técnico instalo en tiempo, Socios, Cual fue el monto que cobro el técnico y porque, Teléfono 2, Ubicación, Tiene dudas acerca del contrato que esta firmando, El técnico le atendio con amabilidad y respeto si, no y porque, Calle, Código Zip, Número exterior, Número interior, Datos geográficos, Medio de venta, Nomenclatura antigua, RFC, Coverage Notes
- **Se llenan solos:** Tipo de Conexión (ya viene puesto: olt), Categoría (ya viene puesto: Particular), Usuario (se genera sola), Contraseña (se genera sola), Tipo de facturación (ya viene puesto: la opción de siempre), Ift (ya viene puesto: la opción de siempre), Estado (ya viene puesto: Nuevo(Todavía no conectado)), Fecha de Activación (ya viene puesto: hoy), Fecha de alta (ya viene puesto: hoy), Vendedor (ya viene puesto: quien lo captura), Costo de Activación (ya viene puesto: 299), Pago el Costo de Activación ? (ya viene puesto: no), Cliente Dedicado (ya viene puesto: no)

## Quién lo usa
El módulo es utilizado por el personal de mostrador, ventas, técnicos y administración en su operación diaria.

## Con JARVIS
JARVIS puede ayudar a buscar clientes, dar de alta nuevos clientes y consultar su ficha, pagos, facturas y tickets.

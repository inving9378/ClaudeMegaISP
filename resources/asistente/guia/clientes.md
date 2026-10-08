# Clientes

- **Buscar un cliente:** en [Lista de clientes](/cliente/listar) se busca por nombre, número de
  cliente, teléfono, correo o número de serie del equipo. Con JARVIS basta con decir el nombre, el
  teléfono o el número.
- **Crear un cliente:** [Crear cliente](/cliente/crear). Se capturan datos de contacto, dirección
  (colonia, municipio, estado) y después se le agregan servicios desde su ficha.
- **Ficha del cliente:** se abre desde la lista. Pestañas:
  - **Información:** nombre, teléfonos, correo, dirección, estado, vendedor, usuario y contraseña del
    portal de clientes.
  - **Documentos:** contratos y documentos generados o subidos.
  - **Servicios:** internet, voz, paquetes y servicios personalizados que tiene contratados, con su
    precio y estado.
  - **Facturación:** pagos, facturas y movimientos del saldo. Desde aquí se registra un pago.
  - **Estadísticas:** consumo de internet y conexión.
  - **Promociones:** promociones aplicadas.
- En el encabezado de la ficha se ve el **saldo** (cuánto debe o cuánto tiene a favor) y la
  **referencia MEG** con un botón para copiarla.
- **Estados:** Activo, Bloqueado (servicio suspendido), Inactivo, Cancelado.
- **Portal de clientes:** el cliente entra a su portal con su usuario o correo y su "Contraseña WEB"
  (la de la pestaña Información). Ahí ve sus facturas, pagos y tickets, y puede pagar en línea.
- Enlaces directos a la ficha: `/cliente/editar/{número}` y a una pestaña con `?tab=`:
  `pagos` (facturación), `services`, `statistics`, `documents`, `information`.

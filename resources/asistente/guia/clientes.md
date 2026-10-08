# Clientes

- **Buscar un cliente:** en [Lista de clientes](/cliente/listar) se busca por nombre, número de
  cliente, teléfono, correo o número de serie del equipo. Con JARVIS basta con decir el nombre, el
  teléfono o el número.
- **Crear un cliente:** [Crear cliente](/cliente/crear). La pantalla pide nombre con apellido paterno
  y materno, CURP (o identificación), correo, colonia y plazo del contrato (6, 12, 18, 24 o 36 meses);
  teléfono y domicilio son opcionales. El usuario y la contraseña del portal se generan solos. Entra
  como "Nuevo", y después se le agregan servicios desde su ficha. JARVIS puede darlo de alta.
- **Prospecto (posible cliente):** [Crear prospecto](/crm/crear). Basta con nombre y apellidos,
  teléfono y colonia; el correo es opcional. Es lo que se usa cuando alguien pide informes y todavía
  no contrata. Se ven en [Lista de prospectos](/crm/listar). JARVIS puede registrarlo.
- **Nombre repetido:** las dos pantallas avisan si ya hay alguien con el mismo nombre completo o el
  mismo correo; se puede registrar de todos modos si se confirma que es otra persona.
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

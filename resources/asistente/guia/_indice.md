# Mapa del sistema MegaISP

El menú de la izquierda organiza el sistema por áreas. Cada usuario solo ve las áreas a las que
tiene acceso. Pantallas principales:

- **Inicio (Dashboard):** resumen del día — [Inicio](/)
- **Clientes:** [Lista de clientes](/cliente/listar) · [Crear cliente](/cliente/crear) ·
  [Dashboard de clientes](/cliente/). La **ficha del cliente** tiene pestañas: Información,
  Documentos, Servicios, Facturación (pagos, facturas y transacciones), Estadísticas y Promociones.
- **CRM (prospectos):** [Lista de prospectos](/crm/listar) · [Crear prospecto](/crm/crear)
- **Tickets (soporte):** [Tickets abiertos](/tickets/abiertos) · [Cerrados](/tickets/cerrados) ·
  [Dashboard de tickets](/tickets)
- **Finanzas:** [Pagos](/finanzas/pagos) · [Facturas](/finanzas/facturas) ·
  [Transacciones](/finanzas/transacciones) · [Facturas proforma](/finanzas/invoices) ·
  [Registrar pago reportado](/finanzas/captura-pago) · [Cola de conciliación](/finanzas/conciliacion)
- **Portal de pago:** [Conciliación](/pagos/conciliacion) · [Ligas de pago](/pagos/links) ·
  [Cuentas de cobro](/pagos/cuentas)
- **Planes:** [Internet](/internet) · [Voz](/voz) · [Personalizados](/custom) · [Paquetes](/paquetes)
- **Agenda y tareas:** [Tareas](/scheduling/task) · [Calendario](/scheduling/task/calendar) ·
  [Proyectos](/scheduling/project)
- **Red:** [Routers](/red/router/listar) · [Red IPv4](/red/ipv4/listar) · [OLTs](/olts) ·
  [Mapa de red](/mapa-red) · [Mapas y zonas](/mapas)
- **Inventario:** [Artículos](/inventory/inventory_item) · [Stock](/inventory/inventory_item_stock) ·
  [Movimientos](/inventory/inventory_movement) · [Almacenes](/inventory/inventory_store)
- **Vendedores:** [Dashboard](/vendedores) · [Vendedores](/sellers/seller) · [Prospectos](/vendedores/prospectos)
- **Talento (personal de campo):** [Colaboradores](/talento) · [Órdenes de trabajo](/talento/ordenes)
- **Mensajes y WhatsApp:** [Bandeja de mensajes](/message/inbox) · [Conversaciones de WhatsApp](/whatsapp)
- **Marketing:** [Leads](/marketing/leads) · [Campañas](/marketing)
- **Otros:** [Embajadores](/embajadores) · [MegaFamilia](/megafamilia) · [Flotas](/flotas) ·
  [Cobranza por llamadas](/cobranza/campanas) · [Manual del sistema](/manual)
- **Administración y configuración:** [Administración](/administracion) · [Configuración](/configuracion)

Temas con más detalle (pídelos con `consultar_guia`): `clientes`, `pagos-y-facturacion`, `tickets`,
`red-y-equipos`, `modulos` (qué hace cada módulo del sistema).

# Palabras del negocio

- **Número de cliente:** el número con el que se identifica a cada cliente (a veces se escribe con
  ceros a la izquierda, por ejemplo 004981 = 4981).
- **Estado del cliente:** Activo (con servicio), Bloqueado (servicio suspendido, normalmente por
  falta de pago), Inactivo, Cancelado.
- **Moroso:** cliente con pagos vencidos.
- **Saldo:** si el cliente **debe**, el saldo es una deuda; si pagó de más, tiene **saldo a favor**.
- **Fecha de pago / fecha de corte:** la fecha límite para pagar y la fecha en que se suspende el
  servicio si no paga.
- **Referencia MEG:** la referencia de pago del cliente (por ejemplo MEG-00004981-37) que usa para
  transferencias; está en el encabezado de su ficha.
- **Factura proforma:** el aviso de cobro que se genera antes de la factura.
- **Equipo / ONU / módem:** el aparato de internet en casa del cliente; tiene un número de serie.
- **Señal:** la potencia de la fibra en el equipo del cliente (se mide en dBm; entre -19 y -27 es
  buena).
- **Ticket:** un reporte de soporte (falla, duda, solicitud) que se atiende hasta cerrarlo.

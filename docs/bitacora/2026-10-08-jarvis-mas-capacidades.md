## 2026-10-08 07:32 — JARVIS (robotsito): listados, alta de clientes y prospectos, y cero pretextos

Rama: `feature/jarvis-mas-capacidades`. Sin item de la Hoja de Ruta (rama aparte normal).

**Qué falló** (conversación real de hoy, usuario 3841):
- "Dame información de los últimos 3 clientes registrados" → JARVIS solo podía buscar un cliente por
  nombre/teléfono/número; llamó `buscar_cliente` con texto vacío y luego con "cliente" y contestó que
  no encontró nada.
- "Agrega el cliente tú mismo: Romelio Eugenio Suárez Espinosa…" → no había herramienta de alta; usó
  `actualizar_contacto_cliente` con cliente 0 y luego inventó un pretexto ("necesito confirmación del
  sistema, no hay número de cliente asignado").

**Qué se agregó** (`app/Services/Asistente/Herramientas/`):
- Listados con filtros (cuántos, desde/hasta, solo contar): `listar_clientes` (estado, colonia,
  vendedor), `listar_prospectos` (estado, texto, colonia, responsable), `listar_tickets` (abiertos/
  cerrados, prioridad, asignado) y `listar_pagos` (método; total cobrado del periodo). Cada uno con el
  permiso de su pantalla.
- `buscar_colonia`: catálogo de colonias (nombre, CP, municipio, estado), lo piden las altas.
- `crear_prospecto` (permiso `crm_add_crm`) y `crear_cliente` (`client_add_client`), ambos con
  confirmación. Van por el MISMO camino que las pantallas: `CrmCreateRequest`/`ClientCreateRequest`
  + `CrmController::store`/`ClientController::store`, con los valores por defecto del formulario
  (estado Nuevo, vendedor/responsable = quien lo pide, activación $299, usuario y contraseña del
  portal generados como la pantalla). `Formulario` arma esa petición y la pone como la petición en
  curso mientras se valida y guarda (las reglas leen `request()`; si no, validarían la del chat).
- `anotar_pendiente`: cuando le piden algo que no puede hacer, lo anota en `activity_log`
  (`log_name='jarvis'`, descripción `Pendiente: …`) y lo dice sin pretextos.
- Instrucciones (`system-prompt.md`): regla 3 sin pretextos + regla 8 "haces lo que te piden"; cómo
  usar los listados; prospecto vs. cliente, cómo separar apellidos, colonia del catálogo, nombre
  repetido (`aunque_exista`). Guía de clientes con lo que pide cada alta.

**Verificado en dev** (usuario de pruebas `circuito_test_126`, con la IA configurada):
- Las mismas dos preguntas de hoy: lista los últimos 3 clientes con enlace; para el alta pide CURP,
  plazo y colonia; con "regístralo como prospecto, colonia Guadalajara Centro" busca la colonia,
  prepara el prospecto y, al confirmar, lo registra con bitácora "Vía JARVIS" (todo dentro de una
  transacción deshecha: 0 registros nuevos).
- Alta de cliente completa (transacción deshecha): cliente + saldo + configuración de facturación +
  fecha de corte, igual que la pantalla. Correo repetido y nombre repetido se rechazan con el mismo
  mensaje de la pantalla; datos faltantes se piden en una lista.
- Pedido sin herramienta ("cámbiale el plan…") → anota el pendiente y lo dice claro, con enlace.
- Listados contra datos reales en 4–260 ms.

**Observación (sin tocar):** el domicilio completo (`client_main_information.address`) no se arma al
crear (ni desde la pantalla); se llena en la primera edición de la ficha.

**Pendiente:** probar en el navegador; ver qué se anota como pendiente para decidir las siguientes
capacidades (cambio de plan, registrar pagos —es dinero, va aparte—, agendar visitas, notas).

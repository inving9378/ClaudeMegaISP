## 2026-10-06 — Integraciones: pestaña "Módulos de servicios"

Rama `servicios-por-modulo` (worktree `/home/meganet/megaisp-wt-integrations`), commit `fdcf85a5`.
Continúa el mapa `docs/integraciones/mapa-servicios-externos-2026-10-05.md` y el trabajo de IA por módulo
(`docs/bitacora/2026-10-05-ia-cambio-de-llave.md`).

Pedido de Irving: "haz la pestaña Módulos de servicios con todo lo que hay que meterle". Decisiones del
2026-10-06: sin asignación el módulo sigue como siempre, y una migración deja asignado lo que funciona hoy;
WhatsApp: el asistente responde por Meta y Evolution queda solo para ver; nunca se le escribe primero al
cliente; la seguridad se corrige después. La migración de WhatsApp a Meta por línea la hizo otra sesión
(`docs/bitacora/2026-10-05-whatsapp-api-oficial-meta.md`); aquí no se tocó su webhook ni su router.

### Qué se veía → qué pasa ahora

- **No había dónde ver ni cambiar qué usa cada módulo para WhatsApp, correo, mapas, etc.** → Integraciones
  tiene la pestaña **Módulos de servicios** con 29 módulos agrupados por área. Cada uno dice qué usa
  ("Como siempre — …" o lo asignado), se puede cambiar, y tiene botón **Probar** que revisa lo que el módulo
  usaría ahora **sin mandar mensajes ni correos** (estado de la línea, conexión con el servidor de correo,
  llave válida).
- **Los avisos de WhatsApp que manda el sistema salían todos por la "línea principal"** (y si no había,
  no salían) → cada aviso puede salir por la línea que se elija: pago recibido, domiciliación (liga y avisos de
  cobro), pausas y recordatorio de facturación, Talento (documentos y OT asignada), embajadores, referidos,
  resumen de pagos, WarRoom (minutas y acciones) y Flotas (geocercas, documentos, suscripción). Si la línea es
  de Evolution sale por **esa** instancia; si es oficial de Meta, por Meta (la pestaña avisa que Meta no deja
  escribir primero). Si la línea elegida se desactiva, vuelve a lo de siempre.
- **Todo el correo salía de una sola cuenta** → nuevo proveedor "Servidor de correo (SMTP)" en Integraciones
  (servidor, puerto, cifrado, usuario, remitente; la contraseña va cifrada). Cada envío de correo puede usar
  su propia cuenta: facturación, proformas/recordatorios de Mensajes, CRM, tickets, domiciliación, recibo del
  portal, resumen de pagos, evaluador empresarial, campañas de Marketing, Flotas y vencimientos de Talento.
  El correo sale con el remitente de esa cuenta. Al guardar la cuenta se prueba la conexión al instante.
- **Mapas, videos de stock y Facebook/Instagram** → se puede elegir qué integración usan; sin asignación,
  la llave de Configuración → Mapas, la principal de Pexels y la principal de Meta, como siempre.
- **Se podía borrar una integración en uso** → ahora no deja borrarla si la usa un módulo de servicios, y
  desactivarla avisa qué módulos vuelven a lo de siempre. Cada integración muestra cuántos módulos la usan.
- Los proveedores de tipo Servicios declaran qué servicio dan (pestaña Proveedores).

### Piezas

- `app/Modules/Addons/Hub/config/servicios_modulos.php` (catálogo), `Services/Servicios.php`,
  `Services/Correo.php`, `Jobs/EnviarCorreoJob.php`, `Models/ServicioAsignacion.php`,
  `Controllers/ServiciosModulosController.php` (+ rutas `api/hub/servicios-modulos`).
- Migraciones del módulo Hub: `2026_10_06_100000_create_servicio_asignaciones_table` (columna
  `api_integration_providers.servicio`, proveedor `smtp`, tabla `servicio_asignaciones`) y
  `2026_10_06_100100_preasigna_servicios_por_modulo` (WhatsApp → línea principal si existe; Pexels y Meta → su
  integración principal activa). En dev quedó asignado solo Pexels (no hay línea principal; Meta inactiva).
- `Marketing\Services\EvolutionApiService::usarInstancia()` (aditivo) para mandar por la línea elegida.
- 15 envíos de WhatsApp, 16 de correo, mapas (`render-config`), Pexels y Meta conectados. Sin asignación,
  cada uno hace exactamente lo de antes.
- El Circuito CC (alarma de Jarvis) queda fuera a propósito.

### Verificación (transacción + rollback, cola falsa, correo a buzón de prueba: nada real enviado)

- Pestaña: 29 módulos (15 WhatsApp, 11 correo, mapas, stock, redes) con su "como siempre" correcto.
- WhatsApp: sin asignar → igual que antes; asignado a la línea `pruebas` → el gateway registra el mensaje en
  esa línea y el envío directo (incluso de Flotas de otra empresa) usa la instancia `pruebas`; línea pasada a
  Meta → usa Meta; línea inactiva → vuelve a lo de siempre; Probar reporta "no conectada (close)".
- Correo: cuenta falsa → la prueba al guardar explica que no conecta; asignada a Flotas → el correo sale con
  De: la cuenta, Para: el destino; encolado → `EnviarCorreoJob`; notificación del sistema y aviso de ticket
  usan la cuenta; servidor del sistema → "Conectó y se autenticó"; borrar cuenta en uso → bloqueado.
- Mapas con integración asignada → `render-config` entrega la llave del Hub; Pexels pre-asignado → su llave;
  Probar Pexels → "la llave funciona".
- Frontend compila.

### Pendiente

- Validación visual en el navegador (no hay credencial de prueba para entrar).
- Huecos de WhatsApp que son de la otra sesión: doble entrada Evolution+Meta en coexistencia, modo
  "Meta + vista Evolution", bloqueo previo fuera de 24 h.
- Seguridad (para después, decisión de Irving): `config` del Hub sin cifrar y visible en pantalla, token de
  COPOMEX, MikroTik.

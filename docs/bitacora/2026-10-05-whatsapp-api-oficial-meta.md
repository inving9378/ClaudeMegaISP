## 2026-10-05 — Migración de WhatsApp a la API oficial de Meta (configurable)

### Origen

Preocupación real: el sistema usa Evolution API (automatización no oficial de WhatsApp Web,
librería Baileys) para todo el tráfico de WhatsApp — bot de ventas, conciliación de pagos,
alertas de geocercas, recordatorios de cobro, etc. Esto arriesga que Meta bloquee el número por
detectar uso automatizado no oficial — el riesgo es real y mayor en los envíos masivos/proactivos
(recordatorios en lote) que en el chat reactivo (responder a alguien que ya escribió).

Decisión: migrar a la API oficial de WhatsApp Business Platform (Meta Cloud API), configurable
por línea (no un interruptor global) — mismo patrón de Integration Hub ya usado para IA.

### Investigación previa (sin cambios de código)

Se auditó la arquitectura completa antes de tocar nada. Hallazgos clave:
- CLAUDE.md decía "WhatsAppAgent es el gateway único" — **ya no es cierto**: existe una
  segunda copia completa (`Marketing\Services\EvolutionApiService`), usada directamente por
  **15 archivos en 7 módulos** (Marketing, Payments, Flotas, PortalPago, WarRoom, Roadmap,
  Referrals). El webhook de ENTRADA sí es único (vive en WhatsAppAgent); la duplicación está
  del lado de ENVÍO.
- Los eventos del gateway (`WhatsAppMessageReceived`/`TextReceived`/`MediaReceived`) ya eran
  genéricos y bien diseñados — no hubo que tocarlos.
- **Diferencia de contrato real que importa**: la API oficial de Meta solo permite texto libre
  dentro de una ventana de 24h desde el último mensaje del cliente; fuera de eso exige
  **plantillas pre-aprobadas por Meta** (trámite del usuario, no de código). Esto rompe los
  flujos proactivos (recordatorios de pago, alertas de geocerca) si no se resuelve con
  plantillas aprobadas.
- Costo verificado (tarifas de México, vigentes desde 1-oct-2026): sin cuota fija; $0.0397 USD
  por mensaje de marketing, $0.0085 USD por utilidad/autenticación, 1,000 mensajes de servicio
  (chat reactivo) gratis al mes por número, luego $0.0085 c/u. Las plantillas de "utilidad"
  (recordatorios/alertas) caen en la tarifa barata, no la de marketing.

### Lo construido en esta sesión

1. **`WhatsAppProviderInterface`** — contrato compartido entre proveedores (sendText, sendMedia,
   sendTemplate, downloadMedia, markAsRead, sendTyping, fetchInstanceStatus, setWebhook,
   normalizeRecipient).
2. **`Marketing\Services\EvolutionApiService`** (la clase real con 15 consumidores) ahora
   implementa el contrato — **cero cambio de comportamiento** en lo que ya existía, solo 3
   métodos nuevos agregados (sendTemplate con fallback a texto plano, downloadMedia y
   normalizeRecipient como alias de métodos ya existentes).
3. **`MetaCloudApiService`** (nuevo) — driver completo de la API oficial: envío de texto/media/
   plantilla real, verificación de firma HMAC-SHA256 del webhook, verificación del handshake GET
   (hub.challenge), descarga de media en 2 pasos (media id → URL temporal → bytes), parseo del
   payload entrante normalizado a la misma forma que ya usa Evolution.
4. **`WhatsAppMetaWebhookController`** (nuevo) — `GET/POST /whatsapp/webhook/meta/{slug}`,
   agregado a las rutas públicas.
5. **`WhatsAppRouterService`** refactorizado — la lógica de negocio compartida (dedupe, matching
   de contacto, upsert de conversación, publicar eventos) se extrajo a
   `processInboundNormalized()`, alimentada por Evolution Y Meta ya normalizados. El webhook
   EN VIVO de Evolution (`route()`) mantiene exactamente el mismo comportamiento de antes —
   verificado con una prueba transaccional (revertida sola) comparando el resultado contra el
   comportamiento original.
6. **`WhatsAppProviderFactory`** — resuelve Evolution vs Meta según `whatsapp_instances.provider`
   (nueva columna, **por línea**, no un flag global — permite migrar una línea a la vez).
7. **Integration Hub**: nuevo proveedor de catálogo `whatsapp-oficial` + validador real (pide
   `verified_name` a Meta para confirmar que el token y el phone_number_id son correctos).
8. **`config/whatsapp_templates.php`** — catálogo de plantillas proactivas (`payment_reminder`,
   `geofence_alert`) con el texto de referencia a someter a aprobación en Meta Business Manager.

### Bug encontrado y corregido en el camino

`WhatsAppInstance::$fillable` no incluía `provider` — la asignación masiva lo descartaba en
silencio (sin error), así que el factory SIEMPRE resolvía Evolution sin importar lo que se
pidiera. Encontrado al verificar el factory con una prueba real, corregido antes de dar la pieza
por terminada.

### Verificación realizada

- Prueba transaccional (revertida sola, sin dejar datos): un payload Evolution real produce
  exactamente el mismo mensaje/conversación que antes del refactor, dedupe incluido.
- Mismo tipo de prueba con un payload Meta real (texto e imagen): se normaliza y persiste
  correctamente, con el nombre de contacto capturado.
- Handshake y firma HMAC: 5 casos (correcto/incorrecto de cada uno) — todos se comportan como
  deben.
- El factory resuelve la clase correcta por `provider`, verificado en memoria (el camino
  end-to-end con la columna real en la base de datos queda pendiente de que la migración se
  aplique, ver abajo).

### Lo que falta (siguiente fase)

- **Migrar los 15 consumidores reales** de `new EvolutionApiService()` directo a
  `WhatsAppProviderFactory::forInstance()/forDefault()` — sin esto, el proveedor sigue sin ser
  configurable de verdad para el tráfico real, solo la infraestructura ya existe.
- **UI para capturar las credenciales de Meta** (reusa la pantalla de Integraciones ya
  existente, el Hub ya tiene el proveedor catalogado) y para elegir el proveedor por línea en
  el panel de WhatsApp.
- **Las migraciones no se corrieron** — el guardrail de migraciones de este proyecto (item #534)
  bloquea migrar desde una rama que no sea `main` o `circuito/item-N`, y esto es trabajo manual
  en una rama de feature normal. Se verificó con `migrate --pretend` que el SQL generado es
  correcto; correrán solas cuando esta rama se fusione a `main`.
- **Del lado del usuario** (no es código): tramitar la cuenta de Meta Business, verificación de
  negocio, el WABA, y someter a aprobación las plantillas de `config/whatsapp_templates.php`
  antes de poder usarlas de verdad.

### Rama

`feature/whatsapp-api-oficial-meta` en `/var/www/megaisp` — 2 commits (`ae3a38cb`, `e8895806`),
pendiente de revisión y fusión a `main`.

## 2026-10-05 (continuación) — Pasos 1 y 2 completados

Siguiendo el plan en orden, se completaron los dos pasos que sí eran código:

### Paso 1 — Migrar los 10 consumidores reales (commits `a2c562c2`, `8b095285`)

Capa de gateway (`sendAndLog`/`PaymentApplicationService`, conciliación de pagos con dinero
real) y los 10 archivos que mandaban WhatsApp directo (Flotas ×3, Marketing ×3, PortalPago,
Roadmap, WarRoom ×2, Referrals) ahora pasan por el proveedor configurable. Dos patrones según
cómo cada uno obtenía el servicio: instanciación directa → `WhatsAppProviderFactory`; inyección
de dependencias → un binding nuevo del contenedor hacia la interfaz compartida. Dejados sin
tocar, a propósito: 3 archivos que solo formatean un teléfono para mostrarlo en pantalla (no
mandan nada).

Hallazgo en el camino: `EnviarRecurrentesCommand` (el comando de pagos recurrentes) **no** es
el envío masivo de ligas de pago a clientes como se pensaba — es solo el resumen diario interno
a Irving. El envío real a clientes vive en otro canal, fuera de este archivo.

### Paso 2 — UI para credenciales y elegir proveedor por línea (commit `d3d95344`)

- Panel de Integraciones (`/integraciones`, el mismo que ya usan los proveedores de IA):
  campos para capturar Phone Number ID, WABA ID, App Secret del webhook, Verify Token y versión
  de la API — la "API Key" genérica que ya existía se reusa como el access token permanente.
- Panel de líneas de WhatsApp: selector Evolution/oficial en cada línea, con confirmación extra
  si es la línea de producción.

### Paso 3 — Migraciones: pendiente de tu decisión

Las 2 migraciones (columna `provider` + catálogo del Hub) siguen sin correr — el propio
guardrail de migraciones del proyecto (item #534) lo bloquea desde esta rama a propósito, y
fusionar 6 commits que tocan conciliación de pagos no es algo que deba decidir solo. Verificado
con `migrate --pretend` que el SQL es correcto; falta que alguien revise la rama
`feature/whatsapp-api-oficial-meta` y la fusione a `main` (yo puedo hacerlo si me lo confirmas
explícitamente).

### Paso 4 — Fuera de código, es tuyo

Tramitar la cuenta de Meta Business, verificación de negocio, el WABA, y someter a aprobación
las plantillas de `config/whatsapp_templates.php`.

## 2026-09-24 15:35 — MegaVoz: auditoría de provisionamiento + diagnóstico de "web1003 se corta al contestar"

### 1. ¿Todas las extensiones con dueño están conectadas a Asterisk?

Pedido de David: "conecta a Asterisk los que tengan extensiones actualmente, igual los que se
agreguen o se modifiquen, para que no vuelva a pasar esto."

**Auditoría completa (comparando `voip_extensiones` activas contra `pjsip show endpoints`
real):** las 38 extensiones activas de la BD están **las 38** provisionadas en Asterisk — cero
faltantes, cero huérfanas del lado de Asterisk sin fila en BD. No había ningún hueco que
backfillear.

Lo que SÍ estaba roto (y ya se corrigió en el commit anterior de esta misma sesión, ver
`docs/bitacora/2026-09-24-megavoz-fix-asignacion-y-config-ia.md`): que asignar un usuario a una
extensión de escritorio no creaba su gemela WebRTC. Eso ya queda cubierto tanto para lo existente
(backfill manual de las 3 que faltaban: Diana/1003, Diseño/1000, Brandon/1002) como para lo
nuevo/editado (`ExtensionController::store()/update()` ya llama
`asegurarGemelaWebrtc()` en cada guardado).

**Importante — lo que esto NO puede resolver:** que un teléfono/softphone de escritorio (ej.
1003, el aparato físico o app de Diana) aparezca como "Unavailable" en Asterisk no es un
problema de provisionamiento — es que NADIE ha registrado ese dispositivo (nadie inició sesión
con esas credenciales SIP). El sistema ya tiene lista la "puerta" (el endpoint existe y acepta
conexiones); MegaISP no puede forzar a un teléfono físico a conectarse solo — eso requiere que el
aparato/softphone de esa persona esté encendido y logueado con el usuario/password de esa
extensión.

### 2. "web1003 se corta cuando contesta" — investigado, sin repro en vivo esta sesión

Se activó `pjsip set logger on` + verbosidad alta para capturar el próximo intento real, pero no
llegó una llamada nueva en la ventana de espera (solo ruido de escaneo SIP de internet, sin
relación).

**Revisando el código del mini-teléfono (`MegaVozTelefono.vue`) se encontró un hueco real de
diagnóstico** que coincide exactamente con el síntoma descrito: `contestar()` pide el micrófono
en ESE momento (`session.answer({mediaConstraints:{audio:true}})`). Si el navegador de quien
contesta no tiene el permiso de micrófono concedido (nunca se le preguntó, lo negó, o el
navegador descartó el prompt), JsSIP dispara el evento `getusermediafailed` y la llamada muere
ahí mismo — y el componente **no tenía ningún listener para ese evento**, así que quien contesta
solo ve "se cortó" sin ninguna pista de qué pasó. Es el candidato más probable encontrado por
revisión de código para "se corta justo al contestar" (contestar = exactamente el momento en que
se pide el micrófono).

**Fix (diagnóstico, no cura la causa de fondo — un permiso de navegador no se puede forzar desde
el código):**
- `engancharSesion()`: nuevo listener `sesion.on('getusermediafailed', ...)` → mensaje claro "No
  se pudo acceder al micrófono — revisa el permiso en el navegador" en vez de morir en silencio.
- `mensajeDeFallo()`: agregadas las causas `USER_DENIED_MEDIA_ACCESS` (JsSIP a veces reporta el
  mismo fallo por el evento genérico `failed` en vez del dedicado) y `RTP_TIMEOUT` (conexión de
  audio perdida, red inestable) — antes ambas caían al mensaje genérico "No se pudo completar la
  llamada".

### Pendiente — verificación real

No se pudo confirmar en vivo esta sesión (sin acceso a navegador). Próximo intento de Irving/
David a `web1003` debería mostrar AHORA un mensaje específico si la causa es el micrófono — si el
mensaje que aparece es justo ese, confirma la hipótesis y la solución real es que quien conteste
revise/acepte el permiso de micrófono en su navegador para el sitio. Si aparece un mensaje
DISTINTO (o sigue sin decir nada), hace falta seguir investigando con ese dato nuevo.

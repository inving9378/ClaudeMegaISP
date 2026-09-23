## 2026-09-23 14:45 — MegaVoz Fase 2: mini-teléfono WebRTC, con audio real verificado

Implementada y depurada en vivo la Fase 2 del plan MegaVoz: un mini-teléfono en el navegador
(widget flotante) para colaboradores con extensión WebRTC. Sesión larga de depuración en vivo con
David — quedan documentados los 4 bugs reales encontrados, uno por uno.

### Infraestructura

- **Transporte WSS** (`transport-wss` en `pjsip.conf.tpl`): la señalización viaja por el mismo
  servidor HTTP interno que ya usa ARI (`http.conf`, loopback:8088) — `res_pjsip_transport_websocket`
  registra la ruta `/ws` sola, sin puerto nuevo en Asterisk.
- **nginx** (`deploy/nginx-dev-tls.conf` + el archivo vivo en `/etc/nginx/sites-enabled/`):
  `location /ws` con proxy_pass + `Upgrade`/`Connection: upgrade` hacia `127.0.0.1:8088/ws`. Reusa
  el dominio y certificado ya existentes de `dev.meganett.com.mx` — **no se abrió el puerto 8089**
  que mencionaba el plan original; decisión deliberada (evita gestionar un segundo certificado y un
  puerto nuevo expuesto, sin ninguna ventaja técnica real para este caso). Verificado con un
  handshake real de websocket (`101 Switching Protocols`, protocolo `sip`) antes de tocar el resto.
- **"Endpoint doble"**: columna aditiva `es_webrtc` en `voip_extensiones` — la extensión de
  escritorio y su gemela `web{numero}` son dos filas separadas (nunca una sola con dos
  comportamientos), ambas provisionadas por el mismo `provisionarExtension()`, que ahora agrega
  `webrtc=yes` + `media_encryption=dtls` + `dtls_auto_generate_cert=yes` cuando `es_webrtc=true`.
- Dialplan nuevo `_web[1-9]XXX` en `from-internal`, para poder marcar la gemela directo en pruebas
  sin esperar a la cola de la Fase 3.
- `MiTelefonoController::credenciales()` — cada quien pide SUS propias credenciales
  (`auth()->id()`, nunca un id del cliente), sin permiso `voip.*` nuevo.
- Widget montado en `master.blade.php`/`app.js` con el mismo patrón que `jarvis-burbuja`/
  `help-float`: app de Vue propia, fuera de `#init-vue`, para que una llamada activa sobreviva la
  navegación entre pantallas (spa-nav solo desmonta la app principal).

### Los 4 bugs reales encontrados en vivo (por orden de aparición)

1. **Rama del tema Torre nunca mergeada** — un descuido propio: se commiteó por la mañana pero
   nunca se mergeó a `main` antes de seguir con la Fase 1 encima; el sitio servía la versión sin el
   tema durante horas. Mergeado (resolviendo el conflicto en `VoipTroncales.vue` a mano) y
   recompilado.
2. **`markRaw()` faltante en los objetos JsSIP** — `TypeError: 'get' on proxy: property 'uri' is a
   read-only and non-configurable data property...` al intentar llamar. JsSIP.UA/RTCSession traen
   propiedades internas no configurables, incompatibles con el Proxy reactivo que Vue 3 les pone
   encima por vivir en `data()`. Fix: `markRaw()` en los 3 puntos donde se asignan (`this.ua`,
   sesión entrante, sesión saliente).
3. **Audio remoto enganchado en el momento equivocado** — la llamada conectaba (Asterisk mostraba
   el canal activo en `Echo()`), pero nunca sonaba. `engancharAudio()` leía
   `sesion.connection.getReceivers()` una sola vez, en los eventos `accepted`/`confirmed` — si el
   track remoto todavía no estaba enlazado en ese instante exacto (normal mientras ICE sigue
   negociando), el stream quedaba vacío para siempre. Fix: escuchar el evento `'track'` de la
   `RTCPeerConnection` directamente (patrón estándar de WebRTC), no depender de un momento fijo del
   ciclo de vida de la llamada.
4. **ICE deshabilitado globalmente + STUN vacío rompía la inicialización** — con `icesupport=no` en
   `rtp.conf` (correcto ANTES de esta fase, nada usaba WebRTC), la llamada conectaba por SIP pero
   el audio nunca se negociaba — confirmado con `pjsip show channelstats`: 0 paquetes en ambos
   sentidos tras más de un minuto de llamada "activa". Fix en dos pasos: `icesupport=yes`, y
   además `stunaddr=stun.l.google.com:19302` (el log de arranque tenía un ERROR real —
   `Failed to setup recurring DNS resolution of stunaddr ''` — con STUN vacío). Verificado el
   momento exacto en que empezó a funcionar: `pjsip show channelstats` pasó de 0/0 paquetes a
   1611/1611 con jitter y RTT normales, y David confirmó el eco audible.

### Otros hallazgos de la sesión (no bugs de esta fase, pero surgieron en el camino)

- **`rendimiento — bundle de dev sin optimizar**: se estuvo compilando todo el día con `npm run dev`
  (43 MB) para iterar rápido; el sitio se sentía lento. `npm run prod` lo baja a 15 MB — se dejó
  esa versión corriendo al cerrar la sesión de pruebas.
- **MCP de Playwright**: se instaló (`claude mcp add playwright`) a petición de David para
  verificación visual futura — no se pudo usar en esta sesión (los MCP nuevos solo se activan al
  reiniciar la sesión de Claude Code, no en caliente). Queda disponible para la próxima.
- Publicada **V1.41-23.09.2026** a mitad de esta sesión (ver bitácora aparte si aplica) — incluye
  todo lo de main hasta ese punto, incluida la Fase 1 completa y parte de la Fase 2 en curso.

### Verificado

- Handshake WSS real (nginx→Asterisk) antes de tocar el resto: `101 Switching Protocols`.
- Registro real del endpoint WebRTC (probado con cuenta de prueba de David, `david_marsal`,
  extensión `web9001`).
- Llamada de prueba a la extensión de eco (1999) con audio bidireccional real confirmado por
  David ("sí, ya se escucha") y por métricas de Asterisk (1611 paquetes en ambos sentidos, sin
  pérdida).

### Pendiente / próximos pasos

- Corregir el `Playback failed on ... for demo-echotest` (cosmético — el mensaje de bienvenida del
  eco no suena, pero `Echo()` sí funciona bien después) — probablemente falta una variante en
  formato/idioma compatible con opus, o el códec necesita transcodificación que no está disponible.
  No bloqueante, no se investigó a fondo hoy.
- Extender el "endpoint doble" a las extensiones YA reclamadas antes de este fix (solo José David,
  extensión 1201, ya tiene su gemela `web1201` creada retroactivamente hoy mismo).
- Seguir con el resto del plan MegaVoz (Fase 3: cola "Atención a Clientes").

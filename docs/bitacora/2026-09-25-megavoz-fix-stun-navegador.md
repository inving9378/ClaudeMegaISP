## 2026-09-25 05:45 — MegaVoz: "cuando yo llamo, ella no escucha nada" — faltaba STUN del lado del navegador

### Síntoma

David: probando el mini-teléfono desde su laptop — cuando Diana lo llama a él, el audio funciona
perfecto en los dos sentidos. Cuando él la llama a ella, Diana contesta pero no escucha nada.
Asimétrico: depende de quién llama.

### Investigado con evidencia real (captura SIP detallada, `logger add channel`)

Se encontró la llamada real que reprodujo el síntoma (Call-ID `o9f9b9cj0fngrufni90f`, David/web9001
→ Diana/web1003, 05:36:05–05:36:29, colgada por Diana a los ~17s). Los códecs SÍ coincidían
correctamente en ambas piernas (PCMU/ulaw primero en las dos, el fix de ayer sigue funcionando) —
descartado como causa.

Lo que SÍ se encontró: el SDP que ofrece el navegador de David solo trae candidatos ICE de tipo
**`host`** (interfaces de red locales — incluidas direcciones de puente de Docker, `172.21.0.1`,
`172.20.0.1`, `172.17.0.1`, claramente inútiles fuera de su propia máquina) — **cero candidatos
`srflx`** (los que vendrían de consultar un servidor STUN para descubrir la dirección pública real
detrás del NAT).

**Causa:** `MegaVozTelefono.vue` nunca configuraba ningún servidor STUN para el
`RTCPeerConnection` del navegador — ni en `ua.call()` ni en `session.answer()`. Asterisk SÍ tiene
su propio STUN de servidor desde Fase 2 (`rtp.conf`, `stun.l.google.com:19302`) — a esto le
faltaba el equivalente del lado del navegador. Sin STUN propio, el navegador de quien está detrás
de un NAT más estricto solo puede ofrecer candidatos locales inútiles — y como el NAT/red de cada
quien es distinta, el síntoma depende de quién llama (asimétrico), no es un bug binario "funciona/
no funciona".

### Fix

`PC_CONFIG = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] }` (mismo servidor STUN
público que ya usa el lado de Asterisk) pasado como `pcConfig` tanto en `ua.call()` (llamar) como
en `session.answer()` (contestar).

### Pendiente

Confirmación real de David/Irving/Diana llamándose de nuevo tras recargar la página — no se pudo
reproducir en vivo con navegador real esta sesión, el diagnóstico se hizo 100% a partir de la
captura SIP real de la llamada que sí falló.

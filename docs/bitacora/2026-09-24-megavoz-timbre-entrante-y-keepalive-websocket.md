## 2026-09-24 16:10 — MegaVoz: timbre de llamada entrante + keepalive del websocket (causa real de las caídas)

### Contexto

Irving y David probando llamadas reales entre extensiones del mini-teléfono (Irving↔Diana,
1003/web1003). Dos síntomas reportados en la misma sesión de pruebas:
1. "Irving llama y a Diana no le suena nada, solo se le abre el modal."
2. "El web (web1003) se cae a los 2 tonos."

### Hallazgo 1 — llamadas entrantes sin ningún aviso sonoro

`MegaVozTelefono.vue` solo tenía tono sintetizado (Web Audio) para el lado que **llama**
(`iniciarTonoLlamando`, dispara con `data.originator === 'local'`). Para el lado que **recibe**
la llamada no había absolutamente ningún sonido — el modal se abría en silencio total. Si la
persona no estaba viendo la pantalla justo en ese instante, nunca se enteraba.

**Fix:** `iniciarTimbreEntrante()` (nuevo) — mismo mecanismo de síntesis, cadencia de "ring-ring"
(dos ráfagas de 0.4s) distinguible del tono largo de "está timbrando" del que llama. Se dispara
en `newRTCSession` cuando `data.originator !== 'local'`. De paso, `ctx.resume()` agregado a
ambos tonos — algunos navegadores crean el `AudioContext` en estado "suspended" incluso dentro
del mismo gesto de clic, y sin `resume()` explícito el tono queda armado pero mudo, sin ningún
error visible.

### Hallazgo 2 — la causa real de "se cae a los 2 tonos": el websocket se cae solo, todo el tiempo

Se activó captura detallada del lado de Asterisk (`logger add channel megavoz-debug.log
notice,warning,error,verbose` + `pjsip set logger on`) para agarrar el momento exacto de una
caída real. Confirmado: **"Web socket closed abruptly" aparece constantemente en el log durante
TODA la sesión** (cientos de veces), no solo durante llamadas — y en el intento de llamada real
de Irving→Diana, una desconexión de websocket coincidió en el mismo segundo que la llamada
timbrando.

**Causa:** `JsSIP.UA` se configuraba sin `keepalive_interval` — nunca mandaba tráfico de
mantenimiento sobre el websocket. Si algo en el camino (Asterisk, el proxy, la red del
navegador) cierra conexiones sin actividad después de cierto tiempo, la conexión se cae sola
cada 1-2 minutos — y si eso pasa justo mientras una llamada está timbrando o conectando, la
llamada muere con la conexión, sin relación con códecs, micrófono, ni nada del lado SIP.

**Fix:** `keepalive_interval: 30` en la configuración de `JsSIP.UA` — mantiene el socket con
actividad regular cada 30s, muy por debajo de cualquier timeout de inactividad razonable
(nginx para `/ws` tiene `proxy_read_timeout 3600s`).

### ⚠️ Importante para la próxima prueba

Este fix vive en el JS que carga el navegador al abrir la página — **quien pruebe necesita
recargar la pestaña (F5) para que tome el cambio nuevo**, no basta con que el servidor ya lo
tenga compilado. Si Diana/Irving prueban sin recargar, seguirán con el JsSIP viejo sin
keepalive y el síntoma seguiría igual.

### Verificado

- `npm run dev` compila sin errores en los dos commits.
- Captura SIP detallada confirmó la coincidencia temporal websocket-caída ↔ llamada-cortada
  (evidencia directa, no solo hipótesis).
- **Pendiente: confirmación de Irving/David con una prueba real después de recargar la
  página** — no se pudo verificar en vivo esta sesión por falta de navegador.

### Limpieza pendiente

El canal de log adicional (`megavoz-debug.log`, agregado en caliente vía `logger add channel`,
no persiste en `logger.conf` — se pierde solo al reiniciar Asterisk) sigue activo para poder
verificar la próxima prueba. Quitar con `logger remove channel megavoz-debug.log` +
`pjsip set logger off` una vez confirmado el fix.

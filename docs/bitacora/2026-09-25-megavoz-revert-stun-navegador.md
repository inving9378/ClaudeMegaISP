## 2026-09-25 06:05 — MegaVoz: revertido el STUN del lado del navegador — regresión real en celular

### Contexto

El fix anterior de esta misma mañana (`docs/bitacora/2026-09-25-megavoz-fix-stun-navegador.md`)
agregó un servidor STUN público (`stun.l.google.com:19302`) al `RTCPeerConnection` del navegador,
para resolver "cuando David llama, Diana no escucha nada" (diagnosticado con evidencia real: el
SDP de David solo traía candidatos ICE locales, sin ninguno de STUN).

### Regresión encontrada en vivo, probando desde celular

Tras desplegar el fix, David reportó: en el celular ya no se escucha ningún tono, la llamada
tarda casi un minuto en aparecer como entrante en su sesión, y al contestar también tarda
demasiado y se cae. Esto es EMPEORAR respecto a como estaba antes — una prueba anterior confirmó
explícitamente que el celular funcionaba bien (esa prueba fue lo que aisló que el problema de
"música de espera" era solo un artefacto de probar dos llamadas desde el mismo dispositivo/
laptop, no un bug real).

### Hipótesis de la causa (no confirmada con evidencia directa, revertido por precaución)

Las redes celulares suelen usar NAT de operador (carrier-grade NAT), donde un STUN simple a
veces no basta para descubrir una ruta utilizable — y mientras el navegador espera la respuesta
del servidor STUN (o agota su tiempo de espera), el armado de la llamada se congela. Antes de
este fix, sin ningún STUN configurado, el navegador solo reunía candidatos locales al instante
(sin esperar ninguna red) — más frágil para el caso original (laptop en NAT normal), pero sin
este nuevo retraso.

### Revertido

Se quitó `PC_CONFIG`/`pcConfig` de `ua.call()` y `session.answer()` en `MegaVozTelefono.vue` —
vuelve exactamente al comportamiento de antes de esta mañana (solo candidatos locales, sin STUN
propio del navegador).

### Pendiente — el problema original ("cuando llamo yo, no escucha nada") sigue sin resolver

Queda documentado el diagnóstico real (SDP con solo candidatos `host`, sin `srflx`) para cuando
se retome. Una solución más robusta necesitaría probablemente:
- Un servidor **TURN** (relay), no solo STUN — el candidato estándar para NAT de operador
  celular que STUN solo no puede resolver.
- O, si se insiste en usar solo STUN, limitar el tiempo de espera de reunión de candidatos ICE
  (timeout corto) para que un STUN lento/inalcanzable no bloquee el armado de la llamada — cae a
  candidatos locales si STUN no responde a tiempo, en vez de esperar casi un minuto.

No se debe reintentar el fix de STUN sin antes validar en un celular real que el tiempo de
armado de la llamada sigue siendo rápido.

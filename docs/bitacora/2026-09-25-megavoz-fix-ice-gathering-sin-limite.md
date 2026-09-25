## 2026-09-25 09:30 — MegaVoz: la causa real de las llamadas que "se caían" al contestar — JsSIP espera la reunión de rutas ICE SIN límite de tiempo

### El síntoma final (tras el TURN + limpiar el STUN redundante)

Con el servidor TURN (coturn) ya funcionando y sin el STUN de Google duplicado, las
llamadas conectaban rápido — pero al contestar, la llamada se seguía cayendo. El
patrón que reportó David en vivo: "no me demoré ni 2 seg en marcar y contestar y se
volvió a caer la llamada al tiempo, es como si faltara algo por enviar al server
para que sepa que ya está atendiendo la llamada".

### Diagnóstico

1. Se subió el timbrado de 30s a 60s (temporal, solo para tener más margen y
   confirmar si era cuestión de tiempo). Mismo resultado: se caía igual.
2. Se leyó el log de Asterisk con `pjsip set logger on` para el tramo exacto entre
   el `180 Ringing` y el corte: **silencio total** del lado de quien contestaba — ni
   un solo mensaje SIP, ni siquiera tarde. La llamada terminaba en `603 Decline` /
   `CANCEL` por el propio timeout de `Dial()`, no por un rechazo explícito.
3. Se leyó el código fuente real de la librería (`node_modules/jssip/lib/RTCSession.js`,
   versión 3.13.8 — la que usa el proyecto) en vez de adivinar. Confirmado:
   `_createLocalDescription()` espera a que el navegador declare la reunión de
   candidatos ICE como **"complete"** antes de mandar CUALQUIER respuesta SIP —
   **sin ningún timeout propio**. Si esa reunión se traba (red lenta llegando al
   TURN, o el navegador nunca la marca "completa"), el que contesta se queda
   literalmente sin mandar nada, sin importar cuánto se suba el timbrado.

### El arreglo

JsSIP expone la salida para esto: en cada candidato encontrado emite un evento
`icecandidate` con `{candidate, ready}` — llamar `ready()` apura la respuesta con lo
que se tenga hasta ese momento, sin esperar a que termine de reunir todo.

`MegaVozTelefono.vue::engancharSesion()` ahora escucha ese evento y llama
`ready()`:
- de inmediato en cuanto aparece una ruta ÚTIL (`srflx` o `relay` — la que sirve
  para conectar fuera de la LAN), o
- como respaldo, a los 3 segundos con lo que haya, si ninguna ruta útil apareció
  todavía.

Se aplica igual para quien llama y quien contesta (mismo método, mismo evento).

### Verificado en vivo (David + Diana, con audio real)

- David llama a Diana, contesta al toque → conecta en pocos segundos, audio
  limpio en los dos lados.
- Diana llama a David (`web9001`) → `180 Ringing` a `200 OK` en **8 segundos**,
  audio limpio confirmado por ambos ("se escuchó bien en los lados").

### Se revirtió el timeout de diagnóstico

El timbrado interno (`_web[1-9]XXX` en `extensions.conf.referencia`) había
quedado en 60s solo para tener margen mientras se diagnosticaba. Ya no hace
falta — regresado a 30s (su valor original) una vez confirmado el fix real.

### Commits

- `0e990567` — sube el timbrado a 60s (diagnóstico)
- `40c2fd95` — el fix real: acota la reunión ICE con `ready()`
- `90dc2b31` — revierte el timbrado a 30s

### Pendiente (no bloqueante, ya anotado antes)

- Puerto 5349/TLS de coturn sigue sin escuchar (permiso de lectura del
  certificado para el usuario `turnserver`) — ver
  `docs/bitacora/2026-09-25-megavoz-turn-coturn.md`.
- Credenciales TURN estáticas — mover a credenciales efímeras (TURN REST API)
  es mejora futura, no urgente para uso interno.

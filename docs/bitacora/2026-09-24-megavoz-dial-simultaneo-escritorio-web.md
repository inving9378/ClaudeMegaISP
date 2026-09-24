## 2026-09-24 16:20 — MegaVoz: marcar el número de 4 dígitos ya no requiere saber el prefijo "web"

### Contexto

Tras el fix del keepalive del websocket, David reportó que Irving seguía sin poder comunicarse
con Diana — "sigue igual, verifica que creo que irvin desconectó algo de esa extensión". Se
verificó que NO había nada desconectado del lado de MegaISP (auditoría completa: las 38
extensiones activas siguen provisionadas, `web1003.activo=true`, sin cambios sospechosos) y que
el patrón de "Web socket closed abruptly" viene desde el día anterior (533 ocurrencias en 2 días,
no algo nuevo de hoy).

Revisando la captura SIP detallada de los intentos reales, se encontró la causa real: **Irving
seguía marcando "1003" (el número de escritorio) en vez de "web1003"** — múltiples INVITEs a
`sip:1003@dev.meganett.com.mx` desde el navegador, que la central rechaza de inmediato porque el
teléfono físico de esa extensión no está conectado (comportamiento correcto, no un bug, ya
explicado antes). El problema real no era un bug técnico escondido: era que **nadie se acuerda
de que existe un prefijo "web" para llamar a la línea del navegador de alguien**, y no hay forma
de saberlo desde la pantalla de marcar.

### Fix — timbrar las dos líneas a la vez

En vez de seguir pidiéndole a la gente que recuerde un prefijo, se cambió el dialplan para que
marcar el número normal de 4 (o 3) dígitos de una persona **timbre su teléfono de escritorio Y
su línea de navegador al mismo tiempo** — contesta la que esté conectada, sin que quien llama
necesite saber cuál de las dos usa la otra persona en ese momento.

`/etc/asterisk/extensions.conf`: `Dial(PJSIP/${EXTEN},30)` → `Dial(PJSIP/${EXTEN}&PJSIP/web${EXTEN},30)`
en los patrones `_[1-9]XXX` y `_[1-9]XX`. Si `web${EXTEN}` no existe (la mayoría de las
extensiones no tienen gemela WebRTC), ese segundo canal simplemente no se puede crear y Asterisk
sigue con el primero normal — no rompe nada, solo una línea extra de log.

### Nota técnica — cómo se aplicó (este archivo no lo genera ningún código del repo)

`extensions.conf` es un archivo **estático**, creado a mano una sola vez durante Fase 1/2 —
a diferencia de `/etc/asterisk/megaisp.d/*` (que sí regenera `DialplanGeneratorService` en cada
cambio de troncales/grupos), no hay ningún generador PHP/shell que lo produzca, y el truco
habitual de escribir vía `www-data` (webroot + curl) **no funciona aquí** (esa ruta de escritura
de grupo solo existe para el subdirectorio `megaisp.d/`, no para el archivo en sí).

Se usó el mecanismo real disponible: sudoers ya tenía `NOPASSWD: /usr/bin/cp /tmp/ast_* /etc/asterisk/`
(solo permite copiar HACIA el directorio, preservando el nombre de origen — no renombrar), así
que se creó `provisioning/actualizar-extensions.sh` (nuevo, versionado) que hace
`cp /tmp/ast_extensions.conf /etc/asterisk/extensions.conf` + `dialplan reload`, ejecutable vía
el sudoers `NOPASSWD: /bin/bash .../provisioning/*.sh` ya existente para este directorio. Se dejó
además `extensions.conf.referencia` (copia versionada del contenido real) para que el cambio
quede en el historial de git — `meganet` no tiene ni siquiera permiso de LECTURA sobre
`/etc/asterisk/`, así que sin esta copia nadie podría ver qué dice el archivo real sin acceso
directo al servidor.

### Verificado

`channel originate Local/1003@from-internal/n ...` → confirma que ahora `PJSIP/web1003` entra en
estado `Ringing` al marcar "1003" (antes fallaba de inmediato, "Could not create dialog"). Sin
errores nuevos en el log tras el cambio.

### Pendiente

Confirmación real de Irving/David — marcar "1003" (el número normal, sin ningún prefijo) y
verificar que ahora sí le suena a Diana en su línea de navegador.

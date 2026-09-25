## 2026-09-25 07:45 — MegaVoz: servidor TURN (coturn) para el mini-teléfono

### Contexto

Dos reversiones seguidas de un STUN público simple (`docs/bitacora/2026-09-25-megavoz-revert-stun-navegador.md`
y `-2.md`): agregarlo arregla el audio asimétrico (confirmado con evidencia directa —
`rtp set debug on` mostró a Asterisk mandando audio a una IP privada de David sin STUN), pero
provoca que la llamada se cuelgue/retrase en su red — confirmado que NO era la red bloqueando
STUN (una página de prueba dedicada, publicada en `public/herramientas/stun-test.html`, mostró
que tanto su laptop como su celular SÍ consiguen un candidato público (`srflx`) sin problema).
El fallo está un nivel más adentro (verificación de conectividad ICE entre el navegador y
Asterisk, no el simple descubrimiento de la IP pública) — STUN solo no alcanza a resolverlo de
forma confiable.

### Solución — TURN (coturn), instalado por David (acceso de administrador del servidor)

David instaló y configuró **coturn** directamente en el servidor (`38.123.192.199`, el mismo
dominio `dev.meganett.com.mx`), escuchando en el puerto 3478 (TCP+UDP), verificado activo. El
puerto 5349 (TLS) no quedó activo (permiso del certificado, pendiente) — no bloqueante para
probar si esto resuelve el problema real.

### Cableado en MegaISP

- **`.env`** (no versionado): `MEGAVOZ_TURN_URL`, `MEGAVOZ_TURN_USERNAME`, `MEGAVOZ_TURN_PASSWORD`.
- **`config/voip.php`** → nueva sección `'turn'` (`url`/`username`/`credential`, vía `env()`).
- **`MiTelefonoController::credenciales()`** → agrega `turn_url`/`turn_username`/`turn_credential`
  a la respuesta — **la contraseña nunca queda hardcodeada en el `.vue`** (violaría la
  convención de secretos-solo-en-.env de este repo): viaja por el mismo endpoint que ya manda el
  `secret` SIP de cada extensión, mismo criterio de "cada quien pide lo suyo, nunca lo de otro"
  (`auth()->id()`).
- **`MegaVozTelefono.vue`** → `iniciarUA(cred)` arma `this.pcConfig` dinámicamente: STUN público
  (Google, liviano, para cuando alcanza) + el TURN nuevo si `cred.turn_username` viene poblado
  (si el backend no tiene TURN configurado, cae solo a STUN — no rompe nada). `llamar()` y
  `contestar()` usan `this.pcConfig` en vez de una constante fija.

### De paso — herramienta de diagnóstico dejada en el repo

`public/herramientas/stun-test.html` — página standalone (sin login, servida directo por nginx)
que arma un `RTCPeerConnection` de prueba con el STUN de Google y muestra en vivo qué tipo de
candidatos ICE encuentra el navegador (host/srflx/relay). Se usó para descartar "la red bloquea
STUN" como causa — útil para el futuro si vuelve a haber dudas de conectividad de red desde el
navegador de alguien.

### Verificado

- `npm run dev` compila sin errores.
- `MiTelefonoController::credenciales()` probado por tinker — devuelve `turn_url`/`turn_username`/
  `turn_credential` correctamente poblados.
- coturn confirmado escuchando en 3478 (TCP+UDP) en la IP pública.

### Pendiente

- **Confirmación real de David/Diana llamándose de nuevo** — no se pudo probar en vivo desde
  esta sesión (cableado recién terminado).
- Arreglar el permiso del certificado para que el puerto 5349 (TLS) también quede activo — no
  bloqueante para la prueba inicial, pero mejor tenerlo si acaba funcionando bien por 3478 solo.
- Si TURN funciona pero se quiere cerrar mejor la seguridad más adelante: credenciales de TURN
  de vida corta por sesión (patrón "TURN REST API") en vez de la fija actual — la fija es
  razonable para uso interno con este volumen, pero queda como mejora futura anotada.

## 2026-10-06 — MegaVoz: confirmado — el TURN (coturn) sí resolvió el audio en redes restrictivas

Seguimiento de `docs/bitacora/2026-09-25-megavoz-turn-coturn.md`. Esa sesión dejó el servidor
TURN propio (coturn) instalado y cableado al mini-teléfono WebRTC, pero sin poder probarlo en
vivo ("Confirmación real de David/Diana llamándose de nuevo — no se pudo probar en vivo desde
esta sesión, el cableado recién terminado"). El problema original ("cuando llamo yo, no se
escucha nada", reproducido con el celular y la laptop de David en redes con NAT más
restrictivo) quedó registrado como sin cerrar en varias bitácoras seguidas
(`fix-stun-navegador.md`, `revert-stun-navegador.md`, `revert-stun-navegador-2.md`,
`fix-ice-gathering-sin-limite.md`).

Irving confirma hoy que **ya se corrigió al cambiar a TURN** — el síntoma de audio roto en
redes restrictivas no ha vuelto a presentarse desde entonces.

Verificado en vivo en esta sesión (solo lectura, sin tocar nada): `coturn.service` activo
desde el 2026-09-25 08:32 (una semana y 3 días corrido sin caerse) y escuchando en el puerto
3478 (TCP+UDP) en la IP pública. El puerto 5349 (TLS) sigue sin levantar — mismo pendiente
menor ya anotado en la bitácora original ("no bloqueante para la prueba inicial") — y no hizo
falta para que el fix funcionara.

### Pendiente que queda cerrado con esto

- ~~Confirmación real de David/Diana llamándose de nuevo tras el cableado de TURN~~ → confirmado
  por Irving.
- ~~"El problema original... sigue sin resolver"~~ (nota en `revert-stun-navegador.md`) → resuelto.

### Pendiente que sigue abierto, sin cambio

- Puerto 5349/TLS de coturn sigue sin escuchar (permiso del certificado para el usuario
  `turnserver`) — cosmético/robustez, no bloqueante.
- Credenciales de TURN de vida corta por sesión (patrón "TURN REST API") en vez de la fija
  actual — mejora futura anotada, no urgente para el volumen de uso interno actual.

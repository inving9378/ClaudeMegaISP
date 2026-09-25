## 2026-09-25 07:10 — MegaVoz: STUN revertido por SEGUNDA vez — confirmado, no era coincidencia

### Contexto

Tras la primera reversión de esta mañana, se encontró evidencia DIRECTA (no solo sospecha) de
que la falta de STUN del lado del navegador es la causa real del audio asimétrico: con
`rtp set debug on` sobre una llamada real en curso (David → Diana), Asterisk mandaba el audio a
`192.168.1.109` — una IP **privada** de la laptop de David, inalcanzable desde internet — mientras
sí recibía correctamente desde su IP pública real. Con esa evidencia, y con la confirmación
explícita de David de reintentar con cuidado, se volvió a aplicar el mismo `pcConfig` con
`stun.l.google.com:19302`.

### Resultado — se repitió el mismo problema, confirmando que NO fue coincidencia

David probó de nuevo (recargó ambos dispositivos, llamó desde su celular) y reportó exactamente
el mismo síntoma que la primera vez: la llamada se demora en conectar. Dos pruebas independientes
con el mismo resultado descartan que haya sido un evento aislado — el STUN público simple
genuinamente rompe la conectividad rápida en la red celular que está usando.

### Revertido de nuevo

Mismo cambio que la primera reversión — se quitó `PC_CONFIG`/`pcConfig` de `ua.call()` y
`session.answer()`. **No se debe reintentar esta misma solución sin cambiar el enfoque** — ya se
probó dos veces con el mismo resultado negativo en celular.

### Diagnóstico consolidado (las dos mitades del problema, ambas con evidencia real)

1. **Sin STUN:** el navegador solo ofrece candidatos ICE locales (`host`) — sin ninguno público,
   Asterisk no tiene a dónde mandar el audio real y lo manda a una IP privada inútil. Esto rompe
   el audio cuando David llama desde su laptop (confirmado con `rtp set debug on`).
2. **Con STUN simple (`stun.l.google.com:19302`):** el navegador SÍ consigue un candidato
   público, pero en la red celular de David la reunión de candidatos ICE se cuelga o tarda casi
   un minuto — probablemente porque un STUN simple no atraviesa el NAT de operador celular
   (carrier-grade NAT), un caso conocido en WebRTC que normalmente requiere un servidor **TURN**
   (relay), no solo STUN.

### Siguiente paso real — decisión pendiente, no es un fix de código rápido

La solución técnica estándar para este caso (STUN funciona en unas redes, falla en otras según
el tipo de NAT) es correr un servidor **TURN** propio (ej. `coturn`) — trabajo de
**infraestructura** (instalar el servicio, abrir puertos, configurar credenciales), no un ajuste
de una línea de código como los intentos de hoy. Eso requiere privilegios de servidor que están
fuera de lo que se puede aplicar solo con los permisos actuales de esta sesión.

**Mientras tanto, límite real y conocido del sistema:** el mini-teléfono funciona bien cuando
Diana llama a David (confirmado), pero el audio falla de forma intermitente/dependiente-de-red
cuando David llama desde una red con NAT más restrictivo (su laptop, y ahora confirmado también
su celular). No es un bug que un ajuste más de código vaya a resolver sin la pieza de
infraestructura (TURN).

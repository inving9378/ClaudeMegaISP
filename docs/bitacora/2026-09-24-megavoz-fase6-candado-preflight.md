## 2026-09-24 14:00 — MegaVoz Fase 6: bug de seguridad real encontrado y corregido antes de dejar el motor conectado

### Contexto

Justo después de conectar el motor de voz de María a la cola real (`grupo-1`, commit
`f214c830`/`9e2a1db4`, con `piloto_porcentaje=0` como configuración "más segura posible" — el
daemon siempre declina), se probó el escenario "el daemon no está corriendo" — el caso más
probable de encontrarse en la vida real (el comando `voip:bot-voz-escuchar` todavía no tenía
servicio persistente, ver pendiente en `docs/bitacora/2026-09-24-megavoz-fase6-motor-ia-voz.md`).

### El bug

`channel originate Local/s@grupo-1/n application Wait 10` con el daemon apagado: el canal
desaparecía casi de inmediato, sin ningún canal visible en `core show channels concise`, y —
la evidencia decisiva — **sin ningún `ENTERQUEUE` en `/var/log/asterisk/queue_log`**. El log de
Asterisk mostraba:

```
WARNING res_audiosocket.c: Connecting to '127.0.0.1:9099' failed ... Conexión rehusada
ERROR   res_audiosocket.c: Failed to connect to AudioSocket service
```

El diseño asumía que `AudioSocket()` "regresaba" con normalidad ante un fallo de conexión y el
dialplan seguía a la siguiente prioridad (el `GotoIf(QUEUE_MEMBER...)` de la regla de oro) — la
misma suposición documentada (erróneamente) en el comentario original de
`DialplanGeneratorService::buildColaExten()`.

### Causa raíz (confirmada contra el código fuente real de Asterisk)

`apps/app_audiosocket.c` (rama `master`, GitHub):

```c
if ((s = ast_audiosocket_connect(args.server, chan)) < 0) {
    /* The res module will already output a log message, so another is not needed */
    return -1;
}
```

Un valor negativo devuelto por una app de dialplan es la convención clásica de Asterisk para
**colgar el canal**, no "seguir a la siguiente prioridad" — a diferencia de un cierre remoto
ordenado del socket (que SÍ se detecta como hangup limpio dentro de `audiosocket_run()` y
devuelve `0`, "normal exit" — ese camino, el de "el daemon está corriendo y decide declinar",
siempre fue seguro). El riesgo real era exclusivamente "el daemon no está corriendo en
absoluto" — exactamente el estado en que estaba el sistema en ese momento, con
`ia_bot_config.enabled=true` ya viviendo en la BD de dev y el dialplan real de `grupo-1` (el
mismo que ya recibe el troncal real de Irving) con el `AudioSocket()` sin protección.

### Mitigación inmediata

Antes de investigar más a fondo: `ia_bot_config.enabled` se puso en `false` y se regeneró +
recargó el dialplan real, sacando `AudioSocket()` del flujo de clientes reales mientras se
diseñaba el fix — ningún cliente real estuvo expuesto a este bug durante la investigación (el
único canal que lo sufrió fue el `channel originate` de prueba, sin cliente real detrás).

### Fix — candado de pre-vuelo

`DialplanGeneratorService::buildColaExten()`: antes de `AudioSocket()`, cuando el bot está
habilitado, se antepone:

```
TrySystem(nc -z -w1 {host} {puerto})
GotoIf($["${SYSTEMSTATUS}" != "SUCCESS"]?sigue_bot)
Set(BOT_UUID=${UUID()})
AudioSocket(${BOT_UUID},{host}:{puerto})
(sigue_bot) NoOp(...)
```

`TrySystem` (a diferencia de `AudioSocket`) es exactamente la app correcta para esto — por
diseño de Asterisk, **nunca** cuelga el canal pase lo que pase con el comando que ejecuta, solo
reporta el resultado en `${SYSTEMSTATUS}` (`SUCCESS`/`FAILURE`/`APPERROR`). `nc -z -w1` ya viene
instalado en el servidor (OpenBSD netcat), confirmado con pruebas directas (exit 0 con puerto
abierto, exit 1 con puerto cerrado).

### Verificación (con Asterisk real, misma metodología que detectó el bug)

1. **Daemon apagado** — `channel originate` contra `grupo-1` con el fix aplicado:
   `core show channels concise` mostró el canal en `Queue(cola_1,...)`, con agentes
   (`PJSIP/1001`, `PJSIP/1003`) timbrando de verdad, y `queue_log` mostró `ENTERQUEUE` — la
   llamada llega a la cola real, exactamente el comportamiento correcto.
2. **Daemon corriendo, piloto=0** (`voip:bot-voz-escuchar` arrancado a mano) — mismo originate:
   el daemon logueó `"fuera del piloto/horario, se declina"`, `AudioSocket()` regresó normal, y
   de nuevo `ENTERQUEUE` apareció en `queue_log`. Confirma que el camino "daemon vivo mata
   piloto=0" seguía siendo seguro como se creía.
3. Efecto colateral observado (inofensivo, documentado): el propio `TrySystem(nc -z ...)` abre y
   cierra una conexión TCP real al puerto del daemon como parte de la prueba — el daemon lo
   loguea como `"no llegó UUID — cerrando"`. Es ruido esperado del candado, no un error; no crea
   ninguna fila en `ia_bot_conversations` (el comando retorna antes de crear el registro cuando
   no llega UUID).

### Estado final dejado en dev

- `ia_bot_config.enabled=true`, `piloto_porcentaje=0` (sin cambio de comportamiento para
  clientes reales — nadie habla con María todavía, es decisión pendiente de Irving/David).
- Dialplan real de `grupo-1` regenerado y recargado con el candado de pre-vuelo.
- `voip:bot-voz-escuchar` sigue sin servicio persistente — se agregó
  `deploy/megaisp-bot-voz.service` + `deploy/README-bot-voz.md` (mismo patrón que
  `flotas:gps-listen`/`cobranza:ami-listener`), **no instalado** (requiere systemd, sin
  privilegios sudo para eso desde esta sesión). El candado de pre-vuelo hace que sea seguro
  tener `enabled=true` en el dialplan real INCLUSO sin este servicio corriendo — antes de este
  fix, no lo era.

### Pendiente

- Instalar `megaisp-bot-voz.service` (Irving, systemd real).
- Decidir y subir `piloto_porcentaje`/horario cuando estén listos para que María atienda
  llamadas reales.

## 2026-09-24 06:45 — MegaVoz Fase 3: cola real "Atención a Clientes" + registro de llamadas + grabación

Continuación directa con David (sesión que arrancó el 2026-09-23 con Fase 1/2 de MegaVoz).
Instrucción explícita de David, plan-inversor: **el asistente SIEMPRE contesta primero**,
haya o no agente libre — nunca se timbra directo. Solo se pasa a un humano si el que
llama lo pide. Por ahora el "asistente" es un contestador de relleno (`Playback(beep)`);
la IA de voz real en tiempo real es Fase 6, todavía no construida.

### Cola real (grupo de timbrado con `es_cola=true`)

- Migración `es_cola` sobre `voip_grupos_timbrado` (reusa el concepto existente en vez
  de crear uno paralelo). `GrupoTimbrado::nombreCola()` = `cola_{id}`.
- `extconfig.conf.tpl` — faltaban las 3 líneas que mapean `queues`/`queue_members`/
  `queue_rules` a realtime (mismo gap de patrón que tuvieron las registraciones de
  troncal esa mañana: la tabla existe con el esquema correcto, pero Asterisk nunca la
  lee sin el `#include`/mapeo).
- `AsteriskProvisioningService::provisionarCola()` — upsert de la cola + sus miembros
  (`PJSIP/{numero}`), `joinempty=no`/`leavewhenempty=no` como candado extra de
  Asterisk (segunda capa, por si el chequeo del dialplan se saltara).
- `DialplanGeneratorService::buildColaExten()` — la regla de oro:
  `Answer()` → `Playback(beep)` (relleno) → `GotoIf($[${QUEUE_MEMBER(cola,ready)}>0]?con_agente:sin_agente)`
  → `Queue(...)` o fallback. **`ready`, no `logged`** — `logged` solo cuenta miembros
  configurados sin importar si su teléfono está de verdad conectado; con eso la regla
  habría dado luz verde aunque nadie pudiera contestar. Verificado en vivo con los
  teléfonos Grandstream reales de Irving (1001) y Diana (1003): "ready" refleja
  disponibilidad real (`ps_contacts`/`pjsip show endpoint` confirmaron sus contactos
  vivos, `queue show cola_1` los mostró "Not in use" = listos).
- Dos permisos más de la misma familia del bug de la mañana (directorio 770 no basta,
  el ARCHIVO pre-creado necesita su propio bit de escritura de grupo): corregido
  permanente en `provisionar-asterisk.sh` (`chmod -R g+w` sobre `megaisp.d/`).

### Registro de llamadas (`voip_llamadas`) + grabación (MixMonitor)

Ya con David aprobado explícito ("sigue con eso, ya después vemos retención").

- **Dónde vive la tabla — la decisión que casi se pasa por alto:** `voip_llamadas`
  tiene que estar en la conexión `asterisk_rt` (la base física "asterisk", la misma
  que `ps_endpoints`/`queues`), **no** en la base del app (`megaisp`). Asterisk solo
  puede escribir CDR en la base a la que apunta el DSN de `res_odbc.conf` — si la
  tabla viviera en `megaisp`, el motor de CDR simplemente no la vería. Migración +
  modelo `Llamada` con `protected $connection = 'asterisk_rt'`.
- Plantillas nuevas `cdr.conf.tpl` (`unanswered=yes` — la cola sin agente libre nunca
  se contesta y es justo el caso que el registro debe poder mostrar) y
  `cdr_adaptive_odbc.conf.tpl`. Las recoge solo `GeneradorConfigAsterisk` (ya barre
  `*.tpl` sin necesidad de tocar el script bash para el mapeo top-level).
- Global `GRABACIONES_DIR` en `extensions.conf.tpl` + `Set(CDR(grabacion)=${UNIQUEID}.wav)`
  + `MixMonitor(${GRABACIONES_DIR}/${UNIQUEID}.wav)` justo después de `Answer()`, antes
  del contestador — cubre la llamada completa. WAV, no MP3: el servidor no trae
  compilado `format_mp3` (verificado, `core show file formats`); si se quiere MP3,
  conversión aparte sobre el archivo ya cerrado con ffmpeg (ya presente para Marketing).
- `preparar_grabaciones_dir()` en el script de provisión: `/var/lib/megaisp/grabaciones`,
  `asterisk:asterisk` + `2770` (setgid). **No hizo falta un grupo nuevo** — `www-data`
  ya es miembro del grupo `asterisk` desde MegaVoz Fase 0/1, así que el mismo patrón de
  `megaisp.d/` sirve tal cual para que el purgador (`megavoz:purgar-grabaciones`, cron
  diario 03:00) pueda leer/borrar sin tocar nada de Asterisk.
- `megavoz:purgar-grabaciones` — retención por ANTIGÜEDAD (no por conteo, a diferencia
  de `backups:purge-test`), dry-run por defecto, guard de path duro. Borra el `.wav`,
  **conserva la fila** de `voip_llamadas` (solo limpia `grabacion`) — el registro de la
  llamada es dato de negocio, no debe desaparecer porque expiró el audio.

### El bug real de la tarde — `cdr_adaptive_odbc.conf` necesitaba `connection=`

Tras dejar todo provisionado y correr una llamada de prueba, `voip_llamadas` se quedaba
en 0 filas — pero `MixMonitor` SÍ grababa bien, y `cdr show status` SÍ listaba "Adaptive
ODBC" como backend registrado. Parecía todo correcto y no lo estaba.

Costó encontrarlo porque ningún canal corto (`asterisk -rx`, `cdr show active`, consola
remota vía `-rvvvvv` en background — poco fiable, capturaba a veces sí y a veces no)
mostraba el error. Apareció solo al leer `/var/log/asterisk/messages.log` directo (legible
por `www-data`, mismo grupo `asterisk`, vía el truco ya establecido de script en
`public/` bootstrapeando Laravel):

```
WARNING[...] cdr_adaptive_odbc.c: No connection parameter found in 'asterisk'.  Skipping.
```

La plantilla tenía `[{{DB_NAME}}]` (= `[asterisk]`) como sección, asumiendo —por
analogía con `res_odbc.conf`/`extconfig.conf`, donde el nombre de sección SÍ es la
clase ODBC— que el nombre de sección bastaba. Pero en `cdr_adaptive_odbc.conf` la
sección es un identificador propio del archivo, y el módulo exige el parámetro
`connection = <clase>` explícito dentro. Sin él, el módulo carga bien, se registra como
backend, y cada llamada se descarta en silencio — ni una sola línea en el log al momento
del hangup, solo esa advertencia UNA VEZ al arrancar.

Fix: `[voip_llamadas]` (identificador propio) + `connection = {{DB_NAME}}` +
`table = voip_llamadas`. Verificado end-to-end tras el fix: llamada de prueba a la
extensión de eco (1999) y a la cola real (`grupo-1`, con el teléfono real de Diana
sonando y colgado antes de contestar) — ambas escribieron su fila con
`disposition`/`duration`/`billsec` correctos, y la de la cola ligó `grabacion` al
`.wav` real. Datos de prueba (filas + archivos) limpiados antes de cerrar.

### Pendiente

- Retención — David: "ya después vemos retención" (el mecanismo ya existe y corre a
  las 03:00, falta decidir/confirmar el valor real de `voip.grabaciones.retencion_dias`,
  hoy 90 días por default).
- Vista/UI para consultar `voip_llamadas` desde el admin (historial de llamadas) —
  no pedida todavía, la tabla y el modelo `Llamada` ya están listos para eso.
- Fase 6 (IA de voz real conectada donde hoy está `Playback(beep)`) sigue sin construir.

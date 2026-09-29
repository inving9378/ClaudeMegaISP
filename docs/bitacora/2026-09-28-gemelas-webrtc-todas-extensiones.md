## 2026-09-28 05:41 — Gemela WebRTC automática para toda extensión (con o sin dueño) + backfill

**Pedido de Irving:** que TODA extensión (no solo las que ya tienen un colaborador
asignado) reciba automáticamente su gemela WebRTC `web{numero}`, verificar que
una extensión nueva (ej. crear `1100`) genere `web1100` sola, y reverificar el
Paso 2 del setup de TURN (`MEGAVOZ_TURN_*` en `.env`).

### Hallazgo de partida

`ReclamadorExtensionAutomatico::crearGemelaWebrtc()` exigía un `User $user` no
nulo, y `ExtensionController::asegurarGemelaWebrtc()` retornaba temprano si la
extensión no tenía `user_id`. Resultado en producción: de las 31 extensiones
existentes, **0 tenían gemela web** — ni siquiera 1002/1003/1004/1011, que sí
tienen dueño real (Diseño, Diana, Alondra, Celular Pagos) y fueron asignadas
antes del fix del 24-sep que solo cubre altas/ediciones NUEVAS, no retroactivo.

### Cambios de código

- `ReclamadorExtensionAutomatico::crearGemelaWebrtc(Extension $extension, ?User $user = null)`
  — `$user` ahora nullable. Sin dueño, la gemela se crea con `user_id=null`,
  `nombre = "{extension->nombre} (navegador)"`. La rama de reasignación (gemela
  existente con OTRO dueño) ya cubría null→con-dueño sin cambios.
- `ExtensionController::asegurarGemelaWebrtc()` — quitado el `|| ! $extension->user_id`;
  ahora se llama para toda extensión que no sea ella misma una gemela
  (`es_webrtc == false`), tenga o no `user_id`. Se dispara desde `store()` y
  `update()`, o sea: **toda alta o edición de extensión desde la pantalla de
  Extensiones ya crea su `webNNNN` sola, con o sin usuario asignado.**
- Nuevo comando `php artisan voip:generar-gemelas-webrtc` (`--dry-run` disponible)
  — backfill idempotente para las extensiones que ya existían antes de este
  cambio. Registrado en `ModuleServiceProvider`.

### Backfill ejecutado en producción

`php artisan voip:generar-gemelas-webrtc` (sin `--dry-run`, tras revisar el
dry-run) — **31 gemelas creadas, 0 fallidas**: 1001, 1002, 1003, 1004, 1005,
1011, 1101-1105, 1201-1210, 1301-1305, 1401-1402, 1501-1503 (más 1001 y 1005,
27 sembradas por departamento sin dueño + las 4 con dueño real). Verificado en
`ps_endpoints` (conexión `asterisk_rt`): las 31 quedaron con `webrtc=yes`,
`media_encryption=dtls`, `transport=transport-wss`, `context=from-internal`.

### Verificación end-to-end (ejemplo del pedido: alta de 1100 → web1100)

Se creó una extensión de prueba `1100` reproduciendo exactamente el flujo de
`ExtensionController::store()` (crear → `provisionarExtension()` →
`crearGemelaWebrtc()`), confirmando `web1100` creada, provisionada y presente
en `ps_endpoints`. Se limpió (`desprovisionar` + `delete` de ambas filas) al
terminar — no quedó rastro en producción, era solo para la prueba.

### Dialplan — resuelve la duda de la sesión anterior

La sesión anterior no pudo confirmar si `_web[1-9]XXX` ya estaba desplegado en
`/etc/asterisk/extensions.conf` (permiso denegado para leer el archivo).
Confirmado ahora vía AMI (`Action: Command`, `dialplan show from-internal`):
**el patrón YA está cargado en vivo**, `extensions.conf:51`, idéntico al de
`resources/asterisk/plantillas/extensions.conf.tpl`. El Paso 3 del README de
TURN (dialplan) estaba completo, contrario a lo que se sospechaba.

### Paso 2 (TURN) — reverificado

`.env` de prod: `MEGAVOZ_TURN_URL=turn:38.123.192.198:3478`,
`MEGAVOZ_TURN_USERNAME=megavoz`, `MEGAVOZ_TURN_PASSWORD` poblada — coincide con
la IP pública real del servidor (`38.123.192.198`, confirmada con `ip addr`) y
con `deploy/setup-turn-megavoz.sh`. `config/voip.php` → `voip.turn.*` lee esas
mismas claves y `MiTelefonoController::credenciales()` ya las expone al
frontend. Cadena completa verificada.

**Nota menor (no bloqueante):** el *default* hardcodeado en
`config/voip.php:109` (`turn:38.123.192.199:3478`, termina en **.199**) no
coincide con la IP real (**.198**) ni con el `.env`. Sin impacto porque el
`.env` ya sobrescribe el default, pero vale corregir el default por si algún
entorno nuevo se levanta sin la variable puesta.

### Pendiente (fuera del alcance de esta sesión — requiere root/Irving)

- `ufw status` no se pudo confirmar (sudo pide contraseña en esta sesión).
- Prueba funcional real: llamada entre redes distintas con audio bidireccional,
  y confirmar que un no-admin no ve `/voip/troncales` ni `/voip/extensiones`
  — requiere navegador, no se puede confirmar por terminal.

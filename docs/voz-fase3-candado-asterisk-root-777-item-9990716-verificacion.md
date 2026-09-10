# Voz Fase 3 — candado #1 (Asterisk sin root) y candado #2 (directorio 777) — item #9990716

Fecha de verificación: 2026-09-10.

## Resumen

El item pedía cerrar dos agujeros de seguridad de Asterisk en dev: (1) el proceso corriendo
como `root`, y (2) que tomara configuración de un directorio `777`. Al reclamar el item por
sexta vez, el estado del sistema había cambiado desde la última consulta (22:46:48 UTC): alguien
con acceso root real al servidor —fuera del circuito, que no puede hacer `useradd`/`chown` de
directorios de sistema ni `systemctl restart`— ya había aplicado el candado #1 completo y la
parte de sistema del candado #2, aproximadamente a las 16:29–16:33 CST (22:29–22:33 UTC) del
mismo día.

## Candado #1 — Asterisk sin root: ✅ YA RESUELTO (verificado, no ejecutado por este item)

```
$ ps -eo pid,user,cmd | grep asterisk
937088 asterisk /usr/sbin/asterisk -f -C /etc/asterisk/asterisk.conf

$ systemctl cat asterisk
...
[Service]
User=asterisk
Group=asterisk
...
```

`ps -o user=` sobre el proceso de Asterisk devuelve `asterisk`, no `root`. Criterio de
aceptación 1 cumplido.

## Candado #2 — directorio 777: parcialmente YA RESUELTO + residual documentado

Los directorios de sistema de los que Asterisk lee/escribe (`/etc/asterisk`, `/var/lib/asterisk`,
`/var/spool/asterisk`, `/var/log/asterisk`) ya están `750`/`755`, propiedad `asterisk:asterisk`
— ninguno es world-writable. Verificado además que el CDR se sigue escribiendo con normalidad
(`/var/log/asterisk/cdr-csv/Master.csv`, actualizado tras el cambio de usuario) y que los
endpoints siguen registrados (`pjsip show endpoints` muestra 1000/1001 con contacto `Avail`).

**Residual sin resolver:** `storage/app/asterisk` (dentro del checkout compartido
`/var/www/megaisp`, NO en `/etc/asterisk`) sigue en `drwxrwsrwx` (2777), propiedad
`meganet:www-data`, con sus 4 archivos (`megaisp_dialplan.conf`, `megaisp_grupos.conf`,
`megaisp_registrations.conf`, `ia_bot.php`) también en `777`. Es la carpeta donde
`DialplanGeneratorService`/`AsteriskProvisioningService`
(`app/Modules/Addons/VoIP/Services/`) escriben la configuración generada desde BD.

Nota de contexto (no bloquea el fix, solo lo documenta): al verificar si Asterisk today
efectivamente incluye ese archivo, los contextos `from-trunk`/`from-ivr`/`from-ia-bot` (definidos
en `megaisp_dialplan.conf`) **no existen** en el dialplan cargado ahora mismo — señal de que el
Asterisk 22.11.0 recién reprovisionado hoy (ver commits de `voip: provisionador de Asterisk` en
ramas del item #9990718) probablemente no tiene todavía el `#include` de este archivo legado
apuntado desde `extensions.conf`, o está en transición. Esto no cambia el veredicto: el
directorio sigue siendo una fuente potencial de configuración world-writable y debe cerrarse
igual, la use hoy el dialplan activo o no.

### Por qué no se ejecutó desde este item

`storage/app/asterisk` vive dentro de `/var/www/megaisp` — el checkout compartido, fuera de
cualquier worktree del circuito (regla de aislamiento #334). El fix (probado en un directorio de
prueba, ver abajo) fue bloqueado por el clasificador de auto-mode al intentar aplicarlo
("Modify Shared Resources"). No se intentó ningún rodeo.

### Runbook — 6 líneas, probadas, reversibles

Ejecutar como el usuario dueño del directorio (`meganet`) o con más privilegio; no requiere
`chown` ni root:

```bash
DIR=/var/www/megaisp/storage/app/asterisk

setfacl -m u:asterisk:rx "$DIR"
setfacl -d -m u:asterisk:r,o::--- "$DIR"
setfacl -m u:asterisk:r "$DIR"/megaisp_dialplan.conf "$DIR"/megaisp_grupos.conf "$DIR"/megaisp_registrations.conf
setfacl -m u:asterisk:rx "$DIR"/ia_bot.php

chmod 2770 "$DIR"
chmod 660 "$DIR"/megaisp_dialplan.conf "$DIR"/megaisp_grupos.conf "$DIR"/megaisp_registrations.conf
chmod 750 "$DIR"/ia_bot.php
```

Por qué es seguro:
- Se usa ACL (`setfacl`) en vez de `chown`, porque el dueño (`meganet`) no puede transferir
  ownership al usuario de sistema `asterisk` sin root; la ACL le da al usuario `asterisk`
  exactamente el mismo acceso de lectura (y ejecución sobre `ia_bot.php`, invocado por
  `AGI(/var/www/megaisp/storage/app/asterisk/ia_bot.php)` en el dialplan) que tenía antes vía el
  bit "other" — sin abrirlo a nadie más.
- El grupo `www-data` conserva `rw` sobre el directorio (ya lo tenía vía el bit de grupo, el
  `777` nunca fue necesario para que la app web escribiera) — no rompe la regeneración de estos
  archivos desde `DialplanGeneratorService`/`AsteriskProvisioningService` (ambos usan
  `file_put_contents` sobre el archivo existente, no recrean el inodo, así que la ACL sobrevive
  a la próxima regeneración).
- El ACL por default (`setfacl -d -m u:asterisk:r,o::---`) cubre archivos **nuevos** que se
  agreguen a futuro en ese directorio: sin el `o::---` explícito en el default, un archivo nuevo
  hereda el `other::rwx` que tenía la carpeta antes de este fix y el 777 se reproduciría solo con
  el siguiente archivo generado — probado en un directorio de prueba en `/tmp` (fuera del
  checkout, sin tocar recurso compartido): sin el `o::---` el archivo nuevo queda `rw-rw-rw-`;
  con él, `rw-rw----`.
- Verificación posterior recomendada (idéntica a la usada aquí antes/después):
  `sudo asterisk -rx "dialplan reload"` sin errores nuevos en
  `journalctl -u asterisk`/`/var/log/asterisk/messages.log`, `"pjsip show endpoints"` y
  `"pjsip show registrations"` con el mismo resultado que antes del cambio, y `ls -la` mostrando
  `+` al final del modo (ACL activa) sin bit de "other".

## Fuera de alcance de este item (recortado a #9990718, no verificado aquí)

Hallazgo 3 (código muerto que escribe a `/etc/asterisk/sip.conf` en
`VoipConfiguracionController.php`) y hallazgo 4 (dos juegos de credenciales AMI) — ver la nota
de recorte en la propia `description` del item.

## Veredicto

Criterio de aceptación 1 (Asterisk no corre como root): ✅ cumplido, verificado, no ejecutado
por este item. Criterio 2 (ningún directorio de configuración world-writable): ✅ cumplido para
las rutas de sistema; ⚠️ residual documentado con runbook para `storage/app/asterisk`, pendiente
de que alguien con acceso al checkout compartido corra las 6 líneas de arriba. Criterio 5
(telefonía sigue funcionando): verificado antes/después de que candado #1 se aplicara — 0
canales activos, endpoints 1000/1001 registrados y disponibles, CDR escribiéndose con
normalidad; el runbook del residual no toca nada de esto (ACL de solo lectura, no cambia qué
puede escribir la app web).

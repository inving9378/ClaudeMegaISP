## 2026-10-27 — Actualización del servidor (kernel 6.1.0-54) y cierre de la promo 163

Sin item del roadmap. Cubre la actualización del servidor de producción y lo revisado en
ClaudeMegaISP después del reinicio.

**Actualización del servidor**
- 26-oct 15:11 PHP 8.2.29 → 8.2.35 (con extensiones y `libapache2-mod-php8.2`).
- 26-oct 15:41 Apache 2.4.65 → 2.4.68.
- 26-oct 15:50 Node.js 20.20.2 → 24.22.1 (salto mayor).
- 27-oct 06:44 respaldo MySQL: `/var/backups/mysql/megaisp-202610270643.sql.gz` (153 MB, `gzip -t` OK).
- 27-oct 06:51 kernel 6.1.153 → 6.1.190 (`linux-image-6.1.0-54-amd64`).
- 27-oct 06:53 reinicio. `uname -r` = `6.1.0-54-amd64`. El 6.1.0-40 sigue instalado como plan B.
- Después del reinicio: `apache2`, `php8.2-fpm` y `mysql` activos, sin unidades fallidas, `/login`
  responde 200, sin errores en el log de Apache ni en el journal, sin migraciones pendientes.

**redis y supervisor**
Quedaron inactivos tras el reinicio y no hacen falta: `.env` tiene `QUEUE_CONNECTION=sync`,
`CACHE_DRIVER=file` y `SESSION_DRIVER=file`. `redis-server` ni siquiera está instalado como
servicio y no existe `/etc/supervisor/conf.d/`. El scheduler corre por cron de root
(`schedule:run`). Cuando el reinicio los apagó no se perdió nada.

**Promo 163 (cliente 4927)**
- Causa: la promo venció el 4-jul pero seguía `active`, con el perfil original guardado como
  `20+30_REGALO` (con `+`). SmartOLT solo tiene `20_30_REGALO` (guion bajo), así que
  `smartolt:sync-promotions` fallaba en cada corrida (26 errores diarios desde el 14-oct).
- Cambio en producción (19:42, una sola fila): `before_download_profile` y `before_upload_profile`
  de `20+30_REGALO` a `20_30_REGALO`.
- Resultado: el 27-oct 14:01 el comando revirtió la ONU y cerró la promo
  (`revertidas=1 fallidas=0`). Desde ahí 0 errores de «No such Upload speed profile».
- El cliente quedó en `20_30_REGALO`, no en el de su plan `Basico_50MB`. Si le corresponde otro
  perfil hay que cambiarlo a mano en la ONU.

**Errores previos a la actualización, sin cambios**
- `Error connecting to RouterOS`: ~290 al día desde el 24-ago. Routers #3 «Club Meganet» (puerto
  API 8787 sin respuesta) y #4 «Inditex» (inactivo, IP privada sin ruta).
- Script `rectify_clients_script_address_list_part1` «ya existe»: 26 el 27-oct, bajando.

**Pendiente**
- Promo 99 (cliente 2364): `closed` desde el 20-jun pero con la ONU aún en `20_30_REGALO`/`GAMER`.
- Origen del `+` en `20+30_REGALO` (el controlador copia el perfil tal cual lo reporta la ONU).
- Probar el frontend con Node 24: el último `mix-manifest.json` es del 26-oct 13:31, anterior al salto.
- Decidir qué hacer con los routers #3 y #4.

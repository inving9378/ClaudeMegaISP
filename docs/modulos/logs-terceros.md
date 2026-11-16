# Módulo Logs de terceros (`addon-logs-terceros`)

**Administración → Logs de terceros.** Ver, completos y sin editar, los logs del servidor (central
telefónica, nginx, MegaISP…) para no tener que adivinar qué pasa. Se pidió en lugar de una terminal
(ver `docs/bitacora/2026-11-14-retiro-v193.md`): aquí no hay shell, solo lectura.

## Uso
1. **Nuevo grupo**: nombre y descripción opcional (p. ej. «Central telefónica»).
2. **+** en el grupo: nombre y **ruta absoluta** del log (desde `/`). El campo sugiere los que existen
   en el servidor. Para los que cambian de nombre cada día se usa `*` y se ve el más reciente:
   `/var/www/<MegaISP>/storage/logs/laravel-*.log`.
3. Clic en el log: se ve lo último (256 KB). **Más antiguas** trae lo anterior; **Ver todo** lo trae
   completo (hasta 16 MB; más grande, **Descargar**); **Seguir** agrega lo nuevo cada 3 s; filtro de
   líneas; **Copiar** copia lo que se ve. Lee también los `.gz` rotados.

Quitar un grupo o un log solo lo quita de la lista: **los archivos del servidor nunca se tocan**.

## Qué rutas acepta (`Services/LectorLogs.php`)
- Absoluta; bajo `/var/log`, bajo `storage/logs` de MegaISP, con «log» en la ruta, o `.out`/`.err`.
- Nunca: `.env` (al principio o al final del nombre), llaves y certificados, nombres con «secret» o
  «credential», `shadow`, respaldos `.sql`/`.bak`/`.dump`, `/proc`, `/sys`, `/dev`, `/run`,
  `/root/.ssh`, `/etc/ssl/private`, ni el resto del código de MegaISP.
- Se revisa al guardar y otra vez al leer sobre la ruta real (un enlace simbólico no sirve de atajo;
  con `*`, los que no pasan se saltan).
- Se lee con el usuario del servidor web: si no tiene permiso, el log lo dice en rojo.

## Permisos
`logs_terceros.ver` (ver) y `logs_terceros.gestionar` (grupos y rutas). Solo super-administrator y
DESARROLLADOR (no terminan en `.view`, así que no se reparten a los demás roles). Bitácora en
`activity_log` con `log_name='logs-terceros'` (altas, cambios, bajas y descargas).

## En prod
Administración → Módulos → **Logs de terceros** → «Instalar» (crea sus dos tablas y permisos; no
instala nada en el servidor).

## Pruebas
`vendor/bin/phpunit tests/Unit/LogsTerceros/LectorLogsTest.php`

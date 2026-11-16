# Módulo Logs de terceros (`addon-logs-terceros`)

**Administración → Logs de terceros.** Ver, completos y sin editar, los logs y cualquier otro archivo
del servidor (central telefónica, nginx, configuraciones, MegaISP…) para no tener que adivinar qué pasa. Se pidió en lugar de una terminal
(ver `docs/bitacora/2026-11-14-retiro-v193.md`): aquí no hay shell, solo lectura.

## Uso
1. **Nuevo grupo**: nombre y descripción opcional (p. ej. «Central telefónica»).
2. **+** en el grupo: nombre y **ruta absoluta** del archivo (desde `/`). El campo sugiere los que existen
   en el servidor. Para los que cambian de nombre cada día se usa `*` y se ve el más reciente:
   `/var/www/<MegaISP>/storage/logs/laravel-*.log`.
3. Clic en el archivo: se ve lo último (256 KB). **Más antiguas** trae lo anterior; **Ver todo** lo trae
   completo (hasta 16 MB; más grande, **Descargar**); **Seguir** agrega lo nuevo cada 3 s; filtro de
   líneas; **Copiar** copia lo que se ve. Lee también los `.gz` rotados. Un archivo binario (imagen,
   ejecutable…) no se pinta: avisa y se puede descargar.

Quitar un grupo o un archivo solo lo quita de la lista: **los archivos del servidor nunca se tocan**.

## Qué rutas acepta (`Services/LectorLogs.php`)
- Absoluta, de **cualquier tipo** (logs, configuraciones, salidas…). Hasta el 17-nov solo aceptaba
  logs; Irving pidió poder ver toda clase de archivos.
- Nunca los que guardan contraseñas o llaves: `.env` (al principio o al final del nombre), llaves
  privadas y almacenes (`.pem`, `.key`, `.p12`, `.pfx`, `.jks`, `.kdbx`, `id_rsa`…), nombres con
  «secret» o «credential», `shadow`/`gshadow`/`sudoers`, bases de datos y sus respaldos (`.db`,
  `.sqlite`, `.sql`, `.sql.gz`, `.dump`), `.netrc`, `.pgpass`, `.my.cnf`, `debian.cnf`, `auth.json`,
  `.npmrc`, `.htpasswd`, ni nada bajo `/proc`, `/sys`, `/dev`, `/run`, `/root/.ssh`, `/etc/ssl/private`.
- Se revisa al guardar y otra vez al leer sobre la ruta real (un enlace simbólico no sirve de atajo;
  con `*`, los que no pasan se saltan).
- Se lee con el usuario del servidor web (www-data). Si no tiene permiso (p. ej. `/home/<usuario>` con
  700, o `/root`), la lista dice «Necesita permisos de root» y al abrirlo se pide la contraseña.

## Leer como root (`Services/LectorComoRoot.php`, `bin/leer-como-root.php`)
Misma escalada que los add-ons (`deploy/system/lib.sh`, `as_root`): ya root → directo; usuario en el
grupo sudo → `sudo -n`, o su propia contraseña con `sudo -S`; si no (www-data normal) → contraseña de
**root** con `su` por `deploy/system/su-pty.py`. La contraseña viaja en el POST (campo `password`, que
no se registra) y se le da a sudo/su por la entrada estándar: no se guarda en disco, sesión ni log. La
pantalla la recuerda en memoria hasta salir, para «Seguir», «Más antiguas» y «Descargar».
- Como root solo corre `bin/leer-como-root.php` (sin arrancar Laravel): lee con `LectorLogs` (mismas
  reglas: nunca `.env`, llaves, `/proc`…) y deja el resultado en `storage/app/logs-terceros/` (0700)
  para www-data; se borra al usarlo y lo que sobre, a la hora. Descarga como root hasta 100 MB.
- 5 contraseñas equivocadas → 10 min de espera (no escribirla no cuenta). Bitácora: «Archivo leído
  como root» al abrir/recargar (no en cada vuelta de «Seguir») y «Archivo descargado como root».

## Permisos
`logs_terceros.ver` (ver) y `logs_terceros.gestionar` (grupos y rutas). Solo super-administrator y
DESARROLLADOR (no terminan en `.view`, así que no se reparten a los demás roles). Bitácora en
`activity_log` con `log_name='logs-terceros'` (altas, cambios, bajas y descargas).

## En prod
Administración → Módulos → **Logs de terceros** → «Instalar» (crea sus dos tablas y permisos; no
instala nada en el servidor).

## Pruebas
`vendor/bin/phpunit tests/Unit/LogsTerceros/` (LectorLogsTest y LectorComoRootTest, este con un `su` simulado).

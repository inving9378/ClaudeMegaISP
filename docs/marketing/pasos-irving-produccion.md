# Marketing IA 360 — pasos para Irving en producción

Producción: `/var/www/ClaudeMegaISP` (base `meganet_prod_claude`). Todo esto lo hace Irving (o una sesión abierta
directamente en `.108`), con bitácora previa en `docs/bitacora/` como pide CLAUDE.md.

> **2026-11-14 — el acceso ya es por ROL (decisión de Irving).** Entran con todo super-administrator, Administrador,
> DESARROLLADOR y Mostrador (`config/marketing.php` → `roles_con_acceso`); la migración `2026_11_14_120000` les da los
> permisos de Marketing. **La línea `MARKETING_EQUIPO_LOGINS` del §1 ya NO hace falta**: sin ella esos cuatro roles
> entran igual. Queda solo como forma opcional de dar acceso a alguien que no tenga ninguno de esos roles.
> Después de aplicar: `php artisan permission:cache-reset`.

## 1. (Opcional desde el 2026-11-14) Lista del equipo de Marketing para quien no tenga uno de los 4 roles
1. Respaldar el `.env`: `cp /var/www/ClaudeMegaISP/.env /var/www/ClaudeMegaISP/.env.bak-$(date +%Y%m%d%H%M)`
2. Agregar al final del `.env` (los `login_user` con que entran Henry e Irving a LM en producción, separados por coma):
   `MARKETING_EQUIPO_LOGINS=<login_user de Henry>,<login_user de Irving>`
3. Revisar que el grupo del archivo no cambió: `ls -la /var/www/ClaudeMegaISP/.env` (debe seguir con el grupo de
   `www-data`; si no, `chgrp www-data /var/www/ClaudeMegaISP/.env`).
4. Aplicar la versión con «Buscar actualizaciones». La migración `2026_11_13_100200` deja solo a esos dos como admin
   de Marketing y desactiva al resto. Si la línea falta o está vacía, nadie entra a Marketing (se arregla agregándola y
   corriendo `php artisan marketing:equipo-fijar <login> <login> --aplicar`).

## 2. Procesar la fila de videos (cola `video-render`) — F2-3
Hoy ningún proceso atiende la cola `video-render`: «Producir en Video MVM» se queda «En cola».
1. Verificar que exista ffmpeg: `ffmpeg -version | head -1` (si no: `sudo apt install ffmpeg`).
2. Ver qué colas atiende hoy el Supervisor: `sudo supervisorctl status` y `grep -H "queue:work" /etc/supervisor/conf.d/*.conf`.
   Si alguna ya incluye `video-render`, no hacer nada más.
3. Crear `/etc/supervisor/conf.d/megaisp-video-render.conf`:
   ```ini
   [program:megaisp-video-render]
   process_name=%(program_name)s
   command=nice -n 10 php /var/www/ClaudeMegaISP/artisan queue:work database --queue=video-render --sleep=5 --tries=2 --timeout=600 --max-time=3600
   directory=/var/www/ClaudeMegaISP
   user=www-data
   numprocs=1
   autostart=true
   autorestart=true
   startsecs=10
   stopwaitsecs=620
   redirect_stderr=true
   stdout_logfile=/var/log/supervisor/megaisp-video-render.out.log
   environment=PATH="/usr/local/bin:/usr/bin:/bin",HOME="/home/www-data"
   ```
4. Cargarlo: `sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status megaisp-video-render`
   → debe decir `RUNNING`.
5. Prueba: en Marketing → Reels y videos, producir un video corto; en 1–3 min debe pasar a «Listo».
   Log: `tail -f /var/log/supervisor/megaisp-video-render.out.log`.
6. Para apagarlo: `sudo supervisorctl stop megaisp-video-render`.
- Un solo proceso y con `nice` para no frenar al resto del sistema. En dev hace falta lo mismo con `/var/www/megaisp`.

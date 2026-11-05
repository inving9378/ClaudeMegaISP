## 2026-10-26 06:10 — DEV (.11): PHP 8.2.30 → 8.2.35

**Qué:** actualización de seguridad de PHP en el servidor de desarrollo (192.168.105.11).
PHP 8.2.35 salió el 21-oct-2026 (release de seguridad). Solo DEV — producción (.108) no
se alcanza desde aquí y queda pendiente en su propia sesión.

**Quién:** Irving corrió los comandos con sudo (la sesión de Claude Code no tiene root);
Claude Code preparó los comandos y verificó después.

**Comandos:**
```bash
sudo apt-get update
sudo UCF_FORCE_CONFFOLD=1 DEBIAN_FRONTEND=noninteractive apt-get install --only-upgrade -y \
  -o Dpkg::Options::=--force-confold \
  php-common php8.2 php8.2-cli php8.2-common php8.2-fpm php8.2-opcache php8.2-readline \
  php8.2-bcmath php8.2-curl php8.2-gd php8.2-intl php8.2-mbstring php8.2-mysql \
  php8.2-soap php8.2-xml php8.2-zip libapache2-mod-php8.2
sudo systemctl restart php8.2-fpm
php artisan queue:restart
```
- `--force-confold`: se conservaron los `php.ini` y el pool de FPM existentes.
- Los paquetes quedaron en `8.2.35-1~deb12u1` (repo **bookworm-security de Debian**, que ya
  publicó la 8.2.35 tras el `apt-get update`; no se cambió a los paquetes de Sury).
  `php-common` no cambió.

**Verificado (06:06):**
- CLI `8.2.35`, FPM `8.2.35 (fpm-fcgi)`; el sitio vía nginx/FPM reporta `8.2.35` con OPcache activo.
- 16 paquetes `php8.2-*` + `libapache2-mod-php8.2` en 8.2.35, ninguno a medias.
- `php8.2-fpm` y `nginx` activos; `https://dev.meganett.com.mx/login` → 200.
- Laravel 10.48.4 arranca (`artisan about`), conexión a MySQL 8.4.6 OK, `schedule:list` OK.
- Los 3 workers de supervisor (`megaisp-queue-worker-1/2`, `megaisp-deploy-worker`) se
  reiniciaron a las 06:03, ya con el binario nuevo.
- 0 errores en `storage/logs/laravel.log` desde la actualización.

**Pendiente:** repetir en PROD (.108) en una sesión abierta allá, con su bitácora previa.

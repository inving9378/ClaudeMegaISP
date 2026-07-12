#!/usr/bin/env bash
# Wrapper ÚNICO de los comandos de cron del Circuito CC.
# Garantiza el cwd = raíz del proyecto ANTES de invocar `php artisan`, para que
# NINGUNA línea del crontab pueda volver a olvidar el `cd`. Sin cd, `php artisan`
# corre desde el home (donde no existe `artisan`), falla y el >/dev/null se lo
# traga en silencio: fue el bug del picker y del scheduler (beat congelado +
# reap-stuck sin correr → huérfanos atorados). Este wrapper cierra el hueco de raíz.
#
# Uso en crontab (SIEMPRE por aquí, NUNCA `php artisan` suelto):
#   * * * * * /var/www/megaisp/deploy/circuito/cron-wrap.sh circuito:scheduler >/dev/null 2>&1
set -uo pipefail

cd /var/www/megaisp || exit 1
exec php artisan "$@"

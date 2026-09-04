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

# CANDADO DE LA BASE DE PRUEBAS (incidente 2026-08-25 18:14).
# Este wrapper es la copia de `main` SIEMPRE (el crontab lo invoca por ruta absoluta), y `main`
# es de donde `vuelta.sh` resincroniza los seis worktrees en cada vuelta. Si el phpunit.xml de
# main volviera a apuntar a la base de la app, esa regresión se repartiría a las seis terminales
# en la vuelta siguiente. Por eso el circuito ENTERO no arranca desde un main no apto: es
# preferible un circuito detenido y ruidoso a seis terminales capaces de vaciar dev.
# La vigilia de Jarvis tiene su propio wrapper y NO pasa por aquí, a propósito: frenar el
# circuito nunca debe dejar ciego al que mira.
if [ -r /var/www/megaisp/deploy/circuito/guard-bd-pruebas.sh ]; then
  . /var/www/megaisp/deploy/circuito/guard-bd-pruebas.sh
  guard_bd_pruebas /var/www/megaisp "cron-wrap:${1:-sin-comando}" || exit 1
else
  echo "FATAL: falta guard-bd-pruebas.sh — no corro comandos del circuito sin el candado." >&2
  exit 1
fi

# #233 — FIN DE LA CEGUERA. Toda línea del crontab termina en `>/dev/null 2>&1`, así que un
# `exec php artisan "$@"` que fallara (comando inexistente, excepción no atrapada, etc.) se veía
# EXACTAMENTE igual que uno que corrió bien: nada en ningún lado. Mismo patrón de rastro-en-archivo
# que guard-bd-pruebas.sh y vigilia-wrap.sh: stdout se sigue descartando (cron ya lo manda a
# /dev/null), pero un fallo real siempre queda escrito aquí.
ERRLOG="/home/meganet/circuito/logs/cron-wrap-errores.log"

salida="$(php artisan "$@" 2>&1)"
rc=$?
printf '%s\n' "$salida"
if [ $rc -ne 0 ]; then
  printf '[%s] rc=%s comando="%s"\n%s\n\n' "$(date +%FT%T)" "$rc" "$*" "$salida" >> "$ERRLOG" 2>/dev/null
fi
exit $rc

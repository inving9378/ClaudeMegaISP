#!/usr/bin/env bash
# Wrapper del CRON DEDICADO DEL SCHEDULER (item #9991026, Q1 aprobada por Irving:
# hash de decisión aa5c0d7a1a04fc77).
#
# Corre las líneas circuito:* del crontab SIEMPRE desde un checkout SEPARADO y fijo
# a `main` (/var/www/megaisp-scheduler), NUNCA desde /var/www/megaisp. Ese es el
# checkout COMPARTIDO donde Irving o una sesión de CC pueden tener una rama de
# trabajo checada a mano; si el scheduler corriera ahí, en ese instante ejecutaría
# los comandos del circuito contra el código de ESA rama en vez de main —
# exactamente la carrera que describe #9991026 (independiente del fix de
# #9990644/#9990640, que solo aisló al merge-runner, no al cron).
#
# Mismo mecanismo de resync que usan los worktrees wt-N del circuito (ver
# `deploy/circuito/vuelta.sh`): `git checkout --detach -f main` antes de cada tick.
# Detached no choca con el `main` real checado en /var/www/megaisp (git solo
# prohíbe la MISMA RAMA en dos worktrees a la vez, no el mismo commit).
#
# Provisionar/reprovisionar el checkout dedicado (idempotente, copia vendor +
# symlinkea .env/node_modules desde el checkout principal — Q2 aprobada: mismo
# .env que el principal):
#   php artisan circuito:provision-worktree --path=/var/www/megaisp-scheduler --base=main
#
# Uso en crontab (mismo patrón que cron-wrap.sh, NUNCA `php artisan` suelto):
#   * * * * * /var/www/megaisp/deploy/circuito/cron-wrap-scheduler.sh circuito:scheduler >/dev/null 2>&1
set -uo pipefail

SCHED_PATH="/var/www/megaisp-scheduler"

if [ ! -d "$SCHED_PATH/.git" ] && [ ! -f "$SCHED_PATH/.git" ]; then
  echo "FATAL: $SCHED_PATH no es un worktree git — provisiónalo primero con:" >&2
  echo "  php artisan circuito:provision-worktree --path=$SCHED_PATH --base=main" >&2
  exit 1
fi

# Resync a la punta de main ANTES de cada tick, para que el scheduler nunca corra
# código viejo ni el de una rama ajena — best-effort: si el fetch/checkout falla
# (red caída, etc.) seguimos con lo que ya había en disco en vez de frenar el tick.
git -C "$SCHED_PATH" checkout --detach -f main >/dev/null 2>&1 || {
  echo "aviso: no pude sincronizar $SCHED_PATH a main." >&2
}

cd "$SCHED_PATH" || exit 1

# Mismo candado de la base de pruebas que cron-wrap.sh (incidente 2026-08-25 18:14):
# nunca correr comandos del circuito si este checkout no es apto (phpunit.xml apunta
# a la BD de la app en vez de la de pruebas).
if [ -r "$SCHED_PATH/deploy/circuito/guard-bd-pruebas.sh" ]; then
  . "$SCHED_PATH/deploy/circuito/guard-bd-pruebas.sh"
  guard_bd_pruebas "$SCHED_PATH" "cron-wrap-scheduler:${1:-sin-comando}" || exit 1
else
  echo "FATAL: falta guard-bd-pruebas.sh — no corro comandos del circuito sin el candado." >&2
  exit 1
fi

# Mismo registro de errores que cron-wrap.sh (#233 — fin de la ceguera): stdout se
# sigue mandando a /dev/null desde el crontab, pero un fallo real queda escrito aquí.
ERRLOG="/home/meganet/circuito/logs/cron-wrap-scheduler-errores.log"

salida="$(php artisan "$@" 2>&1)"
rc=$?
printf '%s\n' "$salida"
if [ $rc -ne 0 ]; then
  printf '[%s] rc=%s comando="%s"\n%s\n\n' "$(date +%FT%T)" "$rc" "$*" "$salida" >> "$ERRLOG" 2>/dev/null
fi
exit $rc

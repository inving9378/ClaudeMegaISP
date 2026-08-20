#!/usr/bin/env bash
# Semáforo de builds del Circuito (#334 Fase 1). Limita a MAX `npm run dev` simultáneos
# (el box es de 4 cores → varios webpack a la vez lo ahogan). Cada ejecutor del paralelo
# compila LLAMANDO A ESTE SCRIPT (no `npm run dev` directo). Adquiere 1 de MAX ranuras por
# flock; si todas están tomadas, espera. Corre en el cwd del ejecutor (su worktree).
set -uo pipefail
RUNTIME="/home/meganet/circuito"
mkdir -p "$RUNTIME"

# #873: fuente única de verdad = config('circuito.max_builds') (vía `circuito:flags`, ya
# usado por vuelta.sh para pausado/modo — lectura barata). Antes este script leía la env
# CIRCUITO_MAX_BUILDS directo y config/circuito.php:136 quedaba como control fantasma (nadie
# lo leía). Si `artisan` falla (worktree roto, DB caída) cae a env/default — nunca bloquea el build.
MAX="$(php artisan circuito:flags 2>/dev/null | sed -n 's/^max_builds=//p')"
MAX="${MAX:-${CIRCUITO_MAX_BUILDS:-3}}"

# Espera hasta adquirir una ranura (bloqueante con backoff).
FD=""
while [ -z "$FD" ]; do
  for i in $(seq 1 "$MAX"); do
    exec {cand}>"$RUNTIME/build-$i.lock"
    if flock -n "$cand"; then FD="$cand"; break; fi
    eval "exec ${cand}>&-"   # cierra el fd si no se adquirió
  done
  [ -z "$FD" ] && sleep 2
done

# Con la ranura tomada, compila. El flock se libera al cerrar el fd (fin del script).
# #432 ADENDA A: modo configurable (dev por default; prod para el rebuild-on-merge de MergeRunner).
MODE="${CIRCUITO_BUILD_MODE:-dev}"
npm run "$MODE" "$@"
RC=$?
eval "exec ${FD}>&-"
exit $RC

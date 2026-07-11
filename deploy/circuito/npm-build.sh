#!/usr/bin/env bash
# Semáforo de builds del Circuito (#334 Fase 1). Limita a MAX `npm run dev` simultáneos
# (el box es de 4 cores → varios webpack a la vez lo ahogan). Cada ejecutor del paralelo
# compila LLAMANDO A ESTE SCRIPT (no `npm run dev` directo). Adquiere 1 de MAX ranuras por
# flock; si todas están tomadas, espera. Corre en el cwd del ejecutor (su worktree).
set -uo pipefail
RUNTIME="/home/meganet/circuito"
MAX="${CIRCUITO_MAX_BUILDS:-3}"
mkdir -p "$RUNTIME"

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
npm run dev "$@"
RC=$?
eval "exec ${FD}>&-"
exit $RC

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

# #9990491: publicación atómica del bundle JS. Antes webpack escribía public/js/app.js (+
# chunks) y public/mix-manifest.json EN EL LUGAR → una carga de página a mitad de un `npm run
# prod` (dispara cada merge, ver MergeRunner::triggerRebuildAsync) podía recibir un app.js a
# medio escribir y romper el render ("no se puede pintar"). Ahora webpack compila el entry JS a
# un staging DENTRO de public/ (mismo filesystem → `mv` es atómico) vía MIX_JS_STAGE_DIR, leído
# por webpack.mix.js; sólo si el build terminó OK se hace el swap (+ se corrige el manifest para
# que apunte a la ruta final) en el mismo paso. Si el build falla, se borra el staging y el
# bundle vivo queda intacto — nadie sirve nunca un archivo a medio escribir.
# Alcance: sólo JS+manifest (lo que reportó el item); CSS/imágenes siguen su camino normal, no
# eran la causa del bug. El mismo patrón aplicaría al deploy de prod (RemoteDeployCommand
# npm_build) — queda anotado para un item aparte, no se toca aquí (decisión de Irving).
STAGE="build-staging-$$-$(date +%s%N)"
export MIX_JS_STAGE_DIR="$STAGE"
find public -maxdepth 1 -name 'build-staging-*' -mmin +60 -exec rm -rf {} + 2>/dev/null

npm run "$MODE" "$@"
RC=$?

STAGE_DIR="public/$STAGE"
if [ "$RC" -eq 0 ] && [ -d "$STAGE_DIR/js" ]; then
  # El manifest ya se escribió (por webpack) con claves apuntando al staging; corregirlas a la
  # ruta final ANTES del swap, para que el instante en que public/js cambia, el manifest ya sea
  # consistente con él.
  [ -f public/mix-manifest.json ] && sed -i "s#/${STAGE}/js/#/js/#g" public/mix-manifest.json
  OLD="public/js.old.$$"
  rm -rf "$OLD" 2>/dev/null
  [ -e public/js ] && mv -T public/js "$OLD"
  mv -T "$STAGE_DIR/js" public/js
  rm -rf "$OLD" 2>/dev/null &
elif [ "$RC" -ne 0 ]; then
  echo "[npm-build] build falló (rc=$RC) — bundle vivo intacto, no se toca public/js." >&2
fi
rm -rf "$STAGE_DIR" 2>/dev/null

eval "exec ${FD}>&-"
exit $RC

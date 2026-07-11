#!/usr/bin/env bash
# Ejecutor ON-BOX del Circuito CC — una vuelta.
# Defensa en capas: kill switch (circuito_pausado) + flock (instancia única) +
# herramientas acotadas por modo (NUNCA --dangerously-skip-permissions) + timeout + log.
# Corre bajo el usuario meganet (donde el CLI `claude` está autenticado).
set -uo pipefail

export HOME=/home/meganet
export PATH="/home/meganet/.local/bin:/usr/local/bin:/usr/bin:/bin"

# El ejecutor se autentica con el login claude.ai (OAuth, ~/.claude/.credentials.json).
# El entorno de dev trae ANTHROPIC_API_KEY (de .bashrc/.env, para las features de IA de
# MegaISP) que TIENE PRECEDENCIA y rompe el headless ("Invalid API key"). La desactivamos
# solo para esta invocación (no toca el .env ni el runtime de la app).
unset ANTHROPIC_API_KEY ANTHROPIC_AUTH_TOKEN CLAUDE_API_KEY

PROJ="/var/www/megaisp"
RUNTIME="/home/meganet/circuito"
LOGDIR="$RUNTIME/logs"
LOCK="$RUNTIME/vuelta.lock"
PROMPT_FILE="$PROJ/deploy/circuito/prompt.txt"
TIMEOUT="${CIRCUITO_TIMEOUT:-600}"      # segundos por vuelta (10 min)
MAXTURNS="${CIRCUITO_MAXTURNS:-60}"

mkdir -p "$LOGDIR"
TS="$(date +%Y%m%d-%H%M%S)"
LOG="$LOGDIR/vuelta-$TS.log"
log(){ echo "[$(date +%H:%M:%S)] $*" | tee -a "$LOG"; }

# Lock de instancia única: si ya hay una vuelta corriendo, salgo (nunca solapar).
exec 9>"$LOCK"
if ! flock -n 9; then
  log "Otra vuelta en curso (lock ocupado). Salgo."
  exit 0
fi

cd "$PROJ" || { log "No pude cd a $PROJ"; exit 1; }

# Kill switch + modo (machine-readable).
FLAGS="$(php artisan circuito:flags 2>>"$LOG")"
PAUSED="$(printf '%s\n' "$FLAGS" | sed -n 's/^pausado=//p')"
MODO="$(printf '%s\n' "$FLAGS" | sed -n 's/^modo=//p')"
log "flags: pausado=${PAUSED:-?} modo=${MODO:-?}"

# Registra la fila de ejecución (#319). Nunca tumba la vuelta si falla.
registrar(){  # started finished modo pausado rc meta
  php artisan circuito:registrar-ejecucion \
    --started="$1" --finished="$2" --modo="$3" --pausado="$4" --rc="$5" \
    --log="$LOG" --meta="$6" >>"$LOG" 2>&1 || log "aviso: no se pudo registrar la ejecución."
}

if [ "$PAUSED" = "1" ]; then
  NOW="$(date +%s)"
  log "Circuito EN PAUSA (kill switch activo). No ejecuto nada."
  # Cierra cualquier estado en vivo colgado (#335) para que la Torre no muestre "corriendo".
  php artisan circuito:vivo --end >>"$LOG" 2>&1 || true
  registrar "$NOW" "$NOW" "${MODO:-aviso_previo}" "1" "0" '{}'
  exit 0
fi

# Herramientas por modo (opción a). aviso_previo => solo Bash; autonomo => Bash+Edit+Write
# (+Read, requisito de Edit). JAMÁS --dangerously-skip-permissions.
if [ "$MODO" = "autonomo" ]; then
  TOOLS="Bash Edit Write Read"
else
  TOOLS="Bash"
fi
log "modo=${MODO:-aviso_previo} tools=[$TOOLS] timeout=${TIMEOUT}s maxturns=$MAXTURNS"
log "===== inicio de la vuelta (claude -p) ====="

START="$(date +%s)"

# Estado EN VIVO (#335): marca el arranque y lanza el latido en background, que espejea a
# BD el heartbeat + el tail del log para que la Torre muestre "corriendo AHORA". Corre como
# meganet (sí lee el log); www-data no puede. Best-effort: nunca tumba la vuelta.
php artisan circuito:vivo --start --log="$LOG" >>"$LOG" 2>&1 || log "aviso: no se pudo marcar inicio live."
php artisan circuito:vivo --watch --log="$LOG" >/dev/null 2>&1 &
HB_PID=$!

timeout "$TIMEOUT" claude -p "$(cat "$PROMPT_FILE")" \
  --allowed-tools $TOOLS \
  --max-turns "$MAXTURNS" \
  >>"$LOG" 2>&1
RC=$?
FIN="$(date +%s)"

# Detener el latido y marcar el fin del estado en vivo (#335).
if [ -n "${HB_PID:-}" ]; then kill "$HB_PID" 2>/dev/null; wait "$HB_PID" 2>/dev/null; fi
php artisan circuito:vivo --end >>"$LOG" 2>&1 || log "aviso: no se pudo marcar fin live."

log "===== fin de la vuelta ====="
if [ "$RC" -eq 124 ]; then
  log "Vuelta cortada por timeout (${TIMEOUT}s)."
elif [ "$RC" -ne 0 ]; then
  log "claude terminó con código $RC."
else
  log "Vuelta OK."
fi

# Meta estructurado que emitió el ejecutor (última línea CIRCUITO_META: {...}).
META="$(grep -a 'CIRCUITO_META:' "$LOG" | tail -1 | sed 's/^.*CIRCUITO_META: *//')"
[ -z "$META" ] && META='{}'
registrar "$START" "$FIN" "${MODO:-aviso_previo}" "0" "$RC" "$META"

log "Log completo: $LOG"
exit 0

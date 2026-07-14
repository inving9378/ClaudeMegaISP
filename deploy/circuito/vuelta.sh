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
# Aislamiento por worktree (#334 Fase 0): el ejecutor trabaja en SU worktree dedicado,
# NUNCA en el checkout principal ($PROJ) donde viven las sesiones interactivas de CC.
# PARALELO (#334 Fase 1): el scheduler pasa CIRCUITO_ITEM/WT/SID → una vuelta POR item en su
# worktree (wt-K). Sin CIRCUITO_ITEM = modo legacy (backlog completo en wt-exec).
ITEM="${CIRCUITO_ITEM:-}"
WT="${CIRCUITO_WT:-$RUNTIME/wt-exec}"
SID="${CIRCUITO_SID:-wt-exec}"           # id de sesión para el estado live por-sesión (#334)
LOCK="$RUNTIME/${SID}.lock"              # lock POR worktree → N vueltas en paralelo (una por slot)
PROMPT_FILE="$PROJ/deploy/circuito/prompt.txt"
PROMPT_ITEM_FILE="$PROJ/deploy/circuito/prompt-item.txt"
TIMEOUT="${CIRCUITO_TIMEOUT:-600}"      # segundos por vuelta (10 min)
MAXTURNS="${CIRCUITO_MAXTURNS:-60}"
MODEL="${CIRCUITO_MODEL:-sonnet}"       # #336: Sonnet por defecto (paralelo barato). Override con CIRCUITO_MODEL.

mkdir -p "$LOGDIR"
TS="$(date +%Y%m%d-%H%M%S)"
LOG="$LOGDIR/vuelta-$TS-$SID.log"
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
  php artisan circuito:vivo --end --sid="$SID" >>"$LOG" 2>&1 || true
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
log "modo=${MODO:-aviso_previo} model=$MODEL tools=[$TOOLS] timeout=${TIMEOUT}s maxturns=$MAXTURNS"

# ── Aislamiento por worktree (#334 Fase 0) ──────────────────────────────────────────────
# Provisiona (idempotente) el worktree dedicado del ejecutor y lo sincroniza a main limpio.
# Se corre DESDE $PROJ (checkout principal) para que el comando resuelva bien la fuente de
# los symlinks/vendor. Si falla, ABORTO la vuelta (no caigo al checkout principal → jamás
# vuelvo a la colisión).
if ! php artisan circuito:provision-worktree --path="$WT" >>"$LOG" 2>&1; then
  log "No pude provisionar el worktree $WT. Aborto la vuelta (no toco el checkout principal)."
  NOW="$(date +%s)"
  php artisan circuito:vivo --end --sid="$SID" >>"$LOG" 2>&1 || true
  registrar "$NOW" "$NOW" "${MODO:-aviso_previo}" "0" "1" '{}' 2>/dev/null || true
  exit 1
fi
cd "$WT" || { log "No pude cd a $WT"; exit 1; }
log "Ejecutor aislado en worktree $WT (sid=$SID)."

# Ejecuta UNA vuelta para el $ITEM (o backlog) actual: sincroniza el worktree a main limpio,
# arma el prompt, corre claude -p con latido en vivo y registra la ejecución.
ejecutar_una() {
  # Cada item arranca de MAIN fresco (con lo ya mergeado por los otros workers). `checkout
  # --detach -f main` NO checa la rama main (vive en $PROJ) → git lo permite en el worktree.
  git -C "$WT" checkout --detach -f main >>"$LOG" 2>&1 || log "aviso: no pude sincronizar $WT a main."
  git -C "$WT" clean -fdq >>"$LOG" 2>&1 || true

  if [ -n "$ITEM" ]; then
    PROMPT_TEXT="$(sed -e "s/__ITEM_ID__/$ITEM/g" -e "s/__SID__/$SID/g" "$PROMPT_ITEM_FILE")"
    log "modo POR-ITEM: trabajando SOLO el item #$ITEM"
  else
    PROMPT_TEXT="$(cat "$PROMPT_FILE")"
  fi
  log "===== inicio de la vuelta (claude -p) ====="

  local START FIN RC HB_PID META
  START="$(date +%s)"
  php artisan circuito:vivo --start --sid="$SID" --log="$LOG" >>"$LOG" 2>&1 || log "aviso: no se pudo marcar inicio live."
  php artisan circuito:vivo --watch --sid="$SID" --log="$LOG" >/dev/null 2>&1 &
  HB_PID=$!

  timeout "$TIMEOUT" claude -p "$PROMPT_TEXT" \
    --model "$MODEL" \
    --allowed-tools $TOOLS \
    --max-turns "$MAXTURNS" \
    >>"$LOG" 2>&1
  RC=$?
  FIN="$(date +%s)"

  if [ -n "${HB_PID:-}" ]; then kill "$HB_PID" 2>/dev/null; wait "$HB_PID" 2>/dev/null; fi
  php artisan circuito:vivo --end --sid="$SID" >>"$LOG" 2>&1 || log "aviso: no se pudo marcar fin live."

  log "===== fin de la vuelta ====="
  if [ "$RC" -eq 124 ]; then log "Vuelta cortada por timeout (${TIMEOUT}s)."
  elif [ "$RC" -ne 0 ]; then log "claude terminó con código $RC."
  else log "Vuelta OK."; fi

  META="$(grep -a 'CIRCUITO_META:' "$LOG" | tail -1 | sed 's/^.*CIRCUITO_META: *//')"
  [ -z "$META" ] && META='{}'

  # PARQUEO EN TIMEOUT (#burn-fix): un item que timeouteó (RC=124) NO se re-encola — si no,
  # re-timeoutea y vuelve a quemar 600s de Max. Se escala a requiere_irving. Bonus: su META quedó
  # en blanco (no emitió CIRCUITO_META), así que el grep de arriba arrastraría el META del item
  # ANTERIOR del pool-continuo (mal-atribución real: #224→#203); en timeout forzamos la atribución
  # al item verdadero ($ITEM).
  if [ "$RC" -eq 124 ] && [ -n "${ITEM:-}" ]; then
    META="{\"items_tocados\":[$ITEM],\"n_propuestas\":0,\"n_decisiones\":0,\"ejecuto\":false,\"resumen\":\"Timeout ${TIMEOUT}s sin resultado — parqueado a requiere_irving (item demasiado grande o atascado; no re-encolar).\"}"
    php artisan tinker --execute="\$i=\App\Modules\Addons\Roadmap\Models\RoadmapItem::find($ITEM); if(\$i && !in_array(\$i->estado_aprobacion,['completado','cancelado','requiere_irving'],true)){ \$i->estado_aprobacion='requiere_irving'; \$i->aprobado_por='timeout'; \$i->save(); }" >>"$LOG" 2>&1 || log "aviso: no pude parquear #$ITEM tras timeout."
  fi

  registrar "$START" "$FIN" "${MODO:-aviso_previo}" "0" "$RC" "$META"
}

if [ -n "$ITEM" ]; then
  # POOL CONTINUO (#334 F1): trabaja su item y, al terminar, PIDE el siguiente elegible SIN esperar
  # al cron → mantiene el slot lleno mientras haya trabajo seguro (mata los valles). claim-next
  # respeta el kill switch (pausa → nada que reclamar) y serializa por flock (reclamo atómico #341).
  while true; do
    ejecutar_una
    NEXT="$(php artisan circuito:claim-next --sid="$SID" 2>/dev/null)"
    if [ -z "$NEXT" ]; then
      log "Sin más trabajo elegible (o pausa): worker $SID suelta el slot; el scheduler lo relanza al haber trabajo."
      break
    fi
    ITEM="$NEXT"
    log "Pool continuo: worker $SID toma de inmediato el siguiente item #$ITEM."
  done
else
  ejecutar_una
fi

log "Log completo: $LOG"
exit 0

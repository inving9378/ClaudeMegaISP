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
# #170 — CENTINELA DEL FRENO DE MANO. Ruta ABSOLUTA: este script hace `cd` al worktree del slot,
# y cada worktree tiene su propio storage/ real — una ruta relativa daría un freno por terminal.
# Se consulta con `test -e`, SIN php y SIN base: es lo que lo hace fiable con MySQL caído (y lo que
# lo hace instantáneo, que es lo que permite consultarlo en cada iteración sin costo).
CENTINELA="${CIRCUITO_FRENO_CENTINELA:-/var/www/megaisp/storage/app/circuito/PAUSA}"
frenado(){ [ -e "$CENTINELA" ]; }

# CANDADO DE LA BASE DE PRUEBAS (incidente 2026-08-25 18:14). Ruta ABSOLUTA por $PROJ, misma
# razón que el centinela de arriba: este script corre sobre worktrees que pueden estar en ramas
# ANTERIORES al candado de PHP, y entonces esa capa no existe ahí. Ésta no se bifurca.
if [ -r "$PROJ/deploy/circuito/guard-bd-pruebas.sh" ]; then
  . "$PROJ/deploy/circuito/guard-bd-pruebas.sh"
else
  echo "FATAL: falta $PROJ/deploy/circuito/guard-bd-pruebas.sh — no arranco sin el candado." >&2
  exit 1
fi

PROMPT_FILE="$PROJ/deploy/circuito/prompt.txt"
PROMPT_ITEM_FILE="$PROJ/deploy/circuito/prompt-item.txt"
TIMEOUT="${CIRCUITO_TIMEOUT:-600}"      # segundos por vuelta (10 min)
MAXTURNS="${CIRCUITO_MAXTURNS:-60}"
# MODEL se resuelve MÁS ABAJO desde `circuito:flags` (settings circuito_modelo_rutina/forzar, #336).
# CIRCUITO_MODEL sigue siendo el override manual de más prioridad (pruebas ad-hoc sin tocar settings).

mkdir -p "$LOGDIR"

# #175 — RETENCIÓN de logs de vuelta. Antes se acumulaban para siempre (uno por ejecución,
# nunca se borraban): 14 días, igual que la retención ya establecida para backup_db:process.
find "$LOGDIR" -maxdepth 1 -name 'vuelta-*.log' -mtime +14 -delete 2>/dev/null || true

TS="$(date +%Y%m%d-%H%M%S)"
LOG="$LOGDIR/vuelta-$TS-$SID.log"
LOG_MAXBYTES=$((20*1024*1024))  # 20 MB
log(){ echo "[$(date +%H:%M:%S)] $*" | tee -a "$LOG"; }

# #175 — ROTACIÓN por tamaño. Origen del incidente: en modo POOL CONTINUO el while-loop de más
# abajo puede encadenar items durante horas sin que el proceso vuelva a arrancar (un "zombie" que
# no se cae), y $LOG es un único archivo fijo desde el arranque → creció a 278 MB sin límite.
# Se llama entre items del pool: si el log activo ya pasó el tope, abre uno nuevo (mismo SID).
rotar_log_si_crecio(){
  local size
  size="$(stat -c%s "$LOG" 2>/dev/null || echo 0)"
  if [ "$size" -ge "$LOG_MAXBYTES" ]; then
    TS="$(date +%Y%m%d-%H%M%S)"
    local prev="$LOG"
    LOG="$LOGDIR/vuelta-$TS-$SID.log"
    log "Rotación por tamaño (${size} bytes >= ${LOG_MAXBYTES}). Log anterior: $prev"
  fi
}

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
MODELO_FLAG="$(printf '%s\n' "$FLAGS" | sed -n 's/^modelo=//p')"
MODEL="${CIRCUITO_MODEL:-${MODELO_FLAG:-sonnet}}"   # #336: settings circuito_modelo_rutina/forzar; CIRCUITO_MODEL manda si viene.
log "flags: pausado=${PAUSED:-?} modo=${MODO:-?} modelo=${MODEL:-?}"

# ── REGISTRO DE PROCESOS DEL CIRCUITO (vigilancia de Jarvis) ────────────────────────────────
# Se escribe EN BASH, no en PHP, y a propósito: tiene que existir cuando la base no responde y
# cuando la app está rota, que es justo cuando hace falta saber quién está corriendo. PHP sólo lee.
#
# POR QUÉ: el vigilante sólo puede tocar procesos que identifique como del circuito POR SU PROPIO
# REGISTRO. Sin esto, la única forma de identificarlos es el nombre del binario — y `ps | grep
# claude` incluye las sesiones interactivas de Irving. Un `pkill claude` es autoinmune.
#
# IDENTIDAD = PID + STARTTIME: los PID se reciclan. El campo 22 de /proc/<pid>/stat es inmutable
# para ese proceso; si no coincide, la entrada está muerta aunque el número siga existiendo.
# Se parsea DESPUÉS del último ')' porque el campo 2 es el nombre del ejecutable entre paréntesis.
JARVIS_PIDS="${CIRCUITO_JARVIS_PIDS:-/var/www/megaisp/storage/app/circuito/jarvis/pids}"
PIDFILE="$JARVIS_PIDS/${SID}.json"

registrar_pid(){  # $1 = item (puede venir vacío)
  mkdir -p "$JARVIS_PIDS" 2>/dev/null || return 0
  local st pgid tmp
  st="$(sed -e 's/^.*) //' "/proc/$$/stat" 2>/dev/null | awk '{print $20}')"
  pgid="$(ps -o pgid= -p $$ 2>/dev/null | tr -d ' ')"
  tmp="$PIDFILE.tmp.$$"
  # Escritura atómica (tmp+rename): el vigilante nunca lee un registro a medio escribir.
  printf '{"sid":"%s","pid":%s,"pgid":"%s","starttime":"%s","item":"%s","wt":"%s","log":"%s","modelo":"%s","timeout":%s,"desde_ts":%s,"host":"%s"}\n' \
    "$SID" "$$" "${pgid:-}" "${st:-}" "${1:-}" "$WT" "$LOG" "${MODEL:-}" "${TIMEOUT:-0}" "$(date +%s)" "$(hostname)" \
    > "$tmp" 2>/dev/null && mv -f "$tmp" "$PIDFILE" 2>/dev/null || rm -f "$tmp" 2>/dev/null
}

borrar_pid(){ rm -f "$PIDFILE" 2>/dev/null || true; }

# ── HISTÓRICO DE ARRANQUES DE claude -p (familia GASTO, #706 sub-item de #208) ──────────────
# APPEND-ONLY, a propósito distinto de $PIDFILE de arriba: ese es estado VIVO (una fila por SID,
# se borra al terminar por el trap EXIT); esto es HISTORIA (una línea por arranque, nunca se
# borra ni se trunca aquí) — es lo único que le permite a la vigilia contar "invocaciones/hora"
# más allá de la vuelta que está corriendo ahora mismo. JSONL para que el lector en PHP no tenga
# que parsear texto libre.
JARVIS_ARRANQUES="${CIRCUITO_JARVIS_ARRANQUES_LOG:-$(dirname "$JARVIS_PIDS")/arranques-claude.log}"
registrar_arranque(){  # $1 = item (puede venir vacío)
  mkdir -p "$(dirname "$JARVIS_ARRANQUES")" 2>/dev/null || return 0
  printf '{"ts":%s,"sid":"%s","item":"%s","modelo":"%s"}\n' \
    "$(date +%s)" "$SID" "${1:-}" "${MODEL:-}" >> "$JARVIS_ARRANQUES" 2>/dev/null || true
}

# El trap cubre timeout, kill y error: si la vuelta muere de cualquier forma, el registro no queda
# mintiendo. Y si aun así quedara colgado, el propio vigilante lo detecta por `starttime` y lo
# reporta como entrada colgada en vez de creerle.
trap borrar_pid EXIT

# Registra la fila de ejecución (#319). Nunca tumba la vuelta si falla.
registrar(){  # started finished modo pausado rc meta modelo
  php artisan circuito:registrar-ejecucion \
    --started="$1" --finished="$2" --modo="$3" --pausado="$4" --rc="$5" \
    --log="$LOG" --meta="$6" --modelo="${7:-}" >>"$LOG" 2>&1 || log "aviso: no se pudo registrar la ejecución."
}

# El centinela manda sobre el flag de base: existe para funcionar cuando la base NO contesta, así
# que si `circuito:flags` falló (PAUSED vacío) esto sigue siendo verdad.
if frenado; then
  log "FRENO DE MANO PUESTO (centinela $CENTINELA). No ejecuto nada."
  cat "$CENTINELA" 2>/dev/null | tee -a "$LOG"
  php artisan circuito:vivo --end --sid="$SID" >>"$LOG" 2>&1 || true
  exit 0
fi

if [ "$PAUSED" = "1" ]; then
  NOW="$(date +%s)"
  log "Circuito EN PAUSA (kill switch activo). No ejecuto nada."
  # Cierra cualquier estado en vivo colgado (#335) para que la Torre no muestre "corriendo".
  php artisan circuito:vivo --end --sid="$SID" >>"$LOG" 2>&1 || true
  registrar "$NOW" "$NOW" "${MODO:-aviso_previo}" "1" "0" '{}' "$MODEL"
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
  registrar "$NOW" "$NOW" "${MODO:-aviso_previo}" "0" "1" '{}' "$MODEL" 2>/dev/null || true
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

  # El worktree ya está en main; verifico ANTES de soltar al agente que este árbol no puede
  # borrar la base de dev. Si el agente reanuda una rama vieja, el candado de PHP que viaja en
  # main lo vuelve a atrapar dentro del proceso de phpunit. Aquí se corta el caso de raíz: un
  # árbol no apto ni siquiera llega a tener un agente encima.
  if ! guard_bd_pruebas "$WT" "$SID"; then
    log "Vuelta ABORTADA: el worktree $WT no es apto para pruebas (ver storage/app/circuito/guard-bd-pruebas.log)."
    return 1
  fi

  if [ -n "$ITEM" ]; then
    PROMPT_TEXT="$(sed -e "s/__ITEM_ID__/$ITEM/g" -e "s/__SID__/$SID/g" "$PROMPT_ITEM_FILE")"
    log "modo POR-ITEM: trabajando SOLO el item #$ITEM"
  else
    PROMPT_TEXT="$(cat "$PROMPT_FILE")"
  fi
  registrar_pid "${ITEM:-}"
  registrar_arranque "${ITEM:-}"
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

  # PARQUEO EN TIMEOUT (#burn-fix, revisado 2026-08-20): un item que timeouteó (RC=124) no se
  # re-encola A CIEGAS — si no, re-timeoutea y vuelve a quemar 600s de Max. Pero "no a ciegas" no es
  # "nunca": el item que AVANZÓ (commits en su rama) es barato de reanudar y `scopeOrdenCola` ya lo
  # prioriza como reanudable, mientras que el que giró en vacío es el que hay que atrapar.
  # La regla vive en `circuito:parquear-timeout` (PHP, testeable) y no aquí: decide si el circuito
  # re-gasta 600s de límite, y eso no debe vivir en un string de bash.
  # Bonus que se conserva: su META quedó en blanco (no emitió CIRCUITO_META), así que el grep de
  # arriba arrastraría el META del item ANTERIOR del pool-continuo (mal-atribución real:
  # #224→#203); en timeout forzamos la atribución al item verdadero ($ITEM).
  if [ "$RC" -eq 124 ] && [ -n "${ITEM:-}" ]; then
    META="{\"items_tocados\":[$ITEM],\"n_propuestas\":0,\"n_decisiones\":0,\"ejecuto\":false,\"resumen\":\"Timeout ${TIMEOUT}s — ver circuito:parquear-timeout (reanudado si la rama tiene commits; a la bandeja si no).\"}"
    php artisan circuito:parquear-timeout "$ITEM" --segundos="$TIMEOUT" >>"$LOG" 2>&1 || log "aviso: no pude parquear #$ITEM tras timeout."
  fi

  registrar "$START" "$FIN" "${MODO:-aviso_previo}" "0" "$RC" "$META" "$MODEL"
}

if [ -n "$ITEM" ]; then
  # #174 — TOPE DE ITERACIONES + TIMEOUT DEL PADRE + DETECCIÓN DE HUÉRFANO.
  # El `timeout $TIMEOUT` de arriba SOLO envuelve al hijo (`claude -p`) dentro de `ejecutar_una`.
  # El padre (este `while true`) podía reentrar sin límite mientras `claim-next` siguiera dando
  # trabajo — y si el proceso que lo lanzó (cron/scheduler) moría a medio camino, quedaba
  # reparentado a init (PPID=1) corriendo indefinidamente: pausar el cron ya no lo alcanzaba,
  # porque cron sólo evita relanzar, no mata lo que ya está corriendo (incidente P0 2026-08-24,
  # huérfano de 1 d 21 h). Tres candados independientes, cualquiera basta para soltar el slot:
  MAXITER="${CIRCUITO_MAXITER:-20}"                    # tope de items por invocación del padre.
  PARENT_TIMEOUT="${CIRCUITO_PARENT_TIMEOUT:-10800}"   # 3h — pared sobre TODO el proceso (no por hijo).
  PARENT_START="$(date +%s)"
  ITERS=0

  # ⚠️ NO REINTRODUCIR UN CHEQUEO DE PPID=1 AQUÍ (regresión del 2026-08-26, #174; retirado 08-27).
  # El tercer candado original preguntaba "¿mi PPID es 1?" para detectar "ya nadie me supervisa".
  # En ESTA arquitectura esa pregunta no se puede contestar: el scheduler lanza cada vuelta con
  # `setsid nohup … &` (SchedulerCommand::lanzarVueltaItem) justamente para desligarla del cron,
  # así que PPID=1 es el estado NORMAL desde el segundo cero, no la señal de un padre muerto.
  # El guard disparaba en la PRIMERA iteración, antes de tocar un solo item: 192 vueltas seguidas
  # abortadas con "suelto el slot" y el circuito parado en seco ~13 h, con el freno suelto y el
  # cron disparando cada minuto. Falla muda: se veía igual que "no hay trabajo elegible".
  # Lo que ese candado quería evitar (el huérfano de 1 d 21 h del P0 del 24-ago) YA lo acotan los
  # otros dos, que sí son medibles desde dentro del proceso: PARENT_TIMEOUT (pared de 3 h sobre
  # todo el lazo) y MAXITER (tope de items por invocación) — un runaway queda acotado a 3 h / 20
  # items en vez de días. Desde fuera siguen `circuito:reap-stuck`, `circuito:watchdog` y el freno
  # de mano en archivo, que este lazo sí consulta antes y después de cada item.

  # POOL CONTINUO (#334 F1): trabaja su item y, al terminar, PIDE el siguiente elegible SIN esperar
  # al cron → mantiene el slot lleno mientras haya trabajo seguro (mata los valles). claim-next
  # respeta el kill switch (pausa → nada que reclamar) y serializa por flock (reclamo atómico #341).
  while true; do
    ELAPSED=$(( $(date +%s) - PARENT_START ))
    if [ "$ELAPSED" -ge "$PARENT_TIMEOUT" ]; then
      log "TOPE DE TIEMPO DEL PADRE (${PARENT_TIMEOUT}s, llevo ${ELAPSED}s): suelto el slot; el scheduler relanza si hay más trabajo."
      php artisan circuito:vivo --end --sid="$SID" >>"$LOG" 2>&1 || true
      break
    fi

    ITERS=$((ITERS+1))
    if [ "$ITERS" -gt "$MAXITER" ]; then
      log "TOPE DE ITERACIONES alcanzado ($MAXITER items en esta invocación): suelto el slot; el scheduler relanza si hay más trabajo."
      php artisan circuito:vivo --end --sid="$SID" >>"$LOG" 2>&1 || true
      break
    fi

    # #170 — EL CHEQUEO QUE FALTABA. Antes el freno sólo se miraba al ARRANCAR la vuelta: una vez
    # dentro de este lazo, el worker seguía pidiendo items hasta secar la cola pasara lo que pasara.
    # Por eso pausar el cron no detuvo al huérfano de 1 d 21 h: el cron ya no lo relanzaba, pero el
    # lazo tampoco volvía a preguntar. Va ANTES de `ejecutar_una` para que también corte la primera.
    # `test -e` sobre un archivo: sin base, sin php, sin depender de que el padre siga vivo.
    if frenado; then
      log "FRENO DE MANO PUESTO a mitad del pool: $SID suelta el slot sin tomar otro item."
      cat "$CENTINELA" 2>/dev/null | tee -a "$LOG"
      php artisan circuito:vivo --end --sid="$SID" >>"$LOG" 2>&1 || true
      break
    fi

    ejecutar_una
    rotar_log_si_crecio

    # Y otra vez DESPUÉS de trabajar: una vuelta dura hasta 10 min, tiempo de sobra para que alguien
    # ponga el freno (o para que el padre se quede huérfano) mientras corría. Sin esto, el worker
    # reclamaría un item más antes de enterarse.
    if frenado; then
      log "FRENO DE MANO PUESTO durante la vuelta: $SID no reclama el siguiente."
      break
    fi
    ELAPSED=$(( $(date +%s) - PARENT_START ))
    if [ "$ELAPSED" -ge "$PARENT_TIMEOUT" ]; then
      log "TOPE DE TIEMPO DEL PADRE alcanzado durante la vuelta (${PARENT_TIMEOUT}s): $SID no reclama el siguiente."
      break
    fi

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

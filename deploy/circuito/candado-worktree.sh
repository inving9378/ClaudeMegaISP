#!/usr/bin/env bash
# Candado de exclusividad por worktree (item #502).
#
# Por qué: el 2026-10-16 una sesión de Claude hizo `git checkout -b` en /var/www/megaisp-cc mientras
# OTRA sesión trabajaba ahí con cambios sin commitear. Los slots del circuito tienen flock; los
# worktrees manuales no tenían nada.
#
# Cómo: hook PreToolUse de Claude Code (/var/www/megaisp/.claude/settings.json). Antes de un
# Edit/Write o de un `git` que mueve el HEAD o el árbol (checkout, switch, reset, merge, rebase,
# commit, stash, pull, restore, clean…) en un worktree, la sesión toma el candado de ESE worktree
# (archivo sesion-claude.json dentro de su git-dir: no aparece en `git status`). Si lo tiene otra
# sesión viva, la acción se bloquea (exit 2) y se le dice cómo crearse su propio worktree.
#
#  - Fuera del candado: el checkout principal (punto de merge de todos; regla aparte: verificar
#    `git branch --show-current` = main antes de mergear) y los slots del circuito
#    (/home/meganet/circuito/*, ya tienen flock propio) y el worktree del merge-runner.
#  - Candado vencido = el proceso de la sesión dueña ya no existe, o lleva más de
#    CANDADO_WORKTREE_TTL_MIN minutos (default 180) sin usar el worktree. Se toma y se anota.
#  - Falla ABIERTO: cualquier error inesperado del propio script deja pasar la acción (un hook roto
#    no debe frenar a todas las sesiones de la cuenta). Solo bloquea un conflicto confirmado.
#  - Límite conocido: una edición hecha con sed/heredoc dentro de Bash no se detecta (sí el git).
#
# Uso:
#   candado-worktree.sh hook            (PreToolUse; JSON de Claude Code por stdin)
#   candado-worktree.sh fin             (SessionEnd; suelta los candados de la sesión)
#   candado-worktree.sh estado          (lista los candados vigentes)
#   candado-worktree.sh soltar <ruta>   (suelta a mano el candado de un worktree)

set -u

TTL_MIN="${CANDADO_WORKTREE_TTL_MIN:-180}"
EXENTOS="${CANDADO_WORKTREE_EXENTOS:-/home/meganet/circuito/:/home/meganet/worktrees/}"
ARCHIVO="sesion-claude.json"

# Raíz del worktree que contiene $1 (sube hasta un directorio existente). Vacío si no es git.
raiz_de() {
    local d="$1"
    while [ -n "$d" ] && [ "$d" != "/" ] && [ ! -d "$d" ]; do d=$(dirname "$d"); done
    [ -d "$d" ] || return 0
    git -C "$d" rev-parse --show-toplevel 2>/dev/null
}

principal_de() {
    local comun
    comun=$(git -C "$1" rev-parse --path-format=absolute --git-common-dir 2>/dev/null) || return 0
    dirname "$comun"
}

exento() {
    local raiz="$1" p
    [ "$raiz" = "$(principal_de "$raiz")" ] && return 0
    IFS=':' read -r -a lista <<<"$EXENTOS"
    for p in "${lista[@]}"; do
        [ -n "$p" ] && case "$raiz/" in "$p"*) return 0 ;; esac
    done
    return 1
}

pid_claude() {
    local p=$PPID c i
    for i in 1 2 3 4 5 6; do
        [ -n "$p" ] && [ "$p" -gt 1 ] 2>/dev/null || return 0
        c=$(ps -o comm= -p "$p" 2>/dev/null)
        [ "$c" = "claude" ] && { echo "$p"; return 0; }
        p=$(ps -o ppid= -p "$p" 2>/dev/null | tr -d ' ')
    done
}

bitacora() {
    local principal="$1"; shift
    { printf '%s %s\n' "$(date '+%F %T')" "$*" >>"$principal/storage/logs/candado-worktree.log"; } 2>/dev/null || true
}

# Directorios que toca un comando Bash con git que modifica (vacío = no aplica).
dirs_de_bash() {
    local cmd="$1" cwd="$2" resto m
    # `git stash list/show` y `git merge-base` son de lectura: no cuentan.
    printf '%s' "$cmd" | grep -Eq '(^|[^[:alnum:]_-])git([[:space:]]+-C[[:space:]]+[^[:space:]]+)?[[:space:]]+((checkout|switch|reset|rebase|merge|commit|pull|cherry-pick|revert|restore|clean|am)([[:space:]]|$|[;&|)])|stash([[:space:]]+(push|pop|apply|drop|save|clear)|[[:space:]]*($|[;&|)])))' || return 0
    local hay=0 re='(^|[^[:alnum:]_-])(cd|-C)[[:space:]]+([^[:space:];&|)]+)'
    resto="$cmd"
    while [[ $resto =~ $re ]]; do
        m="${BASH_REMATCH[3]}"; m="${m%\"}"; m="${m#\"}"; m="${m%\'}"; m="${m#\'}"
        case "$m" in /*) ;; *) m="$cwd/$m" ;; esac
        echo "$m"; hay=1
        resto="${resto#*"${BASH_REMATCH[0]}"}"
    done
    [ "$hay" = 1 ] || echo "$cwd"
}

# Toma o renueva el candado de $raiz para $sesion. 0 = puede seguir; 2 = bloqueado (mensaje a stderr).
tomar() {
    local raiz="$1" sesion="$2" gitdir archivo ahora pid rama principal
    gitdir=$(git -C "$raiz" rev-parse --absolute-git-dir 2>/dev/null) || return 0
    archivo="$gitdir/$ARCHIVO"
    principal=$(principal_de "$raiz")
    ahora=$(date +%s)
    pid=$(pid_claude)
    rama=$(git -C "$raiz" branch --show-current 2>/dev/null)

    exec 9>"$archivo.flock" || return 0
    flock -w 3 9 || return 0

    if [ -s "$archivo" ]; then
        local dueno desde ultimo dpid drama vivo=1 inactivo
        dueno=$(jq -r '.sesion // empty' "$archivo" 2>/dev/null)
        if [ -n "$dueno" ] && [ "$dueno" != "$sesion" ]; then
            desde=$(jq -r '.desde // 0' "$archivo" 2>/dev/null)
            ultimo=$(jq -r '.ultimo // 0' "$archivo" 2>/dev/null)
            dpid=$(jq -r '.pid // empty' "$archivo" 2>/dev/null)
            drama=$(jq -r '.rama // empty' "$archivo" 2>/dev/null)
            if [ -n "$dpid" ] && ! kill -0 "$dpid" 2>/dev/null; then vivo=0; fi
            inactivo=$(( (ahora - ${ultimo:-0}) / 60 ))
            if [ "$vivo" = 1 ] && [ "$inactivo" -lt "$TTL_MIN" ]; then
                bitacora "$principal" "BLOQUEO $raiz: sesión ${sesion:0:8} chocó con ${dueno:0:8} (rama ${drama:-sin rama}, inactiva ${inactivo} min)"
                cat >&2 <<EOF
⛔ Candado de worktree (#502): $raiz lo está usando otra sesión de Claude (sesión ${dueno:0:8}, desde $(date -d "@$desde" '+%d-%b %H:%M' 2>/dev/null), último uso hace ${inactivo} min, rama ${drama:-sin rama}).
No cambies de rama ni edites en ese worktree. Trabaja en uno propio:
  php artisan circuito:provision-worktree --sesion=<nombre>   (crea /var/www/megaisp-<nombre>)
Si esa sesión ya terminó, suelta el candado:
  bash $principal/deploy/circuito/candado-worktree.sh soltar $raiz
EOF
                return 2
            fi
            bitacora "$principal" "TOMA-VENCIDO $raiz: ${sesion:0:8} toma el de ${dueno:0:8} ($([ "$vivo" = 0 ] && echo 'proceso terminado' || echo "inactivo ${inactivo} min"))"
            desde=$ahora
        else
            desde=$(jq -r '.desde // 0' "$archivo" 2>/dev/null)
            [ "$dueno" = "$sesion" ] || desde=$ahora
        fi
    else
        desde=$ahora
        bitacora "$principal" "TOMA $raiz: sesión ${sesion:0:8} (rama ${rama:-sin rama})"
    fi

    jq -n --arg s "$sesion" --arg r "$raiz" --arg rama "$rama" --arg u "$(whoami)" \
        --argjson d "${desde:-$ahora}" --argjson a "$ahora" --arg p "$pid" \
        '{sesion:$s, worktree:$r, rama:$rama, usuario:$u, desde:$d, ultimo:$a, pid:(if $p=="" then null else ($p|tonumber) end)}' \
        >"$archivo.tmp" 2>/dev/null && mv -f "$archivo.tmp" "$archivo"
    return 0
}

modo_hook() {
    local entrada sesion herramienta cwd ruta raiz r=0 vistos=" "
    entrada=$(cat) || return 0
    # Salida rápida (corre antes de CADA comando): un Bash sin «git» no puede mover un HEAD.
    case "$entrada" in
        *'"tool_name":"Bash"'* | *'"tool_name": "Bash"'*) [[ $entrada == *git* ]] || return 0 ;;
    esac
    IFS=$'\t' read -r sesion herramienta cwd < <(jq -r '[.session_id // "", .tool_name // "", .cwd // ""] | @tsv' <<<"$entrada" 2>/dev/null) || return 0
    [ -n "${sesion:-}" ] || return 0

    local dirs=""
    case "$herramienta" in
        Edit|Write|MultiEdit|NotebookEdit)
            ruta=$(jq -r '.tool_input.file_path // .tool_input.notebook_path // empty' <<<"$entrada" 2>/dev/null)
            [ -n "$ruta" ] && dirs=$(dirname "$ruta") ;;
        Bash)
            dirs=$(dirs_de_bash "$(jq -r '.tool_input.command // empty' <<<"$entrada" 2>/dev/null)" "${cwd:-$PWD}") ;;
        *) return 0 ;;
    esac

    while IFS= read -r d; do
        [ -n "$d" ] || continue
        raiz=$(raiz_de "$d")
        [ -n "$raiz" ] || continue
        case "$vistos" in *" $raiz "*) continue ;; esac
        vistos="$vistos$raiz "
        exento "$raiz" && continue
        tomar "$raiz" "$sesion" || r=$?
        [ "$r" = 2 ] && return 2
    done <<<"$dirs"
    return 0
}

archivos_de_candado() {
    local principal
    principal=$(principal_de "${1:-/var/www/megaisp}")
    [ -n "$principal" ] || return 0
    ls "$principal"/.git/worktrees/*/"$ARCHIVO" 2>/dev/null
}

modo_fin() {
    local sesion f
    sesion=$(jq -r '.session_id // empty' 2>/dev/null) || return 0
    [ -n "$sesion" ] || return 0
    for f in $(archivos_de_candado "${CLAUDE_PROJECT_DIR:-/var/www/megaisp}"); do
        [ "$(jq -r '.sesion // empty' "$f" 2>/dev/null)" = "$sesion" ] && rm -f "$f"
    done
    return 0
}

modo_estado() {
    local f n=0 ahora
    ahora=$(date +%s)
    for f in $(archivos_de_candado "${1:-/var/www/megaisp}"); do
        n=$((n + 1))
        jq -r --argjson a "$ahora" '"\(.worktree)  sesión \(.sesion[0:8])  rama \(.rama)  desde \(.desde | strflocaltime("%d-%b %H:%M"))  último uso hace \((($a - .ultimo) / 60) | floor) min  pid \(.pid // "?")"' "$f" 2>/dev/null
        local pid; pid=$(jq -r '.pid // empty' "$f" 2>/dev/null)
        [ -n "$pid" ] && ! kill -0 "$pid" 2>/dev/null && echo "    (la sesión ya terminó: el candado se considera vencido)"
    done
    [ "$n" = 0 ] && echo "Sin candados de worktree vigentes."
    return 0
}

modo_soltar() {
    local raiz gitdir
    raiz=$(raiz_de "${1:-}")
    [ -n "$raiz" ] || { echo "Uso: $0 soltar <ruta-del-worktree>" >&2; return 1; }
    gitdir=$(git -C "$raiz" rev-parse --absolute-git-dir 2>/dev/null) || return 1
    if [ -f "$gitdir/$ARCHIVO" ]; then
        rm -f "$gitdir/$ARCHIVO"
        bitacora "$(principal_de "$raiz")" "SOLTAR $raiz (a mano, $(whoami))"
        echo "Candado de $raiz soltado."
    else
        echo "$raiz no tenía candado."
    fi
}

case "${1:-}" in
    hook)   modo_hook; exit $? ;;
    fin)    modo_fin; exit 0 ;;
    estado) modo_estado "${2:-}"; exit 0 ;;
    soltar) modo_soltar "${2:-}"; exit $? ;;
    *)      sed -n '2,30p' "$0"; exit 0 ;;
esac

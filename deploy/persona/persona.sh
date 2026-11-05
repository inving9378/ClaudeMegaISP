#!/usr/bin/env bash
# Persona en commits y sesiones: separar el trabajo de cada quien en la cuenta compartida.
#
# Por qué: todos entran al servidor como `meganet` y todos los commits salían firmados
# "Irving MegaISP" (también los del circuito), así que el resumen del día de una persona mezclaba
# el trabajo de todos. Ahora cada sesión de Claude Code sabe de quién es:
#  - commits y merges salen con el autor de esa persona (GIT_AUTHOR_* / GIT_COMMITTER_*, que git
#    pone por encima de user.name/user.email);
#  - la sesión queda anotada en un registro para encontrar sus conversaciones después.
#
# De dónde sale la persona, en este orden:
#  1. MEGAISP_DEV — lo pone `claude-como <persona>`, el lanzador para quien entra por SSH.
#  2. La sesión tmux `web-<login_user>` de la terminal web (el login lo verificó Laravel antes de
#     abrirla). NO se usa MEGAISP_TERMINAL_USER: tmux la copia de su entorno global, así que las
#     sesiones nuevas traen el login de quien arrancó el servidor tmux, no el de quien entra.
#  3. Ninguna: la sesión pregunta "¿quién eres?" antes del primer commit y corre `declarar`.
#
# El hook (SessionStart) escribe en $CLAUDE_ENV_FILE, que Claude Code corre antes de cada comando
# Bash de la sesión: los exports de la persona, o —si no la sabe— un `source` del archivo que
# `declarar` crea después. Así lo declarado a media sesión firma todo lo que sigue.
#
#  - Fuera: los slots del circuito y el merge-runner (no son una persona sentada; firman como hoy).
#  - Falla ABIERTO: un error del script no bloquea la sesión; el commit saldría con la firma de
#    siempre.
#
# Mapa de personas, fuera del repo porque trae logins del sistema (el repo es público):
#   ~/.config/megaisp/personas.tsv   — una línea por persona, separada por TAB:
#   persona   nombre de autor   correo   logins de la terminal web (separados por coma)
#
# Uso:
#   persona.sh hook                        (SessionStart; JSON de Claude Code por stdin)
#   persona.sh lanzar <persona> [args]     (lo que corre `claude-como`)
#   persona.sh declarar <persona>          (desde la sesión de Claude: firma lo que siga)
#   persona.sh quien                       (qué persona y qué autor ve esta shell)
#   persona.sh sesiones <persona> [fecha]  (sesiones con actividad desde esa fecha y su transcript)
#   persona.sh commits <persona> [fecha]   (commits de esa persona ese día, en todas las ramas)

set -u

MAPA="${MEGAISP_PERSONAS:-$HOME/.config/megaisp/personas.tsv}"
ESTADO="${MEGAISP_PERSONA_ESTADO:-$HOME/.local/state/megaisp/persona}"
REGISTRO="$ESTADO/sesiones.tsv"
SCRIPT="/var/www/megaisp/deploy/persona/persona.sh"
EXENTOS="/home/meganet/circuito/:/home/meganet/worktrees/"

# Fila "persona<TAB>nombre<TAB>correo" buscando por la columna indicada; vacío si no está.
# Columna 1 = persona (sin distinguir mayúsculas); columna 4 = login exacto de la terminal web.
# Se buscan por separado a propósito: el login "David" (otra persona) no debe caer en "david".
fila_por() {
    local col="$1" buscado="$2"
    [ -n "$buscado" ] && [ -r "$MAPA" ] || return 0
    awk -F'\t' -v col="$col" -v b="$buscado" '
        /^[[:space:]]*#/ || NF < 3 { next }
        col == 1 && tolower($1) == tolower(b) { print $1 "\t" $2 "\t" $3; exit }
        col == 4 {
            n = split($4, logins, ",")
            for (i = 1; i <= n; i++) {
                gsub(/^[[:space:]]+|[[:space:]]+$/, "", logins[i])
                if (logins[i] == b) { print $1 "\t" $2 "\t" $3; exit }
            }
        }' "$MAPA"
}

personas() {
    [ -r "$MAPA" ] || { echo "(no hay mapa en $MAPA)"; return 0; }
    awk -F'\t' '!/^[[:space:]]*#/ && NF >= 3 { printf "%s%s", sep, $1; sep = ", " }' "$MAPA"
}

exports_de() {
    local persona nombre correo
    IFS=$'\t' read -r persona nombre correo <<<"$1"
    printf 'export MEGAISP_DEV=%q\n' "$persona"
    printf 'export GIT_AUTHOR_NAME=%q GIT_AUTHOR_EMAIL=%q\n' "$nombre" "$correo"
    printf 'export GIT_COMMITTER_NAME=%q GIT_COMMITTER_EMAIL=%q\n' "$nombre" "$correo"
}

# Login de la terminal web si esta shell vive en una sesión tmux `web-<login>`.
login_tmux() {
    [ -n "${TMUX:-}" ] && [ -n "${TMUX_PANE:-}" ] || return 0
    local s
    s=$(tmux display-message -p -t "$TMUX_PANE" '#S' 2>/dev/null) || return 0
    case "$s" in web-?*) printf '%s' "${s#web-}" ;; esac
}

registrar() {
    mkdir -p "$ESTADO" 2>/dev/null && chmod 700 "$ESTADO" 2>/dev/null
    printf '%s\t%s\t%s\t%s\t%s\n' "$(date '+%F %T')" "$1" "$2" "$3" "$4" >>"$REGISTRO" 2>/dev/null
}

cmd_hook() {
    local entrada sesion cwd fila="" origen="" login p persona nombre correo declarado
    entrada=$(cat 2>/dev/null)
    sesion=$(jq -r '.session_id // empty' <<<"$entrada" 2>/dev/null)
    cwd=$(jq -r '.cwd // empty' <<<"$entrada" 2>/dev/null)
    [ -n "$sesion" ] || exit 0
    IFS=':' read -r -a lista <<<"$EXENTOS"
    for p in "${lista[@]}"; do
        [ -n "$p" ] && case "$cwd/" in "$p"*) exit 0 ;; esac
    done

    declarado="$ESTADO/sesion-$sesion.sh"
    if [ -n "${MEGAISP_DEV:-}" ]; then
        fila=$(fila_por 1 "$MEGAISP_DEV"); origen="claude-como"
    elif [ -r "$declarado" ]; then
        # Sesión reanudada o compactada que ya se había declarado.
        fila=$(fila_por 1 "$(sed -n "s/^export MEGAISP_DEV=//p" "$declarado")"); origen="declarada"
    else
        login=$(login_tmux)
        if [ -n "$login" ]; then fila=$(fila_por 4 "$login"); origen="terminal-web"; fi
    fi

    if [ -n "$fila" ]; then
        [ -n "${CLAUDE_ENV_FILE:-}" ] && exports_de "$fila" >>"$CLAUDE_ENV_FILE"
        IFS=$'\t' read -r persona nombre correo <<<"$fila"
        registrar "$sesion" "$persona" "$origen" "$cwd"
        echo "Persona de esta sesión: $persona ($origen). Commits y merges salen firmados como \"$nombre <$correo>\"."
        exit 0
    fi

    [ -n "${CLAUDE_ENV_FILE:-}" ] && printf '[ -r %q ] && . %q\n' "$declarado" "$declarado" >>"$CLAUDE_ENV_FILE"
    registrar "$sesion" "-" "sin-declarar" "$cwd"
    # Una vuelta `claude -p` no tiene a quién preguntarle: firma como siempre, sin aviso.
    [ "${CLAUDE_CODE_ENTRYPOINT:-cli}" = "cli" ] && [ "${CLAUDE_CODE_SESSION_ATTENDED:-1}" != "0" ] || exit 0
    cat <<EOF
PERSONA SIN DECLARAR. Esta cuenta la usan varias personas y esta sesión no sabe de quién es (no se
lanzó con \`claude-como\` ni desde una terminal web con persona asignada). Antes del PRIMER commit,
merge o cualquier otra escritura en git,
pregunta quién está del otro lado (personas: $(personas)) y corre:
  bash $SCRIPT declarar <persona>
Sin eso el commit sale firmado como Irving y su trabajo se mezcla en el resumen del día de él.
EOF
}

cmd_lanzar() {
    local fila
    fila=$(fila_por 1 "${1:-}")
    if [ -z "$fila" ]; then
        echo "Uso: claude-como <persona> [opciones de claude]. Personas: $(personas)" >&2
        exit 1
    fi
    shift
    eval "$(exports_de "$fila")"
    exec claude "$@"
}

cmd_declarar() {
    local fila sesion="${CLAUDE_CODE_SESSION_ID:-}" persona nombre correo
    fila=$(fila_por 1 "${1:-}")
    [ -n "$fila" ] || { echo "No conozco a '${1:-}'. Personas: $(personas)" >&2; exit 1; }
    [ -n "$sesion" ] || { echo "Falta CLAUDE_CODE_SESSION_ID: corre esto desde una sesión de Claude Code." >&2; exit 1; }
    mkdir -p "$ESTADO" && chmod 700 "$ESTADO"
    exports_de "$fila" >"$ESTADO/sesion-$sesion.sh"
    IFS=$'\t' read -r persona nombre correo <<<"$fila"
    registrar "$sesion" "$persona" "declarada" "$PWD"
    echo "Sesión $sesion = $persona. Desde el siguiente comando, git firma como \"$nombre <$correo>\"."
    echo "Compruébalo con: git var GIT_AUTHOR_IDENT"
    echo "Si sigue saliendo otro autor: git -c user.name=$(printf %q "$nombre") -c user.email=$correo commit|merge …"
}

cmd_quien() {
    echo "persona: ${MEGAISP_DEV:-(sin declarar)}"
    echo "sesión tmux de la terminal web: $(login_tmux)"
    echo "autor de git: $(git var GIT_AUTHOR_IDENT 2>/dev/null | sed 's/ [0-9]* [-+][0-9]*$//')"
}

cmd_sesiones() {
    local p="${1:-}" desde="${2:-$(date +%F)}" s t
    [ -n "$p" ] || { echo "Uso: persona.sh sesiones <persona> [YYYY-MM-DD]" >&2; exit 1; }
    [ -r "$REGISTRO" ] || exit 0
    # Manda la ÚLTIMA persona anotada para cada sesión (una declarada a media sesión empieza "-").
    awk -F'\t' '{ ultima[$2] = $3 } END { for (s in ultima) print s "\t" ultima[s] }' "$REGISTRO" \
        | awk -F'\t' -v p="$p" 'tolower($2) == tolower(p) { print $1 }' \
        | while read -r s; do
            t=$(find "$HOME/.claude/projects" -maxdepth 2 -name "$s.jsonl" -newermt "$desde" 2>/dev/null | head -1)
            [ -n "$t" ] && printf '%s\t%s\t%s\n' "$(date -r "$t" '+%F %T')" "$s" "$t"
        done | sort
}

cmd_commits() {
    local fila p="${1:-}" dia="${2:-$(date +%F)}" persona nombre correo
    fila=$(fila_por 1 "$p")
    [ -n "$fila" ] || { echo "No conozco a '$p'. Personas: $(personas)" >&2; exit 1; }
    IFS=$'\t' read -r persona nombre correo <<<"$fila"
    git -C /var/www/megaisp log --all --since="$dia 00:00" --until="$dia 23:59:59" \
        --author="<$correo>" --date=format:'%H:%M' --format='%h %ad %s'
}

case "${1:-}" in
    hook) cmd_hook ;;
    lanzar) shift; cmd_lanzar "$@" ;;
    declarar) shift; cmd_declarar "$@" ;;
    quien) cmd_quien ;;
    sesiones) shift; cmd_sesiones "$@" ;;
    commits) shift; cmd_commits "$@" ;;
    *) sed -n '2,/^set -u/p' "$0" | sed '$d'; exit 1 ;;
esac

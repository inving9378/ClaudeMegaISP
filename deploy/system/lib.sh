#!/usr/bin/env bash
# =============================================================================
# deploy/system/lib.sh — ayudas para los scripts de sistema de los add-ons
# =============================================================================
# Un add-on que necesita algo instalado en el servidor (paquetes, servicios,
# configuración del servidor web...) lo declara en su module.json:
#
#   "system": {
#       "install": "system/install.sh",
#       "purge":   "system/purge.sh",
#       "description": "Qué instala en el servidor, en una línea"
#   }
#
# La página de add-ons (/admin/modules) corre install.sh al instalar el módulo y
# purge.sh al desinstalarlo sin conservar datos; también tiene los botones de
# "Dependencias del servidor" para correrlos sueltos.
# Desde una terminal:  php artisan module:system install|purge <slug>
#
# Reglas para escribir los scripts:
#   - Empezar con:  set -euo pipefail
#                   source "$MEGAISP_ROOT/deploy/system/lib.sh"
#   - Cada paso debe poder repetirse sin romper nada (comprobar antes de hacer).
#   - purge.sh deshace lo que puso install.sh, aunque la instalación haya quedado
#     a medias. Lo que comparten otros (Node, ffmpeg, módulos de Apache) NO se quita.
#   - Lo que necesita root va con `as_root`; lo demás corre como el usuario actual.
#   - Nunca imprimir secretos: la salida queda guardada y se ve en la página.
#   - `as_root` no le pasa la entrada estándar al comando: lo que el comando lea
#     de stdin llega vacío (usar archivos, o un heredoc dentro de `bash -c`).
#
# Variables que exporta el runner (php artisan module:system):
#   MEGAISP_ROOT            raíz del sistema
#   MEGAISP_MODULE_SLUG     slug del add-on
#   MEGAISP_MODULE_DIR      carpeta del add-on
#   MEGAISP_SYSTEM_RUN_DIR  carpeta de la corrida; vacía = corrida manual en terminal
#
# as_root CMD...  corre CMD como root por la vía más barata, en este orden:
#   1. ya somos root                         → directo
#   2. el usuario está en el grupo sudo:
#      a. sudo funciona sin contraseña        → sudo -n
#      b. si sudo pide contraseña             → pide SU contraseña → sudo
#      c. si sudo responde que no lo deja     → paso 3
#   3. no está en el grupo sudo               → pide la contraseña de root → su
#      (fuera del grupo ni se intenta sudo: cada intento de un usuario que no está
#      en sudoers queda registrado como incidente)
#   4. nada funcionó                          → error (código distinto de 0)
# La contraseña se pide una sola vez por corrida y se queda solo en memoria
# (variable sin exportar); no se escribe en disco, ni en el log, ni en argumentos.
# =============================================================================

_MS_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
_MS_VIA=""                 # sudo-n | sudo | su — se decide la primera vez que hace falta root
_MS_PW=""                  # contraseña ya aceptada (solo en memoria)
_MS_PW_IN=""               # contraseña recién tecleada, aún sin validar
# Mensajes de sudo/su en inglés para poder reconocerlos; UTF-8 para no romper acentos.
_MS_LOCALE="C.UTF-8"

ms_info() { printf '[sistema] %s\n' "$*"; }
ms_err()  { printf '[sistema] ERROR: %s\n' "$*" >&2; }

_ms_have() { command -v "$1" >/dev/null 2>&1; }

_ms_in_sudo_group() {
    local g
    for g in $(id -nG 2>/dev/null); do
        case "$g" in sudo|wheel|admin) return 0 ;; esac
    done
    return 1
}

# _ms_ask_password QUIEN VIA INTENTO — deja la contraseña en _MS_PW_IN.
# Con runner: avisa a la página (archivo need_password) y espera la respuesta por
# la FIFO de la corrida. Sin runner: la pide en la terminal. rc 1 = cancelado o sin respuesta.
_ms_ask_password() {
    local quien="$1" via="$2" intento="$3" linea=""
    _MS_PW_IN=""
    if [[ -n "${MEGAISP_SYSTEM_RUN_DIR:-}" ]]; then
        local fifo="$MEGAISP_SYSTEM_RUN_DIR/password.fifo"
        local aviso="$MEGAISP_SYSTEM_RUN_DIR/need_password"
        local fd
        [[ -p "$fifo" ]] || { ms_err "No existe el canal de la contraseña (${fifo})."; return 1; }
        # Lectura+escritura: abrir así no se bloquea aunque la página aún no escriba,
        # y permite que el read tenga timeout.
        exec {fd}<>"$fifo"
        printf 'user=%s\nvia=%s\nattempt=%s\n' "$quien" "$via" "$intento" > "${aviso}.tmp"
        mv -f "${aviso}.tmp" "$aviso"
        ms_info "Esperando la contraseña de '${quien}' en la página de add-ons..."
        if ! IFS= read -r -t "${MEGAISP_SYSTEM_PW_TIMEOUT:-900}" -u "$fd" linea; then
            exec {fd}>&-
            rm -f "$aviso"
            ms_err "Nadie escribió la contraseña a tiempo."
            return 1
        fi
        exec {fd}>&-
        rm -f "$aviso"
        case "$linea" in
            "PW "*) _MS_PW_IN="${linea#PW }" ;;
            *) ms_err "Cancelado desde la página."; return 1 ;;
        esac
    elif [[ -r /dev/tty && -w /dev/tty ]]; then
        IFS= read -r -s -p "Contraseña de ${quien}: " linea < /dev/tty || return 1
        printf '\n' > /dev/tty
        _MS_PW_IN="$linea"
    else
        ms_err "Hace falta la contraseña de '${quien}' y no hay página ni terminal para pedirla."
        return 1
    fi
    [[ -n "$_MS_PW_IN" ]] || { ms_err "La contraseña llegó vacía."; return 1; }
}

# _ms_resolve_password_via — pasos 3 y 4: deja _MS_VIA (sudo|su) y _MS_PW listos.
_ms_resolve_password_via() {
    local yo intento salida rc
    yo="$(id -un)"

    if _ms_have sudo && _ms_in_sudo_group; then
        for intento in 1 2 3; do
            _ms_ask_password "$yo" sudo "$intento" || return 1
            if salida="$(printf '%s\n' "$_MS_PW_IN" | LC_ALL="$_MS_LOCALE" sudo -S -k -p '' -v 2>&1)"; then
                _MS_PW="$_MS_PW_IN"; _MS_PW_IN=""; _MS_VIA=sudo
                ms_info "Contraseña de '${yo}' aceptada; se usa sudo."
                return 0
            fi
            _MS_PW_IN=""
            if grep -qiE 'not in the sudoers|may not run sudo|not allowed to|must have a tty' <<<"$salida"; then
                ms_info "'${yo}' está en el grupo sudo pero sudo no lo deja; se intenta con su (contraseña de root)."
                break
            fi
            ms_err "Contraseña de '${yo}' incorrecta (intento ${intento} de 3)."
            if [[ "$intento" -eq 3 ]]; then return 1; fi
        done
    else
        ms_info "'${yo}' no está en el grupo sudo; se escala con su (contraseña de root)."
    fi

    _ms_have python3 || { ms_err "Para escalar con su hace falta python3 en el servidor."; return 1; }
    for intento in 1 2 3; do
        _ms_ask_password root su "$intento" || return 1
        rc=0
        printf '%s\n' "$_MS_PW_IN" | python3 "$_MS_LIB_DIR/su-pty.py" -- true || rc=$?
        case "$rc" in
            0)
                _MS_PW="$_MS_PW_IN"; _MS_PW_IN=""; _MS_VIA=su
                ms_info "Contraseña de root aceptada; se usa su."
                return 0
                ;;
            125)
                _MS_PW_IN=""
                ms_err "Contraseña de root incorrecta (intento ${intento} de 3)."
                ;;
            *)
                _MS_PW_IN=""
                ms_err "su no funcionó (código ${rc}). Revisa que root tenga contraseña y que su esté permitido para '${yo}'."
                return 1
                ;;
        esac
    done
    return 1
}

# as_root CMD... — ver la cabecera.
as_root() {
    if (( $# == 0 )); then ms_err "as_root sin comando."; return 2; fi

    if [[ "$(id -u)" -eq 0 ]]; then
        "$@"
        return
    fi

    # 2a: una sola prueba barata. (`sudo -n -l CMD` no sirve para esto: responde
    # "permitido" aunque la regla que lo permite pida contraseña.)
    if [[ -z "$_MS_VIA" ]] && _ms_have sudo && _ms_in_sudo_group \
        && LC_ALL="$_MS_LOCALE" sudo -n true 2>/dev/null; then
        _MS_VIA=sudo-n
        ms_info "sudo funciona sin contraseña para '$(id -un)'."
    fi

    if [[ -z "$_MS_VIA" ]]; then
        if ! _ms_resolve_password_via; then
            ms_err "No se pudieron obtener permisos de root para: $*"
            return 1
        fi
    fi

    case "$_MS_VIA" in
        sudo-n) sudo -n -- "$@" ;;
        # -k: que sudo SIEMPRE lea la contraseña de stdin; si usara una credencial
        # en caché, la línea de la contraseña le llegaría como entrada al comando.
        sudo) printf '%s\n' "$_MS_PW" | LC_ALL="$_MS_LOCALE" sudo -S -k -p '' -- "$@" ;;
        su)   printf '%s\n' "$_MS_PW" | python3 "$_MS_LIB_DIR/su-pty.py" -- "$@" ;;
    esac
}

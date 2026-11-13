#!/usr/bin/env bash
# =============================================================================
# 50-bot-voz.sh — Servicio del asistente de voz «María» (megaisp-bot-voz)
# =============================================================================
# Se hace `source` desde install.sh / uninstall.sh (ya cargaron common.sh).
#
# María contesta las llamadas desde un proceso aparte (php artisan
# voip:bot-voz-escuchar, AudioSocket en 127.0.0.1:9099). El plan de marcado le
# pasa cada llamada entrante y, si el proceso no está corriendo, la manda directo
# a la cola. Sin este servicio María nunca atiende nada aunque esté habilitada.
#
#   - Unit de systemd con la ruta y el usuario web de ESTA instalación.
#   - Si la unit ya está igual y el servicio corre → OMITIDO.
#   - Respaldo de la unit anterior en $VOIP_BACKUP_DIR/systemd.
#   - Si después de arrancar no escucha en el puerto, se deshace lo hecho
#     (unit anterior restaurada o quitada) y se dice dónde ver el error.
#   - nc (netcat) es requisito: el plan de marcado lo usa para saber si María
#     está arriba antes de pasarle la llamada.
#   - Si María no queda arriba, NO detiene la instalación: deja BOT_VOZ_FALLA=1,
#     dice dónde ver el error y sigue (las llamadas van a las personas sin ella).
#     install.sh termina con error al final, después de la revisión.
# =============================================================================

: "${SYSTEMD_DIR:=/etc/systemd/system}"
: "${BOT_VOZ_SERVICIO:=megaisp-bot-voz}"
BOT_VOZ_FALLA=0

_bot_voz_unit_path() { printf '%s/%s.service' "${SYSTEMD_DIR%/}" "$BOT_VOZ_SERVICIO"; }
_bot_voz_host() { local v; v="$(get_env_value MEGAVOZ_BOT_VOZ_HOST)"; printf '%s' "${v:-127.0.0.1}"; }
_bot_voz_port() { local v; v="$(get_env_value MEGAVOZ_BOT_VOZ_PORT)"; printf '%s' "${v:-9099}"; }

_bot_voz_unit() {
    local php="$1"
    cat <<UNIT
# Generado por el instalador de VoIP de MegaISP (paso 50). Asistente de voz «María».
[Unit]
Description=MegaISP — asistente de voz María (voip:bot-voz-escuchar)
After=network.target mysql.service mariadb.service asterisk.service

[Service]
Type=simple
User=${MEGAISP_USER}
Group=$(megaisp_group)
WorkingDirectory=${MEGAISP_ROOT%/}
ExecStart=${php} ${MEGAISP_ROOT%/}/artisan voip:bot-voz-escuchar
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
UNIT
}

_bot_voz_escucha() {
    local port="$1" i
    for i in 1 2 3 4 5 6 7 8 9 10 11 12; do
        ss -ltn 2>/dev/null | grep -q "[:.]${port}[[:space:]]" && return 0
        sleep "${BOT_VOZ_ESPERA:-1}"
    done
    return 1
}

provision_bot_voz() {
    log_step "Asistente de voz (María) — servicio ${BOT_VOZ_SERVICIO}"

    if ! have nc; then
        log_info "Instalando netcat (el plan de marcado lo usa para saber si María está arriba)"
        if ! DEBIAN_FRONTEND=noninteractive apt-get install -y netcat-openbsd >/dev/null; then
            BOT_VOZ_FALLA=1
            log_err "No pude instalar netcat-openbsd; María se queda sin instalar. Lo demás sigue; vuelve a instalar cuando apt funcione."
            return 0
        fi
        log_ok "netcat instalado"
    fi

    local php; php="${PHP_BIN:-$(command -v php || true)}"
    if [[ -z "$php" ]]; then
        BOT_VOZ_FALLA=1
        log_err "No encuentro php en el PATH para arrancar el servicio de María (fija PHP_BIN=...). Lo demás sigue."
        return 0
    fi

    local unit; unit="$(_bot_voz_unit_path)"
    local nuevo; nuevo="$(_bot_voz_unit "$php")"
    local port; port="$(_bot_voz_port)"
    local cambiado=0 respaldo="" existia=0

    if [[ -f "$unit" ]]; then
        existia=1
    fi

    if [[ "$existia" == 1 && "$(cat "$unit")" == "$nuevo" ]]; then
        log_skip "unit ${unit} al día"
    else
        if [[ "$existia" == 1 ]]; then
            mkdir -p "${VOIP_BACKUP_DIR}/systemd" && chmod 700 "${VOIP_BACKUP_DIR}"
            respaldo="${VOIP_BACKUP_DIR}/systemd/${BOT_VOZ_SERVICIO}.service.$(date +%Y%m%d%H%M%S)"
            cp -p "$unit" "$respaldo"
            log_info "Respaldo de la unit anterior: ${respaldo}"
        fi
        mkdir -p "${SYSTEMD_DIR}"
        printf '%s\n' "$nuevo" > "$unit"
        chmod 644 "$unit"
        systemctl daemon-reload
        cambiado=1
        log_ok "Unit escrita: ${unit} (usuario ${MEGAISP_USER}, ${MEGAISP_ROOT})"
    fi

    systemctl enable "$BOT_VOZ_SERVICIO" >/dev/null 2>&1 || log_warn "No pude habilitar ${BOT_VOZ_SERVICIO} al arranque; revísalo con systemctl enable ${BOT_VOZ_SERVICIO}"

    if ! systemctl is-active --quiet "$BOT_VOZ_SERVICIO"; then
        log_info "Arrancando ${BOT_VOZ_SERVICIO}"
        systemctl start "$BOT_VOZ_SERVICIO" || true
    elif [[ "$cambiado" == 1 ]]; then
        log_info "Reiniciando ${BOT_VOZ_SERVICIO} con la unit nueva"
        systemctl restart "$BOT_VOZ_SERVICIO" || true
    else
        log_skip "${BOT_VOZ_SERVICIO} ya corre"
    fi

    if _bot_voz_escucha "$port"; then
        log_ok "María escucha en $(_bot_voz_host):${port}"
        return 0
    fi

    # No quedó escuchando: deshacer lo que este paso cambió y decir dónde mirar.
    log_err "${BOT_VOZ_SERVICIO} no quedó escuchando en el puerto ${port}."
    if [[ "$cambiado" == 1 ]]; then
        systemctl stop "$BOT_VOZ_SERVICIO" >/dev/null 2>&1 || true
        if [[ -n "$respaldo" ]]; then
            cp -p "$respaldo" "$unit"
            log_info "Unit anterior restaurada desde ${respaldo}"
        else
            systemctl disable "$BOT_VOZ_SERVICIO" >/dev/null 2>&1 || true
            rm -f "$unit"
            log_info "Unit nueva quitada"
        fi
        systemctl daemon-reload
    fi
    BOT_VOZ_FALLA=1
    log_err "María no arrancó. Mira el error con: journalctl -u ${BOT_VOZ_SERVICIO} -n 50   y   ${MEGAISP_ROOT%/}/storage/logs/megavoz-bot-voz.log. Lo demás de la central sigue; las llamadas van a las personas."
    return 0
}

quitar_bot_voz() {
    log_step "Asistente de voz (María) — quitar servicio ${BOT_VOZ_SERVICIO}"
    local unit; unit="$(_bot_voz_unit_path)"
    if [[ ! -f "$unit" ]]; then
        log_gone "${unit}"
        return 0
    fi
    systemctl disable --now "$BOT_VOZ_SERVICIO" >/dev/null 2>&1 || log_warn "No pude detener ${BOT_VOZ_SERVICIO}; sigue el retiro de la unit"
    rm -f "$unit"
    systemctl daemon-reload
    log_ok "Servicio ${BOT_VOZ_SERVICIO} detenido y quitado"
}

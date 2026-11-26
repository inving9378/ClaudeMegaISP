#!/usr/bin/env bash
# =============================================================================
# 55-campanas.sh — Lo que Campañas/Blaster necesita en el servidor
# =============================================================================
# Se hace `source` desde install.sh / uninstall.sh (ya cargaron common.sh).
#
# El blaster (módulo Campañas/Blaster) llama por la troncal del módulo y le lee al
# cliente una voz generada. Hasta el 25-nov nada de esto lo dejaba el instalador:
# la voz no tenía dónde escribirse (las llamadas salían mudas) y nadie actualizaba
# el estado de las llamadas (se quedaban en «marcando», sin reintentos).
#
#   - Carpeta de audios (COBRANZA_AUDIO_BASE_PATH; por omisión
#     /var/lib/asterisk/sounds/cobranza): asterisk:asterisk 2770, como las
#     grabaciones. La app escribe ahí (el usuario web es del grupo asterisk, paso
#     30) y Asterisk la lee.
#   - ffmpeg: convierte la voz a WAV para Asterisk.
#   - Servicio megaisp-campanas-ami (php artisan cobranza:ami-listener): escucha a
#     Asterisk y le pone a cada llamada su estado (contestada, ocupado, sin
#     contestar) y sus reintentos.
#
# Si el módulo Campañas/Blaster no está activo, se omite. Si algo falla NO detiene
# la instalación: deja CAMPANAS_FALLA=1, dice qué pasó y sigue (lo demás de la
# central no depende de esto). install.sh termina con error al final.
# =============================================================================

: "${SYSTEMD_DIR:=/etc/systemd/system}"
: "${SUPERVISOR_DIR:=/etc/supervisor/conf.d}"
: "${CAMPANAS_SERVICIO:=megaisp-campanas-ami}"
# Listener de la guía vieja (deploy/README-cobranza-blaster-prod.md), instalado a mano.
: "${CAMPANAS_SUPERVISOR_VIEJO:=megaisp-cobranza-ami}"
CAMPANAS_FALLA=0

_campanas_audio_dir() {
    local v; v="$(get_env_value COBRANZA_AUDIO_BASE_PATH)"
    v="${v:-/var/lib/asterisk/sounds/cobranza}"
    printf '%s' "${v%/}"
}
_campanas_unit_path() { printf '%s/%s.service' "${SYSTEMD_DIR%/}" "$CAMPANAS_SERVICIO"; }

_campanas_unit() {
    local php="$1"
    cat <<UNIT
# Generado por el instalador de VoIP de MegaISP (paso 55). Campañas/Blaster.
[Unit]
Description=MegaISP — estado de las llamadas de Campañas/Blaster (cobranza:ami-listener)
After=network.target mysql.service mariadb.service asterisk.service

[Service]
Type=simple
User=${MEGAISP_USER}
Group=$(megaisp_group)
WorkingDirectory=${MEGAISP_ROOT%/}
# Sale cada hora y systemd lo levanta otra vez: así toma el código de cada versión. Lo
# que pase en ese segundo lo recupera el blaster con el CDR.
ExecStart=${php} ${MEGAISP_ROOT%/}/artisan cobranza:ami-listener --max-seconds=3600
Restart=always
RestartSec=1

[Install]
WantedBy=multi-user.target
UNIT
}

# ¿Está activo el módulo? Su comando solo existe si lo está.
_campanas_modulo_activo() {
    local php="$1" lista
    # A variable y luego grep: con pipefail, `artisan list | grep -q` falla al azar
    # (grep cierra la tubería en cuanto encuentra y artisan muere por SIGPIPE).
    lista="$(cd "${MEGAISP_ROOT%/}" && run_as "$MEGAISP_USER" "$php" artisan list --raw 2>/dev/null)" || true
    grep -q '^cobranza:ami-listener' <<< "$lista"
}

_campanas_carpeta() {
    local dir; dir="$(_campanas_audio_dir)"
    if [[ "$dir" != /* || "$dir" == / || "$(dirname "$dir")" == / ]]; then
        log_err "La carpeta de audios de Campañas/Blaster «${dir}» no es una ruta válida (COBRANZA_AUDIO_BASE_PATH). Se deja sin tocar."
        return 1
    fi

    mkdir -p "$dir" || { log_err "No pude crear ${dir}."; return 1; }
    chown asterisk:asterisk "$dir" 2>/dev/null || log_warn "No pude dejar ${dir} a nombre de asterisk:asterisk."
    chmod 2770 "$dir" || { log_err "No pude fijar los permisos de ${dir}."; return 1; }
    # Los audios que ya estaban (de cuando nadie preparaba la carpeta): legibles para Asterisk.
    find "$dir" -maxdepth 1 -type f -name '*.wav' -exec chgrp asterisk {} + 2>/dev/null || true

    if ! run_as "$MEGAISP_USER" test -w "$dir"; then
        log_err "${MEGAISP_USER} no puede escribir en ${dir} (¿está en el grupo asterisk? lo hace el paso 30)."
        return 1
    fi
    log_ok "Carpeta de audios: ${dir} (asterisk:asterisk 2770, la app escribe y Asterisk lee)"
}

_campanas_ffmpeg() {
    local bin="${FFMPEG_BIN:-ffmpeg}"
    if have "$bin"; then
        log_skip "ffmpeg"
        return 0
    fi
    log_info "Instalando ffmpeg (convierte la voz de las campañas a WAV para Asterisk)"
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends ffmpeg >/dev/null 2>&1 || true
    if have "$bin"; then
        log_ok "ffmpeg instalado"
        return 0
    fi
    log_err "No pude instalar ffmpeg: sin él la voz de las campañas no se genera y no se marca a nadie."
    return 1
}

_campanas_servicio() {
    local php="$1"
    local unit; unit="$(_campanas_unit_path)"
    local nuevo; nuevo="$(_campanas_unit "$php")"
    local cambiado=0 respaldo=""

    if [[ -f "$unit" && "$(cat "$unit")" == "$nuevo" ]]; then
        log_skip "unit ${unit} al día"
    else
        if [[ -f "$unit" ]]; then
            mkdir -p "${VOIP_BACKUP_DIR}/systemd" && chmod 700 "${VOIP_BACKUP_DIR}"
            respaldo="${VOIP_BACKUP_DIR}/systemd/${CAMPANAS_SERVICIO}.service.$(date +%Y%m%d%H%M%S)"
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

    systemctl enable "$CAMPANAS_SERVICIO" >/dev/null 2>&1 || log_warn "No pude habilitar ${CAMPANAS_SERVICIO} al arranque."

    if ! systemctl is-active --quiet "$CAMPANAS_SERVICIO"; then
        log_info "Arrancando ${CAMPANAS_SERVICIO}"
        systemctl start "$CAMPANAS_SERVICIO" || true
    elif [[ "$cambiado" == 1 ]]; then
        log_info "Reiniciando ${CAMPANAS_SERVICIO} con la unit nueva"
        systemctl restart "$CAMPANAS_SERVICIO" || true
    else
        log_skip "${CAMPANAS_SERVICIO} ya corre"
    fi

    # Con Type=simple, is-active dice «active» en cuanto arranca, aunque artisan truene un
    # segundo después y systemd lo levante cada 5 s para siempre. Se da por bueno solo si
    # sigue activo y con el MISMO proceso tras unos segundos.
    if _campanas_estable; then
        log_ok "${CAMPANAS_SERVICIO} corriendo: las llamadas de campaña llevan su estado"
        return 0
    fi

    log_err "${CAMPANAS_SERVICIO} no quedó corriendo (se cae y systemd lo vuelve a levantar, o no arrancó)."
    if [[ "$cambiado" == 1 ]]; then
        systemctl stop "$CAMPANAS_SERVICIO" >/dev/null 2>&1 || true
        if [[ -n "$respaldo" ]]; then
            cp -p "$respaldo" "$unit"
            log_info "Unit anterior restaurada desde ${respaldo}"
        else
            systemctl disable "$CAMPANAS_SERVICIO" >/dev/null 2>&1 || true
            rm -f "$unit"
            log_info "Unit nueva quitada"
        fi
        systemctl daemon-reload
    fi
    log_err "Mira el error con: journalctl -u ${CAMPANAS_SERVICIO} -n 50"
    return 1
}

_campanas_pid() { systemctl show -p MainPID --value "$CAMPANAS_SERVICIO" 2>/dev/null || true; }

_campanas_estable() {
    sleep "${CAMPANAS_ESPERA:-2}"
    systemctl is-active --quiet "$CAMPANAS_SERVICIO" || return 1
    local antes; antes="$(_campanas_pid)"
    [[ -n "$antes" && "$antes" != 0 ]] || return 1
    sleep "${CAMPANAS_ESPERA_ESTABLE:-8}"
    systemctl is-active --quiet "$CAMPANAS_SERVICIO" || return 1
    [[ "$(_campanas_pid)" == "$antes" ]]
}

# El listener de la guía vieja corría bajo supervisor: con el servicio nuevo serían dos.
_campanas_quitar_supervisor_viejo() {
    local conf="${SUPERVISOR_DIR%/}/${CAMPANAS_SUPERVISOR_VIEJO}.conf"
    [[ -f "$conf" ]] || return 0
    mkdir -p "${VOIP_BACKUP_DIR}/supervisor" && chmod 700 "${VOIP_BACKUP_DIR}"
    local respaldo="${VOIP_BACKUP_DIR}/supervisor/${CAMPANAS_SUPERVISOR_VIEJO}.conf.$(date +%Y%m%d%H%M%S)"
    cp -p "$conf" "$respaldo"
    if have supervisorctl; then
        supervisorctl stop "$CAMPANAS_SUPERVISOR_VIEJO" >/dev/null 2>&1 || true
    fi
    rm -f "$conf"
    if have supervisorctl; then
        supervisorctl reread >/dev/null 2>&1 || true
        supervisorctl update >/dev/null 2>&1 || true
    fi
    log_ok "Quitado el listener viejo de supervisor (${CAMPANAS_SUPERVISOR_VIEJO}); respaldo en ${respaldo}. Ahora lo hace ${CAMPANAS_SERVICIO}."
}

provision_campanas() {
    log_step "Campañas/Blaster — audios, ffmpeg y servicio ${CAMPANAS_SERVICIO}"

    local php; php="${PHP_BIN:-$(command -v php || true)}"
    if [[ -z "$php" ]]; then
        CAMPANAS_FALLA=1
        log_err "No encuentro php en el PATH (fija PHP_BIN=...). Lo demás sigue."
        return 0
    fi

    if ! _campanas_modulo_activo "$php"; then
        log_skip "Campañas/Blaster no está activo en MegaISP; nada que preparar"
        return 0
    fi

    _campanas_carpeta  || CAMPANAS_FALLA=1
    _campanas_ffmpeg   || CAMPANAS_FALLA=1
    _campanas_quitar_supervisor_viejo
    _campanas_servicio "$php" || CAMPANAS_FALLA=1

    (( CAMPANAS_FALLA == 0 )) || log_err "Campañas/Blaster quedó incompleto (el motivo está arriba); lo demás de la central sigue."
    return 0
}

quitar_campanas() {
    log_step "Campañas/Blaster — quitar servicio ${CAMPANAS_SERVICIO}"
    local unit; unit="$(_campanas_unit_path)"
    if [[ ! -f "$unit" ]]; then
        log_gone "${unit}"
        return 0
    fi
    systemctl disable --now "$CAMPANAS_SERVICIO" >/dev/null 2>&1 || log_warn "No pude detener ${CAMPANAS_SERVICIO}; sigue el retiro de la unit"
    rm -f "$unit"
    systemctl daemon-reload
    log_ok "Servicio ${CAMPANAS_SERVICIO} detenido y quitado"
}

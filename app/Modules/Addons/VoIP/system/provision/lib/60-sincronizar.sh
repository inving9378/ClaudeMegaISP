#!/usr/bin/env bash
# =============================================================================
# 60-sincronizar.sh — MegaISP y Asterisk alineados, y revisión final de la central
# =============================================================================
# Se hace `source` desde install.sh (ya cargó common.sh y 50-bot-voz.sh). Va al
# final, con la central ya arriba y el usuario web ya en el grupo asterisk.
#
# provision_sincronizar
#   Lo mismo que el botón «Sincronizar con Asterisk» (VoIP → Extensiones):
#   php artisan voip:sincronizar --aplicar, como el usuario web. Da de alta en
#   Asterisk las extensiones y troncales activas que falten, quita las inactivas,
#   corrige las que difieran, deja las colas como los grupos de timbrado y
#   regenera y recarga el plan de marcado (con la salida a la calle).
#   Antes respalda la base realtime y las tablas voip_extensiones/voip_troncales
#   de MegaISP en $VOIP_BACKUP_DIR (700, solo root). Si algún cambio falla, los
#   demás quedan aplicados (son independientes); se listan las fallas y la
#   instalación termina con error DESPUÉS de la revisión final, para que se vea
#   todo lo demás.
#
# verificar_telefonia
#   Solo lee y avisa, nunca detiene: transporte para teléfonos de fuera,
#   registro de las troncales, ruta de salida a la calle, colas y si María
#   está lista (voip:bot-voz-probar --revisar, que no llama a OpenAI).
# =============================================================================

SINCRONIZAR_FALLAS=0
# Lo que la revisión final encontró que de verdad falta (el resumen lo lista; si
# queda vacío, dice «Nada pendiente»). MARIA_APAGADA: está lista pero nadie la
# ha encendido (decisión de negocio, no un pendiente técnico).
VERIF_PENDIENTES=()
MARIA_APAGADA=0

# _ast COMANDO — salida de la CLI de Asterisk (vacía si no responde).
_ast() { "$ASTERISK_BIN" -rx "$1" 2>/dev/null || true; }

# _artisan_web ARGS... — php artisan como el usuario web, desde la raíz de MegaISP.
_artisan_web() {
    local args; printf -v args '%q ' "$@"
    run_as "$MEGAISP_USER" bash -c "cd '${MEGAISP_ROOT}' && php artisan ${args}"
}

# _respaldar_tablas_megaisp — voip_extensiones y voip_troncales (lo que la
# sincronización escribe del lado de MegaISP). Imprime la ruta; si falla, aborta.
_respaldar_tablas_megaisp() {
    require mysqldump "Se requiere 'mysqldump' para respaldar antes de sincronizar"
    local db host port user pass cnf dump
    db="$(get_env_value DB_DATABASE)"
    es_identificador "$db" || die "DB_DATABASE='${db}' no es un nombre válido; no se sincronizó."
    host="$(get_env_value DB_HOST)"; host="${host:-127.0.0.1}"
    port="$(get_env_value DB_PORT)"; port="${port:-3306}"
    user="$(get_env_value DB_USERNAME)"
    pass="$(get_env_value DB_PASSWORD)"
    pass="${pass//\\/\\\\}"; pass="${pass//\"/\\\"}"

    mkdir -p "$VOIP_BACKUP_DIR"
    chmod 700 "$VOIP_BACKUP_DIR"
    dump="${VOIP_BACKUP_DIR}/${db}-voip-config-$(date +%Y%m%d%H%M%S)-antes-de-sincronizar.sql.gz"
    # Credenciales por archivo 600, nunca en argv.
    cnf="$(mktemp)"; chmod 600 "$cnf"
    printf '[client]\nuser=%s\npassword="%s"\nhost=%s\nport=%s\n' "$user" "$pass" "$host" "$port" > "$cnf"
    if ! bash -c 'set -o pipefail; umask 077; mysqldump --defaults-extra-file="$1" --single-transaction --no-tablespaces --skip-triggers "$2" voip_extensiones voip_troncales | gzip > "$3"' \
            _ "$cnf" "$db" "$dump"; then
        rm -f "$cnf" "$dump"
        die "No pude respaldar voip_extensiones y voip_troncales de '${db}'; no se sincronizó nada."
    fi
    rm -f "$cnf"
    gzip -t "$dump" || { rm -f "$dump"; die "El respaldo ${dump} salió dañado (gzip -t); no se sincronizó nada."; }
    printf '%s' "$dump"
}

provision_sincronizar() {
    log_step "Sincronizar MegaISP con Asterisk — extensiones, troncales, colas y plan de marcado"
    systemctl is-active --quiet asterisk \
        || die "Asterisk no está corriendo (systemctl status asterisk); no se puede sincronizar."

    local d1 d2 salida rc=0
    d1="$(respaldar_bd_realtime antes-de-sincronizar)"
    log_info "Respaldo de la base realtime: ${d1}"
    d2="$(_respaldar_tablas_megaisp)"
    log_info "Respaldo de extensiones y troncales de MegaISP: ${d2}"

    salida="$(_artisan_web voip:sincronizar --aplicar --no-ansi 2>&1)" || rc=$?
    printf '%s\n' "$salida" | sed 's/^/    /'

    if (( rc != 0 )); then
        SINCRONIZAR_FALLAS=1
        log_err "Algunos cambios fallaron (arriba, en «Fallaron»); los demás quedaron aplicados. Respaldos: ${d1} y ${d2}. Corrige lo que dice y vuelve a instalar, o usa «Sincronizar con Asterisk» en VoIP → Extensiones."
        return 0
    fi
    log_ok "MegaISP y Asterisk alineados"
}

verificar_telefonia() {
    log_step "Revisión final — lo que ve Asterisk"
    local bind txt linea nombre estado n rc=0

    bind="$(get_env_value ASTERISK_BIND_EXTERNO)"
    if [[ -n "$bind" ]]; then
        txt="$(_ast 'pjsip show transports')"
        if grep -q 'transport-externo' <<<"$txt"; then
            log_ok "Teléfonos de fuera: transport-externo escucha en ${bind}"
        else
            log_warn "ASTERISK_BIND_EXTERNO=${bind}, pero Asterisk no levantó transport-externo. Si existe /etc/asterisk/pjsip.conf.nuevo, alguien editó pjsip.conf a mano: revisa el diff, aplícalo y reinicia Asterisk (systemctl restart asterisk)."
            VERIF_PENDIENTES+=("Teléfonos de fuera: Asterisk no levantó transport-externo (${bind}); ver el aviso de la revisión.")
        fi
    fi

    txt="$(_ast 'pjsip show registrations' | grep -E '^[[:space:]]*[^[:space:]<=]+/sip:' || true)"
    if [[ -z "$txt" ]]; then
        log_warn "Ninguna troncal está registrada con su proveedor: sin eso no entran ni salen llamadas de la calle."
        VERIF_PENDIENTES+=("Ninguna troncal registrada con el proveedor: en VoIP → Troncales, la del proveedor debe estar activa, con su usuario, contraseña y servidor.")
    else
        while read -r linea; do
            [[ -n "$linea" ]] || continue
            nombre="${linea%%/*}"
            estado="$(awk '{print $3}' <<<"$linea")"
            if [[ "$estado" == Registered ]]; then
                log_ok "Troncal ${nombre}: registrada"
            else
                log_warn "Troncal ${nombre}: ${estado:-sin estado}. Revisa usuario, contraseña y servidor en VoIP → Troncales."
                VERIF_PENDIENTES+=("Troncal ${nombre} no registrada (${estado:-sin estado}): usuario, contraseña y servidor en VoIP → Troncales.")
            fi
        done <<<"$txt"
    fi

    txt="$(_ast 'dialplan show _[2-9]XXXXXXXXX@from-internal')"
    if grep -q 'Dial(' <<<"$txt"; then
        nombre="$(sed -n 's/.*Dial(PJSIP\/\([^/,]*\).*/\1/p' <<<"$txt" | head -n1)"
        log_ok "Llamadas a la calle (10 dígitos): salen por ${nombre}"
    else
        log_warn "No hay salida a la calle: falta una troncal activa con dirección «saliente» o «ambas» en VoIP → Troncales."
        VERIF_PENDIENTES+=("Sin salida a la calle: una troncal activa con dirección «saliente» o «ambas» en VoIP → Troncales.")
    fi

    n="$(_ast 'queue show' | grep -cE '^[^[:space:]]+ has [0-9]+ calls' || true)"
    log_info "Colas en Asterisk: ${n:-0}"

    txt="$(_artisan_web voip:bot-voz-probar --revisar --no-ansi 2>&1)" || rc=$?
    printf '%s\n' "$txt" | sed 's/^/    /'
    if (( rc == 0 )); then
        log_ok "María tiene lo que necesita"
    else
        log_warn "A María le falta lo de arriba. Las llamadas siguen llegando a las personas."
        VERIF_PENDIENTES+=("María: lo que marca ✘ en la revisión (clave de IA en Integraciones → Módulos IA).")
    fi
    grep -q 'Apagada' <<<"$txt" && MARIA_APAGADA=1
    return 0
}

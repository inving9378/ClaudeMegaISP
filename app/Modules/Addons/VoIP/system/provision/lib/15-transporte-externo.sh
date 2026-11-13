#!/usr/bin/env bash
# =============================================================================
# 15-transporte-externo.sh — Teléfonos fuera de la oficina (Zoiper, celulares)
# =============================================================================
# Se hace `source` desde install.sh (ya cargó common.sh). Define
# provision_transporte_externo. Va ANTES del paso 20: lo que fija aquí en el .env
# lo usa el provisionador al generar pjsip.conf y la central lo levanta al
# reiniciarse.
#
# El caso que resuelve (visto en test, 2026-11-12): el servidor sale a internet
# por una IP privada con NAT del router y ADEMÁS tiene una IP pública propia en
# la interfaz. Un teléfono de fuera le habla a la pública, pero Asterisk
# (escuchando en 0.0.0.0) contesta por la ruta normal, con la IP del NAT; el
# router del teléfono tira esa respuesta y el teléfono da «408 Request Timeout».
# La solución es un transporte SIP amarrado a la IP pública
# (ASTERISK_BIND_EXTERNO=IP:puerto → [transport-externo] en pjsip.conf); las
# extensiones que se configuren con ese transporte contestan y mandan el audio
# desde esa misma IP.
#
# Qué decide, sin preguntar:
#   - ASTERISK_BIND_EXTERNO ya está en el .env  → se respeta (OMITIDO); solo avisa
#     si esa IP ya no está en ninguna interfaz.
#   - El servidor sale con su propia IP pública → no hace falta (el 5060 basta).
#   - Sale por IP privada y tiene UNA IP pública en la interfaz → la fija con el
#     puerto $VOIP_PUERTO_EXTERNO (5080).
#   - Solo IP privada, o varias públicas → no adivina: lo dice y sigue.
# Overrides a mano (sudo env): VOIP_BIND_EXTERNO=IP:puerto | VOIP_BIND_EXTERNO=no
#
# Este paso nunca detiene la instalación: si no puede decidir, lo dice y sigue.
# Respaldo del .env antes de escribirlo.
# =============================================================================

: "${VOIP_PUERTO_EXTERNO:=5080}"

# _es_ip_privada IP — RFC 1918, CGNAT (100.64/10), loopback y link-local.
_es_ip_privada() {
    local a b
    IFS=. read -r a b _ _ <<<"$1"
    [[ "$a" =~ ^[0-9]+$ && "$b" =~ ^[0-9]+$ ]] || return 0   # lo que no se entiende, no se publica
    (( a == 10 || a == 127 )) && return 0
    (( a == 192 && b == 168 )) && return 0
    (( a == 172 && b >= 16 && b <= 31 )) && return 0
    (( a == 169 && b == 254 )) && return 0
    (( a == 100 && b >= 64 && b <= 127 )) && return 0
    return 1
}

# _ip_de_salida — IP con la que el servidor sale a internet (vacío si no se sabe).
_ip_de_salida() {
    ip -4 route get 1.1.1.1 2>/dev/null | sed -n 's/.* src \([0-9.]*\).*/\1/p' | head -n1
}

# _ips_locales — IPv4 globales de las interfaces, una por línea.
_ips_locales() {
    ip -4 -o addr show scope global 2>/dev/null | awk '{ sub(/\/.*/, "", $4); print $4 }'
}

_ips_publicas_locales() {
    local x
    while read -r x; do
        [[ -n "$x" ]] && ! _es_ip_privada "$x" && printf '%s\n' "$x"
    done < <(_ips_locales)
}

_ip_es_local() { _ips_locales | grep -qxF "$1"; }

# _bind_valido IP:PUERTO
_bind_valido() {
    [[ "$1" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}:[0-9]{1,5}$ ]] || return 1
    local p="${1##*:}"
    (( p >= 1 && p <= 65535 ))
}

# _udp_ocupado_por IP PUERTO — programa que ya escucha en ese UDP (vacío si nadie).
_udp_ocupado_por() {
    ss -H -lunp 2>/dev/null \
        | awk -v a="$1:$2" -v b="0.0.0.0:$2" -v c="*:$2" '$4 == a || $4 == b || $4 == c' \
        | sed -n 's/.*users:(("\([^"]*\)".*/\1/p' | head -n1
}

provision_transporte_externo() {
    log_step "Teléfonos fuera de la oficina — transporte SIP en la IP pública"
    if ! have ip; then
        log_warn "No encuentro el comando 'ip' (iproute2); no puedo revisar la red. Se omite este paso."
        return 0
    fi

    local actual pedido="${VOIP_BIND_EXTERNO:-}" valor="" salida
    actual="$(get_env_value ASTERISK_BIND_EXTERNO)"

    if [[ "$pedido" == no ]]; then
        log_info "VOIP_BIND_EXTERNO=no: no se toca (ASTERISK_BIND_EXTERNO='${actual}')."
        return 0
    fi

    if [[ -n "$actual" && ( -z "$pedido" || "$pedido" == "$actual" ) ]]; then
        if _ip_es_local "${actual%:*}"; then
            log_skip "ASTERISK_BIND_EXTERNO=${actual}"
        else
            log_warn "ASTERISK_BIND_EXTERNO=${actual}, pero esa IP no está en ninguna interfaz de este servidor: Asterisk no podrá escuchar ahí. Corrígelo en el .env (o bórralo) y vuelve a instalar."
        fi
        return 0
    fi

    if [[ -n "$pedido" ]]; then
        _bind_valido "$pedido" || die "VOIP_BIND_EXTERNO='${pedido}' no tiene la forma IP:puerto (p. ej. 203.0.113.10:5080); no se tocó nada."
        _ip_es_local "${pedido%:*}" || die "VOIP_BIND_EXTERNO=${pedido}: esa IP no está en ninguna interfaz de este servidor; no se tocó nada."
        valor="$pedido"
    else
        salida="$(_ip_de_salida)"
        if [[ -z "$salida" ]]; then
            log_warn "No pude saber por qué IP sale el servidor a internet (ip route get); se omite. Si hace falta: ASTERISK_BIND_EXTERNO=IP_PUBLICA:${VOIP_PUERTO_EXTERNO} en el .env y vuelve a instalar."
            return 0
        fi
        if ! _es_ip_privada "$salida"; then
            log_ok "El servidor sale a internet con su propia IP pública (${salida}): los teléfonos de fuera entran por el 5060; no hace falta otro transporte."
            return 0
        fi
        local -a publicas=()
        mapfile -t publicas < <(_ips_publicas_locales)
        case "${#publicas[@]}" in
            0)
                log_info "El servidor solo tiene IP privada (${salida}). Para teléfonos de fuera hacen falta reenvío de puertos en el router y ASTERISK_EXTERNAL_MEDIA/SIGNALING; este paso no aplica."
                return 0 ;;
            1)
                valor="${publicas[0]}:${VOIP_PUERTO_EXTERNO}"
                log_info "Sale a internet por ${salida} (privada, con NAT) y tiene la IP pública ${publicas[0]} en la interfaz: sin un transporte amarrado a ella, los teléfonos de fuera dan «408 Request Timeout»." ;;
            *)
                log_warn "El servidor tiene varias IP públicas (${publicas[*]}) y no adivino cuál usan los teléfonos. Para fijarla: ASTERISK_BIND_EXTERNO=IP:${VOIP_PUERTO_EXTERNO} en el .env y vuelve a instalar."
                return 0 ;;
        esac
    fi

    local quien
    quien="$(_udp_ocupado_por "${valor%:*}" "${valor##*:}")"
    if [[ -n "$quien" && "$quien" != asterisk ]]; then
        log_warn "El puerto ${valor##*:}/udp ya lo usa '${quien}'; no se fija el transporte. Libéralo (o usa VOIP_BIND_EXTERNO=IP:otro_puerto) y vuelve a instalar."
        return 0
    fi

    local bak; bak="$(respaldar_env bind-externo)"
    upsert_env_var "$MEGAISP_ENV" ASTERISK_BIND_EXTERNO "$valor"
    log_ok "ASTERISK_BIND_EXTERNO=${valor} en el .env (respaldo: ${bak}). El paso siguiente genera [transport-externo] y reinicia la central."
    log_info "En VoIP → Extensiones, a los teléfonos de fuera ponles el transporte «transport-externo» y en el teléfono el servidor ${valor}."
}

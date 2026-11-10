#!/usr/bin/env bash
# =============================================================================
# install.sh — Asterisk para el add-on VoIP (nativo, compilado), idempotente
# =============================================================================
# Deja la central de MegaISP lista en un servidor de PRODUCCIÓN:
#   10 base realtime `asterisk` + usuario MySQL `asterisk_rt` (y sus ASTERISK_RT_DB_*
#      en el .env de MegaISP)
#   20 Asterisk vía `php artisan voip:provisionar` (descarga verificada por sha256,
#      compilación, Alembic, configuración, credenciales AMI/ARI, plan de numeración,
#      arranque y validación)
#   30 el usuario web en el grupo `asterisk` (config generada y grabaciones)
#   40 /ws en el vhost de MegaISP (nginx o Apache) para el mini-teléfono web
#   cierre: config:clear + queue:restart y verificación (CLI, ARI y /ws)
#
# NO instala MySQL (se asume presente) ni el servidor TURN (coturn) del
# mini-teléfono: eso depende de la IP pública y de los puertos del router.
#
# Lo normal es que lo corra la página de add-ons (VoIP → Dependencias del
# servidor → Instalar), vía app/Modules/Addons/VoIP/system/install.sh. A mano:
#   sudo env ALLOW_PROVISION=1 MEGAISP_ROOT=/var/www/ClaudeMegaISP \
#        bash app/Modules/Addons/VoIP/system/provision/install.sh
#
# Overrides (vía `sudo env`, que es lo que sobrevive a env_reset):
#   MEGAISP_ROOT / MEGAISP_USER      raíz de MegaISP / usuario web (www-data)
#   WEB_SERVER=nginx|apache          forzar el servidor web
#   NGINX_VHOST / APACHE_VHOST       forzar el vhost
#   SKIP_WEB_PROXY=1                 plan B: omitir /ws (el resto funciona)
#   ALLOW_PROVISION=1                salta SOLO el check de hostname (prod comparte
#                                    'meganet' con dev); APP_URL sigue abortando en dev
#   VOIP_FORCE_ROTATE_DB_PASSWORD=1  rota el password MySQL de asterisk_rt a juego
#                                    con el .env cuando no coinciden (si no, aborta)
#
# Paso por paso: cada paso dice OMITIDO / [ok] / [XX]. Si uno falla, deshace lo
# suyo, se detiene y deja el motivo a la vista; reintentar retoma desde ahí.
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"
# shellcheck source=lib/10-base-realtime.sh
source "${SCRIPT_DIR}/lib/10-base-realtime.sh"
# shellcheck source=lib/20-asterisk.sh
source "${SCRIPT_DIR}/lib/20-asterisk.sh"
# shellcheck source=lib/30-acceso-web.sh
source "${SCRIPT_DIR}/lib/30-acceso-web.sh"
# shellcheck source=lib/40-proxy-ws.sh
source "${SCRIPT_DIR}/lib/40-proxy-ws.sh"

# --- Preflight ---------------------------------------------------------------
# _quien_escucha PROTO PUERTO — proceso que escucha en ese puerto (vacío si nadie).
_quien_escucha() {
    local flag=-ltnp; [[ "$1" == udp ]] && flag=-lunp
    ss -H "$flag" "sport = :$2" 2>/dev/null | sed -n 's/.*users:(("\([^"]*\)".*/\1/p' | head -n1
}

preflight() {
    log_step "Preflight"
    is_root || die "Se requieren privilegios de root. Corre: sudo env ALLOW_PROVISION=1 bash ${BASH_SOURCE[0]}"
    [[ -f "$MEGAISP_ENV" ]] || die "No encuentro el .env del sistema en ${MEGAISP_ENV} (fija MEGAISP_ROOT)."
    [[ -f "${MEGAISP_ROOT}/artisan" ]] || die "No encuentro ${MEGAISP_ROOT}/artisan (fija MEGAISP_ROOT)."

    abort_if_dev_server

    have apt-get   || die "Este instalador usa apt-get (Debian/Ubuntu); no lo encuentro en el servidor."
    have systemctl || die "Se requiere systemd (systemctl) para el servicio de Asterisk."
    require php    "Se requiere php (CLI)"
    require mysql  "Se requiere el cliente 'mysql' (MySQL ya debe estar instalado)"
    id "$MEGAISP_USER" >/dev/null 2>&1 || die "No existe el usuario web '${MEGAISP_USER}' (fíjalo con MEGAISP_USER=...)."

    # Puertos: si otro programa ya los tiene, Asterisk arrancaría sin poder
    # escuchar y la validación fallaría al final de media hora de compilación.
    local quien
    for par in udp:5060 "tcp:$(voip_ari_port)"; do
        quien="$(_quien_escucha "${par%%:*}" "${par#*:}")"
        if [[ -n "$quien" && "$quien" != asterisk ]]; then
            die "El puerto ${par#*:}/${par%%:*} ya lo usa '${quien}'. Asterisk lo necesita; libéralo (o cambia ASTERISK_ARI_PORT) y reintenta."
        fi
    done

    log_ok "Preflight OK — MegaISP en ${MEGAISP_ROOT}, usuario web '${MEGAISP_USER}'"
}

# --- Cierre ------------------------------------------------------------------
_ari_codigo() {
    local u p
    u="$(get_env_value ASTERISK_ARI_USER)"; u="${u:-medussa}"
    p="$(get_env_value ASTERISK_ARI_PASS)"
    p="${p//\\/\\\\}"; p="${p//\"/\\\"}"
    # Credenciales por stdin (-K -), nunca en argv.
    printf 'user = "%s:%s"\n' "$u" "$p" \
        | curl -s -o /dev/null -w '%{http_code}' -m 10 -K - "http://127.0.0.1:$(voip_ari_port)/ari/asterisk/info" 2>/dev/null \
        || echo 000
}

finalize() {
    log_step "Cierre — refrescar config de MegaISP y verificación"
    # NADA de config:cache (item #520): MegaISP lee env() en runtime.
    log_info "php artisan config:clear + queue:restart (usuario ${MEGAISP_USER})"
    run_as "$MEGAISP_USER" bash -c "cd '${MEGAISP_ROOT}' && php artisan config:clear && php artisan queue:restart" \
        || die "No pude refrescar la config de MegaISP como ${MEGAISP_USER}. Asterisk quedó instalado; córrelo a mano: php artisan config:clear && php artisan queue:restart"
    log_ok "Config de MegaISP refrescada (las colas toman el grupo asterisk al reiniciar)"

    local v code host
    v="$("$ASTERISK_BIN" -rx 'core show version' 2>/dev/null | head -n1 || true)"
    if [[ "$v" == Asterisk* ]]; then log_ok "La central responde: ${v}"; else log_warn "La central no respondió por su CLI; revisa: systemctl status asterisk"; fi

    if have curl; then
        code="$(_ari_codigo)"
        if [[ "$code" == 200 ]]; then
            log_ok "ARI responde en 127.0.0.1:$(voip_ari_port) con las credenciales del .env (HTTP 200)"
        else
            log_warn "ARI respondió HTTP ${code} (se esperaba 200): MegaISP no podrá consultar la central. Revisa ASTERISK_ARI_* en el .env y /etc/asterisk/ari.conf"
        fi
        if [[ "${SKIP_WEB_PROXY:-}" != "1" ]]; then
            host="$(get_env_value APP_URL)"; host="${host#*://}"; host="${host%%/*}"; host="${host%%:*}"
            # --resolve: que entre al vhost de MegaISP (SNI correcto) sin salir a la red.
            code="$(curl -sk -o /dev/null -w '%{http_code}' -m 10 --resolve "${host}:443:127.0.0.1" "https://${host}/ws" 2>/dev/null || true)"
            [[ -z "$code" || "$code" == 000 ]] && code="$(curl -s -o /dev/null -w '%{http_code}' -m 10 --resolve "${host}:80:127.0.0.1" "http://${host}/ws" 2>/dev/null || true)"
            code="${code:-000}"
            case "$code" in
                400|426) log_ok "/ws llega a Asterisk por el servidor web (HTTP ${code} sin WebSocket = correcto)" ;;
                *)       log_warn "/ws respondió HTTP ${code} (se esperaba 426/400): el mini-teléfono no podrá registrarse. Revisa el bloque /ws del vhost." ;;
            esac
        fi
    fi
}

summary() {
    log_step "Resumen"
    printf '  %s\n' "Asterisk $("$ASTERISK_BIN" -V 2>/dev/null | sed 's/^Asterisk //' || echo '?') instalado como servicio (arranca en boot)."
    printf '  %s\n' "Base realtime: $(voip_db_name) (usuario $(voip_db_user)); credenciales en el .env de MegaISP (no se muestran)."
    printf '  %s\n' "Grabaciones: $(voip_grabaciones_dir)"
    printf '\n  %s\n' "Pendiente a mano (no lo puede hacer este script):"
    printf '    - %s\n' "Troncales: en VoIP → Troncales, «Provisionar» cada una (y verificar que registre)."
    [[ -n "$(get_env_value ASTERISK_EXTERNAL_MEDIA)" ]] \
        || printf '    - %s\n' "ASTERISK_EXTERNAL_MEDIA/SIGNALING vacíos: si el servidor está detrás de NAT, el audio con la troncal puede ir en un solo sentido."
    [[ -n "$(get_env_value MEGAVOZ_TURN_USERNAME)" ]] \
        || printf '    - %s\n' "Sin TURN (MEGAVOZ_TURN_*): el mini-teléfono web puede quedarse sin audio fuera de la red local."
    printf '    - %s\n' "SIP escucha en 5060/udp de todas las interfaces: no abras ese puerto en el router sin firewall (fraude telefónico)."
}

main() {
    log_step "== Instalación de Asterisk para VoIP (idempotente) =="
    preflight
    provision_base_realtime   # 10 — base + usuario MySQL + ASTERISK_RT_DB_*
    provision_asterisk        # 20 — php artisan voip:provisionar
    provision_acceso_web      # 30 — usuario web en el grupo asterisk
    provision_proxy_ws        # 40 — /ws en el vhost (nginx o Apache)
    finalize                  # cierre — config + verificación
    summary
    log_ok "Listo."
}

main "$@"

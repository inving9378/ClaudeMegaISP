#!/usr/bin/env bash
# =============================================================================
# install.sh — Provisioning idempotente de Evolution API (NATIVO, sin Docker)
# =============================================================================
# Replica la Evolution de dev en un server nuevo o en producción:
#   Node 20 + PM2 · Evolution v2.3.7 (commit fa09d378) · MySQL 8.4 + Prisma ·
#   nginx `location /evolution/` · arranque en boot.
#
# NO instala MySQL (se asume presente). NO gestiona Asterisk (ver TODO abajo).
#
# Uso (correr como root o con sudo, en un server que NO sea el de dev):
#   sudo bash deploy/provision/install.sh
#
# Overrides por variable de entorno (opcionales):
#   MEGAISP_ROOT=/var/www/megaisp   # raíz del sistema MegaISP
#   MEGAISP_USER=www-data           # usuario que corre artisan (dueño de caché)
#   EVOLUTION_USER=<usuario>        # dueño/ejecutor de Evolution (default: 'evolution', usuario de sistema dedicado)
#   NGINX_VHOST=/ruta/al/vhost      # forzar el vhost si la autodetección falla
#   ALLOW_PROVISION=1               # override consciente: salta SOLO el check de
#                                   #   hostname de la guarda anti-dev (prod comparte
#                                   #   'meganet' con dev). Las demás guardas siguen
#                                   #   activas (APP_URL sigue abortando en dev).
#
# Idempotente: correrlo 2 veces NO rompe ni duplica nada (cada paso detecta y omite).
# =============================================================================
set -euo pipefail

# --- Cargar helpers y pasos --------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"
# shellcheck source=lib/05-evolution-user.sh
source "${SCRIPT_DIR}/lib/05-evolution-user.sh"
# shellcheck source=lib/10-node-pm2.sh
source "${SCRIPT_DIR}/lib/10-node-pm2.sh"
# shellcheck source=lib/20-evolution.sh
source "${SCRIPT_DIR}/lib/20-evolution.sh"
# shellcheck source=lib/30-nginx.sh
source "${SCRIPT_DIR}/lib/30-nginx.sh"
# shellcheck source=lib/40-pm2-boot.sh
source "${SCRIPT_DIR}/lib/40-pm2-boot.sh"
# TODO(futuro): source "${SCRIPT_DIR}/lib/50-asterisk.sh"  # Asterisk se maneja
#   aparte (nativo, ya existe VoiceGateway). NO forma parte de este install.sh.

# Usuario bajo el que se corre `php artisan` en MegaISP (dueño de bootstrap/cache).
: "${MEGAISP_USER:=www-data}"

# run_as USER CMD... — ejecuta un comando como USER (para artisan como www-data).
run_as() {
    local u="$1"; shift
    if [[ "$(id -un)" == "$u" ]]; then
        "$@"
    elif is_root; then
        sudo -u "$u" -H "$@"
    elif have sudo; then
        sudo -n -u "$u" -H "$@"
    else
        die "No puedo ejecutar como '$u' (falta sudo): $*"
    fi
}

# --- Preflight ---------------------------------------------------------------
preflight() {
    log_step "Preflight"

    # Privilegios: varios pasos necesitan root (apt, /opt, mysql, nginx, systemd).
    if ! is_root && ! sudo -n true 2>/dev/null; then
        die "Se requieren privilegios de root. Corre: sudo bash ${BASH_SOURCE[0]}"
    fi

    # El .env del sistema MegaISP es la fuente de verdad de los valores por-server.
    [[ -f "$MEGAISP_ENV" ]] || die "No encuentro el .env del sistema en ${MEGAISP_ENV} (fija MEGAISP_ROOT)."

    # Guarda de seguridad: JAMÁS reconfigurar la Evolution del server de dev.
    abort_if_dev_server

    log_ok "Preflight OK — MegaISP en ${MEGAISP_ROOT}, usuario Evolution '${EVOLUTION_USER}'"
}

# --- Cierre: refrescar config de MegaISP + health check ----------------------
finalize() {
    log_step "Cierre — refrescar config de MegaISP y verificación"

    # Que Laravel tome la WHATSAPP_API_KEY nueva: limpiar y re-cachear config.
    log_info "php artisan config:clear && config:cache (usuario ${MEGAISP_USER})"
    run_as "$MEGAISP_USER" bash -c "cd '${MEGAISP_ROOT}' && php artisan config:clear && php artisan config:cache"
    log_ok "Config de MegaISP refrescada"

    # Warm-up / health check de Evolution (no fatal): ¿responde en local?
    if have curl; then
        local code
        code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${EVOLUTION_PORT}" 2>/dev/null || echo 000)"
        if [[ "$code" != "000" ]]; then
            log_ok "Evolution responde en http://127.0.0.1:${EVOLUTION_PORT} (HTTP ${code})"
        else
            log_warn "Evolution aún no responde en :${EVOLUTION_PORT}; puede tardar unos segundos en levantar. Revisa: pm2 logs ${EVOLUTION_PM2_NAME}"
        fi
    fi
}

# --- Resumen final -----------------------------------------------------------
summary() {
    log_step "Resumen"
    printf '  %s\n' "Evolution provisionada (NATIVO, sin Docker)."
    printf '  %s\n' "SERVER_URL : ${PROVISIONED_SERVER_URL:-?}"
    printf '\n  %s\n' "GUARDA ESTA API KEY (se muestra UNA sola vez):"
    printf '  API_KEY    : %s\n' "${PROVISIONED_API_KEY:-?}"
    printf '\n  %s\n' "Ya quedó escrita en ambos .env (Evolution y MegaISP/WHATSAPP_API_KEY)."
    printf '  %s\n' "Verificación manual sugerida:"
    printf '    %s\n' "curl -H \"apikey: <API_KEY>\" ${PROVISIONED_SERVER_URL:-http://127.0.0.1:${EVOLUTION_PORT}}/instance/fetchInstances"
}

# --- Orquestación ------------------------------------------------------------
main() {
    log_step "== Provisioning de Evolution API (idempotente) =="
    preflight
    provision_evolution_user # 05 — usuario de sistema dedicado 'evolution'
    provision_node_pm2      # 10 — Node 20 + PM2
    provision_evolution     # 20 — código + .env + DB + Prisma
    provision_nginx         # 30 — location /evolution/ en el vhost
    provision_pm2_boot      # 40 — PM2 start + save + startup
    finalize                # cierre — config MegaISP + health check
    summary
    log_ok "Listo."
}

main "$@"

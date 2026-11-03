#!/usr/bin/env bash
# =============================================================================
# uninstall.sh — Quita Evolution API del server (lo inverso de install.sh)
# =============================================================================
# Idempotente: correrlo dos veces no rompe nada; lo que ya no existe se reporta
# como "Nada que quitar". Lo normal es que lo corra la página de add-ons al
# desinstalar addon-whatsapp-agent (app/Modules/Addons/WhatsAppAgent/system/purge.sh).
# A mano:
#   sudo env ALLOW_PROVISION=1 MEGAISP_ROOT=/var/www/ClaudeMegaISP bash deploy/provision/uninstall.sh
#
# Quita: proceso PM2 + servicio pm2-evolution, bloque /evolution/ del vhost
# (Apache o nginx), /opt/evolution-api, la BD evolution_api (con respaldo previo
# en /var/backups/megaisp) y su usuario MySQL, WHATSAPP_API_KEY/URL del .env de
# MegaISP (con respaldo) y el usuario de sistema 'evolution'.
# NO quita: Node, PM2, el repo de NodeSource ni los módulos de Apache (compartidos).
#
# Misma guarda que install.sh: aborta en el server de dev, para no borrar la
# Evolution de dev que está en uso.
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"
# shellcheck source=lib/05-evolution-user.sh
source "${SCRIPT_DIR}/lib/05-evolution-user.sh"
# shellcheck source=lib/20-evolution.sh
source "${SCRIPT_DIR}/lib/20-evolution.sh"
# shellcheck source=lib/30-nginx.sh
source "${SCRIPT_DIR}/lib/30-nginx.sh"
# shellcheck source=lib/30-apache.sh
source "${SCRIPT_DIR}/lib/30-apache.sh"
# shellcheck source=lib/90-unprovision.sh
source "${SCRIPT_DIR}/lib/90-unprovision.sh"

preflight() {
    log_step "Preflight"
    if ! is_root && ! sudo -n true 2>/dev/null; then
        die "Se requieren privilegios de root. Corre: sudo bash ${BASH_SOURCE[0]}"
    fi
    [[ -f "$MEGAISP_ENV" ]] || die "No encuentro el .env del sistema en ${MEGAISP_ENV} (fija MEGAISP_ROOT)."
    abort_if_dev_server
    log_ok "Preflight OK — MegaISP en ${MEGAISP_ROOT}, usuario Evolution '${EVOLUTION_USER}'"
}

main() {
    log_step "== Desinstalación de Evolution API (idempotente) =="
    preflight
    unprovision_pm2              # 1 — proceso PM2 + servicio de arranque
    unprovision_web_proxy        # 2 — bloque /evolution/ del servidor web
    unprovision_code             # 3 — /opt/evolution-api
    unprovision_db               # 4 — BD (con respaldo) + usuario MySQL
    unprovision_megaisp_env      # 5 — WHATSAPP_API_KEY/URL del .env de MegaISP
    unprovision_evolution_user   # 6 — usuario de sistema 'evolution'
    log_ok "Listo. Evolution API quitada del server."
}

main "$@"

#!/usr/bin/env bash
# =============================================================================
# uninstall.sh — Quita Asterisk del servidor (lo inverso de install.sh)
# =============================================================================
# Idempotente: lo que ya no existe se reporta como "Nada que quitar". Lo normal
# es que lo corra la página de add-ons (VoIP/system/purge.sh). A mano:
#   sudo env ALLOW_PROVISION=1 MEGAISP_ROOT=/var/www/ClaudeMegaISP \
#        bash app/Modules/Addons/VoIP/system/provision/uninstall.sh
#
# Orden (primero lo que protege, al final lo que borra):
#   preflight — guardas de dev y de grabaciones (abortan sin tocar nada)
#   1 respaldo de la base realtime en /var/backups/megaisp (si falla, no se sigue)
#   2 bloque /ws del servidor web
#   3 grabaciones: SE QUEDAN donde están (solo pasan a root:<grupo web>)
#   4 Asterisk: servicio, binarios, /etc/asterisk, datos, base realtime, DSN,
#     usuario de sistema (provisioning/desinstalar-asterisk.sh)
#   5 usuario MySQL realtime
#   6 carpeta de trabajo de la compilación
#   7 secretos de la central en el .env de MegaISP (con respaldo)
#
# NO quita: grabaciones, paquetes de apt, unixODBC, módulos de Apache ni los logs
# de provisión de /var/log/megaisp.
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"
# shellcheck source=lib/40-proxy-ws.sh
source "${SCRIPT_DIR}/lib/40-proxy-ws.sh"
# shellcheck source=lib/90-desinstalar.sh
source "${SCRIPT_DIR}/lib/90-desinstalar.sh"

preflight() {
    log_step "Preflight"
    is_root || die "Se requieren privilegios de root. Corre: sudo env ALLOW_PROVISION=1 bash ${BASH_SOURCE[0]}"
    [[ -f "$MEGAISP_ENV" ]] || die "No encuentro el .env del sistema en ${MEGAISP_ENV} (fija MEGAISP_ROOT)."
    abort_if_dev_server
    abort_if_grabaciones_en_riesgo
    log_ok "Preflight OK — MegaISP en ${MEGAISP_ROOT}"
}

main() {
    log_step "== Desinstalación de Asterisk de VoIP (idempotente) =="
    preflight
    desinstalar_respaldo_bd      # 1 — respaldo de la base realtime
    quitar_proxy_ws              # 2 — /ws del servidor web
    desinstalar_grabaciones      # 3 — se quedan; cambia el dueño
    desinstalar_asterisk         # 4 — servicio, binarios, config, datos, base, usuario
    desinstalar_usuario_mysql    # 5 — usuario MySQL realtime
    desinstalar_trabajo          # 6 — /var/cache/megaisp-asterisk
    desinstalar_env              # 7 — secretos en el .env de MegaISP
    log_ok "Listo. Asterisk quitado del servidor; las grabaciones siguen en $(voip_grabaciones_dir)."
}

main "$@"

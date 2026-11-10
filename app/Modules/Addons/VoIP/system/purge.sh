#!/usr/bin/env bash
# Quita Asterisk del servidor (lo inverso de install.sh). Respalda la base realtime en
# /var/backups/megaisp y el .env de MegaISP antes de borrar. Las grabaciones NO se tocan:
# se quedan en su carpeta. En dev aborta por la guarda de APP_URL, igual que el instalador.
set -euo pipefail
source "$MEGAISP_ROOT/deploy/system/lib.sh"

as_root env ALLOW_PROVISION=1 MEGAISP_ROOT="$MEGAISP_ROOT" MEGAISP_USER="${MEGAISP_USER:-www-data}" \
    bash "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/provision/uninstall.sh"

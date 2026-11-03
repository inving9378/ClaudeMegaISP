#!/usr/bin/env bash
# Quita Evolution API del server (lo inverso de install.sh). Respalda la base de
# Evolution en /var/backups/megaisp y el .env de MegaISP antes de borrar.
# En dev aborta por la guarda de APP_URL, igual que el instalador.
set -euo pipefail
source "$MEGAISP_ROOT/deploy/system/lib.sh"

as_root env ALLOW_PROVISION=1 MEGAISP_ROOT="$MEGAISP_ROOT" \
    bash "$MEGAISP_ROOT/deploy/provision/uninstall.sh"

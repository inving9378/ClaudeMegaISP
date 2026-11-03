#!/usr/bin/env bash
# Quita lo que puso install.sh de addon-demo. Se puede correr aunque no haya nada.
set -euo pipefail
source "$MEGAISP_ROOT/deploy/system/lib.sh"

rm -rf "$MEGAISP_ROOT/storage/app/addon-demo"
ms_info "Sin root: storage/app/addon-demo quitado"

if [[ -e /opt/megaisp-addon-demo ]]; then
    as_root rm -rf /opt/megaisp-addon-demo
    ms_info "Con root: /opt/megaisp-addon-demo quitado"
else
    ms_info "Nada que quitar en /opt/megaisp-addon-demo"
fi

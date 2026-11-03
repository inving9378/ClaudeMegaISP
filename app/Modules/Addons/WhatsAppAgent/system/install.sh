#!/usr/bin/env bash
# Dependencias de sistema de addon-whatsapp-agent: Evolution API (nativo, sin Docker).
# Reutiliza el instalador probado de deploy/provision (idempotente; ver su README).
#   ALLOW_PROVISION=1   prod comparte hostname con dev; la guarda por APP_URL sigue activa,
#                       así que en dev este script aborta sin tocar la Evolution de dev.
#   PROVISION_HIDE_KEY  la salida queda guardada en la página: no imprimir la API key
#                       (ya queda escrita en el .env de MegaISP como WHATSAPP_API_KEY).
set -euo pipefail
source "$MEGAISP_ROOT/deploy/system/lib.sh"

as_root env ALLOW_PROVISION=1 PROVISION_HIDE_KEY=1 MEGAISP_ROOT="$MEGAISP_ROOT" \
    bash "$MEGAISP_ROOT/deploy/provision/install.sh"

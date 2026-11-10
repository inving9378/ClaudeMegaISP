#!/usr/bin/env bash
# Dependencias de sistema de addon-voip: Asterisk (nativo, compilado) para la central de MegaISP.
# Corre el instalador de provision/ como root (ver su cabecera; idempotente, paso por paso).
#   ALLOW_PROVISION=1  prod comparte hostname con dev; la guarda por APP_URL sigue activa,
#                      así que en dev este script aborta sin tocar el Asterisk de dev.
# Las credenciales que genera quedan en el .env de MegaISP y no se imprimen: esta salida
# se guarda y se ve en la página de add-ons.
set -euo pipefail
source "$MEGAISP_ROOT/deploy/system/lib.sh"

as_root env ALLOW_PROVISION=1 MEGAISP_ROOT="$MEGAISP_ROOT" MEGAISP_USER="${MEGAISP_USER:-www-data}" \
    bash "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/provision/install.sh"

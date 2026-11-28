#!/usr/bin/env bash
# addon-ia no deja nada propio en el servidor: ffmpeg y poppler-utils los comparten otros
# (VoIP, Marketing), así que no se quitan (regla de deploy/system/lib.sh).
set -euo pipefail
source "$MEGAISP_ROOT/deploy/system/lib.sh"
ms_info "Nada que quitar: ffmpeg y poppler-utils se quedan porque otros módulos los usan."

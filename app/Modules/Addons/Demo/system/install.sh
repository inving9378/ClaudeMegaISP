#!/usr/bin/env bash
# Dependencias de sistema de addon-demo — prueba inofensiva del mecanismo.
# Sirve para comprobar en un server que la escalada a root funciona (root, sudo o
# su con contraseña) antes de usarla con algo real. purge.sh lo deshace todo.
set -euo pipefail
source "$MEGAISP_ROOT/deploy/system/lib.sh"

marca="$MEGAISP_ROOT/storage/app/addon-demo"
mkdir -p "$marca"
date '+%F %T' > "$marca/instalado.txt"
ms_info "Sin root: ${marca}/instalado.txt"

as_root install -d -m 755 /opt/megaisp-addon-demo
as_root bash -c 'id -un > /opt/megaisp-addon-demo/instalado-por.txt && date "+%F %T" >> /opt/megaisp-addon-demo/instalado-por.txt'
ms_info "Con root: /opt/megaisp-addon-demo/instalado-por.txt → $(head -n1 /opt/megaisp-addon-demo/instalado-por.txt)"

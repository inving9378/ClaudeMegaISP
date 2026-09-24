#!/bin/bash
# Actualiza /etc/asterisk/extensions.conf desde un archivo ya preparado en
# /tmp/ast_extensions.conf y recarga el dialplan. Pensado para correr vía
# `sudo bash .../actualizar-extensions.sh` (mismo patrón NOPASSWD que el
# resto de scripts de este directorio) cuando hace falta tocar el plan de
# marcado estático a mano (fuera del alcance de DialplanGeneratorService,
# que solo regenera /etc/asterisk/megaisp.d/*).
set -euo pipefail

SRC="/tmp/ast_extensions.conf"
DST="/etc/asterisk/extensions.conf"

if [ ! -f "$SRC" ]; then
    echo "No existe $SRC — nada que copiar." >&2
    exit 1
fi

cp "$SRC" "$DST"
# Sin chown/chmod restrictivo a propósito: no hay certeza de qué usuario
# corre Asterisk en este servidor, y un 640 a nombre equivocado dejaría al
# propio Asterisk sin poder leer su dialplan. cp conserva permisos legibles
# por defecto (el archivo de origen ya es 644), suficiente para que
# cualquier usuario lo lea.
asterisk -rx "dialplan reload"
echo "OK: $DST actualizado y dialplan recargado."

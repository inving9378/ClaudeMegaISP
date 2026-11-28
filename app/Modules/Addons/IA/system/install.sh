#!/usr/bin/env bash
# Dependencias de sistema de addon-ia: lo que JARVIS usa para leer los archivos del chat.
#   - ffmpeg / ffprobe: cuadros y sonido de los videos, audios para transcribir, fotos HEIC.
#   - poppler-utils (pdftotext, pdfinfo, pdftoppm): texto de los PDF y páginas de los escaneados.
# Idempotente: solo instala lo que falta. Sin estos programas JARVIS sigue funcionando; solo dice
# que no puede ver videos/audios o leer el texto de un PDF.
set -euo pipefail
source "$MEGAISP_ROOT/deploy/system/lib.sh"

faltan=()
command -v ffmpeg  >/dev/null 2>&1 && command -v ffprobe >/dev/null 2>&1 || faltan+=(ffmpeg)
command -v pdftotext >/dev/null 2>&1 && command -v pdftoppm >/dev/null 2>&1 && command -v pdfinfo >/dev/null 2>&1 || faltan+=(poppler-utils)

if [ "${#faltan[@]}" -eq 0 ]; then
    ms_info "Nada pendiente: ffmpeg y poppler-utils ya están instalados."
    exit 0
fi

ms_info "Instalando: ${faltan[*]}"
as_root env DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "${faltan[@]}"

pendiente=()
command -v ffmpeg    >/dev/null 2>&1 || pendiente+=(ffmpeg)
command -v pdftotext >/dev/null 2>&1 || pendiente+=(poppler-utils)
if [ "${#pendiente[@]}" -gt 0 ]; then
    ms_err "No quedaron instalados: ${pendiente[*]}. JARVIS seguirá sin leer videos/audios o el texto de los PDF."
    exit 1
fi
ms_info "Nada pendiente: JARVIS ya puede leer videos, audios y PDF."

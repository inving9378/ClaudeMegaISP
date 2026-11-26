#!/usr/bin/env bash
# =============================================================================
# test-55-campanas.sh — prueba de lib/55-campanas.sh (Campañas/Blaster)
# =============================================================================
# systemctl, apt-get, chown y php SIMULADOS (shims en PATH que registran lo que se
# les pide); la carpeta de audios y la unit van a un directorio temporal. No
# necesita root ni toca /etc.
#
#   bash app/Modules/Addons/VoIP/system/provision/tests/test-55-campanas.sh   # exit 0 = OK
# =============================================================================
set -uo pipefail
LIB="$(cd "$(dirname "${BASH_SOURCE[0]}")/../lib" && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
PASS=0; FAIL=0
ok()   { echo "  ✔ $*"; PASS=$((PASS+1)); }
bad()  { echo "  ✘ $*"; FAIL=$((FAIL+1)); [[ -f "$T/out" ]] && sed 's/^/      /' "$T/out"; }
check(){ if eval "$1"; then ok "$2"; else bad "$2"; fi; }

mkdir -p "$T/bin" "$T/root"
cat > "$T/bin/systemctl" <<'SH'
#!/usr/bin/env bash
echo "systemctl $*" >> "$FAKE_LOG"
if [ "$1" = is-active ]; then svc="${@: -1}"; [[ " ${FAKE_ACTIVE:-} " == *" $svc "* ]] && exit 0 || exit 3; fi
if [ "$1" = show ]; then
    # MainPID: fijo, o distinto en cada consulta si el servicio «se cae y se levanta».
    if [ "${FAKE_CRASH:-0}" = 1 ]; then n=$(( $(cat "$FAKE_LOG.pid" 2>/dev/null || echo 100) + 1 )); echo "$n" > "$FAKE_LOG.pid"; echo "$n"; else echo 4242; fi
fi
exit 0
SH
cat > "$T/bin/supervisorctl" <<'SH'
#!/usr/bin/env bash
echo "supervisorctl $*" >> "$FAKE_LOG"; exit 0
SH
cat > "$T/bin/apt-get" <<'SH'
#!/usr/bin/env bash
echo "apt-get $*" >> "$FAKE_LOG"
# Simula que el paquete quedó instalado (salvo que se pida que falle).
[ "${FAKE_APT_FALLA:-0}" = 1 ] && exit 100
[ -n "${FFMPEG_BIN:-}" ] && { printf '#!/bin/sh\n' > "$(dirname "$0")/$FFMPEG_BIN"; chmod +x "$(dirname "$0")/$FFMPEG_BIN"; }
exit 0
SH
cat > "$T/bin/chown" <<'SH'
#!/usr/bin/env bash
echo "chown $*" >> "$FAKE_LOG"; exit 0
SH
cat > "$T/bin/php" <<'SH'
#!/usr/bin/env bash
# `php artisan list --raw`: el comando del módulo aparece solo si está activo.
[ "${FAKE_MODULO:-1}" = 1 ] && echo "cobranza:ami-listener      Escucha eventos del AMI"
echo "voip:provisionar           Provisiona Asterisk"
SH
chmod +x "$T/bin/"*
export PATH="$T/bin:$PATH" FAKE_LOG="$T/fake.log"
YO="$(id -un)"

run() {
    : > "$FAKE_LOG"
    MEGAISP_ROOT="${ROOT:-$T/root}" MEGAISP_USER="$YO" MEGAISP_ENV="$T/env" \
    VOIP_BACKUP_DIR="$T/backups" SYSTEMD_DIR="$T/systemd" SUPERVISOR_DIR="$T/supervisor" PHP_BIN="$T/bin/php" \
    CAMPANAS_ESPERA=0 CAMPANAS_ESPERA_ESTABLE=0 \
    bash -c "set -euo pipefail; source '$LIB/common.sh'; source '$LIB/55-campanas.sh'; $1" > "$T/out" 2>&1
}
AUDIOS="$T/sonidos/cobranza"
printf 'APP_URL=https://megaisp.example\nCOBRANZA_AUDIO_BASE_PATH=%s/\n' "$AUDIOS" > "$T/env"
UNIT="$T/systemd/megaisp-campanas-ami.service"

echo "1) Instalación nueva con el servicio arrancando"
FAKE_ACTIVE="megaisp-campanas-ami" run 'provision_campanas; echo "FALLA=$CAMPANAS_FALLA"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLA=0" "$T/out"' "termina bien, sin falla"
check '[[ -d "$AUDIOS" && "$(stat -c %a "$AUDIOS")" == 2770 ]]' "crea la carpeta de audios en 2770 (setgid)"
check 'grep -q "chown asterisk:asterisk $AUDIOS" "$FAKE_LOG"' "la deja a nombre de asterisk"
check 'grep -q "OMITIDO (ya existe): ffmpeg" "$T/out"' "ffmpeg ya estaba: no lo instala"
check '[[ -f "$UNIT" ]]' "escribe la unit"
check 'grep -q "^ExecStart=$T/bin/php $T/root/artisan cobranza:ami-listener --max-seconds=3600$" "$UNIT"' "ExecStart con la ruta de esta instalación y salida cada hora"
check 'grep -q "^User=$YO$" "$UNIT" && grep -q "^Restart=always$" "$UNIT"' "corre con el usuario web y se levanta solo"
check 'grep -q "systemctl daemon-reload" "$FAKE_LOG" && grep -q "systemctl enable megaisp-campanas-ami" "$FAKE_LOG"' "recarga y habilita"

echo "2) Segunda corrida igual (idempotente)"
FAKE_ACTIVE="megaisp-campanas-ami" run 'provision_campanas; echo "FALLA=$CAMPANAS_FALLA"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLA=0" "$T/out"' "termina bien"
check 'grep -q "OMITIDO (ya existe): unit" "$T/out"' "no reescribe la unit"
check '! grep -q "daemon-reload\|restart\|start megaisp" "$FAKE_LOG"' "ni recarga ni reinicia"

echo "3) Sin ffmpeg: lo instala"
FFMPEG_BIN=ffmpeg-de-prueba FAKE_ACTIVE="megaisp-campanas-ami" run 'provision_campanas; echo "FALLA=$CAMPANAS_FALLA"'; rc=$?
check 'grep -q "apt-get install -y --no-install-recommends ffmpeg" "$FAKE_LOG" && grep -q "ffmpeg instalado" "$T/out"' "instala ffmpeg"
check 'grep -q "FALLA=0" "$T/out"' "sin falla"
rm -f "$T/bin/ffmpeg-de-prueba"

echo "4) ffmpeg no se puede instalar → falla marcada, la instalación sigue"
FFMPEG_BIN=ffmpeg-nunca FAKE_APT_FALLA=1 FAKE_ACTIVE="megaisp-campanas-ami" run 'provision_campanas; echo "FALLA=$CAMPANAS_FALLA"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLA=1" "$T/out" && grep -q "No pude instalar ffmpeg" "$T/out"' "marca la falla y dice por qué"

echo "5) El servicio no arranca en una instalación nueva → se deshace"
rm -rf "$T/systemd" "$T/backups"
run 'provision_campanas; echo "FALLA=$CAMPANAS_FALLA"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLA=1" "$T/out" && grep -q "no quedó corriendo" "$T/out"' "lo marca como falla sin detener la instalación"
check '[[ ! -f "$UNIT" ]]' "quita la unit nueva"
check 'grep -q "journalctl -u megaisp-campanas-ami" "$T/out"' "dice dónde ver el error"

echo "5b) Arranca pero se cae y systemd lo levanta (PID distinto) → falla, se deshace"
rm -rf "$T/systemd" "$T/backups"
FAKE_CRASH=1 FAKE_ACTIVE="megaisp-campanas-ami" run 'provision_campanas; echo "FALLA=$CAMPANAS_FALLA"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLA=1" "$T/out" && grep -q "se cae y systemd lo vuelve a levantar" "$T/out"' "no lo da por bueno aunque is-active diga active"
check '[[ ! -f "$UNIT" ]]' "quita la unit nueva"

echo "5c) Listener viejo de supervisor (guía a mano) → se quita con respaldo"
mkdir -p "$T/supervisor"; echo "[program:megaisp-cobranza-ami]" > "$T/supervisor/megaisp-cobranza-ami.conf"
FAKE_ACTIVE="megaisp-campanas-ami" run 'provision_campanas; echo "FALLA=$CAMPANAS_FALLA"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLA=0" "$T/out" && [[ ! -f "$T/supervisor/megaisp-cobranza-ami.conf" ]]' "quita el programa viejo"
check 'ls "$T/backups/supervisor/" | grep -q "megaisp-cobranza-ami.conf."' "deja respaldo"
check 'grep -q "supervisorctl stop megaisp-cobranza-ami" "$FAKE_LOG" && grep -q "supervisorctl update" "$FAKE_LOG"' "lo detiene y recarga supervisor"
rm -rf "$T/systemd"

echo "6) Módulo apagado → no toca nada"
rm -rf "$T/systemd" "$AUDIOS"
FAKE_MODULO=0 run 'provision_campanas; echo "FALLA=$CAMPANAS_FALLA"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLA=0" "$T/out" && grep -q "no está activo" "$T/out"' "lo omite"
check '[[ ! -d "$AUDIOS" && ! -f "$UNIT" ]]' "ni carpeta ni servicio"

echo "7) Ruta de audios peligrosa → no la toca"
printf 'COBRANZA_AUDIO_BASE_PATH=/\n' > "$T/env"
FAKE_ACTIVE="megaisp-campanas-ami" run 'provision_campanas; echo "FALLA=$CAMPANAS_FALLA"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLA=1" "$T/out" && grep -q "no es una ruta válida" "$T/out"' "la rechaza y lo marca"
check '! grep -q "chown asterisk:asterisk /$" "$FAKE_LOG"' "no cambia el dueño de /"

echo "8) Desinstalar"
printf 'COBRANZA_AUDIO_BASE_PATH=%s/\n' "$AUDIOS" > "$T/env"
FAKE_ACTIVE="megaisp-campanas-ami" run provision_campanas >/dev/null
run quitar_campanas; rc=$?
check '[[ $rc == 0 && ! -f "$UNIT" ]]' "quita la unit"
check 'grep -q "systemctl disable --now megaisp-campanas-ami" "$FAKE_LOG"' "detiene y deshabilita"
run quitar_campanas; rc=$?
check '[[ $rc == 0 ]] && grep -q "Nada que quitar" "$T/out"' "segunda vez no hay nada que quitar"

echo; echo "Resultado: ${PASS} bien, ${FAIL} mal"
[[ $FAIL == 0 ]]

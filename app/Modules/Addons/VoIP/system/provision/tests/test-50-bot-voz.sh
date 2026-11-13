#!/usr/bin/env bash
# =============================================================================
# test-50-bot-voz.sh — prueba de lib/50-bot-voz.sh (servicio de María)
# =============================================================================
# systemctl y ss SIMULADOS (shims en PATH que registran lo que se les pide); la
# unit se escribe en un directorio temporal. No necesita root ni toca /etc.
#
#   bash app/Modules/Addons/VoIP/system/provision/tests/test-50-bot-voz.sh   # exit 0 = OK
# =============================================================================
set -uo pipefail
LIB="$(cd "$(dirname "${BASH_SOURCE[0]}")/../lib" && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
PASS=0; FAIL=0
ok()   { echo "  ✔ $*"; PASS=$((PASS+1)); }
bad()  { echo "  ✘ $*"; FAIL=$((FAIL+1)); [[ -f "$T/out" ]] && sed 's/^/      /' "$T/out"; }
check(){ if eval "$1"; then ok "$2"; else bad "$2"; fi; }

mkdir -p "$T/bin"
cat > "$T/bin/systemctl" <<'SH'
#!/usr/bin/env bash
echo "systemctl $*" >> "$FAKE_LOG"
if [ "$1" = is-active ]; then svc="${@: -1}"; [[ " ${FAKE_ACTIVE:-} " == *" $svc "* ]] && exit 0 || exit 3; fi
exit 0
SH
cat > "$T/bin/ss" <<'SH'
#!/usr/bin/env bash
echo "State Recv-Q Send-Q Local Address:Port Peer Address:Port"
[ "${FAKE_LISTEN:-1}" = 1 ] && echo "LISTEN 0 128 127.0.0.1:9099 0.0.0.0:*"
exit 0
SH
cat > "$T/bin/apt-get" <<'SH'
#!/usr/bin/env bash
echo "apt-get $*" >> "$FAKE_LOG"; exit 0
SH
chmod +x "$T/bin/"*
export PATH="$T/bin:$PATH" FAKE_LOG="$T/fake.log"
YO="$(id -un)"

run() {
    : > "$FAKE_LOG"
    MEGAISP_ROOT="${ROOT:-/var/www/ClaudeMegaISP}" MEGAISP_USER="$YO" MEGAISP_ENV="$T/env" \
    VOIP_BACKUP_DIR="$T/backups" SYSTEMD_DIR="$T/systemd" PHP_BIN=/usr/bin/php BOT_VOZ_ESPERA=0 \
    bash -c "set -euo pipefail; source '$LIB/common.sh'; source '$LIB/50-bot-voz.sh'; $1" > "$T/out" 2>&1
}
printf 'APP_URL=https://megaisp.example\n' > "$T/env"
UNIT="$T/systemd/megaisp-bot-voz.service"

echo "1) Instalación nueva"
run provision_bot_voz; rc=$?
check '[[ $rc == 0 ]]' "termina bien"
check '[[ -f "$UNIT" ]]' "escribe la unit"
check 'grep -q "^ExecStart=/usr/bin/php /var/www/ClaudeMegaISP/artisan voip:bot-voz-escuchar$" "$UNIT"' "ExecStart con la ruta de esta instalación"
check 'grep -q "^User=$YO$" "$UNIT"' "corre con el usuario web"
check 'grep -q "systemctl daemon-reload" "$FAKE_LOG" && grep -q "systemctl enable megaisp-bot-voz" "$FAKE_LOG" && grep -q "systemctl start megaisp-bot-voz" "$FAKE_LOG"' "recarga, habilita y arranca"
check 'grep -q "María escucha en 127.0.0.1:9099" "$T/out"' "confirma que escucha"

echo "2) Segunda corrida con el servicio arriba (idempotente)"
FAKE_ACTIVE="megaisp-bot-voz" run provision_bot_voz; rc=$?
check '[[ $rc == 0 ]]' "termina bien"
check 'grep -q "OMITIDO (ya existe): unit" "$T/out"' "no reescribe la unit"
check '! grep -q "daemon-reload\|restart\|start megaisp" "$FAKE_LOG"' "ni recarga ni reinicia"

echo "3) La unit cambia con el servicio arriba"
ROOT=/srv/megaisp FAKE_ACTIVE="megaisp-bot-voz" run provision_bot_voz; rc=$?
check '[[ $rc == 0 ]]' "termina bien"
check 'ls "$T/backups/systemd/" 2>/dev/null | grep -q "megaisp-bot-voz.service."' "respalda la unit anterior"
check 'grep -q "/srv/megaisp/artisan" "$UNIT"' "escribe la nueva"
check 'grep -q "systemctl restart megaisp-bot-voz" "$FAKE_LOG"' "reinicia con la unit nueva"

echo "4) No queda escuchando en una instalación nueva → se deshace"
rm -rf "$T/systemd" "$T/backups"
FAKE_LISTEN=0 run 'provision_bot_voz; echo "FALLA=$BOT_VOZ_FALLA"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLA=1" "$T/out" && grep -q "María no arrancó" "$T/out"' "lo marca como falla sin detener la instalación"
check '[[ ! -f "$UNIT" ]]' "quita la unit nueva"
check 'grep -q "systemctl disable megaisp-bot-voz" "$FAKE_LOG"' "la deshabilita"
check 'grep -q "journalctl -u megaisp-bot-voz" "$T/out"' "dice dónde ver el error"

echo "5) No queda escuchando tras cambiar la unit → se restaura la anterior"
run provision_bot_voz >/dev/null
cp "$UNIT" "$T/unit-buena"
ROOT=/srv/otro FAKE_LISTEN=0 FAKE_ACTIVE="megaisp-bot-voz" run 'provision_bot_voz; echo "FALLA=$BOT_VOZ_FALLA"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLA=1" "$T/out"' "lo marca como falla sin detener la instalación"
check 'cmp -s "$UNIT" "$T/unit-buena"' "deja la unit anterior"

echo "6) Desinstalar"
run quitar_bot_voz; rc=$?
check '[[ $rc == 0 && ! -f "$UNIT" ]]' "quita la unit"
check 'grep -q "systemctl disable --now megaisp-bot-voz" "$FAKE_LOG"' "detiene y deshabilita"
run quitar_bot_voz; rc=$?
check '[[ $rc == 0 ]] && grep -q "Nada que quitar" "$T/out"' "segunda vez no hay nada que quitar"

echo; echo "Resultado: ${PASS} bien, ${FAIL} mal"
[[ $FAIL == 0 ]]

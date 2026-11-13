#!/usr/bin/env bash
# =============================================================================
# test-15-transporte-externo.sh — prueba de lib/15-transporte-externo.sh
# =============================================================================
# `ip` y `ss` SIMULADOS (shims en PATH) para armar cada tipo de red; el .env es
# un archivo temporal. No necesita root ni toca la red.
#
#   bash app/Modules/Addons/VoIP/system/provision/tests/test-15-transporte-externo.sh   # exit 0 = OK
# =============================================================================
set -uo pipefail
LIB="$(cd "$(dirname "${BASH_SOURCE[0]}")/../lib" && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
PASS=0; FAIL=0
ok()   { echo "  ✔ $*"; PASS=$((PASS+1)); }
bad()  { echo "  ✘ $*"; FAIL=$((FAIL+1)); [[ -f "$T/out" ]] && sed 's/^/      /' "$T/out"; }
check(){ if eval "$1"; then ok "$2"; else bad "$2"; fi; }

mkdir -p "$T/bin"
# FAKE_SRC: IP de salida (vacía = sin ruta). FAKE_IPS: IPs de las interfaces.
cat > "$T/bin/ip" <<'SH'
#!/usr/bin/env bash
case "$*" in
  "-4 route get 1.1.1.1") [[ -n "${FAKE_SRC:-}" ]] && echo "1.1.1.1 via 192.0.2.1 dev eth0 src ${FAKE_SRC} uid 0"; exit 0;;
  "-4 -o addr show scope global") i=2; for a in ${FAKE_IPS:-}; do echo "$i: eth0    inet $a/24 brd x scope global eth0\       valid_lft forever preferred_lft forever"; i=$((i+1)); done; exit 0;;
esac
exit 1
SH
# FAKE_UDP: "IP:puerto:programa" ya escuchando.
cat > "$T/bin/ss" <<'SH'
#!/usr/bin/env bash
for x in ${FAKE_UDP:-}; do
  IFS=: read -r a p prog <<<"$x"
  echo "UNCONN 0 0 $a:$p 0.0.0.0:* users:((\"$prog\",pid=1,fd=3))"
done
exit 0
SH
chmod +x "$T/bin/"*
export PATH="$T/bin:$PATH"

ENVF="$T/env"
run() {
    MEGAISP_ROOT="$T" MEGAISP_ENV="$ENVF" VOIP_BACKUP_DIR="$T/backups" \
    bash -c "set -euo pipefail; source '$LIB/common.sh'; source '$LIB/15-transporte-externo.sh'; $1" > "$T/out" 2>&1
}
nuevo_env() { printf 'APP_URL=https://megaisp.example\nDB_DATABASE=megaisp\n' > "$ENVF"; chmod 640 "$ENVF"; rm -f "$T"/env.*.bak; }
bind() { grep -E '^ASTERISK_BIND_EXTERNO=' "$ENVF" | tail -n1 | cut -d= -f2-; }

echo "1) Red como la de test: sale por IP privada con NAT y tiene una pública en la interfaz"
nuevo_env
FAKE_SRC=192.168.1.11 FAKE_IPS="192.168.1.11 203.0.113.10" run provision_transporte_externo; rc=$?
check '[[ $rc == 0 && "$(bind)" == 203.0.113.10:5080 ]]' "fija ASTERISK_BIND_EXTERNO=<pública>:5080"
check '[[ $(ls "$T"/env.bind-externo.*.bak 2>/dev/null | wc -l) == 1 ]]' "respalda el .env antes de escribirlo"
check '[[ "$(stat -c %a "$ENVF")" == 640 ]]' "el .env conserva sus permisos"
check 'grep -q "408 Request Timeout" "$T/out" && grep -q "transport-externo" "$T/out"' "explica por qué y qué hacer en Extensiones"

echo "2) Segunda corrida"
cp "$ENVF" "$T/env1"
FAKE_SRC=192.168.1.11 FAKE_IPS="192.168.1.11 203.0.113.10" run provision_transporte_externo; rc=$?
check '[[ $rc == 0 ]] && cmp -s "$ENVF" "$T/env1" && grep -q "OMITIDO" "$T/out"' "idempotente: OMITIDO y .env igual"

echo "3) Ya estaba fijado con una IP que el servidor ya no tiene"
FAKE_SRC=192.168.1.11 FAKE_IPS="192.168.1.11" run provision_transporte_externo; rc=$?
check '[[ $rc == 0 ]] && cmp -s "$ENVF" "$T/env1" && grep -q "no está en ninguna interfaz" "$T/out"' "lo respeta y avisa"

echo "4) El servidor sale con su propia IP pública"
nuevo_env
FAKE_SRC=203.0.113.5 FAKE_IPS="203.0.113.5" run provision_transporte_externo; rc=$?
check '[[ $rc == 0 && -z "$(bind)" ]] && grep -q "no hace falta" "$T/out"' "no hace falta: no toca el .env"

echo "5) Solo IP privada (detrás del router)"
FAKE_SRC=192.168.1.108 FAKE_IPS="192.168.1.108" run provision_transporte_externo; rc=$?
check '[[ $rc == 0 && -z "$(bind)" ]] && grep -q "solo tiene IP privada" "$T/out"' "no aplica: no toca el .env"

echo "6) Varias IP públicas"
FAKE_SRC=10.0.0.2 FAKE_IPS="10.0.0.2 203.0.113.5 198.51.100.7" run provision_transporte_externo; rc=$?
check '[[ $rc == 0 && -z "$(bind)" ]] && grep -q "varias IP públicas" "$T/out"' "no adivina: avisa y no toca el .env"

echo "7) Sin ruta a internet"
FAKE_SRC="" FAKE_IPS="10.0.0.2 203.0.113.5" run provision_transporte_externo; rc=$?
check '[[ $rc == 0 && -z "$(bind)" ]]' "no toca el .env"

echo "8) El puerto 5080 ya lo usa otro programa"
FAKE_SRC=10.0.0.2 FAKE_IPS="10.0.0.2 203.0.113.5" FAKE_UDP="0.0.0.0:5080:kamailio" run provision_transporte_externo; rc=$?
check '[[ $rc == 0 && -z "$(bind)" ]] && grep -q "kamailio" "$T/out"' "no lo fija y dice quién lo usa"
FAKE_SRC=10.0.0.2 FAKE_IPS="10.0.0.2 203.0.113.5" FAKE_UDP="203.0.113.5:5080:asterisk" run provision_transporte_externo; rc=$?
check '[[ $rc == 0 && "$(bind)" == 203.0.113.5:5080 ]]' "si es Asterisk, sí lo fija"

echo "9) Overrides a mano"
nuevo_env
VOIP_BIND_EXTERNO=no FAKE_SRC=10.0.0.2 FAKE_IPS="10.0.0.2 203.0.113.5" run provision_transporte_externo; rc=$?
check '[[ $rc == 0 && -z "$(bind)" ]]' "VOIP_BIND_EXTERNO=no: no toca nada"
VOIP_BIND_EXTERNO=198.51.100.7:5090 FAKE_SRC=10.0.0.2 FAKE_IPS="10.0.0.2 203.0.113.5 198.51.100.7" run provision_transporte_externo; rc=$?
check '[[ $rc == 0 && "$(bind)" == 198.51.100.7:5090 ]]' "VOIP_BIND_EXTERNO=IP:puerto elige entre varias públicas"
cp "$ENVF" "$T/env2"
VOIP_BIND_EXTERNO=198.51.100.7 FAKE_SRC=10.0.0.2 FAKE_IPS="10.0.0.2 198.51.100.7" run provision_transporte_externo; rc=$?
check '[[ $rc != 0 ]] && cmp -s "$ENVF" "$T/env2"' "sin puerto: aborta sin tocar el .env"
VOIP_BIND_EXTERNO=192.0.2.99:5080 FAKE_SRC=10.0.0.2 FAKE_IPS="10.0.0.2 198.51.100.7" run provision_transporte_externo; rc=$?
check '[[ $rc != 0 ]] && cmp -s "$ENVF" "$T/env2"' "IP que no es del servidor: aborta sin tocar el .env"

echo "10) Qué cuenta como IP privada"
run 'for x in 10.1.2.3 192.168.0.1 172.16.0.1 172.31.255.1 100.64.0.1 100.127.0.1 127.0.0.1 169.254.1.1; do _es_ip_privada $x || echo "MAL $x"; done
     for x in 172.15.0.1 172.32.0.1 100.63.0.1 100.128.0.1 203.0.113.10 8.8.8.8; do _es_ip_privada $x && echo "MAL $x"; done; true'
check '! grep -q MAL "$T/out"' "RFC 1918, CGNAT, loopback y link-local; lo demás es pública"

echo
echo "Resultado: ${PASS} bien, ${FAIL} mal"
(( FAIL == 0 ))

#!/usr/bin/env bash
# =============================================================================
# test-30-apache.sh — prueba de lib/30-apache.sh + lib/30-proxy.sh (item #9991239)
# =============================================================================
# Dev no tiene Apache, así que se usa un Apache SIMULADO: vhosts en un directorio
# temporal y shims de apache2ctl / a2enmod / a2dismod / systemctl en PATH que
# registran lo que se les pide. No necesita root ni toca /etc. Cada paso corre con
# `set -euo pipefail`, igual que install.sh.
#
#   bash deploy/provision/tests/test-30-apache.sh      # exit 0 = todo OK
# =============================================================================
set -uo pipefail
LIB="$(cd "$(dirname "${BASH_SOURCE[0]}")/../lib" && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
PASS=0; FAIL=0
ok()   { echo "  ✔ $*"; PASS=$((PASS+1)); }
bad()  { echo "  ✘ $*"; FAIL=$((FAIL+1)); }
check(){ if eval "$1"; then ok "$2"; else bad "$2"; fi; }

mkshims() {
  mkdir -p "$T/bin"
  cat > "$T/bin/apache2ctl" <<'EOF'
#!/usr/bin/env bash
echo "apache2ctl $*" >> "$FAKE_LOG"
case "$1" in
  -v) echo "Server version: Apache/${FAKE_APACHE_VERSION} (Debian)";;
  -M) echo "Loaded Modules:"; while read -r m; do [ -n "$m" ] && echo " ${m}_module (shared)"; done < "$FAKE_MODS";;
  configtest) if [ "${FAKE_CONFIGTEST:-0}" = 0 ]; then echo "Syntax OK" >&2; exit 0; else echo "AH00526: Syntax error on line 7 of /x.conf" >&2; exit 1; fi;;
  graceful) exit 0;;
esac
EOF
  cat > "$T/bin/a2enmod" <<'EOF'
#!/usr/bin/env bash
echo "a2enmod $*" >> "$FAKE_LOG"; m="${@: -1}"; grep -qx "$m" "$FAKE_MODS" || echo "$m" >> "$FAKE_MODS"
EOF
  cat > "$T/bin/a2dismod" <<'EOF'
#!/usr/bin/env bash
echo "a2dismod $*" >> "$FAKE_LOG"; m="${@: -1}"; grep -vx "$m" "$FAKE_MODS" > "$FAKE_MODS.tmp"; mv "$FAKE_MODS.tmp" "$FAKE_MODS"
EOF
  cat > "$T/bin/systemctl" <<'EOF'
#!/usr/bin/env bash
echo "systemctl $*" >> "$FAKE_LOG"
if [ "$1" = is-active ]; then svc="${@: -1}"; [[ " ${FAKE_ACTIVE:-} " == *" $svc "* ]] && exit 0 || exit 3; fi
exit 0
EOF
  chmod +x "$T/bin/"*
}

mkvhosts() {
  rm -rf "$T/etc"; mkdir -p "$T/etc/sites-available" "$T/etc/sites-enabled"
  cat > "$T/etc/sites-available/claudemegaisp.conf" <<'EOF'
<VirtualHost *:80>
    ServerName 38.123.192.198
    DocumentRoot /var/www/ClaudeMegaISP/public
    <Directory /var/www/ClaudeMegaISP/public>
        AllowOverride All
    </Directory>
</VirtualHost>
<VirtualHost *:443>
    ServerName prod.example
    DocumentRoot "/var/www/ClaudeMegaISP/public/"
    SSLEngine on
</VirtualHost>
EOF
  cat > "$T/etc/sites-available/legacy.conf" <<'EOF'
<VirtualHost *:8090>
    DocumentRoot /var/www/MEGANET/public
</VirtualHost>
EOF
  ln -s ../sites-available/claudemegaisp.conf "$T/etc/sites-enabled/claudemegaisp.conf"
  ln -s ../sites-available/legacy.conf "$T/etc/sites-enabled/legacy.conf"
  : > "$T/mods"; echo "rewrite" >> "$T/mods"   # proxy NO cargado al inicio
  : > "$T/log"
}

# Corre una función del instalador en una subshell aislada; devuelve su exit code.
run_step() {
  ( set -euo pipefail; export PATH="$T/bin:$PATH" FAKE_LOG="$T/log" FAKE_MODS="$T/mods"
    export MEGAISP_ROOT=/var/www/ClaudeMegaISP APACHE_SITES_DIR="$T/etc/sites-enabled"
    source "$LIB/common.sh"; source "$LIB/30-nginx.sh"; source "$LIB/30-apache.sh"; source "$LIB/30-proxy.sh"
    run_root() { "$@"; }      # sin sudo en la prueba
    have() { [[ "$1" == nginx && -z "${FAKE_HAS_NGINX:-}" ]] && return 1; command -v "$1" >/dev/null 2>&1; }
    "$@" ) > "$T/out" 2>&1
}

mkshims
VH="$T/etc/sites-available/claudemegaisp.conf"; LEG="$T/etc/sites-available/legacy.conf"

echo "A) Apache 2.4.62, proxy no cargado, configtest OK"
mkvhosts; cp "$VH" "$T/orig.conf"; cp "$LEG" "$T/legacy.orig"
FAKE_APACHE_VERSION=2.4.62 FAKE_CONFIGTEST=0 run_step provision_apache; rc=$?
check '[ $rc -eq 0 ]' "termina bien (rc=$rc)"
check '[ "$(grep -c "ProxyPass        /evolution/ http://127.0.0.1:8080/ upgrade=websocket timeout=300" "$VH")" = 2 ]' "proxy en los 2 VirtualHost (:80 y :443)"
check '[ "$(grep -c "ProxyPassReverse /evolution/" "$VH")" = 2 ]' "ProxyPassReverse en los 2"
check 'cmp -s "$LEG" "$T/legacy.orig"' "vhost legacy /var/www/MEGANET intacto"
check '[ -L "$T/etc/sites-enabled/claudemegaisp.conf" ]' "el symlink de sites-enabled sigue siendo symlink"
check 'grep -q "a2enmod -q proxy$" "$T/log" && grep -q "a2enmod -q proxy_http$" "$T/log"' "habilita proxy y proxy_http"
check '! grep -q "a2enmod -q rewrite" "$T/log"' "no toca rewrite (ya estaba)"
check 'grep -q "systemctl reload apache2" "$T/log"' "recarga Apache"
check 'ls "$T/etc/sites-available/"claudemegaisp.conf.provision.*.bak >/dev/null 2>&1' "deja respaldo con fecha"
check 'cmp -s "$(ls "$T/etc/sites-available/"claudemegaisp.conf.provision.*.bak | head -1)" "$T/orig.conf"' "el respaldo es el original exacto"
awk '/DocumentRoot/{d=1} d&&/ProxyPass /{print NR": "$0; d=0}' "$VH" | head -2 | sed 's/^/     /'
sed -n '1,8p' "$VH" | sed 's/^/     | /'

echo "B) Segunda corrida (idempotencia)"
: > "$T/log"; nbak=$(ls "$T/etc/sites-available/"*.bak | wc -l); cp "$VH" "$T/despuesA.conf"
FAKE_APACHE_VERSION=2.4.62 run_step provision_apache; rc=$?
check '[ $rc -eq 0 ]' "termina bien"
check 'cmp -s "$VH" "$T/despuesA.conf"' "no vuelve a inyectar"
check '[ "$(ls "$T/etc/sites-available/"*.bak | wc -l)" = "$nbak" ]' "no crea otro respaldo"
check '! grep -q a2enmod "$T/log" && ! grep -q "systemctl reload" "$T/log"' "ni habilita módulos ni recarga"
check 'grep -q "ya tiene el proxy" "$T/out"' "lo reporta como OMITIDO"

echo "C) configtest FALLA → revertir todo"
mkvhosts; cp "$VH" "$T/orig.conf"; cp "$T/mods" "$T/mods.orig"
FAKE_APACHE_VERSION=2.4.62 FAKE_CONFIGTEST=1 run_step provision_apache; rc=$?
check '[ $rc -ne 0 ]' "aborta (rc=$rc)"
check 'cmp -s "$VH" "$T/orig.conf"' "vhost restaurado byte a byte"
check 'cmp -s <(sort "$T/mods") <(sort "$T/mods.orig")' "módulos habilitados por el paso, deshabilitados de nuevo"
check '! grep -q "systemctl reload" "$T/log" && ! grep -q graceful "$T/log"' "NO recarga Apache con config rota"
check 'grep -q "AH00526" "$T/out"' "muestra el error de Apache"

echo "D) Apache 2.4.38 (sin upgrade=websocket)"
mkvhosts; : > "$T/mods"
FAKE_APACHE_VERSION=2.4.38 run_step provision_apache; rc=$?
check '[ $rc -eq 0 ]' "termina bien"
check '[ "$(grep -c "RewriteRule ^/evolution/(.\*) ws://127.0.0.1:8080/\$1 \[P,L\]" "$VH")" = 2 ]' "RewriteRule ws:// con \$1 literal"
check '! grep -q upgrade=websocket "$VH"' "sin upgrade=websocket"
check 'grep -q "a2enmod -q proxy_wstunnel" "$T/log" && grep -q "a2enmod -q rewrite" "$T/log"' "habilita proxy_wstunnel y rewrite"

echo "E) Detección del servidor web"
mkvhosts
FAKE_ACTIVE="apache2" run_step _detect_web_server; check '[ "$(cat "$T/out")" = apache ]' "solo apache2 activo → apache"
FAKE_ACTIVE="nginx" run_step _detect_web_server; check '[ "$(cat "$T/out")" = nginx ]' "solo nginx activo → nginx"
FAKE_ACTIVE="nginx apache2" run_step _detect_web_server; rc=$?; check '[ $rc -ne 0 ] && grep -q "activos a la vez" "$T/out"' "ambos activos → aborta pidiendo WEB_SERVER"
FAKE_ACTIVE="nginx apache2" WEB_SERVER=apache run_step _detect_web_server; check '[ "$(cat "$T/out")" = apache ]' "WEB_SERVER=apache decide"
WEB_SERVER=iis run_step _detect_web_server; rc=$?; check '[ $rc -ne 0 ]' "WEB_SERVER inválido → aborta"
: > "$T/log"; SKIP_WEB_PROXY=1 FAKE_ACTIVE="apache2" run_step provision_web_proxy; rc=$?
check '[ $rc -eq 0 ] && ! grep -q apache2ctl "$T/log"' "SKIP_WEB_PROXY=1 no toca Apache"
FAKE_ACTIVE="apache2" FAKE_APACHE_VERSION=2.4.62 run_step provision_web_proxy; rc=$?
check '[ $rc -eq 0 ] && grep -q "upgrade=websocket" "$VH"' "provision_web_proxy despacha a Apache"

echo "F) Sin vhost de MegaISP"
mkvhosts; rm "$T/etc/sites-enabled/claudemegaisp.conf"
FAKE_APACHE_VERSION=2.4.62 run_step provision_apache; rc=$?
check '[ $rc -ne 0 ] && grep -q "APACHE_VHOST" "$T/out"' "aborta y sugiere APACHE_VHOST"
check 'cmp -s "$LEG" "$T/legacy.orig"' "no toca el legacy"

echo; echo "RESULTADO: $PASS ok, $FAIL fallas"
[ $FAIL -eq 0 ]

#!/usr/bin/env bash
# =============================================================================
# test-90-unprovision.sh — prueba de lib/90-unprovision.sh (quitar el proxy y el .env)
# =============================================================================
# Mismo enfoque que test-30-apache.sh: Apache y nginx SIMULADOS (vhosts en un
# directorio temporal + shims en PATH). Se inyecta el proxy con el paso real del
# instalador y se quita con el de la desinstalación: el vhost debe quedar idéntico
# al original. No necesita root ni toca /etc.
#
#   bash deploy/provision/tests/test-90-unprovision.sh      # exit 0 = todo OK
# =============================================================================
set -uo pipefail
LIB="$(cd "$(dirname "${BASH_SOURCE[0]}")/../lib" && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
PASS=0; FAIL=0
ok()   { echo "  ✔ $*"; PASS=$((PASS+1)); }
bad()  { echo "  ✘ $*"; FAIL=$((FAIL+1)); }
check(){ if eval "$1"; then ok "$2"; else bad "$2"; echo "    salida:"; sed 's/^/      /' "$T/out"; fi; }

mkdir -p "$T/bin"
cat > "$T/bin/apache2ctl" <<'EOF'
#!/usr/bin/env bash
echo "apache2ctl $*" >> "$FAKE_LOG"
case "$1" in
  -v) echo "Server version: Apache/2.4.62 (Debian)";;
  -M) echo "Loaded Modules:"; echo " proxy_module (shared)"; echo " proxy_http_module (shared)";;
  configtest) if [ "${FAKE_CONFIGTEST:-0}" = 0 ]; then echo "Syntax OK" >&2; exit 0; else echo "AH00526: Syntax error" >&2; exit 1; fi;;
  graceful) exit 0;;
esac
EOF
cat > "$T/bin/nginx" <<'EOF'
#!/usr/bin/env bash
echo "nginx $*" >> "$FAKE_LOG"
[ "$1" = -t ] && { [ "${FAKE_NGINX_T:-0}" = 0 ] && exit 0 || exit 1; }
exit 0
EOF
cat > "$T/bin/systemctl" <<'EOF'
#!/usr/bin/env bash
echo "systemctl $*" >> "$FAKE_LOG"; exit 0
EOF
cat > "$T/bin/php" <<'EOF'
#!/usr/bin/env bash
echo "php $*" >> "$FAKE_LOG"; exit 0
EOF
chmod +x "$T/bin/"*

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
  cat > "$T/nginx.conf" <<'EOF'
server {
    listen 80;
    server_name _;
    root /var/www/ClaudeMegaISP/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
EOF
  : > "$T/log"
}

run_step() {
  ( set -euo pipefail
    export PATH="$T/bin:$PATH" FAKE_LOG="$T/log"
    export MEGAISP_ROOT="${TEST_ROOT:-/var/www/ClaudeMegaISP}" APACHE_SITES_DIR="$T/etc/sites-enabled" NGINX_VHOST="$T/nginx.conf"
    export MEGAISP_ENV="$T/megaisp.env" MEGAISP_USER="$(id -un)"
    source "$LIB/common.sh"; source "$LIB/05-evolution-user.sh"; source "$LIB/20-evolution.sh"
    source "$LIB/30-nginx.sh"; source "$LIB/30-apache.sh"; source "$LIB/90-unprovision.sh"
    run_root() { "$@"; }      # sin sudo en la prueba
    run_as()   { shift; "$@"; }
    "$@" ) > "$T/out" 2>&1
}

VH="$T/etc/sites-available/claudemegaisp.conf"; LEG="$T/etc/sites-available/legacy.conf"

echo "A) Apache: inyectar con el instalador y quitar con la desinstalación"
mkvhosts; cp "$VH" "$T/orig.conf"; cp "$LEG" "$T/legacy.orig"
run_step provision_apache
check 'grep -q "ProxyPass        /evolution/" "$VH"' "(preparación) el instalador inyectó el proxy"
run_step _unprovision_apache; rc=$?
check '[ $rc -eq 0 ]' "termina bien (rc=$rc)"
check 'cmp -s "$VH" "$T/orig.conf"' "el vhost queda idéntico al original"
check 'cmp -s "$LEG" "$T/legacy.orig"' "el vhost legacy no se toca"
check '[ -L "$T/etc/sites-enabled/claudemegaisp.conf" ]' "el symlink de sites-enabled sigue siendo symlink"
check 'ls "$T/etc/sites-available/"claudemegaisp.conf.purge.*.bak >/dev/null 2>&1' "deja respaldo con fecha"
check 'grep -q "systemctl reload apache2" "$T/log"' "recarga Apache"

echo "B) Apache: segunda corrida"
: > "$T/log"; run_step _unprovision_apache; rc=$?
check '[ $rc -eq 0 ] && grep -q "Nada que quitar: bloque /evolution/ en Apache" "$T/out"' "no hay nada que quitar"
check '! grep -q "systemctl reload" "$T/log"' "no recarga Apache"

echo "C) Apache: configtest falla al quitar"
mkvhosts; run_step provision_apache; cp "$VH" "$T/inyectado.conf"; : > "$T/log"
FAKE_CONFIGTEST=1 run_step _unprovision_apache; rc=$?
check '[ $rc -ne 0 ]' "aborta (rc=$rc)"
check 'cmp -s "$VH" "$T/inyectado.conf"' "restaura el vhost tal como estaba"
check '! grep -q "systemctl reload" "$T/log"' "no recarga Apache"

echo "D) Apache: marcadores que no cuadran"
mkvhosts; run_step provision_apache
sed -i '0,/# <<< evolution-api <<</{/# <<< evolution-api <<</d}' "$VH"; cp "$VH" "$T/roto.conf"
run_step _unprovision_apache; rc=$?
check '[ $rc -ne 0 ] && grep -q "no cuadran" "$T/out"' "aborta con mensaje claro"
check 'cmp -s "$VH" "$T/roto.conf"' "no toca el archivo"

echo "E) nginx: inyectar y quitar"
mkvhosts; cp "$T/nginx.conf" "$T/nginx.orig"
FAKE_HAS=1 run_step provision_nginx
check 'grep -q "location /evolution/" "$T/nginx.conf"' "(preparación) el instalador inyectó el location"
run_step _unprovision_nginx; rc=$?
check '[ $rc -eq 0 ] && cmp -s "$T/nginx.conf" "$T/nginx.orig"' "el vhost queda idéntico al original"
run_step _unprovision_nginx
check 'grep -q "Nada que quitar: location /evolution/" "$T/out"' "segunda corrida: nada que quitar"

echo "F) nginx: nginx -t falla al quitar"
mkvhosts; run_step provision_nginx; cp "$T/nginx.conf" "$T/nginx.iny"
FAKE_NGINX_T=1 run_step _unprovision_nginx; rc=$?
check '[ $rc -ne 0 ] && cmp -s "$T/nginx.conf" "$T/nginx.iny"' "aborta y restaura el vhost"

echo "G) .env de MegaISP: URL local"
printf 'APP_URL=https://prod.example\nWHATSAPP_API_URL=http://127.0.0.1:8080\nWHATSAPP_API_KEY=abc123\nOTRA=1\n' > "$T/megaisp.env"
chmod 640 "$T/megaisp.env"
: > "$T/log"; TEST_ROOT="$T" run_step unprovision_megaisp_env; rc=$?
check '[ $rc -eq 0 ]' "termina bien"
check '! grep -q WHATSAPP_API_KEY "$T/megaisp.env" && ! grep -q WHATSAPP_API_URL "$T/megaisp.env"' "quita la key y la URL local"
check 'grep -q "^OTRA=1$" "$T/megaisp.env" && grep -q "^APP_URL=" "$T/megaisp.env"' "deja lo demás"
check '[ "$(stat -c %a "$T/megaisp.env")" = 640 ]' "conserva los permisos del .env"
check 'ls "$T"/megaisp.env.purge.*.bak >/dev/null 2>&1' "respalda el .env antes"
check 'grep -q "php artisan config:clear" "$T/log"' "refresca la config de MegaISP"
check '! grep -q abc123 "$T/out"' "no imprime la key"

echo "H) .env de MegaISP: URL que no puso el instalador"
rm -f "$T"/megaisp.env.purge.*.bak
printf 'WHATSAPP_API_URL=https://evo.otro.server\nWHATSAPP_API_KEY=abc123\n' > "$T/megaisp.env"
TEST_ROOT="$T" run_step unprovision_megaisp_env
check 'grep -q "^WHATSAPP_API_URL=https://evo.otro.server$" "$T/megaisp.env" && ! grep -q WHATSAPP_API_KEY "$T/megaisp.env"' "deja la URL ajena, quita la key"

echo "I) .env de MegaISP: ya limpio"
printf 'APP_URL=https://prod.example\n' > "$T/megaisp.env"; rm -f "$T"/megaisp.env.purge.*.bak; : > "$T/log"
TEST_ROOT="$T" run_step unprovision_megaisp_env
check 'grep -q "Nada que quitar: WHATSAPP_API_KEY" "$T/out" && ! ls "$T"/megaisp.env.purge.*.bak >/dev/null 2>&1 && ! grep -q php "$T/log"' "no toca nada"

echo
echo "Resultado: ${PASS} OK, ${FAIL} fallidos"
[[ $FAIL -eq 0 ]]

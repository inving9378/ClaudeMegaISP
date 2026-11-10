#!/usr/bin/env bash
# =============================================================================
# test-40-proxy-ws.sh — prueba de lib/40-proxy-ws.sh (poner y quitar /ws)
# =============================================================================
# nginx y Apache SIMULADOS: vhosts en un directorio temporal y shims de nginx /
# apache2ctl / a2enmod / a2dismod / systemctl en PATH que registran lo que se les
# pide. No necesita root ni toca /etc. Cada paso corre con `set -euo pipefail`,
# igual que install.sh.
#
#   bash app/Modules/Addons/VoIP/system/provision/tests/test-40-proxy-ws.sh   # exit 0 = OK
# =============================================================================
set -uo pipefail
LIB="$(cd "$(dirname "${BASH_SOURCE[0]}")/../lib" && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
PASS=0; FAIL=0
ok()   { echo "  ✔ $*"; PASS=$((PASS+1)); }
bad()  { echo "  ✘ $*"; FAIL=$((FAIL+1)); [[ -f "$T/out" ]] && sed 's/^/      /' "$T/out"; }
check(){ if eval "$1"; then ok "$2"; else bad "$2"; fi; }

mkdir -p "$T/bin"
cat > "$T/bin/apache2ctl" <<'EOF'
#!/usr/bin/env bash
echo "apache2ctl $*" >> "$FAKE_LOG"
case "$1" in
  -v) echo "Server version: Apache/${FAKE_APACHE_VERSION:-2.4.62} (Debian)";;
  -M) echo "Loaded Modules:"; while read -r m; do [ -n "$m" ] && echo " ${m}_module (shared)"; done < "$FAKE_MODS";;
  configtest) if [ "${FAKE_CONFIGTEST:-0}" = 0 ]; then echo "Syntax OK" >&2; exit 0; else echo "AH00526: Syntax error on line 7" >&2; exit 1; fi;;
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
cat > "$T/bin/nginx" <<'EOF'
#!/usr/bin/env bash
echo "nginx $*" >> "$FAKE_LOG"
[ "$1" = -t ] && { [ "${FAKE_NGINX_T:-0}" = 0 ] && exit 0 || { echo "nginx: [emerg] unexpected" >&2; exit 1; }; }
exit 0
EOF
cat > "$T/bin/systemctl" <<'EOF'
#!/usr/bin/env bash
echo "systemctl $*" >> "$FAKE_LOG"
if [ "$1" = is-active ]; then svc="${@: -1}"; [[ " ${FAKE_ACTIVE:-} " == *" $svc "* ]] && exit 0 || exit 3; fi
exit 0
EOF
chmod +x "$T/bin/"*
export PATH="$T/bin:$PATH" FAKE_LOG="$T/fake.log" FAKE_MODS="$T/mods"

# run "CODIGO" — corre CODIGO en un bash nuevo con las libs cargadas; salida en $T/out.
run() {
    MEGAISP_ROOT=/var/www/ClaudeMegaISP MEGAISP_ENV="$T/env" VOIP_BACKUP_DIR="$T/backups" \
    APACHE_SITES_DIR="$T/apache" NGINX_SITES_DIR="$T/nginx" NGINX_CONFD_DIR="$T/confd" \
    bash -c "set -euo pipefail; source '$LIB/common.sh'; source '$LIB/40-proxy-ws.sh'; $1" > "$T/out" 2>&1
}
printf 'APP_URL=https://megaisp.example\nASTERISK_ARI_PORT=8088\n' > "$T/env"

# ---------------------------------------------------------------- Apache -----
mkapache() {
    rm -rf "$T/apache" "$T/apache-src"; mkdir -p "$T/apache" "$T/apache-src"
    cat > "$T/apache-src/megaisp.conf" <<'EOF'
<VirtualHost *:80>
    ServerName prod.example
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
    cat > "$T/apache-src/legacy.conf" <<'EOF'
<VirtualHost *:8090>
    DocumentRoot /var/www/MEGANET/public
</VirtualHost>
EOF
    ln -s "$T/apache-src/megaisp.conf" "$T/apache/megaisp.conf"
    ln -s "$T/apache-src/legacy.conf" "$T/apache/legacy.conf"
    cp "$T/apache-src/megaisp.conf" "$T/orig-megaisp.conf"
    cp "$T/apache-src/legacy.conf" "$T/orig-legacy.conf"
    : > "$T/fake.log"
}

echo "Apache >= 2.4.47"
mkapache; printf 'proxy\n' > "$FAKE_MODS"
run 'provision_apache_ws'; rc=$?
check '[[ $rc == 0 ]]' "termina bien"
check '[[ $(grep -c "ProxyPass /ws http://127.0.0.1:8088/ws upgrade=websocket" "$T/apache-src/megaisp.conf") == 2 ]]' "inyecta en los 2 VirtualHost (:80 y :443)"
check 'cmp -s "$T/apache-src/legacy.conf" "$T/orig-legacy.conf"' "no toca el vhost de otro DocumentRoot"
check 'grep -qx proxy_http "$FAKE_MODS" && grep -q "a2enmod -q proxy_http" "$FAKE_LOG"' "habilita proxy_http (faltaba)"
check '! grep -q "a2enmod -q proxy$" "$FAKE_LOG"' "no habilita proxy (ya estaba)"
check 'grep -q "systemctl reload apache2" "$FAKE_LOG"' "recarga Apache"
check '[[ -L "$T/apache/megaisp.conf" ]]' "el symlink de sites-enabled sigue siendo symlink"
check 'ls "$T/backups/vhosts/"*megaisp.conf.provision-voip.*.bak >/dev/null 2>&1' "deja respaldo con fecha en /var/backups/megaisp/vhosts"
check '[[ -z "$(ls "$T/apache-src" | grep bak)" ]]' "no deja .bak junto al vhost (sites-enabled lo cargaría)"
cp "$T/apache-src/megaisp.conf" "$T/despues1"
run 'provision_apache_ws'
check 'cmp -s "$T/apache-src/megaisp.conf" "$T/despues1" && grep -q "OMITIDO" "$T/out"' "segunda corrida: OMITIDO, sin cambios"

echo "Quitar /ws (Apache)"
: > "$FAKE_LOG"
run 'quitar_proxy_ws'; rc=$?
check '[[ $rc == 0 ]]' "termina bien"
check 'cmp -s "$T/apache-src/megaisp.conf" "$T/orig-megaisp.conf"' "el vhost queda idéntico al original"
check 'grep -q "systemctl reload apache2" "$FAKE_LOG"' "recarga Apache"
run 'quitar_proxy_ws'
check 'grep -q "Nada que quitar: bloque /ws en Apache" "$T/out"' "segunda corrida: Nada que quitar"

echo "Apache < 2.4.47"
mkapache; printf 'proxy\nproxy_http\n' > "$FAKE_MODS"
FAKE_APACHE_VERSION=2.4.41 run 'provision_apache_ws'; rc=$?
check '[[ $rc == 0 ]] && [[ $(grep -c "ProxyPass /ws ws://127.0.0.1:8088/ws" "$T/apache-src/megaisp.conf") == 2 ]]' "usa ws:// (mod_proxy_wstunnel)"
check 'grep -qx proxy_wstunnel "$FAKE_MODS"' "habilita proxy_wstunnel"

echo "Apache: configtest falla → rollback"
mkapache; printf 'proxy\n' > "$FAKE_MODS"
FAKE_CONFIGTEST=1 run 'provision_apache_ws'; rc=$?
check '[[ $rc != 0 ]]' "aborta con error"
check 'cmp -s "$T/apache-src/megaisp.conf" "$T/orig-megaisp.conf"' "restaura el vhost"
check '! grep -qx proxy_http "$FAKE_MODS"' "deshabilita el módulo que habilitó"
check '! grep -q "systemctl reload apache2" "$FAKE_LOG"' "NO recarga Apache"
check 'grep -q "AH00526" "$T/out"' "muestra la salida de configtest"

echo "Apache: /ws puesto a mano"
mkapache; printf 'proxy\nproxy_http\n' > "$FAKE_MODS"
sed -i 's|DocumentRoot /var/www/ClaudeMegaISP/public|&\n    ProxyPass /ws http://127.0.0.1:8088/ws upgrade=websocket|' "$T/apache-src/megaisp.conf"
cp "$T/apache-src/megaisp.conf" "$T/manual"
run 'provision_apache_ws'
check 'cmp -s "$T/apache-src/megaisp.conf" "$T/manual" && grep -q "ya tiene el proxy /ws" "$T/out"' "no duplica"
run 'quitar_proxy_ws'
check 'cmp -s "$T/apache-src/megaisp.conf" "$T/manual"' "al quitar no toca un /ws que no es suyo"

# ----------------------------------------------------------------- nginx -----
mknginx() {
    rm -rf "$T/nginx" "$T/confd"; mkdir -p "$T/nginx" "$T/confd"
    cat > "$T/nginx/megaisp.conf" <<'EOF'
server {
    listen 80;
    server_name prod.example;
    root /var/www/ClaudeMegaISP/public;
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
server {
    listen 443 ssl;
    server_name prod.example;
    root /var/www/ClaudeMegaISP/public;   # TLS
    location ~ \.php$ { include fastcgi.conf; }
}
EOF
    cat > "$T/nginx/manual-tls.conf" <<'EOF'
server {
    listen 8443 ssl;
    server_name otro.example;
    root /var/www/ClaudeMegaISP/public;
    location /ws {
        proxy_pass http://127.0.0.1:8088/ws;
    }
}
EOF
    cat > "$T/nginx/otro.conf" <<'EOF'
server {
    listen 80;
    server_name legacy.example;
    root /var/www/MEGANET/public;
}
EOF
    for f in megaisp manual-tls otro; do cp "$T/nginx/$f.conf" "$T/orig-$f.conf"; done
    : > "$T/fake.log"
}

echo "nginx"
mknginx
run 'provision_nginx_ws'; rc=$?
check '[[ $rc == 0 ]]' "termina bien"
check '[[ $(grep -c "location /ws {" "$T/nginx/megaisp.conf") == 2 ]]' "inyecta en los 2 server{} con el root de MegaISP"
check 'awk "/server_name prod.example;/{getline; getline; getline; print; exit}" "$T/nginx/megaisp.conf" | grep -q "location /ws"' "queda justo después del server_name"
check 'grep -q "proxy_set_header   Upgrade    \$http_upgrade" "$T/nginx/megaisp.conf"' "las variables de nginx quedan literales"
check 'cmp -s "$T/nginx/manual-tls.conf" "$T/orig-manual-tls.conf"' "no duplica donde ya hay un location /ws"
check 'cmp -s "$T/nginx/otro.conf" "$T/orig-otro.conf"' "no toca el server{} de otro root"
check '! ls "$T/backups/vhosts/"*manual-tls.conf.* >/dev/null 2>&1' "no deja respaldo donde no cambió nada"
check '[[ -z "$(ls "$T/nginx" | grep bak)" ]]' "no deja .bak dentro de sites-enabled (nginx lo cargaría)"
check 'grep -q "nginx -t" "$FAKE_LOG" && grep -q "systemctl reload nginx" "$FAKE_LOG"' "valida y recarga nginx"
cp "$T/nginx/megaisp.conf" "$T/despues-n"
run 'provision_nginx_ws'
check 'cmp -s "$T/nginx/megaisp.conf" "$T/despues-n" && grep -q "OMITIDO" "$T/out"' "segunda corrida: OMITIDO, sin cambios"

echo "Quitar /ws (nginx)"
: > "$FAKE_LOG"
run 'quitar_proxy_ws'; rc=$?
check '[[ $rc == 0 ]] && cmp -s "$T/nginx/megaisp.conf" "$T/orig-megaisp.conf"' "el vhost queda idéntico al original"
check 'cmp -s "$T/nginx/manual-tls.conf" "$T/orig-manual-tls.conf"' "el /ws puesto a mano se deja"

echo "nginx: nginx -t falla → rollback"
mknginx
FAKE_NGINX_T=1 run 'provision_nginx_ws'; rc=$?
check '[[ $rc != 0 ]] && cmp -s "$T/nginx/megaisp.conf" "$T/orig-megaisp.conf"' "aborta y restaura el vhost"
check '! grep -q "systemctl reload nginx" "$FAKE_LOG"' "NO recarga nginx"

echo "Detección del servidor web"
FAKE_ACTIVE="nginx apache2" run '_detect_web_server'; rc=$?
check '[[ $rc != 0 ]] && grep -q "activos a la vez" "$T/out"' "nginx y Apache activos → pide WEB_SERVER"
FAKE_ACTIVE="apache2" run '_detect_web_server'
check '[[ $(cat "$T/out") == apache ]]' "Apache activo → apache"
SKIP_WEB_PROXY=1 run 'provision_proxy_ws'; rc=$?
check '[[ $rc == 0 ]] && grep -q "OMITIDO por SKIP_WEB_PROXY" "$T/out"' "SKIP_WEB_PROXY=1 omite el paso"

echo
echo "Resultado: ${PASS} bien, ${FAIL} mal"
(( FAIL == 0 ))

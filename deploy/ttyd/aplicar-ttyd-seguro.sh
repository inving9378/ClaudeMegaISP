#!/usr/bin/env bash
# =============================================================================
# Reenciende la terminal web (ttyd) de DevTools — de forma SEGURA.
#
#   sudo bash deploy/ttyd/aplicar-ttyd-seguro.sh            # aplicar
#   sudo bash deploy/ttyd/aplicar-ttyd-seguro.sh --apagar   # apagarla otra vez
#
# Por qué existe: el 2026-10-14 se apagó ttyd porque corría
# `ttyd -p 7681 -W --interface 0.0.0.0 bash` — una terminal SIN contraseña
# abierta a internet. Hubo shells desde IPs externas y quedó un backdoor
# (ver docs/bitacora/2026-10-14-consola-ttyd-diagnostico.md, rama
# docs/bitacora-consola-ttyd). Este script la deja así:
#
#   1. ttyd escucha SOLO en 127.0.0.1 → desde fuera del servidor no se llega
#      al puerto 7681.
#   2. nginx /ttyd/ pregunta a Laravel (auth_request → /devtools/terminal-auth)
#      antes de CADA petición: sin sesión → 401, sin rol DESARROLLADOR o
#      super-administrator → 403.
#   3. ttyd corre con --auth-header: si la petición no trae el header que pone
#      nginx tras autorizar, la rechaza (doble candado).
#   4. --check-origin: un sitio ajeno no puede abrir el WebSocket usando la
#      cookie del usuario.
#
# REQUISITO: la rama fix/ttyd-terminal-segura debe estar mergeada en
# /var/www/megaisp (aporta la ruta /devtools/terminal-auth). El script lo
# verifica y aborta si no.
#
# Todo el contenido que se instala está AQUÍ abajo (heredocs): revisa este
# archivo antes de correrlo como root.
# =============================================================================
set -euo pipefail

UNIT=/etc/systemd/system/ttyd.service
SNIPPET=/etc/nginx/snippets/megaisp-ttyd.conf
VHOST80=/etc/nginx/sites-available/megaisp.conf
VHOSTTLS=/etc/nginx/sites-available/megaisp-dev-tls.conf
INCLUDE='include /etc/nginx/snippets/megaisp-ttyd.conf;'

die() { echo "✗ $*" >&2; exit 1; }
ok()  { echo "✓ $*"; }

[ "$(id -u)" -eq 0 ] || die "Correr con sudo: sudo bash $0"

if [ "${1:-}" = "--apagar" ]; then
    systemctl disable --now ttyd
    ok "ttyd apagado y deshabilitado (nginx /ttyd/ responderá 502)."
    exit 0
fi

# --- 0. Pre-requisito: la puerta de Laravel existe en el checkout vivo --------
code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: localhost' http://127.0.0.1/devtools/terminal-auth || true)
[ "$code" = "401" ] || die "GET /devtools/terminal-auth devolvió $code (se esperaba 401).
  ¿Está mergeada la rama fix/ttyd-terminal-segura en /var/www/megaisp?
  Si ya lo está:  cd /var/www/megaisp && php artisan route:clear"
ok "La puerta /devtools/terminal-auth responde 401 sin sesión."

# --- 1. Respaldo ---------------------------------------------------------------
BK=/var/backups/ttyd-seguro-$(date +%Y%m%d_%H%M%S)
mkdir -p "$BK"
for f in "$UNIT" "$VHOST80" "$VHOSTTLS" "$SNIPPET"; do
    [ -e "$f" ] && cp -a "$f" "$BK/"
done
ok "Respaldo en $BK"

restaurar_nginx() {
    cp -a "$BK/megaisp.conf" "$VHOST80"
    [ -e "$BK/megaisp-dev-tls.conf" ] && cp -a "$BK/megaisp-dev-tls.conf" "$VHOSTTLS"
    if [ -e "$BK/megaisp-ttyd.conf" ]; then cp -a "$BK/megaisp-ttyd.conf" "$SNIPPET"; else rm -f "$SNIPPET"; fi
}

# --- 2. nginx: /ttyd/ solo con sesión válida ------------------------------------
mkdir -p "$(dirname "$SNIPPET")"
cat > "$SNIPPET" <<'NGINX'
# Terminal web ttyd de DevTools (instalado por deploy/ttyd/aplicar-ttyd-seguro.sh).
# Cada petición a /ttyd/ (HTML, /token y el WebSocket) pasa antes por Laravel:
# 204 → adelante; 401 sin sesión; 403 sin rol DESARROLLADOR/super-administrator.
location ^~ /ttyd/ {
    auth_request     /_megaisp_ttyd_auth;
    auth_request_set $megaisp_term_user $upstream_http_x_megaisp_term_user;

    proxy_pass         http://127.0.0.1:7681/;
    proxy_http_version 1.1;
    proxy_set_header   Upgrade             $http_upgrade;
    proxy_set_header   Connection          "upgrade";
    # $http_host (con puerto) para que --check-origin de ttyd compare bien.
    proxy_set_header   Host                $http_host;
    proxy_set_header   X-Real-IP           $remote_addr;
    proxy_set_header   X-Forwarded-For     $proxy_add_x_forwarded_for;
    # Lo pone SIEMPRE nginx (pisa cualquier valor que mande el cliente). ttyd
    # corre con --auth-header: sin este header rechaza la conexión.
    proxy_set_header   X-MegaISP-Term-User $megaisp_term_user;
    proxy_read_timeout 86400s;
}

location = /_megaisp_ttyd_auth {
    internal;
    proxy_pass              http://127.0.0.1:80/devtools/terminal-auth;
    proxy_pass_request_body off;
    proxy_set_header        Content-Length "";
    # Host fijo: cae en el default_server del :80 (el server_name del vhost TLS
    # en :80 solo redirige). La cookie de sesión viaja tal cual.
    proxy_set_header        Host           localhost;
    proxy_set_header        X-Original-URI $request_uri;
    proxy_set_header        X-Real-IP      $remote_addr;
}
NGINX
ok "Snippet nginx en $SNIPPET"

# vhost :80 — reemplaza el bloque viejo `location /ttyd/ { ... }` (sin auth).
if ! grep -qF "$INCLUDE" "$VHOST80"; then
    perl -0pi -e 's{^[ \t]*location /ttyd/ \{[^}]*\}[ \t]*\n}{    include /etc/nginx/snippets/megaisp-ttyd.conf;\n}m' "$VHOST80"
    grep -qF "$INCLUDE" "$VHOST80" || { restaurar_nginx; die "No se encontró el bloque location /ttyd/ en $VHOST80 — no se tocó nada."; }
fi
grep -q 'proxy_pass[[:space:]]*http://127.0.0.1:7681' "$VHOST80" && { restaurar_nginx; die "Quedó un proxy a 7681 sin auth en $VHOST80 — revertido."; }
ok "vhost :80 usa el bloque con auth"

# vhost TLS (dev.meganett.com.mx) — hoy no tiene /ttyd/; se agrega para usar la
# terminal por HTTPS (cifrada) en vez de HTTP plano.
if [ -e "$VHOSTTLS" ] && ! grep -qF "$INCLUDE" "$VHOSTTLS"; then
    perl -0pi -e 's{\n([ \t]*)location / \{}{\n$1include /etc/nginx/snippets/megaisp-ttyd.conf;\n\n$1location / \{}' "$VHOSTTLS"
    grep -qF "$INCLUDE" "$VHOSTTLS" || { restaurar_nginx; die "No se pudo agregar el include en $VHOSTTLS — revertido."; }
    ok "vhost TLS (dev.meganett.com.mx) también sirve /ttyd/ con auth"
fi

if ! nginx -t 2>&1; then
    restaurar_nginx
    nginx -t >/dev/null 2>&1 && die "nginx -t falló con el cambio — se restauró la config anterior."
    die "nginx -t falla incluso con la config restaurada: revisar a mano ($BK)."
fi
systemctl reload nginx
ok "nginx recargado"

# --- 3. ttyd: solo loopback + header obligatorio + origen -----------------------
cat > "$UNIT" <<'UNIT'
# Instalado por deploy/ttyd/aplicar-ttyd-seguro.sh — NO volver a --interface 0.0.0.0
# ni quitar --auth-header (incidente 2026-10-14: terminal sin contraseña a internet).
[Unit]
Description=ttyd web terminal (DevTools) - solo 127.0.0.1, detras de nginx con sesion
After=network.target

[Service]
User=meganet
ExecStart=/usr/bin/ttyd --port 7681 --interface 127.0.0.1 --writable --check-origin --auth-header X-MegaISP-Term-User --client-option scrollback=100000 bash
Restart=always
RestartSec=2

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable ttyd >/dev/null 2>&1
systemctl restart ttyd
ok "ttyd.service reescrito y arrancado"

# --- 4. Verificación -------------------------------------------------------------
escucha=""
for _ in 1 2 3 4 5 6 7 8 9 10; do
    escucha=$(ss -Htln 'sport = :7681' | awk '{print $4}' | sort -u | paste -sd' ')
    [ -n "$escucha" ] && break
    sleep 0.5
done
if [ "$escucha" != "127.0.0.1:7681" ]; then
    systemctl disable --now ttyd
    die "ttyd escucha en '$escucha' (se esperaba solo 127.0.0.1:7681) — APAGADO por seguridad."
fi
ok "ttyd escucha solo en 127.0.0.1:7681"

c1=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:7681/ || true)
[ "$c1" = "407" ] || { systemctl disable --now ttyd; die "ttyd directo sin header devolvió $c1 (se esperaba 407) — APAGADO por seguridad."; }
ok "ttyd directo sin el header de nginx → 407 (rechaza)"

c2=$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: localhost' -H 'X-MegaISP-Term-User: falso' http://127.0.0.1/ttyd/ || true)
[ "$c2" = "401" ] || { systemctl disable --now ttyd; die "/ttyd/ sin sesión devolvió $c2 (se esperaba 401) — APAGADO por seguridad."; }
ok "/ttyd/ sin sesión (aun mandando el header a mano) → 401"

echo
echo "Listo. Abre DevTools logueado con un usuario DESARROLLADOR o super-administrator."
echo "Cada apertura queda en activity_log (log_name='devtools-terminal')."
echo "Respaldo de lo anterior: $BK"

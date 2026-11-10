#!/usr/bin/env bash
# =============================================================================
# 40-proxy-ws.sh — /ws del vhost de MegaISP → WebSocket SIP de Asterisk
# =============================================================================
# Se hace `source` desde install.sh y uninstall.sh. Define provision_proxy_ws y
# quitar_proxy_ws. No ejecuta nada al ser sourceado.
#
# El mini-teléfono (MegaVoz) se registra por wss://<dominio>/ws
# (MiTelefonoController); Asterisk escucha HTTP solo en 127.0.0.1:<ARI_PORT>.
# El servidor web de MegaISP hace de puente, igual que /evolution/ para Evolution:
#   - nginx : `location /ws` en cada server{} cuyo root es <MEGAISP_ROOT>/public.
#   - Apache: `ProxyPass /ws` en cada <VirtualHost> con ese DocumentRoot
#             (>= 2.4.47 upgrade=websocket con mod_proxy_http; antes, ws:// con
#             mod_proxy_wstunnel).
# Por eso entra tanto en :80 como en :443 (wss va por el de TLS).
#
# Guardas (las de Evolution):
#   - Bloque entre marcadores; si el vhost ya tiene un /ws (puesto a mano, como
#     en dev) → OMITIR, no se duplica.
#   - Respaldo con fecha de cada archivo en /var/backups/megaisp/vhosts (fuera de
#     sites-enabled, que nginx incluye entero) antes de tocarlo; si `nginx -t` /
#     `apache2ctl configtest` falla se restauran los archivos (y en Apache se
#     deshabilitan los módulos que habilitó este paso). Nunca se recarga con una
#     configuración rota.
# Overrides: WEB_SERVER=nginx|apache, NGINX_VHOST, APACHE_VHOST, SKIP_WEB_PROXY=1.
# =============================================================================

: "${NGINX_SITES_DIR:=/etc/nginx/sites-enabled}"       # override solo para pruebas
: "${NGINX_CONFD_DIR:=/etc/nginx/conf.d}"
: "${APACHE_SITES_DIR:=/etc/apache2/sites-enabled}"
VOIP_WS_MARK_BEGIN="# >>> megaisp-voip /ws (VoIP/system/provision/lib/40-proxy-ws.sh) >>>"
VOIP_WS_MARK_END="# <<< megaisp-voip /ws <<<"

_ws_target_root() { printf '%s/public' "${MEGAISP_ROOT%/}"; }

# _detect_web_server — "nginx" o "apache" (mismo criterio que deploy/provision).
_detect_web_server() {
    case "${WEB_SERVER:-}" in
        nginx|apache) printf '%s' "$WEB_SERVER"; return 0 ;;
        "") ;;
        *) die "WEB_SERVER='${WEB_SERVER}' no es válido (usa nginx o apache)." ;;
    esac
    if [[ -n "${NGINX_VHOST:-}" ]];  then printf nginx;  return 0; fi
    if [[ -n "${APACHE_VHOST:-}" ]]; then printf apache; return 0; fi
    local nginx_on=0 apache_on=0
    systemctl is-active --quiet nginx 2>/dev/null && nginx_on=1
    systemctl is-active --quiet apache2 2>/dev/null && apache_on=1
    if (( nginx_on && apache_on )); then
        die "nginx y apache2 están activos a la vez; indica cuál sirve MegaISP con WEB_SERVER=nginx o WEB_SERVER=apache."
    fi
    if (( nginx_on ));  then printf nginx;  return 0; fi
    if (( apache_on )); then printf apache; return 0; fi
    if have nginx && ! have apache2ctl; then printf nginx;  return 0; fi
    if have apache2ctl && ! have nginx; then printf apache; return 0; fi
    die "No pude determinar el servidor web (nginx o apache2). Fija WEB_SERVER=nginx o WEB_SERVER=apache."
}

# _quitar_bloque ARCHIVO — reescribe ARCHIVO sin los bloques entre marcadores (y
# sin la línea en blanco que el instalador pone delante). Conserva dueño y permisos.
_quitar_bloque() {
    local f="$1" tmp; tmp="$(mktemp)"
    _B="$VOIP_WS_MARK_BEGIN" _E="$VOIP_WS_MARK_END" awk '
        BEGIN { b = ENVIRON["_B"]; e = ENVIRON["_E"]; skip = 0; held = 0 }
        { t = $0; sub(/^[[:space:]]+/, "", t); sub(/[[:space:]]+$/, "", t) }
        !skip && t == b { skip = 1; held = 0; next }
        skip { if (t == e) skip = 0; next }
        t == "" { if (held) print hl; held = 1; hl = $0; next }
        { if (held) { print hl; held = 0 } print }
        END { if (held) print hl }
    ' "$f" > "$tmp"
    cat "$tmp" > "$f"
    rm -f "$tmp"
}

# _respaldo_vhost ARCHIVO ETIQUETA STAMP — copia ARCHIVO a $VOIP_BACKUP_DIR/vhosts e
# imprime la ruta. FUERA de sites-enabled/conf.d a propósito: nginx incluye
# `sites-enabled/*` completo, y un .bak junto a un archivo normal (no symlink) se
# cargaría como configuración y duplicaría los server{}.
_respaldo_vhost() {
    local dir="${VOIP_BACKUP_DIR}/vhosts" nombre
    nombre="$(printf '%s' "$1" | tr '/' '_')"
    mkdir -p "$dir" && chmod 700 "$dir"
    cp -p "$1" "${dir}/${nombre}.${2}.${3}.bak" || die "No pude respaldar ${1} en ${dir}; no se tocó."
    printf '%s' "${dir}/${nombre}.${2}.${3}.bak"
}

# _marcadores_cuadran ARCHIVO — mismo número de inicios y fines.
_marcadores_cuadran() {
    [[ "$(grep -cF "$VOIP_WS_MARK_BEGIN" "$1")" == "$(grep -cF "$VOIP_WS_MARK_END" "$1")" ]]
}

# =============================================================================
# nginx
# =============================================================================

# _nginx_vhosts — archivos reales (uno por línea) con algún server{} cuyo `root`
# es exactamente <MEGAISP_ROOT>/public. Override NGINX_VHOST.
_nginx_vhosts() {
    if [[ -n "${NGINX_VHOST:-}" ]]; then
        [[ -f "$NGINX_VHOST" ]] || die "NGINX_VHOST='${NGINX_VHOST}' no existe"
        readlink -f "$NGINX_VHOST"
        return 0
    fi
    local target f
    target="$(_ws_target_root)"
    for f in "$NGINX_SITES_DIR"/* "$NGINX_CONFD_DIR"/*.conf; do
        [[ -e "$f" ]] || continue
        if _ROOT="$target" awk '
            BEGIN { t = ENVIRON["_ROOT"]; found = 0 }
            $1 == "root" { v = $2; gsub(/[";]/, "", v); sub(/\/+$/, "", v); if (v == t) found = 1 }
            END { exit(found ? 0 : 1) }
        ' "$f"; then
            readlink -f "$f"
        fi
    done | sort -u
}

# _nginx_inyectar ARCHIVO BLOQUE — inserta BLOQUE tras el server_name de cada
# server{} con el root de MegaISP que todavía no tenga un `location /ws`.
# Imprime en stderr "<insertados> <omitidos>".
_nginx_inyectar() {
    local f="$1" block="$2" tmp; tmp="$(mktemp)"
    _ROOT="$(_ws_target_root)" _BLK="$block" awk '
        function profundidad(linea,   s) { s = linea; sub(/#.*/, "", s); return gsub(/\{/, "{", s) - gsub(/\}/, "}", s) }
        BEGIN { t = ENVIRON["_ROOT"]; b = ENVIRON["_BLK"] }
        NR == FNR {
            if (d == 0 && $0 ~ /^[[:space:]]*server[[:space:]]*\{/) { n++ }
            if (d > 0 || $0 ~ /^[[:space:]]*server[[:space:]]*\{/) {
                if ($1 == "root") { v = $2; gsub(/[";]/, "", v); sub(/\/+$/, "", v); if (v == t) ok[n] = 1 }
                if ($0 ~ /^[[:space:]]*location[[:space:]]+(=[[:space:]]*|\^~[[:space:]]*)?\/ws\/?[[:space:]]*\{/) ws[n] = 1
            }
            d += profundidad($0); next
        }
        FNR == 1 { d = 0; m = 0 }
        {
            if (d == 0 && $0 ~ /^[[:space:]]*server[[:space:]]*\{/) { m++ }
            print
            if (d >= 1 && $1 == "server_name" && ok[m] && !ws[m] && !hecho[m]) { print b; hecho[m] = 1; ins++ }
            d += profundidad($0)
        }
        END {
            omit = 0
            for (i in ok) if (ws[i]) omit++
            print (ins + 0) " " omit > "/dev/stderr"
        }
    ' "$f" "$f" > "$tmp" 2> "${tmp}.n"
    cat "$tmp" > "$f"
    cat "${tmp}.n" >&2
    rm -f "$tmp" "${tmp}.n"
}

provision_nginx_ws() {
    log_step "nginx — location /ws en el vhost de MegaISP (WebSocket del mini-teléfono)"
    require nginx "Se requiere nginx instalado"
    local port; port="$(voip_ari_port)"
    local -a vhosts=()
    mapfile -t vhosts < <(_nginx_vhosts)
    (( ${#vhosts[@]} > 0 )) || die "No encontré un vhost de nginx con 'root $(_ws_target_root)'. Fija NGINX_VHOST=/ruta/al/vhost y reintenta (o SKIP_WEB_PROXY=1)."

    local block
    block="$(cat <<NGINX_BLOCK

    ${VOIP_WS_MARK_BEGIN}
    location /ws {
        proxy_pass         http://127.0.0.1:${port}/ws;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade    \$http_upgrade;
        proxy_set_header   Connection "upgrade";
        proxy_set_header   Host       \$host;
        proxy_read_timeout 3600s;
    }
    ${VOIP_WS_MARK_END}
NGINX_BLOCK
)"

    local stamp v cuenta ins omit total=0 bak
    stamp="$(date +%Y%m%d%H%M%S)"
    local -a tocados=() respaldos=()
    for v in "${vhosts[@]}"; do
        if grep -qF "$VOIP_WS_MARK_BEGIN" "$v"; then
            log_skip "El vhost ${v} ya tiene el bloque /ws de este instalador"
            continue
        fi
        bak="$(_respaldo_vhost "$v" provision-voip "$stamp")"
        cuenta="$(_nginx_inyectar "$v" "$block" 2>&1 >/dev/null)"
        read -r ins omit <<<"$cuenta"
        if (( ins == 0 )); then
            # Nada que poner (todos los server{} ya tenían su /ws): el respaldo sobra.
            rm -f "$bak"
            log_skip "El vhost ${v} ya tiene un location /ws (puesto a mano); no se duplica"
            continue
        fi
        tocados+=("$v"); respaldos+=("$bak")
        total=$((total + ins))
        log_info "location /ws inyectado en ${v} (${ins} server{}; ${omit} ya tenían el suyo; respaldo ${bak})"
    done
    if (( ${#tocados[@]} == 0 )); then
        return 0
    fi

    local errores
    if errores="$(nginx -t 2>&1)"; then
        if systemctl reload nginx >/dev/null 2>&1 || nginx -s reload >/dev/null 2>&1; then
            log_ok "nginx recargado con /ws → 127.0.0.1:${port}/ws (${total} server{})"
        else
            log_warn "Se inyectó /ws pero no pude recargar nginx; recárgalo a mano: systemctl reload nginx"
        fi
    else
        local i
        for i in "${!tocados[@]}"; do cat "${respaldos[$i]}" > "${tocados[$i]}"; done
        die "nginx -t falló tras inyectar /ws; se restauraron los vhosts y nginx NO se recargó. Salida: $(tail -n 5 <<<"$errores")"
    fi
}

# =============================================================================
# Apache
# =============================================================================

_apache_vhosts_ws() {
    if [[ -n "${APACHE_VHOST:-}" ]]; then
        [[ -f "$APACHE_VHOST" ]] || die "APACHE_VHOST='${APACHE_VHOST}' no existe"
        readlink -f "$APACHE_VHOST"
        return 0
    fi
    local target f
    target="$(_ws_target_root)"
    for f in "$APACHE_SITES_DIR"/*; do
        [[ -e "$f" ]] || continue
        if _ROOT="$target" awk '
            BEGIN { t = ENVIRON["_ROOT"]; found = 0 }
            tolower($1) == "documentroot" { v = $2; gsub(/"/, "", v); sub(/\/+$/, "", v); if (v == t) found = 1 }
            END { exit(found ? 0 : 1) }
        ' "$f"; then
            readlink -f "$f"
        fi
    done | sort -u
}

_apache_version() {
    { apache2ctl -v 2>/dev/null || true; } | sed -n 's|.*Apache/\([0-9][0-9.]*\).*|\1|p' | head -n1
}
_version_ge() { [[ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | head -n1)" == "$2" ]]; }
_apache_mod_loaded() { apache2ctl -M 2>/dev/null | grep -qE "^[[:space:]]*${1}_module[[:space:]]"; }

provision_apache_ws() {
    log_step "Apache — ProxyPass /ws en el vhost de MegaISP (WebSocket del mini-teléfono)"
    require apache2ctl "Se requiere Apache (apache2ctl) instalado"
    local port; port="$(voip_ari_port)"

    local -a vhosts=() pendientes=()
    mapfile -t vhosts < <(_apache_vhosts_ws)
    (( ${#vhosts[@]} > 0 )) || die "No encontré en ${APACHE_SITES_DIR} un vhost con 'DocumentRoot $(_ws_target_root)'. Fija APACHE_VHOST=/ruta/al/vhost y reintenta (o SKIP_WEB_PROXY=1)."
    local v
    for v in "${vhosts[@]}"; do
        if grep -qF "$VOIP_WS_MARK_BEGIN" "$v" || grep -qE '^[[:space:]]*ProxyPass[[:space:]]+/ws/?[[:space:]]' "$v"; then
            log_skip "El vhost ${v} ya tiene el proxy /ws"
        else
            pendientes+=("$v")
        fi
    done
    (( ${#pendientes[@]} > 0 )) || return 0

    local version; version="$(_apache_version)"
    [[ -n "$version" ]] || die "No pude leer la versión de Apache (apache2ctl -v)."
    local -a mods=(proxy)
    local block
    if _version_ge "$version" "2.4.47"; then
        log_info "Apache ${version}: WebSocket con 'upgrade=websocket' (mod_proxy_http)"
        mods+=(proxy_http)
        block="$(cat <<APACHE_BLOCK

    ${VOIP_WS_MARK_BEGIN}
    ProxyPass /ws http://127.0.0.1:${port}/ws upgrade=websocket timeout=3600
    ${VOIP_WS_MARK_END}
APACHE_BLOCK
)"
    else
        log_info "Apache ${version} (< 2.4.47): WebSocket con mod_proxy_wstunnel"
        mods+=(proxy_wstunnel)
        block="$(cat <<APACHE_BLOCK

    ${VOIP_WS_MARK_BEGIN}
    ProxyPass /ws ws://127.0.0.1:${port}/ws timeout=3600
    ${VOIP_WS_MARK_END}
APACHE_BLOCK
)"
    fi

    local -a habilitados=()
    local m
    for m in "${mods[@]}"; do
        if _apache_mod_loaded "$m"; then
            log_skip "Módulo Apache ${m}"
        else
            log_info "Habilitando módulo Apache ${m}"
            a2enmod -q "$m" >/dev/null || die "a2enmod ${m} falló"
            habilitados+=("$m")
        fi
    done

    local stamp tmp n target bak
    local -a respaldos=()
    stamp="$(date +%Y%m%d%H%M%S)"
    target="$(_ws_target_root)"
    for v in "${pendientes[@]}"; do
        bak="$(_respaldo_vhost "$v" provision-voip "$stamp")"
        respaldos+=("$bak")
        tmp="$(mktemp)"
        _ROOT="$target" _BLK="$block" awk '
            BEGIN { t = ENVIRON["_ROOT"]; b = ENVIRON["_BLK"]; n = 0 }
            { print }
            tolower($1) == "documentroot" {
                v = $2; gsub(/"/, "", v); sub(/\/+$/, "", v)
                if (v == t) { print b; n++ }
            }
            END { print n > "/dev/stderr" }
        ' "$v" > "$tmp" 2> "${tmp}.n"
        n="$(cat "${tmp}.n")"
        cat "$tmp" > "$v"
        rm -f "$tmp" "${tmp}.n"
        log_info "Proxy /ws inyectado en ${v} (${n} VirtualHost; respaldo ${bak})"
    done

    local errores
    if errores="$(apache2ctl configtest 2>&1)"; then
        if systemctl reload apache2 >/dev/null 2>&1 || apache2ctl graceful >/dev/null 2>&1; then
            log_ok "Apache recargado con /ws → 127.0.0.1:${port}/ws"
        else
            log_warn "Se configuró /ws pero no pude recargar Apache; recárgalo a mano: systemctl reload apache2"
        fi
    else
        local i
        for i in "${!pendientes[@]}"; do cat "${respaldos[$i]}" > "${pendientes[$i]}"; done
        for m in "${habilitados[@]}"; do a2dismod -q -f "$m" >/dev/null 2>&1 || true; done
        die "apache2ctl configtest falló tras inyectar /ws; se restauraron los vhosts y se deshabilitaron los módulos que habilitó este paso. Apache NO se recargó. Salida: $(tail -n 5 <<<"$errores")"
    fi
}

provision_proxy_ws() {
    if [[ "${SKIP_WEB_PROXY:-}" == "1" ]]; then
        log_step "Proxy /ws — OMITIDO por SKIP_WEB_PROXY=1"
        log_warn "El mini-teléfono web no podrá registrarse (wss://<dominio>/ws) hasta poner el proxy; teléfonos SIP y troncales no dependen de esto."
        return 0
    fi
    local ws; ws="$(_detect_web_server)"
    log_info "Servidor web de MegaISP: ${ws}"
    case "$ws" in
        nginx)  provision_nginx_ws ;;
        apache) provision_apache_ws ;;
    esac
}

# =============================================================================
# Quitar (uninstall.sh)
# =============================================================================

# _quitar_de_archivos SERVIDOR ARCHIVOS... — quita el bloque, valida y recarga;
# si la validación falla, restaura y aborta.
_quitar_de_archivos() {
    local srv="$1"; shift
    local stamp v errores bak i
    local -a respaldos=()
    stamp="$(date +%Y%m%d%H%M%S)"
    for v in "$@"; do
        _marcadores_cuadran "$v" || die "En ${v} los marcadores del bloque /ws no cuadran; quítalo a mano."
    done
    for v in "$@"; do
        bak="$(_respaldo_vhost "$v" purge-voip "$stamp")"
        respaldos+=("$bak")
        _quitar_bloque "$v"
        log_info "Bloque /ws quitado de ${v} (respaldo ${bak})"
    done
    local -a archivos=("$@")
    if [[ "$srv" == nginx ]]; then
        if errores="$(nginx -t 2>&1)"; then
            if systemctl reload nginx >/dev/null 2>&1 || nginx -s reload >/dev/null 2>&1; then
                log_ok "nginx recargado sin el proxy /ws"
            else
                log_warn "Se quitó /ws pero no pude recargar nginx; recárgalo a mano: systemctl reload nginx"
            fi
        else
            for i in "${!archivos[@]}"; do cat "${respaldos[$i]}" > "${archivos[$i]}"; done
            die "nginx -t falló al quitar /ws; se restauraron los vhosts y nginx NO se recargó. Salida: $(tail -n 5 <<<"$errores")"
        fi
    else
        if errores="$(apache2ctl configtest 2>&1)"; then
            if systemctl reload apache2 >/dev/null 2>&1 || apache2ctl graceful >/dev/null 2>&1; then
                log_ok "Apache recargado sin el proxy /ws"
            else
                log_warn "Se quitó /ws pero no pude recargar Apache; recárgalo a mano: systemctl reload apache2"
            fi
        else
            for i in "${!archivos[@]}"; do cat "${respaldos[$i]}" > "${archivos[$i]}"; done
            die "apache2ctl configtest falló al quitar /ws; se restauraron los vhosts y Apache NO se recargó. Salida: $(tail -n 5 <<<"$errores")"
        fi
    fi
}

# _con_marcador DIR... — archivos reales que traen el bloque de este instalador.
_con_marcador() {
    local d f
    for d in "$@"; do
        for f in "$d"/*; do
            [[ -e "$f" ]] || continue
            grep -qF "$VOIP_WS_MARK_BEGIN" "$f" && readlink -f "$f"
        done
    done | sort -u
}

quitar_proxy_ws() {
    log_step "Proxy /ws del servidor web"
    local -a archivos=()
    if have nginx; then
        mapfile -t archivos < <(_con_marcador "$NGINX_SITES_DIR" "$NGINX_CONFD_DIR")
        if (( ${#archivos[@]} > 0 )); then _quitar_de_archivos nginx "${archivos[@]}"; else log_gone "bloque /ws en nginx"; fi
    fi
    if have apache2ctl; then
        mapfile -t archivos < <(_con_marcador "$APACHE_SITES_DIR")
        if (( ${#archivos[@]} > 0 )); then _quitar_de_archivos apache "${archivos[@]}"; else log_gone "bloque /ws en Apache"; fi
    fi
    # Un /ws puesto a mano (sin marcadores) no es de este instalador: se deja.
}

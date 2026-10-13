#!/usr/bin/env bash
# =============================================================================
# 30-apache.sh — Proxy /evolution/ en el vhost Apache de MegaISP (item #9991239)
# =============================================================================
# Se hace `source` desde install.sh (que ya cargó common.sh). Define
# provision_apache. No ejecuta nada al ser sourceado.
#
# Equivalente Apache de 30-nginx.sh: PRODUCCIÓN corre Apache2, no nginx, y el
# paso de nginx moría ahí ("No pude detectar el vhost de MegaISP") ANTES del paso
# 40 — Evolution quedaba compilada pero sin arrancar.
#
# Mismo diseño que la rama nginx: Evolution NO tiene vhost propio; se cuelga
# /evolution/ DENTRO del vhost existente de MegaISP → http://127.0.0.1:8080/
# (barra final → quita el prefijo) con soporte WebSocket para el QR/estado.
#   - Apache >= 2.4.47: `ProxyPass ... upgrade=websocket` (mod_proxy_http).
#   - Apache anterior : RewriteRule [P] a ws:// (mod_proxy_wstunnel + mod_rewrite).
#
# Guardas:
#   - Se inyecta en TODOS los <VirtualHost> habilitados cuyo DocumentRoot es
#     exactamente ${MEGAISP_ROOT}/public (p. ej. :80 y :443). Nunca en otro
#     vhost (el legacy /var/www/MEGANET queda fuera por comparación exacta).
#   - Archivo que ya trae el marcador o un ProxyPass a /evolution/ → OMITIR.
#   - Respaldo con fecha de cada archivo antes de tocarlo; si `apache2ctl
#     configtest` falla se restauran los archivos Y se deshabilitan los módulos
#     que este paso habilitó. Apache no se recarga con una config rota.
# =============================================================================

: "${APACHE_SITES_DIR:=/etc/apache2/sites-enabled}"   # override solo para pruebas
EVO_APACHE_MARK_BEGIN="# >>> evolution-api (deploy/provision/lib/30-apache.sh) >>>"
EVO_APACHE_MARK_END="# <<< evolution-api <<<"

# _apache_target_root — "<MEGAISP_ROOT>/public". Los DocumentRoot se comparan contra
# esto de forma EXACTA (awk, tras quitar comillas y barra final), sin regex: la ruta
# puede traer caracteres especiales.
_apache_target_root() { printf '%s/public' "${MEGAISP_ROOT%/}"; }

# _apache_vhosts — imprime (uno por línea) los archivos reales de vhost a tocar.
# Prioridad: override APACHE_VHOST → archivos de sites-enabled con el DocumentRoot
# exacto de MegaISP. Los symlinks de sites-enabled se resuelven a su archivo real.
_apache_vhosts() {
    if [[ -n "${APACHE_VHOST:-}" ]]; then
        [[ -f "$APACHE_VHOST" ]] || die "APACHE_VHOST='${APACHE_VHOST}' no existe"
        readlink -f "$APACHE_VHOST"
        return 0
    fi
    local target f
    target="$(_apache_target_root)"
    for f in "$APACHE_SITES_DIR"/*; do
        [[ -e "$f" ]] || continue
        if _EVO_ROOT="$target" awk '
            BEGIN { t = ENVIRON["_EVO_ROOT"]; found = 0 }
            tolower($1) == "documentroot" {
                v = $2; gsub(/"/, "", v); sub(/\/+$/, "", v)
                if (v == t) found = 1
            }
            END { exit(found ? 0 : 1) }
        ' "$f"; then
            readlink -f "$f"
        fi
    done | sort -u
}

# _apache_version — "2.4.62" (vacío si no se pudo leer).
_apache_version() {
    { run_root apache2ctl -v 2>/dev/null || true; } | sed -n 's|.*Apache/\([0-9][0-9.]*\).*|\1|p' | head -n1
}

# _version_ge A B — ¿A >= B? (comparación de versiones con sort -V)
_version_ge() {
    [[ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | head -n1)" == "$2" ]]
}

_apache_mod_loaded() {
    run_root apache2ctl -M 2>/dev/null | grep -qE "^[[:space:]]*${1}_module[[:space:]]"
}

provision_apache() {
    log_step "Apache — proxy /evolution/ en el vhost de MegaISP"
    require apache2ctl "Se requiere Apache (apache2ctl) instalado"

    local -a vhosts=()
    mapfile -t vhosts < <(_apache_vhosts)
    (( ${#vhosts[@]} > 0 )) || die "No encontré en ${APACHE_SITES_DIR} un vhost con 'DocumentRoot $(_apache_target_root)'. Fija APACHE_VHOST=/ruta/al/vhost y reintenta."

    # Archivos que todavía no tienen el proxy.
    local -a pendientes=()
    local v
    for v in "${vhosts[@]}"; do
        if grep -qF "$EVO_APACHE_MARK_BEGIN" "$v" || grep -qE '^[[:space:]]*ProxyPass[[:space:]]+/evolution/' "$v"; then
            log_skip "El vhost ${v} ya tiene el proxy /evolution/"
        else
            pendientes+=("$v")
        fi
    done
    if (( ${#pendientes[@]} == 0 )); then
        return 0
    fi

    # --- Módulos según versión ------------------------------------------------
    local version; version="$(_apache_version)"
    [[ -n "$version" ]] || die "No pude leer la versión de Apache (apache2ctl -v)."
    local -a mods=(proxy proxy_http)
    local block
    if _version_ge "$version" "2.4.47"; then
        log_info "Apache ${version}: WebSocket con 'upgrade=websocket' (mod_proxy_http)"
        block="$(cat <<APACHE_BLOCK

    ${EVO_APACHE_MARK_BEGIN}
    ProxyPass        /evolution/ http://127.0.0.1:${EVOLUTION_PORT}/ upgrade=websocket timeout=300
    ProxyPassReverse /evolution/ http://127.0.0.1:${EVOLUTION_PORT}/
    ${EVO_APACHE_MARK_END}
APACHE_BLOCK
)"
    else
        log_info "Apache ${version} (< 2.4.47): WebSocket vía mod_proxy_wstunnel + mod_rewrite"
        mods+=(proxy_wstunnel rewrite)
        # \$1 y %{...} quedan literales para Apache.
        block="$(cat <<APACHE_BLOCK

    ${EVO_APACHE_MARK_BEGIN}
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteRule ^/evolution/(.*) ws://127.0.0.1:${EVOLUTION_PORT}/\$1 [P,L]
    ProxyPass        /evolution/ http://127.0.0.1:${EVOLUTION_PORT}/ timeout=300
    ProxyPassReverse /evolution/ http://127.0.0.1:${EVOLUTION_PORT}/
    ${EVO_APACHE_MARK_END}
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
            run_root a2enmod -q "$m" >/dev/null || die "a2enmod ${m} falló"
            habilitados+=("$m")
        fi
    done

    # --- Inyección con respaldo ----------------------------------------------
    local stamp; stamp="$(date +%Y%m%d%H%M%S)"
    local -a respaldos=()
    local target tmp n
    target="$(_apache_target_root)"
    for v in "${pendientes[@]}"; do
        run_root cp -p "$v" "${v}.provision.${stamp}.bak"
        respaldos+=("$v")
        tmp="$(mktemp)"
        # El bloque va justo después de CADA `DocumentRoot <MegaISP>/public` (ancla
        # confiable DENTRO de su <VirtualHost>). ENVIRON evita problemas de escape.
        _EVO_ROOT="$target" _EVO_APACHE_BLOCK="$block" awk '
            BEGIN { t = ENVIRON["_EVO_ROOT"]; b = ENVIRON["_EVO_APACHE_BLOCK"]; n = 0 }
            { print }
            tolower($1) == "documentroot" {
                v = $2; gsub(/"/, "", v); sub(/\/+$/, "", v)
                if (v == t) { print b; n++ }
            }
            END { print n > "/dev/stderr" }
        ' "$v" > "$tmp" 2> "${tmp}.n"
        n="$(cat "${tmp}.n")"
        # `cat >` conserva dueño y permisos del archivo original (no `cp`).
        run_root bash -c 'cat "$1" > "$2"' _ "$tmp" "$v"
        rm -f "$tmp" "${tmp}.n"
        log_info "Proxy /evolution/ inyectado en ${v} (${n} VirtualHost)"
    done

    # --- Validar; si rompe, revertir TODO --------------------------------------
    if run_root apache2ctl configtest >/dev/null 2>&1; then
        if run_root systemctl reload apache2 >/dev/null 2>&1 || run_root apache2ctl graceful >/dev/null 2>&1; then
            log_ok "Apache recargado con el proxy /evolution/"
        else
            log_warn "Se configuró el proxy pero no pude recargar Apache; recárgalo a mano: systemctl reload apache2"
        fi
    else
        local errores; errores="$(run_root apache2ctl configtest 2>&1 | tail -n 5 || true)"
        for v in "${respaldos[@]}"; do
            run_root cp -p "${v}.provision.${stamp}.bak" "$v"
        done
        for m in "${habilitados[@]}"; do
            run_root a2dismod -q -f "$m" >/dev/null 2>&1 || true
        done
        die "apache2ctl configtest falló tras la inyección; se restauraron los vhosts y se deshabilitaron los módulos habilitados por este paso. Apache NO se recargó. Salida: ${errores}"
    fi
}

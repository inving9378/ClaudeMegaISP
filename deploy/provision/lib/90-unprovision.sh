#!/usr/bin/env bash
# =============================================================================
# 90-unprovision.sh — pasos para quitar Evolution API (los usa uninstall.sh)
# =============================================================================
# Se hace `source` después de common.sh, 05, 20, 30-nginx y 30-apache. No ejecuta
# nada al ser sourceado. Cada paso tolera que lo que quita no exista (instalación a
# medias o ya purgada): correr uninstall.sh dos veces no rompe nada.
#
# Lo compartido NO se quita: Node, PM2, el repo de NodeSource y los módulos de
# Apache (proxy, proxy_http...) se quedan, porque otros pueden usarlos.
# =============================================================================


log_gone() { printf '%s[--]%s Nada que quitar: %s\n' "$_C_SKIP" "$_C_RESET" "$*"; }

# remove_env_var ARCHIVO KEY — quita todas las líneas KEY=... (preserva permisos).
remove_env_var() {
    local file="$1" key="$2"
    [[ -f "$file" ]] || return 0
    local tmp; tmp="$(mktemp)"
    _RM_KEY="$key" awk 'BEGIN { k = ENVIRON["_RM_KEY"] } $0 !~ "^[[:space:]]*" k "=" { print }' "$file" > "$tmp"
    cat "$tmp" > "$file"
    rm -f "$tmp"
}

# --- 1. PM2 -------------------------------------------------------------------
unprovision_pm2() {
    log_step "PM2 — detener Evolution y quitar el arranque en boot"
    local hay_usuario=0
    id "$EVOLUTION_USER" &>/dev/null && hay_usuario=1

    if (( hay_usuario )) && have pm2 && _evo pm2 describe "$EVOLUTION_PM2_NAME" >/dev/null 2>&1; then
        log_info "Borrando el proceso '${EVOLUTION_PM2_NAME}' de PM2"
        _evo pm2 delete "$EVOLUTION_PM2_NAME" >/dev/null
        # --force: guardar la lista aunque quede vacía; si no, el boot la resucitaría.
        _evo pm2 save --force >/dev/null
        log_ok "Proceso borrado y lista de PM2 guardada vacía"
    else
        log_gone "proceso '${EVOLUTION_PM2_NAME}' en PM2"
    fi

    local unit="pm2-${EVOLUTION_USER}.service"
    if systemctl list-unit-files 2>/dev/null | grep -q "^${unit}"; then
        log_info "Deshabilitando ${unit}"
        run_root systemctl disable --now "$unit" >/dev/null 2>&1 || log_warn "No pude detener ${unit}; se sigue"
        run_root rm -f "/etc/systemd/system/${unit}"
        run_root systemctl daemon-reload
        log_ok "Servicio ${unit} quitado"
    else
        log_gone "servicio ${unit}"
    fi

    if (( hay_usuario )) && have pm2; then
        _evo pm2 kill >/dev/null 2>&1 || true
    fi
}

# --- 2. Proxy /evolution/ -----------------------------------------------------
_unprovision_apache() {
    have apache2ctl || return 0
    local -a vhosts=()
    local f real b e
    for f in "$APACHE_SITES_DIR"/*; do
        [[ -e "$f" ]] || continue
        grep -qF "$EVO_APACHE_MARK_BEGIN" "$f" || continue
        real="$(readlink -f "$f")"
        b="$(grep -cF "$EVO_APACHE_MARK_BEGIN" "$real")"
        e="$(grep -cF "$EVO_APACHE_MARK_END" "$real")"
        [[ "$b" == "$e" ]] || die "En ${real} los marcadores del bloque /evolution/ no cuadran (${b} de inicio, ${e} de fin). Quítalo a mano."
        [[ " ${vhosts[*]} " == *" ${real} "* ]] || vhosts+=("$real")
    done
    if (( ${#vhosts[@]} == 0 )); then
        log_gone "bloque /evolution/ en Apache"
        return 0
    fi

    local stamp v tmp; stamp="$(date +%Y%m%d%H%M%S)"
    for v in "${vhosts[@]}"; do
        run_root cp -p "$v" "${v}.purge.${stamp}.bak"
        tmp="$(mktemp)"
        # El instalador mete el bloque con una línea en blanco delante (justo después
        # del DocumentRoot): esa línea también es suya y se quita con el bloque.
        _EVO_B="$EVO_APACHE_MARK_BEGIN" _EVO_E="$EVO_APACHE_MARK_END" awk '
            BEGIN { b = ENVIRON["_EVO_B"]; e = ENVIRON["_EVO_E"]; skip = 0; held = 0 }
            { t = $0; sub(/^[[:space:]]+/, "", t); sub(/[[:space:]]+$/, "", t) }
            !skip && t == b { skip = 1; held = 0; next }
            skip { if (t == e) skip = 0; next }
            t == "" { if (held) print hl; held = 1; hl = $0; next }
            { if (held) { print hl; held = 0 } print }
            END { if (held) print hl }
        ' "$v" > "$tmp"
        run_root bash -c 'cat "$1" > "$2"' _ "$tmp" "$v"
        rm -f "$tmp"
        log_info "Bloque /evolution/ quitado de ${v} (respaldo ${v}.purge.${stamp}.bak)"
        if grep -qE '^[[:space:]]*ProxyPass[[:space:]]+/evolution/' "$v"; then
            log_warn "${v} todavía tiene un ProxyPass /evolution/ que no puso el instalador; se deja."
        fi
    done

    if run_root apache2ctl configtest >/dev/null 2>&1; then
        if run_root systemctl reload apache2 >/dev/null 2>&1 || run_root apache2ctl graceful >/dev/null 2>&1; then
            log_ok "Apache recargado sin el proxy /evolution/"
        else
            log_warn "Se quitó el proxy pero no pude recargar Apache; recárgalo a mano: systemctl reload apache2"
        fi
    else
        local errores; errores="$(run_root apache2ctl configtest 2>&1 | tail -n 5 || true)"
        for v in "${vhosts[@]}"; do
            run_root cp -p "${v}.purge.${stamp}.bak" "$v"
        done
        die "apache2ctl configtest falló al quitar el bloque; se restauraron los vhosts y Apache NO se recargó. Salida: ${errores}"
    fi
}

_unprovision_nginx() {
    have nginx || return 0
    local vhost
    vhost="$(_detect_vhost 2>/dev/null)" || { log_gone "vhost de nginx de MegaISP"; return 0; }
    if ! grep -qE 'location[[:space:]]+/evolution/' "$vhost"; then
        log_gone "location /evolution/ en nginx"
        return 0
    fi
    local stamp tmp; stamp="$(date +%Y%m%d%H%M%S)"
    run_root cp -p "$vhost" "${vhost}.purge.${stamp}.bak"
    tmp="$(mktemp)"
    # Igual que en Apache: la línea en blanco justo antes del location es del instalador.
    awk '
        BEGIN { skip = 0; held = 0 }
        { t = $0; gsub(/[[:space:]]/, "", t) }
        !skip && $0 ~ /^[[:space:]]*location[[:space:]]+\/evolution\/[[:space:]]*\{/ { skip = 1; held = 0; next }
        skip { if (t == "}") skip = 0; next }
        t == "" { if (held) print hl; held = 1; hl = $0; next }
        { if (held) { print hl; held = 0 } print }
        END { if (held) print hl }
    ' "$vhost" > "$tmp"
    run_root bash -c 'cat "$1" > "$2"' _ "$tmp" "$vhost"
    rm -f "$tmp"
    log_info "location /evolution/ quitado de ${vhost}"
    if run_root nginx -t >/dev/null 2>&1; then
        if run_root systemctl reload nginx >/dev/null 2>&1 || run_root nginx -s reload >/dev/null 2>&1; then
            log_ok "nginx recargado sin el proxy /evolution/"
        else
            log_warn "Se quitó el bloque pero no pude recargar nginx; recarga manualmente."
        fi
    else
        run_root cp -p "${vhost}.purge.${stamp}.bak" "$vhost"
        die "nginx -t falló al quitar el bloque; se restauró el vhost. Revisar a mano."
    fi
}

unprovision_web_proxy() {
    log_step "Proxy /evolution/ del servidor web"
    _unprovision_apache
    _unprovision_nginx
}

# --- 3. Código ----------------------------------------------------------------
unprovision_code() {
    log_step "Código de Evolution (${EVOLUTION_DIR})"
    if [[ ! -e "$EVOLUTION_DIR" ]]; then
        log_gone "$EVOLUTION_DIR"
        return 0
    fi
    [[ "$EVOLUTION_DIR" == "/opt/evolution-api" ]] || die "EVOLUTION_DIR inesperado (${EVOLUTION_DIR}); no se borra."
    run_root rm -rf -- "$EVOLUTION_DIR"
    log_ok "Borrado ${EVOLUTION_DIR}"
}

# --- 4. Base de datos ---------------------------------------------------------
unprovision_db() {
    log_step "Base de datos '${EVO_DB_NAME}' y usuario MySQL '${EVO_DB_USER}'"
    if ! have mysql; then
        log_warn "No hay cliente mysql en este server; se omite la base de datos."
        return 0
    fi
    local exists
    exists="$(run_root mysql -N -B -e \
        "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${EVO_DB_NAME}'" 2>/dev/null || true)"
    if [[ "$exists" == "$EVO_DB_NAME" ]]; then
        log_info "Respaldando ${EVO_DB_NAME} en ${EVOLUTION_BACKUP_DIR}"
        local dump; dump="$(respaldar_bd_evolution)"
        run_root mysql -e "DROP DATABASE IF EXISTS \`${EVO_DB_NAME}\`"
        log_ok "Base '${EVO_DB_NAME}' borrada (respaldo: ${dump})"
    else
        log_gone "base de datos '${EVO_DB_NAME}'"
    fi
    run_root mysql <<SQL
DROP USER IF EXISTS '${EVO_DB_USER}'@'127.0.0.1';
DROP USER IF EXISTS '${EVO_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    log_ok "Usuario MySQL '${EVO_DB_USER}' quitado (si existía)"
}

# --- 5. .env de MegaISP -------------------------------------------------------
unprovision_megaisp_env() {
    log_step ".env de MegaISP (WHATSAPP_API_KEY / WHATSAPP_API_URL)"
    [[ -f "$MEGAISP_ENV" ]] || { log_gone "$MEGAISP_ENV"; return 0; }
    local url local_url="http://127.0.0.1:${EVOLUTION_PORT}" cambios=0
    url="$(get_env_value WHATSAPP_API_URL)"

    if grep -qE '^[[:space:]]*WHATSAPP_API_KEY=' "$MEGAISP_ENV" || [[ "$url" == "$local_url" ]]; then
        local bak; bak="${MEGAISP_ENV}.purge.$(date +%Y%m%d%H%M%S).bak"
        run_root cp -p "$MEGAISP_ENV" "$bak"
        log_info "Respaldo del .env en ${bak}"
    fi
    if grep -qE '^[[:space:]]*WHATSAPP_API_KEY=' "$MEGAISP_ENV"; then
        remove_env_var "$MEGAISP_ENV" WHATSAPP_API_KEY
        log_ok "WHATSAPP_API_KEY quitada"
        cambios=1
    else
        log_gone "WHATSAPP_API_KEY"
    fi
    if [[ "$url" == "$local_url" ]]; then
        remove_env_var "$MEGAISP_ENV" WHATSAPP_API_URL
        log_ok "WHATSAPP_API_URL quitada (era la de esta Evolution local)"
        cambios=1
    elif [[ -n "$url" ]]; then
        log_info "WHATSAPP_API_URL=${url} no la puso el instalador; se deja."
    fi

    if (( cambios )); then
        log_info "php artisan config:clear + queue:restart (usuario ${MEGAISP_USER})"
        run_as "$MEGAISP_USER" bash -c "cd '${MEGAISP_ROOT}' && php artisan config:clear && php artisan queue:restart"
        log_ok "Config de MegaISP refrescada"
    fi
}

# --- 6. Usuario de sistema ----------------------------------------------------
unprovision_evolution_user() {
    log_step "Usuario de sistema '${EVOLUTION_USER}'"
    if [[ "$EVOLUTION_USER" != "evolution" ]]; then
        log_info "EVOLUTION_USER='${EVOLUTION_USER}' no es el usuario dedicado; no se borra."
        return 0
    fi
    if ! id "$EVOLUTION_USER" &>/dev/null; then
        log_gone "usuario '${EVOLUTION_USER}'"
        return 0
    fi
    if pgrep -u "$EVOLUTION_USER" >/dev/null 2>&1; then
        run_root pkill -u "$EVOLUTION_USER" >/dev/null 2>&1 || true
        sleep 2
    fi
    run_root userdel -r "$EVOLUTION_USER" >/dev/null 2>&1 \
        || run_root userdel "$EVOLUTION_USER" \
        || die "No pude borrar el usuario '${EVOLUTION_USER}'"
    log_ok "Usuario '${EVOLUTION_USER}' y su carpeta borrados"
}

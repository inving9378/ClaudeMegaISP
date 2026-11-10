#!/usr/bin/env bash
# =============================================================================
# 90-desinstalar.sh — pasos de uninstall.sh (lo inverso de install.sh)
# =============================================================================
# Se hace `source` después de common.sh y 40-proxy-ws.sh. No ejecuta nada al ser
# sourceado. Cada paso tolera que lo que quita no exista (instalación a medias o
# ya purgada): correr uninstall.sh dos veces no rompe nada.
#
# LAS GRABACIONES NO SE BORRAN NI SE MUEVEN: se quedan en su carpeta. Como el
# usuario y el grupo `asterisk` desaparecen, pasan a root:<grupo web> para que
# MegaISP las siga pudiendo leer.
#
# Lo compartido NO se quita: los paquetes de apt que instaló la compilación,
# unixODBC y su driver, los módulos de Apache y los logs de provisión de
# /var/log/megaisp (son el registro de lo que pasó).
# =============================================================================

# --- 1. Respaldo de la base realtime (antes de tocar nada) -------------------
desinstalar_respaldo_bd() {
    log_step "Respaldo de la base realtime (llamadas registradas, extensiones publicadas)"
    local db; db="$(voip_db_name)"
    if ! have mysql; then
        log_warn "No hay cliente mysql en este server; no hay base que respaldar."
        return 0
    fi
    if ! mysql_existe_bd "$db"; then
        log_gone "base de datos '${db}'"
        return 0
    fi
    local dump; dump="$(respaldar_bd_realtime antes-de-purgar)"
    log_ok "Respaldo: ${dump} ($(du -h "$dump" | cut -f1))"
}

# --- 3. Grabaciones: se quedan; solo cambia el dueño -------------------------
desinstalar_grabaciones() {
    log_step "Grabaciones — se quedan donde están"
    local g grupo n
    g="$(voip_grabaciones_dir)"
    if [[ ! -d "$g" ]]; then
        log_gone "carpeta de grabaciones ${g}"
        return 0
    fi
    grupo="$(megaisp_group)"
    n="$(find "$g" -type f | wc -l)"
    chown -R "root:${grupo}" "$g"
    chmod 2770 "$g"
    chmod -R g+rX "$g"
    log_ok "${n} grabación(es) siguen en ${g}; dueño root:${grupo} (MegaISP las sigue leyendo sin el usuario asterisk)"
}

# --- 4. Asterisk (servicio, binarios, config, datos, base, DSN, usuario) ------
desinstalar_asterisk() {
    log_step "Asterisk — servicio, binarios, configuración, base realtime y usuario de sistema"
    local db hay=0
    db="$(voip_db_name)"
    [[ -e "$ASTERISK_BIN" || -d /etc/asterisk || -d /var/lib/asterisk || -e /etc/systemd/system/asterisk.service ]] && hay=1
    getent passwd asterisk >/dev/null 2>&1 && hay=1
    have mysql && mysql_existe_bd "$db" && hay=1
    if (( hay == 0 )); then
        log_gone "Asterisk (no hay binario, configuración, servicio, usuario ni base '${db}')"
        return 0
    fi
    # La receta de qué deja Asterisk en el servidor vive en un solo lugar: el
    # desinstalador del provisionador.
    ASTERISK_ODBC_DSN="$(get_env_value ASTERISK_ODBC_DSN)" \
        bash "$VOIP_DESINSTALAR_ASTERISK_SH" \
            --confirmar=SI-BORRAR-ASTERISK --base="$db" --desde-addon \
        || die "desinstalar-asterisk.sh falló (salida arriba). La base ya estaba respaldada en ${VOIP_BACKUP_DIR}; reintentar es seguro."
    log_ok "Asterisk quitado del servidor"
}

# --- 5. Usuario MySQL realtime ------------------------------------------------
desinstalar_usuario_mysql() {
    log_step "Usuario MySQL de la base realtime"
    have mysql || { log_warn "No hay cliente mysql en este server; se omite."; return 0; }
    local user; user="$(voip_db_user)"
    es_identificador "$user" || die "ASTERISK_RT_DB_USERNAME='${user}' no es un nombre válido; no se toca MySQL."
    if ! mysql_existe_usuario "$user"; then
        log_gone "usuario MySQL '${user}'"
        return 0
    fi
    mysql <<SQL || die "MySQL no aceptó quitar el usuario '${user}' (salida arriba)."
DROP USER IF EXISTS '${user}'@'127.0.0.1';
DROP USER IF EXISTS '${user}'@'localhost';
FLUSH PRIVILEGES;
SQL
    log_ok "Usuario MySQL '${user}' quitado"
}

# --- 6. Carpeta de trabajo de la compilación ---------------------------------
desinstalar_trabajo() {
    log_step "Carpeta de trabajo de la compilación (${ASTERISK_TRABAJO_DIR})"
    if [[ ! -e "$ASTERISK_TRABAJO_DIR" ]]; then
        log_gone "$ASTERISK_TRABAJO_DIR"
        return 0
    fi
    rm -rf -- "$ASTERISK_TRABAJO_DIR"
    log_ok "Borrada ${ASTERISK_TRABAJO_DIR} (tarballs descargados)"
}

# --- 7. Secretos de VoIP en el .env de MegaISP -------------------------------
desinstalar_env() {
    log_step ".env de MegaISP — credenciales de la central"
    [[ -f "$MEGAISP_ENV" ]] || { log_gone "$MEGAISP_ENV"; return 0; }
    # Solo los secretos: con la central quitada ya no sirven, y al reinstalar se
    # generan nuevos. Host, puerto, nombres y rutas se dejan (no son secretos y
    # pueden estar personalizados).
    local -a claves=(ASTERISK_RT_DB_PASSWORD ASTERISK_ARI_PASS AMI_SECRET)
    local -a presentes=()
    local k
    for k in "${claves[@]}"; do
        grep -qE "^[[:space:]]*${k}=" "$MEGAISP_ENV" && presentes+=("$k")
    done
    if (( ${#presentes[@]} == 0 )); then
        log_gone "${claves[*]}"
        return 0
    fi
    local bak; bak="$(respaldar_env purge-voip)"
    log_info "Respaldo del .env en ${bak}"
    for k in "${presentes[@]}"; do
        remove_env_var "$MEGAISP_ENV" "$k"
        log_ok "${k} quitada"
    done
    log_info "php artisan config:clear + queue:restart (usuario ${MEGAISP_USER})"
    run_as "$MEGAISP_USER" bash -c "cd '${MEGAISP_ROOT}' && php artisan config:clear && php artisan queue:restart" \
        || log_warn "No pude refrescar la config de MegaISP; córrelo a mano: php artisan config:clear && php artisan queue:restart"
}

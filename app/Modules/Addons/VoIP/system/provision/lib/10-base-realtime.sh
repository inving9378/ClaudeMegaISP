#!/usr/bin/env bash
# =============================================================================
# 10-base-realtime.sh — Base de datos realtime de Asterisk y su usuario MySQL
# =============================================================================
# Se hace `source` desde install.sh (que ya cargó common.sh). Define
# provision_base_realtime. No ejecuta nada al ser sourceado.
#
# El provisionador (voip:provisionar) da por hecho que la conexión `asterisk_rt`
# ya sirve: en dev la base y el usuario se crearon a mano. Este paso los crea en
# el MySQL local y deja las variables ASTERISK_RT_DB_* en el .env de MegaISP.
#
# Guardas (las mismas de Evolution, caso #201):
#   - Base y usuario: CREATE ... IF NOT EXISTS (no rota el password de un usuario
#     que ya existe).
#   - Si el .env no trae password, se genera uno; si el usuario MySQL YA existía
#     (con otro password) se ABORTA sin tocar nada, salvo
#     VOIP_FORCE_ROTATE_DB_PASSWORD=1 (rota el de MySQL a juego con el .env).
#   - Si el .env trae password y el usuario existe, se prueba que entre; si no
#     entra, mismo aborto (mismo override).
#   - Si al final no se puede escribir el .env, se quita lo que este paso creó.
# =============================================================================

# _rt_login_ok PASSWORD — ¿entra el usuario realtime con ese password? Credenciales
# por archivo 600 (nunca en argv, donde las vería un `ps`).
_rt_login_ok() {
    local pw="$1" cnf rc=0
    cnf="$(mktemp)"; chmod 600 "$cnf"
    printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' \
        "$(voip_db_user)" "$pw" "$(voip_db_host)" "$(voip_db_port)" > "$cnf"
    mysql --defaults-extra-file="$cnf" -N -B -e 'SELECT 1' >/dev/null 2>&1 || rc=$?
    rm -f "$cnf"
    return "$rc"
}

_rt_rotar_password() {
    local user="$1" pw="$2"
    mysql <<SQL
ALTER USER IF EXISTS '${user}'@'127.0.0.1' IDENTIFIED BY '${pw}';
ALTER USER IF EXISTS '${user}'@'localhost' IDENTIFIED BY '${pw}';
FLUSH PRIVILEGES;
SQL
}

provision_base_realtime() {
    log_step "Base realtime de Asterisk (MySQL local)"
    require mysql "Se requiere el cliente 'mysql' (no se instala MySQL aquí)"

    local host port db user pw fresh=0 user_existia=0 bd_existia=0
    host="$(voip_db_host)"; port="$(voip_db_port)"
    db="$(voip_db_name)";   user="$(voip_db_user)"

    es_identificador "$db"   || die "ASTERISK_RT_DB_DATABASE='${db}' no es un nombre válido (solo letras, números y _)."
    es_identificador "$user" || die "ASTERISK_RT_DB_USERNAME='${user}' no es un nombre válido (solo letras, números y _)."
    case "$host" in
        127.0.0.1|localhost) ;;
        *) die "ASTERISK_RT_DB_HOST='${host}': este instalador crea la base realtime solo en el MySQL de este servidor. Déjalo en 127.0.0.1 o crea la base y el usuario a mano en ese host." ;;
    esac
    mysql -N -B -e 'SELECT 1' >/dev/null 2>&1 \
        || die "No puedo entrar a MySQL como root por socket. Revisa que MySQL esté corriendo y que root entre sin contraseña desde este servidor."

    pw="$(get_env_value ASTERISK_RT_DB_PASSWORD)"
    if [[ -z "$pw" ]]; then
        pw="$(gen_secret)"; fresh=1
        log_info "ASTERISK_RT_DB_PASSWORD vacío en el .env → se genera uno nuevo (no se muestra)"
    fi
    mysql_existe_usuario "$user" && user_existia=1
    mysql_existe_bd "$db" && bd_existia=1

    # Desajuste .env↔MySQL: se detecta ANTES de crear nada.
    if (( user_existia )); then
        local motivo=""
        if (( fresh )); then
            motivo="el .env no tenía password (se generó uno nuevo) pero el usuario MySQL '${user}' YA existía con otro"
        elif ! _rt_login_ok "$pw"; then
            motivo="el usuario MySQL '${user}' existe pero no entra con el ASTERISK_RT_DB_PASSWORD del .env"
        fi
        if [[ -n "$motivo" ]]; then
            if [[ "${VOIP_FORCE_ROTATE_DB_PASSWORD:-0}" == "1" ]]; then
                log_warn "Desajuste: ${motivo}. VOIP_FORCE_ROTATE_DB_PASSWORD=1 → se rota el de MySQL a juego con el .env."
                _rt_rotar_password "$user" "$pw" || die "MySQL no aceptó rotar el password de '${user}' (salida arriba); no se cambió nada más."
                log_ok "Password de '${user}' rotado"
            else
                die "Desajuste .env↔MySQL: ${motivo}. No se tocó nada. Opciones: (a) poner en ASTERISK_RT_DB_PASSWORD el password correcto; (b) reintentar con VOIP_FORCE_ROTATE_DB_PASSWORD=1 para rotar el de MySQL a juego con el .env."
            fi
        else
            log_skip "Usuario MySQL '${user}' (entra con el password del .env)"
        fi
    fi

    if (( bd_existia )); then
        log_skip "Base de datos '${db}'"
    else
        log_info "Creando base de datos '${db}'"
    fi
    (( user_existia )) || log_info "Creando usuario MySQL '${user}'"

    # Password hex (sin comillas ni barras) → seguro en el SQL. Por stdin, nunca argv.
    mysql <<SQL || die "MySQL rechazó la creación de la base o del usuario realtime (salida arriba)."
CREATE DATABASE IF NOT EXISTS \`${db}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${user}'@'127.0.0.1' IDENTIFIED BY '${pw}';
CREATE USER IF NOT EXISTS '${user}'@'localhost' IDENTIFIED BY '${pw}';
GRANT ALL PRIVILEGES ON \`${db}\`.* TO '${user}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${db}\`.* TO '${user}'@'localhost';
FLUSH PRIVILEGES;
SQL

    if ! _rt_login_ok "$pw"; then
        _rt_deshacer "$db" "$user" "$bd_existia" "$user_existia"
        die "El usuario '${user}' quedó creado pero no logra entrar a MySQL por ${host}:${port}; se deshizo lo creado en este paso."
    fi

    # Variables en el .env: solo las que faltan (y el password si se generó).
    local -a cambios=()
    [[ -n "$(get_env_value ASTERISK_RT_DB_HOST)" ]]     || cambios+=("ASTERISK_RT_DB_HOST=${host}")
    [[ -n "$(get_env_value ASTERISK_RT_DB_PORT)" ]]     || cambios+=("ASTERISK_RT_DB_PORT=${port}")
    [[ -n "$(get_env_value ASTERISK_RT_DB_DATABASE)" ]] || cambios+=("ASTERISK_RT_DB_DATABASE=${db}")
    [[ -n "$(get_env_value ASTERISK_RT_DB_USERNAME)" ]] || cambios+=("ASTERISK_RT_DB_USERNAME=${user}")
    (( fresh )) && cambios+=("ASTERISK_RT_DB_PASSWORD=${pw}")

    if (( ${#cambios[@]} == 0 )); then
        log_skip "Variables ASTERISK_RT_DB_* del .env"
    else
        local bak par
        bak="$(respaldar_env provision-voip)"
        log_info "Respaldo del .env en ${bak}"
        for par in "${cambios[@]}"; do
            if ! ( upsert_env_var "$MEGAISP_ENV" "${par%%=*}" "${par#*=}" ); then
                cat "$bak" > "$MEGAISP_ENV"
                _rt_deshacer "$db" "$user" "$bd_existia" "$user_existia"
                die "No pude escribir ${par%%=*} en ${MEGAISP_ENV}; se restauró el .env y se deshizo lo creado en este paso."
            fi
            log_ok "${par%%=*} escrita en el .env"
        done
    fi
    log_ok "Base realtime '${db}' y usuario '${user}' listos"
}

# _rt_deshacer BD USUARIO BD_EXISTIA USUARIO_EXISTIA — quita solo lo que creó este
# paso. La base solo si quedó vacía (recién creada no tiene tablas).
_rt_deshacer() {
    local db="$1" user="$2" bd_existia="$3" user_existia="$4" tablas
    if [[ "$user_existia" == "0" ]]; then
        mysql -e "DROP USER IF EXISTS '${user}'@'127.0.0.1'; DROP USER IF EXISTS '${user}'@'localhost'; FLUSH PRIVILEGES;" || true
        log_info "Rollback: usuario MySQL '${user}' quitado"
    fi
    if [[ "$bd_existia" == "0" ]]; then
        tablas="$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${db}'" 2>/dev/null || echo 1)"
        if [[ "$tablas" == "0" ]]; then
            mysql -e "DROP DATABASE IF EXISTS \`${db}\`" || true
            log_info "Rollback: base '${db}' (vacía) quitada"
        fi
    fi
}

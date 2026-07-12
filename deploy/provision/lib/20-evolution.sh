#!/usr/bin/env bash
# =============================================================================
# 20-evolution.sh — Código, .env, base de datos y Prisma de Evolution API
# =============================================================================
# Se hace `source` desde install.sh (que ya cargó common.sh). Define
# provision_evolution y sus ayudantes internos. No ejecuta nada al ser sourceado.
#
# Deja Evolution instalada en $EVOLUTION_DIR, compilada (dist/main.js), con su
# .env renderizado (preservado si ya existía), la BD MySQL creada y el esquema
# Prisma aplicado. NO arranca PM2 (eso es 40-pm2-boot.sh).
#
# Guardas de idempotencia (decisión 2):
#   - Código: si ya está en el commit $EVOLUTION_COMMIT → no re-clona/re-checkout.
#   - Build:  si falta dist/main.js o cambió el commit → npm ci + build; si no → OMITIR.
#   - .env:   si ya existe → NO se sobrescribe (preserva la key/password generados).
#   - DB:     CREATE ... IF NOT EXISTS (no rota el password de un usuario existente).
#             Caso borde (#201): si el .env se borró/regeneró (password NUEVO) pero el
#             usuario MySQL YA existía (password VIEJO) → desajuste .env↔MySQL. Por
#             default el script ABORTA con mensaje claro (no rota nada solo). Para
#             re-sincronizar a propósito: EVOLUTION_FORCE_ROTATE_DB_PASSWORD=1.
#   - migrate deploy: siempre corre (Prisma lo hace idempotente).
#
# Efectos exportados para el resumen final de install.sh:
#   PROVISIONED_API_KEY, PROVISIONED_SERVER_URL
# =============================================================================

# Usuario que será dueño del código y bajo el que correrá Evolution.
# Por defecto, el usuario de SISTEMA dedicado 'evolution' (lo crea
# 05-evolution-user.sh). Override con EVOLUTION_USER=... si se quiere otro.
: "${EVOLUTION_USER:=evolution}"

# Bandera interna: ¿hay que compilar? (la fija _ensure_evolution_code)
EVO_BUILD_NEEDED=0

# _evo CMD... — ejecuta un comando como $EVOLUTION_USER (dueño del código).
# Si ya somos ese usuario, directo; si somos root, `sudo -u`; si no, `sudo -n -u`.
_evo() {
    local u="$EVOLUTION_USER"
    if [[ "$(id -un)" == "$u" ]]; then
        "$@"
    elif is_root; then
        sudo -u "$u" -H "$@"
    elif have sudo; then
        sudo -n -u "$u" -H "$@"
    else
        die "No puedo ejecutar como '$u' (falta sudo): $*"
    fi
}

# --- 1) Código en el commit exacto -------------------------------------------
_ensure_evolution_code() {
    if [[ -d "$EVOLUTION_DIR/.git" ]]; then
        local head
        head="$(git -C "$EVOLUTION_DIR" rev-parse --short HEAD 2>/dev/null || echo '')"
        if [[ "$head" == "$EVOLUTION_COMMIT" ]]; then
            log_skip "Evolution ya está en el commit ${EVOLUTION_COMMIT}"
            # aunque el commit sea el correcto, puede faltar el build compilado
            if [[ -f "$EVOLUTION_DIR/dist/main.js" ]]; then
                log_skip "Build presente (dist/main.js)"
            else
                log_info "Falta dist/main.js → se recompilará"
                EVO_BUILD_NEEDED=1
            fi
        else
            log_info "Evolution en commit '${head:-desconocido}'; cambiando a ${EVOLUTION_COMMIT}"
            _evo git -C "$EVOLUTION_DIR" fetch --all --tags --quiet
            _evo git -C "$EVOLUTION_DIR" checkout --quiet "$EVOLUTION_COMMIT"
            EVO_BUILD_NEEDED=1
        fi
    else
        log_info "Clonando Evolution en ${EVOLUTION_DIR}"
        require git "Se requiere 'git' para clonar Evolution"
        # /opt requiere root para crear el directorio; luego se cede al usuario.
        run_root mkdir -p "$EVOLUTION_DIR"
        run_root chown "$EVOLUTION_USER":"$EVOLUTION_USER" "$EVOLUTION_DIR"
        _evo git clone --quiet "$EVOLUTION_REPO" "$EVOLUTION_DIR"
        _evo git -C "$EVOLUTION_DIR" checkout --quiet "$EVOLUTION_COMMIT"
        EVO_BUILD_NEEDED=1
    fi
}

# --- 2) Secretos + .env (preserva si existe) ---------------------------------
# Define/exporta API_KEY, DB_PASSWORD, SERVER_URL para los pasos siguientes.
# También fija EVO_ENV_FRESH=1 cuando el DB_PASSWORD es NUEVO (se acaba de generar),
# para que _ensure_evolution_db pueda detectar el caso borde #201 (usuario MySQL
# preexistente con el password VIEJO).
EVO_ENV_FRESH=0
_ensure_evolution_env() {
    local envf="$EVOLUTION_DIR/.env"
    if [[ -f "$envf" ]]; then
        log_skip ".env de Evolution ya existe (se preservan key y password)"
        API_KEY="$(get_env_value AUTHENTICATION_API_KEY "$envf")"
        DB_PASSWORD="$(get_env_value DATABASE_PASSWORD "$envf")"
        SERVER_URL="$(get_env_value SERVER_URL "$envf")"
        [[ -n "$API_KEY" ]]     || die ".env existe pero AUTHENTICATION_API_KEY está vacío; revisar a mano"
        [[ -n "$DB_PASSWORD" ]] || die ".env existe pero DATABASE_PASSWORD está vacío; revisar a mano"
    else
        log_info "Generando secretos y renderizando .env de Evolution"
        API_KEY="$(gen_secret)"          # key hex de 64 chars
        DB_PASSWORD="$(gen_secret)"       # password de DB hex (seguro dentro de la URI)
        SERVER_URL="$(derive_server_url)" # APP_URL del sistema + /evolution
        EVO_ENV_FRESH=1
        # render (render_template lee API_KEY/DB_PASSWORD/SERVER_URL por scope dinámico)
        local tmp; tmp="$(mktemp)"
        render_template "${TEMPLATES_DIR}/evolution.env.tpl" > "$tmp"
        run_root cp "$tmp" "$envf"
        rm -f "$tmp"
        run_root chown "$EVOLUTION_USER":"$EVOLUTION_USER" "$envf"
        run_root chmod 600 "$envf"
        log_ok ".env de Evolution creado (permisos 600, propietario ${EVOLUTION_USER})"
    fi
    export API_KEY DB_PASSWORD SERVER_URL
}

# --- 3) Sincronizar la key en el .env del sistema MegaISP --------------------
_sync_megaisp_key() {
    local current wa_url
    current="$(get_env_value WHATSAPP_API_KEY "$MEGAISP_ENV")"
    if [[ "$current" == "$API_KEY" ]]; then
        log_skip "WHATSAPP_API_KEY del sistema ya coincide con la de Evolution"
    else
        log_info "Sincronizando WHATSAPP_API_KEY en ${MEGAISP_ENV}"
        # respaldo del .env del sistema la primera vez que lo tocamos
        run_root cp -n "$MEGAISP_ENV" "${MEGAISP_ENV}.provision.bak" 2>/dev/null || true
        upsert_env_var "$MEGAISP_ENV" WHATSAPP_API_KEY "$API_KEY"
        log_ok "WHATSAPP_API_KEY sincronizada"
    fi
    # El panel de MegaISP habla con el Evolution LOCAL directo (no vía nginx).
    # Solo se fija si está vacío, para no pisar un valor personalizado.
    wa_url="$(get_env_value WHATSAPP_API_URL "$MEGAISP_ENV")"
    if [[ -z "$wa_url" ]]; then
        log_info "WHATSAPP_API_URL vacío → fijando http://127.0.0.1:${EVOLUTION_PORT}"
        upsert_env_var "$MEGAISP_ENV" WHATSAPP_API_URL "http://127.0.0.1:${EVOLUTION_PORT}"
    else
        log_skip "WHATSAPP_API_URL ya definido (${wa_url})"
    fi
}

# --- 4) Base de datos + usuario MySQL ----------------------------------------
_ensure_evolution_db() {
    require mysql "Se requiere el cliente 'mysql' (no se instala MySQL aquí)"
    local exists
    exists="$(run_root mysql -N -B -e \
        "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${EVO_DB_NAME}'" 2>/dev/null || true)"
    if [[ "$exists" == "$EVO_DB_NAME" ]]; then
        log_skip "Base de datos '${EVO_DB_NAME}' ya existe"
    else
        log_info "Creando base de datos '${EVO_DB_NAME}' y usuario '${EVO_DB_USER}'"
    fi

    # Caso borde #201: si el .env se acaba de generar (password NUEVO) pero el
    # usuario MySQL YA existía (password VIEJO), `CREATE USER IF NOT EXISTS` es
    # un no-op → el .env queda con un password que MySQL no reconoce. Se detecta
    # ANTES de tocar nada y, por default, se aborta con instrucciones claras en
    # vez de rotar el password en silencio.
    if [[ "$EVO_ENV_FRESH" -eq 1 ]]; then
        local user_exists
        user_exists="$(run_root mysql -N -B -e \
            "SELECT User FROM mysql.user WHERE User='${EVO_DB_USER}' AND Host IN ('127.0.0.1','localhost') LIMIT 1" 2>/dev/null || true)"
        if [[ "$user_exists" == "$EVO_DB_USER" ]]; then
            if [[ "${EVOLUTION_FORCE_ROTATE_DB_PASSWORD:-0}" == "1" ]]; then
                log_warn "Usuario MySQL '${EVO_DB_USER}' ya existía con password VIEJO; EVOLUTION_FORCE_ROTATE_DB_PASSWORD=1 → rotando a juego con el .env nuevo"
                run_root mysql <<SQL
ALTER USER '${EVO_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${EVO_DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
FLUSH PRIVILEGES;
SQL
                log_ok "Password de '${EVO_DB_USER}' rotado para coincidir con el .env"
            else
                die "Desajuste .env↔MySQL: se generó un DB_PASSWORD nuevo en ${EVOLUTION_DIR}/.env pero el usuario MySQL '${EVO_DB_USER}' YA existía (con su password viejo) — 'CREATE USER IF NOT EXISTS' no lo actualiza. Evolution NO podrá conectar a la BD así. Opciones: (a) restaura el .env/password viejo si lo tienes; (b) re-corre con EVOLUTION_FORCE_ROTATE_DB_PASSWORD=1 para rotar el password de MySQL a juego con el .env nuevo."
            fi
        fi
    fi

    # Idempotente: IF NOT EXISTS no rota el password de un usuario ya creado.
    # El password es hex (sin comillas ni backslashes) → seguro en el SQL.
    # Se pasa por stdin (heredoc), nunca por argv → no aparece en `ps`.
    run_root mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${EVO_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${EVO_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${EVO_DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${EVO_DB_NAME}\`.* TO '${EVO_DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${EVO_DB_NAME}\`.* TO '${EVO_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    log_ok "Base de datos y usuario asegurados"
}

# --- 5) Dependencias + generate + build (solo si hace falta) ------------------
_ensure_build() {
    if [[ "$EVO_BUILD_NEEDED" -ne 1 ]]; then
        log_skip "No hay que compilar (código y build al día)"
        return 0
    fi
    log_info "npm ci (instalando dependencias — puede tardar)"
    _evo bash -c "cd '${EVOLUTION_DIR}' && npm ci"
    log_info "prisma generate (cliente Prisma, provider mysql)"
    _evo bash -c "cd '${EVOLUTION_DIR}' && npx prisma generate --schema prisma/mysql-schema.prisma"
    log_info "npm run build (compilando a dist/)"
    _evo bash -c "cd '${EVOLUTION_DIR}' && npm run build"
    [[ -f "$EVOLUTION_DIR/dist/main.js" ]] || die "El build no generó dist/main.js"
    log_ok "Build completo (dist/main.js)"
}

# --- 6) Aplicar esquema Prisma (siempre, es idempotente) ---------------------
_ensure_prisma_deploy() {
    # `prisma/migrations/` viene en el .gitignore de Evolution (línea
    # "/prisma/migrations/*") → en un checkout nuevo (clon fresco) llega VACÍA.
    # El propio script `db:deploy` de Evolution (package.json, vía runWithProvider.js)
    # copia primero prisma/mysql-migrations → prisma/migrations y RECIÉN DESPUÉS corre
    # `prisma migrate deploy` (que siempre resuelve migraciones contra prisma/migrations/,
    # sin importar qué --schema se le pase). Sin este paso, `migrate deploy` no encuentra
    # migraciones que aplicar y el esquema de Evolution nunca se crea en un server nuevo
    # (item Hoja de Ruta #200). Replicado aquí explícito para no depender de scripts npm
    # ajenos; idempotente (mismo resultado si ya existía o se re-corre).
    log_info "Sincronizando prisma/mysql-migrations → prisma/migrations (mismo paso que el db:deploy de Evolution)"
    _evo bash -c "cd '${EVOLUTION_DIR}' && rm -rf prisma/migrations && cp -r prisma/mysql-migrations prisma/migrations"

    log_info "prisma migrate deploy (aplicando esquema a MySQL)"
    # Prisma carga el .env del directorio del proyecto automáticamente
    # (DATABASE_PROVIDER y DATABASE_CONNECTION_URI) → sin secretos en argv.
    _evo bash -c "cd '${EVOLUTION_DIR}' && npx prisma migrate deploy --schema prisma/mysql-schema.prisma"
    log_ok "Esquema Prisma aplicado"
}

# --- Orquestación del módulo Evolution ---------------------------------------
provision_evolution() {
    log_step "Evolution API (commit ${EVOLUTION_COMMIT}) — código, .env, DB y Prisma"
    log_info "Usuario propietario/ejecución: ${EVOLUTION_USER}"

    _ensure_evolution_code     # 1) git en el commit exacto (fija EVO_BUILD_NEEDED)
    _ensure_evolution_env      # 2) secretos + .env (preserva si existe)
    _sync_megaisp_key          # 3) WHATSAPP_API_KEY/URL en el .env del sistema
    _ensure_evolution_db       # 4) BD + usuario MySQL
    _ensure_build              # 5) npm ci + generate + build (si hace falta)
    _ensure_prisma_deploy      # 6) migrate deploy (siempre)

    # Para el resumen final de install.sh
    PROVISIONED_API_KEY="$API_KEY"
    PROVISIONED_SERVER_URL="$SERVER_URL"
    export PROVISIONED_API_KEY PROVISIONED_SERVER_URL
}

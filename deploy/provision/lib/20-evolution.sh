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
#   - Parches: los de patches/*.patch, en orden; los que ya están → OMITIR.
#   - Build:  si falta dist/main.js, cambió el commit o se aplicaron los parches →
#             npm ci + generate + tsup; si no → OMITIR.
#   - .env:   si ya existe → NO se sobrescribe (preserva la key/password generados).
#   - DB:     CREATE ... IF NOT EXISTS (no rota el password de un usuario existente).
#             Caso borde (#201): si el .env se borró/regeneró (password NUEVO) pero el
#             usuario MySQL YA existía (password VIEJO) → desajuste .env↔MySQL. Por
#             default el script ABORTA con mensaje claro (no rota nada solo). Para
#             re-sincronizar a propósito: EVOLUTION_FORCE_ROTATE_DB_PASSWORD=1.
#   - migrate deploy: siempre corre (Prisma lo hace idempotente). Si la BD ya tenía
#             tablas sin historial de Prisma: se registran las migraciones que ya tiene
#             (comparando estructura); si no es de ninguna: vacía → respaldo + recrear,
#             con datos → ABORTA.
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
        # Como el dueño del código: git rechaza como root un repo ajeno ("dubious
        # ownership") y el commit salía 'desconocido' en cada corrida.
        head="$(_evo git -C "$EVOLUTION_DIR" rev-parse --short HEAD 2>/dev/null || echo '')"
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

# --- 1b) Parches de MegaISP (los que corre dev sobre el mismo commit) --------
# Evolution v2.3.7 trae SQL crudo de PostgreSQL y un esquema MySQL incompleto; dev
# corre con estos cambios desde 2026-07:
#   01 — queries de mensajes en sintaxis MySQL (sin ellas messages.upsert revienta y el
#        webhook a MegaISP nunca se dispara), `lid` en el esquema de IsOnWhatsapp y
#        salida del proceso ante uncaughtException para que PM2 lo reinicie;
#   02 — migración que vuelve a crear `lid` (la de Kafka de Evolution la borra; en dev
#        se agregó a mano), idempotente.
# Cada parche se aplica una vez (si ya está, se omite) y por stdin: el usuario de
# Evolution no necesita leer el repo.
_ensure_evolution_patches() {
    local patch nombre n=0
    for patch in "${PROVISION_DIR}"/patches/*.patch; do
        [[ -f "$patch" ]] || continue
        n=$((n + 1)); nombre="$(basename "$patch")"
        if _evo git -C "$EVOLUTION_DIR" apply --reverse --check - <"$patch" >/dev/null 2>&1; then
            log_skip "Parche ${nombre} ya aplicado"
        elif _evo git -C "$EVOLUTION_DIR" apply --check - <"$patch" >/dev/null 2>&1; then
            log_info "Aplicando parche ${nombre}"
            _evo git -C "$EVOLUTION_DIR" apply - <"$patch"
            EVO_BUILD_NEEDED=1
            log_ok "Parche ${nombre} aplicado"
        else
            die "El parche ${nombre} no aplica limpio en ${EVOLUTION_DIR} (¿cambios locales?). Revisa: git -C ${EVOLUTION_DIR} status"
        fi
    done
    (( n > 0 )) || die "No encuentro parches en ${PROVISION_DIR}/patches"
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
    # tsup directo, como dev: `npm run build` antepone `tsc --noEmit`, que con el
    # cliente Prisma de MySQL truena por tipos de PostgreSQL que trae Evolution
    # (JSON path como arreglo, labelId_instanceId) aunque el bundle sí funcione.
    log_info "tsup (compilando a dist/)"
    _evo bash -c "cd '${EVOLUTION_DIR}' && npx tsup"
    [[ -f "$EVOLUTION_DIR/dist/main.js" ]] || die "El build no generó dist/main.js"
    log_ok "Build completo (dist/main.js)"
}

# --- 6) Aplicar esquema Prisma ------------------------------------------------
# _evo_prisma ARGS... — `npx prisma` en el directorio de Evolution, como su dueño.
# Prisma carga el .env de ese directorio (DATABASE_CONNECTION_URI) → sin secretos en argv.
_evo_prisma() {
    _evo bash -c 'cd "$1" && shift && npx prisma "$@"' _ "$EVOLUTION_DIR" "$@"
}

# _evo_db_query SQL — consulta sobre la BD de Evolution como root de MySQL (una columna, sin encabezado).
_evo_db_query() {
    run_root mysql -N -B -D "$EVO_DB_NAME" -e "$1"
}

# _evo_rollback_fallidas "NOMBRES" — marca como revertidas las migraciones que quedaron
# a medias (finished_at NULL): mientras existan, `migrate deploy` se niega (P3009).
_evo_rollback_fallidas() {
    local m
    for m in $1; do
        log_info "Prisma: la migración ${m} quedó fallida en un intento anterior → se marca como revertida"
        _evo_prisma migrate resolve --rolled-back "$m" --schema prisma/mysql-schema.prisma >/dev/null
    done
}

# _evo_db_huella BD — columnas, índices y llaves foráneas de BD (sin _prisma_migrations ni
# el nombre de la BD), ordenado: dos BD con la misma huella tienen la misma estructura.
_evo_db_huella() {
    local q="table_schema='$1' AND table_name<>'_prisma_migrations'"
    run_root mysql -N -B -e "SELECT 'col', table_name, column_name, column_type, is_nullable, IFNULL(column_default,'(null)'), extra FROM information_schema.columns WHERE ${q} ORDER BY 2,3"
    run_root mysql -N -B -e "SELECT 'idx', table_name, index_name, non_unique, seq_in_index, column_name FROM information_schema.statistics WHERE ${q} ORDER BY 2,3,5"
    run_root mysql -N -B -e "SELECT 'fk', table_name, constraint_name, referenced_table_name, update_rule, delete_rule FROM information_schema.referential_constraints WHERE constraint_schema='$1' AND table_name<>'_prisma_migrations' ORDER BY 2,3"
}

_evo_db_referencia_borrar() {
    run_root mysql -e "DROP DATABASE IF EXISTS \`${EVO_DB_NAME}_ref\`"
}

# _evo_db_sin_historial "FALLIDAS" — la BD ya tiene tablas pero Prisma no tiene ninguna
# migración aplicada (BD creada o importada fuera de este instalador, o historial perdido).
# `migrate deploy` intentaría crear todo de nuevo ("Table ... already exists").
# Se arma una BD temporal ${EVO_DB_NAME}_ref aplicando las migraciones una por una (como
# root, sin Prisma ni contraseñas) y se busca hasta cuál coincide la estructura:
#   - coincide hasta la k → se registran 1..k como aplicadas sin tocar los datos y
#                           `migrate deploy` aplica las que faltan;
#   - ninguna, sin datos  → respaldo + BD recreada vacía (la llena `migrate deploy`);
#   - ninguna, con datos  → se muestra la diferencia y se ABORTA sin tocar nada.
# (Se compara contra las migraciones y no contra el esquema Prisma: en Evolution no
# coinciden al 100%, y lo que existe en las BD reales es lo que dejan las migraciones.)
_evo_db_sin_historial() {
    local fallidas="$1" ref="${EVO_DB_NAME}_ref" tablas huella_bd huella_ref="" m k=0 hasta=0
    local -a migs
    mapfile -t migs < <(_evo bash -c 'ls -1 "$1/prisma/migrations"' _ "$EVOLUTION_DIR" | grep -E '^[0-9]{14}_' | sort)
    tablas="$(_evo_db_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name<>'_prisma_migrations'")"
    log_warn "La BD '${EVO_DB_NAME}' ya tiene ${tablas} tablas, pero sin historial de Prisma (se creó fuera de este instalador o se perdió su historial)."
    log_info "Comparando su estructura con la que deja cada migración de Evolution (BD temporal ${ref})"

    huella_bd="$(_evo_db_huella "$EVO_DB_NAME")"
    run_root mysql -e "DROP DATABASE IF EXISTS \`${ref}\`; CREATE DATABASE \`${ref}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    for m in "${migs[@]}"; do
        if ! run_root mysql -D "$ref" < "${EVOLUTION_DIR}/prisma/migrations/${m}/migration.sql" >/dev/null; then
            _evo_db_referencia_borrar
            die "No pude aplicar ${m} en la BD de referencia ${ref}."
        fi
        k=$((k + 1))
        huella_ref="$(_evo_db_huella "$ref")"
        if [[ "$huella_ref" == "$huella_bd" ]]; then
            hasta=$k                          # la última de una racha de coincidencias
        elif (( hasta > 0 )); then
            break
        fi
    done
    _evo_db_referencia_borrar

    if (( hasta > 0 )); then
        log_info "Su estructura es la que dejan las primeras ${hasta} de ${#migs[@]} migraciones: se registran como aplicadas sin tocar los datos"
        _evo_rollback_fallidas "$fallidas"
        for m in "${migs[@]:0:hasta}"; do
            _evo_prisma migrate resolve --applied "$m" --schema prisma/mysql-schema.prisma >/dev/null
        done
        log_ok "Historial de Prisma registrado (${hasta} migraciones)"
        return 0
    fi

    local t c filas=0 con_datos=""
    for t in $(_evo_db_query "SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name<>'_prisma_migrations' ORDER BY table_name"); do
        c="$(_evo_db_query "SELECT COUNT(*) FROM \`${t}\`")"
        if (( c > 0 )); then
            filas=$((filas + c))
            con_datos+="    ${t}: ${c} filas"$'\n'
        fi
    done
    if (( filas == 0 )); then
        log_info "Su estructura no es la de ninguna migración y no tiene datos: se respalda y se recrea vacía"
        local dump; dump="$(respaldar_bd_evolution antes-de-recrear)"
        log_ok "Respaldo: ${dump}"
        run_root mysql <<SQL
DROP DATABASE \`${EVO_DB_NAME}\`;
CREATE DATABASE \`${EVO_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${EVO_DB_NAME}\`.* TO '${EVO_DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${EVO_DB_NAME}\`.* TO '${EVO_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
        log_ok "BD '${EVO_DB_NAME}' recreada vacía"
        return 0
    fi

    log_warn "Tablas con datos:"
    printf '%s' "$con_datos"
    log_warn "Diferencias de estructura (< esta BD, > con todas las migraciones; primeras 60 líneas):"
    { diff <(printf '%s\n' "$huella_bd") <(printf '%s\n' "$huella_ref") | grep -E '^[<>]' | head -n 60; } || true
    die "La BD '${EVO_DB_NAME}' tiene datos (${filas} filas) y una estructura que no es la de ninguna migración de Evolution ${EVOLUTION_COMMIT}; no se toca. Hay que decidir a mano si se conservan (migrar su estructura) o se respaldan y descartan."
}

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

    # Estado de la BD antes de migrar: tablas y historial de Prisma.
    local tablas aplicadas=0 fallidas=""
    tablas="$(_evo_db_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name<>'_prisma_migrations'")"
    if [[ "$(_evo_db_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='_prisma_migrations'")" == "1" ]]; then
        aplicadas="$(_evo_db_query "SELECT COUNT(*) FROM _prisma_migrations WHERE finished_at IS NOT NULL AND rolled_back_at IS NULL")"
        fallidas="$(_evo_db_query "SELECT migration_name FROM _prisma_migrations WHERE finished_at IS NULL AND rolled_back_at IS NULL ORDER BY started_at")"
    fi

    if (( aplicadas > 0 )); then
        [[ -z "$fallidas" ]] || die "Prisma tiene migraciones fallidas sobre una BD con historial (${fallidas//$'\n'/, }); no se adivina su estado. Revisa: cd ${EVOLUTION_DIR} && npx prisma migrate status --schema prisma/mysql-schema.prisma"
    elif (( tablas == 0 )); then
        _evo_rollback_fallidas "$fallidas"
    else
        _evo_db_sin_historial "$fallidas"
    fi

    log_info "prisma migrate deploy (aplicando esquema a MySQL)"
    _evo_prisma migrate deploy --schema prisma/mysql-schema.prisma
    log_ok "Esquema Prisma aplicado"
}

# --- Orquestación del módulo Evolution ---------------------------------------
provision_evolution() {
    log_step "Evolution API (commit ${EVOLUTION_COMMIT}) — código, .env, DB y Prisma"
    log_info "Usuario propietario/ejecución: ${EVOLUTION_USER}"

    _ensure_evolution_code     # 1) git en el commit exacto (fija EVO_BUILD_NEEDED)
    _ensure_evolution_patches  # 1b) parches de MegaISP (fija EVO_BUILD_NEEDED)
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

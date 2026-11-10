#!/usr/bin/env bash
# =============================================================================
# common.sh — Helpers del instalador de Asterisk del add-on VoIP
# =============================================================================
# Se hace `source` desde install.sh / uninstall.sh y desde cada lib/NN-*.sh.
# NO ejecuta acciones por sí solo: solo define funciones y constantes.
#
# Mismo diseño que deploy/provision (Evolution):
#   - Idempotencia por detección ("existe → OMITIR").
#   - Cada paso que toca algo compartido (vhost, .env) respalda antes y, si falla,
#     restaura y se detiene con el motivo a la vista.
#   - Los valores por servidor se DERIVAN del .env de MegaISP; no se piden.
#   - Secretos jamás se imprimen: la salida queda guardada en la página de add-ons.
# =============================================================================

# --- Rutas --------------------------------------------------------------------
: "${MEGAISP_ROOT:=/var/www/megaisp}"          # raíz del sistema MegaISP en el server
: "${MEGAISP_ENV:=${MEGAISP_ROOT}/.env}"       # .env del sistema (fuente de verdad)
: "${MEGAISP_USER:=www-data}"                  # usuario que corre php-fpm, colas y artisan
: "${VOIP_BACKUP_DIR:=/var/backups/megaisp}"   # respaldos (base realtime, vhosts, .env)

# Carpetas de la provisión (las mismas que usan config/requisitos-voip.php y el
# provisionador). Fijas a propósito: el desinstalador las borra y una ruta
# cambiada por .env no debe poder mandarlo a borrar otra cosa.
ASTERISK_BIN="/usr/sbin/asterisk"
: "${ASTERISK_TRABAJO_DIR:=/var/cache/megaisp-asterisk}"   # override solo para pruebas

# Lo que borra el desinstalador (desinstalar-asterisk.sh + este paquete). Las
# grabaciones NUNCA pueden vivir dentro de ninguna de estas rutas.
ASTERISK_DIRS_QUE_SE_BORRAN=(
    /etc/asterisk
    /var/lib/asterisk
    /var/log/asterisk
    /var/spool/asterisk
    /var/run/asterisk
    /usr/share/megaisp-asterisk
    /var/cache/megaisp-asterisk
)

# Marcadores que identifican al server de DESARROLLO (.11). Mismos que Evolution:
# los scripts de add-ons son solo para producción. Prod comparte hostname con dev,
# por eso la página manda ALLOW_PROVISION=1 (salta SOLO el hostname); el APP_URL
# sigue distinguiendo dev y ahí se aborta siempre.
DEV_HOSTNAME="meganet"
DEV_APP_URL_MATCH="192.168.105.11"

VOIP_PROVISION_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VOIP_MODULE_DIR="$(cd "${VOIP_PROVISION_DIR}/../.." && pwd)"
# Lo que sabe quitar Asterisk del servidor (override solo para pruebas).
: "${VOIP_DESINSTALAR_ASTERISK_SH:=${VOIP_MODULE_DIR}/provisioning/desinstalar-asterisk.sh}"

# --- Logging (mismo formato que deploy/provision) ----------------------------
if [[ -t 1 ]]; then
    _C_RESET=$'\033[0m'; _C_INFO=$'\033[0;36m'; _C_OK=$'\033[0;32m'
    _C_SKIP=$'\033[0;33m'; _C_ERR=$'\033[0;31m'; _C_STEP=$'\033[1;35m'
else
    _C_RESET=""; _C_INFO=""; _C_OK=""; _C_SKIP=""; _C_ERR=""; _C_STEP=""
fi

log_step() { printf '\n%s==> %s%s\n' "$_C_STEP" "$*" "$_C_RESET"; }
log_info() { printf '%s[..]%s %s\n' "$_C_INFO" "$_C_RESET" "$*"; }
log_ok()   { printf '%s[ok]%s %s\n' "$_C_OK" "$_C_RESET" "$*"; }
log_skip() { printf '%s[--]%s OMITIDO (ya existe): %s\n' "$_C_SKIP" "$_C_RESET" "$*"; }
log_gone() { printf '%s[--]%s Nada que quitar: %s\n' "$_C_SKIP" "$_C_RESET" "$*"; }
log_warn() { printf '%s[!!]%s %s\n' "$_C_SKIP" "$_C_RESET" "$*" >&2; }
log_err()  { printf '%s[XX]%s %s\n' "$_C_ERR" "$_C_RESET" "$*" >&2; }

die() { log_err "$*"; exit 1; }

have()    { command -v "$1" >/dev/null 2>&1; }
require() { have "$1" || die "${2:-Falta la herramienta requerida: $1}"; }
is_root() { [[ "$(id -u)" -eq 0 ]]; }

# run_as USER CMD... — ejecuta como USER (artisan como el usuario web).
run_as() {
    local u="$1"; shift
    if [[ "$(id -un)" == "$u" ]]; then
        "$@"
    elif is_root; then
        if have runuser; then runuser -u "$u" -- "$@"; else sudo -u "$u" -H "$@"; fi
    else
        die "No puedo ejecutar como '$u' sin ser root: $*"
    fi
}

# --- .env de MegaISP ----------------------------------------------------------
# get_env_value KEY [ARCHIVO] — valor de KEY sin comillas envolventes; vacío si no
# existe. Lee la ÚLTIMA definición (comportamiento de dotenv).
get_env_value() {
    local key="$1" file="${2:-$MEGAISP_ENV}"
    [[ -f "$file" ]] || return 0
    local line
    line="$(grep -E "^[[:space:]]*${key}=" "$file" | tail -n1)" || true
    [[ -z "$line" ]] && return 0
    local val="${line#*=}"
    val="${val%\"}"; val="${val#\"}"
    val="${val%\'}"; val="${val#\'}"
    printf '%s' "$val"
}

# upsert_env_var ARCHIVO KEY VALUE — fija KEY=VALUE (reemplaza o agrega). Sin sed
# (valores con / & +); `cat >` conserva dueño y permisos del original.
upsert_env_var() {
    local file="$1" key="$2" value="$3"
    [[ -f "$file" ]] || die "No existe el archivo .env destino: $file"
    local tmp; tmp="$(mktemp)"
    _UPSERT_KEY="$key" _UPSERT_VAL="$value" awk '
        BEGIN { k = ENVIRON["_UPSERT_KEY"]; v = ENVIRON["_UPSERT_VAL"]; done = 0 }
        $0 ~ "^[[:space:]]*" k "=" { if (!done) { print k "=" v; done = 1 } ; next }
        { print }
        END { if (!done) print k "=" v }
    ' "$file" > "$tmp"
    cat "$tmp" > "$file"
    rm -f "$tmp"
}

# remove_env_var ARCHIVO KEY — quita todas las líneas KEY=... (conserva permisos).
remove_env_var() {
    local file="$1" key="$2"
    [[ -f "$file" ]] || return 0
    local tmp; tmp="$(mktemp)"
    _RM_KEY="$key" awk 'BEGIN { k = ENVIRON["_RM_KEY"] } $0 !~ "^[[:space:]]*" k "=" { print }' "$file" > "$tmp"
    cat "$tmp" > "$file"
    rm -f "$tmp"
}

# respaldar_env ETIQUETA — copia el .env de MegaISP junto a él (mismo dueño y
# permisos) e imprime la ruta. Una por corrida y etiqueta.
respaldar_env() {
    local bak="${MEGAISP_ENV}.${1}.$(date +%Y%m%d%H%M%S).bak"
    cp -p "$MEGAISP_ENV" "$bak" || die "No pude respaldar ${MEGAISP_ENV}; no se tocó."
    printf '%s' "$bak"
}

# gen_secret — secreto hex de 64 caracteres: seguro dentro del .env y de SQL.
gen_secret() {
    require openssl "Se requiere 'openssl' para generar secretos"
    openssl rand -hex 32
}

# --- Valores del módulo -------------------------------------------------------
# Las mismas variables y valores por omisión que config/database.php
# (conexión asterisk_rt) y config/voip.php.
voip_db_host() { local v; v="$(get_env_value ASTERISK_RT_DB_HOST)"; printf '%s' "${v:-127.0.0.1}"; }
voip_db_port() { local v; v="$(get_env_value ASTERISK_RT_DB_PORT)"; printf '%s' "${v:-3306}"; }
voip_db_name() { local v; v="$(get_env_value ASTERISK_RT_DB_DATABASE)"; printf '%s' "${v:-asterisk}"; }
voip_db_user() { local v; v="$(get_env_value ASTERISK_RT_DB_USERNAME)"; printf '%s' "${v:-asterisk_rt}"; }
voip_ari_port() { local v; v="$(get_env_value ASTERISK_ARI_PORT)"; printf '%s' "${v:-8088}"; }
voip_grabaciones_dir() {
    local v; v="$(get_env_value MEGAISP_ASTERISK_GRABACIONES_DIR)"
    v="${v:-/var/lib/megaisp/grabaciones}"
    printf '%s' "${v%/}"
}
voip_generados_dir() {
    local v; v="$(get_env_value MEGAISP_ASTERISK_GENERADOS_DIR)"
    v="${v:-/etc/asterisk/megaisp.d}"
    printf '%s' "${v%/}"
}

# Grupo principal del usuario web (dueño de lo que crea artisan).
megaisp_group() { id -gn "$MEGAISP_USER" 2>/dev/null || printf '%s' "$MEGAISP_USER"; }

# es_identificador VALOR — nombres de base/usuario que van interpolados en SQL.
es_identificador() { [[ "$1" =~ ^[A-Za-z0-9_]+$ ]]; }

# --- Guardas ------------------------------------------------------------------
# abort_if_dev_server — aborta si el host parece el de desarrollo.
abort_if_dev_server() {
    local host app_url
    host="$(hostname 2>/dev/null || echo '')"
    app_url="$(get_env_value APP_URL)"

    if [[ "$host" == "$DEV_HOSTNAME" ]]; then
        if [[ "${ALLOW_PROVISION:-}" == "1" ]]; then
            log_warn "hostname='$host' coincide con el marcador de dev, pero ALLOW_PROVISION=1 → se OMITE solo el check de hostname (el de APP_URL sigue activo)."
        else
            die "Este parece ser el server de DESARROLLO (hostname='$host'). ABORTADO: los scripts de add-ons son solo para producción. Si de verdad es producción (comparte hostname con dev): sudo env ALLOW_PROVISION=1 bash $0"
        fi
    fi
    if [[ "$app_url" == *"$DEV_APP_URL_MATCH"* ]]; then
        die "Este parece ser el server de DESARROLLO (APP_URL contiene '$DEV_APP_URL_MATCH'). ABORTADO: los scripts de add-ons son solo para producción; el Asterisk de dev no se toca desde aquí."
    fi
    log_ok "Guarda de seguridad OK: no es el server de dev (hostname='$host')."
}

# dentro_de RUTA BASE — ¿RUTA es BASE o está debajo de BASE? (sin resolver symlinks
# de la ruta, que puede no existir todavía)
dentro_de() {
    local r="${1%/}/" b="${2%/}/"
    [[ "$r" == "$b"* ]]
}

# abort_if_grabaciones_en_riesgo — las grabaciones se quedan donde están. Si la
# carpeta configurada cae dentro de algo que el desinstalador borra, se aborta
# ANTES de tocar nada.
abort_if_grabaciones_en_riesgo() {
    local g d real
    g="$(voip_grabaciones_dir)"
    [[ "$g" == /* ]] || die "MEGAISP_ASTERISK_GRABACIONES_DIR='${g}' no es una ruta absoluta; no se sigue."
    real="$g"
    [[ -e "$g" ]] && real="$(readlink -f "$g")"
    for d in "${ASTERISK_DIRS_QUE_SE_BORRAN[@]}"; do
        if dentro_de "$g" "$d" || dentro_de "$real" "$d"; then
            die "Las grabaciones están configuradas en ${g}, dentro de ${d}, que esta desinstalación borra. ABORTADO sin tocar nada: mueve las grabaciones fuera (o cambia MEGAISP_ASTERISK_GRABACIONES_DIR) y reintenta."
        fi
    done
    log_ok "Grabaciones en ${g}: fuera de lo que se borra; se quedan donde están."
}

# --- Respaldos ----------------------------------------------------------------
# respaldar_bd_realtime [ETIQUETA] — mysqldump comprimido de la base realtime en
# $VOIP_BACKUP_DIR (700, solo root). Imprime la ruta; si falla, aborta.
respaldar_bd_realtime() {
    require mysqldump "Se requiere 'mysqldump' para respaldar la base realtime"
    local db; db="$(voip_db_name)"
    es_identificador "$db" || die "Nombre de base realtime inválido: '${db}'"
    local dump="${VOIP_BACKUP_DIR}/${db}-$(date +%Y%m%d%H%M%S)${1:+-$1}.sql.gz"
    mkdir -p "$VOIP_BACKUP_DIR"
    chmod 700 "$VOIP_BACKUP_DIR"
    bash -c 'set -o pipefail; umask 077; mysqldump --single-transaction --routines --no-tablespaces "$1" | gzip > "$2"' \
        _ "$db" "$dump" \
        || { rm -f "$dump"; die "No se pudo respaldar la base '${db}' en ${dump}; no se tocó nada."; }
    gzip -t "$dump" || { rm -f "$dump"; die "El respaldo ${dump} salió dañado (gzip -t); no se tocó nada."; }
    printf '%s' "$dump"
}

# mysql_existe_bd NOMBRE / mysql_existe_usuario NOMBRE — consultas como root de MySQL.
mysql_existe_bd() {
    [[ "$(mysql -N -B -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$1'" 2>/dev/null || true)" == "$1" ]]
}
mysql_existe_usuario() {
    [[ -n "$(mysql -N -B -e "SELECT User FROM mysql.user WHERE User='$1' AND Host IN ('127.0.0.1','localhost') LIMIT 1" 2>/dev/null || true)" ]]
}

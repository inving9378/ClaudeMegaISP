#!/usr/bin/env bash
# =============================================================================
# common.sh — Helpers compartidos del provisioning de Evolution API
# =============================================================================
# Se hace `source` desde install.sh y desde cada lib/NN-*.sh.
# NO ejecuta acciones por sí solo: solo define funciones y constantes.
#
# Reglas de diseño:
#   - Idempotencia por detección ("existe → OMITIR"). Nada aquí muta estado.
#   - Todo en español. Secretos jamás se imprimen (salvo la key, UNA vez, al cierre).
#   - Los valores por-server se DERIVAN del .env del sistema MegaISP; no se piden.
# =============================================================================

# --- Rutas y constantes de la receta (auditada en dev, NO adivinar) ----------
: "${MEGAISP_ROOT:=/var/www/megaisp}"          # raíz del sistema MegaISP en el server
: "${MEGAISP_ENV:=${MEGAISP_ROOT}/.env}"       # .env del sistema (fuente de verdad)

EVOLUTION_DIR="/opt/evolution-api"             # instalación de Evolution
EVOLUTION_REPO="https://github.com/EvolutionAPI/evolution-api.git"
EVOLUTION_COMMIT="fa09d378"                    # commit EXACTO (git describe: 2.3.7-6-gfa09d378)
EVOLUTION_PM2_NAME="evolution-api"             # nombre del proceso en PM2
EVOLUTION_PORT="8080"                          # puerto local donde escucha Evolution

NODE_MAJOR="20"                                # Node 20.x

EVO_DB_NAME="evolution_api"                    # base de datos de Evolution
EVO_DB_USER="evolution_user"                   # usuario MySQL de Evolution
EVO_DB_HOST="127.0.0.1"
EVO_DB_PORT="3306"

# Marcadores que identifican al server de DESARROLLO (.11). El script DEBE abortar
# si detecta cualquiera de ellos, para no reconfigurar la Evolution de dev que ya
# funciona. (Guarda de seguridad — decisión de Irving.)
DEV_HOSTNAME="meganet"
DEV_APP_URL_MATCH="192.168.105.11"

# Directorio base de este paquete de provisioning (resuelto en tiempo de source)
PROVISION_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEMPLATES_DIR="${PROVISION_DIR}/templates"

# --- Logging -----------------------------------------------------------------
# Colores solo si la salida es una terminal.
if [[ -t 1 ]]; then
    _C_RESET=$'\033[0m'; _C_INFO=$'\033[0;36m'; _C_OK=$'\033[0;32m'
    _C_SKIP=$'\033[0;33m'; _C_ERR=$'\033[0;31m'; _C_STEP=$'\033[1;35m'
else
    _C_RESET=""; _C_INFO=""; _C_OK=""; _C_SKIP=""; _C_ERR=""; _C_STEP=""
fi

log_step() { printf '\n%s==> %s%s\n' "$_C_STEP" "$*" "$_C_RESET"; }   # inicio de sección
log_info() { printf '%s[..]%s %s\n' "$_C_INFO" "$_C_RESET" "$*"; }     # acción en curso
log_ok()   { printf '%s[ok]%s %s\n' "$_C_OK" "$_C_RESET" "$*"; }       # hecho
log_skip() { printf '%s[--]%s OMITIDO (ya existe): %s\n' "$_C_SKIP" "$_C_RESET" "$*"; }
log_warn() { printf '%s[!!]%s %s\n' "$_C_SKIP" "$_C_RESET" "$*" >&2; }
log_err()  { printf '%s[XX]%s %s\n' "$_C_ERR" "$_C_RESET" "$*" >&2; }

# die MENSAJE — imprime error y aborta con código 1.
die() { log_err "$*"; exit 1; }

# --- Comprobaciones de herramientas ------------------------------------------
# have CMD — ¿existe el comando en PATH?
have() { command -v "$1" >/dev/null 2>&1; }

# require CMD [MENSAJE] — aborta si el comando no existe.
require() {
    have "$1" || die "${2:-Falta la herramienta requerida: $1}"
}

# is_root — ¿corremos como root? (varios pasos necesitan privilegios).
is_root() { [[ "$(id -u)" -eq 0 ]]; }

# run_root CMD... — ejecuta como root (directo si ya somos root, si no vía sudo -n).
run_root() {
    if is_root; then
        "$@"
    elif have sudo; then
        sudo -n "$@"
    else
        die "Se requieren privilegios de root para: $*"
    fi
}

# --- Lectura del .env del sistema MegaISP ------------------------------------
# get_env_value KEY [ARCHIVO] — devuelve el valor de KEY (sin comillas envolventes).
# Cadena vacía si no existe. Lee la ÚLTIMA definición (comportamiento de dotenv).
get_env_value() {
    local key="$1" file="${2:-$MEGAISP_ENV}"
    [[ -f "$file" ]] || return 0
    local line
    line="$(grep -E "^[[:space:]]*${key}=" "$file" | tail -n1)" || true
    [[ -z "$line" ]] && return 0
    local val="${line#*=}"
    # quitar comillas simples o dobles envolventes
    val="${val%\"}"; val="${val#\"}"
    val="${val%\'}"; val="${val#\'}"
    printf '%s' "$val"
}

# upsert_env_var ARCHIVO KEY VALUE — fija KEY=VALUE de forma idempotente:
# reemplaza la línea si existe, la agrega si no. Sin sed (evita problemas de
# escape con `/`, `&`, `+` en keys/passwords). Usa ENVIRON de awk (no interpreta
# secuencias de escape en el valor).
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
    # preservar permisos del original
    cat "$tmp" > "$file"
    rm -f "$tmp"
}

# --- Render de plantillas -----------------------------------------------------
# render_template ARCHIVO_TPL — imprime la plantilla con los {{PLACEHOLDERS}}
# sustituidos. Los valores se toman de estas variables de entorno (deben estar
# exportadas/definidas por el llamador): SERVER_URL, API_KEY, DB_PASSWORD.
# Sustitución por expansión de parámetros de bash (segura ante `/`, `:`, etc.).
render_template() {
    local tpl="$1"
    [[ -f "$tpl" ]] || die "No existe la plantilla: $tpl"
    local content; content="$(cat "$tpl")"
    content="${content//\{\{SERVER_URL\}\}/${SERVER_URL:-}}"
    content="${content//\{\{API_KEY\}\}/${API_KEY:-}}"
    content="${content//\{\{DB_PASSWORD\}\}/${DB_PASSWORD:-}}"
    printf '%s\n' "$content"
}

# --- Secretos -----------------------------------------------------------------
# gen_secret — genera un secreto hexadecimal fuerte (64 chars). Hex a propósito:
# sin caracteres especiales, así es seguro dentro de la URI mysql:// y del .env.
gen_secret() {
    require openssl "Se requiere 'openssl' para generar secretos"
    openssl rand -hex 32
}

# --- Guarda de seguridad: NO correr en el server de dev ----------------------
# abort_if_dev_server — aborta si el host actual parece ser el de desarrollo.
# Compara hostname y APP_URL del .env de MegaISP contra los marcadores conocidos.
abort_if_dev_server() {
    local host app_url
    host="$(hostname 2>/dev/null || echo '')"
    app_url="$(get_env_value APP_URL)"

    if [[ "$host" == "$DEV_HOSTNAME" ]]; then
        die "Este parece ser el server de DESARROLLO (hostname='$host'). ABORTADO: este install.sh NO debe reconfigurar la Evolution de dev. Córrelo solo en un server nuevo o en producción."
    fi
    if [[ "$app_url" == *"$DEV_APP_URL_MATCH"* ]]; then
        die "Este parece ser el server de DESARROLLO (APP_URL contiene '$DEV_APP_URL_MATCH'). ABORTADO: este install.sh NO debe reconfigurar la Evolution de dev. Córrelo solo en un server nuevo o en producción."
    fi
    log_ok "Guarda de seguridad OK: no es el server de dev (hostname='$host')."
}

# --- Derivación de valores por-server ----------------------------------------
# derive_server_url — imprime la SERVER_URL de Evolution derivada del APP_URL
# del sistema MegaISP + '/evolution'. Aborta si APP_URL no está definida.
derive_server_url() {
    local app_url; app_url="$(get_env_value APP_URL)"
    [[ -n "$app_url" ]] || die "APP_URL no está definida en ${MEGAISP_ENV}; no puedo derivar SERVER_URL."
    app_url="${app_url%/}"                       # sin barra final
    printf '%s/evolution' "$app_url"
}

#!/usr/bin/env bash
# =============================================================================
#  MegaISP - Script de Instalación Automática
#  Repositorio : https://github.com/inving9378/ClaudeMegaISP
#  Compatible  : Debian 11/12 · Ubuntu 20.04/22.04/24.04 · AlmaLinux/Rocky 8+
#
#  ── COMANDO DE UN SOLO PASO (desde cualquier servidor nuevo) ───────────────
#  bash <(curl -fsSL https://raw.githubusercontent.com/inving9378/ClaudeMegaISP/main/install_megaisp.sh)
#
#  ── O en dos pasos ─────────────────────────────────────────────────────────
#  curl -fsSL https://raw.githubusercontent.com/inving9378/ClaudeMegaISP/main/install_megaisp.sh -o install.sh
#  sudo bash install.sh
# =============================================================================

set -euo pipefail

# ─────────────────────────────────────────────────────────
#  COLORES
# ─────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

info()    { echo -e "${CYAN}[INFO]${RESET}  $*"; }
success() { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
error()   { echo -e "${RED}[ERROR]${RESET} $*"; exit 1; }
step()    {
    echo -e "\n${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
    echo -e "${BOLD}  $*${RESET}"
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
}

# ─────────────────────────────────────────────────────────
#  VERIFICAR ROOT
# ─────────────────────────────────────────────────────────
[[ $EUID -ne 0 ]] && error "Ejecuta este script como root:  sudo bash $0"

# ─────────────────────────────────────────────────────────
#  DETECTAR DISTRIBUCIÓN
# ─────────────────────────────────────────────────────────
detect_distro() {
    [ -f /etc/os-release ] || error "No se puede detectar la distribución Linux."
    . /etc/os-release
    DISTRO_ID="${ID,,}"
    DISTRO_VER="${VERSION_ID:-0}"
    case "$DISTRO_ID" in
        debian|ubuntu|linuxmint|pop)        PKG_MGR="apt" ;;
        rhel|centos|almalinux|rocky|fedora) PKG_MGR="dnf" ;;
        *) error "Distro '$DISTRO_ID' no soportada." ;;
    esac
    info "Distribución: ${DISTRO_ID} ${DISTRO_VER}  |  Gestor: ${PKG_MGR}"
}

# ─────────────────────────────────────────────────────────
#  VARIABLES
# ─────────────────────────────────────────────────────────
APP_DIR="/var/www/megaisp"
REPO_URL="https://github.com/inving9378/ClaudeMegaISP.git"
PHP_VERSION="8.2"
NODE_VERSION="20"
DB_NAME="megaisp"
DB_USER="megaisp_user"
DB_PASS="$(openssl rand -base64 18 | tr -dc 'a-zA-Z0-9' | head -c 22)"
SERVER_IP="$(hostname -I | awk '{print $1}')"
APP_URL="http://${SERVER_IP}"
CREDS_FILE="/root/megaisp_credenciales.txt"

# ─────────────────────────────────────────────────────────
#  BANNER
# ─────────────────────────────────────────────────────────
clear
echo -e "${BOLD}${CYAN}"
cat <<'BANNER'
  ███╗   ███╗███████╗ ██████╗  █████╗ ██╗███████╗██████╗
  ████╗ ████║██╔════╝██╔════╝ ██╔══██╗██║██╔════╝██╔══██╗
  ██╔████╔██║█████╗  ██║  ███╗███████║██║███████╗██████╔╝
  ██║╚██╔╝██║██╔══╝  ██║   ██║██╔══██║██║╚════██║██╔═══╝
  ██║ ╚═╝ ██║███████╗╚██████╔╝██║  ██║██║███████║██║
  ╚═╝     ╚═╝╚══════╝ ╚═════╝ ╚═╝  ╚═╝╚═╝╚══════╝╚═╝
BANNER
echo -e "${RESET}"
echo -e "  ${BOLD}Instalador Automático — Sistema Medussa / MegaISP${RESET}"
echo -e "  ${YELLOW}Entorno de pruebas / development${RESET}\n"
echo -e "  Directorio  : ${CYAN}${APP_DIR}${RESET}"
echo -e "  URL acceso  : ${CYAN}${APP_URL}${RESET}"
echo -e "  Base datos  : ${CYAN}${DB_NAME}${RESET}"
echo -e "  Usuario DB  : ${CYAN}${DB_USER}${RESET}"
echo ""

# ── Leer confirmación desde /dev/tty para compatibilidad con curl | bash ──
if [ -t 0 ]; then
    read -rp "$(echo -e "${YELLOW}  ¿Continuar con la instalación? [s/N]: ${RESET}")" CONFIRM
else
    read -rp "$(echo -e "${YELLOW}  ¿Continuar con la instalación? [s/N]: ${RESET}")" CONFIRM </dev/tty
fi
[[ "${CONFIRM,,}" != "s" ]] && echo -e "\n  Instalación cancelada.\n" && exit 0

detect_distro

# ─────────────────────────────────────────────────────────
#  PASO 1 — ACTUALIZAR SISTEMA
# ─────────────────────────────────────────────────────────
step "PASO 1/9 — Actualizando el sistema"
if [ "$PKG_MGR" = "apt" ]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get upgrade -y -qq \
        -o Dpkg::Options::="--force-confdef" \
        -o Dpkg::Options::="--force-confold"
else
    dnf update -y -q
fi
success "Sistema actualizado"

# ─────────────────────────────────────────────────────────
#  PASO 2 — DEPENDENCIAS BASE
# ─────────────────────────────────────────────────────────
step "PASO 2/9 — Instalando dependencias base"
if [ "$PKG_MGR" = "apt" ]; then
    apt-get install -y -qq \
        curl wget git unzip zip openssl \
        software-properties-common ca-certificates gnupg lsb-release apt-transport-https
else
    dnf install -y -q curl wget git unzip zip openssl ca-certificates gnupg
fi
success "Dependencias base instaladas"

# ─────────────────────────────────────────────────────────
#  PASO 3 — PHP 8.2 + EXTENSIONES + COMPOSER
# ─────────────────────────────────────────────────────────
step "PASO 3/9 — Instalando PHP ${PHP_VERSION} y extensiones"

if [ "$PKG_MGR" = "apt" ]; then
    if ! php${PHP_VERSION} --version &>/dev/null 2>&1; then
        info "Agregando repositorio PHP Sury/Ondrej..."
        add-apt-repository -y ppa:ondrej/php 2>/dev/null || {
            curl -sSL https://packages.sury.org/php/apt.gpg \
                | gpg --dearmor -o /usr/share/keyrings/sury-php.gpg
            echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(lsb_release -cs) main" \
                > /etc/apt/sources.list.d/sury-php.list
            apt-get update -qq
        }
    fi
    apt-get install -y -qq \
        php${PHP_VERSION} php${PHP_VERSION}-cli php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-mysql php${PHP_VERSION}-xml php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-gd \
        php${PHP_VERSION}-bcmath php${PHP_VERSION}-intl php${PHP_VERSION}-soap \
        php${PHP_VERSION}-tokenizer php${PHP_VERSION}-fileinfo \
        php${PHP_VERSION}-sockets php${PHP_VERSION}-imagick
else
    dnf install -y -q epel-release
    dnf install -y -q "https://rpms.remirepo.net/enterprise/remi-release-8.rpm" 2>/dev/null || true
    dnf module reset php -y -q
    dnf module enable php:remi-8.2 -y -q
    dnf install -y -q \
        php php-cli php-fpm php-mysqlnd php-xml php-mbstring \
        php-curl php-zip php-gd php-bcmath php-intl php-soap php-sockets
fi

if [ "$PKG_MGR" = "apt" ]; then
    systemctl enable --now php${PHP_VERSION}-fpm
else
    systemctl enable --now php-fpm
fi

# Instalar Composer con verificación de checksum
if ! command -v composer &>/dev/null; then
    info "Instalando Composer..."
    EXPECTED_SIG="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    ACTUAL_SIG="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [ "$EXPECTED_SIG" = "$ACTUAL_SIG" ] || error "Checksum de Composer no coincide. Abortando."
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm /tmp/composer-setup.php
fi
success "PHP ${PHP_VERSION} + Composer instalados"

# ─────────────────────────────────────────────────────────
#  PASO 4 — NODE.JS 20
# ─────────────────────────────────────────────────────────
step "PASO 4/9 — Instalando Node.js ${NODE_VERSION}"

CURRENT_NODE="$(node --version 2>/dev/null | sed 's/v//' | cut -d. -f1 || echo 0)"
if [ "$CURRENT_NODE" -lt "$NODE_VERSION" ] 2>/dev/null; then
    info "Configurando repositorio NodeSource..."
    if [ "$PKG_MGR" = "apt" ]; then
        curl -fsSL "https://deb.nodesource.com/setup_${NODE_VERSION}.x" | bash - 2>&1 | tail -3
        apt-get install -y -qq nodejs
    else
        curl -fsSL "https://rpm.nodesource.com/setup_${NODE_VERSION}.x" | bash - 2>&1 | tail -3
        dnf install -y -q nodejs
    fi
fi
success "Node.js $(node --version)  /  npm $(npm --version)"

# ─────────────────────────────────────────────────────────
#  PASO 5 — MARIADB + BASE DE DATOS LIMPIA
# ─────────────────────────────────────────────────────────
step "PASO 5/9 — Instalando MariaDB"

if [ "$PKG_MGR" = "apt" ]; then
    apt-get install -y -qq mariadb-server mariadb-client
else
    dnf install -y -q mariadb-server mariadb
fi

systemctl enable --now mariadb

info "Creando base de datos LIMPIA (DROP + CREATE)..."
mysql -u root <<EOSQL
DROP DATABASE IF EXISTS \`${DB_NAME}\`;
CREATE DATABASE \`${DB_NAME}\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS '${DB_USER}'@'localhost';
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOSQL
success "MariaDB listo — DB: ${DB_NAME}  |  Usuario: ${DB_USER}"

# ─────────────────────────────────────────────────────────
#  PASO 6 — NGINX
# ─────────────────────────────────────────────────────────
step "PASO 6/9 — Instalando y configurando Nginx"

if [ "$PKG_MGR" = "apt" ]; then
    apt-get install -y -qq nginx
else
    dnf install -y -q nginx
fi

systemctl enable --now nginx

PHP_SOCK="/var/run/php/php${PHP_VERSION}-fpm.sock"
[ "$PKG_MGR" = "dnf" ] && PHP_SOCK="/run/php-fpm/www.sock"

mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled

cat > /etc/nginx/sites-available/megaisp.conf <<NGINX
server {
    listen 80 default_server;
    server_name _;
    root ${APP_DIR}/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* { deny all; }

    client_max_body_size 100M;
}
NGINX

ln -sf /etc/nginx/sites-available/megaisp.conf /etc/nginx/sites-enabled/megaisp.conf
rm -f /etc/nginx/sites-enabled/default

# En RHEL nginx.conf no incluye sites-enabled por defecto
if [ "$PKG_MGR" = "dnf" ]; then
    grep -q "sites-enabled" /etc/nginx/nginx.conf || \
        sed -i '/http {/a\    include /etc/nginx/sites-enabled/*.conf;' /etc/nginx/nginx.conf
fi

nginx -t 2>&1 && systemctl reload nginx
success "Nginx configurado"

# ─────────────────────────────────────────────────────────
#  PASO 7 — CLONAR REPOSITORIO
# ─────────────────────────────────────────────────────────
step "PASO 7/9 — Clonando repositorio ClaudeMegaISP"

if [ -d "$APP_DIR" ]; then
    warn "Directorio ${APP_DIR} existe — eliminando para instalación limpia..."
    rm -rf "$APP_DIR"
fi

git clone "$REPO_URL" "$APP_DIR"
success "Repositorio clonado en ${APP_DIR}"

# ─────────────────────────────────────────────────────────
#  PASO 8 — CONFIGURAR LARAVEL
# ─────────────────────────────────────────────────────────
step "PASO 8/9 — Configurando Laravel"

cd "$APP_DIR"

# Neutralizar credenciales de producción que vienen en el repo
info "Limpiando credenciales de producción del .env.example..."
sed -i \
    -e 's|^APP_KEY=.*|APP_KEY=|' \
    -e 's|^DB_HOST=.*|DB_HOST=127.0.0.1|' \
    -e 's|^DB_DATABASE=.*|DB_DATABASE=|' \
    -e 's|^DB_USERNAME=.*|DB_USERNAME=|' \
    -e 's|^DB_PASSWORD=.*|DB_PASSWORD=|' \
    .env.example

cp .env.example .env

# Inyectar configuración del entorno actual
sed -i \
    -e "s|^APP_ENV=.*|APP_ENV=local|" \
    -e "s|^APP_DEBUG=.*|APP_DEBUG=true|" \
    -e "s|^APP_URL=.*|APP_URL=${APP_URL}|" \
    -e "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" \
    -e "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" \
    .env

# Dependencias PHP
info "Ejecutando composer install..."
composer install --no-interaction --optimize-autoloader --no-dev 2>&1 | tail -6

# APP_KEY
php artisan key:generate --ansi --force

# Storage link
php artisan storage:link --force 2>/dev/null || true

# Migraciones
info "Ejecutando migraciones..."
php artisan migrate --force 2>&1 \
    && success "Migraciones completadas" \
    || warn "Algunas migraciones fallaron — revisa: php artisan migrate:status"

# Seeders
php artisan db:seed --force 2>/dev/null \
    && success "Seeders ejecutados" \
    || warn "Seeders no disponibles (normal en dev)"

# Frontend
info "Instalando dependencias npm (puede tardar varios minutos)..."
npm install --silent 2>&1 | tail -4

info "Compilando assets (Laravel Mix)..."
npm run production 2>&1 | tail -6

# Permisos
WEB_USER="www-data"
[ "$PKG_MGR" = "dnf" ] && WEB_USER="nginx"
chown -R "${WEB_USER}:${WEB_USER}" "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# Caché Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

success "Laravel configurado correctamente"

# ─────────────────────────────────────────────────────────
#  PASO 9 — REINICIAR SERVICIOS
# ─────────────────────────────────────────────────────────
step "PASO 9/9 — Reiniciando servicios"

systemctl restart mariadb
systemctl restart nginx
if [ "$PKG_MGR" = "apt" ]; then
    systemctl restart php${PHP_VERSION}-fpm
else
    systemctl restart php-fpm
fi

success "Todos los servicios activos"

# ─────────────────────────────────────────────────────────
#  GUARDAR CREDENCIALES EN /root/
# ─────────────────────────────────────────────────────────
cat > "$CREDS_FILE" <<CREDS
════════════════════════════════════════════════
  MegaISP — Credenciales de instalación
  Fecha    : $(date '+%Y-%m-%d %H:%M:%S')
  Servidor : ${SERVER_IP}
════════════════════════════════════════════════

  URL del sistema  :  ${APP_URL}
  Directorio app   :  ${APP_DIR}

  Base de datos    :  ${DB_NAME}
  Usuario DB       :  ${DB_USER}
  Contraseña DB    :  ${DB_PASS}

  Guarda este archivo en lugar seguro.
  Para eliminarlo:  rm ${CREDS_FILE}
════════════════════════════════════════════════
CREDS
chmod 600 "$CREDS_FILE"

# ─────────────────────────────────────────────────────────
#  RESUMEN FINAL
# ─────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${GREEN}╔══════════════════════════════════════════════════╗${RESET}"
echo -e "${BOLD}${GREEN}║       ✅  INSTALACIÓN COMPLETADA CON ÉXITO       ║${RESET}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════════╝${RESET}"
echo ""
echo -e "  🌐  Sistema disponible en  →  ${BOLD}${CYAN}${APP_URL}${RESET}"
echo -e "  🗄️   Base de datos          →  ${CYAN}${DB_NAME}${RESET}"
echo -e "  🔑  Credenciales guardadas  →  ${CYAN}${CREDS_FILE}${RESET}"
echo ""
echo -e "  ${YELLOW}⚠️  Pendiente de tu parte:${RESET}"
echo -e "     • Agrega tu Google Maps API Key en ${APP_DIR}/.env"
echo -e "       Variable: MIX_VUE_APP_GOOGLEMAPS_KEY"
echo -e "     • Revoca la API Key expuesta en el README del repositorio"
echo ""
echo -e "  ${CYAN}Comandos útiles:${RESET}"
echo -e "     Logs Laravel   →  tail -f ${APP_DIR}/storage/logs/laravel.log"
echo -e "     Logs Nginx     →  tail -f /var/log/nginx/error.log"
echo -e "     Limpiar caché  →  cd ${APP_DIR} && php artisan optimize:clear"
echo -e "     Ver servicios  →  systemctl status nginx mariadb"
echo ""

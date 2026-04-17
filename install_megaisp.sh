#!/usr/bin/env bash
# =============================================================================
#  MegaISP - Script de Instalación Automática v2
#  Repositorio : https://github.com/inving9378/ClaudeMegaISP
#  Compatible  : Debian 11/12 · Ubuntu 20.04/22.04/24.04 · AlmaLinux/Rocky 8+
#
#  ── COMANDO DE UN SOLO PASO ────────────────────────────────────────────────
#  bash <(curl -fsSL https://raw.githubusercontent.com/inving9378/ClaudeMegaISP/main/install_megaisp.sh)
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
[[ $EUID -ne 0 ]] && error "Ejecuta este script como root: sudo bash $0"

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
    info "Distribución: ${DISTRO_ID} ${DISTRO_VER} | Gestor: ${PKG_MGR}"
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
ADMIN_PASS="Admin$(openssl rand -base64 6 | tr -dc 'a-zA-Z0-9' | head -c 6)"
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
echo -e "  ${BOLD}Instalador Automático v2 — Sistema Medussa / MegaISP${RESET}"
echo -e "  ${YELLOW}Entorno de pruebas / development${RESET}\n"
echo -e "  Directorio  : ${CYAN}${APP_DIR}${RESET}"
echo -e "  URL acceso  : ${CYAN}${APP_URL}${RESET}"
echo -e "  Base datos  : ${CYAN}${DB_NAME}${RESET}"
echo ""

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
step "PASO 1/10 — Actualizando el sistema"
if [ "$PKG_MGR" = "apt" ]; then
    export DEBIAN_FRONTEND=noninteractive
    # Agregar DNS por si no resuelve
    grep -q "8.8.8.8" /etc/resolv.conf || echo "nameserver 8.8.8.8" >> /etc/resolv.conf
    grep -q "8.8.4.4" /etc/resolv.conf || echo "nameserver 8.8.4.4" >> /etc/resolv.conf
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
step "PASO 2/10 — Instalando dependencias base"
if [ "$PKG_MGR" = "apt" ]; then
    apt-get install -y -qq \
        curl wget git unzip zip openssl \
        software-properties-common ca-certificates gnupg lsb-release apt-transport-https
else
    dnf install -y -q curl wget git unzip zip openssl ca-certificates gnupg
fi
success "Dependencias base instaladas"

# ─────────────────────────────────────────────────────────
#  PASO 3 — PHP 8.2 + EXTENSIONES
# ─────────────────────────────────────────────────────────
step "PASO 3/10 — Instalando PHP ${PHP_VERSION}"

if [ "$PKG_MGR" = "apt" ]; then
    # Detener Apache si existe (usaremos Nginx)
    systemctl stop apache2 2>/dev/null || true
    systemctl disable apache2 2>/dev/null || true

    if ! php${PHP_VERSION} --version &>/dev/null 2>&1; then
        info "Agregando repositorio PHP Sury..."
        add-apt-repository -y ppa:ondrej/php 2>/dev/null || {
            curl -sSL https://packages.sury.org/php/apt.gpg \
                | gpg --dearmor -o /usr/share/keyrings/sury-php.gpg
            echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(lsb_release -cs) main" \
                > /etc/apt/sources.list.d/sury-php.list
            apt-get update -qq
        }
    fi
    # Instalar sin php-imagick (no disponible en todos los repos)
    apt-get install -y -qq \
        php${PHP_VERSION} php${PHP_VERSION}-cli php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-mysql php${PHP_VERSION}-xml php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-gd \
        php${PHP_VERSION}-bcmath php${PHP_VERSION}-intl php${PHP_VERSION}-soap \
        php${PHP_VERSION}-sockets php${PHP_VERSION}-tokenizer php${PHP_VERSION}-fileinfo
    # Intentar imagick por separado (no crítico)
    apt-get install -y -qq php${PHP_VERSION}-imagick 2>/dev/null || \
        warn "php-imagick no disponible — se omite (no crítico)"
else
    dnf install -y -q epel-release
    dnf install -y -q "https://rpms.remirepo.net/enterprise/remi-release-8.rpm" 2>/dev/null || true
    dnf module reset php -y -q
    dnf module enable php:remi-8.2 -y -q
    dnf install -y -q \
        php php-cli php-fpm php-mysqlnd php-xml php-mbstring \
        php-curl php-zip php-gd php-bcmath php-intl php-soap php-sockets
fi

systemctl enable --now php${PHP_VERSION}-fpm 2>/dev/null || systemctl enable --now php-fpm

# Composer
if ! command -v composer &>/dev/null; then
    info "Instalando Composer..."
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm /tmp/composer-setup.php
fi
success "PHP ${PHP_VERSION} + Composer instalados"

# ─────────────────────────────────────────────────────────
#  PASO 4 — NODE.JS 20
# ─────────────────────────────────────────────────────────
step "PASO 4/10 — Instalando Node.js ${NODE_VERSION}"

CURRENT_NODE="$(node --version 2>/dev/null | sed 's/v//' | cut -d. -f1 || echo 0)"
if [ "$CURRENT_NODE" -lt "$NODE_VERSION" ] 2>/dev/null; then
    if [ "$PKG_MGR" = "apt" ]; then
        curl -fsSL "https://deb.nodesource.com/setup_${NODE_VERSION}.x" | bash - 2>&1 | tail -3
        apt-get install -y -qq nodejs
    else
        curl -fsSL "https://rpm.nodesource.com/setup_${NODE_VERSION}.x" | bash - 2>&1 | tail -3
        dnf install -y -q nodejs
    fi
fi
success "Node.js $(node --version) / npm $(npm --version)"

# ─────────────────────────────────────────────────────────
#  PASO 5 — MARIADB + BASE DE DATOS LIMPIA
# ─────────────────────────────────────────────────────────
step "PASO 5/10 — Instalando MariaDB"

if [ "$PKG_MGR" = "apt" ]; then
    apt-get install -y -qq mariadb-server mariadb-client
else
    dnf install -y -q mariadb-server mariadb
fi

systemctl enable --now mariadb

info "Creando base de datos LIMPIA..."
mysql -u root <<EOSQL
DROP DATABASE IF EXISTS \`${DB_NAME}\`;
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS '${DB_USER}'@'localhost';
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOSQL
success "MariaDB listo — DB: ${DB_NAME}"

# ─────────────────────────────────────────────────────────
#  PASO 6 — NGINX (reemplaza Apache)
# ─────────────────────────────────────────────────────────
step "PASO 6/10 — Instalando Nginx"

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

if [ "$PKG_MGR" = "dnf" ]; then
    grep -q "sites-enabled" /etc/nginx/nginx.conf || \
        sed -i '/http {/a\    include /etc/nginx/sites-enabled/*.conf;' /etc/nginx/nginx.conf
fi

nginx -t 2>&1 && systemctl reload nginx
success "Nginx configurado"

# ─────────────────────────────────────────────────────────
#  PASO 7 — CLONAR REPOSITORIO
# ─────────────────────────────────────────────────────────
step "PASO 7/10 — Clonando repositorio"

[ -d "$APP_DIR" ] && rm -rf "$APP_DIR"
git clone "$REPO_URL" "$APP_DIR"
success "Repositorio clonado"

# ─────────────────────────────────────────────────────────
#  PASO 8 — CREAR ARCHIVO ARTISAN (falta en el repo)
# ─────────────────────────────────────────────────────────
step "PASO 8/10 — Verificando archivo artisan"

if [ ! -f "$APP_DIR/artisan" ]; then
    warn "Archivo artisan no encontrado en el repo — creando..."
    cat > "$APP_DIR/artisan" <<'ARTISAN'
#!/usr/bin/env php
<?php

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$status = $kernel->handle(
    $input = new Symfony\Component\Console\Input\ArgvInput,
    new Symfony\Component\Console\Output\ConsoleOutput
);

$kernel->terminate($input, $status);

exit($status);
ARTISAN
    chmod +x "$APP_DIR/artisan"
    success "Archivo artisan creado"
else
    success "Archivo artisan encontrado"
fi

# ─────────────────────────────────────────────────────────
#  PASO 9 — CONFIGURAR LARAVEL
# ─────────────────────────────────────────────────────────
step "PASO 9/10 — Configurando Laravel"

cd "$APP_DIR"

# Limpiar credenciales de producción del .env.example
sed -i \
    -e 's|^APP_KEY=.*|APP_KEY=|' \
    -e 's|^DB_HOST=.*|DB_HOST=127.0.0.1|' \
    -e 's|^DB_DATABASE=.*|DB_DATABASE=|' \
    -e 's|^DB_USERNAME=.*|DB_USERNAME=|' \
    -e 's|^DB_PASSWORD=.*|DB_PASSWORD=|' \
    .env.example

cp .env.example .env

sed -i \
    -e "s|^APP_ENV=.*|APP_ENV=local|" \
    -e "s|^APP_DEBUG=.*|APP_DEBUG=true|" \
    -e "s|^APP_URL=.*|APP_URL=${APP_URL}|" \
    -e "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" \
    -e "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" \
    .env

# Composer
info "Ejecutando composer install..."
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --optimize-autoloader --no-dev 2>&1 | tail -5

# Crear estructura de storage necesaria
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/logs
mkdir -p bootstrap/cache

# APP_KEY
php artisan key:generate --force
php artisan storage:link --force 2>/dev/null || true

# Migraciones
info "Ejecutando migraciones..."
php artisan migrate --force 2>&1 | tail -20 \
    && success "Migraciones completadas" \
    || warn "Algunas migraciones fallaron — se continúa"

# Agregar columna active si no existe (corrección conocida)
info "Verificando columna 'active' en users..."
mysql -u root "$DB_NAME" -e "
    SELECT COUNT(*) INTO @col_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = '${DB_NAME}'
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'active';
    SET @sql = IF(@col_exists = 0,
        'ALTER TABLE users ADD COLUMN active tinyint(1) NOT NULL DEFAULT 1',
        'SELECT 1'
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
" 2>/dev/null && success "Columna active verificada" || warn "No se pudo verificar columna active"

# Crear usuario administrador
info "Creando usuario administrador..."
ADMIN_PASS_B64="$(echo -n "${ADMIN_PASS}" | base64)"
mysql -u root "$DB_NAME" -e "
INSERT INTO users (name, email, login_user, password, active, is_seller, created_at, updated_at)
VALUES ('Administrador', 'admin@megaisp.local', 'admin', '${ADMIN_PASS_B64}', 1, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    password = '${ADMIN_PASS_B64}',
    active = 1;
" 2>/dev/null && success "Usuario admin creado" || warn "No se pudo crear usuario admin automáticamente"

# Asignar rol DESARROLLADOR al admin
php artisan tinker --execute="
try {
    \$user = \App\Models\User::where('login_user', 'admin')->first();
    if(\$user) {
        \$user->syncRoles(['DESARROLLADOR']);
        \$user->givePermissionTo(\Spatie\Permission\Models\Permission::all());
        echo 'Permisos asignados: ' . \$user->getAllPermissions()->count();
    }
} catch(\Exception \$e) { echo 'Warning permisos: ' . \$e->getMessage(); }
" 2>/dev/null || warn "Asignar permisos manualmente después"

# Frontend
info "Instalando npm (puede tardar varios minutos)..."
npm install --legacy-peer-deps --silent 2>&1 | tail -3

info "Compilando assets..."
npx mix --production 2>&1 | tail -5

# Permisos
WEB_USER="www-data"
[ "$PKG_MGR" = "dnf" ] && WEB_USER="nginx"
chown -R "${WEB_USER}:${WEB_USER}" "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# Caché
php artisan config:cache
php artisan route:cache
php artisan view:cache

success "Laravel configurado"

# ─────────────────────────────────────────────────────────
#  PASO 10 — REINICIAR SERVICIOS
# ─────────────────────────────────────────────────────────
step "PASO 10/10 — Reiniciando servicios"

systemctl restart mariadb
systemctl restart nginx
systemctl restart php${PHP_VERSION}-fpm 2>/dev/null || systemctl restart php-fpm

success "Todos los servicios activos"

# ─────────────────────────────────────────────────────────
#  GUARDAR CREDENCIALES
# ─────────────────────────────────────────────────────────
cat > "$CREDS_FILE" <<CREDS
════════════════════════════════════════════════
  MegaISP — Credenciales de instalación
  Fecha    : $(date '+%Y-%m-%d %H:%M:%S')
  Servidor : ${SERVER_IP}
════════════════════════════════════════════════

  URL del sistema  :  ${APP_URL}
  Directorio app   :  ${APP_DIR}

  ── Acceso al sistema ──
  Usuario          :  admin
  Contraseña       :  ${ADMIN_PASS}

  ── Base de datos ──
  Base de datos    :  ${DB_NAME}
  Usuario DB       :  ${DB_USER}
  Contraseña DB    :  ${DB_PASS}

  Guarda este archivo en lugar seguro.
  Para eliminarlo: rm ${CREDS_FILE}
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
echo -e "  🌐  Sistema en        →  ${BOLD}${CYAN}${APP_URL}${RESET}"
echo -e "  👤  Usuario           →  ${CYAN}admin${RESET}"
echo -e "  🔐  Contraseña        →  ${CYAN}${BOLD}${ADMIN_PASS}${RESET}"
echo -e "  🔑  Credenciales      →  ${CYAN}${CREDS_FILE}${RESET}"
echo ""
echo -e "  ${YELLOW}⚠️  Pendiente:${RESET}"
echo -e "     • Agrega tu Google Maps API Key en ${APP_DIR}/.env"
echo -e "       Variable: MIX_VUE_APP_GOOGLEMAPS_KEY"
echo ""
echo -e "  ${CYAN}Comandos útiles:${RESET}"
echo -e "     Actualizar sistema →  cd ${APP_DIR} && git pull && php artisan migrate --force"
echo -e "     Logs Laravel       →  tail -f ${APP_DIR}/storage/logs/laravel.log"
echo -e "     Limpiar caché      →  cd ${APP_DIR} && php artisan optimize:clear"
echo ""

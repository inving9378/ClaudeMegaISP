#!/usr/bin/env bash
# Item roadmap #144 — Portal Cliente en su dominio dedicado, DEV primero.
#
# Genera un certificado LOCAL (autofirmado, mismo patrón que setup-https-dev.sh
# del item #139) e instala el vhost de deploy/nginx-portal-dev.conf para que
# portal.meganet.mx sirva el Portal Cliente en la raíz (sin el prefijo /portal).
#
# Esto es DEV: no toca DNS público ni pide certificado de CA pública. Para que
# resuelva el hostname sin DNS real, agrega en /etc/hosts de la máquina desde la
# que navegas (tu laptop, NO el servidor):
#   192.168.105.11  portal.meganet.mx
#
# Uso (requiere root — lo corre Irving una sola vez):
#   sudo bash deploy/setup-portal-dev.sh
#
# Idempotente: si el certificado ya existe no se regenera; el server block se
# reescribe siempre (seguro, no pisa megaisp.conf ni el 80 default_server).

set -euo pipefail

if [[ ${EUID} -ne 0 ]]; then
  echo "Este script debe correr como root: sudo bash deploy/setup-portal-dev.sh" >&2
  exit 1
fi

DOMAIN="${PORTAL_DOMAIN:-portal.meganet.mx}"
CERT_DIR="/etc/nginx/ssl-dev"
DOCROOT="/var/www/megaisp/public"
SITE_NAME="megaisp-portal-dev"
SITE_AVAILABLE="/etc/nginx/sites-available/${SITE_NAME}.conf"
SITE_ENABLED="/etc/nginx/sites-enabled/${SITE_NAME}.conf"

mkdir -p "${CERT_DIR}"

if [[ -f "${CERT_DIR}/portal.pem" && -f "${CERT_DIR}/portal-key.pem" ]]; then
  echo "Certificado ya existe en ${CERT_DIR} — no se regenera (borra los archivos si quieres uno nuevo)."
else
  echo "Generando autofirmado con openssl para ${DOMAIN} (el navegador pide aceptar la advertencia una sola vez)."
  openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
    -keyout "${CERT_DIR}/portal-key.pem" \
    -out "${CERT_DIR}/portal.pem" \
    -subj "/CN=${DOMAIN}" \
    -addext "subjectAltName=DNS:${DOMAIN}"
  chmod 600 "${CERT_DIR}/portal-key.pem"
  chmod 644 "${CERT_DIR}/portal.pem"
fi

cat > "${SITE_AVAILABLE}" <<EOF
# Portal Cliente — dominio dedicado (item roadmap #144), generado por deploy/setup-portal-dev.sh.
# Cert local autofirmado — SOLO DEV. Camino a prod: reemplazar por certbot cuando
# exista el registro A público (ver deploy/README-portal-dev.md).
server {
    listen 80;
    listen 443 ssl;
    server_name ${DOMAIN};

    root ${DOCROOT};
    index index.php index.html;
    charset utf-8;

    ssl_certificate ${CERT_DIR}/portal.pem;
    ssl_certificate_key ${CERT_DIR}/portal-key.pem;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 7200;
    }
    location ~ /\.(?!well-known).* { deny all; }
    client_max_body_size 2048M;
}
EOF

ln -sf "${SITE_AVAILABLE}" "${SITE_ENABLED}"

nginx -t
systemctl reload nginx

echo ""
echo "Listo. Agrega en el /etc/hosts de tu máquina (no del servidor):"
echo "  192.168.105.11  ${DOMAIN}"
echo "Luego prueba: https://${DOMAIN}/ (advertencia de certificado la primera vez, 'Avanzado' -> 'Continuar')."

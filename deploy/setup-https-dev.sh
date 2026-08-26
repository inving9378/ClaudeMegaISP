#!/usr/bin/env bash
# Item roadmap #139 — HTTPS local para la LAN de dev (192.168.105.11).
#
# Objetivo: servir https://192.168.105.11 con un certificado LOCAL (mkcert si está
# instalado, si no autofirmado con openssl) para que el navegador considere la página
# un "secure context" (isSecureContext=true) — desbloquea navigator.clipboard, Service
# Workers y demás APIs modernas. Caso motivador: el copiado en la terminal ttyd (proxy
# /ttyd/, ver server block) fallaba silenciosamente en HTTP puro.
#
# ⚠️ Esto es DISTINTO de deploy/nginx-dev-tls.conf (subdominio dev.meganett.com.mx):
# ese caso expone el circuito a un fetcher EN LA NUBE que exige CA pública (Let's
# Encrypt) y rechaza autofirmados. Este script es solo para el acceso LAN interno de
# Irving, donde un cert local (autofirmado o mkcert) es la solución correcta — el
# navegador solo pide aceptar una advertencia una vez (o ninguna, si usas mkcert -install
# en la MISMA máquina desde la que navegas).
#
# Uso (requiere root — lo corre Irving una sola vez):
#   sudo bash deploy/setup-https-dev.sh
#
# Idempotente: si el certificado ya existe no se regenera; el server block se
# reescribe siempre (seguro, no pisa megaisp.conf ni el 80 default_server).

set -euo pipefail

if [[ ${EUID} -ne 0 ]]; then
  echo "Este script debe correr como root: sudo bash deploy/setup-https-dev.sh" >&2
  exit 1
fi

LAN_IP="${LAN_IP:-192.168.105.11}"
CERT_DIR="/etc/nginx/ssl-dev"
DOCROOT="/var/www/megaisp/public"
SITE_NAME="megaisp-https-lan"
SITE_AVAILABLE="/etc/nginx/sites-available/${SITE_NAME}.conf"
SITE_ENABLED="/etc/nginx/sites-enabled/${SITE_NAME}.conf"

mkdir -p "${CERT_DIR}"

if [[ -f "${CERT_DIR}/dev.pem" && -f "${CERT_DIR}/dev-key.pem" ]]; then
  echo "Certificado ya existe en ${CERT_DIR} — no se regenera (borra los archivos si quieres uno nuevo)."
elif command -v mkcert >/dev/null 2>&1; then
  # Nota: mkcert genera el cert firmado por SU CA local, pero "-install" solo mete esa CA
  # en el trust store de ESTA máquina (el servidor). Como Irving navega desde OTRA
  # máquina (su laptop/PC), para cero-advertencia hay que copiar además la CA
  # ($(mkcert -CAROOT)/rootCA.pem) al equipo desde el que navega e importarla ahí.
  # Sin ese paso extra, el resultado es equivalente al autofirmado (misma advertencia).
  echo "mkcert detectado — generando cert para ${LAN_IP}..."
  mkcert -cert-file "${CERT_DIR}/dev.pem" -key-file "${CERT_DIR}/dev-key.pem" "${LAN_IP}" localhost 127.0.0.1
  chmod 600 "${CERT_DIR}/dev-key.pem"
  chmod 644 "${CERT_DIR}/dev.pem"
else
  echo "mkcert no está instalado — generando autofirmado con openssl (funciona igual; el navegador pide aceptar la advertencia una sola vez)."
  openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
    -keyout "${CERT_DIR}/dev-key.pem" \
    -out "${CERT_DIR}/dev.pem" \
    -subj "/CN=${LAN_IP}" \
    -addext "subjectAltName=IP:${LAN_IP},DNS:localhost,IP:127.0.0.1"
  chmod 600 "${CERT_DIR}/dev-key.pem"
  chmod 644 "${CERT_DIR}/dev.pem"
fi

cat > "${SITE_AVAILABLE}" <<EOF
# HTTPS local LAN (item roadmap #139) — generado por deploy/setup-https-dev.sh.
# Habilita isSecureContext/clipboard/Service Workers en https://${LAN_IP}.
# Cert local (mkcert o autofirmado) — SOLO para uso LAN interno, no exponer a internet
# (para eso ver deploy/README-dev-tls.md, que exige CA pública).
server {
    listen 443 ssl;
    server_name ${LAN_IP};

    root ${DOCROOT};
    index index.php index.html;
    charset utf-8;

    ssl_certificate ${CERT_DIR}/dev.pem;
    ssl_certificate_key ${CERT_DIR}/dev-key.pem;

    location /ttyd/ {
        proxy_pass         http://127.0.0.1:7681/;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade          \$http_upgrade;
        proxy_set_header   Connection       "upgrade";
        proxy_set_header   Host             \$host;
        proxy_set_header   X-Real-IP        \$remote_addr;
        proxy_set_header   X-Forwarded-For  \$proxy_add_x_forwarded_for;
        proxy_read_timeout 86400s;
    }

    location /evolution/ {
        proxy_pass         http://127.0.0.1:8080/;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade          \$http_upgrade;
        proxy_set_header   Connection       "upgrade";
        proxy_set_header   Host             \$host;
        proxy_set_header   X-Real-IP        \$remote_addr;
        proxy_set_header   X-Forwarded-For  \$proxy_add_x_forwarded_for;
        proxy_read_timeout 300s;
        client_max_body_size 50M;
    }

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
    proxy_read_timeout 7200;
}
EOF

ln -sf "${SITE_AVAILABLE}" "${SITE_ENABLED}"

nginx -t
systemctl reload nginx

echo ""
echo "Listo. Prueba: https://${LAN_IP}/ (el navegador mostrará advertencia de certificado la primera vez;"
echo "'Avanzado' -> 'Continuar' basta para que isSecureContext=true funcione)."
echo "Para cero advertencia: en la máquina desde la que navegas, importa la CA de mkcert como raíz de"
echo "confianza (cópiala desde el servidor: \$(mkcert -CAROOT)/rootCA.pem) — ver deploy/README-https-dev.md."

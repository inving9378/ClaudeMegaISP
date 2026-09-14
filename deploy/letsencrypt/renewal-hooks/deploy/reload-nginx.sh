#!/bin/sh
# Deploy-hook de certbot para DEV (dev.meganett.com.mx) — item #9991083.
#
# certbot ejecuta TODO lo que haya en /etc/letsencrypt/renewal-hooks/deploy/ SOLO después
# de una renovación exitosa (nunca en un `renew` que no renueva nada ni en --dry-run salvo
# con --run-deploy-hooks). Sin esto, la renovación del 2026-09-11 dejó cert2.pem en disco
# pero nginx siguió sirviendo cert1.pem (el proceso solo lee el .pem al arrancar/recargar)
# → el banner de la Torre siguió contando los días del cert viejo.
#
# Instalación (root): ver deploy/README-dev-tls.md → "Renovación automática + recarga".
# Es nginx quien sirve :443 en dev (apache2 está inactivo) — NO `reload apache2`.
set -e
if nginx -t >/dev/null 2>&1; then
    systemctl reload nginx
    logger -t certbot-deploy-hook "dev.meganett.com.mx renovado (${RENEWED_LINEAGE:-?}): nginx recargado"
else
    logger -t certbot-deploy-hook "dev.meganett.com.mx renovado pero nginx -t FALLÓ: no se recargó, revisar a mano"
    exit 1
fi

# TLS para el Circuito (subdominio HTTPS) — runbook

El fetcher de Claude Cowork convierte todo a HTTPS y **rechaza** `http://` y los
certificados **autofirmados** (valida la cadena de una CA pública). Por eso el endpoint
`http://38.123.192.199/api/roadmap-externo/...` no le sirve: necesita **443 con un
certificado de CA válida** (Let's Encrypt).

> **Autofirmado: NO sirve.** Un fetcher en la nube no permite confiar en un cert propio;
> la conexión TLS fallaría en la validación de cadena. No hay atajo: se necesita un
> dominio que resuelva + Let's Encrypt. (Si el DNS tardara, la única alternativa real es
> el reto **DNS-01** de certbot —requiere control de la API DNS—, no un autofirmado.)

## Paso 1 — DNS (lo creas TÚ, Irving)

Crear un registro **A** en la zona que controles:

| Campo | Valor |
|-------|-------|
| Tipo | `A` |
| Host / nombre | `dev` (→ FQDN `dev.meganett.com.mx`) |
| Apunta a | `38.123.192.199` (IP pública de DEV) |
| TTL | `300` (5 min, para propagar rápido) |
| Proxy (si es Cloudflare) | **DNS only / grey cloud** (para que el reto HTTP-01 llegue al origen) |

- Confirmar que **`meganett.com.mx` es la zona real** que administras (o usar el subdominio que prefieras; si cambia, ajustar `server_name` en `deploy/nginx-dev-tls.conf`).
- Abrir el **puerto 443** hacia internet igual que el 80 (hoy `http://38.123.192.199` responde → 80 abierto; 443 debe abrirse en firewall/VLAN).

Verificar propagación:
```bash
dig +short dev.meganett.com.mx     # debe devolver 38.123.192.199
```

## Paso 2 — nginx + certbot (lo corro YO con sudo, cuando el DNS resuelva)

```bash
# a) Colocar el server block del subdominio (no toca el default_server / acceso LAN)
sudo cp /var/www/megaisp/deploy/nginx-dev-tls.conf /etc/nginx/sites-available/megaisp-dev-tls.conf
sudo ln -s /etc/nginx/sites-available/megaisp-dev-tls.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# b) Instalar certbot (no está instalado)
sudo apt-get update && sudo apt-get install -y certbot python3-certbot-nginx

# c) Emitir + instalar el certificado (HTTP-01; añade el bloque 443 + redirect al subdominio)
sudo certbot --nginx -d dev.meganett.com.mx --agree-tos -m <tu-correo> --redirect --non-interactive

# d) Renovación automática (certbot instala un systemd timer)
sudo certbot renew --dry-run
```

## Paso 3 — verificación

```bash
# Cadena TLS válida (sin -k) y respuesta del circuito por HTTPS:
curl -sI https://dev.meganett.com.mx/api/roadmap-externo/<READ_TOKEN> | head -1   # 200
```
Luego apuntar el fetcher de Cowork a `https://dev.meganett.com.mx/api/roadmap-externo/...`.

> Tras exponer HTTPS, aplicar el enmascarado del token en el access log y considerar
> rotar tokens: ver `docs/circuito-seguridad-tokens.md`.

## Renovación automática + recarga de nginx (item #9991083, 2026-09-14)

**Diagnóstico del 2026-09-14 (escenario E2):** el cert **sí** se renovó — el
2026-09-11 16:08 certbot (authenticator `webroot`, webroot `/var/www/megaisp/public`)
emitió `cert2.pem` y apuntó `live/dev.meganett.com.mx/*.pem` a él — pero **nginx nunca
recargó** (su worker seguía siendo el del 2026-09-01), así que `:443` siguió sirviendo
`cert1.pem` (Jul 10 → **Oct 8 2026**). El banner de la Torre lee el cert **en vivo**
(`EnvironmentHealthService::certificado()`, `ssl://127.0.0.1:443` con SNI, caché 30 s) y
por eso decía "24 días": no mentía, describía lo que nginx servía. `certbot.timer` está
activo y `certbot renew` corre dos veces al día (exit 0 en 2 s = "cert2 no toca aún").

Lo que faltaba es el **deploy-hook**: certbot no recarga el servidor web solo.

```bash
# 1) Instalar el hook versionado (corre SOLO tras una renovación exitosa, para cualquier cert)
sudo install -m 0755 /var/www/megaisp/deploy/letsencrypt/renewal-hooks/deploy/reload-nginx.sh \
     /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh

# 2) Recargar nginx AHORA para que tome el cert2.pem que ya está en disco (reload, no restart)
sudo nginx -t && sudo systemctl reload nginx

# 3) Verificar que :443 ya sirve el cert nuevo (notAfter ≈ 2026-12-10, 90 días desde el 09-11)
echo | openssl s_client -connect dev.meganett.com.mx:443 -servername dev.meganett.com.mx 2>/dev/null \
  | openssl x509 -noout -dates -issuer

# 4) Prueba en seco de la renovación (staging de Let's Encrypt, no consume cuota ni modifica nada)
sudo certbot renew --dry-run
```

Verificación del banner: `GET /api/roadmap/torre/salud-entorno` → `certificado.expira_at`
debe coincidir con el `notAfter` del paso 3 (a lo sumo 30 s de caché). Rollback del hook:
`sudo rm /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh` (no afecta la renovación en
sí, solo la recarga).

> Alternativa equivalente por cert: `renew_hook = systemctl reload nginx` bajo
> `[renewalparams]` en `/etc/letsencrypt/renewal/dev.meganett.com.mx.conf` (es lo que
> escribe `certbot renew --deploy-hook "…"`). Se prefiere el directorio de hooks porque
> sobrevive a que certbot reescriba el `.conf` y aplica a cualquier cert futuro del box.

> `public/.well-known/acme-challenge/` (webroot del reto HTTP-01, archivos root-owned) es
> untracked: en el checkout principal bloqueaba `syncCheckoutPrincipal()` del merge-runner
> (`git status --porcelain` no vacío). Quedó excluido localmente en `.git/info/exclude`; si
> se quiere permanente, agregarlo a `.gitignore` (ver item de respuesta del #9991083).

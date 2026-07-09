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

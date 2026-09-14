# nginx dev — gzip para JSON/texto de la API (item #9991136)

**Problema (medido 2026-09-14):** `/etc/nginx/nginx.conf` trae `gzip on;` pero `gzip_types`
comentado → solo se comprime `text/html`. El JSON de la API de la Torre viaja crudo:
`GET /api/roadmap/items` = **2,269,129 bytes (2.16 MB)** por carga; comprimido a nivel 4 pesa
**460,487 bytes (0.44 MB)**. Afecta a toda la API, no solo a la Hoja de ruta.

**Decisión de Irving:** gzip primero; la deuda estructural (contadores por agregados + lista
paginada, **#9991129**) se ejecuta solo cuando se cumpla su disparador (medido con
`php artisan roadmap:medir-hoja-de-ruta`, item #9991137).

## Instalación (root; sin downtime)

```bash
# 1) Drop-in versionado → conf.d (se incluye en el contexto http, después del bloque gzip)
sudo install -m 0644 /var/www/megaisp/deploy/nginx-gzip-dev.conf /etc/nginx/conf.d/gzip-dev.conf

# 2) V1 — probar la config ANTES de recargar. Si falla, NO recargar.
sudo nginx -t

# 3) Recargar (reload, no restart)
sudo systemctl reload nginx
```

## Validación

```bash
# V2/V3 — bytes con y sin Accept-Encoding + cabecera Content-Encoding (sin sesión el endpoint
# responde 401 JSON chico, <1024 bytes → NO se comprime por gzip_min_length; por eso se valida
# también con una respuesta grande pública: el bundle JS).
curl -s -o /dev/null -w "sin gzip:  %{size_download} bytes\n" https://dev.meganett.com.mx/js/app.js
curl -s -o /dev/null -H "Accept-Encoding: gzip" -w "con gzip:  %{size_download} bytes\n" https://dev.meganett.com.mx/js/app.js
curl -sI -H "Accept-Encoding: gzip" https://dev.meganett.com.mx/js/app.js | grep -i "content-encoding"

# Con sesión (navegador → DevTools → Network → /api/roadmap/items → Size vs Content):
# debe verse Content-Encoding: gzip y ~0.44 MB transferidos frente a ~2.16 MB de contenido.

# V5 — el tiempo de index() no cambia con gzip (es consulta+serialización, no transporte):
php artisan roadmap:medir-hoja-de-ruta
```

## Reversión (sin downtime)

```bash
sudo rm /etc/nginx/conf.d/gzip-dev.conf && sudo nginx -t && sudo systemctl reload nginx
```

> PROD (`192.168.105.108`) no se toca desde dev. Si algún día se quiere allá, es el mismo
> drop-in, instalado a mano en esa máquina por Irving.

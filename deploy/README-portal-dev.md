# Portal Cliente en su dominio dedicado — DEV primero (item roadmap #144)

## Decisión (aprobada por Irving, 2026-08-28)

- **Subdominio:** `portal.meganet.mx` (config `config/portalcliente.php` →
  `PORTAL_CLIENTE_DOMAIN` en `.env` si algún día cambia).
- **SSL:** Let's Encrypt vía certbot cuando exista DNS público — **en DEV, cert
  autofirmado** (no hay registro A público que resolver todavía).
- **Entorno de esta fase:** **solo DEV** (vhost local + hosts file / DNS
  interno). Producción queda fuera de alcance de este item — es la frontera
  dura de infra/DNS/SSL en el box de prod que siempre pasa por Irving
  directamente (ver checklist de CLAUDE.md, sección "Portal: subdominio +
  SSL").
- **Estructura del routing:** mismo Laravel, mismo `public/`, un solo bloque
  de rutas del módulo (`app/Modules/Addons/PortalCliente/routes.php`) montado
  dos veces: bajo el prefijo `/portal` (como siempre, nombres `portal.*`,
  intacto) y bajo `Route::domain(config('portalcliente.domain'))` sin prefijo
  (nombres `portal_domain.*`, nuevo) — así `portal.meganet.mx/facturas`
  resuelve al mismo controller que `/portal/facturas`, sin duplicar lógica.

## Por qué esto es distinto de los otros dos TLS que ya existen en el repo

- `deploy/nginx-dev-tls.conf` — subdominio `dev.meganett.com.mx` para exponer
  `/api/roadmap-externo` a un fetcher **en la nube** (exige CA pública, nunca
  autofirmado).
- `deploy/setup-https-dev.sh` — HTTPS para la **IP LAN** `192.168.105.11`
  (secure context para clipboard/Service Workers).
- **Este** (`deploy/nginx-portal-dev.conf` + `deploy/setup-portal-dev.sh`) — un
  **hostname nuevo** (`portal.meganet.mx`) que sirve el Portal Cliente en la
  raíz, para validar en DEV la experiencia de subdominio antes de pedir el
  registro DNS público real.

## Paso 1 — instalar el vhost + cert autofirmado (con sudo, lo corre Irving)

```bash
sudo bash deploy/setup-portal-dev.sh
```

Es idempotente: si el certificado ya existe no lo regenera; el server block se
reescribe siempre. No toca `megaisp.conf` ni el puerto 80 del `default_server`.

## Paso 2 — resolver el hostname sin DNS público

En el `/etc/hosts` de la máquina desde la que **navegas** (tu laptop/PC, NO el
servidor):

```
192.168.105.11  portal.meganet.mx
```

## Paso 3 — verificar

```bash
curl -sk -o /dev/null -w "%{http_code}\n" https://portal.meganet.mx/           # portal (dashboard o redirect a login)
curl -sk -o /dev/null -w "%{http_code}\n" https://portal.meganet.mx/login      # login del portal
curl -sk -o /dev/null -w "%{http_code}\n" https://portal.meganet.mx/facturas   # protegido → redirect a login si no hay sesión
```

En el navegador: `https://portal.meganet.mx/login` debe verse igual que
`http://192.168.105.11/portal/login` (mismo controller, misma vista).

## Camino a producción (fuera de alcance de este item)

Cuando Irving decida publicar de verdad:

1. Crear el registro **A** de `portal.meganet.mx` apuntando a la IP pública de
   prod (`.198`).
2. En el box de prod: `cp deploy/nginx-portal-dev.conf` a
   `sites-available/`, ajustar `root` si aplica, `nginx -t && systemctl reload
   nginx`.
3. `certbot --nginx -d portal.meganet.mx --agree-tos -m <correo> --redirect
   --non-interactive` (reemplaza el cert autofirmado por uno de CA pública;
   certbot añade el bloque 443 + redirect automáticamente).
4. Activar el webhook de OpenPay en su dashboard con la URL real (ver
   `OpenpayWebhookController` — ya listo, solo pendiente de URL pública).

Esto último es exactamente lo que el checklist de CLAUDE.md ("Portal:
subdominio + SSL") ya marca como acción manual de Irving en prod — este item
(#144) solo deja lista la parte de DEV + el código de routing por dominio.

## Rollback

```bash
sudo rm /etc/nginx/sites-enabled/megaisp-portal-dev.conf
sudo systemctl reload nginx
```

El acceso normal por `http://192.168.105.11/portal/*` nunca se toca — sigue
funcionando igual se active o revierta este vhost.

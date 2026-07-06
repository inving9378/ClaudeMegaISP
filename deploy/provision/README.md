# Provisioning de Evolution API (nativo, sin Docker)

`install.sh` idempotente que **replica la Evolution de dev** en un server nuevo
(modelo de arriendo) o en producción. Reproduce exactamente la receta auditada en
dev — **NO usa Docker**: Evolution corre como app Node bajo PM2, con MySQL como
backing y sin Redis.

## Qué instala / configura

| Componente | Detalle |
|---|---|
| Runtime | **Node 20.x** + **PM2** (fork_mode) |
| Evolution | **v2.3.7**, commit exacto **`fa09d378`**, en `/opt/evolution-api`, compilado a `dist/` |
| Backing | **MySQL** (el del sistema — NO se instala), DB `evolution_api` + user `evolution_user`, esquema vía **Prisma** (`migrate deploy`, provider mysql) |
| Cache | **Local en disco** (`CACHE_LOCAL_ENABLED=true`), **sin Redis** |
| Proxy | `location /evolution/` inyectado **dentro del vhost de MegaISP** → `http://127.0.0.1:8080/` con headers WebSocket |
| Boot | `pm2 startup` (systemd) + `pm2 save` |

**Fuera de alcance:** Asterisk (se maneja aparte, ya hay VoiceGateway). Hay un
`TODO` en `install.sh` marcando dónde engancharía un `50-asterisk.sh` futuro.

## Requisitos

- Correr **como root o con `sudo`** (necesita apt, `/opt`, mysql, nginx, systemd).
- **MySQL ya instalado y respondiendo** (no lo instala este script).
- El sistema **MegaISP** presente con su `.env` (fuente de los valores por-server).
- **NO correrlo en el server de dev** — el script lo detecta (hostname/APP_URL) y
  **aborta** para no romper la Evolution de dev que ya funciona.

## Uso

```bash
sudo bash deploy/provision/install.sh
```

Idempotente: correrlo 2 veces no rompe ni duplica nada. Cada paso registra
`OMITIDO (ya existe)` o `INSTALANDO...`.

### Overrides opcionales (variables de entorno)

| Variable | Default | Para qué |
|---|---|---|
| `MEGAISP_ROOT` | `/var/www/megaisp` | Raíz del sistema MegaISP |
| `MEGAISP_USER` | `www-data` | Usuario que corre `php artisan` (dueño de la caché) |
| `EVOLUTION_USER` | `evolution` (usuario de sistema dedicado, se crea solo) | Dueño/ejecutor de Evolution |
| `NGINX_VHOST` | autodetectado | Forzar el vhost si la autodetección falla |

## Valores que el script deriva/genera por server

1. **`SERVER_URL`** — se **deriva** de `APP_URL` del `.env` de MegaISP + `/evolution`.
2. **`API_KEY`** — se **genera** aleatoria (`openssl rand -hex 32`) **solo si no existe**.
   Se escribe con el MISMO valor en:
   - `AUTHENTICATION_API_KEY` del `.env` de Evolution, y
   - `WHATSAPP_API_KEY` del `.env` de MegaISP (para que el panel coincida).
   Se imprime **una sola vez** al final para guardarla.

El password de MySQL de Evolution también se genera (hex) y se preserva en el
`.env` de Evolution entre corridas (no se rota).

## Idempotencia (cómo detecta y omite)

- **Node**: si ya está en 20.x → omite.
- **PM2**: si el binario ya existe → omite.
- **Código Evolution**: si `/opt/evolution-api` ya está en `fa09d378` → no re-clona;
  compila solo si falta `dist/main.js`.
- **`.env` de Evolution**: si existe → **no se sobrescribe** (preserva key/password).
- **DB/usuario**: `CREATE ... IF NOT EXISTS` (no rota el password de un user existente);
  `migrate deploy` corre siempre (Prisma lo hace idempotente).
- **nginx**: si el vhost ya tiene `location /evolution/` → omite; si inyecta y
  `nginx -t` falla → **revierte** el vhost desde el backup.
- **PM2 boot**: si el proceso ya existe → omite `start`; si el servicio systemd ya
  está → omite `startup`.

## Cómo revertir (manual)

```bash
# 1) Detener y borrar el proceso PM2
pm2 delete evolution-api && pm2 save

# 2) Quitar el location del vhost (hay backup .provision.bak junto al vhost)
#    p.ej.: cp /etc/nginx/sites-available/megaisp.conf.provision.bak <vhost> && nginx -t && systemctl reload nginx

# 3) (opcional) Borrar código y datos
sudo rm -rf /opt/evolution-api
mysql -e "DROP DATABASE IF EXISTS evolution_api; DROP USER IF EXISTS 'evolution_user'@'127.0.0.1','evolution_user'@'localhost';"

# 4) Restaurar el .env del sistema si hiciera falta (backup .provision.bak)
#    cp /var/www/megaisp/.env.provision.bak /var/www/megaisp/.env
```

## Estructura

```
deploy/provision/
├── install.sh              # orquestador (preflight → 10 → 20 → 30 → 40 → cierre)
├── lib/
│   ├── common.sh           # helpers, guardas, lectura del .env del sistema
│   ├── 05-evolution-user.sh # usuario de sistema dedicado 'evolution' (home, sin login)
│   ├── 10-node-pm2.sh      # Node 20 + PM2
│   ├── 20-evolution.sh     # clone fa09d378 + build + .env + DB + Prisma
│   ├── 30-nginx.sh         # inyecta location /evolution/
│   └── 40-pm2-boot.sh      # pm2 start + save + startup
└── templates/
    └── evolution.env.tpl   # plantilla del .env con {{PLACEHOLDERS}}
```

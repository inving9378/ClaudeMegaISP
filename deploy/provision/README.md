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
| Proxy | `/evolution/` **dentro del vhost de MegaISP** → `http://127.0.0.1:8080/` con WebSocket. **nginx** (`location`) o **Apache** (`ProxyPass … upgrade=websocket`, o `RewriteRule [P]` a `ws://` en Apache < 2.4.47); se autodetecta |
| Boot | `pm2 startup` (systemd) + `pm2 save` |

**Fuera de alcance:** Asterisk (se maneja aparte, ya hay VoiceGateway). Hay un
`TODO` en `install.sh` marcando dónde engancharía un `50-asterisk.sh` futuro.

## Requisitos

- Correr **como root o con `sudo`** (necesita apt, `/opt`, mysql, nginx/Apache, systemd).
- **MySQL ya instalado y respondiendo** (no lo instala este script).
- El sistema **MegaISP** presente con su `.env` (fuente de los valores por-server).
- **NO correrlo en el server de dev** — el script lo detecta (hostname/APP_URL) y
  **aborta** para no romper la Evolution de dev que ya funciona.

## Uso

```bash
sudo bash deploy/provision/install.sh
```

Con overrides, **pásalos después de `sudo` con `env`** — `sudo` con `env_reset` descarta las
variables puestas antes (`ALLOW_PROVISION=1 sudo bash …` NO llega al script):

```bash
sudo env ALLOW_PROVISION=1 MEGAISP_ROOT=/var/www/ClaudeMegaISP bash deploy/provision/install.sh
```

Idempotente: correrlo 2 veces no rompe ni duplica nada. Cada paso registra
`OMITIDO (ya existe)` o `INSTALANDO...`.

### Overrides opcionales (variables de entorno)

| Variable | Default | Para qué |
|---|---|---|
| `MEGAISP_ROOT` | `/var/www/megaisp` | Raíz del sistema MegaISP |
| `MEGAISP_USER` | `www-data` | Usuario que corre `php artisan` (dueño de la caché) |
| `EVOLUTION_USER` | `evolution` (usuario de sistema dedicado, se crea solo) | Dueño/ejecutor de Evolution |
| `NGINX_VHOST` | autodetectado | Forzar el vhost nginx si la autodetección falla |
| `APACHE_VHOST` | autodetectado (`sites-enabled` con `DocumentRoot <MEGAISP_ROOT>/public` exacto) | Forzar el vhost Apache |
| `WEB_SERVER` | autodetectado (servicio activo) | `nginx` o `apache`; obligatorio si ambos están activos |
| `SKIP_WEB_PROXY` | (sin fijar) | `1` = plan B: omite el proxy `/evolution/`. MegaISP sigue funcionando (habla con Evolution por `127.0.0.1:8080`); solo no queda publicada en `<APP_URL>/evolution` |
| `ALLOW_PROVISION` | (sin fijar) | `1` = salta SOLO el check de hostname (prod comparte `meganet` con dev); el check de `APP_URL` sigue activo |
| `EVOLUTION_FORCE_ROTATE_DB_PASSWORD` | (sin fijar) | Rota el password MySQL de `evolution_user` cuando se detecta un desajuste `.env`↔MySQL (ver caso borde abajo) |

## Valores que el script deriva/genera por server

1. **`SERVER_URL`** — se **deriva** de `APP_URL` del `.env` de MegaISP + `/evolution`.
2. **`API_KEY`** — se **genera** aleatoria (`openssl rand -hex 32`) **solo si no existe**.
   Se escribe con el MISMO valor en:
   - `AUTHENTICATION_API_KEY` del `.env` de Evolution, y
   - `WHATSAPP_API_KEY` del `.env` de MegaISP (para que el panel coincida).
   Se imprime **una sola vez** al final para guardarla.

El password de MySQL de Evolution también se genera (hex) y se preserva en el
`.env` de Evolution entre corridas (no se rota).

**Caso borde — `.env` borrado/regenerado a mano:** si se borra el `.env` de
Evolution y se re-corre `install.sh`, se genera un `DB_PASSWORD` **nuevo**, pero
`CREATE USER IF NOT EXISTS` **no** actualiza el password de un usuario MySQL que
ya existe → desajuste `.env`↔MySQL (Evolution no podría conectar a la BD). El
script lo **detecta antes de tocar nada** y por default **aborta** con un mensaje
explicando las dos salidas: restaurar el `.env`/password viejo, o re-correr con
`EVOLUTION_FORCE_ROTATE_DB_PASSWORD=1` para rotar el password de MySQL a juego
con el `.env` nuevo (rotación explícita, nunca automática/silenciosa).

## Idempotencia (cómo detecta y omite)

- **Node**: si ya está en 20.x → omite.
- **PM2**: si el binario ya existe → omite.
- **Código Evolution**: si `/opt/evolution-api` ya está en `fa09d378` → no re-clona;
  compila solo si falta `dist/main.js`.
- **`.env` de Evolution**: si existe → **no se sobrescribe** (preserva key/password).
- **DB/usuario**: `CREATE ... IF NOT EXISTS` (no rota el password de un user existente).
  Si el `.env` se regeneró y el user MySQL ya existía → **aborta** con mensaje claro
  (ver caso borde arriba), salvo `EVOLUTION_FORCE_ROTATE_DB_PASSWORD=1`.
  `migrate deploy` corre siempre (Prisma lo hace idempotente).
- **nginx**: si el vhost ya tiene `location /evolution/` → omite; si inyecta y
  `nginx -t` falla → **revierte** el vhost desde el backup.
- **Apache**: inyecta en **todos** los `<VirtualHost>` habilitados cuyo `DocumentRoot` es
  exactamente `<MEGAISP_ROOT>/public` (p. ej. `:80` y `:443`); si el archivo ya trae el marcador
  `# >>> evolution-api` o un `ProxyPass /evolution/` → omite. Respaldo
  `<vhost>.provision.<fecha>.bak`; si `apache2ctl configtest` falla → **restaura** los vhosts,
  **deshabilita** los módulos que habilitó y **no** recarga Apache.
- **PM2 boot**: si el proceso ya existe → omite `start`; si el servicio systemd ya
  está → omite `startup`.

## Cómo revertir (manual)

```bash
# 1) Detener y borrar el proceso PM2
pm2 delete evolution-api && pm2 save

# 2) Quitar el proxy del vhost (hay backup junto al vhost)
#    nginx : cp /etc/nginx/sites-available/megaisp.conf.provision.bak <vhost> && nginx -t && systemctl reload nginx
#    Apache: cp <vhost>.provision.<fecha>.bak <vhost> && apache2ctl configtest && systemctl reload apache2
#            (o borrar el bloque entre "# >>> evolution-api" y "# <<< evolution-api <<<")

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
│   ├── 30-nginx.sh         # nginx: inyecta location /evolution/
│   ├── 30-apache.sh        # Apache: inyecta ProxyPass /evolution/ (+ módulos)
│   ├── 30-proxy.sh         # elige nginx/Apache (o SKIP_WEB_PROXY) y despacha
│   └── 40-pm2-boot.sh      # pm2 start + save + startup
└── templates/
    └── evolution.env.tpl   # plantilla del .env con {{PLACEHOLDERS}}
```

## Runbook — PRODUCCIÓN (Apache, `/var/www/ClaudeMegaISP`) — item #9991239

> El circuito y el server de dev **no** ejecutan nada de esto: dev (.11) no llega a prod. Lo corre
> una persona con root en una sesión **directamente en .108**. Requisito: que el release
> desplegado en prod ya incluya este cambio (`ls /var/www/ClaudeMegaISP/deploy/provision/lib/30-apache.sh`
> debe existir); si no, el script viejo muere en el paso de nginx.

### 0. Bitácora PREVIA (regla de CLAUDE.md — antes de tocar nada)

Crear y commitear en el repo `docs/bitacora/<AAAA-MM-DD>-evolution-prod.md` **antes** de correr:

```markdown
## <AAAA-MM-DD HH:MM> — Instalación de Evolution API en PROD (item #9991239)
- Quién lo corre: <persona>
- Servidor: 192.168.105.108, MegaISP en /var/www/ClaudeMegaISP, BD meganet_prod_claude (NO se toca)
- Comando: sudo env ALLOW_PROVISION=1 MEGAISP_ROOT=/var/www/ClaudeMegaISP bash deploy/provision/install.sh
- Qué escribe: /opt/evolution-api, BD nueva evolution_api + usuario evolution_user, vhost Apache de
  MegaISP (bloque /evolution/), módulos proxy/proxy_http, unit systemd pm2-evolution, y
  WHATSAPP_API_KEY en el .env de MegaISP
- Respaldo tomado antes: /root/pre-evolution-<fecha>.tgz (/etc/apache2 + .env de MegaISP)
- Resultado: <se completa al terminar>
```

### 1. Revisiones previas (solo lectura)

```bash
cd /var/www/ClaudeMegaISP
hostname; grep -E '^APP_URL=' .env               # APP_URL debe ser la de prod, no 192.168.105.11
systemctl is-active apache2 nginx                 # se espera: active / inactive
grep -rl 'DocumentRoot.*/var/www/ClaudeMegaISP/public' /etc/apache2/sites-enabled/
apache2ctl -v                                     # >= 2.4.47 usa upgrade=websocket
ss -ltnp | grep ':8080 ' || echo "8080 libre"     # Evolution necesita el 8080
grep -E '^WHATSAPP_(FAKE|API_URL)=' .env          # FAKE debe quedar false o ausente
```

### 2. Respaldo

```bash
sudo tar czf /root/pre-evolution-$(date +%F).tgz /etc/apache2 /var/www/ClaudeMegaISP/.env
```

### 3. Instalar

```bash
cd /var/www/ClaudeMegaISP
sudo env ALLOW_PROVISION=1 MEGAISP_ROOT=/var/www/ClaudeMegaISP bash deploy/provision/install.sh
```

Al final imprime la **API key una sola vez**: guárdala en el gestor de contraseñas. **No** la
pegues en la bitácora, en el chat ni en un archivo de log (por eso no se usa `tee`).

**Plan B** si el paso de Apache aborta (revirtió solo, Apache sigue sano): re-correr con
`SKIP_WEB_PROXY=1` para que al menos termine el paso 40 (Evolution corriendo y con arranque en
boot); el proxy se revisa después con calma.

### 4. Verificar

```bash
sudo -u evolution -H pm2 ls                                   # evolution-api online
systemctl is-enabled pm2-evolution                             # enabled (arranca en boot)
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080  # 200
curl -s -o /dev/null -w '%{http_code}\n' -H "Host: <dominio de prod>" http://127.0.0.1/evolution/
# 200 = el proxy llega a Evolution (301 si el :80 redirige a HTTPS; 404/503 = revisar el vhost)
```

En MegaISP: **WhatsApp → Líneas** sin el aviso rojo de "Evolution no está instalada" (item
#9991240) → «Conectar / Ver QR» en la línea.

### 5. Cerrar la bitácora

Completar "Resultado" en el archivo del paso 0 (sin la API key) y commitear.

### Revertir

Ver "Cómo revertir" arriba; el respaldo del paso 2 restaura Apache y el `.env` de MegaISP.

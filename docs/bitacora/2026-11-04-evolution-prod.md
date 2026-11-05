## 2026-11-04 12:19 — Instalación de Evolution API en PROD desde la página de add-ons (bitácora PREVIA)

Escrita ANTES de pulsar el botón (regla de CLAUDE.md: ninguna escritura manual en producción sin
bitácora previa). El mecanismo está en V1.70-03.11.2026 (ver `2026-11-03-release-v170.md` y
`2026-11-03-dependencias-sistema-addons.md`).

- **Quién lo corre:** el usuario de la sesión de Claude Code de dev (terminal con login
  `david_marsal`, persona declarada `irving`), desde la página de add-ons de prod con su sesión de
  MegaISP. La contraseña que pide el modal la escribe esa misma persona; no pasa por Claude ni se
  guarda en disco, BD ni log.
- **Servidor:** PROD 192.168.105.108 (`https://megaisp.meganett.com.mx`), MegaISP en
  `/var/www/ClaudeMegaISP`. La BD de MegaISP (`meganet_prod_claude`) **no se toca**.
- **Acción:** Configuración → Add-ons → WhatsApp Agent → Dependencias del servidor → Instalar
  dependencias (o «Instalar» si el add-on aún no está instalado: corre el script y, si termina
  bien, instala el módulo). Corre `app/Modules/Addons/WhatsAppAgent/system/install.sh` →
  `deploy/provision/install.sh` como root (`ALLOW_PROVISION=1 PROVISION_HIDE_KEY=1`).
- **Qué escribe:**
  - usuario de sistema `evolution`;
  - Node solo si falta o es < 20 (instala 24 vía NodeSource) y PM2 global;
  - `/opt/evolution-api` (clon de GitHub, v2.3.7 commit `fa09d378`) + su `.env`;
  - BD nueva `evolution_api` y usuario MySQL `evolution_user` (en el mismo MySQL);
  - bloque `/evolution/` en el vhost Apache de MegaISP + módulos `proxy`/`proxy_http`
    (`systemctl reload`, no restart);
  - proceso PM2 `evolution-api` y unidad systemd `pm2-evolution`;
  - `WHATSAPP_API_KEY` (y `WHATSAPP_API_URL` solo si está vacío) en el `.env` de MegaISP, luego
    `config:clear` + `queue:restart`.
- **Respaldos:** los que hace el instalador antes de tocar cada archivo: vhost →
  `<vhost>.provision.<fecha>.bak` (si `apache2ctl configtest` falla, restaura y no recarga);
  `.env` de MegaISP → `.env.provision.bak`. Ninguna tabla existente se modifica.
- **Prueba del mecanismo antes:** `addon-demo` en prod — instalado desde la página (pidió
  contraseña en el modal, la aceptó, instaló) y quitado después. Reportado por el usuario.
- **Comprobado desde dev (solo lectura, 2026-11-04):** prod responde por HTTPS con certificado
  válido (Let's Encrypt, `megaisp.meganett.com.mx`, vence 2027-01-09). Por HTTP también responde
  sin redirigir: la contraseña se escribe entrando por `https://`.
- **Riesgo conocido:** el instalador reemplaza `WHATSAPP_API_KEY` pero conserva un
  `WHATSAPP_API_URL` que ya existiera. Si prod hoy enviara WhatsApp por otra Evolution, quedaría
  URL vieja + key nueva. Se confirma con el usuario antes de pulsar que prod no usa ninguna
  Evolution hoy.
- **Si falla:** el instalador es idempotente; se corrige y se vuelve a pulsar. Reversa completa:
  WhatsApp Agent → Purgar dependencias (`deploy/provision/uninstall.sh`: respalda la BD
  `evolution_api` y el `.env` antes de borrar).
- **Resultado:** _pendiente — se completa al terminar (sin la API key)._

### Intento 1 — 2026-11-04 12:23 (V1.70/V1.71): falló al compilar

- Escalada: `www-data` fuera del grupo sudo → `su` con la contraseña de root → aceptada.
- Ya existían de una corrida anterior (no documentada aquí): usuario `evolution`,
  `/opt/evolution-api`, el `.env` de Evolution, la BD `evolution_api`, y en el `.env` de MegaISP
  la `WHATSAPP_API_KEY` igual a la de Evolution y `WHATSAPP_API_URL=http://127.0.0.1:8080`.
  Nada de eso se cambió. Node 24.22.1 y PM2 7.0.3 ya estaban.
- El commit salió 'desconocido' (git como root sobre un repo de `evolution`) → `checkout
  fa09d378` + `npm ci` + `prisma generate` → `npm run build` falló en `tsc --noEmit` (29 errores
  de tipos con el cliente MySQL). Se detuvo ahí: no llegó a `migrate deploy`, al proxy de
  Apache ni a PM2. Sin `dist/` nuevo, nada quedó corriendo ni a medias en Apache.
- Arreglo publicado en V1.72 (ver `2026-11-04-release-v172.md`). Siguiente intento: aplicar V1.72
  y volver a pulsar «Instalar dependencias».

### Intento 2 — 2026-11-04 (V1.72): falló en `prisma migrate deploy`

- Compiló bien (parche 01 + `tsup`).
- `prisma migrate deploy` → P3018 en `20240809105427_init`: «Table 'Instance' already exists».
  La BD `evolution_api` ya tenía tablas y una `_prisma_migrations` sin la init aplicada (si no
  existiera, Prisma habría dado P3005). Lo más probable: tablas de las 18 migraciones de Evolution
  con el historial perdido. MySQL rechazó la primera sentencia: no se cambió ninguna tabla. Prisma
  dejó registrada la init como fallida.
- No llegó al proxy de Apache ni a PM2.
- Arreglo publicado en V1.73 (ver `2026-11-04-release-v173-evolution.md`). Si la BD tiene datos y
  una estructura que no corresponde a ninguna migración, el instalador se detiene y muestra la
  diferencia sin tocarla.

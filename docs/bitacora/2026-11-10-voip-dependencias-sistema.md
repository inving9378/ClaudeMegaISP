## 2026-11-10 12:10 — VoIP: instalar / desinstalar Asterisk desde la página de add-ons

**Pedido:** script de instalación y desinstalación de Asterisk en los add-ons para el módulo VoIP,
siguiendo el de Evolution (`deploy/provision`): paso por paso, rollback si algo falla dejando ver
qué pasó. Decisiones del usuario: los scripts de add-ons son **solo para producción**; las
**grabaciones no se eliminan, se quedan donde están**.

Rama `feat/voip-dependencias-sistema` (worktree propio). Nada se corrió en dev ni en prod: en dev
los scripts abortan por la guarda de `APP_URL`, igual que Evolution.

### Qué se hizo

| Archivo | Qué |
|---|---|
| `app/Modules/Addons/VoIP/module.json` | bloque `"system"` (install / purge / description) |
| `VoIP/system/install.sh`, `purge.sh` | lo que corre la página: `as_root env ALLOW_PROVISION=1 … bash provision/…` |
| `VoIP/system/provision/install.sh` | preflight → 10 base realtime → 20 Asterisk → 30 acceso web → 40 proxy /ws → cierre → resumen |
| `VoIP/system/provision/uninstall.sh` | preflight → respaldo BD → /ws → grabaciones (se quedan) → Asterisk → usuario MySQL → trabajo → .env |
| `VoIP/system/provision/lib/common.sh` | helpers con el mismo formato de salida que Evolution; guardas de dev y de grabaciones |
| `lib/10-base-realtime.sh` | base `asterisk` + usuario `asterisk_rt` en el MySQL local + `ASTERISK_RT_DB_*` en el .env (caso #201 de Evolution: aborta si no coinciden, `VOIP_FORCE_ROTATE_DB_PASSWORD=1` rota) |
| `lib/20-asterisk.sh` | corre `php artisan voip:provisionar` como root sin logs/caché de Laravel en disco; devuelve al usuario web lo que root cree en storage; si falla y Asterisk no estaba corriendo, lo detiene y deshabilita |
| `lib/30-acceso-web.sh` | usuario web al grupo `asterisk` + reload de php-fpm (en dev se hizo a mano) |
| `lib/40-proxy-ws.sh` | `/ws` → `127.0.0.1:<ARI_PORT>/ws` en nginx o Apache (todos los server/VirtualHost con el root de MegaISP), con respaldo en `/var/backups/megaisp/vhosts`, `nginx -t`/`configtest` y restauración |
| `lib/90-desinstalar.sh` | pasos de la desinstalación |
| `VoIP/provisioning/desinstalar-asterisk.sh` | `--desde-addon`: no imprime el «Ahora: voip:provisionar --descubrimiento» de dev |
| `VoIP/Services/ProvisionadorAsterisk.php` | `sudo -n` solo si no corre como root (en un servidor sin sudo fallaba el primer paso); `migrar_esquema` asegura `voip_llamadas` |
| `config/requisitos-voip.php` | `provisionador_version` 1.0.0 → 1.1.0 (cambió lo que hace `migrar_esquema`) |

### Por qué cada pieza

- **Base y usuario realtime:** el provisionador daba por hecho que existían; en dev se crearon a mano.
- **Sin `sudo` como root:** producción corre los scripts con `su` (www-data no está en sudo) y el
  provisionador anteponía `sudo -n` a todo.
- **`voip_llamadas`:** su migración no hace nada si no hay base realtime y aun así queda registrada
  como corrida. En prod ya está marcada así: tras instalar Asterisk nunca se habría creado y no se
  registraría ninguna llamada.
- **Respaldos de vhost fuera de `sites-enabled`:** nginx incluye `sites-enabled/*` entero y en dev
  conviven archivos normales y symlinks; un `.bak` junto al archivo se cargaría como configuración.
  Lo encontró la prueba de punta a punta.
- **Grabaciones:** no se mueven ni se borran. Como el usuario/grupo `asterisk` desaparece, pasan a
  `root:<grupo web>` (siguen legibles para MegaISP). Si `MEGAISP_ASTERISK_GRABACIONES_DIR` cae
  dentro de algo que se borra (`/var/lib/asterisk`, `/var/spool/asterisk`, `/etc/asterisk`…), la
  desinstalación aborta en el preflight sin tocar nada.

### Pruebas (simuladas, sin root, igual que las de Evolution)

- `tests/test-40-proxy-ws.sh` — 40/40: Apache ≥/< 2.4.47, :80 y :443, rollback de configtest con
  módulos deshabilitados, nginx con varios server{}, /ws puesto a mano, quitar deja el archivo
  idéntico, sin `.bak` en sites-enabled.
- `tests/test-10-base-y-guardas.sh` — 38/38: base nueva, #201, password que no entra, MySQL que
  rechaza, host remoto, guardas de dev y de grabaciones (incluye symlink), secretos del .env.
- `tests/test-flujo-completo.sh` — 37/37: orden de pasos, idempotencia, rollback del servicio,
  respaldo antes de borrar, grabaciones intactas, dev aborta sin tocar nada.
- Además, en dev (solo lectura): el bloque nginx pasa `nginx -t` real y, con un nginx temporal en
  :18080, `/ws` da 426 con GET y `101 Switching Protocols` con el handshake SIP contra el Asterisk
  de dev. La guarda frena con el `.env` real de dev. `ModuleSystemService` reconoce los scripts.

### Hallazgos de la revisión de VoIP en dev (misma sesión, antes de esto)

- Troncales Servnet y UCM registradas; entrantes llegan a la cola y timbran el 1001 (simulado por
  AMI). **No hay ruta saliente** en el dialplan ni contexto `cobranza-blaster` (campañas sin salida).
- Sin agentes libres, `grupo-1` vuelve a `s,1` sin pausa: beep en bucle y un MixMonitor nuevo por
  vuelta (confirmado en vivo: 2.ª grabación al regresar a la cola).
- La carga de prod en dev (hoy) dejó `voip_*` de la app distintas de lo que Asterisk tiene
  (troncal id 4 vs `trunk_3`, extensiones de más y de menos).
- «Desinstalar sin conservar datos» corre las migraciones `down()` (borra `voip_llamadas`) ANTES de
  `purge.sh`, así que ese respaldo ya no la trae; «Purgar dependencias» suelto sí.

### Para producción (cuando se publique la versión)

Bitácora PREVIA en prod antes de pulsar. Revisiones: `APP_URL` de prod, `systemctl is-active
apache2 nginx`, `ss -lunp | grep 5060` y `:8088` libres, ~3 GB libres, salida a
`megaisp.com.mx/artefactos/asterisk`. Luego VoIP → Dependencias del servidor → Instalar (pide la
contraseña de root en el modal; la compilación tarda 30–60 min). Después: «Provisionar» cada
troncal en VoIP → Troncales; TURN y `ASTERISK_EXTERNAL_*` quedan a mano.

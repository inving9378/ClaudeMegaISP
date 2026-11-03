## 2026-11-03 — Dependencias de sistema por add-on (install.sh / purge.sh desde la página de add-ons)

**Qué se pidió.** Que un add-on que necesita paquetes o servicios en el servidor traiga sus
propios scripts de bash, y que al instalarlo/desinstalarlo desde Configuración → Add-ons
(`/admin/modules`) el sistema los corra solo, pidiendo contraseña en un modal cuando haga
falta root. Las versiones y el deploy NO cambian: los scripts viajan en git con el add-on
y solo corren cuando alguien pulsa el botón. Primera prueba: instalar Evolution API en prod.

**Rama:** `feat/dependencias-sistema-addons` (worktree `/var/www/megaisp-deps-sistema`). Sin
mergear: es infraestructura (scripts que escalan a root), el merge lo aprueba Irving.

### Cómo funciona

- El add-on declara en su `module.json`:
  `"system": {"install": "system/install.sh", "purge": "system/purge.sh", "description": "..."}`.
- `deploy/system/lib.sh` da `as_root CMD`, con la escalada acordada:
  1. ya es root → directo;
  2. en el grupo sudo: `sudo -n` si sudo no pide contraseña; si la pide → modal con la
     contraseña **del usuario** → `sudo`; si sudoers no lo deja → paso 3;
  3. fuera del grupo sudo → modal con la contraseña **de root** → `su` (con pseudo-terminal,
     `deploy/system/su-pty.py`, porque su no acepta la contraseña por tubería);
  4. nada funcionó → error.
  Tres intentos por contraseña, 15 min de espera, se pide una sola vez por corrida.
- La contraseña viaja: modal → POST → FIFO de la corrida que el script está leyendo. No se
  escribe en disco, ni en la BD, ni en el log, ni en argumentos de proceso; en el script
  queda en una variable sin exportar mientras dura la corrida.
- `ModuleSystemService` lanza `php artisan module:system <install|purge> <slug> --run=<id>`
  desprendido (mismo patrón que "Buscar actualizaciones") y guarda la corrida en
  `storage/app/module-system/runs/<id>/` (estado, salida, FIFO). **No hay tabla nueva**: es
  estado local del servidor; las corridas terminadas se borran a los 30 días.
- Instalar un add-on que declara `install.sh`: primero se valida que el módulo se pueda
  instalar (`ModuleLifecycleService::assertInstallable`, extraído de `install()` sin cambiar
  su lógica), corre el script y, si termina bien, se instala el módulo (`--lifecycle`).
- Desinstalar sin conservar datos: tras desinstalar el módulo corre `purge.sh`. Con
  "conservar datos" las dependencias se quedan (ahí suelen vivir datos); se pueden purgar
  después con el botón.
- Detalle del módulo → "Dependencias del servidor": botones para correr solo el script,
  última corrida, salida en vivo.
- Las rutas nuevas no están en `config/route_permission.php` a propósito: solo
  administrador, desarrollador y super-administrador pasan `CheckRoutePermission`.
- Terminal: `php artisan module:system install|purge <slug>` (pide la contraseña ahí mismo).

### Evolution (addon-whatsapp-agent)

- `system/install.sh` llama al instalador probado `deploy/provision/install.sh` como root,
  con `ALLOW_PROVISION=1` (prod comparte hostname con dev; la guarda por APP_URL sigue, así
  que en dev aborta) y `PROVISION_HIDE_KEY=1` (la salida se ve en la página: la API key ya
  no se imprime; queda en el `.env`).
- `system/purge.sh` → nuevo `deploy/provision/uninstall.sh` (+ `lib/90-unprovision.sh`):
  quita proceso PM2 y servicio `pm2-evolution`, el bloque `/evolution/` del vhost (Apache o
  nginx; respaldo, configtest y restauración si falla), `/opt/evolution-api`, la BD
  `evolution_api` (con `mysqldump` previo en `/var/backups/megaisp`; si el respaldo falla no
  se borra), su usuario MySQL, `WHATSAPP_API_KEY` y `WHATSAPP_API_URL` (solo si era la local)
  del `.env` (con respaldo) y el usuario `evolution`. No quita Node, PM2, NodeSource ni los
  módulos de Apache (compartidos).
- **Node:** el instalador exigía Node 20 exacto. Dev ya está en 24 (bitácora 2026-10-26) y
  prod está pendiente de revisar: con 24 el instalador moría, y si no había Node instalaba
  una línea sin parches. Ahora respeta el Node que haya si es >= 20 (lo usa todo el sistema)
  y solo instala 24 si falta.
- `run_as` pasó de `install.sh` a `lib/common.sh` para que lo compartan instalador y
  desinstalador (sin duplicarlo).

### Bug encontrado al probar con sudo real

`sudo -n -l CMD` responde "permitido" aunque la regla que lo permite pida contraseña (en dev
`meganet` tiene `ALL` con contraseña): el script intentaba `sudo -n`, fallaba y no pedía la
contraseña. Se cambió por una sola prueba `sudo -n true`. Además, fuera del grupo sudo ya ni
se toca sudo: cada intento de un usuario que no está en sudoers queda como incidente.

### Verificación

| Prueba | Resultado |
|---|---|
| `bash deploy/system/tests/test-lib.sh` (id/sudo/su simulados, página simulada por FIFO) | 32/32 |
| `bash deploy/provision/tests/test-90-unprovision.sh` (inyectar con el instalador y quitar: vhost idéntico al original; configtest fallido; marcadores rotos; `.env`) | 27/27 |
| `bash deploy/provision/tests/test-30-apache.sh` (el existente, tras mover `run_as`) | 33/33 |
| Runner real en dev con `addon-demo`: corre la parte sin root, se queda en "esperando contraseña de meganet (sudo)", bloquea una segunda corrida, cancelar la corta limpio; purge sin root termina bien | OK |

**No verificado aquí:** sudo con la contraseña correcta (lo prueba una persona desde el
modal en dev), `su` real (dev no tiene la contraseña de root; se ejercita en prod) y la
instalación de Evolution de punta a punta (solo en prod: en dev el instalador aborta a
propósito).

### Pendiente / riesgos

- Hay un worktree sin commits de Irving (`/home/meganet/megaisp-wt-addons-servidor`, rama
  `feat/addons-servicios-servidor`, 2026-10-14) que agrega `"servicios_servidor"` al mismo
  `module.json` de WhatsApp y una pantalla que solo **revisa** si Evolution está corriendo.
  No choca con esto, pero toca el mismo archivo: al integrar, decidir si su verificador queda
  como el "check" de estas dependencias.
- Prod debe servirse por HTTPS antes de escribir la contraseña de root en el modal.
- Si el paso de Apache falla en prod, el instalador revierte solo; el plan B
  `SKIP_WEB_PROXY=1` hoy no se puede elegir desde la página (solo a mano).
- `su` necesita que root tenga contraseña en el servidor y python3.

### Plantilla de bitácora PREVIA para la prueba en prod

Crear y commitear **antes** de pulsar el botón (regla de CLAUDE.md), por ejemplo
`docs/bitacora/<AAAA-MM-DD>-evolution-prod.md`:

```markdown
## <AAAA-MM-DD HH:MM> — Instalación de Evolution API en PROD desde la página de add-ons
- Quién lo corre: <persona>; quién escribe la contraseña de root: <persona>
- Servidor: 192.168.105.108, MegaISP en /var/www/ClaudeMegaISP, BD meganet_prod_claude (no se toca)
- Acción: Configuración → Add-ons → WhatsApp Agent → Dependencias del servidor → Instalar dependencias
  (corre deploy/provision/install.sh como root vía su)
- Qué escribe: /opt/evolution-api, usuario de sistema evolution, BD nueva evolution_api + usuario
  evolution_user, vhost Apache de MegaISP (bloque /evolution/, con respaldo .provision.<fecha>.bak),
  módulos proxy/proxy_http, unit systemd pm2-evolution, WHATSAPP_API_KEY/URL en el .env de MegaISP
- Respaldo previo: <cuál>
- Prueba del mecanismo antes: addon-demo → Instalar dependencias / Purgar dependencias
- Resultado: <se completa al terminar, sin la API key>
```

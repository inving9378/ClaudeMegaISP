## 2026-10-15 10:20 — Terminal web (ttyd) de DevTools: encendido seguro, listo para que root lo aplique

**Pedido:** "arregla lo del tty". La terminal de DevTools está muerta (nginx `/ttyd/` → 502) porque
ttyd se apagó a propósito el 2026-10-14 tras el incidente (corría `--interface 0.0.0.0` sin
contraseña; ver `docs/bitacora/2026-10-14-consola-ttyd-diagnostico.md`, rama `docs/bitacora-consola-ttyd`).

**Rama:** `fix/ttyd-terminal-segura` (sin mergear). **Falta un paso de root**: el usuario `meganet`
no tiene sudo sin contraseña.

### Qué se hizo
- `GET /devtools/terminal-auth` (`DevToolsController::terminalAuth`): puerta que consulta nginx con
  `auth_request`. 401 sin sesión, 403 sin rol `DESARROLLADOR`/`super-administrator`, 204 + header
  `X-MegaISP-Term-User: <login_user>`. Solo `EncryptCookies`+`StartSession` (sin `auth`: su 302 lo
  toma nginx como 500). Registra en `activity_log` (`devtools-terminal`) cada apertura del
  WebSocket y cada intento denegado, con la IP real (`X-Real-IP`).
- `deploy/ttyd/aplicar-ttyd-seguro.sh` (correr con sudo): snippet nginx con `auth_request`,
  reemplaza el bloque `/ttyd/` sin auth de `megaisp.conf`, lo agrega al vhost HTTPS, reescribe
  `ttyd.service` (`--interface 127.0.0.1 --check-origin --auth-header … --client-option
  scrollback=100000`), respalda en `/var/backups/ttyd-seguro-*`, y se auto-verifica (si algo queda
  abierto, apaga ttyd). `--apagar` para volver a apagarla.
- `~/.bashrc` (no versionado; respaldo `~/.bashrc.bak-20261015_101405-ttyd-seguro`): la rama de
  identidad usa `TTYD_USER` directo (antes llamaba `php artisan terminal:validar-token`).
- `deploy/README-terminal-por-usuario.md` marcado como SUPERADO (sus pasos reabrían 0.0.0.0).
- `DevtoolsPanel.vue`: el aviso de "terminal no disponible" recomendaba
  `ttyd -p 7681 -W --interface 0.0.0.0 bash`; ahora apunta al script. Requiere `npm run prod`.

### Hallazgo
ttyd 1.7.7 guarda el valor de `--auth-header` en un buffer de 30 bytes: con ≥30 caracteres corta el
WebSocket (`User code denied connection`) aunque el HTML y `/token` pasen. Medido: 29 → 101, 30 →
cerrado. El token HMAC de #9991177 (~90 car.) nunca habría funcionado por WS; se pasa el
`login_user` simple, confiable porque a ttyd solo se llega por nginx (loopback) y nginx pisa el
valor del cliente. `TerminalIdentityTokenService`/`terminal:validar-token`/cookie
`megaisp_term_token` quedan sin uso real (no se tocaron).

### Verificado (sin root: nginx + ttyd + Laravel de la rama en puertos de prueba de loopback)
Sin sesión 401 · header falsificado sin sesión 401 · Vendedor 403 · super-admin 200 y `/token` 200 ·
`/_megaisp_ttyd_auth` desde fuera 404 · ttyd directo sin header 407 · WebSocket con sesión 101 y
`TTYD_USER=Irving` · con el `.bashrc` real cae en tmux `web-Irving` · origen ajeno rechazado.
Las 7 filas de `activity_log` que generó la prueba se borraron; procesos y sesiones de prueba
detenidos/borrados.

### Pendiente
1. Mergear la rama + `npm run prod` en `/var/www/megaisp`.
2. Root: `sudo bash /var/www/megaisp/deploy/ttyd/aplicar-ttyd-seguro.sh`.
3. Revisar quién tiene rol `DESARROLLADOR` (dará shell): hay 6 cuentas `Meganet<hex8>`
   (ids 4875, 4883, 4898, 4899, 4906, 4908, creadas jul–ago 2026), `t_mapared_cat` (5045),
   `yasmani` (3870) y `yosvani` (4116, super-administrator).
4. Siguen los pendientes del incidente: revisión como root incompleta, rotar `.env` (incluido
   `APP_KEY`; el `.env` no cambia desde el 28-sep) y la llave SSH; `python3 -m http.server` en
   0.0.0.0:8765 y :8899 sirviendo `public/` de worktrees desde el 7-sep.

## 2026-10-19 06:51 — Terminal web: la sesión vencida ya no se confunde con «ttyd apagado»

**Síntoma:** la terminal de DevTools mostraba «Terminal no disponible… corre
`sudo bash deploy/ttyd/aplicar-ttyd-seguro.sh`», y se corría el script sin que hiciera falta.

**Causa:** ttyd estaba encendido y bien configurado (habilitado en systemd, nginx con su
`location /ttyd/`). Lo que fallaba era la sesión del sistema: vence a las 2 h
(`SESSION_LIFETIME=120`) sin peticiones a la app, y trabajar solo en la terminal no hace
ninguna porque el WebSocket no pasa por Laravel. Si después se cortaba la conexión, ttyd ya no
podía reconectar (el `auth_request` de nginx respondía 401) y el panel daba el mismo mensaje
para cualquier fallo. El script solo vuelve a escribir la misma configuración.

**Cambios (rama `fix/devtools-ttyd-mensaje-sesion`, `DevtoolsPanel.vue`):**
- `83e53921` — cada causa tiene su mensaje: 401 «Tu sesión expiró» + botón de recargar,
  403 sin permiso, sin red, y solo para el resto el comando del script.
- Latido de sesión: mientras el panel está abierto, cada 10 min hace `GET
  /devtools/terminal-auth` (la misma puerta que consulta nginx; no escribe en la bitácora y su
  respuesta renueva la cookie de sesión, `Max-Age=7200`). También al volver a la pestaña. Si
  responde 401, el panel cambia a «Tu sesión expiró» en vez de dejar una terminal que no
  reconecta.

**Verificado:** el componente compila (`@vue/compiler-sfc`); `/devtools/terminal-auth`
devuelve la cookie de sesión renovada en su respuesta. Entrar desde la IP pública
(`:3032` → nginx :80) funciona por diseño: `--check-origin` compara contra `$http_host`, que
lleva el puerto.

**Queda:** si la compu pasa más de 2 h dormida, la sesión vence igual y hay que volver a entrar
(el panel ya lo dice). El script de encendido no hace falta en ningún caso de estos.

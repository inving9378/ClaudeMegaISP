# Terminal web de DevTools (ttyd) — encendido seguro

La terminal se apagó el 2026-10-14: ttyd corría `--interface 0.0.0.0` **sin contraseña** y hubo
shells abiertos desde IPs externas (diagnóstico: `docs/bitacora/2026-10-14-consola-ttyd-diagnostico.md`,
rama `docs/bitacora-consola-ttyd`). Así queda ahora:

| Candado | Qué hace |
|---|---|
| `--interface 127.0.0.1` | Desde fuera del servidor no se llega al puerto 7681. |
| nginx `auth_request` | Antes de cada petición a `/ttyd/`, nginx pregunta a `GET /devtools/terminal-auth`: sin sesión → 401, sin rol `DESARROLLADOR`/`super-administrator` → 403. |
| `--auth-header X-MegaISP-Term-User` | ttyd rechaza (407) lo que no traiga el header; lo pone **solo** nginx, pisando el del cliente. |
| `--check-origin` | Otro sitio no puede abrir el WebSocket con la cookie del usuario. |
| `activity_log` `log_name='devtools-terminal'` | Quién abrió (o intentó abrir) una terminal, con IP real. |

El header lleva el `login_user`; ttyd lo expone al bash como `TTYD_USER` y `~/.bashrc` abre la
sesión tmux **de ese usuario** (`web-<login_user>`), así nadie cae en la sesión de otro.

## Aplicar (requiere root, una sola vez)

1. Mergear la rama `fix/ttyd-terminal-segura` en `/var/www/megaisp` (trae `/devtools/terminal-auth`).
2. Revisar el script (todo lo que instala está dentro) y correrlo:
   ```bash
   sudo bash /var/www/megaisp/deploy/ttyd/aplicar-ttyd-seguro.sh
   ```
   Respalda en `/var/backups/ttyd-seguro-<fecha>/`, instala `/etc/nginx/snippets/megaisp-ttyd.conf`,
   cambia el bloque `/ttyd/` de `megaisp.conf` por el include (y lo agrega al vhost HTTPS
   `dev.meganett.com.mx`), reescribe `ttyd.service`, y **verifica** al final: si ttyd no queda
   solo en 127.0.0.1, o responde sin el header, o `/ttyd/` sin sesión no da 401, **lo apaga**.
3. `~/.bashrc` de `meganet` ya trae el bloque que usa `TTYD_USER` (dotfile, no versionado).

Apagar otra vez: `sudo bash /var/www/megaisp/deploy/ttyd/aplicar-ttyd-seguro.sh --apagar`.

## Gotchas medidos (2026-10-15, ttyd 1.7.7)

- **El valor del header debe medir ≤29 caracteres.** ttyd lo copia a un buffer de 30 bytes y, si
  no cabe, corta el WebSocket con un genérico `User code denied connection` (el HTML y `/token`
  sí pasan, así que parece un bug de red). Por eso no se usa el token HMAC de #9991177 (~90 car.).
- `Host` se reenvía con `$http_host` (con puerto) para que `--check-origin` compare bien.
- `auth_request` toma un 302 como error 500: `/devtools/terminal-auth` no usa el middleware `auth`
  (que redirige a /login) y responde 401/403/204 explícitos.

Probado sin root con un nginx + ttyd + Laravel de la rama en puertos de prueba: sin sesión 401,
header falsificado sin sesión 401, Vendedor 403, super-admin 200 + WebSocket 101 con
`TTYD_USER=Irving` y sesión tmux `web-Irving`, origen ajeno rechazado, ttyd directo sin header 407.

# Terminal ttyd por usuario logueado — Fase 1 (item #9991177) — runbook

Código y diseño ya mergeados a `main` (`TerminalIdentityTokenService`, endpoint
`GET /devtools/terminal-token`, comando `terminal:validar-token`). Este runbook
cubre los **3 pasos que requieren root** y que ninguna terminal del circuito
puede ejecutar — motivo `requiere_root`. Hasta que se corran, el comportamiento
actual de `~/.bashrc` (cualquier sesión `web-N` libre) sigue **intacto**.

Correr en una ventana **sin agentes del circuito activos** (el paso (b) tumba
todas las conexiones ttyd vivas, incluidas las de otras terminales trabajando
en paralelo).

## Contexto (ya investigado — no repetir)

- ttyd hoy: `/etc/systemd/system/ttyd.service`, `ExecStart=/usr/bin/ttyd -p 7681 -W --interface 0.0.0.0 bash` (`User=meganet`).
- nginx `location /ttyd/` en `/etc/nginx/sites-enabled/megaisp.conf` hace `proxy_pass` a `127.0.0.1:7681` sin auth propia.
- El usuario `meganet` (el que corre el circuito) **no tiene sudo passwordless** (`sudo -n true` falla) — de ahí que estos 3 pasos no los pueda correr una terminal del circuito.

## Paso (c) — YA CONFIRMADO (2026-09-16, sin tocar systemd/root)

El nombre de la variable de entorno que ttyd expone al proceso hijo cuando
corre con `-H/--auth-header` **NO** depende del nombre del header pasado a la
bandera — siempre es fijo: **`TTYD_USER`**.

Confirmado empíricamente (ttyd 1.7.7-40e79c7), sin tocar el unit real: se
levantó una instancia de prueba en un puerto no privilegiado, se completó el
handshake WebSocket con el header custom, se envió el mensaje inicial que
manda el cliente JS de ttyd (`{"AuthToken":"","columns":80,"rows":24}` — sin
eso ttyd no llega a spawnear el pty) y se leyó `/proc/<pid_hijo>/environ` del
bash resultante:

```bash
ttyd -p 7682 --interface 127.0.0.1 -H X-MegaISP-Term-User bash
# … (cliente WS con header "X-MegaISP-Term-User: probando123" + mensaje inicial) …
# /proc/<pid>/environ del bash hijo contiene: TTYD_USER=probando123
```

Reproducible con cualquier nombre de header — `strings /usr/bin/ttyd | grep TTYD_USER`
ya lo insinúa (`TTYD_USER=%s` como format string interno). Si en el futuro se
actualiza el binario de ttyd, repetir esta prueba en el puerto 7682 (o
cualquier puerto libre no privilegiado) **antes** de fiarse del nombre — no
requiere root, cualquier terminal del circuito puede rehacerla.

Con esto confirmado, el diff de `~/.bashrc` de abajo ya usa `$TTYD_USER`
directamente (no una variable "por confirmar").

## Paso (a) — nginx: reenviar la cookie de identidad como header (root)

Diff exacto sobre `/etc/nginx/sites-enabled/megaisp.conf` (bloque
`location /ttyd/` actual, sin cambios salvo la línea nueva):

```diff
     location /ttyd/ {
           proxy_pass         http://127.0.0.1:7681/;
           proxy_http_version 1.1;
           proxy_set_header   Upgrade          $http_upgrade;
           proxy_set_header   Connection       "upgrade";
           proxy_set_header   Host             $host;
           proxy_set_header   X-Real-IP        $remote_addr;
           proxy_set_header   X-Forwarded-For  $proxy_add_x_forwarded_for;
+          proxy_set_header   X-MegaISP-Term-User $cookie_megaisp_term_token;
           proxy_read_timeout 86400s;
       }
```

Aplicar y recargar (reload, no restart — no debería tumbar conexiones ya
establecidas):

```bash
sudo nano /etc/nginx/sites-enabled/megaisp.conf   # aplicar el diff de arriba
sudo nginx -t && sudo systemctl reload nginx
```

## Paso (b) — systemd: activar `-H` en ttyd (root, TUMBA sesiones vivas)

⚠️ Este paso sí corta **todas** las conexiones WebSocket de ttyd activas en
ese instante (tmux sigue vivo, solo se cae el cliente — ver el bloque de
`~/.bashrc` ya existente que reengancha tmux al reconectar). Avisar antes en
el canal del equipo / verificar que no haya terminales del circuito en medio
de un commit.

Diff exacto sobre `/etc/systemd/system/ttyd.service`:

```diff
 [Service]
-ExecStart=/usr/bin/ttyd -p 7681 -W --interface 0.0.0.0 bash
+ExecStart=/usr/bin/ttyd -p 7681 -W -H X-MegaISP-Term-User --interface 0.0.0.0 bash
 Restart=always
 User=meganet
```

Aplicar:

```bash
sudo nano /etc/systemd/system/ttyd.service   # aplicar el diff de arriba
sudo systemctl daemon-reload
sudo systemctl restart ttyd
```

## Paso (d) — extender `~/.bashrc` del usuario `meganet` (NO requiere root)

`~/.bashrc` es un dotfile del filesystem, **no versionado en git** — por eso
va aquí como diff a aplicar a mano, igual que los dos anteriores. Este paso sí
lo puede aplicar cualquiera con acceso a la cuenta `meganet` (no requiere
root), pero se documenta junto con (a)/(b) porque **no tiene efecto real
hasta que ambos estén aplicados** (sin ellos, `$TTYD_USER` nunca llega
poblada y el bloque cae siempre al fallback intacto).

Bloque actual (líneas ~129-178 de `~/.bashrc`, "ttyd → tmux (2026-08-26)"):
solo reengancha la primera sesión `web-N` libre o crea una nueva numerada —
sin identidad de usuario. El diff inserta la identificación por token
**antes** de esa lógica existente, dejándola intacta como fallback:

```diff
 if [ -z "${TMUX:-}" ] && [[ $- == *i* ]] && command -v tmux >/dev/null 2>&1 \
    && [ "$(ps -o comm= -p "${PPID:-0}" 2>/dev/null | tr -d '[:space:]')" = "ttyd" ]; then
+    # Identidad por usuario logueado (item #9991177, Fase 1). Si ttyd trae un
+    # token de sesión válido (env TTYD_USER, inyectado por -H del ExecStart
+    # tras aplicar el paso (b) de deploy/README-terminal-por-usuario.md),
+    # reengancha SIEMPRE la sesión tmux nombrada de ESE usuario — nunca
+    # "cualquiera libre". Token con TTL de 60s (HMAC validado por el propio
+    # comando artisan) — si viene vacío, inválido o expirado (systemd sin
+    # aplicar el paso (b), o el usuario tardó al hacer login), cae intacto
+    # al comportamiento existente de abajo. Nunca rompe el flujo actual.
+    __term_login_user=""
+    if [ -n "${TTYD_USER:-}" ]; then
+        __term_login_user="$(cd /var/www/megaisp && php artisan terminal:validar-token "$TTYD_USER" 2>/dev/null)"
+    fi
+    if [ -n "$__term_login_user" ]; then
+        export MEGAISP_TERMINAL_USER="$__term_login_user"
+        tmux new-session -A -s "web-${__term_login_user}" && exit
+    fi
+    unset __term_login_user
+
     # 1) Reengancha la primera sesión web-* sin cliente: es la que quedó huérfana
     #    en la desconexión anterior, con tu contexto intacto.
     #
```

(El resto del bloque —reintento de 5×0.5s, numeración `web-N`— queda **sin
tocar**, como rama que solo se alcanza si el token no vino o no fue válido.)

Aplicar con un editor (`nano ~/.bashrc`), pegando el bloque nuevo justo
después de la línea del `if [ -z "${TMUX:-}" ] ...; then` existente. No hace
falta reiniciar sesión — el bloque solo corre en shells **nuevos** que abra
ttyd (`source ~/.bashrc` en una sesión ya abierta no re-dispara la condición
del padre `ttyd`).

## Verificación end-to-end (tras los 4 pasos)

1. Loguearse en `/devtools` con un usuario cualquiera del panel admin (no
   solo DESARROLLADOR) → confirmar en devtools del navegador que llegó la
   cookie `megaisp_term_token` (HttpOnly, no debería ser legible por JS —
   solo confirmar que existe en la pestaña Application/Cookies).
2. Abrir una terminal ttyd (dos pestañas del **mismo** usuario) → ambas deben
   reengancharse a la **misma** sesión `web-<login_user>` (antes: podían caer
   en `web-1`/`web-2` distintas).
3. Con dos usuarios distintos logueados en paralelo → cada uno a su propia
   `web-<login_user>`, sin cruzarse.
4. Cookie vencida (>60s desde el último `GET /devtools*`) o usuario sin sesión
   web → cae al comportamiento actual (`web-N` libre), sin errores visibles.

## Rollback

- Paso (b): revertir el diff de `ttyd.service` (quitar `-H ...`) +
  `daemon-reload && restart ttyd` — vuelve a tumbar sesiones vivas.
- Paso (a): revertir el diff de nginx + `nginx -t && reload nginx` (reload,
  no tumba nada).
- Paso (d): el bloque nuevo es aditivo con fallback — se puede dejar
  instalado aunque se reviertan (a)/(b) (simplemente nunca encontrará
  `TTYD_USER` poblada y caerá siempre al comportamiento actual).

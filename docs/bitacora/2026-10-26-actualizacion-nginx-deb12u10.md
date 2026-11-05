## 2026-10-26 07:05 — DEV (.11): nginx 1.22.1-9+deb12u4 → deb12u10 + revisión de los CVE de octubre

**Qué:** parches de seguridad de nginx en el servidor de desarrollo (192.168.105.11). Sigue
siendo nginx 1.22.1 (paquete de Debian 12), solo con los parches acumulados de 2026 encima.
Solo DEV — producción (.108) queda pendiente en su propia sesión (su config puede ser distinta).

**Quién:** Irving corrió los comandos con sudo; Claude Code investigó los CVE, preparó el
comando y verificó.

**Comandos:**
```bash
sudo apt-get install --only-upgrade nginx nginx-common
sudo systemctl restart nginx   # ver nota del proceso viejo abajo
```

**Qué cierra deb12u10 (DEV estaba en deb12u4, le faltaban u5–u10):**
- CVE-2026-42945 "NGINX Rift" (`rewrite` + `$1` con `?` → desbordamiento de memoria / posible
  ejecución de código) — corregido en u7 (DSA-6278-1). Una fuente lo reporta explotado desde mayo.
- CVE-2026-42533 (grave, `map` con regex), CVE-2026-56434 (SSI), CVE-2026-60005 (slice, fuga de
  memoria) — corregidos en u10 (DLA-4784-1, 18-jul-2026).
- En la config de DEV no se usa nada de lo que dispara esas fallas (no hay `rewrite`, `alias`,
  SSI, AIO, ni `map` con regex), así que la exposición real era baja; se parcheó igual.

**CVE de octubre (aviso de F5 del 14-oct-2026, corregidos en nginx 1.30.6 / 1.31.7):**
CVE-2026-15176, -93479, -95101, -97205, -97629. Debian **todavía no publica** corrección para
bookworm (el tracker los marca "vulnerable" sin versión fija; trixie ya la tiene, DSA-6575-1).
En DEV ninguno aplica:
- 15176: requiere `alias` + `rewrite` + `$request_filename` → no se usan.
- 97205: requiere AIO + SSI → ninguno activo.
- 93479: solo 1.30+ → no aplica a 1.22.
- 95101: HTTP/3, existe desde 1.25 → no aplica a 1.22.
- 97629: tickets de sesión TLS desde 1.23.2 → no aplica, y además `ssl_session_tickets off`
  (lo pone `/etc/letsencrypt/options-ssl-nginx.conf`).
Se descartó instalar 1.30.6 desde el repo de nginx.org (cambia paquete y estructura de config;
más riesgo del que justifica una exposición nula). **Pendiente:** cuando Debian publique
`1.22.1-9+deb12u11`, repetir el `apt-get install --only-upgrade`.

**Nota — proceso viejo tras la actualización:** el paquete hace una actualización "en caliente"
(arranca un master nuevo y deja al viejo drenando conexiones abiertas, p. ej. la terminal web).
El master viejo quedó vivo con `/run/nginx.pid.oldbin` y systemd seguía apuntando a él como
`MainPID`. Se limpió con `systemctl restart nginx` (07:00:42).

**Verificado (07:03):**
- `nginx` y `nginx-common` en `1.22.1-9+deb12u10`; `nginx -t` OK.
- Un solo master (MainPID = `/run/nginx.pid`, sin `.oldbin`) con 4 workers.
- `https://dev.meganett.com.mx/login` → 200; raíz → 302 al login; descarga de APK → 200;
  reto ACME → 404 (archivo inexistente, ruta viva); puerto 80 → 301 a HTTPS; vista previa
  :8081 → 200; terminal web sin sesión → 401 (correcto).
- TLS 1.3 OK; certificado vigente hasta 10-dic-2026.

**Siguiente:** kernel por CVE-2026-31431 ("Copy Fail", escalada local a root) — bitácora aparte.

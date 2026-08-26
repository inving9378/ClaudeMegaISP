# HTTPS local para la LAN de dev (item roadmap #139)

## Objetivo

Servir `https://192.168.105.11` con un certificado **local** (no de CA pública) para que
el navegador trate la página como **secure context** (`window.isSecureContext === true`).
Eso desbloquea `navigator.clipboard`, Service Workers y otras APIs que Chrome/Firefox
bloquean en HTTP plano por IP.

**Caso motivador:** el copiar/pegar en la terminal web ttyd (proxy `/ttyd/` del sitio)
fallaba en silencio porque `navigator.clipboard` no existe fuera de un secure context.

## Por qué esto es distinto del otro TLS que ya existe en el repo

Este repo ya tiene `deploy/nginx-dev-tls.conf` + `deploy/README-dev-tls.md` para
`dev.meganett.com.mx` — pero ese es un caso **completamente distinto**: expone
`/api/roadmap-externo` a un fetcher **en la nube** (Claude Cowork), que exige cadena de
**CA pública** (Let's Encrypt) y rechaza cualquier autofirmado.

Este documento es para el acceso **LAN interno** de Irving a `192.168.105.11` — aquí un
certificado local (autofirmado u obtenido con `mkcert`) **sí es la solución correcta**:
el navegador solo pide aceptar una advertencia una vez (o cero veces, si se importa la
CA local — ver abajo). No hace falta CA pública ni DNS público para este caso.

## Uso

```bash
sudo bash deploy/setup-https-dev.sh
```

El script (idempotente):
1. Genera el certificado en `/etc/nginx/ssl-dev/` — usa `mkcert` si está instalado en el
   servidor, si no cae a un autofirmado con `openssl` (mismo resultado funcional).
2. Escribe un server block nuevo `megaisp-https-lan` en `sites-available`/`sites-enabled`
   con `listen 443 ssl` para `192.168.105.11`, replicando los mismos `location` del sitio
   actual (`/ttyd/`, `/evolution/`, PHP-FPM) — **no toca** `megaisp.conf` ni el puerto 80.
3. `nginx -t` + `systemctl reload nginx`.

Verificado en sandbox (sin tocar el nginx real): el par cert+key generado con `openssl` y
un server block `listen ... ssl` equivalente pasan `nginx -t` sin errores.

## Después de correrlo

Abre `https://192.168.105.11/` en el navegador:
- **Autofirmado (default si no hay mkcert):** el navegador muestra advertencia de
  certificado la primera vez → "Avanzado" → "Continuar". Una vez aceptado, la página
  corre como secure context (el candado sale tachado/amarillo, pero
  `isSecureContext` ya es `true` y el clipboard funciona).
- **Con mkcert:** `mkcert -install` en el script solo instala la CA en el trust store del
  **servidor** — como navegas desde OTRA máquina, eso no evita la advertencia por sí
  solo. Para cero-advertencia real: copia `$(mkcert -CAROOT)/rootCA.pem` del servidor a
  tu máquina e impórtalo ahí como autoridad raíz de confianza (Chrome/Windows: certmgr →
  "Entidades de certificación raíz de confianza"; macOS: Keychain Access → System →
  Certificates → Trust; Firefox: Ajustes → Privacidad → Certificados → Ver certificados
  → Autoridades → Importar).

## Verificación de que quedó activo

```bash
curl -sk -o /dev/null -w "%{http_code}\n" https://192.168.105.11/   # 200/302, no error de conexión
```

En DevTools del navegador (consola, tras aceptar el certificado):
```js
window.isSecureContext   // true
navigator.clipboard      // objeto definido (antes era undefined en HTTP)
```

## Rollback

```bash
sudo rm /etc/nginx/sites-enabled/megaisp-https-lan.conf
sudo systemctl reload nginx
```
El puerto 80 (`megaisp.conf`) nunca se toca — el acceso HTTP actual sigue intacto en
paralelo aunque se active o revierta este cambio.

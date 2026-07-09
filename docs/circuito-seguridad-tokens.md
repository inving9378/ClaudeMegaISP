# Circuito de Mejora Continua — seguridad de los tokens de acceso externo

Los endpoints externos (Claude Cowork, fuera de la red) llevan el token **en el path**:

- Lectura: `GET  /api/roadmap-externo/{token}`
- Escritura POST: `POST /api/roadmap-externo/{token}/item/{id}`
- Escritura GET: `GET  /api/roadmap-externo/{token}/item/{id}/set?...`

Tokens en `.env` (`ROADMAP_EXTERNAL_READ_TOKEN`, `ROADMAP_EXTERNAL_WRITE_TOKEN`), nunca en git.

## Hallazgo del auditor: el token viaja en el path y se filtra a logs

- **Lado Laravel: ya mitigado.** El canal `roadmap_externo` NO loguea el token: en un
  acceso válido registra verbo/resultado/campos; en un `denied` solo `token_prefix`
  (6 caracteres). No hay que tocar nada aquí.
- **Lado nginx: SÍ se filtra.** El `access_log` por defecto registra `$request`, que
  incluye el URI completo → el token queda en texto plano en
  `/var/log/nginx/*access.log`. Esto es lo que hay que mitigar.

## Mitigación A (recomendada) — enmascarar el token en el access log de nginx

Requiere `sudo`. Crear `/etc/nginx/conf.d/roadmap-log-mask.conf` (se incluye en el
contexto `http` por defecto):

```nginx
# Reemplaza el segmento del token por *** en el URI que se va a loguear.
map $request_uri $roadmap_masked_uri {
    default                                           $request_uri;
    ~^(?<pre>/api/roadmap-externo/)[^/]+(?<post>.*)$  "${pre}***${post}";
}

# log_format que usa el URI enmascarado en vez de $request.
log_format masked_roadmap '$remote_addr - $remote_user [$time_local] '
                          '"$request_method $roadmap_masked_uri $server_protocol" '
                          '$status $body_bytes_sent "$http_referer" "$http_user_agent"';
```

Y en el server block de `megaisp.conf` (dentro de `server { ... }`):

```nginx
access_log /var/log/nginx/megaisp-access.log masked_roadmap;
```

Aplicar:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

Verificación: hacer un request a `/api/roadmap-externo/<token>/...` y confirmar que en
`/var/log/nginx/megaisp-access.log` aparece `/api/roadmap-externo/***/...` (el resto del
path y los query params —no secretos— se conservan). Ojo: los logs **anteriores** al
cambio ya contienen tokens en claro → rotar los tokens (abajo) tras aplicar esto.

## Mitigación B (obligatoria como práctica) — rotación periódica de tokens

Aunque se enmascare el log, el token viaja en el path y puede quedar en históricos,
proxies intermedios o el historial del fetcher. Por eso se **rota periódicamente**:

- **Cadencia:** cada 90 días, y de inmediato ante cualquier sospecha de filtración o
  al cerrar acceso a alguien que lo conoció.
- **Cómo rotar (sin downtime del sitio):**
  1. Generar tokens nuevos, p.ej. `openssl rand -hex 32` (uno para read, otro para write).
  2. Editar `.env` de la instancia: `ROADMAP_EXTERNAL_READ_TOKEN=` / `ROADMAP_EXTERNAL_WRITE_TOKEN=`.
  3. `php artisan config:cache` (los tokens se leen vía `config()`, así que el cache
     cacheado viejo seguiría sirviendo el token anterior hasta regenerarlo).
  4. Actualizar el token guardado en la configuración de Claude Cowork.
  5. El token viejo deja de validar en cuanto se regenera el cache → sin ventana de solape.
- **No** commitear los tokens (viven solo en `.env`).

## Nota de diseño (futuro, opcional)

Mover el token de escritura del path a un **header** (`Authorization`/`X-Roadmap-Token`)
lo sacaría de los access logs de raíz. Se descartó por ahora porque el fetcher de Cowork
solo hace GET simples sin headers personalizados; si en el futuro los soporta, es la
mitigación de fondo.

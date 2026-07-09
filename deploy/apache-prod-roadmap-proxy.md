# Reverse proxy PROD (Apache2) → DEV para el Circuito de Mejora Continua

**PROD** (`v1megaisp.meganett.com.mx`) corre **Apache2** (no nginx) y es alcanzable
desde internet con HTTPS + certificado válido. El firewall de la red bloquea 80/443
entrantes hacia **DEV** (`192.168.105.11`). Solución: PROD publica **solo** el prefijo
`/api/roadmap-externo/` y lo reenvía a DEV por la LAN. Nada más de DEV se expone.

> ⚠️ **PRE-CHECK CRÍTICO (desde PROD, antes de todo):** el proxy exige que PROD alcance
> a DEV en el puerto 80 por la LAN.
> ```bash
> curl -sI http://192.168.105.11/robots.txt      # debe dar 200
> ```
> Si da timeout/refused → el proxy dará 502; NO continuar (habilitar esa ruta LAN primero).

---

## Paso 1 — Ubicación (Irving en PROD, por PuTTY)

```bash
# VirtualHost 443 de v1megaisp:
grep -rniE "server(name|alias).*v1megaisp|<VirtualHost .*:443" /etc/apache2/sites-enabled/
# Módulos ya activos:
apache2ctl -M 2>/dev/null | grep -E "proxy|headers|ssl|setenvif"
```
Anotar: (1) el archivo del vhost 443, (2) qué módulos de `proxy proxy_http headers` faltan.

## Paso 2 — Módulos (solo los que falten en el Paso 1)

```bash
sudo a2enmod proxy proxy_http headers
# (setenvif viene activo por defecto; ssl ya está porque hay 443)
sudo systemctl reload apache2
```

## Paso 3 — Snippet DENTRO del `<VirtualHost *:443>` de v1megaisp

Pegar junto a las demás directivas del vhost (NO dentro de un `<Directory>`):

```apache
    # ── Circuito de Mejora Continua — proxy SOLO de este prefijo hacia DEV ──
    # ProxyPass intercepta ANTES del DocumentRoot/Laravel → el Laravel de PROD
    # NUNCA ve este path. El token va en el path y se preserva íntegro (ambos lados
    # llevan el MISMO sufijo /api/roadmap-externo/).
    ProxyPreserveHost On
    <Location "/api/roadmap-externo/">
        ProxyPass        "http://192.168.105.11/api/roadmap-externo/" connectiontimeout=10 timeout=30
        ProxyPassReverse "http://192.168.105.11/api/roadmap-externo/"
        RequestHeader set X-Forwarded-Proto "https"
        RequestHeader set X-Real-IP "expr=%{REMOTE_ADDR}"
        # X-Forwarded-For lo añade mod_proxy_http automáticamente.
    </Location>

    # LOG: excluir estas peticiones del access log de PROD (el token va en el path).
    # Apache no tiene map/log_format con regex como nginx → se marca la petición y se
    # EXCLUYE del CustomLog. La auditoría REAL vive en DEV (canal roadmap_externo,
    # storage/logs/roadmap-externo-*.log) → no se pierde rastro.
    SetEnvIf Request_URI "^/api/roadmap-externo/" roadmapreq
```

**Y editar la línea `CustomLog` EXISTENTE del vhost** añadiendo `env=!roadmapreq` al final
(si solo se agrega un CustomLog nuevo, el viejo seguiría logueando el token):

```apache
#  Antes:   CustomLog ${APACHE_LOG_DIR}/v1megaisp-access.log combined
#  Después: CustomLog ${APACHE_LOG_DIR}/v1megaisp-access.log combined env=!roadmapreq
```

### Decisiones del snippet
- **ProxyPreserveHost On (recomendado):** DEV es `server_name _` (default_server) y acepta
  cualquier Host, así que da igual funcionalmente; se usa `On` para que DEV vea el host
  público real, consistente con `X-Forwarded-Proto https`. (Con `Off` DEV vería `192.168.105.11`;
  también válido. No es crítico.)
- **Rate limit:** vive en DEV (throttle de Laravel). NO se duplica aquí.
- **`ProxyRequests`** queda `Off` (default) — es reverse proxy, no forward.

## Paso 4 — Validar y recargar

```bash
sudo apache2ctl configtest && sudo systemctl reload apache2
```

## Paso 5 — Verificación

Desde DEV (o cualquier lado con internet):
```bash
curl -sS "https://v1megaisp.meganett.com.mx/api/roadmap-externo/<READ_TOKEN>" | head -c 300
```
Debe devolver el JSON del circuito (`generated_at`, `manual_criterios`, `items[...]`).

### Si Laravel de PROD capturara el path (no debería)
Con `<Location>` + `ProxyPass` el proxy gana sobre el DocumentRoot y sobre el `.htaccess`
de Laravel (esos rewrites son per-directory y corren después). Si la verificación devolviera
HTML/login en vez de JSON, agregar al inicio del vhost, antes de cualquier rewrite de la app:
```apache
    RewriteEngine On
    RewriteRule ^/api/roadmap-externo/ - [END]
```

## Nota robots.txt
El fetcher pide `https://v1megaisp.meganett.com.mx/robots.txt`, servido por **PROD** (NO se
proxya). Verificar permisivo: `curl -s https://v1megaisp.meganett.com.mx/robots.txt`. Si PROD
tuviera `Disallow: /`, tratarlo aparte (es SEO de producción; no se toca a la ligera).

---

## Consultas del circuito vía PROD (para Claude Cowork)

**Base lectura:** `https://v1megaisp.meganett.com.mx/api/roadmap-externo/<READ_TOKEN>`
**Base escritura (GET/set):** `https://v1megaisp.meganett.com.mx/api/roadmap-externo/<WRITE_TOKEN>/item/{id}/set`

La respuesta se modela por MODOS para no truncar (el manual pesa ~134 KB y va aparte).

### Lectura
| Consulta | URL |
|----------|-----|
| Resumen (conteos por estado/nivel) + primera tanda compacta | `…/<READ_TOKEN>` |
| Pendientes nivel A | `…/<READ_TOKEN>?estado=pendiente_revision&nivel=A` |
| Siguiente tanda (paginado) | `…/<READ_TOKEN>?solo=items&page=2&per_page=50` |
| Filtrar por módulo | `…/<READ_TOKEN>?solo=items&modulo=Talento` |
| Detalle completo de un item (description, prompt, log) | `…/<READ_TOKEN>?id=211` |
| Solo el manual (respuesta pesada, una vez) | `…/<READ_TOKEN>?solo=manual` |

- **default** = `resumen` + `leyenda` + `_ayuda` + `meta`(paginación) + `items`(compacto), SIN manual.
- **compacto** = `id,title,modulo,nivel_riesgo,estado_aprobacion,priority,status`.
- `per_page` def 50, máx 100. `meta` trae `total`, `page`, `total_pages`.
- Parámetro inválido → **422 JSON** (no redirige).

### Escritura (asignar aprobación)
| Acción | URL |
|--------|-----|
| Aprobar para auto-ejecución (SOLO nivel A) | `…/<WRITE_TOKEN>/item/{id}/set?estado_aprobacion=aprobado_claude` |
| Validado, falta Irving (B y C) | `…/<WRITE_TOKEN>/item/{id}/set?estado_aprobacion=requiere_irving&comentarios_claude=<texto>` |
| Endurecer nivel de riesgo | `…/<WRITE_TOKEN>/item/{id}/set?nivel_riesgo=C` |

- Guards server-side: `aprobado_claude` **solo** si el item es nivel A; B/C topan en `requiere_irving`.
  El `nivel_riesgo` solo se puede **endurecer** (A→B→C), nunca degradar. Violación → 422.
- El token va en el path (enmascarado en el log de prod; auditoría real en dev).

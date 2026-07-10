---
name: circuito-cc
description: >
  Memoria operativa del "Circuito CC" del proyecto MegaISP: el lazo cerrado
  Claude (Cowork) ⇄ Hoja de Ruta (Roadmap) ⇄ Claude Code, con la API externa
  del roadmap como memoria compartida. Usar SIEMPRE que la tarea mencione el
  "Circuito CC", el "circuito cc", la "Hoja de Ruta", el "Roadmap" de MegaISP,
  leer o escribir items del roadmap, la API `roadmap-externo`, o coordinar
  trabajo entre Cowork y Claude Code sobre MegaISP — aunque el usuario (Irving)
  no la nombre explícitamente. Cubre la topología dev/prod, el guardrail de
  producción, los endpoints de la API, el flujo del lazo y cómo leer/escribir
  el roadmap desde una sesión con red permitida. Combínala con la skill
  `megaisp-conventions`.
---

# Circuito CC — memoria operativa

El **Circuito CC** es un lazo cerrado de trabajo entre tres piezas, con la
**Hoja de Ruta (módulo Roadmap de MegaISP)** como memoria y fuente de verdad
compartida. Su gracia: ninguna pieza depende de recordar la sesión anterior —
todo el estado vive en la Hoja de Ruta.

## El lazo

1. **Irving + Claude (Cowork)** proponen y nutren una idea; dejan indicaciones listas.
2. Esas indicaciones se registran como **item en la Hoja de Ruta**.
3. **Claude Code (CC)** recibe la orden, ejecuta en el servidor de dev y **escribe su reporte de vuelta en la Hoja de Ruta**.
4. **Claude (Cowork)** lee ese reporte desde la Hoja de Ruta y deja la siguiente tarea.
5. **Cowork → CC** le indica a Claude Code que ejecute esa tarea.
6. El circuito gira indefinidamente.

## Topología — dev y prod son MÁQUINAS DISTINTAS (guardrail crítico)

- **DEV** (donde trabaja el circuito):
  - IP local `192.168.105.11` · IP pública `38.123.192.199`
  - Dominio `dev.meganett.com.mx` (HTTPS/443 con cert Let's Encrypt)
  - Base de datos **propia** (local, 127.0.0.1)
- **PROD** (vivo al público — **NUNCA TOCAR**):
  - IP local `192.168.105.108` · IP pública `38.123.192.198`
  - Dominio `v1megaisp.com.mx` · base de datos **propia** separada

**Guardrail duro:** trabajar SOLO en la máquina de dev. Nunca conectar ni
escribir hacia `192.168.105.108` / `v1megaisp.com.mx` ni su BD. `192.168.105.11`
es la IP local de dev (no es prod); un error común es confundir un vhost del
propio server de dev con prod.

## La API externa de la Hoja de Ruta

- **Base URL:** `https://dev.meganett.com.mx/api/roadmap-externo`
- **Auth: token en el PATH** (no header `Authorization`), porque el fetcher de
  Cowork no puede mandar cabeceras. Hay dos tokens: **RO** (lectura) y **RW**
  (escritura acotada). Comparación timing-safe, rate limit por verbo, cada
  acceso auditado en el canal `roadmap_externo`.
- **Los tokens son secretos y NO están en esta skill.** Irving los provee al
  inicio de la sesión, o los inyecta un conector MCP. Nunca los escribas en
  archivos versionados, logs, ni PRs.

### Endpoints (con `{RO}` o `{RW}` = el token correspondiente)

Lectura (token RO, ~60/min):
- `GET /{RO}` — resumen + lista paginada. Query: `solo=manual|items`, `id`, `estado`, `nivel`, `modulo`, `page`, `per_page` (máx 100).
- `GET /{RO}/item/{id}` — detalle completo de un item (variante PATH, a prueba de fetchers).
- `GET /{RO}/q/{estado}/{nivel}/{page}/{perpage}` — lista filtrada por PATH; `-` = comodín.

Escritura (token RW, ~30/min), **acotada a 3 campos**: `estado_aprobacion`, `nivel_riesgo`, `comentarios_claude`:
- `POST /{RW}/item/{id}` — body con los 3 campos.
- `GET /{RW}/item/{id}/set?estado_aprobacion=..&nivel_riesgo=..&comentarios_claude=..` — escritura por query.
- `GET /{RW}/item/{id}/set/{estado}/{nivel}/{comentario?}` — escritura por PATH; `-` = no cambiar.

Enums:
- `nivel_riesgo`: `A` `B` `C`
- `estado_aprobacion`: `pendiente_revision` `aprobado_claude` `requiere_irving` `rechazado` `en_progreso` `completado`

Guards server-side: solo nivel `A` puede quedar `aprobado_claude`; `nivel_riesgo`
solo se endurece (A→B→C), nunca degrada; B/C topan en `requiere_irving`.

**Limitación actual (deuda):** la escritura pisa `comentarios_claude` y **no
crea items ni guarda historial de reportes**. Extender esto (endpoints de
creación + `roadmap_item_reports`) es la "Opción 2" pendiente.

## Cómo leer/escribir el roadmap desde una sesión

Requisito: estar en una sesión/entorno con **red permitida hacia
`dev.meganett.com.mx`** (allowlist "Custom" del entorno; aplica a sesiones
nuevas). Desde ahí, usar **Bash + `curl`** (ignora `robots.txt`, a diferencia
del lector web). Ejemplo de lectura de un item:

```
curl -s -w "\nHTTP:%{http_code}\n" \
  "https://dev.meganett.com.mx/api/roadmap-externo/<TOKEN_RO>/item/300"
```

Si `curl` devuelve `HTTP:000` con "CONNECT tunnel failed, response 403", el
dominio no está permitido en ese entorno: hay que agregar `dev.meganett.com.mx`
al allowlist del entorno (Network access → Custom) y abrir una sesión nueva.

## Convenciones del proyecto

Aplica siempre la skill **`megaisp-conventions`** (git selectivo, Paso 0 de
confirmación, permisos Spatie, fechas legacy DD/MM/YYYY, caché/warm-up, etc.).
Todo pendiente, bug o decisión diferida se registra de inmediato como item en
la Hoja de Ruta. Respuestas y commits en español.

## Pendientes vigentes del circuito

1. Allowlistear `dev.meganett.com.mx` en el entorno de Cowork que actúe como lector.
2. Exentar de la **detección de DOS del MikroTik** el tráfico a `dst=38.123.192.199 dport=443` (o whitelistear el rango de salida de Anthropic), para que el polling del circuito no se auto-blocklistee.
3. **Renovar el cert** de `dev.meganett.com.mx` antes de su expiración (se emitió con `--manual`, no auto-renueva): repetir certbot DNS-01 o montar hook.
4. Extender la API de escritura (Opción 2): crear items + historial de reportes.

## Infraestructura de referencia

- Proyecto Laravel en `/var/www/megaisp` (docroot `/var/www/megaisp/public`), vhost dev `megaisp-dev-tls.conf`.
- DNS del dominio en Neubox (cPanel Zone Editor). Cert Let's Encrypt vía DNS-01 (registro TXT `_acme-challenge.dev`).
- MikroTik de borde: regla forward que permite `tcp dst=38.123.192.199 dport=80,443`.

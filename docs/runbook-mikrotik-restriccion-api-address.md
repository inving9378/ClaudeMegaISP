# Runbook — Restringir origen del servicio `/ip service api` en MikroTik (item roadmap #979)

**Ejecutor:** Irving o el equipo de red (NOC). **NO se ejecuta desde el circuito ni desde ningún
comando de MegaISP** — decisión explícita de Irving al aprobar el item #979 (ver
`comentarios_claude` del item: preguntas q1/q2/q3, todas resueltas a favor de la opción manual
y con pilotaje). Este documento es la guía paso a paso para aplicarlo.

## 1. Contexto del hallazgo

El servicio `/ip service api` (usado por `pear2/net_routeros` para que MegaISP se conecte a cada
MikroTik — sync de clientes, PPPoE, address-lists, etc.) se configura por router en la tabla
`mikrotiks` (`login_api`/`password_api`/`port_api`). En al menos el router de referencia usado en
los items #811/#951, ese servicio está habilitado **sin restricción de `address`** (el campo que
en RouterOS limita desde qué IP/rango se acepta la conexión). Cualquier host que alcance el puerto
configurado puede intentar autenticarse contra la API del router.

Esto es un control **a nivel de servicio** (`/ip service set api address=...`), distinto — y
complementario — del control a nivel de **firewall** que ya existe parcialmente en el código de
MegaISP (ver sección 5, "Hallazgo relacionado").

## 2. Paso 0 — Inventariar routers expuestos

Antes de tocar cualquier equipo, correr en cada router (vía Winbox/terminal, **no** vía la API que
se está auditando, para no depender del control que aún no existe):

```
/ip service print
```

Anotar, para cada servicio (`api`, `api-ssl`, `winbox`, `ssh`, `www` si aplica):
- `port`
- `address` (vacío = sin restricción = expuesto)
- `disabled` (si ya está deshabilitado, no aplica)

**Alternativa desde MegaISP (opcional, solo lectura):** el comando
`php artisan mikrotik:audit-api-exposure` (agregado en este mismo item, ver sección 4) hace este
inventario automáticamente contra los routers marcados `active=true` en la tabla `mikrotiks`,
reusando la misma conexión API que ya usa el resto del sistema. Es de **solo lectura** (nunca
llama `/set` ni `/remove`), pero sigue dependiendo de la API — para el router que se sabe expuesto,
usar primero el `/ip service print` manual de arriba.

## 3. Paso 1 — Definir la whitelist de IPs de gestión

Reunir **todas** las IPs que legítimamente necesitan hablar con `/ip service api` de cada router:
- IP pública (o rango) del servidor MegaISP (dev **y** prod — son máquinas distintas).
- Cualquier jump host / IP de NOC que administre el router directamente por API.
- Si hay balanceo o failover de servidor, **todas** las IPs de salida posibles.

⚠️ Antes de aplicar: confirmar contra `mikrotik_config_server_*` y `meganet_config_ip_address`
(tabla `mikrotik_configs`, ficha del router en `/red/router/...`) qué IP tiene registrada MegaISP
para ESE router — es el mismo valor que ya usa la regla de firewall existente
`MgNet_INPUT_MEGANET_TO_API_ACCEPT` (ver sección 5). Si esa IP está desactualizada, corregirla ahí
también.

## 4. Paso 2 — Aplicar el cambio (MANUAL, un router piloto primero)

En **un solo router piloto** (no todos a la vez — así lo decidió Irving en la pregunta q2):

```
/ip service set api address=<IP_o_rango_whitelist>
```

Repetir para los demás servicios de gestión expuestos que se quieran restringir de la misma forma
(`winbox`, `ssh`, `www`), evaluando caso por caso si aplica en ese router.

**Verificación inmediata post-cambio (antes de tocar el siguiente router):**
1. `php artisan check_conection_mikrotik:process` (o el comando de sync habitual,
   `app:mikrotik-sync-command`) — confirmar que MegaISP sigue conectando.
2. Probar en la UI de MegaISP una operación real contra ese router (alta/edición de un servicio de
   cliente que use ese router, o el botón "Probar conexión" del módulo Router si existe).
3. Confirmar que **no** se perdió acceso de gestión (Winbox/SSH) al equipo — si el cambio incluyó
   esos servicios, probar login desde una IP de la whitelist antes de cerrar la sesión actual.

Si algo falla: revertir con `/ip service set api address=` (vacío) en ese mismo router y ajustar la
whitelist antes de reintentar. Solo tras confirmar el piloto sano, continuar con el resto de los
routers (rollout, uno por uno o en lotes pequeños — no todos de golpe).

## 5. Hallazgo relacionado (NO se toca en este item — registrado para revisión aparte)

Durante la investigación se encontró que `App\Jobs\Mikrotik\MikrotikRulesJob` (que se dispara al
guardar/activar la config de un MikroTik) ya arma una regla de firewall
`MgNet_INPUT_MEGANET_TO_API_ACCEPT` (`/ip firewall filter`, chain `input`, `accept` solo desde
`meganet_config_ip_address` hacia el puerto de la API) — es decir, el código **ya tiene** la
intención de restringir el acceso a nivel de firewall, no solo a nivel de servicio.

Sin embargo, `RouterConnection::addRulesInputDorpRest()` (la regla que **dropea el resto** del
chain `input` tras las reglas de `accept`) está **definida pero nunca se invoca** desde
`MikrotikRulesJob::handle()`. Sin esa regla final, el chain `input` de RouterOS queda con su
política implícita (`accept`), así que la regla de `accept`-solo-desde-MegaISP es una excepción
dentro de un chain que de todos modos deja pasar todo lo demás — no restringe nada en la práctica.

Esto **no se corrige en este item** porque activar esa regla afectaría el firewall de TODOS los
routers en cuya config se dispare `MikrotikRulesJob` (incluyendo producción), y las reglas
`accept` actuales no cubren explícitamente Winbox/SSH — agregar el `drop` del resto sin antes
auditar qué más necesita pasar por `input` podría cortar la gestión remota de los equipos. Queda
registrado como sub-item de seguimiento para que se evalúe con el mismo cuidado (piloto + rollback)
que este item.

## 6. Registro de que se aplicó

Tras aplicar en producción, documentar en este mismo archivo (o en la bitácora de sesiones) qué
routers quedaron restringidos, con qué whitelist y fecha — para que quede trazable sin depender de
memoria.

## 7. Inventario de servicios de gestión para `enforce_input_drop_rest` (item roadmap #1037)

Prerequisito para activar el mecanismo de la sección 5 (`MgNet_INPUT_DROPEA_EL_RESTO`,
`config('mikrotik.enforce_input_drop_rest')`) en un router real sin cortar acceso administrativo.
**Sigue INACTIVO** (kill-switch global en `false`, ninguna variable `MIKROTIK_MANAGEMENT_ALLOWLIST_*`
poblada) — esta sección solo documenta qué falta y cómo se conecta cuando Irving entregue la lista.

**Qué necesita entregar Irving (frontera dura — nadie más la puede completar, no es derivable del
código):**
- IP(s) desde donde se administra Winbox (puerto 8291) de cada router.
- IP(s) desde donde se administra SSH (puerto 22).
- Rango(s) de monitoreo/NOC (Zabbix/LibreNMS/pings de salud, etc.) si necesitan alcanzar el router
  por `input` fuera del servicio de la API ya cubierto por `meganet_config_ip_address`.
- Cualquier otro servicio de gestión que hoy dependa de la política implícita `accept` del chain
  `input` (ej. `www` del router, si se administra por navegador).

**Dónde se captura una vez que Irving la dé** — `config/mikrotik.php` →
`management_services.{winbox,ssh,monitoreo}.allowed_addresses`, poblado vía `.env`:
```
MIKROTIK_MANAGEMENT_ALLOWLIST_WINBOX=<IP_o_CIDR>,<IP_o_CIDR>
MIKROTIK_MANAGEMENT_ALLOWLIST_SSH=<IP_o_CIDR>,<IP_o_CIDR>
MIKROTIK_MANAGEMENT_ALLOWLIST_MONITOREO=<IP_o_CIDR>,<IP_o_CIDR>
```

**Qué hace el código con esa lista (ya implementado, código muerto mientras la lista esté vacía):**
- `RouterConnection::addRulesInputManagementAccept()` agrega un `accept` en el chain `input` por
  cada IP/rango configurado, ANTES de que `MikrotikRulesJob` evalúe instalar el drop-resto. Solo
  corre si el kill-switch global está en `true`.
- `MikrotikRulesJob::shouldEnforceInputDropRest()` ahora exige, además de los 3 candados de la
  sección 5, que **cada servicio marcado `critical` (`winbox`, `ssh`)** tenga al menos una IP en
  `allowed_addresses`; si falta alguno, registra un `Log::warning` con el motivo y **no** instala
  `MgNet_INPUT_DROPEA_EL_RESTO` en ese router (falla-segura, igual patrón que el candado de
  `meganet_config_ip_address`).

**Aún pendiente tras recibir la lista de Irving (fuera de alcance de este item, frontera dura):**
piloto en un solo router no crítico + verificación de acceso de gestión antes de tocar el resto,
mismo criterio que la sección 4.

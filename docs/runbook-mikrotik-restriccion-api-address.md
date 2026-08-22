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

## 8. Runbook de piloto + rollback para activar `enforce_input_drop_rest` (item roadmap #1038)

**Ejecutor:** Irving o el equipo de red (NOC), igual que las secciones 2 y 4 de este documento. **NO
se ejecuta desde el circuito** — activar el drop-resto del chain `input` en un router real es
frontera dura (cambia comportamiento de firewall en producción). Este documento es solo la guía;
aprobado por Irving el 2026-08-22 (item #1038, respuestas q1/q2/q3, las tres a favor de la opción
recomendada: runbook-only sin tocar routers, criterio de rollback por umbral, rollout gradual por
lotes). **Este sub-item es solo documentación/planeación — no activa nada en ningún router.**

### 8.1 Precondición bloqueante (no saltar)

El mecanismo tiene **4 candados** (ver `MikrotikRulesJob::shouldEnforceInputDropRest()`,
`app/Jobs/Mikrotik/MikrotikRulesJob.php:401-449`) y los 4 deben pasar para que un router instale
`MgNet_INPUT_DROPEA_EL_RESTO`:
1. Kill-switch global `config('mikrotik.enforce_input_drop_rest')` (env
   `MIKROTIK_ENFORCE_INPUT_DROP_REST`) — hoy **`false`**.
2. Flag por router `mikrotik_configs.enforce_input_drop_rest` — hoy **`false`** en todos (no hay UI
   para editarlo todavía; se togglea por DB/tinker, ver 8.3).
3. `mikrotik_configs.meganet_config_ip_address_enable` + `meganet_config_ip_address` no vacíos (la
   única IP que la regla `accept` deja pasar hacia la API).
4. **El inventario de la sección 7 (`config('mikrotik.management_services')`) debe traer al menos
   una IP/rango en `allowed_addresses` para `winbox` y `ssh`** (los dos marcados `critical`).
   Mientras Irving no entregue esas IPs (sección 7, "Qué necesita entregar Irving"), este candado
   **siempre falla** y el mecanismo no se activa en ningún router aunque los otros 3 estén en
   `true` — es la precondición real de este runbook, no solo un "nice to have". **Sin esto, no hay
   piloto que lanzar.**

### 8.2 Paso 0 — Activar el kill-switch global (una sola vez, no es el piloto)

Con el inventario de la sección 7 ya poblado (`.env`: `MIKROTIK_MANAGEMENT_ALLOWLIST_WINBOX`,
`_SSH`, `_MONITOREO`) y `php artisan config:clear` corrido:

```
MIKROTIK_ENFORCE_INPUT_DROP_REST=true
```

Esto por sí solo **no** instala el drop en ningún router — cada router sigue necesitando su propio
flag en `true` (candado 2) para calificar. Es seguro dejarlo en `true` mientras ningún router tenga
su flag propio activado (equivale al estado actual: candado 1 pasa, candado 2 sigue bloqueando).

### 8.3 Paso 1 — Piloto: 1-2 routers NO críticos (decisión q3 — rollout gradual por lotes)

Elegir 1-2 routers **no críticos** (ejemplo: sin clientes activos, o de menor impacto si algo sale
mal) y activar SOLO ahí el flag por router. No existe UI para este campo todavía, se hace por
tinker:

```php
$router = \App\Models\Router::find(<ID_DEL_ROUTER_PILOTO>);
$router->mikrotikconfig->enforce_input_drop_rest = true;
$router->mikrotikconfig->save();
```

Luego **disparar `MikrotikRulesJob` para ese router** — el flag solo,en la tabla, no toca el
router hasta que el job corre. Dos formas de dispararlo:
- Botón **"Crear las reglas iniciales del Router"** en `/red/router/editar/{id}` (mismo botón que
  ya usa el equipo para las reglas base; internamente llama `POST /create-rules-by-router/{id}` →
  `MikrotikController::getMikrotikCreateRules` → `MikrotikRulesJob::dispatchAfterResponse`).
- Guardar el router desde el formulario de edición (dispara el mismo job vía
  `MikrotikObserver::updated`).

**Ventana de observación: 24-72h** (decisión q3) antes de tocar el siguiente lote. Durante la
ventana, monitorear:
- Que MegaISP siga sincronizando ese router (`php artisan check_conection_mikrotik:process` o el
  comando de sync habitual — mismo chequeo que la sección 4).
- Que el acceso de gestión (Winbox/SSH) siga funcionando **desde las IPs que se pusieron en el
  inventario de la sección 7** — probar login real, no asumir.
- Logs de error / reportes de clientes de ese router (ver criterio de aborto, 8.4).

Solo tras confirmar el piloto sano en el/los 1-2 routers, continuar el rollout **por lotes**
(siguiente decisión q3): **10% de la flota → 50% → 100%**, repitiendo el mismo patrón
(activar flag por router del lote → disparar el job → ventana de observación) antes de avanzar al
siguiente lote. No es big-bang: un lote fallido no debe arrastrar al resto.

### 8.4 Criterio de aborto / rollback (decisión q2)

**Abortar y hacer rollback del lote/router en cuanto ocurra CUALQUIERA de:**
- Un cliente reporta pérdida de conectividad que corresponda a ese router.
- El error rate en los logs relevantes (sync MikroTik, conexión API) sube **más de 5%** respecto al
  baseline de la 1h previa a la activación de ese router/lote.

(Umbral fijado como conservador por falta de datos históricos — decisión q2 de Irving; si con el
tiempo se junta suficiente histórico de error rate normal, se puede afinar.)

### 8.5 Cómo se hace el rollback (por router)

**Togglear el flag por router a `false` revierte de inmediato en el siguiente ciclo del job** —
gracias a la limpieza simétrica ya implementada en la Fase 1 (`MikrotikRulesJob::handle()`,
`app/Jobs/Mikrotik/MikrotikRulesJob.php:164-175`): cuando `shouldEnforceInputDropRest()` da `false`
pero el **kill-switch global sigue en `true`**, el job llama `removeById(...)` sobre la regla
`MgNet_INPUT_DROPEA_EL_RESTO` de ESE router — no hace falta borrar nada a mano en Winbox/terminal.

```php
$router = \App\Models\Router::find(<ID_DEL_ROUTER_A_REVERTIR>);
$router->mikrotikconfig->enforce_input_drop_rest = false;
$router->mikrotikconfig->save();
```

…y disparar el job otra vez (mismo botón/guardado que en 8.3) para que el `elseif` de limpieza
corra contra el router real. **No es instantáneo en el router hasta que el job corre** — el toggle
por sí solo en la tabla no cambia nada en RouterOS.

⚠️ **Caveat verificado en el código (no es folclore):** esa limpieza automática depende de que el
**kill-switch global siga en `true`** al momento de correr el job — si en vez de togglear el flag
del router se apaga `MIKROTIK_ENFORCE_INPUT_DROP_REST` (candado 1) a `false` global, el `elseif`
tampoco corre (su condición es la misma bandera global) y la regla `MgNet_INPUT_DROPEA_EL_RESTO` que
ya esté instalada en los routers **se queda tal cual, sin limpiar**, hasta que se revierta por
router como arriba. Para el rollback de un piloto/lote puntual: togglear el flag **por router**, no
el kill-switch global. El kill-switch global solo se apaga como freno de emergencia final, y en ese
caso cualquier router que ya tuviera la regla instalada requiere el toggle por-router (con el global
ya reactivado) para limpiarla — dejarlo documentado aquí para que nadie asuma "apagué el global y ya
quedó limpio".

### 8.6 Mecanismo del enforcement: firewall (este item) vs `/ip service address=` (#979) — trade-offs

Pendiente de **decisión de Irving** (no se decide en este documento, solo se dejan los tradeoffs
para que la tome con datos):

| | Firewall (`enforce_input_drop_rest`, este item) | Service address (`/ip service set address=`, item #979) |
|---|---|---|
| Nivel | Chain `input` completo — corta TODO lo que no esté explícitamente aceptado antes | Por servicio individual (api, winbox, ssh, www…) |
| Granularidad | Gruesa: una sola regla final protege todos los servicios de golpe | Fina: hay que configurar `address=` en cada servicio por separado |
| Riesgo de mal config | Alto blast radius si falta un `accept` (puede cortar gestión completa) — por eso el candado 4 (inventario sección 7) es obligatorio | Bajo blast radius: un servicio mal configurado no afecta a los demás |
| Cobertura | Cubre servicios futuros no contemplados hoy (todo lo no-aceptado se dropea) | Solo cubre los servicios que se configuren explícitamente |
| Estado actual | Código listo, 4 candados, inactivo (sección 8.1) | Runbook manual sección 2-4, pendiente de piloto por Irving/NOC |
| Ambos en capas | Defensa en profundidad: el `address=` reduce superficie por servicio, el firewall dropea cualquier cosa no contemplada | — |

**Placeholder explícito:** falta que Irving decida si el rollout final usa uno de los dos mecanismos
o ambos en capas. Este runbook queda listo para ejecutarse en cuanto esa decisión y el inventario de
la sección 7 estén disponibles; mientras tanto no bloquea nada más (el kill-switch global sigue en
`false`, comportamiento actual intacto).

### 8.7 Registro de que se ejecutó

Igual que la sección 6: tras correr un piloto o lote real en producción, documentar aquí (o en la
bitácora de sesiones) qué routers quedaron con el flag activo, fecha, resultado de la ventana de
observación y si hubo rollback.

# Módulo VoIP

> `app/Modules/Addons/VoIP/` (módulo addon `addon-voip`, id=210) + pieza compartida `app/Modules/Core/Voice/` (gateway AMI/ARI único) · rutas bajo `/voip/*`, permisos `voip.*`.

## 0. En simple
Es el conmutador telefónico del sistema: da de alta los troncales (líneas SIP de los proveedores), las extensiones internas de cada empleado y a quién le timbra cada llamada entrante, y lo aplica directo en Asterisk sin que nadie tenga que tocar archivos de configuración a mano.

## 1. Qué es
Un módulo addon que administra la telefonía IP (VoIP) de MegaISP sobre **Asterisk** vía **PJSIP Realtime**: troncales (líneas hacia proveedores SIP), extensiones (internos de usuarios), grupos de timbrado (a quién suena una llamada entrante) y un asistente de IA telefónico en construcción parcial (config + bitácora de conversaciones/leads, sin el enrutamiento de llamada en vivo todavía expuesto por HTTP).

## 2. Para qué sirve
Le resuelve a los administradores de telefonía (y de forma transversal a Cobranza/Talento, que reusan el mismo gateway) tres problemas:
- **Alta/baja de líneas sin tocar Asterisk a mano**: crear una troncal o extensión en la UI escribe directo en las tablas `ps_*` (PJSIP Realtime) y dispara los reloads AMI necesarios — sin editar `pjsip.conf`/`extensions.conf` por SSH.
- **Enrutamiento de llamadas entrantes configurable**: un troncal se asocia a un grupo de timbrado (ringall o secuencial) y el sistema genera el dialplan (`megaisp_grupos.conf`) automáticamente cada vez que cambia algo.
- **Ciclo de vida del empleado ↔ su extensión**: si un usuario se bloquea/desactiva/reactiva, su extensión SIP se restringe/desprovisiona/restaura sola (vía observer), sin que alguien tenga que acordarse de hacerlo manualmente.

## 3. Cómo funciona

### 3.1 Piezas clave
- **Modelos** (`Models/`): `Troncal` (`voip_troncales`), `Extension` (`voip_extensiones`), `GrupoTimbrado` (`voip_grupos_timbrado`, pivote `voip_grupo_extension`), `IaBotConfig`/`IaBotConversation`/`IaBotLead`/`IaBotKnowledgeBase` (tablas `ia_bot_*`). `Troncal`/`Extension` cifran su `secret` con `Crypt` (mutator `setSecretAttribute`/accessor `getSecretPlainAttribute`), columna oculta del serializado.
- **`AsteriskProvisioningService`** (singleton, `Services/AsteriskProvisioningService.php`) — motor de provisión de troncales y extensiones: escribe/borra filas en `ps_aors`/`ps_endpoints`/`ps_auths`/`ps_endpoint_id_ips`/`ps_registrations` (conexión `asterisk_rt`), resuelve las IPs del proveedor por DNS (cacheadas 5 min) para `identify_by=ip,username`, y para troncales tipo `registro` mantiene un archivo estático de registración (`storage/app/asterisk/megaisp_registrations.conf`, merge/remove de bloques por sección) + `pjsip reload` vía AMI. También expone `restringirExtension`/`restaurarExtension` (cambio liviano de `context`) y `verificarRegistro` (consulta AMI `PJSIPShowRegistrationsOutbound`).
- **`DialplanGeneratorService`** (`Services/DialplanGeneratorService.php`) — genera `storage/app/asterisk/megaisp_grupos.conf` a partir de los grupos de timbrado activos y los troncales con `grupo_entrante_id`: un contexto `inbound-trunk-{id}` por troncal entrante que hace `Goto` al contexto `grupo-{id}` del grupo (estrategia `ringall` = todos timbran a la vez con `Dial` múltiple; secuencial = un `Dial` por miembro en orden de pivote), más un contexto fijo `from-internal-restringido` para usuarios bloqueados (solo internos, salientes denegados). Patrón "regenerar todo, idempotente"; también actualiza `ps_endpoints.context` de cada troncal.
- **`IABotCustomerService`** (`Services/IABotCustomerService.php`) — identifica si un número entrante es cliente existente (busca por los 3 teléfonos de `client_main_information`) o lead nuevo, y lista sus servicios activos. Sin consumidor HTTP/console todavía en el repo (pieza preparada para el flujo de llamada en vivo del bot IA, aún no cableada a un endpoint/AGI).
- **`App\Modules\Core\Voice\AmiClient`** — cliente AMI (socket crudo `fsockopen`, sin librería externa) **único** del sistema; `VoiceGateway` (troncal Servnet de CobranzaBlaster) y `AsteriskProvisioningService` lo consumen, nadie más debe abrir su propio socket AMI.
- **`UserVoipObserver`** (`app/Observers/`) — escucha `User::updated()` sobre el campo `estado`: usuario → `inactivo` desprovisiona su(s) extensión(es); → `bloqueado` las restringe (`from-internal-restringido`, sin salientes); → `activo` las re-provisiona (si venía de inactivo) o las restaura (si venía de bloqueado).
- **Comandos de reconciliación** (`Console/`): `voip:reconciliar [--dry-run]` (repara inconsistencias en las tablas `voip_*` propias, nunca toca `ps_*` ni Asterisk) y `voip:reconciliar-estados [--dry-run]` (re-sincroniza extensión ↔ estado del usuario cuando el observer no pudo correr, ej. tras un restore de BD).

### 3.2 Flujo principal (alta y provisión de una troncal)
1. Admin crea la troncal desde `/voip/troncales` → `TroncalController::store` valida y guarda en `voip_troncales`.
2. Se regenera el dialplan (`DialplanGeneratorService::regenerar`).
3. Si la troncal quedó `activo=true`, se provisiona en el acto (`AsteriskProvisioningService::provisionar`): upsert de `ps_aors`/`ps_endpoints`, y si es tipo `registro` además `ps_auths` + bloque de registración estática + `pjsip reload` por AMI; si es tipo `ip`, se limpian restos de registro previo.
4. Un troncal con `grupo_entrante_id` recibe automáticamente el contexto `inbound-trunk-{id}` (vía el paso 2), que enruta la llamada entrante al grupo de timbrado configurado.
5. Cambios posteriores a campos que afectan Asterisk (host/puerto/usuario/tipo/codecs/transporte/secret) disparan re-provisión automática en `update()`.

### 3.3 Flujo del ciclo de vida de un usuario
`User::updated()` (campo `estado` dirty) → `UserVoipObserver` busca las extensiones del usuario y aplica `AsteriskProvisioningService::desprovisionarExtension` / `restringirExtension` / `provisionarExtension` / `restaurarExtension` según la transición de estado — sin intervención manual.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Rutas HTTP** (`middleware ['web','auth']`, prefijo `/voip`, definidas en `routes.php`): CRUD + provisión de **troncales** (`/voip/troncales*`, permisos `voip.troncales.view/create/edit/delete/provision/test`), CRUD + provisión de **extensiones** (`/voip/extensiones*`), CRUD de **grupos de timbrado** (`/voip/grupos-timbrado*`), y panel del **IA Bot** (`/voip/ia-bot*`: config, conversaciones, leads, base de conocimiento — permisos `voip.ia-bot.view/config/leads/kb`).
- **Servicio singleton** `AsteriskProvisioningService` — registrado en el contenedor por `ModuleServiceProvider`, único punto de escritura de `ps_*` para troncales/extensiones VoIP.
- **Servicios de soporte** `DialplanGeneratorService`, `IABotCustomerService` (sin consumidor externo aún).
- **Comandos artisan** `voip:reconciliar` y `voip:reconciliar-estados`.
- **Observer** `UserVoipObserver` sobre el modelo `User` (reacciona a cambios de `estado`).
- **Config** `config/voip.php` (`ari_host/port/user/pass`, `ami_host/port/user/pass`, todos vía `.env`: `ASTERISK_ARI_*`, `AMI_*`).

**Consume**
- **`App\Modules\Core\Voice\AmiClient`** — gateway AMI único compartido (login + acción + logoff por conexión, stateless); VoIP es uno de sus dos consumidores conocidos (el otro es `VoiceGateway`/CobranzaBlaster).
- **Conexión de BD `asterisk_rt`** — segunda conexión (tablas `ps_aors`, `ps_endpoints`, `ps_auths`, `ps_endpoint_id_ips`, `ps_registrations`) definida en `config/database.php`, es el "realtime" que Asterisk lee directo sin reload de archivos.
- **ARI de Asterisk** (`http://{ari_host}:{ari_port}/ari/...`, Basic Auth) — usado por `TroncalController::probarConexion` para verificar conectividad.
- **`client_main_information` / `clients` / `client_internet_services`** — `IABotCustomerService` los consulta (solo lectura) para identificar clientes por teléfono.
- **`App\Models\User`** — `UserVoipObserver` escucha sus cambios de `estado`; `Extension.agente()` referencia al usuario dueño del interno.
- **Sistema de archivos local** — `storage/app/asterisk/megaisp_registrations.conf` y `megaisp_grupos.conf`, generados/editados solo por este módulo (comentario "no editar manualmente" en ambos).

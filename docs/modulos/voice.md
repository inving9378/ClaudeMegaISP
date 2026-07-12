# Módulo Voice

> `app/Modules/Core/Voice/` (`VoiceGateway`, `AmiClient`) · módulo **core**, sin `module.json` propio, sin rutas ni pantalla propia — es un servicio interno consumido por otros módulos.

## 0. En simple
Es la pieza que le habla al conmutador telefónico (Asterisk) en nombre de todo el sistema: configura la línea telefónica del proveedor y avisa si está conectada, para que otros módulos (como el marcador de cobranza) no tengan que hablarle ellos mismos.

## 1. Qué es
`Voice` es el **gateway único hacia Asterisk** (PJSIP Realtime + AMI/ARI). No es un módulo de negocio con pantalla propia: son dos clases de infraestructura —`VoiceGateway` (provisión y estado de la troncal SIP) y `AmiClient` (cliente crudo del protocolo AMI, socket `fsockopen`)— que viven en `app/Modules/Core/Voice/` y se inyectan en otros módulos vía el contenedor de Laravel.

## 2. Para qué sirve
Antes de este módulo, cada consumidor de telefonía (CobranzaBlaster, VoIP) escribía su propio socket AMI o su propia configuración de `chan_sip`, con riesgo de duplicar lógica y de pisarse entre sí en Asterisk. `Voice` centraliza:
- **Provisión de la troncal del proveedor (Servnet)** en las tablas PJSIP Realtime (`ps_endpoints`, `ps_auths`, `ps_aors`, `ps_endpoint_id_ips`), reemplazando la escritura directa de `/etc/asterisk/sip.conf` (chan_sip legacy) que usaba CobranzaBlaster.
- **Un único cliente AMI** (login/acción/logout por conexión), para que ningún módulo abra su propio socket a Asterisk.
- **Reporte de estado real** de la troncal (registrada/alcanzable) combinando ARI (estado del endpoint) y AMI (respaldo informativo), para que las pantallas de configuración de VoIP puedan mostrar "conectado sí/no" sin adivinar.

## 3. Cómo funciona

### 3.1 Piezas clave
- **`VoiceGateway`** (`app/Modules/Core/Voice/VoiceGateway.php`):
  - `configureTrunk(array $cfg)` — provisiona (idempotente, `updateOrInsert`) la troncal `servnet` en la conexión de BD dedicada `asterisk_rt`: fila en `ps_auths` (credencial en **texto plano**, es la credencial nativa de Asterisk, no una password de usuario del sistema), `ps_aors` (contacto + `qualify_frequency=60` para ping OPTIONS), `ps_endpoints` (codecs, contexto `from-servnet`, caller ID) y `ps_endpoint_id_ips` (todas las IPs A resueltas del host del proveedor, para identificación inbound). Servnet es un peer IP estático sin `REGISTER`, por lo que nunca escribe `ps_registrations` (limpia restos si los hubiera).
  - Candado `RESERVED_IDS` (`trunk_2`, `1001`-`1004`): el gateway **nunca** puede crear/pisar esos IDs, que pertenecen a otra troncal/extensiones fuera de su alcance.
  - `reloadPjsip()` — manda `Action: Reload / Module: res_pjsip.so` por AMI para que Asterisk relea el realtime tras un cambio.
  - `getRegistrationStatus()` — primero consulta ARI (`GET /ari/endpoints/PJSIP/servnet`, estado `online`/`offline`); si ARI no responde, cae a AMI (`PJSIPShowRegistrationsOutbound`) como respaldo informativo (normalmente vacío porque Servnet no registra).
  - `testConnection()` — combina lo anterior en un solo array listo para JSON (provisionado/registrado/estado/ips/warnings), usado por la pantalla de configuración VoIP.
- **`AmiClient`** (`app/Modules/Core/Voice/AmiClient.php`) — cliente AMI crudo y **stateless**: cada llamada a `action()`/`send()` abre socket, hace login, envía la acción, lee la respuesta (saltando eventos espontáneos como `FullyBooted`) y cierra la conexión. `parseEvents()` convierte una respuesta cruda en bloques clave→valor. Es el **único** cliente AMI del sistema.

### 3.2 Flujo principal (configurar la troncal desde la UI de VoIP)
1. Un admin guarda la configuración SIP en `/cobranza/voip` (`VoipConfiguracionController::store`, módulo CobranzaBlaster).
2. El controller llama `VoiceGateway::configureTrunk()` con host/puerto/credenciales → escribe/actualiza las tablas PJSIP Realtime.
3. Llama `reloadPjsip()` para que Asterisk tome el cambio sin reiniciar el proceso.
4. La pantalla consulta `testConnection()` para mostrar el estado real (provisionado/registrado/IPs/warnings).

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Servicio** `App\Modules\Core\Voice\VoiceGateway` — inyectable vía contenedor; métodos `configureTrunk()`, `reloadPjsip()`, `getRegistrationStatus()`, `testConnection()`.
- **Servicio** `App\Modules\Core\Voice\AmiClient` — cliente AMI genérico (`action()`, `send()`, `parseEvents()` estático) para cualquier módulo que necesite mandar una acción a Asterisk.
- Sin rutas HTTP ni permisos propios (no tiene `module.json`); su superficie es exclusivamente de código.

**Consume**
- **Conexión de BD `asterisk_rt`** (`config/database.php`) — tablas PJSIP Realtime de Asterisk.
- **`config/voip.php`** — `ari_host`/`ari_port`/`ari_user`/`ari_pass` (ARI) y `ami_host`/`ami_port`/`ami_user`/`ami_pass` (AMI), todos con default y sobreescribibles por `.env` (`ASTERISK_ARI_*`, `AMI_*`).
- **Asterisk** vía ARI (HTTP, `Illuminate\Support\Facades\Http`) y AMI (socket TCP crudo).

**Quién lo consume**
- **CobranzaBlaster** (`app/Modules/Addons/CobranzaBlaster/Controllers/VoipConfiguracionController.php`) — usa `VoiceGateway` para provisionar la troncal y reportar su estado desde la pantalla `/cobranza/voip`.
- **VoIP** (`app/Modules/Addons/VoIP/Services/AsteriskProvisioningService.php`) — delega en `AmiClient` para sus propias acciones AMI, en vez de duplicar el socket.

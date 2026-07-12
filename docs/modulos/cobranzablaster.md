# Módulo CobranzaBlaster

> Robocalling de cobranza por voz. `app/Modules/Addons/CobranzaBlaster/` · slug `addon-cobranza-blaster` (id 140) · addon activo.

**En simple:** es el sistema que llama por teléfono, en automático y con voz generada, a los clientes que deben dinero para recordarles que paguen.

## 0. En simple
Es el sistema que llama por teléfono, en automático y con voz generada, a los clientes que deben dinero para recordarles que paguen.

## 1. Qué es
Motor de **llamadas automáticas de cobranza** (robocall): arma campañas de morosos, origina las llamadas vía Asterisk (AMI) usando un mensaje leído por voz generada (OpenAI TTS), y registra el resultado de cada intento (contestada, ocupado, no contestó, fallida, pagada).

## 2. Para qué sirve
Le ahorra al equipo de cobranza el trabajo manual de marcar a cada cliente moroso: crea una campaña, el sistema carga automáticamente a los clientes con saldo vencido, los llama en el horario configurado, reintenta si no contestan y, si se agotan los intentos, puede escalar a la suspensión del servicio. También expone la pantalla de configuración VoIP/SIP que usa el blaster para originar llamadas.

## 3. Cómo funciona
- **Modelos/tablas:**
  - `cobranza_campanas` (`CobranzaCampana`) — una campaña: estado (`borrador→activa→pausada/completada`), ventana horaria (`hora_inicio`/`hora_fin`), `max_intentos`, `minutos_entre_intentos`, `dias_vencimiento`, mensaje de audio.
  - `cobranza_llamadas` (`CobranzaLlamada`) — una llamada por cliente dentro de una campaña: teléfono, `intentos`, estado (`pendiente→marcando→contestada/no_contesto/ocupado/fallida/pagada/excluida`), `ami_channel`/`ami_uniqueid` (llave de correlación con Asterisk), `proximo_intento_at`.
  - `cobranza_llamada_eventos` (`CobranzaLlamadaEvento`) — bitácora cruda de cada evento AMI de una llamada (Originate/ANSWER/BUSY/NOANSWER/FAILED/DTMF/TRANSFER/HANGUP).
  - `voip_configuracion` (`VoipConfiguracion`) — fila única (id=1) con host/puerto/usuario/secreto SIP (secreto **cifrado en BD**), caller ID y estado de la troncal.
- **Flujo principal:**
  1. Se crea una campaña (`CampanaController@store`) y se activa (`activar`) → `CobranzaCampanaService::activarCampana()` carga los clientes morosos (saldo vencido, servicio activo, con teléfono) como filas `cobranza_llamadas` y despacha `BlastCampanaJob`.
  2. Cada 5 minutos (`cobranza:blast-activas`, cron en `Kernel.php`) se relanza `BlastCampanaJob` para todas las campañas `activa`. El job respeta la ventana horaria de la campaña, toma hasta 50 llamadas pendientes, genera el audio del mensaje con `CobranzaTtsService` (OpenAI TTS → WAV 8kHz vía `ffmpeg`) y origina la llamada con `AmiConnectionService::originate()` sobre el canal PJSIP compartido `servnet`.
  3. El daemon `cobranza:ami-listener` (`AmiEventListenerCommand`, corre bajo Supervisor, NO por cron) mantiene un socket abierto al AMI, filtra los eventos de sus propias llamadas (uniqueid `cob-*`) y despacha `ProcessCallResultJob`, que actualiza el estado de la llamada, registra el evento y, si se agotan los intentos por falla, delega la suspensión del servicio al `SuspendService` del módulo Clientes.
  4. `VoipConfiguracionController` administra la troncal SIP: guarda la config y la provisiona en Asterisk **vía PJSIP Realtime** a través del servicio compartido `App\Modules\Core\Voice\VoiceGateway` (no escribe `sip.conf` directamente — eso quedó como método legado sin uso).
- **Colas:** `BlastCampanaJob` y `ProcessCallResultJob` corren en la cola `cobranza`.
- **Nota de arquitectura (para quien retome el roadmap):** el módulo mantiene su **propio cliente AMI** (`AmiConnectionService`) para originar/leer llamadas, separado del `Core\Voice\AmiClient` que usa `VoiceGateway` solo para aprovisionar la troncal — es una duplicación menor, no un bug.

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas web** bajo `/cobranza/*` (middleware `web`+`auth`; la autorización real es `auth()->user()->can(...)` dentro de cada controller, no `check_route_permission`): `/cobranza/campanas` (listar/crear), `/cobranza/campanas/{id}/activar|pausar`, `/cobranza/campanas/{id}/llamadas`, `/cobranza/voip` (config SIP) y `/cobranza/voip/test`.
- **3 permisos**: `cobranza.view`, `cobranza.manage`, `cobranza.configure`.
- **Menú** en el sidebar (Campañas / Config. VoIP), gateado por `cobranza.view`.
- No dispara eventos de Laravel ni tiene endpoints públicos/webhooks (el webhook antiguo `/webhooks/cobranza/ami-event` se **eliminó** por ser código muerto — el camino real siempre fue el daemon `cobranza:ami-listener`).

**Consume**
- **Asterisk AMI** (`env('AMI_HOST'/'AMI_PORT'/'AMI_USERNAME'/'AMI_SECRET'/'AMI_CONTEXT')`) — origina llamadas y lee eventos en tiempo real vía TCP directo.
- **OpenAI TTS** (`https://api.openai.com/v1/audio/speech`) — a través del Integration Hub compartido (`api_integrations` / trait `UsesApiIntegration`), no con key propia del módulo.
- **`App\Modules\Core\Voice\VoiceGateway`** — servicio compartido para aprovisionar/recargar la troncal PJSIP y consultar su estado (ARI, con AMI como respaldo).
- **Módulo Clientes** — lee `clients`/`client_main_information`/`invoices` para armar la lista de morosos, y llama a `SuspendService::suspendServiceByClient()` para suspender el servicio tras agotar reintentos.
- **`ffmpeg`** (binario del sistema) — convierte el audio MP3 generado por TTS a WAV 8kHz mono que entiende Asterisk.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._

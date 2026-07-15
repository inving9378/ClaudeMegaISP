# Item roadmap #203 — Fase A: preparación técnica del gateway único (Marketing)

Consolidar la integración WhatsApp duplicada de Marketing (`EvolutionApiService`
propio + webhook `/webhooks/marketing/evolution` + `ProcessIncomingMessageJob`)
hacia el gateway único `WhatsAppAgent` (`WhatsAppGateway` + eventos
`WhatsAppMessageReceived`/`WhatsAppTextReceived`/`WhatsAppMediaReceived`).

Autorizado por Irving (log de #203) como **Nivel A, ejecución autónoma en
DEV**. Alcance estricto de esta fase: preparación aditiva y 100% reversible.
**NO** se tocó el webhook real, **NO** se retiró `EvolutionApiService`, **NO**
se reiniciaron colas de producción, **NO** se envía nada por el gateway nuevo.

## Qué se agregó

- `config/marketing_gateway.php` — modo de operación:
  `WHATSAPP_MARKETING_GATEWAY_MODE` = `legacy` (default) | `shadow` | `unified`.
- `App\Modules\Addons\Marketing\Listeners\GatewayMessageListener` — se
  suscribe a `WhatsAppTextReceived`/`WhatsAppMediaReceived` del gateway único,
  filtra por función de línea `ventas`, y según el modo:
  - `legacy`: no-op total.
  - `shadow`: solo loguea (`Log::channel('evolution')`), nunca responde.
  - `unified`: reservado para el cutover (#203-C), **intencionalmente sin
    implementar** — hoy solo deja constancia en log, no envía nada.
- Registro de los dos listeners en `Marketing\ModuleServiceProvider::boot()`
  (mismo patrón que `Payments\ModuleServiceProvider` con `ConciliationListener`
  / `ConciliationTextListener`).
- Tests: `tests/Feature/Marketing/GatewayMessageListenerTest.php` (payloads
  simulados de texto e imagen, sin tocar Evolution real).

## Por qué es inerte en producción hoy

El listener solo recibe eventos si la instancia de Evolution de ventas
(`meganet-ventas`) está registrada en `whatsapp_instances` y webhookeando al
gateway único (`POST /whatsapp/webhook/{slug}`). **Hoy esa instancia sigue
apuntando a `/webhooks/marketing/evolution`** (el webhook propio de
Marketing), así que el listener nuevo no recibe tráfico real
independientemente del modo configurado. Reapuntar el webhook de Evolution
hacia el gateway es un paso de infraestructura fuera de alcance de esta fase
(pertenece a #203-B, convivencia en modo shadow).

## Inventario de consumidores de `Marketing\Services\EvolutionApiService`

El radio de impacto real es MAYOR al descrito en el enunciado original — no
solo Marketing depende de esta clase:

**Dentro de Marketing:**
- `app/Modules/Addons/Marketing/Services/ConversationResolverService.php`
- `app/Modules/Addons/Marketing/Jobs/SendOutboundMessageJob.php`
- `app/Modules/Addons/Marketing/Jobs/ProcessIncomingMessageJob.php`
- `app/Modules/Addons/Marketing/Jobs/DownloadWhatsAppMediaJob.php`

**Fuera de Marketing (reusan su `EvolutionApiService` en vez del gateway único):**
- `app/Services/Referrals/ReferralWhatsAppNotifier.php`
- `app/Modules/Addons/WarRoom/Observers/ActionItemObserver.php`
- `app/Modules/Addons/WarRoom/Jobs/SendMeetingMinutesJob.php`
- `app/Modules/Addons/Embajadores/Controllers/Api/EmbajadorExtApiController.php`
- `app/Modules/Addons/Flotas/Services/Notifications/Drivers/WhatsappChannel.php`
- `app/Modules/Addons/Flotas/Services/Notifications/DocumentAlertDispatcher.php`
- `app/Modules/Addons/Flotas/Services/Notifications/SubscriptionAlertDispatcher.php`
- `app/Modules/Addons/Payments/Jobs/ConciliationIntakeJob.php`
- `app/Modules/Addons/Payments/Services/Conciliation/ConciliationRouter.php`
- `app/Modules/Addons/Payments/Services/Conciliation/IdentificationSessionStarter.php`

Nota: dentro de `Payments`, `PaymentApplicationService` ya usa el
`EvolutionApiService` del gateway único (`WhatsAppAgent`) — uso mixto dentro
del mismo módulo. Migrar estos consumidores al gateway único es trabajo de
fases posteriores (#203-D, retiro del camino legado), no de esta fase.

## Rollback

100% reversible sin tocar datos:
1. Fijar `WHATSAPP_MARKETING_GATEWAY_MODE=legacy` en `.env` (o quitar la
   variable — `legacy` ya es el default) → el listener vuelve a no-op total
   de inmediato, sin reiniciar nada más que `queue:restart` si había workers
   corriendo con `shadow`/`unified` activos.
2. Para revertir del todo: quitar los dos `Event::listen(...)` de
   `Marketing\ModuleServiceProvider::boot()` y borrar
   `GatewayMessageListener.php` + `config/marketing_gateway.php`. No hay
   migraciones nuevas ni tablas creadas — cero estado persistente propio.

## Siguientes fases (fuera de alcance de #203-A, requieren aprobación aparte)

- **#203-B** (riesgo B): activar modo `shadow` de verdad — requiere además
  reapuntar (o duplicar) el webhook de Evolution hacia el gateway único para
  que el listener reciba tráfico real de ventas. Comparar texto/imagen/IA/
  identificación/errores/latencia contra el camino legacy, sin doble envío.
- **#203-C** (riesgo C, sesión en vivo con Irving): cutover a `unified`
  (implementar `handleUnified` de verdad), probar texto e imagen reales,
  confirmar una sola respuesta, rollback inmediato a `legacy` disponible.
- **#203-D** (riesgo C, mínimo 7 días estables tras el cutover): retirar el
  webhook paralelo y `EvolutionApiService` de Marketing solo cuando tenga
  cero referencias (incluye migrar los consumidores externos listados arriba).

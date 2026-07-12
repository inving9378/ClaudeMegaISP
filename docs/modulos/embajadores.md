# Módulo Embajadores

> Programa de lealtad por recomendación (referidos multinivel, 5 niveles) con comisiones y mensualidades gratis para clientes embajadores. `app/Modules/Addons/Embajadores/` · slug `addon-embajadores` · módulo addon, activo.

## 0. En simple
Es el programa de "recomienda y gana": cuando un cliente invita a alguien y ese alguien se vuelve cliente y paga, el que invitó recibe créditos en su mensualidad, y esos créditos también se reparten un poco hacia quien lo invitó a él (hasta 5 niveles hacia arriba).

## 1. Qué es
Módulo **addon** que implementa un programa de referidos multinivel para clientes del ISP: un cliente "embajador" comparte su link/código, cuando alguien se registra con ese código y llega a pagar cierto monto acumulado (umbral), se activa un `Referral` y se generan comisiones (créditos de mensualidad) tanto para quien refirió directamente como, en porcentajes decrecientes, para hasta 4 niveles de ancestros en el árbol de referidos.

## 2. Para qué sirve
Le da al **equipo de ventas/CRM** una herramienta de crecimiento orgánico: incentiva a los clientes actuales a traer clientes nuevos a cambio de créditos reales aplicados a su saldo/mensualidad. Sirve también como CRM ligero de prospectos referidos (seguimiento de a quién invitó cada embajador, con follow-ups), y expone un panel al propio cliente (portal web) y a la app móvil (Flutter, vía Sanctum) para que el embajador vea su red, comisiones y recompensas.

## 3. Cómo funciona

**Piezas de datos** (`app/Models/Referrals/`, migraciones `2026_05_26_300001`…`2026_06_25_110000`):
- `ReferralSetting` — configuración única del programa (activo/inactivo, `threshold_amount` = monto acumulado que activa un referido, periodo de garantía default 15 días, vencimiento de créditos a 12 meses).
- `ReferralCommissionTier` — porcentaje de comisión configurable por cada uno de los 5 niveles del árbol.
- `ClientReferralProfile` — perfil de embajador por cliente, con contadores denormalizados (`total_referrals`, `total_commissions_earned`, `total_rewards_earned`) recalculados por `ReferralStandingService::computeCountersForProfile()` (fuente única de verdad, recompute completo, nunca increment/decrement — evita drift).
- `Referral` — el vínculo referidor→referido, con estado (`pending_threshold` → `active` → cancelado) y el monto acumulado pagado por el referido hacia el umbral.
- `ReferralCommission` — cada comisión generada por nivel, con `apply_after_at` (fecha de garantía) y `applied_at` (cuándo se acreditó).
- `ReferralReward` — recompensas/créditos otorgados (con expiración a 12 meses, aviso previo).
- `ReferralProspect` + `ProspectFollowup` — CRM de prospectos referidos aún no convertidos, con seguimientos.
- `ReferralShareLog` / `ReferralNotificationLog` / `ReferralNotificationTemplate` — bitácora de veces que un embajador compartió su link y de notificaciones (WhatsApp) enviadas.

**Motor de comisiones (flujo principal):**
1. Un cliente paga una factura → evento `App\Events\InvoicePaid` (disparado por el módulo de Facturación, **no** por este módulo).
2. `HandleInvoicePaid` (listener, cola `referrals`) despacha el job `ProcessReferralCommissions`, que busca el `Referral` `pending_threshold`/`active` del cliente que pagó, acumula el pago hacia `threshold_amount`, y si cruza el umbral marca el referido `active` (dispara `ReferralThresholdCovered`) y genera las `ReferralCommission` por nivel (según `ReferralCommissionTier`), cada una con `apply_after_at` = ahora + días de garantía (dispara `ReferralCommissionGenerated`).
3. En paralelo, `AccumulateClientThreshold` (otro listener del mismo evento) mantiene el acumulado de umbral con "freeze" para no permitir drift.
4. Job programado diario `ApplyReferralCommissions` (`Kernel.php` 03:15, cola `referrals`, `withoutOverlapping`+`onOneServer`) busca comisiones `approved` cuya garantía ya venció (`apply_after_at <= now()` y `applied_at` nula), las acredita al `Balance` del beneficiario dentro de una transacción con `lockForUpdate` (anti-doble-acreditación) y dispara `ReferralCommissionsApplied`.
5. Jobs adicionales programados: `ExpireReferralRewards` (03:30, vence recompensas a 12 meses), `WarnExpiringRewards` (09:00, avisa antes de vencer), `CalculateDailyStats` (02:30, snapshot diario de métricas). Comando `embajadores:rebuild-kpis` (04:00) recalcula contadores como respaldo nightly vía `ReferralStandingService`.
6. Observers `ReferralObserver` / `ReferralCommissionObserver` recomputan contadores del perfil en cada escritura (además del recompute nightly).
7. Notificaciones WhatsApp (`ReferralWhatsAppNotifier`) se disparan desde listeners dedicados (`NotifyEmbajadorActivated`, `NotifyCommissionGenerated`, `NotifyCommissionsApplied`, `NotifyProspectConverted`, `NotifyThresholdCovered`, `NotifyRewardExpiringSoon`) ante los eventos correspondientes.

**Backend web (`Controllers/`):** `DashboardController` (KPIs), `ClientesController` (lista de embajadores + árbol de referidos por cliente), `MetricsController` (métricas del programa), `ComisionesController` (lista + aprobar/cancelar comisiones), `TiersController` (CRUD de niveles), `SettingsController` (reglas del programa), `VideoController` (video explicativo del programa), `LandingController` (landing pública de registro de referido).

**API móvil (Flutter, `Controllers/Api/`):** reutiliza la misma base Sanctum que MegaFamilia. `EmbajadorApiController` (estado, activar, link, compartir, dashboard), `EmbajadorExtApiController` (red de referidos, recompensas, comisiones, share masivo — Fase 7), `ProspectsApiController` + `ProspectFollowupsApiController` + `ProspectImportApiController` (CRM de prospectos, Fase 4), `NotificationsLogApiController` (historial de notificaciones, Fase 6).

**Consumidores cross-módulo (lectura, sin duplicar lógica):**
- **Portal Cliente** (`PortalCliente\Controllers\EmbajadoresController`) — panel de embajador dentro de `/portal`, guard `cliente`, scope estricto `->forClient($clientId)` vía `CurrentClientResolver`; si el cliente aún no es embajador muestra estado vacío con CTA en vez de error.
- **Talento** (`Talento\Controllers\TalentoEmbajadoresController`) — cross-link de solo lectura: si un colaborador (técnico/vendedor) es también cliente embajador (mismo email), muestra sus datos de referidos; no escribe en las tablas de Referrals.

**Sin componente propio en el sidebar:** el módulo se navega desde `/embajadores` (menú declarado en `module.json`), no aparece hardcodeado en `sidebar.blade.php` como otros addons — depende de que el menú del `module.json` se resuelva por el mecanismo estándar de módulos.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Rutas web** bajo `['web','auth']` + `permission:` de Spatie, prefijo `/embajadores`: dashboard (`/`, `/dashboard/summary`), clientes (`/clientes`, `/clientes/data`, `/clientes/{id}`, `/clientes/{id}/tree`), métricas (`/metrics`, `/metrics/data`), video (`/video`), comisiones (`/comisiones`, `/comisiones/data`, `/comisiones/{id}/approve`, `/comisiones/{id}/cancel`), configuración (`/configuracion`, `/configuracion/get`, `/configuracion/update`), tiers (`/tiers`, CRUD).
- **Ruta pública sin auth** `GET /registro` (`embajadores.registro`) — landing que el embajador comparte por WhatsApp/redes para que el prospecto se registre.
- **API móvil** bajo `api/megafamilia/embajadores/*` (mismo prefijo que MegaFamilia, reutiliza su token Sanctum): `/terms` (pública), y bajo `auth:sanctum` — `/status`, `/activate`, `/link`, `/share-log`, `/dashboard`, `/notifications-log`, `/red`, `/recompensas`, `/recompensas/{id}/aplicar`, `/comisiones`, `/share-masivo`, CRUD `/prospects` + `/prospects/{id}/followups`.
- **7 permisos Spatie**: `embajadores.view`, `embajadores.configure`, `embajadores.tiers.manage`, `embajadores.rewards.manage`, `embajadores.commissions.approve`, `embajadores.commissions.cancel`, `embajadores.payouts.generate` (este último declarado en `module.json` pero sin ruta/comando que lo verifique actualmente — el payout real corre automático vía el job programado, no por acción manual gateada).
- **Eventos de dominio** (`App\Events\Referrals\*`): `EmbajadorActivated`, `ProspectConverted`, `ReferralThresholdCovered`, `ReferralCommissionGenerated`, `ReferralCommissionsApplied`, `ReferralRewardExpiringSoon` — cualquier módulo futuro puede escuchar estos eventos sin acoplarse a las tablas.
- **Pestaña de ficha de cliente** declarada en `module.json` (`client_tab.component = "EmbajadoresClientTab"`) — ⚠️ el componente Vue con ese nombre **no está registrado en `resources/js/app.js`** (los componentes reales registrados son `embajadores-dashboard/-configuracion/-tiers/-clientes/-comisiones/-arbol`); la pestaña en la ficha de cliente probablemente no renderiza. No se corrige aquí (documentación read-only).
- **Tarjeta de administración** y sección de configuración ("Embajadores — Reglas del Programa") vía `module.json` → `admin_cards` / `config_sections`.

**Consume**
- **`App\Events\InvoicePaid`** — evento del módulo de Facturación; dispara todo el motor de comisiones (`HandleInvoicePaid` + `AccumulateClientThreshold`). Este módulo NO calcula ni sabe de facturación, solo reacciona a que una factura fue pagada.
- **`Balance`** (modelo compartido, `app/Models/Balance.php`) — destino de la acreditación de comisiones aplicadas (mismo balance que usa el resto del sistema de saldos de cliente).
- **`ClientMainInformation` / `Client` / `ClientInvoice` / `ClientInternetService`** (Core\Clientes) — datos de cliente y servicios para resolver referidos y calcular umbrales.
- **`CurrentClientResolver`** (Tenant) — resuelve el `client_id` del guard `cliente` para el panel del Portal Cliente (scope multi-tenant).
- **Cola `referrals`** — todos los jobs/listeners del motor de comisiones corren en esta cola dedicada (aislada de otras colas del sistema).
- **WhatsApp** — vía `ReferralWhatsAppNotifier`, para notificar activación de embajador, comisión generada/aplicada, prospecto convertido, umbral cubierto y recompensa por vencer. (No se confirmó en esta pasada si pasa por el gateway único `WhatsAppAgent`/`WhatsAppGateway` documentado en `CLAUDE.md` o por un canal propio — verificar si se retoma este módulo).
- **Sistema de permisos Spatie** (`permission:` middleware) — gating estándar de todas las rutas web.

> _Nota aparte (no es de Embajadores):_ los eventos `ProspectRegistered`/`ClientRegistered` → `CalculateProspectCommission`/`CalculateClientCommission` que aparecen junto a este bloque en `EventServiceProvider` pertenecen al sistema de comisiones de **Vendedores/Sellers** (`App\Models\Seller`, `App\Models\Commission`), no al programa de Embajadores — no confundir ambos sistemas de "comisión".

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._

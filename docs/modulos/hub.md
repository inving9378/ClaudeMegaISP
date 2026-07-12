# Módulo Hub (Integration Hub)

> Centro único de API keys de proveedores externos. `app/Modules/Addons/Hub/` · slug `addon-hub` · addon, sin dependencias, activo.

**En simple:** es la caja fuerte donde se guardan todas las contraseñas de los servicios externos (Claude, OpenAI, WhatsApp, mapas, etc.) para que cada módulo del sistema las tome de un solo lugar en vez de tener la suya.

## 1. Qué es
Addon que centraliza la gestión de **API keys de proveedores externos** (Anthropic/Claude, OpenAI, Evolution/WhatsApp, Pexels, Google Maps, Meta) en una sola pantalla y una sola tabla, en vez de tener cada módulo con su propia key en `.env` o en su configuración privada.

## 2. Para qué sirve
Le resuelve a Irving/DESARROLLADOR el poder **ver, validar, rotar y auditar** todas las llaves de API del sistema desde `/integraciones`, sin tener que editar `.env` a mano ni buscar en la config de cada módulo. También le da a cada módulo consumidor una única forma de pedir su key, con una jerarquía de respaldo (Hub → `.env` → configuración propia del módulo) para que nada se rompa si el Hub todavía no tiene esa key cargada.

## 3. Cómo funciona
- **Modelos** (`app/Models/Core/`):
  - `ApiIntegration` (tabla `api_integrations`, soft deletes) — una fila por key configurada (`provider`, `slug`, `name`, `config` json, `active`, `is_default_for_provider`). La key nunca se guarda en claro: el mutator `value` la cifra con `Crypt::encryptString` en `encrypted_value` y calcula `key_preview` (últimos 3 caracteres) y `key_fingerprint` (sha256) para poder mostrarla/compararla sin descifrar. Scopes `forCompany()` y `defaultFor($provider)`.
  - `ApiIntegrationLog` (tabla `api_integration_logs`) — bitácora de auditoría por integración (`event_type`: `key_created`/`key_updated`/`key_rotated`/`key_deleted`/`set_as_default`/`validated`/`validation_failed`, `actor`, `metadata`).
  - `ApiIntegrationUsage` (tabla `api_integration_usage`) — contador diario de uso por integración+feature (`call_count`, `cost_usd`), vía `upsert`.
- **Servicio** `App\Services\Core\ApiIntegrationService` (singleton `::instance()`):
  - `getKey($provider)` / `getIntegration($provider)` — resuelve la integración **default** de un proveedor para la compañía (hoy siempre `company_id=1`, sistema mono-tenant en el admin).
  - `validate($integration)` — hace una llamada real y barata a cada proveedor (Anthropic: `POST /v1/messages` con `max_tokens=1`; OpenAI: `GET /v1/models`; Evolution: `GET {endpoint}/instance/fetchInstances`; Pexels: `GET /v1/search`; Google Maps: `geocode/json`) para confirmar que la key funciona, y deja el resultado en `last_validation_status`/`last_validated_at` + un registro de auditoría.
  - `trackUsage()` / `audit()` — helpers que usan los módulos consumidores para reportar consumo y dejar rastro de cambios.
  - `getProviders()` — catálogo estático de proveedores soportados (id, nombre, ícono, url de docs, formato de key).
- **Controller** `ApiIntegrationController` (`app/Modules/Addons/Hub/Controllers/`) — CRUD de integraciones + `validateKey`/`setDefault`/`rotate`/`logs`/`usage`, cada acción protegida por su propio permiso Spatie (`view-integrations`, `manage-integrations`, `rotate-integration-keys`, `view-integration-logs`, `validate-integrations`).
- **Frontend:** vista Blade `views/index.blade.php` monta el componente Vue registrado como `integrations-hub-view` (`resources/js/app.js`) en `/integraciones`.
- **Trait de consumo** `App\Services\Core\UsesApiIntegration` (usado por los módulos que necesitan una key externa, NO vive en Hub sino en `app/Services/Core/` para que cualquier módulo lo importe): `resolveApiKey($provider, $envFallback, $settingKey)` implementa el **fallback de 3 niveles** — 1) Hub (`ApiIntegrationService::getKey`), 2) `env($envFallback)`, 3) `marketing_settings` (`Setting::get($settingKey)`) — envuelto en try/catch para no tronar si la tabla del Hub aún no existe (migraciones frescas).

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Vista** `GET /integraciones` (permiso `manage-integrations`, guard `check_route_permission`).
- **API JSON** bajo `api/hub/*` (todas tras `auth`): `GET providers`, `GET/POST integrations`, `GET/PUT/DELETE integrations/{id}`, `POST integrations/{id}/validate`, `POST integrations/{id}/set-default`, `POST integrations/{id}/rotate`, `GET integrations/{id}/logs`, `GET integrations/{id}/usage`.
- **Permiso** `manage-integrations` declarado en `module.json`; los demás (`view-integrations`, `rotate-integration-keys`, `view-integration-logs`, `validate-integrations`) se usan en el controller vía middleware Spatie pero no están en el manifest del módulo.
- **Servicio compartido** `App\Services\Core\ApiIntegrationService` (singleton) + trait `UsesApiIntegration` — el punto de entrada que CUALQUIER módulo debe usar para resolver una API key externa, en vez de leer `.env` o tener su propio cliente HTTP (regla de servicios únicos del proyecto).

**Consume**
- **Proveedores externos** directamente durante la validación: `api.anthropic.com`, `api.openai.com`, el endpoint de Evolution API configurado, `api.pexels.com`, `maps.googleapis.com`.
- **Consumidores reales del trait `UsesApiIntegration`** (confirmado por código, no todos declarados como dependencia formal): `ClaudeApiClient` y `EvolutionApiService` (Marketing), `OpenAiTtsDriver` y `AbstractPublishDriver` (Marketing/Publishing), `CobranzaTtsService` (CobranzaBlaster) — todos piden su key al Hub primero y caen a `.env`/config propia si el Hub no la tiene.
- **Laravel `Crypt`** (cifrado nativo de la app) para almacenar las keys, nunca en texto plano.

> _Servicio único designado: IA/proveedores externos deben conectarse a los adaptadores existentes vía este Hub, sin clientes HTTP propios ni keys por módulo (regla del proyecto). No hay entrada en el registro de contratos inter-módulos (`docs/contratos/`) para Hub al momento de esta doc._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._

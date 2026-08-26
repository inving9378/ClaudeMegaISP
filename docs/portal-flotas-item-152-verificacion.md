# Item #152 — "Portal: Flotas para cliente" (RESUELTO — ya implementado)

**Título del item:** Portal: Flotas para cliente
**Descripción:** "Scope por `fleet_vehicles.client_id` y tracking en el portal. [Migrado de CLAUDE.md
HOJA DE RUTA 2026-06-19]"

## Hallazgo

El pedido del item (scope por `client_id` + tracking en el portal de cliente) **ya estaba
implementado** antes de que este item llegara a ejecución, en 3 commits ya presentes en `main`:

- `cd25a346` — feat(portal): panel Flotas scopeado por cliente (lectura)
- `de850de2` — fix(portal): UI rename Flotas->Vehiculos + CTA adaptativo por tipo de cliente
- `0b557790` — feat(portal-flotas): agrega rastreo (ubicación) al panel Mis Vehículos

El item fue creado el 2026-06-20 (migrado del checklist de CLAUDE.md fechado 2026-06-19) y quedó
huérfano en la Hoja de Ruta mientras el trabajo real se hacía por otra vía — la documentación
(CLAUDE.md) nunca se actualizó para reflejarlo, así que el item seguía viéndose "pendiente".

## Verificación técnica (sin cambio de código en el panel)

- **Ruta:** `GET /portal/flotas` (`portal.flotas`), guard `cliente`, registrada en
  `app/Modules/Addons/PortalCliente/routes.php:92`.
- **Controller:** `FlotasController::index()`
  (`app/Modules/Addons/PortalCliente/Controllers/FlotasController.php`):
  - `client_id` resuelto vía `CurrentClientResolver::resolve()` (mismo resolver que el resto del
    portal — no un query param, no confiable del cliente).
  - **Fail-closed real:** sin `client_id` resuelto, `$vehicles = collect()` y no se consulta nada.
  - Vehículos: `FleetVehicle::forClient($clientId)`.
  - Geocercas: `FleetGeofence::forClient($clientId)`.
  - Tracking: `FleetPositionService::getCurrentPositions($clientId)` → internamente
    `FleetVehicle::forClient($clientId)->where('has_gps', true)` + última posición conocida
    (`lastPosition`) + estado en vivo (`moving`/`stopped`/`idle`/`offline`, calculado por
    antigüedad del ping).
- **Scope real (`App\Traits\BelongsToClientTenant`, usado por `FleetVehicle` y `FleetGeofence`):**
  - `clientId` concreto → `WHERE client_id = $clientId` (las filas con `client_id` NULL —
    flota interna Meganet— **nunca** casan por igualdad; quedan excluidas siempre).
  - `clientId` NULL → `allowNullTenant` está **desactivado** en estos modelos para el consumo del
    portal (el flag existe en el trait pero por defecto es `false`) → fail-closed: cero filas.
  - Verificado en el propio código del trait (`scopeForClient`), no solo por inspección superficial.
- **UI:** `views/flotas.blade.php` — tabla de vehículos con placa/tipo/año/estado + columna
  "Ubicación" (badge de estado en vivo + link a Google Maps con la última posición conocida),
  KPIs (total, activos, mantenimientos próximos 30 días, geocercas activas), estado vacío con CTA
  cuando el cliente no tiene flota.
- **Sidebar del portal:** enlace "Mi Flota" ya presente en
  `views/layouts/portal.blade.php:437` (`route('portal.flotas')`).

## Lo que NO estaba pedido por el item y sigue gateado (correcto, no es un bug)

El **alta self-service** de Flotas (que un cliente cree su propia suscripción/vehículos desde
`/portal/servicios` sin intervención de un admin) sigue gateada como "En preparación" en
`MarketplaceController`/`marketplace.blade.php` — eso es una decisión de producto (pricing/ventas
de un módulo SaaS vendible) fuera del alcance de "scope + tracking", no una omisión técnica. Hoy
el flujo real es: un admin da de alta la suscripción (`fleet_subscriptions`, `client_id` NOT NULL)
y los vehículos (`fleet_vehicles.client_id` = el del cliente) desde el panel admin de Flotas, y el
cliente los ve/rastrea de solo lectura en `/portal/flotas`. Se corrigió el comentario desactualizado
en `MarketplaceController::index()` que decía "Flotas: gateado, no tiene escopo por cliente final"
(ya no es cierto — el escopo sí existe, lo que falta es la alta self-service).

## Cambios de esta sesión (item #152)

Sin cambios de código funcional (el panel ya funcionaba). Solo se corrigió documentación
desactualizada que hacía ver el trabajo como pendiente:

1. `CLAUDE.md` — movida la fila "Portal: Flotas para cliente" de la tabla de pendientes a la de
   "implementado y cerrado" (con los 3 commits) + corregida la línea de "Módulos activables" que
   decía "GATEADO ... no tiene scope por cliente".
2. `MarketplaceController::index()` — corregido el docblock que afirmaba que Flotas "no tiene
   escopo por cliente final" (ya lo tiene; lo gateado es solo la alta self-service).
3. Este documento de verificación.

# MultiOLT — Fases autónomas (plan + bitácora)

> Generado: 2026-06-13 · Sesión desatendida · Solo guardrails duros se respetan.

---

## Auditoría Paso 0

### Rutas verificadas

| Artefacto | Ruta real |
|---|---|
| Módulo Mapas (module) | `app/Modules/Addons/Mapas/` |
| Mapas controllers | `app/Modules/Addons/Mapas/Controllers/Geo/LayersController.php` (principal) |
| Mapas routes | `app/Modules/Addons/Mapas/routes.php` (`prefix=maps`) |
| Mapas Vue | `resources/js/components/module/maps/LeafletMap.vue` (Leaflet npm, ~1500 líneas) |
| Stack Mapas | Leaflet (npm) + `L.control.groupedLayers` + `L.control.layers` + OSM tiles |
| `olt_onus` modelo | `app/Models/OltOnu.php` |
| `olt_odbs` modelo | `app/Models/OltOdb.php` |
| `PaymentApplicationService` | `app/Modules/Addons/Payments/Services/PaymentApplicationService.php` |
| Motor notificaciones | `app/Models/GeneralNotification.php` + `app/Notifications/StandardNotification.php` |
| Scheduler | `app/Console/Kernel.php` (bloque OLT: líneas ~44–52) |
| Hoja de Ruta | `storage/app/roadmap-memory/` (último ítem: 120) |

### Motor de notificaciones (patrón)
```php
$notification = new GeneralNotification();
$notification->priority = 'Alta';  // Alta | Media | Baja
$notification->title   = 'Mensaje';
$notification->code    = 'codigo-unico';
$notification->save();
$users = User::admin()->get();       // o filtra por rol
Notification::send($users, new StandardNotification($notification));
```
`StandardNotification` implementa `ShouldQueue` — `via()` es `['database']` por defecto.

### Estado de `service_id` (GR-2)
| Categoría | Cantidad |
|---|---|
| Total ONUs | 2 954 |
| Con `service_id` | 2 606 (88.2%) |
| Sin `service_id` | 348 |
| → Sin `client_id` tampoco | 50 |
| → Con `client_id`, sin servicio en BD | 3 |
| → Con 1 svc `Pendiente` (resolvible) | 280 |
| → Con 1 svc `Activo` no enlazado (timing?) | 13 |
| → Otros (Desactivado) | 2 |

Causa raíz del backfill original: exigía `estado IN ('Activo','Activado',...)` y `HAVING COUNT=1`. Los 280 Pendientes quedaron fuera.

### PaymentApplicationService — wiring OLT
Líneas 150–193: código comentado explícito. El enlace correcto es:
`olt_onus.client_id` → `clients.id` (ya existe). El comentario menciona duda sobre si usar `mac/portid/sn`. El análisis muestra que `client_id` ya está en `olt_onus` (98.3% cobertura), así que `resolveClientOnu($client)` puede hacer `OltOnu::where('client_id', $client->id)->first()`.

---

## Plan de fases

### Fase 1 — Cron faltante + Hoja de Ruta (rápida)
- Agregar `smartolt:sync-clients-with-ont` al scheduler (`dailyAt('05:30')` — después del sync-inventory de las 05:00)
- Registrar ítems de deuda en Hoja de Ruta: wiring ONU→Pago, alertas PON desconectadas
- Commit por sub-paso

### Fase 2 — Completar `service_id` (GR-2)
- Migración aditiva de re-backfill: ampliar estados a incluir `Pendiente` (con `HAVING COUNT=1`)
- Comando artisan `smartolt:backfill-service-id` para re-run manual futuro
- Tests: resolver que la resolución no asigna a la ONU incorrecta
- Meta: de 88.2% → ~99%+
- **PARADA:** No tocar `PaymentApplicationService`

### Fase 3 — Integración Mapas (A5)
- Endpoint `GET /olts/mapa-capas` → GeoJSON de OLTs, ODBs, ONUs coloreadas por señal
- En `LeafletMap.vue`, agregar capas toggleables (off por defecto) via `L.control.groupedLayers`
- Tier de color: verde (>= -23 dBm), ámbar (-24 a -26.99), rojo (<= -27 o sin señal)
- No romper capas existentes

### Fase 4 — Alertas PON (A6, flag apagado)
- Enganchar `olt_interruption_pons` al motor de notificaciones
- Flag `alertas_olt_activas` (en `system_settings` o en `olt_smartolt_config`)
- Dry-run: loguea pero NO envía
- Tests con datos sintéticos

### Fase 5 — Wiring ONU→Pago (scaffolding, con PARADA)
- Implementar `resolveClientOnu(Client $client)` en `PaymentApplicationService`
- Habilitar la llamada `enableDisableONU` detrás de flag `olt_activation_enabled`
- Tests que cubran la resolución
- **PARADA OBLIGATORIA:** Dejar flag apagado; marcar en reporte final para validación de Irving

---

## Bitácora de ejecución

| Fase | Estado | Commits |
|---|---|---|
| Paso 0 | ✅ Doc escrito | — |
| Fase 1 | ⏳ | — |
| Fase 2 | ⏳ | — |
| Fase 3 | ⏳ | — |
| Fase 4 | ⏳ | — |
| Fase 5 | ⏳ | — |

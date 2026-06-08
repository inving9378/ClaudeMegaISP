# War Room — Background Insights & KPI Snapshots
**Fecha:** 2026-06-08  
**Commits:** 2db3f94 (backend) · e8ba6ff (frontend)

---

## Problema que resuelve

Antes, `InsightsController::show()` llamaba `InsightsService::generate()` síncronamente al expirar el TTL. Un primer usuario después de la expiración esperaba **~20s** bloqueando la request mientras Claude API generaba 7 insights. Además:
- `warroom_kpi_snapshots` existía pero nunca se escribía
- `InsightsService` usaba `config('services.claude.api_key')` en vez de `config('services.anthropic.key')` → la API **nunca se llamaba** (fallback a reglas siempre)
- No había job ni cron para mantener el cache caliente

---

## Qué se creó

### 1. Comando `warroom:refresh` — `Console/RefreshWarRoomCommand.php`

```bash
php artisan warroom:refresh [--period=YYYY-MM] [--view=all|resumen|...] [--skip-insights] [--skip-snapshot]
```

Flujo:
1. Para cada vista (resumen, finanzas, operaciones, ventas, red, marketing, talento), llama `InsightsService::generate($view, $period)` → escribe/actualiza `warroom_insights_cache` (updateOrCreate por `view_key+period`)
2. Para cada vista, llama `KpiController::raw($view, $period)` → acumula JSON de KPIs de las 7 vistas
3. Escribe UN registro en `warroom_kpi_snapshots` con `updateOrCreate(['period' => $period])` → idempotente

**Columnas usadas de `warroom_kpi_snapshots`:** `period VARCHAR`, `kpis JSON` (objeto con 7 claves, una por vista), `snapshot_at TIMESTAMP`.

### 2. Job `RefreshInsightsJob` — `Jobs/RefreshInsightsJob.php`

- `implements ShouldQueue`, queue=`default` (driver database)
- tries=2, timeout=30s
- Parámetros: `viewKey`, `period`
- Llama `InsightsService::generate()` → actualiza cache automáticamente
- `failed()` loguea en `laravel.log`

### 3. Exposición de `KpiController::raw()` — sin duplicar queries

`KpiController::show()` ahora delega a `raw()`:
```php
public function show(string $view, ?string $period = null): JsonResponse
{
    return response()->json($this->raw($view, $period ?? now()->format('Y-m')));
}

public function raw(string $view, string $period): array { ... }
```
Los métodos privados (`resumenKpis`, `finanzasKpis`, etc.) no cambiaron — solo se movió el match a `raw()`.

### 4. `InsightsController` — comportamiento asíncrono

| Antes | Después |
|-------|---------|
| `show()` genera síncronamente si cache expiró → hasta ~20s | `show()` devuelve cache stale (si existe) + `status:'generating'` + despacha `RefreshInsightsJob` |
| `regenerate()` bloquea hasta tener respuesta de Claude | `regenerate()` encola job, devuelve `{queued:true, status:'generating'}` |

TTL para "fresco" en HTTP: **120 minutos** (el scheduler refresca cada hora, así en la mayoría de cargas el cache tendrá < 60 min y se sirve sin job).

### 5. Frontend — estado `generating`

**`useInsights.js`:**
- Expone `status` ref (`'ready'` | `'generating'`)
- Si el backend devuelve `status: 'generating'` → programa un `setTimeout` de 8s para hacer fetch nuevamente (poll único; el timer se limpia en cada `fetchInsights` para evitar acumulación)

**`InsightsBlock.vue`:**
- Recibe `status` prop
- Si `status === 'generating'` y lista vacía → muestra "Generando insights en background, listo en unos segundos…" con spinner
- Si `status === 'generating'` y hay insights previos → muestra los insights existentes + banner "Actualizando…" abajo

---

## Fix de config path (InsightsService)

| Antes (bug) | Después (correcto) |
|-------------|-------------------|
| `config('services.claude.api_key', ...)` | `config('services.anthropic.key', ...)` |
| `config('services.claude.model', ...)` | `config('services.anthropic.model', ...)` |

El config real está en `config/services.php` bajo la clave `anthropic` (no `claude`). El path incorrecto hacía que `$apiKey` siempre fuera `null` → `InsightsService` caía SIEMPRE al fallback de reglas aunque hubiera API key en `.env`. Ahora usa el path correcto.

**Modelo Claude configurado:** `env('CLAUDE_MODEL', 'claude-sonnet-4-6')` — válido según CLAUDE.md. El default en `services.php` es `claude-sonnet-4-20250514` (formato dated-version de Anthropic; no se modificó para no arriesgar cambiar un ID válido sin verificar en API).

---

## Schedule (Kernel.php)

| Comando | Frecuencia | Propósito |
|---------|-----------|-----------|
| `warroom:refresh --skip-snapshot` | **Cada hora** (:00) | Mantener insights calientes para los 7 dashboards |
| `warroom:refresh --skip-insights` | **Diario a las 23:55** | Escribir snapshot diario de KPIs para histórico |

- Ambas con `withoutOverlapping` y `onOneServer`
- El scheduler de insights (~7 × 3s = 21s por corrida) no supera el minuto de overlap

---

## Verificación (corrida manual)

```
php artisan warroom:refresh
→ ✓ insights [resumen] source=ai
→ ✓ insights [finanzas] source=ai
→ (x7 vistas)
→ ✓ snapshot [2026-06] 7 vistas

warroom_insights_cache: 8 filas (7 vistas mes actual + 1 prev)
warroom_kpi_snapshots:  1 fila  (period=2026-06, 7 vistas en JSON)
```

El schedule list confirma:
```
0  *  * * *   warroom:refresh --skip-snapshot   Next Due: en N minutos
55 23 * * *   warroom:refresh --skip-insights   Next Due: en 9 horas
```

---

## Qué NO se hizo (fuera de alcance)

| Pendiente | Razón |
|-----------|-------|
| UI para listar/comparar snapshots históricos | Requiere diseñar una pantalla nueva; los snapshots YA se escriben y están disponibles para consulta futura |
| Retención / limpieza de snapshots viejos | Decisión de negocio; actualmente crecerán ~1 fila/mes indefinidamente |
| Notificaciones cuando insights están listos | Requiere WebSocket o SSE; el poll de 8s es suficiente para MVP |
| Modelo dated-version en services.php | No se cambió `claude-sonnet-4-20250514` sin validación explícita contra la API |

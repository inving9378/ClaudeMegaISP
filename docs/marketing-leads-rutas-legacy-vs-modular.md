# Marketing — mapa de rutas de Leads: legacy vs modular (item #236)

Auditoría de la duplicación "código muerto de las dos arquitecturas" reportada para el
módulo Marketing. Solo documentación/plan — **sin cambios de comportamiento** en esta
sesión (FASE 3 del item).

## Hallazgo: dos implementaciones paralelas de Lead sobre la misma tabla

Existen **dos modelos Eloquent `Lead` distintos**, ambos apuntando por convención a la
misma tabla `leads` (ninguno declara `$table` explícito):

| | Modelo | Controller | Prefijo de ruta | Consumo UI (`resources/js`) |
|---|---|---|---|---|
| **Canónico** | `App\Models\Marketing\Lead` | `MarketingLeadController` | `leads`, `lead-forms` | ✅ Sí — `leads.index`, `leads.show`, `lead-detail.view`, etc. (Vue Kanban/CRM) |
| **Legacy (muerto)** | `App\Modules\Addons\Marketing\Models\Lead` | `LeadController` | `leads-legacy` | ❌ Ninguno — `grep -rn "leads-legacy\|LeadController" resources/js` no arroja resultados |

`app/Http/Controllers/Marketing/` (la ruta "Fase 1-2, standard Laravel" que menciona
`CLAUDE.md`) **ya no existe** en el árbol actual — el directorio fue removido/migrado y
esa nota quedó desactualizada. Ambas implementaciones de Lead viven hoy dentro de
`app/Modules/Addons/Marketing/`, no repartidas entre las dos arquitecturas históricas.

### Riesgo silencioso encontrado
`App\Models\Marketing\Lead` tiene un observer registrado
(`AppServiceProvider::boot()` → `Lead::observe(LeadObserver::class)`, que dispara
`LeadActivity` y el resto del pipeline de scoring/notificaciones). El modelo legacy
`App\Modules\Addons\Marketing\Models\Lead` **no tiene observer** — si algún día se
conectara UI a `leads-legacy/*`, sus mutaciones (`qualify`, `updateStatus`, `assign`)
pasarían por debajo del pipeline de eventos sin que nadie lo note. Otro motivo para
retirarlo en vez de mantenerlo "por si acaso".

## Servicio de scoring canónico

| Servicio | Usado por | Estado |
|---|---|---|
| `LeadScoringService` | `ScoreLeadJob` (dispatcheado desde `MarketingLeadController::triggerScoring`, ruta `leads/{id}/score`) | ✅ **Canónico** — es el que corre en producción vía el flujo real de scoring (IA, Claude) |
| `LeadQualifierService` | Solo `LeadController` (legacy, sin consumidor) | ❌ Código muerto, ligado 1:1 al controller legacy |

## Plan de retiro (no ejecutado en esta sesión — próximo item)

1. Confirmar con Irving que `leads-legacy/*` nunca se usó desde fuera (Postman/integraciones
   externas), no solo desde `resources/js`.
2. Eliminar `Route::prefix('leads-legacy')` (`app/Modules/Addons/Marketing/routes.php:182-188`),
   `LeadController`, `LeadQualifierService` y `App\Modules\Addons\Marketing\Models\Lead`.
3. Verificar que ningún job/comando referencie el modelo legacy antes de borrarlo
   (`grep -rn "Modules\\\\Addons\\\\Marketing\\\\Models\\\\Lead"`).
4. Commit de retiro separado (nivel B — es una eliminación, no aditivo puro), con su propia
   verificación de regresión.

## Cómo se auditó (reproducible)

```bash
# Consumidores de las rutas legacy en el frontend (0 resultados = código muerto):
grep -rn "leads-legacy" resources/js resources/views

# Las dos clases Lead, confirmando namespace distinto + mismo nombre de tabla implícito:
grep -n "class Lead" app/Modules/Addons/Marketing/Models/Lead.php app/Models/Marketing/Lead.php

# Observer registrado solo en el canónico:
grep -n "Lead::observe" app/Providers/AppServiceProvider.php
```

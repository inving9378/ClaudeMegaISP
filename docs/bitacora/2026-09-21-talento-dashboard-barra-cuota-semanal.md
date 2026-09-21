# Fix — TalentoDashboard: barra de "Cuota semanal" sin color de progreso

## 2026-09-21 12:50 — Barrido de errores pendientes tras el fix de "Ver flujo"

### Contexto

Irving pidió revisar si quedaba algún otro error tras el fix de `/talento/campo`.
En vez de auditar a ciegas, revisé el rastro que ya dejó esta misma sesión hoy:
el log real de errores (`storage/logs/laravel-2026-09-21.log`) y las bitácoras
de las Fases A-E del plan Vendedores→Talento, buscando hallazgos "documentados
pero no corregidos" (fuera de alcance de la tarea que los encontró).

### Lo que ya estaba resuelto (verificado, sin tocar)

- `TalentoEmbajadoresController::embajadorData()` consultaba `clients.name`/
  `email` (columnas que no existen en esa tabla) — encontrado en el lote 5 de
  la Fase B (08:13) y corregido 6 minutos después (commit `75be3ff8`, 08:19).
  Sin más ocurrencias en el log tras esa hora.
- `ClientMainInformation.php` — accesos sin `?->` a `payment_method`/`user`
  que podían dar 500 — ya corregido en el trabajo de rendimiento de esta misma
  sesión (commit `6c458541`, 10:01). Sin más ocurrencias en el log tras esa hora.
- `CalculateBalanceSellerService` tardaba ~40s — ya corregido (mismo commit de
  arriba, es lo que Irving pidió corregir "primero" antes de la Fase E).

### Bug nuevo encontrado y corregido

`TalentoDashboard.vue`, tarjeta "Cuota semanal" (tab "Panel del técnico"): la
barra de progreso nunca mostraba amarillo ni rojo. Causa — un ternario anidado
mal escrito:

```html
:class="quotaPct>=100?'bg-success':'quotaPct>=75?\'bg-warning\':\'bg-danger\''"
```

Las comillas escapadas (`\'`) convertían la segunda rama en un **string
literal** (`"quotaPct>=75?'bg-warning':'bg-danger'"`), no en una expresión —
Vue lo usaba tal cual como nombre de clase CSS (que no existe, así que no
aplica ningún color). Resultado: con ≥100% de la cuota la barra se veía verde
correctamente; con cualquier avance menor, la barra quedaba sin color
(gris/default), sin distinguir "va bien" (≥75%) de "va mal" (<75%).

**Fix:** paréntesis reales en vez de comillas escapadas —
`quotaPct>=100?'bg-success':(quotaPct>=75?'bg-warning':'bg-danger')` — ahora
evalúa como ternario anidado real. `npm run dev` compila limpio.

Encontrado y anotado como "fuera de alcance" en el lote 5 de la Fase B
(08:13, ver `docs/bitacora/2026-09-21-talento-fase-b-lote5-catalogos.md`) por
no ser parte de esa migración de estilos — quedó pendiente hasta ahora.

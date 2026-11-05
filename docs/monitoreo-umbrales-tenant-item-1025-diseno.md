# Item #1025 — Umbrales de monitoreo por tenant (junto con #994) — DISEÑO

Estado: **solo diseño**, sin migración ni código. Complementa
`docs/monitoreo-dedicados-credenciales-tenant-item-994-diseno.md` (routers por tenant).

## Hallazgo
`config/metricas.php` (bloque `tipos`, donde la decisión de Irving del 30-oct dice que viven los
umbrales por tipo de cliente) **no existe todavía en `main`** (ni en el historial). La función
`TipoMonitoreoCliente` de la épica #988 tampoco. Este diseño fija el contrato para cuando
existan; no se inventa el archivo aquí.

## Diseño
**Resolución en cascada (el más específico gana):**
1. Umbral del **tenant + tipo de cliente** (tabla).
2. Umbral del **tenant** sin tipo (fila con `tipo_cliente` NULL).
3. **Default global**: `config/metricas.php` → `tipos.<tipo>` (valor por defecto, Meganet).

**Tabla `monitoring_thresholds`** (nueva, aditiva, misma convención que `monitoring_routers` de #994)
- `id`, `tenant_id` (NULL = Meganet interno), `tipo_cliente` (string NULL), `metrica` (ej. `latencia_ms`,
  `perdida_pct`, `jitter_ms`, `uso_pct`), `warn`, `crit` (decimales), `active`,
  `created_by/updated_by`, soft deletes.
- Único `(tenant_id, tipo_cliente, metrica)`. Validación: `warn` ≤ `crit` en métricas "más es peor".
- Modelo con el scope `BelongsToClientTenant` (sin tenant resuelto → solo defaults, falla cerrado).

**Servicio único** `MonitoreoUmbrales::para($tenantId, $tipo, $metrica)` → `{warn, crit, origen}`
(`origen` = tenant+tipo | tenant | default, para mostrarlo en UI). Todo consumidor (alertas,
semáforos) pasa por aquí; nadie lee `config('metricas')` directo. Caché corta por tenant.

**Permisos** (convención #538): ver `monitoreo_view_block_umbrales`; editar
`monitoreo.umbrales.manage` (admin del tenant). Cambios a `activity_log`.

## Dependencias y orden
Bloqueado por #994 F1 (existencia de tenant en el monitoreo) y por que exista
`config/metricas.php`/`TipoMonitoreoCliente` (#988). Fases: F1 migración+modelo+servicio+prueba de
aislamiento A≠B; F2 CRUD/UI; F3 conectar alertas.

## Decisión de criterio
Tabla propia + defaults en config (en vez de JSON por tenant) por ser consultable, validable y
auditable. Reversible: nada aplicado.

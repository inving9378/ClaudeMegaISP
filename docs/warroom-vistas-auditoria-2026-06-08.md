# War Room — Auditoría y Completado de Vistas
**Fecha:** 2026-06-08  
**Commits sesión anterior:** 40f2087 (Finanzas) · 4c4b8a1 (Operaciones) · 80361d3 (Ventas) · 8b483f6 (Red) · 8116d46 (Marketing)  
**Commits sesión final:** d2e2ef4 (fix Finanzas delta) · b4864aa (Talento completo)

---

## Estado final de cada vista (HEAD post-cierre)

| Vista | KPIs | Gráfica | Paneles adicionales | Estado |
|-------|------|---------|---------------------|--------|
| **Resumen** | 5 reales (3 hero: Ingresos/Clientes/Comisiones + Por cobrar + Tickets abiertos) | ✅ Overlay ingresos diarios 2 líneas (área) | Top performers, Riesgos/Oportunidades, Activity feed, Insights, Action items | ✅ Completo |
| **Finanzas** | 4 reales: MRR, Por cobrar, Tasa cobro %, Cartera vencida | ✅ MRR semanal × 3 meses | Top deudores, Cash flow 4 sem, Insights | ✅ Completo |
| **Operaciones** | 4 reales: Tickets mes, Tiempo prom. resolución, Pendientes, Cerrados | ✅ Tickets cerrados/semana × 3 meses | Por estado, Por prioridad, Insights | ✅ Completo |
| **Ventas** | 4 reales: Clientes nuevos, Comisiones, Embajadores activos, Referidos mes | ✅ Clientes nuevos/semana × 3 meses | Insights | ✅ Completo |
| **Red** | 4 reales: Clientes activos, ONUs online, ONUs caídas, PPPoE configurados | ✅ Tickets de red/semana × 3 meses | Estado ONUs, Uso por OLT, Insights | ✅ Completo |
| **Marketing** | 4 reales: Publicaciones, Campañas activas, Leads captados, Leads ganados | ✅ Leads captados/semana × 3 meses | Canal de mensajes (desglose real), Insights | ✅ Completo |
| **Talento** | 4 operativos: Colaboradores activos, Asistencia hoy, Órdenes hoy, OTs validadas hoy | ✅ OTs validadas/semana × 3 meses | Alertas, Top performers, Links, **Insights** | ✅ Completo |

---

## Estado de cada vista ANTES de esta sesión (para referencia)

| Vista | KPIs antes | Gráfica antes | Notas |
|-------|-----------|--------------|-------|
| **Resumen** | ✅ 5 reales | ✅ Overlay ingresos diarios 2 líneas | Ya completo |
| **Finanzas** | ✅ 4 reales | ✅ MRR semanal 3 meses (sesión anterior) | Bug: delta cartera_vencida usaba `.count` en vez de `.facturas` → texto del delta no aparecía |
| **Operaciones** | ✅ 4 reales | ✅ Tickets cerrados semanal (sesión anterior) | Ya completo |
| **Ventas** | ✅ 4 reales | ✅ Clientes nuevos semanal (sesión anterior) | Ya completo |
| **Red** | ✅ 4 reales | ✅ Tickets de red semanal (sesión anterior) | Ya completo |
| **Marketing** | ✅ 4 reales | ✅ Leads semanal (sesión anterior) | Ya completo |
| **Talento** | ✅ 4 operativos | ❌ Sin gráfica | Sin InsightsBlock. Backend no retornaba weekly_series. InsightsService no tenía caso 'talento'. |

---

## Cambios de esta sesión (commits d2e2ef4 + b4864aa)

### Fix Finanzas (d2e2ef4)
- `ViewFinanzas.vue`: `kpis?.cartera_vencida?.count` → `kpis?.cartera_vencida?.facturas`
- El backend retorna la clave `facturas` (no `count`) en el objeto `cartera_vencida`. El delta text ("N facturas vencidas") no se mostraba por este typo.

### Talento completo (b4864aa)

**Backend `KpiController::talentoKpis()`:**
- Agrega `weekly_series` usando `weeklySeriesHelper` con OTs validadas (status='validated', grouped by DAY(validated_at) por semana) × 3 meses

**Backend `InsightsService`:**
- `fetchKpis('talento')`: retorna colaboradores_activos, ots_validadas_mes, ots_creadas_mes, pendientes_validar; con guard `Schema::hasTable` por si Talento no está instalado
- `generateWithRules('talento')`: nuevo método `talentoRules()` → 3 insights: equipo activo, tasa de validación, oportunidad de mejora
- `use Illuminate\Support\Facades\Schema` agregado al archivo

**Frontend `ViewTalento.vue`:**
- Misma estructura de gráfica que las demás vistas: ApexCharts line, 3 series (verde w=3 / azul w=1.5 / gris w=1), eje X Sem 1-4
- `InsightsBlock` agregado debajo de top performers (misma sección que otras vistas)
- `useInsights('talento')` agregado al composable

---

## Qué NO se hizo (tareas separadas)

| Área | Pendiente | Razón |
|------|-----------|-------|
| OLT/MikroTik en vivo | Métricas en tiempo real (throughput, latencia, % utilización por puerto activo) | Requiere integración activa con SmartOLT/RouterOS API; los datos de BD (olt_onus, olt_pon_ports) ya se usan; la capa "en vivo" es tarea aparte |
| Marketing — apertura/entrega | Tasa de apertura, entrega, clics por canal | Requiere Evolution API analytics (actualmente no expuesto en API) |
| Resumen — gráfica 3 períodos | Tiene gráfica overlay diaria 2 líneas (área); no se migró a semanal 3 líneas | Ya tiene gráfica funcional y distinta; la overlay diaria es más útil para el Resumen |
| Insights background | Regeneración automática periódica de insights por vista | Job/snapshot scheduling — tarea separada |
| KPI Snapshots | Guardar snapshot de KPIs en warroom_kpi_snapshots para histórico | Requiere job programado |

---

## Esquema de la gráfica comparativa (patrón reutilizado en todas las vistas)

```
Series 0 — este mes:      color #1D9E75, stroke.width = 3 (más gruesa y brillante)
Series 1 — mes anterior:  color #534AB7, stroke.width = 1.5
Series 2 — hace 2 meses:  color #6b6b85, stroke.width = 1
Eje X: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4']  (días 1-7, 8-14, 15-21, 22-31)
Fondo: transparent, theme dark, sin toolbar
Grid: borderColor rgba(255,255,255,0.06), strokeDashArray 4
```

Implementado en: `weeklySeriesHelper()` en KpiController (3 períodos × 4 semanas × 1 query por celda = 12 queries por vista al cargar).

---

## Tablas reales usadas (verificadas con tinker antes de cada query)

| Vista | Tabla principal | Columnas clave |
|-------|----------------|----------------|
| Finanzas | client_invoices | estado='Pagado', payment_date; también estado IN ('Atrasado','impagado') para cartera vencida |
| Operaciones | tasks | status='Done', created_at, updated_at; TIMESTAMPDIFF(HOUR) para tiempo promedio |
| Ventas | clients | created_at, deleted_at |
| Red (PPPoE) | mikrotik_client_ppoes | COUNT total (sin campo status) — 5,407+ registros |
| Red (OLTs) | olt_pon_ports + olts | online_onus_count, onus_count, olt_id → name |
| Red (gráfica) | tasks | title LIKE '%sin internet%' (proxy de incidencias de red) |
| Marketing | marketing_channels + marketing_publications | LEFT JOIN channel_id, status='published' |
| Marketing (gráfica) | marketing_leads | created_at |
| Talento | talento_work_orders | status='validated', validated_at (gráfica semanal) |
| Talento (insights) | talento_colaboradores + talento_work_orders | status='active'; status IN ('completed','pending_validation') |

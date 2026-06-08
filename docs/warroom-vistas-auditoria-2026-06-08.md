# War Room — Auditoría y Completado de Vistas
**Fecha:** 2026-06-08  
**Commits:** 40f2087 (Finanzas) · 4c4b8a1 (Operaciones) · 80361d3 (Ventas) · 8b483f6 (Red) · 8116d46 (Marketing)

---

## Estado de cada vista (paso 0 — HEAD antes de cambios)

| Vista | KPIs antes | Gráfica antes | Paneles antes |
|-------|-----------|--------------|--------------|
| **Resumen** | ✅ 5 reales (3 hero: Ingresos/Clientes/Comisiones + Por cobrar + Tickets abiertos) | ✅ Overlay ingresos diarios 2 líneas (ApexCharts) | Top performers, Riesgos/Oportunidades, Activity feed, Insights, Action items |
| **Finanzas** | ✅ 4 reales: MRR, Por cobrar, Tasa cobro %, Cartera vencida | ❌ | Top deudores, Cash flow 4 sem |
| **Operaciones** | ⚠️ 4 (KPI #2 "Por estado" era label calculado sin delta real; tiempo_promedio ausente) | ❌ | by_status barras, by_priority chips |
| **Ventas** | ⚠️ 4 (Clientes nuevos+Comisiones con delta; Embajadores activos+Referidos sin delta) | ❌ | — |
| **Red** | ✅ 4 reales (clientes_activos, onus online/caídas, olts activas) | ❌ | Estado ONUs; sin PPPoE ni uso por OLT |
| **Marketing** | ✅ 4 reales (publicaciones/campañas/leads) | ❌ | "Canal de mensajes": texto placeholder honesto sobre Evolution API |
| **Talento** | ✅ 4 operativos (activos/asistencia hoy/OTs hoy/validadas hoy) | — apropiado | Alertas, top performers, links — completo |

---

## Qué se agregó por vista

### Finanzas (commit 40f2087)
- **Gráfica ApexCharts line** antes de "Top deudores": MRR semanal × 3 meses (este mes verde w=3, anterior azul w=1.5, hace 2 meses gris w=1)
- **Backend:** `weeklySeriesHelper` privado; `finanzasKpis()` incluye `weekly_series` (MRR pagado agrupado por DAY BETWEEN Sem 1-4, 3 períodos)
- **KPIs confirmados OK** — tasa_cobro y cartera_vencida ya existían; no se rehizo

### Operaciones (commit 4c4b8a1)
- **KPI reemplazado:** "Por estado" (label calculado sin valor numérico comparativo) → **"Tiempo prom. resolución"** (avg TIMESTAMPDIFF HOUR, tasks Done del período, current+previous; formato h o d si ≥48h; invert=true porque menor es mejor)
- **Gráfica ApexCharts line** antes de "Por estado" panel: tickets cerrados por semana × 3 meses
- **Backend:** `tiempo_promedio` (current+previous) + `weekly_series` en `operacionesKpis()`

### Ventas (commit 80361d3)
- **Gráfica ApexCharts line** antes de InsightsBlock: clientes nuevos por semana × 3 meses
- **Backend:** `weekly_series` en `ventasKpis()` (clients.created_at agrupado por semana)

### Red (commit 8b483f6)
- **KPI #4 cambiado:** "OLTs activas" → **"PPPoE configurados"** (5,407 cuentas de mikrotik_client_ppoes; OLTs activas/total pasa al delta de este KPI)
- **Panel nuevo "Uso por OLT":** tabla con barras coloreadas por uptime para cada OLT real (datos de olt_pon_ports + olts); color verde ≥90%, ámbar ≥70%, naranja <70%
- **Gráfica ApexCharts line:** tickets sin internet por semana × 3 meses (proxy de incidencias de red)
- **Backend:** `ppoe_activos` + `olt_uso` (SUM online_onus/onus_count + COUNT ports, agrupado por OLT) + `weekly_series`

### Marketing (commit 8116d46)
- **Panel "Canal de mensajes" reemplazado:** lista real de 6 canales configurados (WhatsApp, Facebook, Instagram, Email, SMS, Voz) con conteo de publicaciones del mes (actualmente 0 — datos honestos, no placeholder falso); nota explícita de que apertura/entrega requiere integración Evolution API
- **Gráfica ApexCharts line:** leads captados por semana × 3 meses
- **Backend:** `canal_desglose` (LEFT JOIN marketing_channels + marketing_publications del mes) + `weekly_series` (marketing_leads.created_at)

---

## Qué NO se hizo (tareas separadas)

| Área | Pendiente | Razón |
|------|-----------|-------|
| OLT/MikroTik en vivo | Métricas en tiempo real (throughput, latencia, % utilización por puerto) | Requiere integración activa con SmartOLT/RouterOS API; fuera de alcance de esta tarea |
| Marketing — apertura/entrega | Tasa de apertura, entrega, clics por canal | Requiere Evolution API analytics (actualmente no expuesto en API) |
| Resumen — gráfica 3 períodos | Tiene gráfica diaria 2 líneas; no se actualizó a semanal 3 líneas | Ya tiene gráfica funcional; task spec excluye vistas que ya tienen gráfica |
| Talento — gráfica semanal | No se agregó gráfica | KPIs son "hoy" (operativos), no acumulados mensuales; no aplica comparativo semanal |
| Insights background | Regeneración automática periódica de insights por vista | Job/snapshot scheduling — tarea separada |
| KPI Snapshots | Guardar snapshot de KPIs en warroom_kpi_snapshots para histórico | Requiere job programado |

---

## Esquema de la gráfica comparativa (patrón reutilizado)

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
| Finanzas | client_invoices | estado='Pagado', payment_date (varchar) |
| Operaciones | tasks | status='Done', created_at, updated_at (timestamp) |
| Ventas | clients | created_at, deleted_at |
| Red (PPPoE) | mikrotik_client_ppoes | COUNT total (sin campo status) |
| Red (OLTs) | olt_pon_ports + olts | online_onus_count, onus_count, olt_id → name |
| Red (gráfica) | tasks | title LIKE '%sin internet%' (proxy) |
| Marketing | marketing_channels + marketing_publications | LEFT JOIN channel_id, status='published' |
| Marketing (gráfica) | marketing_leads | created_at |

# Auditoría: Métricas de desempeño por colaborador — MegaISP
**Fecha:** 2026-06-08 · **Tipo:** solo lectura · **Autor:** Claude Code

---

## 1. Qué muestra War Room hoy por colaborador

El War Room tiene exactamente **3 rankings por persona**, todos en la vista de cada sección:

| Vista | Bloque | Tabla | Campo de agrupación | Métrica |
|-------|--------|-------|---------------------|---------|
| **Resumen** | Top captadores | `clients` JOIN `users` | `clients.created_by` → `users.id` | COUNT clientes nuevos/mes |
| **Finanzas** | Top deudores | `client_invoices` JOIN `clients` JOIN `users` | `clients.user_id` → `users.id` | SUM deuda + COUNT facturas sin pagar |
| **Talento** | Top performers | `talento_work_orders` JOIN `talento_colaboradores` JOIN `users` | `wo.colaborador_id` → `talento_colaboradores.id` → `user_id` | SUM `wo.points` donde `status='validated'` en el período |

Todo lo demás que el War Room muestra son totales agregados (sin desglose por persona).

---

## 2. Qué calcula Talento por colaborador (escalafón)

### CompositeScoreService — 5 componentes (score 0-100)

| Componente | Peso | Tabla origen | Campo de colaborador | Fórmula |
|------------|------|-------------|----------------------|---------|
| **quota** | 35% | `talento_work_orders` + `talento_project_activities` | `colaborador_id` | Promedio semanas: (internas + externas) / quota_semanal |
| **quality** | 25% | `talento_caja_inspections` | `colaborador_id` | % registros con `overall_result = 'pass'` |
| **health_bonus** | 15% | `talento_health_bonus_log` | `colaborador_id` | % OTs con `eligible = 1` |
| **normas** | 15% | `talento_penalties` | `colaborador_id` | `1 - min(penalidades_activas, 5) / 5` (inverso) |
| **attendance** | 10% | `talento_attendances` | `colaborador_id` | % días `day_type = 'worked'` / días esperados en período |

Todos los IDs son FK a `talento_colaboradores.id`, que tiene FK UNIQUE `user_id → users.id`.

### DashboardService — vista individual (`tecnicoPreview`)

Métricas adicionales por colaborador que NO entran en el escalafón pero ya existen:

| Métrica | Tabla | Campo |
|---------|-------|-------|
| Unidades internas (puntos) | `talento_work_orders` | `colaborador_id` + `status='validated'` |
| Unidades externas (proyectos) | `talento_project_activities` | `colaborador_id` |
| Asistencia: días ausentes | `talento_attendances` | `colaborador_id`, `day_type='absent'` |
| Créditos extra / débitos | `talento_ledger_entries` | `colaborador_id` |
| Préstamos, fondos, deducciones | `talento_loans`, `talento_funds` | `colaborador_id` |
| Últimas OTs en período | `talento_work_orders` | `colaborador_id` |

---

## 3. Atribución de actividades — tabla completa

| Actividad | Tabla | Campo usuario responsable | Tipo FK | Ya en WarRoom | Ya en Talento | Notas |
|-----------|-------|--------------------------|---------|--------------|--------------|-------|
| Alta de cliente | `clients` | `created_by` | → `users.id` | ✅ Top captadores | ❌ | Único campo captador identificable |
| Factura / deuda morosa | `client_invoices` | `clients.user_id` (JOIN) | → `users.id` | ✅ Top deudores | ❌ | FK indirecta, requiere JOIN con clients |
| Ticket creado | `tasks` | `created_by` | varchar(255) | ❌ | ❌ | Tipo varchar, no FK tipada |
| Ticket asignado | `tasks` / `task_user` | `assigned_to` / `task_user.user_id` | varchar / FK | ❌ | ❌ | `task_user` pivot tiene FK real a `users.id` |
| Ticket cerrado | `tasks` | `updated_by` (inferencia) | varchar(255) | ❌ | ❌ | No hay campo `closed_by` explícito |
| OT ejecutada (técnico) | `talento_work_orders` | `colaborador_id` | → `talento_colaboradores.id` | ✅ Top performers puntos | ✅ Componente quota 35% | **Campo principal Talento** |
| OT creada/asignada | `talento_work_orders` | `created_by`, `assigned_by` | → `users.id` | ❌ | ❌ | Sin métrica de productividad backoffice |
| OT validada (admin) | `talento_work_orders` | `validated_by` | → `users.id` | ❌ | ❌ | Podría medir velocidad de validación |
| Activación OLT/ONU | `talento_work_order_activations` | `activated_by` | → `users.id` | ❌ | ❌ | Tabla Talento Fase 4a; user que dispara OLT |
| Incidencia OT | `talento_work_order_incidents` | `created_by` | → `users.id` | ❌ | ❌ (usa status de OT) | Count de OTs con problema por técnico |
| Asistencia (check-in/out) | `talento_attendances` | `colaborador_id` | → `talento_colaboradores.id` | ❌ | ✅ Componente attendance 10% | — |
| Inspección caja | `talento_caja_inspections` | `colaborador_id` | → `talento_colaboradores.id` | ❌ | ✅ Componente quality 25% | — |
| Penalización activa | `talento_penalties` | `colaborador_id` | → `talento_colaboradores.id` | ❌ | ✅ Componente normas 15% | — |
| Bono salud de red | `talento_health_bonus_log` | `colaborador_id` | → `talento_colaboradores.id` | ❌ | ✅ Componente health_bonus 15% | — |
| Crédito/débito compensación | `talento_ledger_entries` | `colaborador_id` | → `talento_colaboradores.id` | ❌ | ❌ (input contable) | Compensación bruta por período |
| Actividad en OT (tiempo) | `talento_work_order_activities` | `recorded_by` | → `users.id` | ❌ | ❌ | Auditoría de tiempo por usuario |
| Cualquier cambio en el sistema | `activity_log` | `causer_id` | → `users.id` | ❌ | ❌ | Fuente universal; events: created/updated/deleted |

### Observación sobre `tasks.created_by`

El campo es `varchar(255)`, no una FK tipada a `users.id`. Funciona como user_id numérico almacenado como string (herencia del BaseModel). Para agregarla en un ranking se haría `CAST(created_by AS UNSIGNED)`. Es funcional pero no tiene constraint de integridad referencial.

---

## 4. Cobertura de atribución: resumen ejecutivo

```
Actividades atribuibles HOY (campo de usuario existe):
  ✅ Alta de cliente            → clients.created_by
  ✅ OT ejecutada               → talento_work_orders.colaborador_id
  ✅ OT validada                → talento_work_orders.validated_by
  ✅ Activación OLT             → talento_work_order_activations.activated_by
  ✅ Incidencia OT              → talento_work_order_incidents.created_by
  ✅ Ticket creado              → tasks.created_by (varchar, CAST necesario)
  ✅ Ticket asignado            → task_user.user_id (pivot, FK real)
  ✅ Asistencia                 → talento_attendances.colaborador_id
  ✅ Inspección caja            → talento_caja_inspections.colaborador_id
  ✅ Penalización               → talento_penalties.colaborador_id
  ✅ Compensación               → talento_ledger_entries.colaborador_id
  ✅ Auditoría general          → activity_log.causer_id

Actividades SIN campo de usuario explícito:
  ❌ Ticket cerrado             → no hay closed_by; se infiere por updated_by o tasks.finish_at
  ❌ Activación cliente OLT     → olt_onus viene del OLT físico sin causer
```

**Cobertura: ~12 de 13 actividades relevantes tienen atribución directa.**

---

## 5. Diferencia entre `users.id` y `talento_colaboradores.id`

Hay dos "namespaces" de usuario en el sistema:

| Espacio | Tabla | Quiénes | Uso |
|---------|-------|---------|-----|
| **users** | `users` | TODO el personal (admins, backoffice, técnicos, vendedores) | Login, permisos, clientes, tickets, OLT |
| **talento_colaboradores** | `talento_colaboradores` | Solo técnicos de campo registrados en Talento | OTs, asistencia, compensación, escalafón |

Relación: `talento_colaboradores.user_id = users.id` (UNIQUE, 1:1). Un técnico existe en ambas tablas. Un admin NO existe en `talento_colaboradores`.

**Implicación para la vista comparativa:**
- Si la vista compara SOLO técnicos → agrupar por `talento_colaboradores.id` y hacer JOIN a `users.name`.
- Si la vista compara TODO el personal (tickets + altas + validaciones) → agrupar por `users.id` y hacer LEFT JOIN con `talento_colaboradores` para enriquecer con métricas de campo donde aplique.

---

## 6. Actividades del activity_log

La tabla `activity_log` (Spatie Laravel Activitylog) captura:
- `causer_type = 'App\Models\User'` + `causer_id = users.id` — quién hizo la acción
- `subject_type` — el modelo afectado (Client, Task, Invoice, etc.)
- `event` — `created`, `updated`, `deleted`
- `properties` — JSON con datos before/after

Es la fuente más amplia pero también la más ruidosa. Para un ranking de desempeño sería útil como fallback para actividades sin atribución directa, o para medir "actividad total del sistema" por usuario.

Eventos más frecuentes registrados: `created` y `updated` sobre los modelos de negocio principales (clients, tasks, invoices, etc.). Volumen alto; agregar por período requiere índice en `created_at + causer_id`.

---

## 7. Conclusión: qué se puede armar y qué falta

### Se puede armar HOY (datos 100% disponibles):

Una vista comparativa de desempeño por colaborador que muestre, para cada técnico/usuario en un período seleccionado:

| KPI | Fuente | JOIN necesario | Complejidad |
|-----|--------|---------------|-------------|
| Clientes captados | `clients.created_by` | → `users` | Baja |
| OTs ejecutadas (cantidad) | `talento_work_orders.colaborador_id` | → `talento_colaboradores` → `users` | Baja |
| OTs validadas (puntos) | `talento_work_orders` WHERE `status='validated'` | ídem | Baja |
| OTs con incidencia | `talento_work_order_incidents.created_by` | → `users` | Baja |
| Activaciones OLT | `talento_work_order_activations.activated_by` | → `users` | Baja |
| Tickets abiertos | `tasks.created_by` (CAST) | → `users` | Baja (CAST quirk) |
| Asistencia % | `talento_attendances.colaborador_id` | → `talento_colaboradores` → `users` | Media |
| Score compuesto (0-100) | `CompositeScoreService` ya existe | — | Ya existe en Talento |

**Total construible sin nuevas tablas:** 8 KPIs por persona.

### Qué falta atribuir (requeriría trabajo):

| Actividad | Brecha | Esfuerzo estimado |
|-----------|--------|-------------------|
| Ticket cerrado con resolución | No hay `closed_by` en `tasks`; se necesitaría agregar el campo o usar `task_user` + `updated_by` como proxy | Migración + lógica en controller |
| Velocidad de atención tickets | Sin timestamp de asignación vs cierre por usuario | Migración + timestamps adicionales |
| Activación ONU sin OT (desde backoffice) | `olt_onus` no tiene `causer_id`; solo las activaciones via Talento están atribuidas | Agregar campo `activated_by` en `olt_onus` si se quiere rastrear activaciones manuales |

---

## 8. Recomendación: ¿nueva vista War Room o parte de Talento?

### Opción A — Nueva vista en War Room (recomendada para visibilidad ejecutiva)

**Pros:**
- El War Room ya tiene la estructura de KPIs comparativos en 7 secciones
- Los directivos ya la usan para reuniones de seguimiento
- Se pueden comparar técnicos, vendedores y backoffice en una sola pantalla
- Los datos ya existen; solo es cuestión de hacer un nuevo endpoint en `KpiController`

**Contras:**
- Los 5 componentes del escalafón de Talento ya calculan scoring profundo; duplicar en War Room sería superficial

**Vista sugerida:** "Desempeño" (tab #8 o drawer lateral) con selector de rol (técnicos / ventas / backoffice) y tabla comparativa con los 8 KPIs de la sección anterior.

### Opción B — Ampliar el módulo Talento

**Pros:**
- El escalafón y `CompositeScoreService` ya hacen el trabajo pesado
- Más apropiado para RRHH / supervisores que necesitan detalle de técnicos

**Contras:**
- Solo aplica a colaboradores de Talento; excluye a vendedores y backoffice

### Recomendación final

**Combinar ambas:** la vista War Room sería una tabla ejecutiva simplificada (top N por KPI, visible para gerencia en reunión). El escalafón de Talento mantiene el scoring profundo para RRHH y supervisores. No hay duplicación real porque las métricas de War Room son conteos simples mientras el escalafón usa fórmulas ponderadas.

El punto de partida de menor fricción: agregar un nuevo endpoint `KpiController::desempenoKpis()` que devuelva la tabla de 8 KPIs por colaborador agrupando las consultas ya existentes en el controller, y añadir una vista `ViewDesempeno.vue` siguiendo el patrón de las 7 vistas existentes.

---

*Archivo generado por auditoría de solo lectura — ningún archivo fue modificado.*

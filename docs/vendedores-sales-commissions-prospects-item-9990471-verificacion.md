# Item #9990471 — Vendedores: `sales`/`commissions`/`prospects` vacías en dev

Investigación SOLO LECTURA. No se importó, cargó, borró ni generó ningún dato — este documento
es el entregable pedido por el item: el veredicto + evidencia para que Irving decida.

## 1. Estado real de las tablas en dev

| Tabla | Filas | Rol |
|---|---|---|
| `sales` | **0** | Sin modelo Eloquent. Sin migración versionada (ni `database/migrations/` ni `migrations_old/`). Sólo existe como `CREATE TABLE` en dumps SQL viejos de mayo 2024 (`database/sql/sql_old.sql:3368`, `database/sql/db_27_5_24.sql:3439`). |
| `commissions` | **0** | Modelo `App\Models\Commission` (sin `$table`, usa convención). Truncada dos veces en julio 2024 (ver §3) y nunca repoblada. |
| `prospects` | **0** | Sin modelo Eloquent. Sin migración versionada. Sólo dump SQL de mayo 2024. |
| `commissions_details` | **566,780** | Modelo `App\Models\CommissionDetail`. Todas las filas son **huérfanas**: `commission_id` distintos = 566,780 = 100% de las filas, y `commissions` tiene 0 filas → ningún registro tiene padre. |
| `commissions_rules` | 8 | Modelo `App\Models\CommissionRule`. Configuración de reglas de comisión — viva y en uso (UI propia en Vendedores → Comisiones). |
| `commissions_rules_sellers` | 12 | Pivote reglas↔vendedor. |
| `payment_by_rule_commissions` | 72 | — |
| `medium_sales` | 12 | Catálogo (medios de venta). |
| `ranges_of_sales_sectors` | 15 | Catálogo (rangos/sectores). |
| `history_sellers_rules` | 39 | — |
| `transactions_sellers` | 2 | — |
| `referral_commissions` | 46 | Módulo **Embajadores** (dominio distinto). |
| `referral_prospects` | 11 | Módulo **Embajadores** (dominio distinto). |
| `prospect_followups` | 1 | Huérfana de facto — su tabla "padre" conceptual (`prospects`) está vacía. |
| `sellers` | 29 | Catálogo de vendedores — vivo. |
| `discounts_sales`, `distribution_commission_sales`, `distribution_commission_sales_amount` | 0 | — |

## 2. Quién escribe (o escribía) en `sales`/`commissions`/`prospects`

- **`sales`**: cero escritores en todo el repo. Ningún modelo Eloquent, ningún `DB::table('sales')`.
- **`prospects`**: cero escritores reales. Sólo aparece como literal `'prospects'` en
  `app/Modules/Addons/SmartImportExport/Services/SmartImportService.php:784` (`mode => 'raw'`,
  sin modelo asociado) — es decir, está declarada como *importable* pero nada en el sistema
  la puebla en operación normal.
- **`commissions`** (y `commissions_details`): el mecanismo de escritura SÍ existe en el código —
  `app/Listeners/CalculateClientCommission.php` y `app/Listeners/CalculateProspectCommission.php`
  — pero el cuerpo completo de ambos `handle()` está **comentado en bloque** (`/* ... */`) desde
  hace tiempo. Los eventos que los disparan (`ClientRegistered`, `ProspectRegistered`) SÍ se
  emiten hoy (`app/Modules/Core/CRM/Controllers/CrmController.php:82`,
  `CrmInformationController.php:32`, registrados en `EventServiceProvider.php:57-61`) — el listener
  está enganchado pero no hace nada. Confirma código **inerte, no vivo**: el evento dispara, el
  listener no actúa.
- Migraciones históricas (`database/migrations_old/`) muestran el churn: `commissions_rules` se
  truncó el 2024-07-02, `commissions` el 2024-07-09, y **`commissions` + `commissions_details`
  juntas** el 2024-07-12 (`2024_07_12_210549_truncate_table_commissions_and_details_commissions.php`).
  Las 566,780 filas actuales de `commissions_details` tienen `created_at` desde 2024-07-15 en
  adelante (verificado con el primer registro, id=1) — es decir, **`commissions_details` se
  repobló después del truncate conjunto, pero `commissions` no**. Esto es evidencia directa de un
  import/sync parcial: llegó el detalle, no llegó (o se perdió) el padre.

## 3. ¿De dónde sale lo que la UI de Vendedores muestra HOY?

Ninguna pantalla activa de Vendedores lee `sales` o `prospects`:

- **Pestaña Ventas** (`SaleController.php`, `graphics/{Sales,SalesByMonth,RankingSale}.vue`):
  lee de `client_main_information` (clientes reales con `seller_id`), no de `sales`.
- **Pestaña Prospectos** (`ProspectController.php`, `StatusProspects.vue`): lee de
  `crm_lead_information`/`crm_main_information` (módulo **CRM**), no de `prospects`.
- **Pestaña Comisiones** (`ComissionController.php` en `Core/Configuracion/Controllers/Commission/`,
  `CommissionsList.vue`): hace `commissions` **INNER JOIN** `commissions_details`. Como
  `commissions` está vacía, este endpoint específico (`/configuracion/comisiones/{id}/get-commissions-by-seller`)
  devuelve vacío HOY pese a que `commissions_details` tiene 566K filas — son huérfanas, ningún
  JOIN las alcanza.

`referral_prospects`/`prospect_followups` (Embajadores) son un dominio de negocio distinto
("prospecto referido" de un programa de referidos), no el mismo concepto que "prospecto de
vendedor" en la pestaña de Vendedores.

## 4. Veredicto por tabla

| Tabla | Veredicto | Evidencia clave |
|---|---|---|
| **`sales`** | **(c) Descontinuada / nunca llegó a construirse.** Diseño original de mayo 2024, sin modelo, sin escritor, sin lector. La feature "Ventas" real vive en `client_main_information`. | Sin migración versionada; sin consumidores en `app/`/`resources/`. |
| **`prospects`** | **(c) Descontinuada.** Reemplazada por el módulo CRM (`crm_lead_information`) antes de que este código llegara a usar la tabla. | Sin modelo; sin consumidores; el CRM ya cubre ese rol. |
| **`commissions`** | **(a)/(b) mixto — no encaja limpio en ninguna.** El listener que la poblaría existe pero está comentado (código inerte). Su tabla hija (`commissions_details`) SÍ tiene 566K filas reales, casi con certeza importadas/sincronizadas desde producción en julio 2024, pero **sin sus padres** — el import fue parcial o el truncate del 2024-07-12 se aplicó después de repoblar el detalle. **No se puede afirmar "falta importar de prod" sin que Irving confirme**: podría ser que prod tampoco tenga `commissions` poblada (que el sistema real de comisiones haya migrado a otro mecanismo tras julio 2024), o que sí exista en prod y sólo faltó ese tramo del import en dev. | JOIN huérfano 566,780/566,780; truncados conjuntos en julio 2024; listener comentado. |

## 5. Impacto en items relacionados

- **#9990448/#9990449/#9990450** (llevar estas vistas a Talento): si el veredicto de `sales`/
  `prospects` es "descontinuadas" (confirmado arriba), esas vistas en Talento saldrían vacías
  igual que hoy en el admin — no es un problema de Talento, es que no hay dato de origen. Para
  "Comisiones" en Talento, el mismo problema del JOIN huérfano aplicaría si se reusa
  `ComissionController` tal cual.
- **#9990453** (análisis de migración de comisiones): la pieza que falta decidir es puntual —
  ¿el negocio de "comisiones" sigue vivo hoy vía otro mecanismo (p.ej. derivado de `payments`/
  `invoices`, revisar `TransactionSeller`/`commissions_rules` en vivo), o `commissions_details`
  es un snapshot histórico congelado en julio 2024 que ya no se usa? Esa pregunta no se puede
  responder sólo con dev — necesita el estado real en producción.

## 6. Recomendación (sin ejecutar nada)

1. **`sales` y `prospects`**: recomendación de cerrar como descontinuadas — el trabajo real no
   está en repoblarlas sino en no construir nada nuevo sobre ellas (Ventas/Prospectos de
   Vendedores ya funcionan sobre `client_main_information`/CRM). Decisión de Irving si además se
   quieren limpiar del catálogo de `SmartImportService` (item nivel B/C, cosmético).
2. **`commissions`**: antes de decidir importar o no, verificar en **producción** si esa tabla
   tiene filas hoy. Si prod SÍ tiene `commissions` poblada → es candidato claro a "falta importar
   a dev" (camino (a), vía SmartImport, que ya declara `commissions`/`commissions_details` como
   importables). Si prod también la tiene en 0 → el negocio de comisiones migró a otro mecanismo
   después de julio 2024 y `commissions_details` es un snapshot muerto — ahí sí sería (c), y
   valdría la pena entender qué reemplazó a `commissions`/`CalculateClientCommission` antes de
   construir nada nuevo (Talento, #9990453) sobre este esquema.

Ninguna de estas dos acciones se ejecutó — quedan para que Irving decida con este veredicto en
mano.

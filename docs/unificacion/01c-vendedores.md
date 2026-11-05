# UNI-01c · Auditoría de Vendedores: ventas, atribución, metas y reglas de comisión

Item #682 · Documento 1 (estado actual), pieza 3 · 2026-10-24 · **Solo lectura**: no se cambió ninguna
regla, cálculo, dato ni permiso. Conteos de dev, solo agregados (sin personas ni montos por persona).
Contrato: `plan-maestro-unificacion.md` §8 (modelos A/B/C/D) y §13 (integración sin pérdida).

## 1. Resumen ejecutivo (hallazgos que cambian el plan)

1. **Hay dos motores de ventas, y ninguno calcula comisiones por evento hoy.**
   > **Actualizado tras auditoría UNI-T A5 (2026-10-26):** corrección: el cálculo **semanal** sí paga comisión por venta (21 de los 72 pagos de `payment_by_rule_commissions` son comisión adicional sobre el mínimo, `AdditionalSalesCommissionPayment`); lo apagado es solo la comisión **por evento** (listeners). Existe además un **tercer camino de pago**: el bono semanal de Talento (`TalentoWeeklyRuleEvaluationService`, ledger de Talento). El *legacy* (`sellers`,
   `commissions_rules`, `payment_by_rule*`) paga **sueldo fijo y bonos semanales** (72 filas históricas) pero
   la comisión por venta **por evento está apagada**: el cuerpo de `CalculateClientCommission` y
   `CalculateProspectCommission` está comentado. El *nuevo* (`ventas_*`, módulo `Ventas`, 2026-09-11) tiene
   prospectos y custodia, pero `ventas_ventas`, `ventas_esquemas_comision`, `ventas_maduraciones` y
   `ventas_ledger_movimientos` están en **0 filas** y sin código que las escriba.
2. **La atribución real es una sola columna:** `client_main_information.seller_id` (4,439 de 5,847 clientes),
   y guarda **`users.id`**, no `sellers.id` (4,420 coinciden con `users.id`; solo 2,557 coinciden con
   `sellers.id`, por colisión numérica). `colaborador_id` en paralelo existe pero está en **0 filas**: la
   doble escritura (`identidad.doble_escritura_colaborador_id`) sigue **OFF**.
3. **No hay protección contra atribución duplicada** en el modelo legacy: `seller_id` es una columna simple,
   sin historial ni único por venta; reasignar sobrescribe. Solo `ventas_prospectos` tiene único
   `(origen, origen_id)`.
4. **Un técnico o mostrador puede ser "vendedor" sin ceremonia:** `Actor::seller()` busca `sellers` por
   `user_id` y el docblock dice que "todo colaborador nace vendedor". Hoy 32 filas `sellers`, 9 sin ficha viva de
   Talento. Lo que falta es la **política de elegibilidad** (qué ventas cuentan), no la capacidad.
5. **Las reglas actuales no alcanzan para los modelos A–D** (ver §5): 8 reglas, todas semanales, ligadas a
   persona por pivote; sin vigencia, puesto, departamento, campaña ni producto. Existe historial de cambios
   (`history_sellers_rules`, 39 filas) pero es un volcado JSON, no versionado consultable.
6. **No existen metas.** Ninguna tabla de metas/cuotas en el sistema (barrido de `SHOW TABLES` por
   goal/meta/quota/cuota/objetiv: solo `client_payment_metadata`, que no es eso). «Metas vigentes» del
   contrato = **nada que conservar**; hay que crearlas.
7. **Histórico con huérfanos:** `commissions` = 0 filas y `commissions_details` = **566,780** (100 % sin
   padre, jul–ago 2024; 566,764 «Venta Base», 15 «Venta», 1 «Prospecto», bonus sumado 0). Los endpoints de
   comisiones por vendedor devuelven vacío. Detalle previo: `docs/vendedores-sales-commissions-prospects-item-9990471-verificacion.md`.
8. **Rutas rotas confirmadas:** `/sellers/prospectos` y `/sellers/statistics` no existen (son del menú
   viejo; las reales son `/vendedores/prospectos` y `/vendedores/dashboard`). Item #585.
   > **Actualizado tras auditoría UNI-T A5 (2026-10-26):** las rutas rotas **actuales** son `/statics/*`, `/vendedores/prospectos` y `createComission`; la lista de arriba es la de la auditoría original.

## 2. Inventario

### 2.1 Tablas (dev)

| Tabla | Filas | Uso real |
|---|---|---|
| `sellers` | 32 | Una por vendedor/colaborador; `user_id`, `type_id`, `status_id`, `balance`, `range` |
| `seller_types` / `seller_status` | 5 / 5 | Catálogo (tipos duplicados por nombre: «Interno»×2, «Externo»×2) |
| `commissions_rules` | 8 | Reglas semanales (sueldo fijo, % o $ por venta, bonos) |
| `commissions_rules_sellers` | 12 | Pivote regla↔vendedor; `colaborador_id` 0/12 |
| `history_sellers_rules` | 39 | Historial de reglas por vendedor (JSON `data`); `colaborador_id` 0/39 |
| `payment_by_rule` / `payment_by_rule_commissions` | — / 72 | Pagos calculados por semana: 51 `fixed_salary`, 21 `additional_sales_commissions` |
| `transactions_sellers` | 2 | Movimientos de saldo del vendedor |
| `payments_sellers` | 0 | Pagos al vendedor (vacía) |
| `discounts_sales` | 0 | Descuentos (vacía) |
| `commissions` / `commissions_details` | 0 / 566,780 | Ver hallazgo 7 |
| `sales` / `prospects` | 0 / 0 | Sin modelo, sin migración, sin consumidores (item #9990471) |
| `distribution_commission_sales(_amount)` | 0 / 0 | Comisión de distribuidores (vacía) |
| `ranges_of_sales_sectors` | 15 | Rangos de venta por sector (bronce/plata/oro) |
| `medium_sales` | 12 | Medios de venta |
| `ventas_prospectos` | 58 | Motor nuevo: todos `origen=crm`, estado `pool`; 47 con `colaborador_id`, 11 sin |
| `ventas_custodias` / `ventas_evidencias` | 0 / 0 | Custodia de prospectos (Ventas) sin uso aún |
| `ventas_ventas`, `ventas_esquemas_comision`, `ventas_maduraciones`, `ventas_ledger_movimientos` | 0 | Esqueleto del motor nuevo |
| `referrals` / `referral_commissions` / `referral_closures` | 101 / 46 / 42 | Embajadores; comisiones 30 `approved`, 16 `applied` |
| `referral_prospects` / `referral_commission_tiers` | 11 / 3 | Prospectos y niveles de embajador |
| `talento_comisiones_espejo` | 0 | Puente Vendedores→Talento (flag OFF) |

### 2.2 Código y rutas

| Pieza | Dónde | Notas |
|---|---|---|
| Addon Vendedores | `app/Modules/Addons/Vendedores/` (module.json, 35 permisos `seller_*`/`selleritems_*`) | Controllers: panel, ventas, prospectos, cortes de caja, pagos, reglas |
| Modelos legacy | `app/Models/{Seller,CommissionRule,Commission,CommissionDetail,TransactionSeller,PaymentSeller,HistorySellerRule,RangeSale}.php` | Siguen en `app/Models`, no en el módulo |
| Cálculo semanal | `app/Services/CalculateBalanceSellerService.php` + `app/Pipes/*` | Pipeline: FixedSalary → SalesCommission → AdditionalSales → Distributors → Discount (+ MonthlyBonus) |
| Listeners de comisión | `app/Listeners/Calculate{Client,Prospect}Commission.php` | Registrados en `EventServiceProvider:65-69`, **cuerpo comentado** |
| Reglas (UI/API) | `/configuracion/reglas-comisiones*` → `CommissionRuleController` | CRUD + asignación a vendedores |
| Reportes | `/vendedores/ventas*`, `/statics/*` | Leen de `client_main_information`/CRM |
| Ventas (nuevo) | `app/Modules/Addons/Ventas/` | Solo custodia: `/ventas/custodias` (rol super-admin/DESARROLLADOR, sin permiso Spatie propio) |
| Embajadores | `app/Modules/Addons/Embajadores/` + `app/Listeners/Referrals/*` | Comisiones por factura pagada (`InvoicePaid`) |
| Identidad | `ColaboradorIdBridgeObserver`, `ColaboradorIdResolver`, `IdentidadConsistencyService`, `config/identidad.php` | Docs: `docs/identidad-*.md` |

### 2.3 Permisos
- 35 permisos `seller_*`/`selleritems_*` en dev; `seller_view_panel` lo portan 9 roles/asignaciones.
- Talento: `talento.ventas.view`, `talento.comisiones.view/manage`, `talento.caja-vendedor.view/manage`.
- `ventas-custodia.*` **no usa permiso**, solo rol (declarado en su propio `routes.php`).

## 3. Atribución: cómo se liga hoy una venta a quien la hizo

| Vía | Mecanismo | Evidencia |
|---|---|---|
| Alta de cliente | `client_main_information.seller_id` (= `users.id`) | 4,439/5,847; 27 valores distintos |
| Identidad unificada | `colaborador_id` junto a `seller_id`, por observer detrás de flag | 0 filas; flag OFF (`config/identidad.php`); `docs/identidad-seller-id-callsites-item-9990962-verificacion.md` (4 callsites; 2 clases Eloquent con observers separados; el import SMART escribe raw) |
| Prospecto CRM | Dueño en `crm_lead_information.owner_id`; la conversión copia `seller_id` (`ConvertToClientRequest`) | `crm_main_information` no tiene `seller_id` |
| Motor nuevo | `ventas_prospectos.colaborador_id` con único `(origen, origen_id)` | 58 filas, 47 con dueño |
| Embajadores | `referrals.embajador_id` → `referred_client_id`, cadena `chain_path` | 101 referidos; independiente de `seller_id` |

**¿Puede un técnico/mostrador atribuirse una venta sin ser vendedor?** Hoy solo si existe fila `sellers`
(o se pone su `users.id` en `seller_id`): no hay validación de elegibilidad. Con el motor nuevo basta
`colaborador_id`. **Decisión pendiente** (ver Preguntas) de quién puede ser dueño y bajo qué reglas.

**¿Qué impide atribuciones duplicadas?** En legacy, nada: una columna, sobrescribible, sin bitácora
dedicada (solo `activity_log` general del modelo). Un mismo cliente puede además estar en `referrals`
(embajador) **y** tener `seller_id`: dos comisiones posibles por la misma venta, sin regla de exclusión
documentada. En el motor nuevo, solo previene el duplicado del prospecto, no de la venta.

## 4. Reglas de comisión existentes

`commissions_rules` (8 filas, todas `period = Semanal`):

| Tipo observado | Reglas | Campos que lo definen |
|---|---|---|
| Interno, sueldo fijo | 3 | `is_fixed_salary`, `fixed_salary` |
| Interno, comisión `$` por venta | 3 | `sales_commission`, `sales_commission_type`, `minimum_sales`, `monthly_bonus` |
| Externo, comisión `$` | 1 | `type_of_seller = 2` |
| Sin tipo | 1 | regla incompleta |

Capacidades presentes en columnas: sueldo fijo, comisión por venta, comisión adicional sobre mínimo
(`additional_sales_commissions`, `minimum_number_of_prospects`/`number_sales_required`), bono mensual,
comisión de distribuidores, penalización, costo de instalación, zona/IVA. Condiciones en JSON
(`conditions`, `conditions_comission`).

## 5. ¿Alcanzan para los modelos A, B, C, D?

| Modelo del contrato | ¿Cubierto hoy? | Brecha |
|---|---|---|
| **A** comisión desde la 1.ª venta validada | Parcial: `sales_commission` + `minimum_sales=0` | No hay noción de "venta validada" (`ventas_maduraciones` está vacía) |
| **B** meta mínima semanal y comisión solo sobre excedente | Parcial: `AdditionalSalesCommissionPayment` (21 pagos) | La meta vive dentro de la regla; sin entidad "meta" |
| **C** bono por cuota + comisiones | Parcial: `monthly_bonus` / `total_bonus` | Bono mensual, no semanal ni versionado |
| **D** campañas/productos especiales | **No** | Sin campaña ni producto en ninguna regla |

Para versionar por persona, puesto, departamento, campaña, producto, tipo de venta y vigencia falta:
(1) una entidad de política con alcance y `vigente_desde/hasta` (el esqueleto `ventas_esquemas_comision`
ya trae `colaborador_id`, `modalidad`, `parametros`, `vigente_desde/hasta`, pero solo por persona);
(2) asignación versionada (hoy el pivote se sobrescribe); (3) estado de elegibilidad de la venta
(cancelada/rechazada/no elegible); (4) bloqueo de liquidadas (`payment_by_rule*` no tiene candado de
periodo cerrado). Hallazgo útil: el esquema nuevo ya apunta en la dirección correcta; faltan alcances
por puesto/departamento/campaña/producto.

## 6. Histórico que hay que conservar

| Dato | Estado real | Riesgo |
|---|---|---|
| Ventas históricas | Viven implícitas en `client_main_information` (+ `seller_id`, `activation_date`); `sales` vacía | Sin tabla de ventas: reconstruir de clientes |
| Comisiones calculadas | `commissions_details` 566,780 huérfanas; `commissions` vacía | No usables sin padre; no borrar |
| Comisiones pagadas | 72 filas en `payment_by_rule_commissions` (51 sueldo, 21 adicional); `payments_sellers` 0 | Fuente real de lo pagado |
| Reglas | 8 + 12 asignaciones + 39 historiales | Conservar tal cual |
| Metas vigentes | No existen | Nada que migrar |
| Referencias a clientes/contratos/pagos | Intactas vía `client_id` | Mantener |

## 7. Puente con Talento

| Pieza | Estado |
|---|---|
| `TalentoEmbajadoresController::sellerData` (`/talento/api/colaboradores/{id}/seller-data`) | Funciona, solo lectura (item #123) |
| `Actor::seller()` / sidebar «Mis prospectos» y «Mi panel» | Funciona; gatea por existencia de `sellers` |
| `TalentoVentasController` (`mis-ventas`, `ventas/ranking`, `prospectos`) | Funciona; comisión total lee `commissions` (vacía → 0) |
| `TalentoComisionSellerController` (estado de cuenta, pagos, deuda, reglas) | Funciona sobre el motor legacy |
| `TalentoCajaVendedorController`, `TalentoSellerItemsController` | Funcionan (caja y artículos del vendedor) |
| Espejo `talento_comisiones_espejo` + `talento:reconciliar-comisiones-espejo` | Código listo, flag `TALENTO_VENDEDORES_ESPEJO_ENABLED` OFF, 0 filas |
| «Mi Espacio» → «Mis Ventas/Mis Incentivos» | Base existe en Mi día/Mi dinero/Mis prospectos; faltan metas y atribución por colaborador |

## 8. Rutas rotas o sin uso

| Elemento | Hallazgo |
|---|---|
| `/sellers/prospectos`, `/sellers/statistics` | No registradas → 404 (#585). Reales: `/vendedores/prospectos`, `/vendedores/dashboard` |
| `/message/reminder` (GET) | Solo existen los POST `send_message`/`table` |
| Tablas `sales`, `prospects` | Sin consumidores |
| Listeners de comisión | Registrados pero inertes |
| `ventas-custodia` | Solo lectura, rol fijo, sin permiso |
| Tipos de vendedor duplicados | `seller_types` repite «Interno»/«Externo» |

## 9. Funciona / incompleto / duplicado / roto

| Área | Estado |
|---|---|
| Cortes de caja y pagos de vendedor | **Funciona** (legacy) |
| Reglas semanales (sueldo fijo, adicional) | **Funciona** (72 pagos históricos) |
| Reportes de ventas/prospectos del vendedor | **Funciona** (desde clientes/CRM) |
| Puente de lectura Talento | **Funciona** |
| Comisión por venta por evento | **Roto/apagado** (listeners comentados) |
| `commissions` / `commissions_details` | **Roto** (huérfanas) |
| Atribución `seller_id` ↔ `colaborador_id` | **Incompleto** (flag OFF, 0 filas) |
| Motor `ventas_*` (ventas/esquemas/maduración/ledger) | **Incompleto** (esqueleto vacío) |
| Metas | **Inexistente** |
| Dos motores de prospectos (CRM/`ventas_prospectos`) | **Duplicado** (consolidación con `ConsolidarProspectosCrmCommand`) |
| Comisión de embajador vs. de vendedor sobre un mismo cliente | **Duplicado potencial** |
| Protección anti-duplicado y de comisiones liquidadas | **Incompleto** |
| `/sellers/prospectos`, `/sellers/statistics` | **Roto** (404) |

## 10. Preguntas para Irving

1. **Fuente de verdad de la comisión por venta.** A) Reactivar los listeners legacy. B) Terminar el motor
   `ventas_*` y dejar el legacy solo como histórico. C) Híbrido hasta cerrar. *Pros/contras:* A es rápido
   pero consolida el esquema sin versionado y con `seller_id` por `users.id`; B alinea con el contrato pero
   requiere construir validación/maduración/ledger. **Recomendación: B**, sin tocar `payment_by_rule*`.
2. **Comisión de embajador y de vendedor sobre el mismo cliente.** A) Se acumulan. B) Excluyentes.
   C) Se define por campaña. **Recomendación: B por defecto**, con excepción por campaña (modelo D).
3. **¿Quién puede atribuirse una venta?** A) Cualquier colaborador activo con permiso `ventas.registrar`.
   B) Solo quien tenga política asignada. **Recomendación: B**; sin política no hay comisión (evita pagos
   accidentales).
4. **Activar la doble escritura de `colaborador_id`** (Fase 3d del plan de identidad). Recomendación:
   activar en dev, conciliar con `IdentidadConsistencyService` y recién después en prod.
5. **Metas.** No existen: ¿se modelan como parte de la política (modelo B) o entidad propia con vigencia?
   **Recomendación: entidad propia versionada** (se reutiliza en B y C).
6. **Qué hacer con las 566,780 filas huérfanas.** A) Conservar tal cual y marcarlas legado. B) Archivar
   fuera de la tabla activa. **Recomendación: A** (nada se borra; regla de §13).
7. **Regla de compensación de roles no técnicos (item #121)**, aún sin decidir: la definición condiciona
   los modelos A–D y debería cerrarse antes de construir políticas.

## Verificación

Solo lectura. Rutas: `php artisan route:list --json` filtrado. Conteos: consultas `COUNT`/`GROUP BY`/
`SHOW COLUMNS` en dev. Código leído: listeners, `CalculateBalanceSellerService`, `Actor`, observer de
identidad, `config/identidad.php`, `config/talento.php`. Sin escrituras.

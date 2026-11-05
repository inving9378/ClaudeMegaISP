# UNI-T I4 · Inventario de incentivos comerciales

Insumo para K1. Solo DEV (2026-10-26). Solo conteos y nombres técnicos.
Marcas: [código] leído en el código · [DEV] observado en la BD de DEV · [por verificar].

**Principio:** vendedor + embajador NO se consideran duplicado automático: son
beneficiarios y conceptos distintos hasta que K1 decida lo contrario.

## 1. Combinaciones (beneficiario · concepto · evento · periodo)

| # | Beneficiario | Concepto | Evento que lo origina | Periodo | Destino del pago | [DEV] |
|---|---|---|---|---|---|---|
| 1 | Vendedor (`seller_id`) | `fixed_salary` (sueldo fijo / proporcional) | Corte de pago de la regla de comisión (`FixedSalaryPayment`) | Semanal/rango de la regla (`start_date`–`end_date`) | `payment_by_rule` + `payment_by_rule_commissions` | 51 pagos |
| 2 | Vendedor | `additional_sales_commissions` (venta adicional sobre el mínimo) | Ventas por encima de `minimum_sales` en el rango (`AdditionalSalesCommissionPayment`) | Rango de la regla | ídem | 21 pagos |
| 3 | Vendedor | `sales_commission` (comisión por venta) | `SalesCommissionPayment` | Rango de la regla | ídem | 0 |
| 4 | Vendedor | `monthly_bonus` (bono mensual por ventas) | `MonthlyBonusPayment`, escalón por número de ventas | Mes | ídem | 0 |
| 5 | Vendedor-distribuidor | `distributors_commission` (pago inicial + pagos secuenciales por semana) | `DistributorsCommissionPayment`, contratos con comisión diferida | Semanal, hasta terminar el plan por cliente | ídem + `distribution_commission_sales*` | 0 (tablas en 0) |
| 6 | Colaborador (Talento) | `bonus` por regla semanal con `metric_type=ventas` (y otras métricas) | `TalentoWeeklyRuleEvaluationService::aplicarBonoAlLedger` (outcome `bonus`) | Semana PayWeek (Sáb 18:00 → Sáb 18:00) | `talento_ledger_entries` (crédito) | 3 bonos, de 3 de 17 evaluaciones; 1 de las 7 reglas activas es de ventas |
| 7 | Cliente-embajador (`beneficiary_id`) | `referral_commissions` por nivel 1–5 y tramo de velocidad | Factura del referido (`invoice_id`), pct por `referral_commission_tiers` | Mes (`period_month/year`, `period_yyyymm`) | Se aplica a una factura propia (`applied_invoice_id`) tras `apply_after_at` | 46 filas: 16 `applied`, 30 `approved`; 0 con `applied_invoice_id` |
| 8 | Vendedor (histórico) | `commissions` / `commissions_details` | Motor anterior a `payment_by_rule` | — | — | `commissions`=0, `commissions_details`=566,780 sin padre (ver item #9990471) |

Notas [código]: las filas 1–5 pasan por la misma tubería (`app/Pipes`) y se
registran en `payment_by_rule_commissions.type`; `payment_by_rule.colaborador_id`
existe pero [DEV] las 72 filas lo tienen vacío (solo `seller_id`, 4 vendedores).
`DiscountPayment` está vacío (sin efecto).

## 2. Cruces entre fuentes [DEV]

- Embajadores: 19 beneficiarios distintos en 46 comisiones; todos los beneficiarios
  son clientes (`client_main_information`), no vendedores por definición.
- Dentro de la misma tubería el vendedor cobra por cliente vendido; el embajador
  cobra por la factura de un referido. Un cliente con ambas relaciones existe
  (1 caso según la auditoría): el evento y el beneficiario difieren, por lo que
  **no es doble pago por sí mismo** — decisión económica de Irving, no se resuelve aquí.
- Talento (#6) y Vendedores (#1–#5) pagan por sistemas distintos (ledger vs
  `payment_by_rule`): un colaborador-vendedor podría recibir el bono semanal y el
  sueldo fijo del vendedor. [por verificar] si hay personas en ambos; K1 lo decide.

## 3. Insumo para K1 (puntos abiertos, sin resolver aquí)

1. ¿Los conceptos 1–2 y 6 se pagan por ambos sistemas a la misma persona?
2. ¿Se unifica el destino (`payment_by_rule` → ledger) o solo la lectura?
3. Embajadores (#7) se paga como descuento en factura, no como dinero: ¿entra al
   mismo inventario de pagos?
4. Los conceptos 3, 4, 5 y 8 no tienen datos en DEV: el comportamiento solo está
   verificado en [código].

## 4. Consultas de verificación (solo lectura)

```sql
-- 1/2 (y 3,4,5 si existieran): por concepto
SELECT type, COUNT(*), MIN(start_date), MAX(end_date), COUNT(DISTINCT payment_id)
FROM payment_by_rule_commissions GROUP BY type;
-- beneficiario del pago de vendedor
SELECT seller_id IS NOT NULL, colaborador_id IS NOT NULL, COUNT(*)
FROM payment_by_rule GROUP BY 1,2;
-- 6: bonos de regla semanal
SELECT concept, COUNT(*) FROM talento_ledger_entries
WHERE reference_type LIKE '%WeeklyRule%' GROUP BY concept;
SELECT metric_type, active, COUNT(*) FROM talento_weekly_rules GROUP BY 1,2;
-- 7: embajadores
SELECT level, status, COUNT(*), COUNT(DISTINCT beneficiary_id)
FROM referral_commissions GROUP BY level, status;
SELECT COUNT(*), SUM(applied_invoice_id IS NOT NULL) FROM referral_commissions;
-- 8: histórico
SELECT (SELECT COUNT(*) FROM commissions), (SELECT COUNT(*) FROM commissions_details);
```

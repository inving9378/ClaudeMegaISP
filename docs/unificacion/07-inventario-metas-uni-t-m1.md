# UNI-T M1 — Inventario de metas, cuotas, reglas semanales, escalafón y evaluaciones

Item #903 (paraguas #862). Etiquetas: [código] leído en el código · [DEV] observado en la BD de DEV (no producción).
Solo conteos y nombres técnicos. No cambia ninguna regla económica; las contradicciones se señalan, no se resuelven.

## 1. Inventario

| # | Meta / indicador | Dónde vive | Cómo se calcula | ¿Afecta pago? |
|---|---|---|---|---|
| 1 | Cuota semanal de unidades (`weekly_quota_units` + `period`) | Tabla `talento_compensation_rules`; modelo `Talento/Models/TalentoCompensationRule.php` (`cuotaSemanal()`); alta/edición en `Talento/Controllers/TalentoCompensacionController.php:72,104` | `cuotaSemanal()`: si `period=daily` ×6 (lun–sáb), si no tal cual. Copia congelada por colaborador en `talento_compensation_rule_history.data` (`Models/TalentoCompensationRuleHistory.php`) | **Sí**: `LiquidationService.php:99,123,332-338` (valor por unidad = sueldo base / cuota; sobreproducción = unidades sobre cuota × valor) |
| 2 | Misma cuota, usada como indicador | `CompositeScoreService::quotaScore` (:86), `DashboardService.php:470,565,620` | % de cuota logrado (tope 100 %), promedio de 4 semanas | Informativo (escalafón / tablero) |
| 3 | Reglas semanales (mínimo, tope de bono, penalización) | Tabla `talento_weekly_rules`; `Models/TalentoWeeklyRule.php`; evaluación en `Services/TalentoWeeklyRuleEvaluationService.php` (:86-150) y resultados en `talento_weekly_rule_evaluations` | `metric_type` ∈ tecnico / mostrador / ventas / calidad_senal. Unidades medidas con `medirUnidades()` (:291): técnico = `LiquidationService::countBillableUnits`, mostrador = conteo de `Payment.add_by`, ventas = `CalculateBalanceSellerService`. Bajo `min_*` → `penalty_pending`; sobre `bonus_*_threshold` → `bonus` | **Sí**: la penalización genera registro pendiente; el bono se escribe al ledger (`aplicarBonoAlLedger`, :235) |
| 4 | Calidad de señal (dBm) como regla semanal | `metric_type=calidad_senal` (:171); reutiliza `min_sales`/`bonus_sales_threshold` con significado invertido (violaciones) | Cuenta OTs de la semana fuera del umbral (`TalentoHealthBonusLog`) | Sí (penalización / bono) |
| 5 | Resolución de qué regla aplica | `resolveRule()` (:324) | Match exacto del conjunto de roles; si no, el subconjunto con más roles; empate = id mayor | Determina cuál meta aplica |
| 6 | Mínimos de Vendedores `minimum_sales`, `number_of_prospects` | Tabla `commissions_rules`; `app/Models/CommissionRule.php:24,28`; UI `resources/js/components/module/vendors/commissions/AddRule.vue`; CRUD `Vendedores/Controllers/Vendors/Billing/CommissionRuleController.php:50-156` | `app/Pipes/FixedSalaryPayment.php:23-38` (sueldo fijo si cumple mínimos; atajo hardcodeado `ventas*20 + prospectos >= 120`), `app/Pipes/AdditionalSalesCommissionPayment.php:18-68` (ventas sobre el mínimo = adicionales), `app/Listeners/CalculateProspectCommission.php:50-55` (`number_of_prospects/4` por día) | **Sí** (sueldo fijo y comisiones de vendedor) |
| 7 | Rangos de ventas `RangeSale.number_of_prospects` | `app/Models/RangeSale.php`; `RangeSaleController.php:61` | Escalas por rango | Sí (comisión) |
| 8 | Escalafón (puntaje compuesto) | `Services/CompositeScoreService.php`; consumidores `TalentoEscalafonController`, `WarRoom/Controllers/DesempenoController.php` | Pesos por defecto (suma 100): cuota 35, calidad 25, bono de salud 15, normas 15, asistencia 10 (:19-25); estrellas por umbral 90/75/55/35/15 (:197) | **Informativo** (no escribe al ledger) |
| 9 | Exámenes | `ExamService::grade` (:18-40), `TalentoExam.passing_score` | `score >= passing_score` | Informativo (condición para certificar) |
| 10 | Certificaciones | `CertificationService::tryIssue` (:18-62), `TalentoCertification` | Examen aprobado + evaluación práctica `approved` | Informativo |
| 11 | Evaluaciones prácticas | `Models/TalentoPracticalEvaluation.php` | Resultado `approved` / otro, registrado por evaluador | Informativo |
| 12 | Historial de reglas | `TalentoCompensationRuleHistory` (usado por Liquidación, Escalafón, Tablero, Compensación) | Copia de la regla al asignarla | Sí: la liquidación lee la copia, no la regla viva |
| 13 | Campos de marco KPI (`variable_type`, `kpi_key`, `formula_config`, `monthly_cutoff_day`, `clawback_*`) | Migración `2026_08_27_200410_...`, fillable de `TalentoCompensationRule` | Sin consumidor de cálculo (grep: ningún servicio los lee) | **No** (scaffolding sin motor) |

## 2. Conteos [DEV]
`talento_compensation_rules` 5 (2 de ellas inactivas y con cuota 0, 2 más con cuota 0); `talento_weekly_rules` 7 (3 tecnico, 2 mostrador, 1 ventas, 1 calidad_senal, todas activas); `talento_compensation_rule_history` 7; `talento_weekly_rule_evaluations` 17; `commissions_rules` 8; `talento_exams` 0; `talento_certifications` 0; `talento_practical_evaluations` 0 (exámenes/certificaciones/prácticas sin uso en DEV).

## 3. Contradicciones y solapes señalados (sin resolver)
1. **Dos metas para el mismo concepto en técnicos**: cuota de pago (#1, `period=daily`, 36 → 216/semana) vs mínimo de regla semanal (#3, `min_sales`) — hoy coinciden para el rol TECNICO solo (216), pero son dos campos independientes que pueden divergir. Los roles combinados TECNICO+Vendedor (y Mostrador+TECNICO+Vendedor) tienen mínimo 36 y tope 45 en la regla semanal, sin una cuota de pago equivalente.
2. **Dos metas para vendedores**: `commissions_rules.minimum_sales`/`number_of_prospects` (#6, Vendedores) vs `talento_weekly_rules` metric `ventas` (#3, mín. 2 ventas/8 prospectos): cifras distintas en conceptos equivalentes, calculadas por mecanismos distintos (Pipes vs evaluación semanal).
3. **Dos fuentes de “ventas/prospectos”**: ambas pasan por `CalculateBalanceSellerService`, pero con ventanas distintas (la regla semanal usa día calendario, la liquidación de técnicos usa corte `PayWeek` 18:00).
4. **Constante mágica** `ventas*20 + prospectos >= 120` en `FixedSalaryPayment.php:35`, independiente de los campos configurables.
5. **Reutilización con significado invertido**: en `calidad_senal`, `min_sales`/`bonus_sales_threshold` significan violaciones, no ventas.
6. **Cuota ×6 solo en `cuotaSemanal`**: cualquier consumidor que lea `weekly_quota_units` directo ignoraría el factor diario (grep actual: los 6 consumidores usan `cuotaSemanal`; el controlador solo valida/guarda).
7. **Pesos del escalafón** fijos en código (no configurables salvo parámetro `weights`) y el componente de cuota depende del historial de reglas: sin historial, cuota = 0 → puntaje de cuota 0.
8. **Marco KPI (#13)** configurable en UI pero sin motor: no afecta pago aunque la regla exista.

Nada de lo anterior requiere decisión de código en este item; las decisiones económicas (cuál meta prevalece) quedan para Irving.

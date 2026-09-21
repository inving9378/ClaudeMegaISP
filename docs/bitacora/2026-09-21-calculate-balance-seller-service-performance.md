## 2026-09-21 — Rendimiento del motor de comisiones (CalculateBalanceSellerService)

**Pedido de Irving:** corregir el tiempo de cálculo del estado de cuenta de
comisiones (~40 segundos, documentado como hallazgo-sin-corregir al cerrar la
Fase D de Talento) antes de seguir con la Fase E.

**Investigación (sin tocar código hasta entender la causa real):** medí por
partes dónde se iba el tiempo de `statementAccount()` (motor compartido entre
Vendedores y Talento). El primer sospechoso obvio — `getRule()`/`getSales()`
repitiendo query por cada semana×tipo de comisión (hasta 480 veces) — SÍ era
un problema real pero NO el dominante: memoizarlos bajó el tiempo de ~41s a
~46s (ruido, sin mejora neta). El verdadero cuello de botella, encontrado con
`DB::enableQueryLog()` sobre una sola llamada `getSalaryFromRange()`: **97
queries en UNA llamada**, casi todas en cascadas de ~12 queries por cada
venta (`clients`, `client_main_information`, `client_additional_information`,
`billing_configurations`, `balances`, `client_bundle_services`,
`client_internet_services` ×2, `client_custom_services` ×2, `client_voz_services`,
`bundles`). Origen: `ClientMainInformation::getServiceAttribute()` (el
accessor `service`, en `$appends`) crea un `ClientRepository` y llama
`getCostAllService()` **sin memoria propia** — se recalcula desde cero cada
vez que se accede a `->service`, incluso sobre la MISMA instancia de modelo,
y el motor de comisiones lo toca 2 veces por venta por cada una de las ~480
iteraciones semana×tipo (una vez al filtrar en `getSales()`, otra al leer
`$s['service']` dentro del pipe `SalesCommissionPayment`).

**3 fixes de memoización, todos POR INSTANCIA/POR REQUEST (nunca estática ni
persistente entre requests — cada controller crea sus propios objetos, sin
riesgo de fuga entre usuarios ni de quedar desactualizado):**
1. `ClientMainInformation::getServiceAttribute()` — memoiza el resultado en
   dos propiedades privadas de la instancia (`$serviceAttributeCache`/
   `$serviceAttributeCached`), primer acceso calcula, el resto reutiliza. Es
   el fix de mayor impacto — y como `service` está en `$appends`, beneficia
   a **cualquier lugar del sistema** que serialice un `ClientMainInformation`
   más de una vez sobre la misma instancia, no solo a este cálculo.
2. `CalculateBalanceSellerService::getRule()`/`getSales()` — traen el
   histórico completo del vendedor UNA vez (por `seller_id`/`user_id`) y
   filtran/buscan en memoria para cada rango, en vez de repetir la consulta
   por cada semana o mes.
3. `Seller::hasPaymentByRuleInPeriod()`/`hasNotBeenPaid()` — mismo patrón:
   trae los `PaymentByRuleDetails` del vendedor una vez, filtra en memoria.

**Bug real encontrado de paso (no de rendimiento — de corrección) y
corregido:** `ClientMainInformation::getPaymentsInThreeFirstFiveMonths()`
(línea ~365) hacía `$p->user->name` y `$p->payment_method->type` sin
null-safe — para pagos históricos con `add_by` apuntando a un usuario borrado
o inexistente, esto es solo un WARNING en tinker (silencioso, con
degradación), pero **el manejador de errores real de Laravel sobre HTTP lo
convierte en excepción fatal (500)** — confirmado: la llamada directa por
HTTP a `estado-cuenta` daba 500 real hasta corregirlo. Cambiado a `?->` en
ambos accesos (mismo patrón `?->` ya usado en el resto del código de este
sesión). Esto invalida mi propia nota anterior en la bitácora de la Fase D,
que había catalogado esto como "solo un warning, no bloqueante" — era
incorrecta para el camino HTTP real; queda corregida aquí.

**Verificación de correctitud (antes de medir velocidad):** capturé
`statementAccount()`/`pendingPaymentsBySeller()` para 3 vendedores reales
distintos (Diana/67 pagos, otro con 3 pagos, otro con 1 pago) **antes** de
tocar código, apliqué los fixes, y comparé de nuevo. Resultado: **números
idénticos en los 5 campos** para los 3 vendedores; el payload completo de
`pendingPaymentsBySeller` (36KB, todas las comisiones por tipo y periodo)
**idéntico byte a byte** (ignorando el UUID aleatorio de cada fila) antes y
después. `registerPayment()`/`collectDebt()` (Fase D) re-probados en
transacción con rollback tras el cambio — mismos resultados.

**Resultado de velocidad (mismo vendedor, mismos datos reales):**

| Vendedor | Antes | Después | Mejora |
|---|---|---|---|
| Diana (67 pagos, histórico completo) | 41.2s | 10.5s | 74% |
| Vendedor con 3 pagos | 10.6s | 3.1s | 71% |
| Vendedor con 1 pago | 1.9s | 1.0s | ~lo mismo (ya era rápido) |
| `pendingPaymentsBySeller` (Diana) | 30.5s | 4.8s | 84% |

Verificado también por HTTP real (antes daba 500 por el bug de `?->`, ahora
200 en ~10.5s directo / ~17s incluyendo el resto del round-trip del
navegador con Debugbar activo — en producción, sin Debugbar, será más rápido
aún).

**Alcance de lo NO tocado:** no se cambió ningún cálculo, regla de negocio,
condición de pago ni estructura de datos — solo se evitó repetir trabajo ya
hecho dentro de la misma petición. `getProspects()` y el resto de los pipes
(`FixedSalaryPayment`, `AdditionalSalesCommissionPayment`,
`DistributorsCommissionPayment`, `DiscountPayment`, `MonthlyBonusPayment`) se
dejaron intactos — no eran el cuello de botella real.

Rama `perf/calculate-balance-seller-service-cache`, mergeada a `main`. Sin
migraciones (solo cambios de código, sin tocar schema).

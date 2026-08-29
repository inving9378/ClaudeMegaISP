# Item #632 — Plan de ejecución para unificar `invoices`/`client_invoices`

> Continuación de `docs/deuda-facturas-invoices-item-159-plan.md`. Este documento es el
> entregable de la Opción elegida por Irving para la pregunta q1 del item #632
> ("Investigación previa SIN tocar código — mapear diferencias de esquema, volúmenes,
> dependencias y entregar reporte con propuesta de estrategia de unificación"). **No se tocó
> ninguna tabla, modelo, controller ni flujo de cobro para producir este documento** — todo lo
> de abajo es lectura (`grep`, `SHOW COLUMNS`, `SELECT`) contra el código y la BD de dev.

## 0. Decisiones que Irving YA tomó (log del item #632, 2026-08-28 18:28)

Irving aprobó el item respondiendo sus 3 preguntas. Quedan registradas aquí para que ningún
sub-item futuro las vuelva a preguntar:

| Pregunta | Respuesta elegida |
|---|---|
| **q1** — ¿Cómo abordar la unificación? | **Investigación previa SIN tocar código** (este documento). |
| **q2** — ¿Cuál es la tabla canónica destino? | **`invoices` como canónica** (`client_invoices` se absorbe). |
| **q3** — ¿Modo de switch en producción? | **Switch gradual detrás de feature flag** (leer de la nueva, escribir en ambas, luego solo en la nueva). Sin ventana de mantenimiento. |

Esto fija el rumbo (Opción A del doc #159 + mecanismo de flag), pero **cada fase de ejecución
que toque escritura de dinero sigue siendo nivel C** — Irving decidió la estrategia, no dio luz
verde a ejecutar el corte completo sin supervisión por fase.

## 1. Confirmación de volúmenes y esquema (sin cambios desde #159, 2026-08-26 → 2026-08-28)

| | `client_invoices` (LEGACY) | `invoices` (MODERNA) |
|---|---|---|
| Filas en dev (hoy) | **110,860** (igual) | **93,203** (igual) |
| `payment_date`/`document_date` | `varchar(255)`, formato `d/m/Y` (ej. `"12/10/2023"`), **confirmado con `SHOW COLUMNS` + muestreo real** | `date` real |
| `estado`/`status` | `varchar(255)` libre (`Pagado`, `Atrasado`, `Partially paid`, `impagado`, `Pagar (del saldo de la cuenta)`, …) | `enum` cerrado |
| Relación a servicios | `morphedByMany` a `ClientBundleService`/`ClientInternetService`/`ClientVozService`/`ClientCustomService` | **Ninguna** — confirmado leyendo `app/Models/Invoice.php` (solo `client()`, `items()`, `transaction()`, `payment()`) |

Nada de esto cambió desde #159 — la deuda sigue intacta y con el mismo tamaño.

## 2. Hallazgos NUEVOS de esta auditoría (no estaban en el doc de #159)

### 2.1 — El propio panel de auto-diagnóstico de la Torre YA detecta esta divergencia en vivo

`app/Modules/Core/Release/Controllers/AuditController.php:49-79` (endpoint `generate()`, el
"reporte en vivo" de `/releases`) trae una métrica **`kpi_warroom`** que compara
`invoices.status='paid'` contra `client_invoices.estado='Pagado'` del mes en curso y se marca
`estado='error'` si difieren — es decir, el sistema ya trae instrumentación que corrobora
exactamente el riesgo que describe el item #159/#632: las dos tablas pueden contar historias
distintas del mismo mes.

Además, `seedDefaultPlan()` (mismo archivo, línea ~275) trae un ítem histórico de plan
("Fix KpiController → usar tabla invoices... Ingresos reales mayo 2026: \$767,558") que ya
había señalado este problema como `prioridad: urgente` en un ciclo anterior. **Ese fix nunca se
aplicó** (ver 2.2) — es contenido de seed, no un registro de que se haya ejecutado.

### 2.2 — Bug real y ACTIVO, independiente de la unificación: War Room filtra por mes sobre un VARCHAR

`app/Modules/Addons/WarRoom/Controllers/KpiController.php` sigue leyendo **solo** de
`client_invoices` (confirmado, ni un solo `Invoice::`/`DB::table('invoices')` en el archivo) y
en varias métricas (línea ~60, ~65, ~247) hace `whereYear('payment_date', …)->whereMonth('payment_date', …)`
sobre una columna que es `varchar(255)` con formato `d/m/Y` (`"12/10/2023"`), **no** `Y-m-d`.
`YEAR()`/`MONTH()` de MySQL sobre un string en ese formato no lo interpreta como fecha válida →
esas métricas del mes en curso muy probablemente están devolviendo `0`/`NULL` en vivo hoy mismo,
con independencia de si algún día se unifican las tablas.

**Esto es un bug de REPORTE (solo lectura), no de dinero en movimiento** — no mueve saldo ni
aplica pagos, solo muestra mal una cifra ya existente. No lo corregí en esta vuelta porque el
alcance aprobado de #632 es "sin tocar código" (q1), pero lo registro como sub-item de nivel A
aparte (ver §5) para que no se pierda: es accionable HOY, no requiere que la unificación grande
avance primero.

### 2.3 — Colisión de nombres: dos clases `InvoiceService` completamente distintas

- `App\Services\InvoiceService` (`app/Services/InvoiceService.php`) — opera sobre **`ClientInvoice`** (legacy). La usan `ClientService.php` y `ProcessCreateServiceJob.php` para generar la factura inicial al dar de alta un servicio.
- `App\Services\Finance\Invoice\InvoiceService` (`app/Services/Finance/Invoice/InvoiceService.php`) — opera sobre **`Invoice`** (moderna). La usa el cron de proformas y Finanzas.

Mismo nombre de clase, comportamiento y tabla completamente distintos, solo diferenciados por
namespace. Cualquier `grep -r "InvoiceService"` sin filtrar por `use` trae ambas mezcladas — un
riesgo concreto de que una fase de migración futura (humana o de IA) edite la que no es. Vale la
pena, cuando se ejecute la Fase de escritores (§4, Fase 6), renombrar una de las dos a algo sin
ambigüedad (ej. `LegacyClientInvoiceService`) como parte de esa fase — no antes, para no generar
un diff sin relación con la unificación misma.

### 2.4 — Consumidores adicionales no listados en el doc de #159

- `app/Modules/Core/Release/Controllers/AuditController.php` — lee ambas tablas (auto-diagnóstico, §2.1).
- `app/Modules/Addons/PortalCliente/Console/AntiIdorTestCommand.php` — usa `client_invoices` en el test de aislamiento multi-tenant del portal.
- `app/Modules/Addons/MegaFamilia/Controllers/ApiController.php` — lee `invoices` (solo lectura, `max('updated_at')`/consulta por `client_id`) para una pantalla de MegaFamilia; consumidor menor, sin relación con facturación pero sensible a que cambie el significado de la tabla.

### 2.5 — Los 5 puntos EXACTOS de escritura viva de `client_invoices.estado` (el blast radius real)

Grep dirigido a mutaciones de `estado` (no solo lecturas) — estos son los únicos 5 lugares del
sistema que hoy marcan una factura legacy como pagada, y por lo tanto los únicos que una fase de
dual-write (Fase 2, §4) tendría que tocar:

1. `app/Modules/Addons/Payments/Services/PaymentApplicationService.php:122` — `applyPayment()`, el motor central de pagos (mostrador Paso 2/2b/2c, WhatsApp F4, webhook SPEI/OpenPay lo reusan).
2. `app/Modules/Addons/Domiciliacion/Commands/DomiciliacionCobrarCommand.php:246` — cobro automático por domiciliación.
3. `app/Modules/Addons/PortalCliente/Controllers/OpenpayWebhookController.php:151` — webhook de tarjeta OpenPay del Portal Cliente.
4. `app/Modules/Addons/PortalCliente/Controllers/PortalPagoController.php:240` — confirmación de pago SPEI del Portal Cliente.
5. `app/Modules/Addons/PortalPago/Services/ConciliacionService.php:61` — conciliación manual de SPEI del lado staff (PortalPago admin).

Confirma lo que ya advertía #159 en la sección 3 (el matcher abandonado): **cualquier fase de
escritura toca simultáneamente 5 subsistemas independientes** (mostrador, domiciliación,
OpenPay, SPEI-cliente, SPEI-staff). Ninguno de los 5 se tocó en esta auditoría.

## 3. Qué NO cambió respecto al plan de #159

- El intento previo de puente (`PaymentApplicationService::matchPendingInvoice()`, línea 166-187)
  sigue **desconectado**, exactamente como lo dejó el ciclo #191/#193. No se reconectó ni se
  tocó en esta auditoría.
- Sigue sin existir ningún job/observer que sincronice una tabla con la otra.

## 4. Plan de ejecución por fases (dado que Irving ya fijó destino=`invoices` + mecanismo=feature flag)

Cada fase está dimensionada para caber en una sola vuelta de un ejecutor. Las fases de sólo
lectura pueden bajar de nivel C a B; las que tocan escritura de dinero **se quedan en C** y
requieren su propio brief/aprobación aunque la dirección general ya esté decidida — el "qué"
está decidido, el "cómo exacto de cada diff" no.

| Fase | Contenido | Nivel sugerido | Toca escritura de dinero |
|---|---|---|---|
| **0** (este item) | Auditoría read-only + este documento | — (ya ejecutada) | No |
| **1** | Migración aditiva de esquema en `invoices`: agregar lo que le falta para representar lo que hoy solo vive en `client_invoices` (relación morph a servicios vía tabla pivote nueva, o reuso de `client_serviceable` apuntando también a `invoices`). Sin togglear nada, sin leer/escribir en ningún flujo vivo. | B (aditivo puro, reversible con `down()`) | No |
| **2** | Feature flag `config('billing.dual_write_invoices')` (default OFF) + capa de dual-write en los **5 puntos de escritura** de §2.5: cuando se marca `client_invoices.estado='Pagado'`, si el flag está ON también refleja el estado en la fila `invoices` correspondiente (crearla si no existe). `client_invoices` sigue siendo la fuente de verdad; `invoices` es espejo. | **C** (toca los 5 subsistemas de cobro, aunque en modo espejo) | Sí (efecto colateral de escritura, aunque no cambia el resultado del cobro) |
| **3** | Backfill histórico: script idempotente y chunked que recorre las 110,860 filas de `client_invoices` y crea/actualiza su espejo en `invoices` (sin flag — corre una vez, offline). | B (solo construye espejo, no altera `client_invoices`) | No |
| **4** | Migrar lectores de bajo riesgo a leer de `invoices` en vez de `client_invoices`: `KpiController` (War Room), `HomeController` (Dashboard), `AuditController` (Torre). Validar que los números coincidan con el espejo de la Fase 2/3 antes de fijar. Incluye de paso el fix del bug de §2.2 (ya no aplica si el lector se movió a `invoices`, que tiene `date` real). | B | No |
| **5** | Ventana de monitoreo en producción con el flag de la Fase 2 en ON: comparar `invoices` vs `client_invoices` durante N días, medir divergencias reales antes de tocar ningún flujo de escritura primario. | B (solo observación) | No |
| **6** | Cortar los 5 puntos de escritura (§2.5) para que `invoices` sea la escritura PRIMARIA y `client_invoices` reciba el espejo (se invierte la dirección de la Fase 2). Incluye resolver la colisión de nombres de §2.3. | **C** — requiere su propio brief por subsistema (son 5 flujos de dinero real independientes; probablemente 5 sub-items, no uno) | Sí, directo |
| **7** | Decommission: `client_invoices` pasa a solo-lectura/archivo histórico; eventualmente se deja de escribir. | **C** (irreversible en la práctica una vez que nadie más escribe ahí) | Sí (indirecto) |

**Este item (#632) entrega hasta aquí (Fase 0).** Las fases 1-7 se registran como sub-items
independientes (ver `circuito:sub-item`) para que cada una se trie y ejecute por separado, con
Irving ya habiendo fijado el destino y el mecanismo — así ningún sub-item futuro tiene que
re-preguntar q2/q3, solo el detalle técnico de su propia fase.

## 5. Recomendación inmediata fuera de la unificación grande

El hallazgo de §2.2 (KPI del mes en curso probablemente en `0` en War Room por comparar
`YEAR()`/`MONTH()` contra un `varchar` `d/m/Y`) es un bug de **solo lectura**, aislado a
`KpiController`, que no depende de que avance la unificación — se puede arreglar parseando la
fecha en PHP antes de filtrar (o con `STR_TO_DATE(payment_date, '%d/%m/%Y')` en el query) sin
tocar ningún flujo de cobro. Se registra como sub-item de nivel A aparte.

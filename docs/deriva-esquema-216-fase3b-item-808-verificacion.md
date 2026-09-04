# Deriva #216 Fase 3b (item #808) — consumidores + desde-cuándo de las diferencias "falta_en=megaisp" + 3 anomalías de migración

**Item:** #808, sub-item de #740 (paraguas Fase 3). **Alcance:** solo lectura — audita, no migra ni borra nada.

## Resultado

`storage/app/circuito/diff-esquema-216-fase3b.json` (artefacto de runtime, gitignored como el resto de
`storage/app/circuito/*`) con **18 objetos** `{tabla, elemento, tipo, falta_en, consumidores[], desde_cuando, notas}`:
15 índices/FK con `falta_en=megaisp` (declarados en la migración vigente, ausentes en la BD viva de dev) +
3 anomalías de migración documentadas aparte.

A diferencia de la Fase 3a (#807), que encontró drift entre el corte del item original y el archivo vigente,
este subconjunto **coincide exactamente**: el item citaba 15 registros `falta_en=megaisp` y el archivo vigente
tiene exactamente 15 con `lado_faltante=megaisp` (13 índice + 2 FK). Sin drift de alcance en esta fase.

## Los 15 índices/FK — patrón dominante

13 de los 15 (todos salvo las 2 FK de `referral_prospects`/`referrals` comentadas abajo) comparten la misma
firma: la migración que crea la tabla (`api_integrations`, `marketing_generated_content`, `marketing_messages`)
declara un `->index()` **suelto** sobre una columna, ese índice standalone falta en runtime, pero el índice
**compuesto** de la misma migración sobre esa misma columna (ej. `UNIQUE(company_id, slug)`,
`(company_id, status)`, `(conversation_id, sent_at)`) **sí existe**. Verificado en vivo con `SHOW INDEX` para
`api_integrations.provider` (0 filas) y confirmado con la tabla `migrations` real de esta BD. Ninguna migración
posterior hace `dropIndex` sobre estas columnas (grep completo en `database/migrations/` +
`app/Modules/*/migrations/`). 13 de los 15 tienen consumidor real en código.

**Hallazgo más relevante del lote:** `referrals_referred_client_id_unique` — el `UNIQUE` que impide que un
mismo cliente aparezca dos veces como referido **no está enforced por la BD**. Verificado en vivo
(`SHOW INDEX FROM referrals WHERE Column_name='referred_client_id'`): el único índice existente es
`referrals_referred_client_id_foreign` (`Non_unique=1`, autogenerado por el FK, NO es el UNIQUE de la
migración). Hoy la garantía depende 100% de `ClientObserver`, sin candado de motor. 0 duplicados verificados
en las 101 filas actuales (sin corrupción hoy), pero nada impide que una carrera o un import futuro la
introduzca. Candidato prioritario para una futura migración de reconciliación (fuera de alcance de este item).

Las 2 FK ausentes (`referral_prospects.converted_client_id`, `referrals.prospect_id`) comparten un patrón
propio: ambas usan `onDelete('set null')`, mientras las FK hermanas de las mismas tablas que sí sobreviven en
vivo (`embajador_id`, `referred_client_id`) usan `onDelete('cascade')`. Causa probable: pérdida durante una
reconstrucción/import de esquema (ver CLAUDE.md "Importación de BD de producción", que ya documenta errores
de FK/PK en tablas relacionadas a `clients` durante imports), no un cambio de código.

## Las 3 anomalías de migración

1. **`jobs`** — Laravel ya trae `2021_11_01_113323_create_jobs_table` (batch 1). Existen además 2 migraciones
   custom redundantes registradas como `Ran`: `2026_05_26_143203_create_jobs_table` (batch 363, **con** guard
   `Schema::hasTable`, corrió como no-op) y `2026_05_27_140742_create_jobs_table` (batch 378, **sin** guard,
   mismo commit que la Fase 3 del Agente IA de Marketing). Verificado en la tabla `migrations` real: los 3
   registros conviven (batches 1/363/378).
2. **`failed_jobs`** — mismo patrón: Laravel trae `2019_08_19_000000_create_failed_jobs_table` (batch 1) y
   existe `2026_05_27_140743_create_failed_jobs_table` (batch 379, sin guard, mismo commit que la anomalía de
   `jobs`, un batch después).
   - **Riesgo real de ambas:** en un entorno **nuevo** (prod, clon fresco) donde `migrate` corre todo en una
     sola pasada, la migración sin guard intentaría `Schema::create` sobre una tabla que el paso anterior de
     la misma corrida ya creó → `SQLSTATE... table already exists` → frenaría el deploy en el paso `migrate`
     (fatal-sin-rollback, ver "Deploy remoto" en CLAUDE.md). En la BD viva de dev no truena porque ya está
     registrada como `Ran` (no se re-ejecuta), pero el archivo sigue siendo una bomba para cualquier `migrate`
     desde cero. No se corrige aquí (solo lectura); la corrección natural es un guard `hasTable()` o borrar el
     archivo redundante — decisión de Irving, fuera de alcance.
3. **`invoices_client_invoice_id_index`** — migración fantasma de tercer caso (mismo patrón que CLAUDE.md
   #534, `add_reporte_dual`/`add_decision_metadata`, previo al guardrail `MigrationGuardService`).
   `database/migrations/2026_08_28_970000_add_review_flags_and_unique_client_invoice_id_to_invoices_table.php`
   (commit `bdcb8f7e`, circuito#748) hace `dropIndex(['client_invoice_id'])` asumiendo un índice simple creado
   por `2026_08_28_192630_add_client_invoice_id_to_invoices_table.php` — ese archivo está `Ran` en la tabla
   `migrations` de esta BD (batch 576) pero **no existe en git ni en disco**. En dev no truena porque el
   índice fantasma ya estaba puesto; en cualquier entorno donde `192630` nunca corrió (clon fresco de main,
   prod), la columna `client_invoice_id` ni siquiera existiría → `970000` fallaría antes de llegar al
   `dropIndex`. Coincide exactamente con la descripción original del item #216 ("el ALTER... que falla por
   drop de índice inexistente"). No se corrige aquí; la resolución natural es crear `client_invoice_id` + su
   índice simple antes de `970000`, o fusionar ambas migraciones — decisión de Irving/#748.

## Metodología

Por cada uno de los 18: `grep -rn` del nombre de tabla/columna/índice en `app/ resources/ routes/ config/`
(incluyendo migraciones de módulos addon) para consumidores; `git log` sobre el archivo de migración para el
"desde cuándo"; y verificación directa en la BD viva (`SHOW INDEX`, `information_schema.KEY_COLUMN_USAGE`,
tabla `migrations`) para confirmar que la ausencia es real y no un artefacto del comparador. Los hallazgos de
`api_integrations.provider`, `referrals.referred_client_id` y las 3 filas de `migrations` (jobs/failed_jobs)
se re-verificaron en vivo en esta misma sesión de cierre.

## Sin cambio de código

Este item es de auditoría de solo lectura. No se escribió ninguna migración de reconciliación (fuera de
alcance explícito). El artefacto JSON completo queda en `storage/app/circuito/diff-esquema-216-fase3b.json`
para que el propio #740 lo consuma al producir el entregable final de #216. Decisión reportada también en el
log de #740 (`circuito:reportar 740 --tipo=decision`), ya que #740 es el item que eventualmente cierra #216 —
este sub-item no cierra el paraguas.

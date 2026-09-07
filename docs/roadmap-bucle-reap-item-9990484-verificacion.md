# Item #9990484 — bucle reap sobre paraguas ya descompuesto (Barrido seller_id ↔ user_id en Vendedores/Talento)

## Contexto

`#9990484` ("Barrido: confusión seller_id ↔ user_id en Vendedores y sus vistas en Talento") pedía
auditar y corregir todo el patrón sistémico donde columnas "de vendedor" (`client_main_information.
seller_id`, `crm_lead_information.owner_id`) guardan `users.id` pero el código las consulta con
`sellers.id`. El propio log del item muestra que una vuelta previa (`wt-2`, 2026-09-07 08:49) ya
hizo lo correcto:

1. Corrió `circuito:cabida` → **NO CABE** (max_turns previo sin commits en la rama).
2. Investigó (grep + lectura, sin editar código) y descompuso el barrido en 4 sub-items, cada uno
   con su propia spec exacta:
   - **#9990485** — Vendors/Billing: `PaymentClientController` + `TransactionController`×2 +
     `InstallationController`, verificar id vs `CMI.seller_id`=`users.id`.
   - **#9990486** — `SaleController` (Vendors/Sales) endpoints con `{id}` y sus vistas.
   - **#9990487** — verificar empíricamente `transactions_sellers.seller_id`/
     `payments_sellers.seller_id` (¿`sellers.id` o `users.id`?) y auditar
     `SellerTransactionController`+`PaymentSellerController`+`ProspectController` en consecuencia.
   - **#9990488** — verificación end-to-end con Guadalupe + 2° vendedor + tabla de referencia final
     (PASO 4 del item padre).
3. Dejó la nota de decisión en `comentarios_claude` a las `08:49`.

Pero el proceso **murió antes de intentar el cierre** del padre: el log registra a las `09:16:04`
el evento `huerfano_reencolado` (`reaper`, tope 3) — "worker murió/timeout con el item en
en_progreso (hace 26 minutos)" — el claim se liberó y el item volvió a `aprobado_revisor` sin que
nadie hubiera intentado cerrarlo. El pool lo repartió de nuevo (a `wt-4`) sin que hubiera trabajo
propio que hacer: exactamente la misma familia de bug ya documentada en
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#852`/`#905`/`#878`/`#906`/`#907`/`#924`/`#9990012`/
`#917`/`#910`/`#936`/`#9990422`/`#9990412` (y otros).

## Verificación

Consultados los 4 hijos (`origen_item_id=9990484`) directo en BD:

| Item | Título | Estado | Worker |
|------|--------|--------|--------|
| #9990485 | Vendors/Billing (PaymentClientController+TransactionController×2+InstallationController) | `completado` | wt-3 |
| #9990486 | SaleController (Vendors/Sales) endpoints con `{id}` y sus vistas | `completado` | wt-3 |
| #9990487 | transactions_sellers/payments_sellers + SellerTransactionController/PaymentSellerController/ProspectController | `en_progreso` | wt-6 |
| #9990488 | Verificación end-to-end Guadalupe + 2° vendedor + tabla de referencia final | `aprobado_revisor` | (sin reclamar) |

Dos de los cuatro ya cerraron; los otros dos siguen abiertos (uno en curso activo bajo otra
terminal, otro pendiente de tomarse) — la descomposición original sigue siendo correcta y
completa, nadie más la tocó ni hace falta re-descomponer nada.

## Corrección

Esta vuelta ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'` vía
tinker) sobre `#9990484`. El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b)
PARAGUAS") lo reenrutó a `aprobado_irving` + `excluir_pool_automatico=true`, liberando
`worker_sid`/`claimed_at` (evento `paraguas_abierto` en el log: "le quedan 2 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491` aprox.) lo complete solo cuando `#9990487` y `#9990488` cierren los
dos.

## Resultado

Sin cambio de código de negocio. El trabajo técnico real del barrido (verificar
`transactions_sellers`/`payments_sellers` + auditar los 3 controllers restantes, y la
verificación end-to-end con Guadalupe + 2° vendedor) sigue en `#9990487` (en curso, `wt-6`) y
`#9990488` (`pendiente_revision`, sin reclamar).

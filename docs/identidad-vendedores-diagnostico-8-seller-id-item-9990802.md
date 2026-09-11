# Item #9990802 — Diagnóstico de identidad `seller_id`: listado detallado de los sellers problemáticos (SOLO LECTURA)

Ejecuta la **Opción 1** ya aprobada por Irving sobre el item (`opcion_elegida`, 2026-09-11): *"Solo
generar reporte read-only (listado de los seller_id problemáticos con detalle: nombre, cuentas
asociadas, comisiones pendientes, últimas ventas) y adjuntarlo para que Irving decida el
tratamiento caso por caso"*. Este reporte es **100% solo-lectura** — ninguna consulta de las de
abajo escribe en BD, no hay migraciones ni cambio de código fuera de `docs/`.

Fuente: item #9990801 (`docs/identidad-vendedores-inventario-seller-id-item-9990801.md`), §5.1 y
§5.2. Ese inventario listó dos categorías de `sellers` con inconsistencia de identidad:

- **§5.1** — 6 `sellers` activos cuyo `users.is_seller = 0` pese a tener fila `sellers` viva.
- **§5.2** — 6 `sellers` activos **sin** contraparte en `talento_colaboradores` (vía el puente
  `sellers.user_id == talento_colaboradores.user_id`).

⚠️ **Nota de conteo:** la pregunta escalada del item habla de "8 seller_id problemáticos", pero la
unión real de §5.1 ∪ §5.2 son **10 seller_id distintos** — 2 de ellos (`23` y `25`, las cuentas
espejo) aparecen en **ambas** listas simultáneamente, así que "2 espejo + 6 sin colaborador" no es
una suma aritmética limpia (el 6 de "sin colaborador" ya incluye a los 2 espejo). Este reporte
cubre los **10 distintos** para que Irving tenga el cuadro completo, agrupados en 3 bloques según
cuál(es) problema(s) tiene cada uno.

## Metodología

Para cada `seller_id` se consultó (todo vía `DB::table(...)->count()`/`->first()`, sin escritura):

- `sellers` (`balance`, `status_id`) + `users` (`login_user`, `name`, `is_seller`, `estado`).
- **Comisiones/actividad del motor legado**: `commissions_rules_sellers`, `history_sellers_rules`,
  `payment_by_rule`, `transactions_sellers`, `sales`, `commissions` (conteo de filas + fecha más
  reciente donde aplica).
- **Cuentas asociadas**: `client_main_information` y `whatsapp_conversations` filtrados por
  `seller_id = sellers.user_id` — recordando el hallazgo de #9990801 §3.1/§3.2: en esas dos
  tablas la columna `seller_id` es en realidad `users.id`, no `sellers.id`, así que la cartera de
  clientes de cada vendedor se resuelve por su `user_id`, no por el `seller_id` numérico de la
  tabla `sellers`.

## Bloque A — 2 sellers de cuentas espejo (aparecen en AMBAS listas: `is_seller=0` Y sin colaborador Talento)

| seller_id | user_id | login_user | Nombre | `is_seller` | estado user | balance | cartera (clientes) | comisiones/pagos/reglas | última actividad |
|---|---|---|---|---|---|---|---|---|---|
| **25** | 3986 | `Meganet8f3d7255` | JUAN | 0 | activo | 0.00 | 0 | 0 reglas / 0 pagos / 0 transacciones | — sin registro |
| **23** | 3988 | `Meganetdf642fa2` | MELANEE GALILEA | 0 | activo | 0.00 | 0 | 0 reglas / 0 pagos / 0 transacciones | — sin registro |

Ambos: `sellers.status_id = NULL` (ni siquiera tienen un estado de vendedor asignado), cero
cartera de clientes, cero comisiones, cero pagos, cero transacciones. Son las mismas 2 cuentas
espejo del patrón `^Meganet[0-9a-f]{8}$` (Fase 2.5, ya saneadas de roles/permisos de staff, pero
`sellers` nunca fue tocada por esa limpieza). **Inertes en la práctica** — no hay nada que se
"pierda" si se dan de baja, no hay comisión ni cliente que reasignar.

## Bloque B — 4 sellers reales con `is_seller = 0` (NO son cuentas espejo)

| seller_id | user_id | login_user | Nombre | estado user | balance | cartera (clientes) | comisiones/pagos/reglas | última actividad |
|---|---|---|---|---|---|---|---|---|
| 12 | 9 | GUADALUPE | GUADALUPE | activo | 0.00 | 474 | 1 regla / 0 pagos / 4 asignaciones de regla (`history_sellers_rules`) | 2026-01-14 |
| 16 | 3695 | Suriromero | Surisadai Marcos | **inactivo** | 0.00 | 22 | 1 regla / 0 pagos / 1 asignación de regla | 2025-05-20 |
| 24 | 4032 | sergio | SERGIO | **inactivo** | 0.00 | 13 | 1 regla / **1 pago ($300.00, 2025-01-11)** / 4 asignaciones de regla | 2026-01-14 (regla) / 2025-01-11 (pago) |
| 47 | 4668 | fernanda | MARIA FERNANDA | **inactivo** | 0.00 | 1 | 0 reglas / 0 pagos / 1 asignación de regla | 2026-02-19 |

Estos 4 sí tienen cartera de clientes real (hasta 474 en el caso de GUADALUPE) y 2 de ellos
(`sergio`, `24`) tienen historial de pago real ($300.00 registrado el 2025-01-11). `balance` de
`sellers` está en `0.00` para los 4 — **no hay comisión pendiente de pago visible en ese campo**
para ninguno hoy (la tabla `commissions`, que sería la fuente autoritativa de comisión adeudada,
está en 0 filas globalmente — ver `docs/vendedores-sales-commissions-prospects-item-9990471-verificacion.md`,
hallazgo ya cerrado aparte: comisión huérfana en `commissions_details`, no en `commissions`).
`GUADALUPE` (12) ya fue investigada aparte como persona real distinta de otra "Guadalupe"
(`docs/vendedores-guadalupe-duplicado-item-9990472-verificacion.md`).

## Bloque C — 4 sellers reales sin contraparte en `talento_colaboradores` (NO son cuentas espejo, `is_seller=1`)

| seller_id | user_id | login_user | Nombre | estado user | balance | cartera (clientes) | comisiones/pagos/reglas | última actividad |
|---|---|---|---|---|---|---|---|---|
| 5 | 4 | kathya | KATHYA | activo | 0.00 | 113 | 0 reglas / 0 pagos / 0 transacciones | — sin registro |
| 6 | 6 | TERE | MARIA TERESA | activo | 0.00 | 75 | 0 reglas / 0 pagos / 0 transacciones | — sin registro |
| 28 | 8 | Irving | IRVING | activo | 0.00 | **2127** | 0 reglas / 0 pagos / 0 transacciones | — sin registro |
| 26 | 4070 | kevin | Kevin Casimiro | activo | 0.00 | 0 | 0 reglas / 0 pagos / 0 transacciones | — sin registro |

`is_seller = 1` para los 4 (correctamente marcados como vendedores), pero sin fila espejo en
Talento — son personal que opera el sistema (Irving = dueño, TERE = mostrador, documentada
extensamente en este repo) o vendedores sin alta de colaborador de campo. `Irving` (28) carga la
cartera "sin vendedor asignado" (2127 clientes, ya documentado en #9990801 §3.1 — es el
`seller_id=8` que en `client_main_information` en realidad significa `users.id=8`, no
`sellers.id=8`). Cero actividad de comisión para los 4.

## `sales` / `commissions` (motor legado) — vacías para los 10, sin excepción

Las 10 filas de `sellers` de este reporte tienen **0** en `sales` y **0** en `commissions` — ya
documentado como hallazgo global cerrado (`sales` descontinuada, `commissions` con 566K filas de
detalle huérfanas por truncado parcial de 2024-07-12, ver
`docs/vendedores-sales-commissions-prospects-item-9990471-verificacion.md`). No es un hallazgo
nuevo de este item, se confirma aquí caso por caso para que "últimas ventas" no quede como campo
vacío sin explicación.

## Resumen ejecutivo para decidir caso por caso

| seller_id | Problema(s) | ¿Tiene cartera/pagos que perder? | Riesgo de dar de baja |
|---|---|---|---|
| 25 (JUAN, espejo) | is_seller=0 + sin colaborador | No (0 clientes, 0 pagos) | Bajo — inerte |
| 23 (MELANEE, espejo) | is_seller=0 + sin colaborador | No (0 clientes, 0 pagos) | Bajo — inerte |
| 12 (GUADALUPE) | is_seller=0 | Sí — 474 clientes, historial de reglas | Medio — corregir flag, no dar de baja |
| 16 (Suriromero) | is_seller=0, usuario inactivo | Sí — 22 clientes | Medio — usuario ya inactivo, evaluar archivar |
| 24 (sergio) | is_seller=0, usuario inactivo | Sí — 13 clientes + 1 pago real ($300) | Medio — historial de pago real, no tocar sin revisar |
| 47 (fernanda) | is_seller=0, usuario inactivo | Sí — 1 cliente | Bajo-medio |
| 5 (kathya) | sin colaborador Talento | Sí — 113 clientes | Bajo — es_seller correcto, solo falta alta en Talento |
| 6 (TERE) | sin colaborador Talento | Sí — 75 clientes | Bajo — personal de mostrador conocido |
| 28 (Irving) | sin colaborador Talento | Sí — 2127 clientes (cartera "sin asignar") | Bajo — es el dueño, caso esperado |
| 26 (kevin) | sin colaborador Talento | No — 0 clientes | Bajo — sin actividad, evaluar si sigue activo |

## Qué sigue

Este reporte no decide nada — es el insumo pedido por Irving (Opción 1) para que la Hoja de Ruta
registre, si él lo confirma, items nivel B específicos por caso (ej. "dar de baja sellers 23/25",
"corregir `is_seller` de 12/16/24/47", "dar de alta en Talento a 5/6/26") cuando retome #9990778.
Las 3 preguntas abiertas de #9990801 §7 siguen sin resolver — este documento solo les añade el
detalle cuantitativo que faltaba.

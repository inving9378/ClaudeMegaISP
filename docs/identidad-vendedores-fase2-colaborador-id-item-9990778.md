# Item #9990778 — Identidad unificada, Fase 2 (parcial): `colaborador_id` en `client_main_information` y `whatsapp_conversations`

Continuación de la Fase 1 (#9990801, `docs/identidad-vendedores-inventario-seller-id-item-9990801.md`).
Este item cubre las Fases 2–4 del plan original; esta vuelta implementó la parte de Fase 2 que es
100% determinista y aditiva, y descompuso el resto en sub-items.

## Qué se hizo

1. **Migración aditiva** (`database/migrations/2026_09_12_003000_add_colaborador_id_bridge_columns_item_9990778.php`):
   agrega `colaborador_id` (unsigned, nullable, FK a `talento_colaboradores.id` con `onDelete('set
   null')`) a `client_main_information` y `whatsapp_conversations` — las dos tablas donde el
   diagnóstico de Fase 1 confirmó, con datos y triangulación de código, que `seller_id` es en
   realidad `users.id`, no `sellers.id`. `seller_id` no se tocó.
2. **Backfill idempotente** (`app/Console/Commands/Active/BackfillColaboradorIdBridgeCommand.php`,
   comando `identidad:backfill-colaborador-id {--dry-run}`): puebla `colaborador_id` con un JOIN
   determinista `talento_colaboradores.user_id = seller_id`. No es un match por nombre ni un ID
   cruzado de entornos (la regla dura de CLAUDE.md sobre eso aplica a IDs que viajan entre dev/prod;
   aquí ambas columnas viven en la misma BD y el bridge por `user_id` es el mismo que ya usa el resto
   del sistema, ver `Actor::seller()`).
3. **Corrido en dev:** `client_main_information` — 4195 filas con `seller_id`, 1856 resueltas,
   2339 quedan `colaborador_id=NULL` (reportadas, no adivinadas): `users.id` 8 (Irving, 2127
   filas — cartera "sin vendedor asignado"), 6 (TERE, 75), 4 (kathya, 113), 11 (14), 3812 (5), 1 (4),
   401 (1). `whatsapp_conversations` — 0 filas pendientes (tabla vacía en dev).
4. **Verificación de consistencia:** 0 discrepancias entre `colaborador_id` y `seller_id` en las
   1856 filas resueltas (join `talento_colaboradores.user_id = seller_id` para las mismas filas).

## Qué NO se hizo (y por qué)

- **`colaborador_id` en las 8 tablas donde `seller_id` sí es `sellers.id`** (`commissions`,
  `commissions_rules_sellers`, `discounts`, `history_sellers_rules`, `payment_by_rule`,
  `payments_sellers`, `sales`, `transactions_sellers`): mismo patrón aditivo pero con un join
  adicional (`sellers.id → sellers.user_id → talento_colaboradores.user_id`). No cabía en esta
  vuelta junto con lo anterior → sub-item **#9990877**.
- **Consolidación de duplicados** (Guadalupe seller 12/48, cuentas espejo `sellers.id=23,25`):
  el propio item lo pide, pero reasigna atribución histórica de ventas/comisiones — es frontera
  dura de dinero. Ya hay un reporte detallado por caso en
  `docs/identidad-vendedores-diagnostico-8-seller-id-item-9990802.md` (item #9990802, cerrado como
  solo-lectura). La fusión en sí NO se ejecutó → sub-item **#9990877** (incluye instrucción
  explícita de consultar a Thomas/Irving antes de tocar esas filas).
- **Fase 3 (doble escritura)** y **Fase 4 (corte de lectura)**: dependen de que la Fase 2 completa
  (incluida la decisión de duplicados) esté resuelta → sub-items **#9990878** y **#9990879**,
  encadenados en dependencia 2→3→4 como pide el propio prompt del item.

## Plan de reversa

Revertir esta vuelta es un solo comando (aditivo, no toca `seller_id`):

```
php artisan migrate:rollback --path=database/migrations/2026_09_12_003000_add_colaborador_id_bridge_columns_item_9990778.php
```

Esto borra la columna `colaborador_id` (y su FK) de ambas tablas. `seller_id` nunca se modificó,
así que el sistema queda exactamente como antes de esta vuelta.

## Qué validar con screenshot

No hay UI nueva en esta vuelta (es puramente de datos/backend) — nada que revisar visualmente
todavía. La ficha del vendedor mostrando sus clientes correctamente (síntoma original de
#9990470) ya estaba resuelto por ese item, por una causa distinta (import faltante), no por este.

## Preguntas originales del item — estado

Las 4 preguntas (`q1`-`q4`) del item nacieron ANTES de la Fase 1 y asumían que
`client_main_information.seller_id` era ambiguo. El diagnóstico de Fase 1 (#9990801) lo resolvió
con evidencia: no es ambiguo, es `users.id`. Por eso `q3` (estrategia de población) ya tiene
respuesta empírica y se aplicó (Opción 1 de esa pregunta: join determinista, no adivinar, reportar
huérfanos). `q2` (criterio de fusión de duplicados) sigue abierta y pasó a #9990877. `q1`
(¿escalar todo a Irving?) y `q4` (cuándo cortar lectura) quedan cubiertas por la descomposición en
fases/sub-items, que es justo lo que la Opción 1 de `q1` recomendaba hacer con evidencia en mano.

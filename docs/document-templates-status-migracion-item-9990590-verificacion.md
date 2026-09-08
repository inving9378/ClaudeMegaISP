# Item #9990590 — Fase 1a-i (parte 2/2): backfill status='publicada' en `document_templates` (RESUELTO — ya aplicado por #9990572, mismo hallazgo que #9990589/#9990591)

## Pedido

Sub-item de seguimiento de `#9990587`. Pedía crear una **SEGUNDA** migración ACTIVA (timestamp
posterior a la "parte 1/2") cuyo `up()` hiciera únicamente el backfill de datos:

```php
DB::table('document_templates')->whereNull('deleted_at')->update(['status' => 'publicada']);
```

con la premisa de que la parte 1/2 solo crea la columna `status` (sin poblarla), dejando el
backfill para esta segunda migración separada.

## Hallazgo

La premisa no corresponde al estado real del código: la migración que agrega la columna
`status` **ya incluye el backfill en el mismo `up()`**, no lo deja para una migración aparte.

- Archivo: `database/migrations/2026_09_07_190000_add_status_and_updated_by_to_document_templates_table.php`.
- Creada por el commit `e665c134` ("Agrega columnas status y updated_by a document_templates
  (Fase 1 #9990572)"), ya en `main`.
- Su `up()` agrega `status` (`enum('borrador','publicada')` default `'borrador'`) + `updated_by`,
  y a continuación corre exactamente el mismo `UPDATE` que pedía este item:
  `DB::table('document_templates')->whereNull('deleted_at')->update(['status' => 'publicada'])`.
- Verificado en esta vuelta contra la BD de dev:
  - `migrate:status` → la migración aparece `Ran` (batch 640).
  - Conteo real: `document_templates` con `deleted_at IS NULL` → **20 filas totales, 0 en
    'borrador', 20 en 'publicada'**. El backfill ya corrió y cubrió el 100% de las filas
    productivas.

## Por qué #9990590 existe siendo un duplicado de trabajo ya hecho

Mismo patrón ya documentado tres veces en este mismo lote de sub-items
(`docs/document-templates-status-migracion-item-9990589-verificacion.md` y
`-item-9990591-verificacion.md`, y antes en `CLAUDE.md` para `#733`/`#741`/`#753`/`#9990003`/
`#9990353`): un sub-item de seguimiento nace del spec original de `#9990587` (que a su vez pedía
"columna + backfill" como si aún no existieran), sin ver que el trabajo ya se había hecho antes
—vía `#9990572`— por una vuelta distinta. La única variante aquí es que el spec de `#9990590`
fragmenta el pedido en "parte 2/2 = solo backfill", pero el hallazgo de fondo es el mismo: no hay
nada pendiente de crear.

## Por qué no se crea una segunda migración

Correr de nuevo el mismo `UPDATE` sería inocuo en sí mismo (la condición `whereNull('deleted_at')`
es idempotente), pero agregar una migración cuyo único efecto es repetir un backfill que ya se
aplicó no resuelve nada real y viola la regla de minimalismo del circuito (no agregar código sin
un problema que resolver). No se creó el archivo.

## Conclusión

**Sin cambio de código de aplicación.** El backfill pedido por `#9990590` ya está aplicado en la
BD de dev (verificado con conteo real: 20/20 filas en `'publicada'`) desde antes de que este item
existiera. Al cerrar este item, el paraguas `#9990587` queda con este único sub-item resuelto —
si no tiene otros hijos abiertos, el hook de cierre en cascada lo completará solo.

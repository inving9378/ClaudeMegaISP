# Item #9990915 — CIRC-03 Fase A2: exponer `motivo_espera` en el modelo + verificación end-to-end (RESUELTO — ya aplicado por el propio padre #9990904)

## Contexto

`#9990915` es un sub-item de seguimiento (`origen_item_id=9990904`) que pedía, dependiendo de que
la Fase A1 (migración de la columna `motivo_espera`) ya estuviera commiteada/mergeada: agregar
`'motivo_espera'` a `$fillable` en `app/Modules/Addons/Roadmap/Models/RoadmapItem.php` (~línea 93,
junto a `excluir_pool_automatico`) con un comentario corto documentando los 6 valores válidos
(`decision|credencial|hardware|sesion_presencial|autorizacion|frontera_produccion`) y que `NULL`
significa "no espera nada / no aplica" — sin tocar `scopeBandeja()` ni ninguna UI (eso es Fase C,
fuera de alcance) — y verificar con tinker el round-trip de los 6 valores + reversión a `null`.

## Verificación

El propio item padre **#9990904** ("CIRC-03 Fase A: migración aditiva motivo_espera en
roadmap_items") ya hizo exactamente este trabajo en el mismo commit que creó la migración, ANTES
de descomponer el resto en sub-items (`#9990914` Fase A1 y este `#9990915` Fase A2):

- `app/Modules/Addons/Roadmap/Models/RoadmapItem.php:96-99` — dentro de `$fillable`:
  ```php
  // CIRC-03 Fase A — por qué espera un item requiere_irving. Valores: decision|credencial|
  // hardware|sesion_presencial|autorizacion|frontera_produccion; NULL = no espera nada.
  'motivo_espera',
  ```
  Coincide exactamente con los 6 valores y la semántica de `NULL` pedidos por el spec.
- `scopeBandeja()` **no menciona** `motivo_espera` (verificado por grep) — intacto, tal como exige
  el spec (esa pieza es Fase C, fuera de alcance).
- Commit `7c811799` ("feat(roadmap): agrega columna aditiva motivo_espera a roadmap_items (CIRC-03
  Fase A)"), integrado a `main` vía `a94fd956` — el mismo commit trae la migración (Fase A1) **y**
  el cambio a `$fillable` con su comentario (Fase A2) juntos, ya en `main` antes de que esta vuelta
  reclamara `#9990915`.

**Verificación end-to-end (tinker, esta vuelta, sobre items de prueba desechables):**

- Round-trip de los 6 valores de la convención sobre un item real (`create()` + `save()` por
  cada valor + `find()` fresco desde BD): los 6 (`decision`, `credencial`, `hardware`,
  `sesion_presencial`, `autorizacion`, `frontera_produccion`) se guardan y se leen de vuelta
  idénticos.
- Reversión a `null` tras el barrido: confirmado `NULL` en el registro fresco.
- **Mass-assignment vía `$fillable`** (lo que realmente valida este item, más allá de la columna):
  `RoadmapItem::create([..., 'motivo_espera' => 'decision'])` asigna el campo correctamente — sin
  el cambio de este item, `create()` lo habría ignorado silenciosamente por no estar en
  `$fillable`.
- Los 2 items de prueba (`#9990940`, `#9990941`) se eliminaron con `forceDelete()` al terminar,
  sin dejar basura.

## Causa

Carrera de timing entre el item padre (que ejecutó el trabajo completo de Fase A —migración +
exposición en el modelo— en un solo commit, y luego descompuso el resto en sub-items) y el
generador de sub-items de seguimiento, que creó `#9990915` con el spec completo de "Fase A2" sin
verificar que el padre ya lo había cerrado. Mismo patrón documentado repetidas veces en
`CLAUDE.md` para items #733/#741/#753/#9990003/#9990353/#9990658/#9990914 — aquí, igual que en su
hermano `#9990914`, el padre hizo el trabajo él mismo (no solo delegó) y aun así generó un
sub-item redundante para cada fase que había cubierto.

## Alcance

Fase C de CIRC-03 (consumir `motivo_espera` desde `scopeBandeja()`/UI) sigue **fuera de alcance**
de este item — no se tocó, tal como exige el spec.

## Conclusión

**Sin cambio de código de aplicación.** `motivo_espera` ya está en `$fillable` de `RoadmapItem`
con su comentario de convención, ya en `main`, y el round-trip + mass-assignment end-to-end quedan
verificados en esta vuelta sin dejar datos de prueba. Este item se cierra documentando la
verificación.

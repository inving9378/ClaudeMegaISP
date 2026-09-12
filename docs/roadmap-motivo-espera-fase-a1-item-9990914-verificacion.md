# Item #9990914 — CIRC-03 Fase A1: migración aditiva `motivo_espera` (RESUELTO — ya aplicado por el propio padre #9990904)

## Contexto

`#9990914` es un sub-item de seguimiento (`origen_item_id=9990904`) que pedía crear una migración
aditiva en `app/Modules/Addons/Roadmap/migrations/` agregando `motivo_espera` (`string(30)
nullable`) a `roadmap_items`, con el mismo patrón que `frontera_valvula` (string libre, no ENUM de
MySQL), valores de convención `decision|credencial|hardware|sesion_presencial|autorizacion|
frontera_produccion`, `down()` con `dropColumn`, y sin tocar `RoadmapItem.php` (eso quedaba para la
Fase A2, un item aparte).

## Verificación

El propio item padre **#9990904** ("CIRC-03 Fase A: migración aditiva motivo_espera en
roadmap_items") ya hizo exactamente este trabajo, en su propia rama, ANTES de descomponer el resto
en sub-items:

- Migración: `app/Modules/Addons/Roadmap/migrations/2026_09_12_005458_add_motivo_espera_to_roadmap_items.php`
  - `Schema::table('roadmap_items', ...)` con guard `hasColumn` antes de `addColumn`
  - `$table->string('motivo_espera', 30)->nullable()->after('frontera_valvula_at')`
  - Comentario en el archivo documenta exactamente los mismos 6 valores de convención pedidos aquí
  - `down()` con `dropColumn` guardado por `hasColumn`
- Commit `7c811799` ("feat(roadmap): agrega columna aditiva motivo_espera a roadmap_items (CIRC-03
  Fase A)"), integrado a `main` vía `a94fd956` ("Integra circuito #9990904 ... a main") — ambos ya
  en el historial de `main` antes de que esta vuelta reclamara #9990914.
- Confirmado en la BD real de dev (`SHOW COLUMNS FROM roadmap_items WHERE Field='motivo_espera'`):
  `varchar(30)`, `Null=YES`, `Default=NULL` — coincide exactamente con el spec.

## Causa

Carrera de timing entre el item padre (que ejecutó el trabajo Y descompuso el resto en la misma
vuelta) y el generador de sub-items de seguimiento, que creó `#9990914` con el spec completo de
"Fase A1" sin verificar que el padre ya lo había cerrado. Mismo patrón documentado repetidas veces
en `CLAUDE.md` para items #733/#741/#753/#9990003/#9990353/#9990658 — la diferencia aquí es que el
padre hizo el trabajo él mismo (no solo delegó), y aun así generó un sub-item redundante.

## Alcance

`#9990915` ("CIRC-03 Fase A2: exponer motivo_espera en el modelo RoadmapItem + verificación
end-to-end") sigue siendo trabajo real pendiente, **fuera de alcance** de este item — no se tocó.

## Conclusión

**Sin cambio de código de aplicación.** La columna `motivo_espera` ya existe en `main` y en la BD
de dev con el diseño exacto pedido. Este item se cierra documentando la verificación.

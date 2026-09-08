# Item #9990589 — Fase 1a-i (parte 1/2): migración status/updated_by en `document_templates` (RESUELTO — ya aplicada por #9990572)

## Pedido

Crear una migración ACTIVA (no en `migrations_old/`) que agregue a `document_templates` **solo el
esquema** (sin backfill): `status` string NOT NULL default `'borrador'` y `updated_by` del mismo
tipo que `created_by` (`string(...)->nullable()`), con `down()` reversible. El backfill quedaba
explícitamente fuera de alcance, reservado para una "parte 2/2" dependiente de esta.

## Hallazgo

La migración ya existe en `main` y ya corrió en la BD de dev — no hay nada que crear.

- Archivo: `database/migrations/2026_09_07_190000_add_status_and_updated_by_to_document_templates_table.php`.
- Creada por el commit `e665c134` ("Agrega columnas status y updated_by a document_templates (Fase 1 #9990572)"),
  integrado a `main` vía `153a3840` ("Integra circuito #9990572 ... a main").
- Contenido: agrega `status` (`enum('borrador','publicada')` con default `'borrador'`, ubicado
  `after('type')`) + `updated_by` (`string(...)->nullable()`, **mismo tipo que `created_by`**, tal
  como pedía el spec), y además ejecuta el backfill (`UPDATE ... SET status='publicada' WHERE
  deleted_at IS NULL`) en el mismo `up()`. `down()` hace `dropColumn(['status','updated_by'])` —
  reversible, igual que pedía el spec.
- Verificado contra la tabla `migrations` de la BD de dev: la fila
  `2026_09_07_190000_add_status_and_updated_by_to_document_templates_table` está registrada
  (`batch=640`) — **ya corrió**. `Schema::getColumnListing('document_templates')` confirma las
  columnas `status` y `updated_by` presentes.

Este item (`#9990589`) nació como sub-item de seguimiento de `#9990587` ("Fase 1a-i — Escribir
migración document_templates.status+updated_by+backfill") — la misma fase, pidiendo dividir el
trabajo en "parte 1/2 esquema" y "parte 2/2 backfill". Para cuando el seguimiento se generó, la
migración real (obra de `#9990572`→`#9990578`, ya en `main`) ya había resuelto ambas partes en un
solo archivo. El propio `#9990587` documenta en su log un `colision-check` con ganador `#9990578`,
confirmando que ese trabajo ya estaba tomado. Mismo patrón de carrera de timing entre un
seguimiento y el item que ya lo resolvió, documentado repetidas veces en `CLAUDE.md`
(`#733`/`#741`/`#753`/`#9990003`/`#9990353`, entre otros).

## Por qué no se crea una migración nueva

Crear una segunda migración que agregue las mismas columnas fallaría al correr `php artisan
migrate` (columna ya existe) — sería una regresión real, no una mejora. La única acción segura es
verificar que el objetivo (esquema con `status`+`updated_by`, reversible) ya está cumplido y
cerrar el item sin tocar código de aplicación.

## Diferencia menor de tipo (no bloqueante)

El spec pedía `status` como `string`; la migración real usa `enum('borrador','publicada')`. Ambos
cumplen "NOT NULL, default 'borrador'" y el propósito de la Fase 1 (distinguir borrador/publicada);
el tipo `enum` ya está en uso end-to-end (`DocumentTemplate::$fillable`, validación de alta/edición
y catálogo del CRUD, todo vía `#9990578`, ya en `main`). No se justifica migrar a `string` solo por
coincidir literalmente con la redacción del spec.

## Conclusión

**Sin cambio de código de aplicación.** El esquema pedido por `#9990589` ya existe en `main` y ya
está aplicado en la BD de dev.

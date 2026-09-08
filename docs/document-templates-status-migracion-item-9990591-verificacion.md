# Item #9990591 — Fase 1a-i (parte 1/2): migración status/updated_by en `document_templates` (RESUELTO — ya aplicada por #9990572, mismo hallazgo que #9990589)

## Pedido

Reenvío idéntico de `#9990589`: crear una migración ACTIVA (no en `migrations_old/`) que agregue a
`document_templates` **solo el esquema** (sin backfill): `status` string NOT NULL default
`'borrador'` y `updated_by` del mismo tipo que `created_by`, con `down()` reversible. El item nació
porque `#9990589` "no cupo en una vuelta por umbral histórico módulo=Auditoria+nivel=B, no por
complejidad real" — spec sin cambios respecto al original.

## Hallazgo

Mismo hallazgo que `#9990589` (ver `docs/document-templates-status-migracion-item-9990589-verificacion.md`):
la migración ya existe en `main` y ya corrió en la BD de dev — no hay nada que crear.

- Archivo: `database/migrations/2026_09_07_190000_add_status_and_updated_by_to_document_templates_table.php`.
- Creada por el commit `e665c134` ("Agrega columnas status y updated_by a document_templates (Fase 1 #9990572)").
- Contiene `status` (`enum('borrador','publicada')` default `'borrador'`, `after('type')`) +
  `updated_by` (`string(...)->nullable()`, mismo tipo que `created_by`) + el backfill (fuera de
  alcance de esta "parte 1/2" según el spec, pero ya resuelto igual). `down()` hace
  `dropColumn(['status','updated_by'])` — reversible.
- Verificado en esta vuelta contra dev: `migrate:status` muestra la migración `Ran` (batch 640) y
  `Schema::getColumnListing('document_templates')` incluye `status` y `updated_by`.

## Por qué #9990591 existe siendo idéntico a #9990589

`#9990591` es el **sub-item que dejó abierto `#9990589`** al cerrarse: la vuelta que resolvió
`#9990589` (escribió su doc de verificación y lo mergeó, commit `002d064e`) ya tenía este hijo
creado desde un intento anterior que murió a media vuelta — el guard de paraguas
(`RoadmapItem.php` bloque "(2b) PARAGUAS") lo detectó abierto y dejó a `#9990589` en
`aprobado_irving`+`excluir_pool_automatico` en vez de completarlo. Mismo patrón de "seguimiento que
repite el pedido del padre ya resuelto" documentado repetidas veces en `CLAUDE.md`
(`#733`/`#741`/`#753`/`#9990003`/`#9990353`), aquí en su variante de sub-item-huérfano-de-paraguas.
No se descompuso de nuevo (el `circuito:cabida` da NO CABE por umbral histórico del módulo, no por
tamaño real del trabajo restante, que es solo verificar y cerrar) — decisión registrada vía
`circuito:reportar --tipo=decision`.

## Por qué no se crea una migración nueva

Crear una segunda migración que agregue las mismas columnas fallaría al correr `php artisan
migrate` (columna ya existe) — sería una regresión real, no una mejora.

## Conclusión

**Sin cambio de código de aplicación.** El esquema pedido por `#9990591` ya existe en `main` y ya
está aplicado en la BD de dev. Al cerrar este item, el paraguas `#9990589` debe cerrarse en
cascada (era su único sub-item abierto).

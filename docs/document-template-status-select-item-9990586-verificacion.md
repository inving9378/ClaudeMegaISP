# Item #9990586 — Fase 1c: select Borrador/Publicada en form de plantilla + validación (RESUELTO — ya aplicado antes de reclamarse)

## Pedido

Sub-item de seguimiento de `#9990578` (Fase 1 del catálogo de plantillas), depende de las Fases
1a (migración, `#9990584`) y 1b (modelo, `#9990585`), ambas ya `completado`. Pedía:

1. Regla de validación `status => nullable|in:borrador,publicada` en
   `DocumentTemplateCreateRequest`/`DocumentTemplateUpdateRequest`.
2. Un `<select>` Borrador/Publicada en la vista de alta/edición de plantilla.
3. Comportamiento en el controller: en `store()`, si no viene `status` cae al default de columna
   (`borrador`); en `update()`, si no lo envían, NO pisar el valor existente.

## Hallazgo — las 3 piezas ya estaban en `main`

Todo el trabajo llegó de una sola vez el 2026-09-07 19:06-19:08, cuando `#9990578` (el padre, aún
sin descomponer en 1a/1b/1c) se trabajó directo — antes de que este sub-item (`#9990586`, creado
el mismo día 18:56) llegara a reclamarse. Verificado con `git merge-base --is-ancestor <hash> HEAD`
para ambos commits relevantes → los dos ya son ancestros de la rama de trabajo actual:

- **Validación** — commit `32551280` ("Valida status (borrador\|publicada) en alta y edición de
  plantillas"): `DocumentTemplateCreateRequest.php:31` y `DocumentTemplateUpdateRequest.php:31`
  ya tienen `'status' => 'nullable|in:borrador,publicada'`.
- **Controller** — mismo commit `32551280`, en
  `app/Modules/Core/Documentos/Controllers/DocumentTemplate/DocumentTemplateController.php`:
  - `store()` (línea 94-96): `if ($request->filled('status')) { $data['status'] = $request->status; }`
    — si no viene, la clave ni se agrega al array de `create()`, así que el INSERT omite la
    columna y el default de MySQL (`'borrador'`) la llena.
  - `update()` (línea 154-156): `if ($request->filled('status')) { $template->status = $request->status; }`
    — si no viene, la propiedad del modelo ya cargado NUNCA se toca, así que `save()` conserva el
    valor existente.
- **Select en el form** — commit `3ecf6320` ("Agrega el campo status al catálogo del CRUD de
  plantillas"), migración `2026_09_08_010000_add_status_field_to_document_template_form.php`:
  agrega la fila `status` al catálogo `modules`/`module_fields` (módulo `DocumentTemplate`, id=61
  en dev) como `select-component` con `options={borrador:Borrador, publicada:Publicada}`,
  `include=true`, `position=4`.

## Por qué el select "ya renderiza" sin tocar ningún `.vue`

Este módulo usa el patrón de **formulario 100% DB-driven** documentado en `CLAUDE.md`
("Catálogo de módulos (form config DB-driven)"): no hay vistas `add.blade.php`/`edit.blade.php`
reales (las rutas GET `create()`/`edit()` del controller no están registradas en
`routes.php` — código muerto, confirmado por `route:list`), la UI real es el modal
`resources/js/components/module/adminstration/document_template/TemplateManager.vue`, que pide
`fieldsJson` vía `requestFieldsByModule('DocumentTemplate')` / `requestEditedFieldsById(...)` y
renderiza cada campo con `<ComponentFormDefault v-if="val.include" :json="val" ...>` — sin ninguna
lista de campos hardcodeada en el componente. `ComponentFormDefault.vue` mapea
`type == 'select-component'` a `<SelectComponent>` (el mismo tipo ya usado por el campo hermano
`type`, con `options` estático, igual patrón que `priority`/`transaction_category` según el propio
comentario de la migración). Verificado en la BD real de dev:

```
id=1388 name=status type=22(select-component) label="Estado" position=4 include=1
```

Con `include=1` y el `type` correcto, el select se pinta solo — no requiere ningún cambio en
`TemplateManager.vue`.

## Verificación funcional (tinker, con limpieza de los datos de prueba)

```
created status (refreshed): [borrador]              ← store() sin status → default de columna
after explicit set persisted: [publicada]            ← update() con status → persiste
after unrelated save (status untouched): [publicada] ← update() sin status → NO se pisa
```

(Un primer intento sin `->refresh()` mostró `status` vacío porque el objeto en memoria de
Eloquent no se auto-refresca tras `create()`/`save()` — no es un bug del comportamiento, solo de
cómo se leía el resultado. Confirmado también con un `INSERT` crudo vía `DB::table(...)` que la
columna real en MySQL es `enum('borrador','publicada') NOT NULL DEFAULT 'borrador'`.)

## Conclusión

**Sin cambio de código de aplicación** — las 3 piezas de la Fase 1c (validación, comportamiento
del controller, y el select del form) ya estaban en `main` antes de que este sub-item se
reclamara, aplicadas en el mismo lote de commits que hizo la Fase 1a/1b originalmente sobre el
padre `#9990578`. Mismo patrón de carrera de timing documentado repetidas veces en `CLAUDE.md`
(`#733`/`#741`/`#753`/`#9990003`/`#9990353`/`#9990658`/`#9990585`).

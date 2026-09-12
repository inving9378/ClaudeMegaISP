# Item #9990585 — Fase 1b: Modelo DocumentTemplate `$fillable` status + updated_by (RESUELTO — ya aplicado antes de reclamarse)

## Pedido

Sub-item de seguimiento de `#9990578` (Fase 1 del catálogo de plantillas): en
`app/Models/DocumentTemplate.php` agregar `status` y `updated_by` a `$fillable`, y verificar si
`BaseModel` auto-llena `updated_by` (igual que `created_by`) vía algún evento/trait — si no lo hace
automáticamente, documentarlo y decidir si el controller debe setearlo explícito en el `update`.

## Hallazgo — el fillable ya está en `main`

`app/Models/DocumentTemplate.php` ya tiene ambos campos:

```php
protected $fillable = [
    'name',
    'html',
    'type',
    'status',
    'created_by',
    'updated_by'
];
```

Commit `3f667c19` ("Agrega status y updated_by al \$fillable de DocumentTemplate"), del
2026-09-07 19:02 — 6 minutos después de que este item (`#9990585`) se creara (18:56, sub-item de
`#9990578`). Verificado con `git merge-base --is-ancestor 3f667c19 HEAD` → ya es ancestro de la
rama de trabajo actual (que arranca de `main`). Mismo patrón de carrera de timing ya documentado
repetidas veces en `CLAUDE.md` (`#733`/`#741`/`#753`/`#9990003`/`#9990353`/`#9990658`): el trabajo
se aplicó directo (por Irving) casi al mismo tiempo que se generaba el sub-item que lo pedía.

## Hallazgo — `BaseModel` NO auto-llena `updated_by`/`created_by`

Verificado leyendo `app/Models/BaseModel.php`: solo usa el trait `LogsActivity` (Spatie
Activitylog) para auditoría de cambios — no tiene ningún hook `creating()`/`updating()`/`saving()`
que setee `created_by`/`updated_by` automáticamente. Se buscó también en
`app/Providers/AppServiceProvider.php`, en traits de `app/Http/Traits/Models/` y en observers
(`find app -iname "*Observer*.php"`) — ninguno setea esas columnas de forma global.

**La nota "`BaseModel` auto-llena `created_by`/`updated_by`" de `CLAUDE.md` no aplica a este
modelo tal cual está escrita** (no hay auto-stamp global). Lo que sí ocurre, y es lo que realmente
llena esas columnas, es que **el controller las setea explícito**
(`app/Modules/Core/Documentos/Controllers/DocumentTemplate/DocumentTemplateController.php`):

- `store()` (línea 92): `'created_by' => auth()->user()?->id` dentro del array que se pasa a
  `DocumentTemplateRepository::createDocumentTemplate($data)` → `$this->model->create($array)`
  (mass assignment — **por eso `created_by`/`status` necesitan estar en `$fillable`**, ya lo
  estaban desde antes de este item).
- `update()` (línea 157): `$template->updated_by = auth()->user()?->id;` como asignación directa
  de propiedad, **antes** de `$template->save()`. Una asignación directa (`$model->campo = valor`)
  no pasa por `$fillable` (esa guarda solo protege `fill()`/`create()`/`update($array)`), así que
  este camino funcionaría aunque `updated_by` no estuviera en `$fillable` — pero si algún día se
  reescribe ese `update()` para usar `$template->update($array)` o
  `DocumentTemplateRepository`, sí lo necesitará, y ya está cubierto.

## Verificación en tinker (transacción con rollback, sin dejar datos)

```
before updated_by=NULL
after updated_by='1' auth_id=1
fillable status='borrador' created_by=1
ROLLED_BACK
```

Confirma: (a) el `update()` del controller deja `updated_by` poblado con el usuario autenticado
tal como está hoy; (b) la asignación masiva (`fill()`) acepta `status` y `created_by` gracias al
`$fillable` ya vigente.

## Conclusión

**Sin cambio de código de aplicación** — el `$fillable` pedido por la Fase 1b ya estaba en `main`
antes de que este item se reclamara. Se documenta que el auto-stamp de `updated_by` NO viene de
`BaseModel` sino de una asignación explícita en el controller (ya presente y correcta), cerrando
así la pregunta de verificación que pedía el propio item.

# Item #9990268 — Seguimiento: ¿qué permiso Spatie controla la edición de `mencion_retiene_categorias`? (RESUELTO — ya implementado)

## Contexto

Al cerrar el item #9990256 ("Exponer `mencion_retiene_categorias` en Torre → Configuración con
bitácora") quedó 1 pregunta sin `opcion_elegida`: **¿qué permiso Spatie controla la edición de este
flag?**, con 3 opciones:

1. **Reutilizar un permiso existente** de Torre → Configuración (p.ej. `torre.configuracion.editar`
   o el que ya proteja esa vista) — aditivo, sin cambios de permisos. *(recomendada)*
2. Crear un permiso nuevo específico `torre.mencion_retiene_categorias.editar`.
3. Restringir a super-admin únicamente (hardcode de rol).

Irving aprobó la **Opción 1** (`clave` `706c44761ca38bf4`, verificado con
`RoadmapItem::claveOpcion()` contra el texto de las 3 opciones).

## Verificación — la Opción 1 ya está aplicada desde #9990256/#9990258

No hace falta elegir ni cablear ningún permiso: el propio trabajo de #9990256 (backend) y su
sub-item de UI (#9990259, ver `docs/torre-mencion-categorias-ui-item-9990259-verificacion.md`) ya
reutilizan el permiso existente de la pantalla, exactamente como pide la Opción 1.

**Backend** — `TorreFronterasController::mencionCategorias()` (línea 340) llama
`$this->autorizarEscribir($request)` (línea 342), que en la línea 55 hace
`$this->authorize('torre.config.edit')` antes de aceptar el POST. Es el MISMO candado que usan
`categoria()`, `termino()`, `valvula()` y `techoAutopilot()` — ningún endpoint de esta pantalla usa
un permiso distinto.

**Frontend** — el checkbox de cada categoría en `TorreConfiguracion.vue:195`
(`:disabled="!puedeEditar"`) usa el mismo `puedeEditar` que gobierna el resto de los controles de la
pantalla (radios de válvula, categorías, techo del autopilot, etc.), poblado por
`TorreFronterasController::index()` línea 111: `'puede_editar' => (bool)
auth()->user()?->can('torre.config.edit')`.

**Permiso verificado en BD** (tinker):

```
$ php artisan tinker --execute='$p = \Spatie\Permission\Models\Permission::where("name","torre.config.edit")->first(); echo $p ? "EXISTS id={$p->id}\n" : "MISSING\n"; if ($p) foreach($p->roles as $r) echo " - rol: {$r->name}\n";'
EXISTS id=732
 - rol: super-administrator
 - rol: DESARROLLADOR
```

`torre.config.edit` existe, está asignado a `super-administrator` + `DESARROLLADOR` (el mismo par
que gobierna el resto de las perillas de Torre → Configuración) y ya protege tanto el endpoint como
el toggle de la UI.

## Conclusión

La pregunta ya tenía respuesta antes de que este item se creara: la Opción 1 elegida por Irving
("reutilizar un permiso existente, sin tocar permisos") describe exactamente el estado real del
código desde que #9990256 se cerró. No hay ninguna línea que escribir — ni permiso nuevo, ni
hardcode de rol. Se cierra #9990268 documentando la coincidencia entre la decisión y la
implementación ya viva, sin diffs de código de negocio.

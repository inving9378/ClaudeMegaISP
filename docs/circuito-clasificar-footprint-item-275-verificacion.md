# Verificación clasificación de footprint — item #275 (RESUELTO — premisa incorrecta)

El item #275 nació el 2026-08-26 desde el Motor de Auditoría Continua (detector 5,
`AuditorService::detSinClasificar`) reportando **1 item sin módulo**: `#171 Guardrail de
migraciones falla abierto en la ruta web de Ignition`, con el encargo de correr
`php artisan circuito:clasificar-modulo` para darle footprint.

**Re-verificado en esta vuelta (2026-08-29):** la premisa ya no aplica. `#171` fue **completado
y archivado el 2026-08-27** (`estado_aprobacion=completado`, `archivado_at=2026-08-27
19:35:02`) — un día después de que se creara #275 — por otra vuelta del circuito, sin relación
con este item.

## Comprobación

Tanto `detSinClasificar()` (el detector que generó #275) como el comando
`circuito:clasificar-modulo` excluyen explícitamente los items `completado`/`cancelado`/
`rechazado`:

```php
RoadmapItem::query()
    ->whereNotIn('status', ['done', 'cancelled'])
    ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
    ->where(fn ($q) => $q->whereNull('modulo')->orWhere('modulo', '')->orWhere('modulo', 'Sin clasificar'))
```

Corridas en esta vuelta:

```
$ php artisan circuito:clasificar-modulo --id=171
No hay items sin clasificar.

$ php artisan circuito:clasificar-modulo
No hay items sin clasificar.
```

Cero items activos sin módulo hoy. El propio DoD del item ("el gap ya no aparece si se vuelve a
correr el detector de auditoría") ya se cumple: la próxima corrida de
`AuditorService::detSinClasificar()` no volverá a emitir este gap, porque la consulta es
idéntica a la de arriba.

## Por qué no hay trabajo que hacer

No había ningún mapa de `config/circuito.clasificador` que ampliar ni ninguna asignación manual
que hacer — el único item listado en el spec de #275 dejó de calificar para la consulta antes de
que esta vuelta empezara. Adivinar o forzar una clasificación sobre un item ya cerrado no
aportaría nada (MINIMALISMO — sin un item activo que clasificar, no hay cambio que escribir).

**Sin cambio de código.** Solo esta nota de verificación.

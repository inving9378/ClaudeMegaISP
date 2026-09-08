# Item #9990637 — Backfill de `releases` desde tags git + auditoría de consumidores

## (a) Backfill — hecho

Comando idempotente `php artisan releases:backfill-from-tags` (`--dry-run` disponible).
Lee `git tag -l 'V*'` local (sin `fetch`, sin red) + `git log -1 --format=%aI <tag>` para la fecha,
y hace upsert por `version` exacto (`Release::firstOrCreate`). Corrido en dev: **15 filas
creadas** (ids 75–89), una por cada tag de git V1.16→V1.32 que no tenía fila:

```
V1.16-29.06.2026  V1.17-30.06.2026  V1.18-01.07.2026  V1.19-01.07.2026
V1.21-03.07.2026  V1.22-04.07.2026  V1.23-07.07.2026  V1.24-08.07.2026
V1.25-09.07.2026  V1.26-04.08.2026  V1.27-04.08.2026  V1.28-04.08.2026
V1.29-05.08.2026  V1.30-06.08.2026  V1.32-10.08.2026
```

Cada fila trae **solo** `version` + `release_date` (fecha del commit del tag) +
`origin='backfill_git_tags'` (columna nueva, migración aditiva) + `created_by=`
`User::systemBot()->id`. El resto (`title`, `summary`, `description`, `commit_sha`,
`migracion_desde/hasta`, `snapshot_bd`, `aplicada_en_dev_at/prod_at`, `reversible*`) queda
`NULL` — decisión de Irving (q2): no se inventa autor/notas/vínculo técnico que el tag no trae.
Reejecutar el comando es un no-op (verificado: segunda corrida → "Nada que backfillear").

**No incluidas a propósito:** `V1.20-08.09.2026` (id=73) ya existía — es la fila real creada
hoy por el flujo viejo, antes del fix de `NextVersionResolver` (Fase 1). `V1.7`/`V1.8` están en
la tabla (ids 60/61) pero **no** como tags de git — no hay nada que backfillear ahí, son releases
que sí se registraron en su momento por el flujo normal aunque no dejaron tag correspondiente
(fuera de alcance de este item: el hueco a resolver es tabla←tags, no tags←tabla).

## (b) Auditoría de consumidores de `Release`/`releases`

| Consumidor | Uso | Efecto del hueco (antes del backfill) |
|---|---|---|
| `NextVersionResolver` (Fase 1, ya resuelto hoy) | Consecutivo de la siguiente versión | Ya NO lee la tabla — lee tags git directo. Causa raíz original, ya cerrada. |
| `Modules/Core/Release/Controllers/ReleaseController::index` | Listado "Historial de Releases" (`/releases`) | Mostraba un salto directo V1.15→V1.16(04-sep) en la UI — **corregido por el backfill**. |
| `Modules/Core/Layout/ViewComposers/TopbarComposer` | Badge de versión instalada en el topbar (`Release::latest('release_date')->latest('id')`) | No afectado por el hueco en sí (toma la fila más reciente por fecha, y esa siempre existió); el backfill no cambia su resultado hoy. |
| `App\Services\Updates\GitHubUpdateService::fetchFromGitHub()` | Compara "versión instalada" (`Release::latest('release_date')->latest('id')`) contra el último GitHub Release, para decidir si mostrar "hay actualización" | Mismo comentario que Topbar: usa la fila más reciente, no la serie completa — el hueco no lo hacía fallar, pero una tabla con huecos es una fuente frágil para cualquier futura lógica que sí recorra la serie completa. |
| `App\Services\MigrationGuardService::contraccionMaduraSegunRoadmap()` | Busca `Release::where('version', $tag)` para checar `aplicada_en_prod_at` de una versión declarada `contraccion_de:` en un item | Diseño ya fail-closed: sin fila o sin `aplicada_en_prod_at`, el guard se pone MÁS estricto (nunca menos). Las filas backfilleadas tienen `aplicada_en_prod_at=NULL` a propósito → si algún item futuro declara `contraccion_de: V1.20` (por ejemplo), el guard seguirá negando la excepción — comportamiento correcto, no requiere cambio. |
| `SmartImportExport\Services\SmartImportService` | Declara `releases` como modelo importable (`conflict_keys: version`) | Solo metadata de import/export; no afectado. |
| `Console\Commands\Active\PublishGithubReleaseCommand` | Publica un GitHub Release para una versión ya tageada, busca `Release::where('version', ...)` | Antes del backfill, publicar manualmente V1.16–V1.32 (si alguna vez hiciera falta) habría fallado con "no existe la versión en la tabla releases" pese a que el tag sí existe. Ahora sí encuentra la fila. |
| `Console\Commands\Active\RemoteDeployCommand::saveRelease()` | Escritor principal (crea/actualiza la fila al desplegar) | Es la vía normal de escritura — ver causa raíz abajo: durante jun-ago aparentemente no se usó (o no llegó a este paso) para V1.16–V1.32. |

**Conclusión de la auditoría (para la pregunta q3, "requiere_irving"):** ningún consumidor activo
dependía de que la serie fuera continua de forma que *rompiera* con el hueco (todos toman la fila
más reciente o una versión puntual, nunca recorren "todas las versiones entre X y Y"). El único
efecto real era **de exhibición** (el listado `/releases` mostraba un salto falso) y de
**disponibilidad futura** (`PublishGithubReleaseCommand` no habría encontrado esas versiones si
alguna vez hiciera falta publicarlas). Con el backfill aplicado, ambos quedan resueltos. Se
reporta para que Irving decida (opción 1 ya elegida) si la tabla `releases` sigue siendo la
fuente de la UI de historial a futuro, o si conviene migrarla a una proyección de los tags git —
no se tomó esa decisión de arquitectura aquí.

## (c) Causa raíz de la pérdida (investigación time-boxed, q4)

**No es una fila borrada — nunca se insertó.** Evidencia: la tabla salta de `id=68` (V1.15,
26-jun) a `id=69` **sin hueco en el autoincrement**. Si esos ~16 releases se hubieran insertado
y luego perdido por un `TRUNCATE` o por los incidentes de `migrate:fresh` de dev (22-ago y
25-ago, `docs/bitacora-sesiones.md:1936`), el autoincrement habría quedado adelantado y —en el
caso del 25-ago— el PITR (que reconstruyó el binlog hasta 6 segundos antes del `DROP`) habría
traído de vuelta esas filas con sus ids/fechas originales de jun-ago, no un hueco. Que el
siguiente id disponible sea exactamente el consecutivo de junio indica que los tags V1.16–V1.32
(reales, 29-jun a 10-ago) se cortaron por una vía que nunca llegó a insertar en la tabla
`releases` de esta base — no por `ReleaseController::store()` ni por
`RemoteDeployCommand::saveRelease()` en este entorno. Los dos incidentes de `migrate:fresh`
documentados son **posteriores** a todo ese rango y el PITR conservó el hueco tal cual estaba
(reconstruyó dev al estado exacto de un instante antes del `DROP`) — comparten familia
("escritura de `releases` que no ocurrió") pero no son la causa de este hueco en particular.

No se determinó la vía exacta por la que se cortaron esos 16 releases sin pasar por la tabla
(no quedan logs de deploy de esa ventana — `storage/logs/self-update-*.log` rota). Investigación
cerrada aquí por ser time-boxed (q4, opción elegida); si se quisiera profundizar, el siguiente
paso sería revisar el historial de `RemoteDeployCommand.php`/`ReleaseController.php` entre
29-jun y 10-ago para ver si en algún punto de ese rango el paso de guardado de release estuvo
deshabilitado o apuntando a otra conexión.

## Archivos

- `database/migrations/2026_09_08_220000_add_origin_to_releases_table.php`
- `app/Console/Commands/Active/BackfillReleasesFromTagsCommand.php`
- `app/Models/Release.php` (agrega `origin` a `$fillable`)

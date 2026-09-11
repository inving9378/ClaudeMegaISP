# Item #9990764 — bucle reap sobre paraguas ya descompuesto (verificación)

## Síntoma

El item #9990764 ("Fase 4b — Rutas/acciones sin protección de permiso = agujero espejo (punto 3)",
sub-item de seguimiento de #9990745) fue escalado inicialmente por el revisor (denylist propia por
mención de "permiso") y por el DES-TRABE anti-loop de Opus ("el ejecutor ya corrió este item 1× y
NO lo ejecutó"), reaprobado por Irving con sus 2 preguntas estructuradas resueltas (q1 = Opción 1,
generar reporte de auditoría read-only agrupado por módulo, sin tocar código; q2 = Opción 1,
deny-by-default vía middleware global como política futura — decisión registrada, no ejecutada en
este item, que sigue siendo estrictamente solo-lectura).

Una vuelta previa ya hizo lo correcto: corrió `circuito:cabida 9990764` y descompuso el trabajo en
4 sub-items por lote, respetando el límite de una auditoría de ~1750 líneas de `routes/web.php` +
~30 módulos addon en una sola vuelta:

- **#9990769** — Fase 4b-1: Core + `routes/web.php`, con el hallazgo metodológico ya confirmado
  (`BaseModuleServiceProvider::boot()` hace `loadRoutesFrom($routes)` a secas — sin
  `Route::middleware(['web',...])->group()` por default — cada `routes.php` de módulo envuelve sus
  propias rutas en el middleware que elige; no se puede asumir herencia de `check_route_permission`).
- **#9990770** — Fase 4b-2: addons grandes (Talento/MegaFamilia/Mapas/Roadmap/Marketing/
  GestionRed/MapaRed), con la misma clasificación en 5 categorías (protegida / gateada por
  rol-guard / authorize inline / bypass admin conocido / agujero real).
- **#9990771** — Fase 4b-3: ~29 módulos addon restantes, misma metodología.
- **#9990772** — Fase 4b-4: consolidación de los 3 reportes en un único
  `docs/permisos-agujero-item-9990745-auditoria-completa.md`, correctamente bloqueada a esperar
  que los 3 lotes tengan `merge_commit` en `main` antes de tocarlos.

Pero esa vuelta **nunca intentó cerrar** el item padre (#9990764) tras crear los sub-items — el
log solo registra `soltar-claim` ("La vuelta de wt-2 terminó sin cerrar el item... se libera el
reclamo y vuelve a la cola como aprobado_revisor"). Sin ese intento de cierre, el guard de
paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") nunca se disparó, así que Irving
volvió a aprobarlo y el pool lo repartió de nuevo (otra vez a `wt-2`) sin que hubiera trabajo
propio pendiente — mismo síntoma que la larga familia de items documentada en `CLAUDE.md`
(#738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/
#962/#9990554/#9990549/#9990624/#9990650/#9990733/#9990740/#9990741, entre otros).

## Verificación

Se confirmó contra la BD real de dev que los 4 sub-items siguen intactos, sin reclamar:

| Item | Título | Estado | worker_sid |
|------|--------|--------|------------|
| #9990769 | Fase 4b-1 — Core + routes/web.php | `pendiente_revision` | (vacío) |
| #9990770 | Fase 4b-2 — addons grandes | `pendiente_revision` | (vacío) |
| #9990771 | Fase 4b-3 — ~29 módulos restantes | `pendiente_revision` | (vacío) |
| #9990772 | Fase 4b-4 — consolidación del reporte final | `pendiente_revision` | (vacío) |

Los 4 cubren el alcance completo de la auditoría original de #9990764 (todas las rutas de Core +
`routes/web.php` + todos los módulos addon, con una fase final de consolidación en un solo
documento), sin superposición entre lotes. La descomposición original seguía siendo correcta —
nadie más la tocó desde que se creó.

## Corrección

Esta vuelta ejecutó el intento de cierre faltante:

```php
$i = RoadmapItem::find(9990764);
$i->estado_aprobacion = 'completado';
$i->save();
```

El guard de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo reenrutó automáticamente a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log: "le
quedan 4 sub-item(s) abierto(s)"), liberando `worker_sid`/`claimed_at` y sacándolo del
pool/reaper. Queda así hasta que el hook de cierre en cascada (`RoadmapItem.php`, ~459-491) lo
complete solo cuando #9990769, #9990770, #9990771 y #9990772 cierren los cuatro (el último,
#9990772, solo puede empezar cuando los 3 primeros tengan `merge_commit` en `main`).

## Sin cambio de código de negocio

El trabajo técnico real (auditoría de rutas sin protección de permiso, clasificación por riesgo,
reporte consolidado) sigue en #9990769/#9990770/#9990771/#9990772, pendientes de que una terminal
los reclame.

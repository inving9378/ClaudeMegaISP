# Item #933 — Fase 1: mapa del camino actual (marcar → nada) y del release (git log → tag)

Solo lectura, con citas archivo:línea. Base para las Fases 2-6 (Fases 2 y 3 ya implementadas en
este mismo item; 4-6 quedan como sub-item — ver abajo).

## 1. El botón "🏷 Marcar para versión" ya existe, desconectado

- `RoadmapController::integracionMarcarVersion()` — `app/Modules/Addons/Roadmap/Controllers/RoadmapController.php:2118-2133`.
  Toggle booleano puro de `roadmap_items.marcado_version` (línea 2126), sin condiciones ni
  asociación a ninguna versión concreta. El comentario original ya citaba el item #312
  ("armador de versiones") — era la semilla de este trabajo, nunca conectada.
- Ruta: `POST /api/roadmap/integracion/marcar-version` — `app/Modules/Addons/Roadmap/routes.php:203`.
- Botón en la Torre: `IntegracionRamas.vue:133-134` (solo visible si `r.merged`), handler
  `marcarVersion()` en las líneas 351-360 del mismo archivo.
- **Antes de este item: cero consumidores.** `ReleaseChangelogService` tiene 0 referencias a
  `roadmap_items`; el paso `git_tag` del pipeline tampoco lo consulta. Verificado en BD:
  `marcado_version=1` en 0 de 913 items al arrancar este item.

## 2. Cómo se genera el changelog hoy (`ReleaseChangelogService`)

`app/Services/ReleaseChangelogService.php` — método público único `generate(string $newVersion): array`
(línea 57). Reconstruye el changelog **100% desde git**, ignorando `roadmap_items`:
- `findPreviousTag()` (línea 175): `git tag --sort=-version:refname`, toma el más reciente.
- `gatherGitData()` (línea 128): `git log {prevTag}..HEAD --oneline --no-merges -- . {exclusiones}`
  (líneas 137-143) — hoy compara contra **todo lo que hay en `main`**, no contra una selección.
- Filtra archivos sensibles y excluye `app/Modules/Addons/DevTools` (líneas 15-30).
- Consumidor único: `ReleaseController::generateChangelog()` —
  `app/Modules/Core/Release/Controllers/ReleaseController.php:272-300`, expuesto en
  `POST /releases/generate-changelog`.

## 3. Cómo nace un tag hoy (pipeline del publicador)

`config/deployment.php:46-136` — array `steps`. El paso relevante:
- `git_tag` (líneas 92-102): `git tag -a {version} -m "Release {version}"` — **anotado a propósito**
  (`--follow-tags` solo empuja tags anotados).
- Corre dentro de `DeploymentService::run()` (`app/Services/Deploy/DeploymentService.php:13`),
  disparado por `ReleaseController::store()` → `dispatchDeploy()` (líneas 75-173) → `DeployJob` o
  `release:deploy {id}` (`DeployReleaseCommand`).
- El tag se pone siempre sobre **`main` en el instante de cortar la versión** — no hay ningún punto
  donde algo quede fuera; confirma el diagnóstico del propio item.

## 4. Cómo consume prod el tag (`RemoteDeployCommand`)

`app/Console/Commands/Active/RemoteDeployCommand.php:91-99` — `git checkout tags/{version}`
(línea 96). Corre en el servidor receptor, con su propio array de pasos (`backup_db`, `git_sync`,
`migrate_dryrun`, `migrate`, `optimize`, `queue_restart`) — **NO se toca en este item** (frontera
dura explícita del prompt: "el pipeline de deploy NO se modifica").

## 5. Piezas reutilizables ya existentes (por qué la Fase 3 no escribió un segundo calculador de raíz)

- `RoadmapCircuitoService::footprintDeRama(string $branch)` —
  `app/Modules/Addons/Roadmap/Services/RoadmapCircuitoService.php:2590` (antes de este item).
  `git merge-base main {branch}` + `git diff --name-only {sha} {branch}` → array de archivos.
  Usado por `detectarColisionesEnVuelo()` (línea 2613) para pares de items `en_progreso`.
  **Hallazgo de este item:** para una rama YA fusionada, `merge-base(main, branch)` es la propia
  punta de la rama (verificado con git real) → el diff daba **siempre vacío** post-merge. Se
  extendió con un 2º parámetro opcional (`$mergeCommitSiFusionado`, fallback a
  `merge_commit^1..merge_commit`) que no cambia el comportamiento existente para ramas en vuelo —
  ver commit `4561b09a`.
- `origen_item_id` (fillable, `RoadmapItem.php`) — relación padre/hijo ya usada por sub-items y
  seguimientos; reusable para detectar dependencias jerárquicas de release (caso citado:
  #199→#200→#201→#870→#871→#872).

## 6. Campos de `roadmap_items` relevantes (confirmados vía `SHOW COLUMNS`)

```
target_version    varchar(20)  NULL   -- texto libre, SIN FK a `releases`, sin formato validado
branch            varchar(255) NULL
merge_commit      varchar(64)  NULL   -- 593 items lo tienen poblado (a la fecha de este item)
marcado_version   tinyint(1)   NOT NULL DEFAULT 0   -- único con cast boolean en el modelo
origen_item_id    bigint unsigned NULL -- sin FK explícita; compartido con sub-items/seguimiento
```

## 7. Lo que este item YA conectó (Fases 2 y 3 — ver commits en la rama `circuito/item-933-*`)

- `RoadmapCircuitoService::itemsCandidatosVersion()` — candidatos = items con `merge_commit`
  integrado desde el último tag (`git log {tag}..HEAD --format=%H --merges`, un solo git call).
- `RoadmapCircuitoService::detectarDependenciasVersion()` — detector de dependencias (jerarquía +
  archivos con orden cronológico real del merge).
- Endpoints `GET /api/roadmap/integracion/version-candidatos` y `version-dependencias`.
- Panel "Armado de versión" en `ReleasesCrud.vue` (modal "Agregar Versión", solo al crear):
  lista de candidatos con checkbox (reusa el toggle existente, sin campo nuevo) + botón
  "Verificar dependencias" que muestra los avisos de la Fase 3.

## 8. Lo que queda fuera de esta vuelta (Fases 4-6 — ver sub-item)

La Fase 3 (detector) está completa y verificada con datos reales (ver reporte de decisión en el
log del item). Las Fases 4 (construcción de la rama de release con cherry-pick en orden
cronológico + abort limpio), 5 (`ReleaseChangelogService` resumiendo la rama de release, no
`main`) y 6 (verificación en seco con una versión de prueba) requieren manipular git de forma real
(crear ramas, cherry-pick, tags de prueba) y coordinarse con el item hermano #892
(truncamiento del changelog) — se registran como sub-item propio para no comprimir esa parte
crítica en el resto del presupuesto de esta vuelta.

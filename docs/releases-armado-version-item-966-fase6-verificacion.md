# Item #966 — Fase 6: verificación en seco de `construirRamaVersion()` (sin tocar prod)

Continuación de #933 (Fases 1-3, mergeadas a esta misma rama vía `210b0c78`). Fases 4 y 5 ya
estaban implementadas y commiteadas en `circuito/item-966-versiones-a-la-carta-fases-4-6-constr`
(`7795c20b` construcción de rama por cherry-pick, `498a930e` changelog con rama opcional) por una
vuelta anterior que fue reencolada por el reaper antes de cerrar el item. Esta vuelta ejecutó la
Fase 6 (verificación) descrita en el spec del item, todo **local sobre este worktree** (`base_path()`
de `RoadmapCircuitoService::git()` resuelve al cwd del proceso — nunca toca `/var/www/megaisp`).

## Método

Script tinker temporal (`/tmp/verificacion_966.php`, no versionado, borrado al terminar) que:

1. Guarda el `marcado_version` original de los items usados en la prueba (todos `false`).
2. Ejercita `detectarDependenciasVersion()` + `construirRamaVersion()` con datos reales de la BD
   de dev, usando ramas/tags con nombre `*-prueba-966-*` / `vPRUEBA-*` para no chocar con nada real.
3. Verifica los 5 puntos (a-e) del spec de Fase 6.
4. Limpia: borra rama y tag de prueba, restaura `marcado_version` a su valor original, confirma
   `git status` limpio y que quedó en la rama de trabajo original.

## Resultados

**Punto (c) — dependencia excluida se detecta y avisa (caso #898 depende de #882):**
Con `#898` marcado y `#882` (su `origen_item_id`) sin marcar, `detectarDependenciasVersion()`
devolvió la violación `tipo=jerarquia` esperada (más 4 violaciones `tipo=archivos` reales, contra
items que tocan `RoadmapItem.php` sin estar marcados). `construirRamaVersion(..., ignorarAvisos:
false)` devolvió `ok:false, motivo:dependencias_sin_resolver` **sin tocar el repo** — no crea rama.
✅ Confirmado.

**Punto (a) — la rama contiene EXACTAMENTE los commits marcados:**
Candidatos independientes elegidos: #893, #911, #922 (sin `origen_item_id`, `merge_commit` único
cada uno). También chocaron con archivos ya mergeados fuera de lo marcado (`module.json` de
Talento vs #223), así que se probó **la ruta `ignorarAvisos:true`** — el otro camino que pide el
propio spec de Fase 4 ("el llamador debe poder decidir continuar bajo responsabilidad de Irving").
`construirRamaVersion('release/prueba-966-ok', 'vPRUEBA-OK-966', true)` devolvió `ok:true` con los
3 `incluidos` y `fallidos:[]`. `git log {tag}..{rama} --oneline --no-merges` mostró **exactamente 3
commits**, uno por cada item marcado, en el orden cronológico de merge ascendente esperado.
✅ Confirmado.

**Punto (b) — el tag apunta a la rama:**
`git rev-parse vPRUEBA-OK-966` (SHA del *objeto tag*, porque `git tag -a` crea un tag anotado) NO
es igual al SHA de la rama — eso es comportamiento normal de git, no un bug: el tag anotado es un
objeto propio que *envuelve* al commit. Dereferenciado (`git rev-parse vPRUEBA-OK-966^{commit}`),
el SHA coincide exactamente con la punta de `release/prueba-966-ok`. ✅ Confirmado (con la
comparación correcta — `^{commit}`, no el rev-parse directo).

**Punto (d) — lo no elegido queda intacto:**
Los candidatos no marcados usados como control (#899, #903, #921) siguieron con `marcado_version:
false` después de toda la prueba — `construirRamaVersion()` nunca toca ese campo, solo lo lee vía
`itemsCandidatosVersion()`. ✅ Confirmado.

**Punto (e) — el changelog describe SOLO lo incluido:**
`ReleaseChangelogService::gatherGitData()` (vía reflection, sin llamar a la API de Claude — no se
necesita gastar en la resumida para confirmar que el *rango* de commits es el correcto, que es lo
que cambió en la Fase 5) devolvió `total=3` commits con `$branch='release/prueba-966-ok'`, contra
`total=744` con `$branch=null` (comportamiento previo, todo desde el último tag hasta HEAD/main).
✅ Confirmado — el acotamiento reduce el rango de 744 a exactamente los 3 elegidos.

## Limpieza

Rama `release/prueba-966-ok` y tag `vPRUEBA-OK-966` borrados al terminar (`git branch -D` /
`git tag -d`). `marcado_version` de los 8 items usados en la prueba (893, 897, 898, 899, 903, 911,
921, 922) restaurado a su valor original (`false` en los 8 — ninguno estaba marcado antes de
empezar). `git status` limpio al cerrar, HEAD de vuelta en
`circuito/item-966-versiones-a-la-carta-fases-4-6-constr`. Sin residuos en el repo compartido.

## Verificación adicional de regresión

`php -l` limpio en los 5 archivos PHP tocados por Fases 4-5 (`RoadmapController.php`,
`RoadmapCircuitoService.php`, `routes.php`, `ReleaseController.php`, `ReleaseChangelogService.php`),
`php artisan --version` bootea, la ruta nueva `POST /api/roadmap/integracion/version-construir-rama`
aparece en `route:list`, y el build de frontend (`ReleasesCrud.vue`, panel de #933 Fase 2 heredado
en el merge) compiló limpio vía el semáforo (`deploy/circuito/npm-build.sh`).

## Conclusión

Fases 4-6 de #966 quedan verificadas end-to-end en dev, sin tocar prod ni dejar residuos. El
mecanismo sigue siendo **aislado y a demanda** (endpoint propio, no forma parte del pipeline de
`config/deployment.php`): construir una rama de release real para publicar sigue siendo una
decisión explícita de Irving desde la UI, este item solo deja construido y probado el motor.

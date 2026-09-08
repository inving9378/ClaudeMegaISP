# Item #9990628 — Verificación end-to-end changelog+versión con el rango real V1.32..HEAD (Fase 5 de #9990624)

Fecha: 2026-09-08 · Worktree wt-2 · Dependencias confirmadas mergeadas a `main` antes de empezar:
`#9990626` (merge_commit `c65d422f`) y `#9990627` (merge_commit `532a66f8`).

Verificación **ejecutada de verdad** contra el rango real (`V1.32-10.08.2026..HEAD`, sin persistir
nada en BD — la versión usada en las pruebas es un string ficticio que nunca se guardó como
`Release`). Sin cambios de código: todo lo verificado ya funciona como se esperaba.

## (a) Changelog del rango real V1.32..HEAD — respuesta rápida + job en background

- **Dispatch (lo que dispara el click del usuario)**: se llamó directamente el mismo código que
  `ReleaseController::generateChangelog()` ejecuta (`Cache::put` + `GenerateReleaseChangelogJob::dispatch()`).
  Tiempo real medido: **17.5 ms**. Muy por debajo del límite de 60s del criterio de aceptación.
  El job quedó encolado en la tabla `jobs` (conexión `database`, cola `default`) — se verificó y
  se borró esa fila de prueba sin ejecutarla (limpieza, sin efectos colaterales).
- **Job en background (la generación real con IA)**: se corrió `ReleaseChangelogService::generate()`
  contra el rango real completo. Resultado medido:
  - `total_commits = 1427` (commits reales desde el tag `V1.32-10.08.2026` hasta `HEAD`, ya sin
    merges y sin las rutas excluidas de DevTools — igual que hace `gatherGitData()`).
  - `resumidos_commits = 1200` (tope de `MAX_BATCHES=15 × BATCH_SIZE=80`).
  - `truncado = true`, con `aviso_truncamiento = "Se resumieron 1200 de 1427 commits desde
    V1.32-10.08.2026; el resumen está incompleto."` — el aviso es explícito, no un truncamiento
    silencioso (comportamiento introducido por el item #892, ver punto (d)).
  - **Tiempo real de principio a fin: 208.8 s (~3.5 min)** — dentro del rango "2-4 minutos" que
    describe el propio item y el comentario de `GenerateReleaseChangelogJob.php`.
  - El resultado de IA se generó correctamente (`title`, `summary` y `improvements` con 1827
    caracteres) — el pipeline map-reduce (15 llamadas de mapeo + 1 de síntesis) funcionó de punta
    a punta con el volumen real actual del repo (mayor al de cuando se escribió el item: pasó de
    ~1122 commits estimados a 1427 reales al día de hoy).

**Conclusión (a): confirmado, funciona como se espera.**

## (b) Guardar la versión sin esperar a la IA

Revisado `ReleaseController::store()` (líneas 78-177): la validación (`'summary' =>
['nullable', 'string']`) no exige el campo, y `ai_description` solo se usa si viene no vacío
(línea 144-152) para crear un `ReleaseDescription` adicional — no hay ninguna dependencia del
estado del job de changelog (`Cache::get(GenerateReleaseChangelogJob::cacheKey(...))`) dentro de
`store()`. En el frontend (`ReleasesCrud.vue`), el botón Guardar solo se deshabilita por errores
de validación del formulario, no por `aiLoading`, y `onSubmit()` documenta explícitamente (líneas
401-404) que "la versión se crea SIEMPRE, aunque el resumen no exista".

**Conclusión (b): confirmado, Guardar es independiente del job de IA.**

## (c) `nextVersion()` alineado con el máximo tag real

Se ejecutó `NextVersionResolver::resolver()` de verdad (incluye el `git fetch --tags --force`
real contra `origin`, que sí tiene conectividad desde esta máquina dev):

```
build: 33
label: "V1.33-08.09.2026"
max_detectado: 32
origen: "V1.32-10.08.2026"
```

Ya no calcula desde `Release::pluck('version')` (la causa del bug histórico documentado en la
cabecera del propio archivo) sino desde `git tag -l 'V*'` tras el fetch — fail-closed si el fetch
falla (lanza `RuntimeException`, el controller responde 503 con mensaje explícito, nunca inventa
un consecutivo). `ReleaseController::store()` además tiene el guard anti-retroceso (líneas
106-132) que rechaza con 422 cualquier build ≤ al máximo publicado en tags.

**Conclusión (c): confirmado, sugiere V1.33 (superior a V1.32, el máximo real), sin retroceso.**

## (d) Item #892 — ¿sigue teniendo sentido aparte?

`#892` ("Changelog de release trunca en silencio con 571 commits...") está **`completado`/`done`**
(merge_commit `23d8d886d650b2592c3507c6fd9dc992bf46160b`), previo a esta cadena de items. Es
justamente el item que introdujo `BATCH_SIZE`, `MAX_BATCHES` y `aviso_truncamiento` en
`ReleaseChangelogService`, y su propio código ya documenta (comentario en la línea del `max_tokens`
del `reduceSummaries()`) una verificación previa con el rango real `V1.32..HEAD` (726 commits en su
momento, antes de que crecieran más commits hasta los 1427 de hoy). Nada de lo tocado en
`#9990624`/`#9990626`/`#9990627`/`#9990628` reabre ni contradice ese fix — esta verificación lo
único que hizo fue re-ejercitarlo con datos más grandes y confirmar que sigue funcionando (aviso
explícito, no silencioso).

**Conclusión (d): no duplica ni necesita reabrirse — sigue cerrado correctamente.**

## Resultado general

Ningún hallazgo de (a)/(b)/(c) resultó en discrepancia — no se requirió abrir sub-items de
corrección (política elegida en la pregunta `q2` del propio item: documentar sin corregir salvo
que hubiera defecto real). **Sin cambio de código** — solo esta verificación documentada.

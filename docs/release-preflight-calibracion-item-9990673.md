# Calibración de ReleasePreflight contra los últimos 5 rangos publicados

**Item:** #9990683 (F2d, sub-item de aceptación del padre #9990673 — épica #9990668)
**Depende de:** F2c / #9990682 (hook ya mergeado en `DeploymentService`, commit `9da94e58`)
**Fecha:** 2026-09-10
**Comando evaluado:** `php artisan release:preflight {version}` (F2b, #9990681)
**Fuente de "publicado":** `php artisan releases:reconciliar` (#9990669) — veredicto `PUBLICADA`

## 1. Selección de las 5 versiones

`releases:reconciliar` ordena por número de versión descendente (`compararDesc()` vía
`VersionComparator::isNewer`), no por fecha de fila. Las últimas 5 con veredicto `PUBLICADA` al
momento de correr este item:

| # | Versión           | Predecesora real (`git tag --sort=-version:refname`) |
|---|--------------------|--------------------------------------------------------|
| 1 | V1.34-09.09.2026   | V1.33-08.09.2026                                        |
| 2 | V1.33-08.09.2026   | V1.32-10.08.2026                                        |
| 3 | V1.32-10.08.2026   | V1.30-06.08.2026 (V1.31 no existe como tag publicado)   |
| 4 | V1.30-06.08.2026   | V1.29-05.08.2026                                        |
| 5 | V1.29-05.08.2026   | V1.28-04.08.2026                                        |

(V1.31 está ausente de la secuencia — no es un hallazgo de este item, es la misma deriva de
numeración que documentan #9990669/F0 y que resolverá F6/#9990677.)

## 2. Corrida A — literal del spec: `release:preflight {version}` (sin `--branch`)

Ejecución tal cual la especifica el paso 2 del item (ref implícito = `HEAD`, el dev tip actual).

| Versión | Veredicto | Checks distintos de OK |
|---|---|---|
| V1.34-09.09.2026 | 🟢 VERDE | ninguno |
| V1.33-08.09.2026 | 🟢 VERDE | ninguno |
| V1.32-10.08.2026 | 🟢 VERDE | ninguno |
| V1.30-06.08.2026 | 🟢 VERDE | ninguno |
| V1.29-05.08.2026 | 🟢 VERDE | ninguno |

**Resultado literal: 0 falsos positivos** — ningún check salió `fail` ni `warn` en ninguna de las
5 corridas. Por la definición del propio item (paso 3: "cualquier check en fail o warn... es un
falso positivo"), no hay nada que corregir bajo esta lectura estricta.

**Pero el resultado es poco informativo por diseño**, y vale documentarlo: como no se pasó
`--branch`, los checks que dependen de `$ref`/`$prevTag` (`head_alcanzable_origin`,
`migraciones_aditivas`, `changelog_no_truncado`, `version_previa_publicada`) evaluaron el HEAD
actual del repo, no el commit histórico real de cada versión — el `version_previa_publicada` de
las 3 corridas intermedias (V1.32/V1.30/V1.29) devolvió `V1.34-09.09.2026 está PUBLICADA` en las
tres, no su predecesora real (ver §4). El "VERDE" de esas 3 filas no certifica nada sobre el
momento en que esas versiones se cortaron — certifica que el HEAD de hoy está limpio, lo cual es
trivialmente cierto y no depende de qué versión se le pase como argumento.

## 3. Corrida B (diligencia adicional, no pedida literalmente por el spec) — `--branch={version}`

Para que la calibración tuviera algo que decir sobre el momento real de cada corte, se repitió
ANCLANDO `$ref` al tag histórico (`--branch=V1.xx-...`), reconstruyendo lo que el preflight habría
dicho si hubiera corrido en ese instante contra ese commit.

| Versión | Veredicto | Check en fail/warn |
|---|---|---|
| V1.34-09.09.2026 | 🔴 ROJO | `head_alcanzable_origin` (fail) |
| V1.33-08.09.2026 | 🔴 ROJO | `head_alcanzable_origin` (fail) |
| V1.32-10.08.2026 | 🔴 ROJO | `head_alcanzable_origin` (fail) |
| V1.30-06.08.2026 | 🔴 ROJO | `head_alcanzable_origin` (fail) |
| V1.29-05.08.2026 | 🔴 ROJO | `head_alcanzable_origin` (fail) |

Las 5 corridas fallan por el mismo check, en el mismo modo. El resto (`arbol_limpio`,
`sin_secretos_en_diff`, `migraciones_aditivas`, `app_debug_esperado`,
`killswitches_dinero_false`, `token_github_valido`, `changelog_no_truncado`) salió OK en las 5.

## 4. Análisis de los hallazgos

### 4.1 `head_alcanzable_origin` con `--branch=<tag histórico>` → FALSO POSITIVO ESTRUCTURAL, esperado

`checkHeadAlcanzableOrigin()` hace `git merge-base --is-ancestor origin/main {ref}` — pregunta
"¿origin/main es ancestro de lo que voy a publicar?". Esa pregunta solo tiene sentido en el
INSTANTE de cortar una versión nueva (ahí `$ref` = HEAD ≈ origin/main reciente). Al anclar `$ref`
a un tag de hace semanas, `origin/main` (que ya avanzó mucho más allá) deja de ser ancestro de ese
commit viejo por definición — el check está comparando contra un origin/main que en su momento no
existía. **Es un falso positivo garantizado de este check cada vez que se le pasa `--branch` con
un commit que no es el tip actual**, no un bug del check en su uso real (que siempre corre con
`$ref` implícito = HEAD, en el pipeline de emisión, F2c). No accionable ni deseable de "arreglar"
aquí: el check hace exactamente lo que se le pidió (F2b), y el spec de esta fase (paso 3) solo
pide documentar. Nota para quien reuse `--branch` con fines de auditoría histórica: ignorar este
check en ese modo.

### 4.2 Checks 4/8/9 comparten `$prevTag` — y `findPreviousTag()` no encuentra "la predecesora de
`$version`", encuentra "la tag más alta que no sea `$version`"

`ReleasePreflightService::evaluate()` calcula `$prevTag` UNA vez vía
`ReleaseChangelogService::coverage($version, $branch)` → `findPreviousTag()`
(`app/Services/ReleaseChangelogService.php:211-217`):

```php
$output = $this->runGit('git tag --sort=-version:refname', $env, $base);
$tags   = array_filter(explode("\n", trim($output)));
$tags   = array_values(array_filter($tags, fn($t) => $t !== $newVersion));
return $tags[0] ?? null;
```

Esto devuelve el tag de **versión más alta que exista en el repo, excluyendo únicamente
`$newVersion` mismo** — no busca la posición de `$newVersion` en la secuencia y toma su vecino
inferior. **En el flujo real (`release:deploy`, cortar una versión nueva) esto es correcto y
transparente**: la versión que se está por publicar siempre es la más alta que existirá, así que
"la más alta que no sea ella" = su predecesora real por construcción.

**Pero al reevaluar retroactivamente una versión ya publicada que NO es la última** (exactamente
este ítem de calibración), el método sigue devolviendo la tag global más alta —hoy,
`V1.34-09.09.2026`— sin importar cuál de las 5 versiones se esté evaluando. Confirmado en los
datos de la Corrida A: `version_previa_publicada` reportó `V1.34-09.09.2026 está PUBLICADA` para
V1.33, V1.32, V1.30 **y** V1.29 (4 de las 5 corridas), en vez de sus predecesoras reales
(V1.32, V1.30, V1.29, V1.28 respectivamente — ver tabla §1). Solo V1.34 (que sí es la más alta)
obtuvo el `$prevTag` correcto (V1.33).

Efecto en cascada: como `checkMigracionesAditivas` y `checkChangelogNoTruncado` (checks 4 y 8)
usan el mismo `$prevTag`, para V1.33/V1.32/V1.30/V1.29 esos dos checks terminaron evaluando el
rango `V1.34..HEAD` (vacío o casi vacío, porque V1.34 es la tag más reciente) en vez del rango
real contra su predecesora — por eso reportaron "sin migraciones nuevas" / "changelog no se
truncaría" de forma trivial, sin haber examinado el rango que en teoría debían examinar. **No
llegaron a fallar por accidente, pero tampoco probaron nada real** en este modo de calibración.

**Esto es la MISMA deriva histórica que el propio item anticipó para el check 9** (paso 4 del
spec) y que **F6/#9990677 existe específicamente para resolver** (reconciliar el historial de
versiones/tags). Documentado tal como pide el spec — **"falso positivo estructural conocido, no
accionable hasta F6"** — sin tocarlo aquí, para no adelantar ni duplicar el trabajo de F6. La
corrección de fondo (que `findPreviousTag()` ubique la posición real de `$newVersion` en la lista
ordenada, en vez de asumir que siempre es la más nueva) es responsabilidad de F6, porque requiere
la misma reconciliación de secuencia que esa fase va a construir — no un fix puntual de una línea
aislado del resto.

### 4.3 ¿Algún falso positivo "barato y obvio" (ej. regex mal escrito) para corregir en este mismo item?

No se encontró ninguno. El único check que efectivamente marcó `fail` (`head_alcanzable_origin`,
§4.1) lo hizo por diseño esperado al usarse fuera de su contexto real (`--branch` apuntando a un
commit viejo), no por una expresión regular ni una condición mal escrita — no hay "fix puntual"
que aplicarle sin cambiar su semántica (que es correcta para su uso real en F2c). El otro hallazgo
(§4.2) es estructural y explícitamente diferido a F6 por el propio spec de este item. **Sin
cambio de código en este item**, consistente con la decisión ya tomada por Irving en la pregunta
q3 del brief de este item ("NO tocar reglas en F2d; los ajustes van en item aparte que Irving
apruebe con la tabla en mano").

## 5. Conclusión y siguientes pasos

- **Corrida A (como pide el spec, sin `--branch`): 0/5 falsos positivos literales** — nada salió
  `fail`/`warn`. Pero es un resultado de bajo valor para 3 de los 4 checks dependientes de
  `$prevTag`/`$ref`, porque sin `--branch` esos checks evalúan el HEAD actual, no el commit
  histórico de la versión pasada como argumento.
- **Corrida B (`--branch` anclado al tag), diligencia adicional: 5/5 ROJO**, siempre por el mismo
  check (`head_alcanzable_origin`) y siempre por la misma razón estructural esperada (§4.1) — no
  un bug, es el check funcionando fuera de su ventana de validez.
- **Hallazgo de fondo para F6 (#9990677):** `ReleaseChangelogService::findPreviousTag()` asume
  que `$newVersion` es siempre la tag más nueva del repo; al evaluar una versión histórica
  no-última, calcula mal `$prevTag` (siempre la tag global más alta, no la predecesora real) —
  esto silencia los checks 4 (`migraciones_aditivas`) y 8 (`changelog_no_truncado`) y produce el
  patrón de "predecesora incorrecta" ya anticipado para el check 9 en el propio spec de este
  item. No se toca aquí a propósito; queda anotado para que F6 lo resuelva como parte de la
  reconciliación de historial (la corrección correcta necesita la misma reconstrucción de
  secuencia que esa fase ya va a construir, no un parche aislado).
- **F3 (veto real, item futuro):** cuando se decida bloquear en vez de solo reportar, el veto NO
  debe considerar `head_alcanzable_origin` cuando se evalúe con `--branch` apuntando a un commit
  que no es el HEAD/tip actual (ese modo es retroactivo/de auditoría, no de corte real) — dejarlo
  anotado para no repetir esta confusión al diseñar F3.
- Cierre del item padre #9990673: este es el último sub-item pendiente (position 4/4); el hook de
  cierre en cascada lo completa solo al cerrar este item — no se fuerza manualmente.

# Item #9990627 — next-version: tags de git vs. tabla `releases` (RESUELTO — ya corregido antes de que el item llegara a ejecutarse)

## Lo que pedía el item

Sub-item de seguimiento de #9990624 (Fase 4, READ-ONLY): `ReleaseController::nextVersion()`
calculaba el consecutivo solo desde `Release::pluck('version')` (tabla `releases`), y esa tabla
se había quedado en V1.18 mientras el último tag real de git ya era V1.32. Pedía:

1. Cambiar la fuente de verdad de `nextVersion()` al **MAX entre** (a) los tags de git y (b)
   `Release::pluck('version')`, logueando ambos valores.
2. Investigar y dejar reportado por qué divergieron ~14 versiones entre la tabla y los tags.
3. No tocar producción, no crear/mover tags — solo lectura de git + cálculo.

Irving aprobó un brief con 3 preguntas (`q1`/`q2`/`q3`), las 3 con la opción recomendada:
MAX(tags, tabla) + loguear ambos, reportar divergencia sin abortar, y filtrar tags por regex
semver estricto.

## Qué se encontró al llegar a implementarlo

El fix real **ya estaba en `main`** desde antes de que Irving llegara a aprobar el brief:
commit `a32cc74f` ("fix(release): el consecutivo de versión sale de los tags git, no de la
tabla releases (Fase 1)"), autor Irving MegaISP + Claude Opus 5, con timestamp
`2026-09-08 12:55:30` — **2h34min antes** de que Irving aprobara el brief de este item
(`2026-09-08 15:29:13`). Fue una corrección de emergencia hecha por fuera de este item/circuito
(el mismo día que dev había emitido V1.19 y V1.20 con prod ya en V1.32 — bug activo, destrababa
prod).

La solución real implementada (`app/Services/Updates/NextVersionResolver.php` +
`ReleaseController::nextVersion()`/`store()`) es **más estricta** que la aprobada en el brief:

- **NO** es MAX(tags, tabla) — es **tags de git ÚNICAMENTE**, fail-closed: si `git fetch --tags
  --force` falla, lanza excepción y `nextVersion()` responde 503 sin inventar un número. La
  tabla `releases` deja de participar en el cálculo del consecutivo.
- `store()` añade un **guard anti-retroceso**: rechaza (422) cualquier versión con build ≤ al
  máximo real detectado en los tags, "sin importar quién lance el release".
- Motivo explícito en el propio código (docblock + commit): volver a mezclar con la tabla
  reintroduce el mismo riesgo que causó el bug (la tabla puede perder filas / un PITR puede
  regresarla), así que el fix decidió NO usarla como fuente en absoluto.

## Verificación hecha en esta vuelta (sin tocar producción, sin crear/mover tags)

- `git tag -l 'V*' | sort -V | tail`: el tag más alto real es **`V1.32-10.08.2026`** (28 tags
  totales).
- `NextVersionResolver::resolver()` (tinker, solo lectura + `git fetch --tags` de sincronización):
  ```
  {"build":33,"label":"V1.33-08.09.2026","max_detectado":32,"origen":"V1.32-10.08.2026"}
  ```
  Confirma el criterio de aceptación del propio item: ya sugiere `V1.33-<fecha de hoy>`, no
  `V1.19`.
- Tabla `releases`, últimas filas: id=68 `V1.15` (`2026-06-26`) → id=69 `V1.16`
  (`2026-09-04`) → id=70 `V1.17` (`2026-09-07`) → id=71 `V1.18`/id=72 `V1.19`/id=73 `V1.20`
  (los tres el `2026-09-08`, entre las 12:01 y las 12:34 — antes de que este item se creara).
  El salto de fecha (26-jun → 04-sep, ~2 meses) entre id=68 y id=69, con los tags de git
  cubriendo V1.16 a V1.32 en ese mismo lapso, confirma la causa raíz ya documentada en el
  commit `a32cc74f`: la tabla dejó de recibir esas 16 filas (V1.16 a V1.31) — ya sea porque el
  paso `save_release` del pipeline no corrió en esas publicaciones, o porque esos releases se
  taggearon fuera del flujo que escribe en la tabla — y cuando alguien retomó `nextVersion()` el
  04-sep, la tabla solo "recordaba" hasta V1.15, así que reinició la cuenta en V1.16 sin saber
  que prod ya iba en V1.32.

## Decisión (registrada, no ejecutiva)

No se revierte el fix `a32cc74f` a la forma "MAX(tags, tabla)" que aprobó el brief del item:
hacerlo reintroduciría exactamente la dependencia en la tabla `releases` que causó el
incidente que ese mismo commit vino a cerrar. La versión ya implementada satisface el criterio
de aceptación del item (fuente de verdad corregida + verificado `V1.33-08.09.2026`) con una
solución más robusta (fail-closed + guard anti-retroceso) que la propuesta original. Registrado
vía `circuito:reportar --tipo=decision` antes de este documento.

## Alcance

Sin cambio de código de aplicación — el fix ya estaba en `main`. Este documento cierra el item
con la verificación independiente + la investigación de la divergencia que pedía la Fase 4.

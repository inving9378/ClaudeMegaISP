# Item #9990636 — V1.33 emitido en dev SIN push (RESUELTO — la premisa ya no es cierta: el tag fue borrado)

## Lo que pedía el item

El item daba por hecho que `V1.33-08.09.2026` ya estaba emitido en dev (tag anotado local sobre
`d1548c8a` + fila `id=74` en `releases`) y que solo faltaba una acción manual de David en ventana
de mantenimiento: `git push origin main --follow-tags` + publicar el GitHub Release, sin
desplegar a prod. Irving aprobó esa acción (Opción 1 del brief del DES-TRABE).

## Qué se encontró al llegar a ejecutarlo

**El artefacto que había que publicar ya no existe.** Verificado en esta vuelta (solo lectura,
sin tocar `origin`):

- `git tag -l` no lista `V1.33-08.09.2026`. El único tag de hoy es `V1.20-08.09.2026`.
- `git fsck --unreachable` sí encuentra el **objeto tag colgante**
  `b4a163ed7fe7533e00ec9351c996b5e468abb922` → `git cat-file -p` confirma que es exactamente
  ese tag (`tag V1.33-08.09.2026`, apunta a `d1548c8a`, tagger `Irving MegaISP`,
  `1788894607 -0600` = 2026-09-08 12:30). Es decir: **el tag se creó y después se borró**
  (`git tag -d`) — no es que nunca se haya creado, ni que este worktree no lo vea (todos los
  worktrees comparten el mismo `.git`, confirmado con `git worktree list` +
  `git rev-parse --git-common-dir`).
- Tabla `releases`: no existe fila `id=74`. La fila más reciente es `id=73` = `V1.20-08.09.2026`
  (`commit_sha=eb61ec36…`, un commit **distinto** de `d1548c8a`). `releases` no tiene soft
  deletes (`Schema::hasColumn('releases','deleted_at')` = false), así que si la fila 74 llegó a
  existir, se borró de verdad; lo más probable es que nunca se haya llegado a insertar (el tag
  se hizo y se deshizo antes de completar el flujo de `store()`).

## Por qué se borró (contexto, no reconstruido de cero)

El mismo día, y antes de que este item fuera aprobado, se cerró **#9990627** (Fase 4 de
#9990624 — ver `docs/release-nextversion-tags-item-9990627-verificacion.md`, mergeado a `main`
en `532a66f8`), que documenta el fix real `a32cc74f`: `ReleaseController::nextVersion()` pasó a
calcular el consecutivo **solo** desde los tags de git (fail-closed), porque la tabla `releases`
se había quedado congelada en V1.15 mientras git ya iba en V1.32 — la causa de que hoy mismo se
hubieran emitido V1.18/V1.19/V1.20 con números por debajo de lo que prod ya conocía. El tag
`V1.33-08.09.2026` sobre `d1548c8a` es del mismo rango horario que ese incidente; todo indica
que fue un intento (manual o del flujo viejo) de corregir la numeración a mano, descartado en
cuanto `NextVersionResolver` quedó operativo y pudo recalcularla correctamente por sí solo.
`NextVersionResolver::resolver()` corrido en esta misma vuelta (solo lectura) sigue devolviendo
`{"build":33,"label":"V1.33-08.09.2026","max_detectado":32,"origen":"V1.32-10.08.2026"}` — el
número correcto sigue siendo V1.33, pero como **release nuevo por crear**, no como el tag viejo
que había que empujar.

## Por qué no se reconstruye aquí

- Recrear el tag sobre `d1548c8a` (4 commits detrás del `main` actual) y pushearlo sería
  reintroducir un artefacto que alguien descartó a propósito, exactamente el tipo de
  ANTI-PING-PONG que este protocolo pide evitar en vez de pelear.
- La vía correcta hacia adelante es la que ya deja lista la pantalla "Crear versión" (ya
  corregida por #9990624/#9990625/#9990627): David genera un release nuevo desde ahí — con el
  pipeline de changelog ya sin cuelgue (Fase A/B de #9990624/#9990625) y el consecutivo ya
  calculado de forma correcta y fail-safe (#9990627) — sobre el HEAD real de hoy, no sobre un
  commit viejo. Ese release nuevo (aunque probablemente se siga llamando V1.33 por la fecha) es
  un artefacto distinto al que describía este item, y su publicación a GitHub sigue siendo,
  como ya se decidió, una acción manual de David en ventana de mantenimiento — fuera del alcance
  de este item y del circuito automático (frontera dura de producción: nunca se hizo, ni se
  intenta aquí, ningún `git push origin`).

## Alcance

Sin cambio de código de aplicación — la corrección de fondo (numeración) ya estaba mergeada
antes de esta vuelta. Este documento cierra el item dejando registrado que el artefacto
específico que pedía publicar ya no existe, y por qué. Si Irving/David quieren publicar una
versión a GitHub, el camino es generar un release nuevo desde "Crear versión" y decidir el push
en ese momento — no reviving este tag.

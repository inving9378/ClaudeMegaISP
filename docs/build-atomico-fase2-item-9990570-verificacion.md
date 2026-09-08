# Item #9990570 — Build atómico Fase 2 (swap staging→public) — verificado + regresión encontrada y corregida

## Qué pedía el item

Sub-item de seguimiento de #9990491, planteado como "Fase 2" (hermano de #9990569, cerrado por
premisa superada — ver `docs/build-atomico-fase1-item-9990569-verificacion.md`): implementar el
swap atómico staging→public con `rename()`, un guard de completitud (build incompleto = build
fallido, sin tocar el bundle vivo) y una verificación end-to-end real (build + polling durante el
build para confirmar que nunca se sirve un manifest o un `app.js` a medio escribir).

## Qué ya existía en `main`

El swap atómico en sí **ya estaba mergeado** desde #9990491 (commit `0d9f28e4`, antes de que este
sub-item existiera): `deploy/circuito/npm-build.sh` compila a un staging dentro de `public/` (env
`MIX_JS_STAGE_DIR`) y sólo si `npm run $MODE` termina con RC=0 corrige el manifest (`sed`) y mueve
el bundle al lugar final; si falla, el staging se borra y el bundle vivo queda intacto. Mismo
patrón que #9990569: la premisa de este item ("depende de una Fase 1 que deja un staging con
`{js,css,mix-manifest.json}`") no se cumplió tal cual — #9990491 hizo staging+swap end-to-end en un
solo commit, sólo para JS+manifest (CSS fuera de alcance por decisión ya tomada de Irving).

## Verificación end-to-end realizada (lo que este item sí necesitaba)

Se corrió un build real (`bash deploy/circuito/npm-build.sh`, modo dev, ~1.2–2.2 min de compilación
según la corrida) con un monitor en paralelo muestreando cada ~0.1s el inodo y tamaño del
`public/js/app.js` **vivo**:

- 569 muestras a lo largo de 79 segundos de build: el `app.js` vivo mantuvo el mismo inodo y
  tamaño durante **todo** el compile — cero estados `MISSING` o de tamaño parcial.
- El cambio de inodo (el swap real) ocurrió en una única transición no observable entre dos
  muestras consecutivas (sub-100ms), confirmando que la ventana de inconsistencia se redujo de los
  "varios segundos de escritura de webpack" del bug original a un puñado de syscalls, tal como
  preveía la Opción 1 aprobada por Irving.
- Sin residuos de `public/build-staging-*` tras cada corrida.

## Regresión real encontrada durante la verificación (y corregida en esta rama)

El swap ya mergeado hacía `mv -T "$STAGE_DIR/js" public/js` — **reemplazo del directorio
`public/js` completo**. `public/js/` también contiene vendor estáticos **trackeados en git y NO
producidos por webpack** (`driver.min.js`, `maps.js`, `qrcode.min.js`; ver el propio comentario de
`.gitignore`: "Se mantienen trackeados los vendor estáticos"). `driver.min.js` se carga en
`app/Modules/Core/Layout/views/master.blade.php` (TODAS las páginas del admin) y `qrcode.min.js` en
`app/Modules/Addons/MegaFamilia/views/descargar/index.blade.php`.

Confirmado con un build real: tras el swap de directorio completo, `git status` mostraba los 3
archivos como **borrados** — cada rebuild disparado por un merge (`MergeRunner::triggerRebuildAsync`)
habría dejado el layout principal sirviendo un 404 para `driver.min.js` hasta que alguien los
restaurara a mano desde git. Esto es más grave que el bug original que #9990491 cerró.

### Fix aplicado (`deploy/circuito/npm-build.sh`, commit `78836790`)

1. **Swap archivo por archivo** en vez de reemplazo de directorio: `find "$STAGE_DIR/js"
   -mindepth 1 -maxdepth 1 -exec mv -f -t public/js {} +`. Cada `mv` sigue siendo un `rename()`
   atómico (mismo filesystem) — lo que le importa a una carga en curso es que cada archivo
   individual nunca se sirva a medio escribir, no que el directorio cambie en un solo syscall. Los
   archivos que webpack no produce (los 3 vendor estáticos) nunca se tocan.
2. **Guard de completitud**: la condición de éxito pasó de `[ -d "$STAGE_DIR/js" ]` (el directorio
   existe) a `[ -f "$STAGE_DIR/js/app.js" ]` (el artefacto real existe) — más fiel al "verificar que
   el staging tiene los artefactos completos" que pedía el item.
3. **RC=0 con artefacto faltante ya no se traga en silencio**: nueva rama `elif` que loguea el caso
   y fuerza `RC=1`, cumpliendo el "si falta alguno, tratar como build fallido" del spec.

Verificado tras el fix con 2 builds reales adicionales: `driver.min.js`/`maps.js`/`qrcode.min.js`
mantienen su mismo inodo y tamaño (sin tocar) en ambas corridas; `app.js` se swapea correctamente
(nuevo inodo, mismo contenido esperado); sin residuos de staging. Simulación aislada del caso
"RC=0 sin artefacto" confirma `RC` final `1` y el bundle vivo intacto.

## Fuera de alcance (sin cambio aquí, a propósito)

- **CSS**: sigue sin generalizarse el swap atómico (decisión ya tomada por Irving en #9990491 —
  CSS no era la causa del bug reportado).
- **PROD** (`RemoteDeployCommand::npm_build`): frontera dura de prod, ya escalado aparte como
  sub-item hermano **#9990571** ("requiere_irving") — no se toca en esta rama.
- **Nombres de chunks dinámicos sin patrón de `.gitignore`** (`var_www_megaisp_node_modules_*`,
  generados por imports dinámicos de `canvg`/`dompurify`): quedan como archivos sin trackear tras
  cada build local (`git status` los lista como `??`). No es una regresión de este fix — ya
  ocurría antes — y es cosmético (no rompe nada, sólo ensucia `git status`); se deja anotado como
  posible mejora futura de `.gitignore`, no se tocó aquí por no ampliar el alcance de esta rama.

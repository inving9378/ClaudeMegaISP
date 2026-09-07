# Item #9990569 — Build atómico Fase 1 (parametrizar Mix a staging) — RESUELTO por #9990491 (premisa superada)

## Qué pedía el item

Sub-item de seguimiento de #9990491 (creado 2026-09-07 17:24). Planteaba una Fase 1 aislada:
parametrizar `webpack.mix.js` con `mix.setPublicPath(process.env.MIX_PUBLIC_PATH || 'public')`
para que un directorio de staging completo (js+chunks, css, mix-manifest.json, incluido el
`mix.copy` de chart.js) recibiera la salida del build sin tocar `public/js`/`public/css`/
`public/mix-manifest.json` reales — dejando el swap atómico como "Fase 2, item hermano" a
resolver después.

## Qué pasó

Una sesión previa (`wt-1`, 2026-09-07 17:31–17:40) ya había implementado esa Fase 1 en la rama
`circuito/item-9990569-build-atomico-fase-1-parametrizar-mix` (3 commits: `ab2a20a8`, `fe70b96a`,
`405f1d92` — `MIX_PUBLIC_PATH`, staging por PID en `npm-build.sh`, y el ajuste de `mix.copy` de
chart.js dentro del mismo publicPath tras verificar un ENOENT real). El proceso murió a media
vuelta sin integrar (`claim_liberado_al_morir_la_vuelta` ×2 en el log).

Mientras esa rama esperaba, el propio item padre **#9990491** ("Fase 2, item hermano") se trabajó
en paralelo y se mergeó a `main` (commit `0d9f28e4`, "Rebuild atómico del bundle JS: swap sólo
tras compilar OK") con una solución **completa y distinta**, no solo la Fase 2 planeada:

- Staging del JS **dentro** de `public/` vía `MIX_JS_STAGE_DIR` (no un árbol externo con
  `MIX_PUBLIC_PATH`).
- `deploy/circuito/npm-build.sh` hace el **swap atómico** (`mv` en el mismo filesystem) de
  `public/js` + corrección del manifest (`sed`) **solo si el build terminó con éxito**; si falla,
  el bundle vivo queda intacto.
- Alcance explícitamente acotado a JS+manifest (decisión ya tomada por Irving, documentada en el
  propio commit): CSS/imágenes no eran la causa del bug reportado y no se tocan.

Es decir: #9990491 no dejó pendiente ninguna "Fase 2" — implementó staging+swap end-to-end en un
solo commit, con un mecanismo de env var distinto (`MIX_JS_STAGE_DIR`) al que planeaba esta Fase 1
(`MIX_PUBLIC_PATH`).

## Por qué no se integró la rama de #9990569

Los dos mecanismos son incompatibles si conviven: si se integran los 3 commits de #9990569 tal
cual, `webpack.mix.js` volvería a escribir el JS usando `MIX_PUBLIC_PATH` (o al default `public/js`
si esa env no está seteada), y `npm-build.sh` dejaría de exportar `MIX_JS_STAGE_DIR` con el nombre
que el `mix.js()` mergeado por #9990491 espera — el swap atómico ya en producción **dejaría de
dispararse**, regresando el bug original que #9990491 acababa de cerrar. Es un caso real de
anti-ping-pong (un item deshaciendo en sentido opuesto lo que otro ya integró), consultado con el
supervisor del circuito (Thomas) antes de tocar nada:

> PROCEDE con la opción recomendada: cerrar #9990569 como resuelto/premisa superada por #9990491
> (que ya implementó staging+swap end-to-end para JS+manifest); no integrar el branch; dejar CSS
> fuera de alcance porque Irving ya decidió eso en #9990491.

La rama original con los 3 commits se conservó (renombrada a
`circuito/item-9990569-build-atomico-fase-1-parametrizar-mix-superseded`) sin mergear, por si en
el futuro se decide generalizar el swap atómico a CSS/manifest completo usando ese enfoque como
punto de partida.

## Verificado

- `main` (commit `0817b99b`, tip actual) ya trae `0d9f28e4` con el swap atómico funcionando para
  JS+manifest.
- La rama superseded (`ab2a20a8`..`405f1d92`) queda intacta y sin mergear, sin afectar `main`.
- Sin cambio de código de negocio en este cierre — es documental, siguiendo el mismo patrón que
  otros items "RESUELTO — premisa incorrecta/superada" del roadmap (ver CLAUDE.md, items #75,
  #103, #123, #9990003, #9990353).

## Pendiente (fuera de alcance de este item)

Generalizar el swap atómico a CSS (hoy explícitamente fuera de alcance por decisión de Irving en
#9990491, porque CSS no era la causa del bug reportado) queda como posible mejora futura, no
como deuda urgente.

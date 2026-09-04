# Item #138 — "SPA: prefetch on hover de rutas del sidebar" (RESUELTO — ya implementado)

## Lo que pedía el item

> Al hacer hover sobre un enlace del sidebar, iniciar el fetch de esa página en segundo plano
> (prefetch). Si el usuario hace click antes de que termine, reutilizar la respuesta ya en vuelo.
> Reduce latencia percibida en navegación SPA a ~0ms en conexiones lentas. Implementar con
> debounce de 100ms para no saturar el servidor.
> _(Origen: cierre del refactor SPA, Paso 3B-3 — 10/jun/2026.)_

## Lo que hay realmente en el código (dev, 2026-08-28)

La funcionalidad **ya está construida, íntegra y en `main`**, del 2026-07-15 — commit `d87747e5`
("feat(spa-nav): prefetch on hover de rutas del sidebar (#138)"), integrado a main por el propio
circuito en el merge `ffe4b150` ("Integra circuito #138 (circuito/item-138-...) a main"). Ambos
commits referencian este mismo item (#138) por número, título y contenido — no es un feature
parecido, es literalmente este item, ya cerrado una vez.

Verificado en `resources/js/spa-nav.js` (líneas 50-107 + consumo en `spaNavigate` líneas 199-203):

- **Hover con debounce de 100ms**: `handleSidebarHover` escucha `mouseover` delegado sobre
  `#sidebar-menu a[href]`, filtra links no navegables (`isPrefetchableLink`: sin `#`, sin
  `javascript:`/`mailto:`/`tel:`, sin `target`/`download`, mismo origen) y dispara
  `prefetchUrl(link.href)` tras 100ms si el cursor sigue sobre el mismo link (`hoverHref` evita
  reiniciar el timer en cada micro-movimiento sobre el mismo enlace).
- **Fetch en segundo plano + cache corto**: `prefetchUrl` hace `fetch()` con
  `X-Requested-With: XMLHttpRequest` (igual que la navegación real) y guarda la `Promise` en
  `prefetchCache` (`Map<path, {promise, expires}>`) con TTL de 15s. Respeta la blacklist SPA
  (`SPA_BLACKLIST`) y no prefetch-ea la ruta actual.
  - Login/CSRF: usa `credentials: 'same-origin'` — reusa la sesión activa, no hay client-side
    request forjado ni fuga de datos entre usuarios (el fetch corre en el navegador del usuario
    ya autenticado).
- **Reutilización en el click**: `spaNavigate()` (paso 1) busca el path en `prefetchCache`; si
  está, reutiliza esa `Promise` en vez de repetir el `fetch`, y la borra del cache al consumirla
  (nunca sirve una respuesta stale en un segundo click a la misma ruta).
- **Fallos no rompen nada**: un prefetch fallido (`catch`) borra la entrada del cache en vez de
  cachear el error; si el click llega y no hay prefetch disponible, `spaNavigate` cae al `fetch`
  normal — el mecanismo es puramente una optimización, nunca un requisito.
- Registrado en el bundle: `resources/js/app.js:1000` → `import './spa-nav';`.

Esto cubre 1:1 los cuatro requisitos del item: prefetch en hover, reutilización si el click llega
antes de que termine, debounce de 100ms, y reducción de latencia percibida en la navegación SPA.

## Por qué volvió a aparecer en la bandeja

El item #138 se re-triajeó (#419), se re-aprobó tras un destrabe (2026-08-26) y Irving lo
re-aprobó el 2026-08-28 — todo eso sin que el propio roadmap detectara que ya tenía un commit y
un merge asociados desde julio. No hay evidencia de que el código se haya revertido: el commit
`d87747e5` sigue siendo ancestro de `HEAD` (`git merge-base --is-ancestor d87747e5 HEAD` → true) y
el contenido actual de `spa-nav.js` coincide exactamente con lo que ese commit introdujo. Es un
duplicado de tracking en la Hoja de Ruta, no una regresión de código.

## Cambio en esta vuelta

Ninguno — no hay nada que construir ni que arreglar. Se cierra el item documentando el hallazgo
para que no vuelva a generar trabajo fantasma.

## Dónde verlo

Cualquier pantalla con el sidebar cargado: pasar el cursor sobre un enlace del menú lateral
(`#sidebar-menu`) sin hacer click — a los ~100ms se dispara una petición de fondo visible en la
pestaña Network del navegador (Chrome DevTools) con el mismo path del link. Si se hace click
sobre ese mismo link enseguida, la navegación se resuelve sin repetir el fetch.

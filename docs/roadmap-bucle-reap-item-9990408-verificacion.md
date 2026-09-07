# Item #9990408 — MR-12 UI panel de unión de hilos: bucle reap sobre paraguas ya descompuesto (con re-apertura espuria del guard)

## Contexto

`#9990408` ("MR-12 UI: panel de unión de hilos (doble clic en Rack/Mufa/NAP)") es la misma
familia de bug documentada repetidamente en `CLAUDE.md` (#738, #745, #830, #816, #818, #848,
#852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936, entre otros): un item se
descompone correctamente en sub-items, pero el intento de cierre que dispara el guard de
paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS") nunca se ejecuta, así que el item se queda
`en_progreso` colgado hasta que el reaper lo re-encola y el pool lo vuelve a repartir sin que
haya trabajo propio que hacer.

## Lo que ya se hizo bien (sesión previa, 2026-09-07 10:43-10:44)

Una vuelta anterior descompuso correctamente el trabajo en dos sub-items directos
(`origen_item_id=9990408`):

- **#9990501** — "Fase A: EmpalmesController + rutas" (backend) → `completado`, merge
  `d8579fb6`.
- **#9990502** — "Fase B: doble clic + modal Quasar en LeafletMapRed.vue" (frontend) → quedó
  ella misma como paraguas de dos nietos.

Esa misma vuelta también mergeó a la propia rama de #9990408 los componentes UI compartidos
(`ElementSidePanel.vue`, `EmpalmesPanel.vue`, `helper/empalmes-request.js` — commit `e5d2c8b4`,
507 líneas) antes de repartir el resto del trabajo entre los dos hijos. Sí intentó el cierre:
el log muestra `paraguas_abierto` a las 10:44:04 y `excluir_pool_automatico=true` quedó puesto
correctamente.

## La variante nueva: el guard fue desatendido por un mecanismo distinto

A diferencia de los precedentes (donde el problema era que el cierre-intento **nunca** se
hacía), aquí el cierre-intento se hizo bien y el paraguas quedó parqueado correctamente. Pero
8 minutos después (10:52:04), `jarvis-ya-decidido` procesó un brief pendiente del propio
`#9990408` ("Brief completamente respondido: la decisión ya estaba tomada y el item seguía
retenido sin que faltara nadie") y lo devolvió a `aprobado_revisor`; el scheduler, al
despachar, limpió `excluir_pool_automatico` (log índice 226, `antes:1 → despues:null`). El
pool volvió a repartir #9990408 — a esta terminal — sin que hubiera trabajo propio pendiente:
el trabajo real seguía (y sigue) en la descomposición ya hecha.

Este comportamiento (un brief de un item que YA es paraguas termina limpiando su bandera de
exclusión) no está cubierto por ninguno de los escritos previos de esta familia y podría
repetirse en otros paraguas con un brief pendiente. Se deja anotado aquí como observación —
**no se investiga ni se corrige en este item** (es un bug de la maquinaria del propio circuito,
fuera del alcance de "MR-12 UI panel de unión de hilos"; seguir el mismo criterio que items
como #924, que sí lo tomaron como su propio objeto de investigación cuando correspondía).

## Verificación del estado real (reconfirmado en esta vuelta)

```
9990408 (yo)  | en_progreso            | excl=false | sid=wt-1
  9990501     | completado             | merge=d8579fb6 (Fase A: EmpalmesController)
  9990502     | aprobado_irving        | excl=true  | merge=13b3c947 (paraguas de:)
    9990520   | completado             | merge=bf860bb5 (Fase B1: EmpalmeConfigDialog.vue + Mufa/NAP)
    9990521   | aprobado_revisor       | sin reclamar (Fase B2: enganche en Rack/RackConfiguration.vue)
```

`#9990520` (Fase B1 — componente `EmpalmeConfigDialog.vue` + enganche en Mufa/NAP) ya cerró y
mergeó **durante esta misma vuelta** (mientras se investigaba el estado del árbol). `#9990521`
(Fase B2 — enganche del panel en Rack/`RackConfiguration.vue`) sigue `aprobado_revisor`, sin
reclamar. Mientras exista, `#9990502` sigue siendo paraguas abierto, y por transitividad
`#9990408` también.

## Corrección aplicada

Se ejecuta el intento de cierre faltante sobre `#9990408` (`estado_aprobacion='completado'`).
El guard de paraguas del modelo lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true`
(vuelve a sacarlo del pool/reaper), y el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo completará solo cuando `#9990502` cierre — lo cual, a su vez,
depende de que `#9990521` cierre.

**Sin cambio de código de negocio.** El trabajo real de UI del panel de unión de hilos sigue
en `#9990521` (única pieza pendiente: enganche en Rack), listo para que una terminal lo
reclame.

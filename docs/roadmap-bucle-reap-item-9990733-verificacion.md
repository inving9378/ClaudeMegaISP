# Item #9990733 — Mapa de Red: flujo animado de puntos en los enlaces OLT — bucle reap sobre paraguas ya descompuesto

## Resumen

Mismo patrón documentado repetidamente en `CLAUDE.md` (familia de items #738, #745, #830, #816,
#818, #848, #852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936, #9990408, #962,
#9990554, #9990549, #9990624, #9990650): un item se descompone correctamente en sub-items, pero la
vuelta que lo hizo termina (muere/timeoutea) **sin intentar cerrar al padre** — y sin ese intento el
guard de paraguas de `RoadmapItem.php` nunca dispara, así que el item queda `en_progreso`/
`aprobado_irving` sin `excluir_pool_automatico`, y el pool/reaper lo vuelve a repartir una y otra vez
sin que quede trabajo propio por hacer.

## Línea de tiempo real (BD)

1. **2026-09-10 16:30–16:44** — Una vuelta anterior en `wt-2` hizo el PASO 0 (auditoría de solo
   lectura, exigida por el propio prompt del item antes de escribir nada) y dejó registrada la
   decisión en `comentarios_claude`:
   - El mapa real del módulo MAPA DE RED es `resources/js/components/module/mapared/LeafletMapRed.vue`
     (Leaflet geográfico, **no** un SVG topológico propio).
   - `map.options.preferCanvas` **no está seteado** → el renderer SVG de Leaflet ya es el default;
     la pregunta `q4` del item ("¿qué hacer si el mapa usa canvas?") queda resuelta sin cambio de
     código: no hace falta forzar `L.svg()`.
   - El estado de cada ONT se lee hoy del campo **`signal`** (no `rx_power`) vía
     `OLTsService`/`OLTsOnuController::getSignalAndStatus`, cacheado en la tabla `olt_onus` +
     sincronización masiva cada 10 min (`smartolt:sync-critical`).
   - El mapa dibuja hoy **3084 polylines tipo `route`** en `mapared_layers` — son enlaces
     **genéricos de infraestructura**, sin asociación a cliente/ONT. `mapared_enlaces_servicio`
     (la tabla que sí ligaría NAP→ONT→cliente) sigue en **0 filas**, hallazgo ya documentado por
     separado en los items #9990496/#963 (MR-16/MR-26).
   - Se creó el commit `91f51cf8` (`feat(mapared): hoja de estilos del efecto enlace-vivo`) con la
     hoja de estilos CSS completa del efecto (clases `.enlace-fibra`, estados
     `est-ok/est-degradado/est-critico/est-caido`, `troncal/derivacion`, `rev`, guardas
     `.sin-animar`/`.congelado`, `prefers-reduced-motion`) en la rama propia del item
     `circuito/item-9990733-mapa-de-red-flujo-animado-de-puntos-en`. Aún sin wiring a ningún dato.
2. Un ciclo **DES-TRABE (Opus)** marcó el item como "ejecutor no pudo" (anti-loop: ya se había
   re-escalado 1 vez sin ejecutar) y lo mandó a la bandeja de Irving.
3. **2026-09-10 18:02** — Irving aprobó el item completo, con las 5 preguntas estructuradas
   resueltas (verificado por hash `sha1(texto)[:16]` contra cada `opcion_elegida`: **las 5**
   corresponden a la Opción 1 — la recomendada — de cada pregunta):
   - q1: rollout piloto en 1 OLT/NAP detrás de un feature flag OFF por default.
   - q2: fuente de potencia óptica = último valor cacheado en BD (poll ya existente de ONTs),
     refresco 30–60s vía endpoint de lectura — sin consultas nuevas en vivo a la OLT.
   - q3: umbrales dBm = los mismos que ya usa Torre de Control V2 (reutilizar constantes).
   - q4: si el mapa usara canvas, forzar `preferCanvas:false` solo en Mapa de Red (aditivo) — en
     la práctica resultó ser un no-op porque el mapa ya usa SVG por default (punto 1 arriba).
   - q5: guardas de rendimiento combo — feature flag global, límite de enlaces animados con
     degradación, pausa por `visibilitychange`, viewport culling.
4. **2026-09-10 18:09** — Esa misma vuelta (`wt-2`) **ya descompuso correctamente** el trabajo
   real en 4 sub-items (`origen_item_id=9990733`), cada uno con spec detallado (archivos/líneas
   exactas, decisiones ya tomadas, y qué falta reportar/decidir):
   - **#9990739** — Fase 1: wiring del efecto en el NAP piloto tras el feature flag (toca
     `resources/js/components/module/mapared/helper/mapUtils.js`, `createLayerFromObject()` y
     `updateLayerFromObject()`; decide el mecanismo de feature flag).
   - **#9990740** — Fase 2: los 4 estados simulados a mano sobre el mismo NAP piloto (depende de
     que la Fase 1 esté mergeada).
   - **#9990741** — Fase 3: guardas de rendimiento (viewport culling, zoom mínimo, pausa por
     `visibilitychange`, botón "congelar animación") sobre `LeafletMapRed.vue` (depende de Fase 1).
   - **#9990742** — Fase 4: conectar a la potencia óptica real — con instrucción explícita de
     **verificar primero** si `mapared_enlaces_servicio` sigue en 0 filas (si sigue así, reportar
     bloqueo real en vez de simular datos falsos como reales; depende de Fases 1-3).
5. Pero esa vuelta **nunca intentó cerrar** el padre — el log solo registra, a las **18:09:43**,
   `soltar-claim` ("La vuelta de wt-2 terminó sin cerrar el item... se libera el reclamo y vuelve a
   la cola como `aprobado_irving`"). El pool la volvió a repartir (otra vez a `wt-2`) sin que
   quedara trabajo propio de #9990733 por hacer — mismo síntoma que toda la familia de items
   listada arriba.

## Verificación de esta vuelta

- Los 4 hijos (`origen_item_id=9990733`) siguen intactos: `#9990739` `aprobado_revisor`,
  `#9990740`/`#9990741` `pendiente_revision`, `#9990742` `pendiente_revision`. Ninguno tiene
  `worker_sid` — nadie los reclamó todavía. La descomposición original seguía siendo correcta;
  nadie más la tocó.
- El commit `91f51cf8` (hoja de estilos del efecto) seguía en la rama propia del item, sin
  mergear a `main` (`git merge-base --is-ancestor 91f51cf8 main` → falso antes de esta vuelta).
  Es CSS puro y aditivo (67 líneas nuevas + 3 de import en `app.scss`), sin ningún selector que
  hoy reciba esas clases — inerte hasta que la Fase 1 (#9990739) haga el wiring en
  `mapUtils.js`. Se rebasó sin conflicto sobre el `main` actual y se aprovechó la propia rama del
  item para dejar la documentación de este hallazgo, evitando que quede huérfana.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'` sobre #9990733). El
guard de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS", ~líneas 348-379) lo detectó y
reenrutó:

```
[2026-09-10T18:13:09] por=paraguas evento=paraguas_abierto
  "Este item se descompuso y le quedan 4 sub-item(s) abierto(s): no se completa.
   Queda como paraguas y cierra solo cuando el último de ellos cierre."
```

Resultado: `estado_aprobacion=aprobado_irving`, `excluir_pool_automatico=true`,
`worker_sid=null`, `claimed_at=null`. Queda fuera del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo, cuando #9990739, #9990740, #9990741 y
#9990742 cierren los cuatro.

## Sin cambio de código de negocio

El trabajo técnico real del efecto (wiring del feature flag, los 4 estados, las guardas de
rendimiento y la conexión a la potencia óptica real) sigue en #9990739/#9990740/#9990741/#9990742,
pendientes de que una terminal los reclame. Este item (#9990733) sólo aporta, en su propia rama,
la hoja de estilos ya commiteada por la sesión anterior (`91f51cf8`) — inerte hasta que #9990739
la conecte — más esta documentación del bucle.

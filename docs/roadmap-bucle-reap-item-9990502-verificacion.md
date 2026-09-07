# Item #9990502 — bucle reap sobre paraguas ya descompuesto (MR-12 Fase B: doble clic + modal Quasar)

## Contexto

`#9990502` ("MR-12 UI panel de unión de hilos — Fase B: doble clic + modal Quasar en
LeafletMapRed.vue") es un sub-item de seguimiento de `#9990408`. Tras varias vueltas atascadas
(2 timeouts por max-turns sin commits, 6 ciclos de "límite de cuenta detectado" seguidos, un
`soltar-claim` de `wt-5` por muerte de proceso) e Irving aprobándolo con las 3 respuestas de la
pregunta estructurada (q1=modal Quasar, q2=solo UI+estado local en esta fase, q3=validaciones
mínimas), el propio log del item muestra que una vuelta previa (`wt-5`, 2026-09-07 10:46) ya hizo
lo correcto:

1. Corrió `circuito:cabida` → **NO CABE** (`ya_timeouteo_antes`).
2. Investigó el punto de enganche real en `LeafletMapRed.vue` (~línea 1609,
   `dialogs.value[\`${o.dialog}_config\`]`) y confirmó que "Rack" es un caso más complejo
   (`RackConfiguration.vue` ya maneja conexión de puertos de `MapaRedDevice`, dominio distinto a
   hilos/empalmes) que Mufa/NAP (marcadores de mapa reales, mismo handler `dblclick` genérico).
3. Descompuso el trabajo en 2 fases:
   - **#9990520** — Fase B1: componente `EmpalmeConfigDialog.vue` (panel de unión de hilos) +
     enganche en Mufa/NAP.
   - **#9990521** — Fase B2: enganche del panel en Rack (`cupboard`/`RackConfiguration.vue`).
4. Dejó la nota de decisión en `comentarios_claude`, evitando que el próximo ejecutor repitiera la
   exploración.

Pero el proceso **murió antes de intentar el cierre** del padre: el log registra a las `10:46:09`
el evento `claim_liberado_al_morir_la_vuelta` (`soltar-claim`, sid `wt-5`) — "La vuelta de wt-5
terminó sin cerrar el item (muerte del proceso: kill, OOM o freno a media vuelta)" — el claim se
liberó y el item volvió a `aprobado_revisor` sin que nadie hubiera intentado cerrarlo. Entre medio
hubo además 6 ciclos de `limite_cuenta_detectado` que devolvieron el item a `aprobado_revisor` sin
contarlo como timeout. El pool lo repartió de nuevo (a `wt-1`, esta vuelta) sin que hubiera trabajo
propio que hacer: la misma familia de bug ya documentada en
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#852`/`#905`/`#878`/`#906`/`#907`/`#924`/`#9990012`/
`#917`/`#910`/`#936`/`#9990422`/`#9990412`/`#9990484`/`#9990507`/`#9990508` (y otros).

## Verificación

Consultados los 2 hijos (`origen_item_id=9990502`) directo en BD:

| Item | Título | Estado | Worker |
|------|--------|--------|--------|
| #9990520 | Fase B1 — Componente `EmpalmeConfigDialog.vue` + enganche en Mufa/NAP | `en_progreso` | `wt-4` (activo) |
| #9990521 | Fase B2 — Enganche del panel en Rack (`cupboard`/`RackConfiguration.vue`), depende de B1 | `pendiente_revision` | (sin reclamar) |

Ambos siguen intactos — la descomposición original sigue siendo correcta y completa, nadie más la
tocó ni hace falta re-descomponerla. `#9990520` está siendo trabajado activamente por otra terminal
(`wt-4`) en este mismo instante, así que no se toca (un item = un dueño).

## Corrección

Esta vuelta ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'` vía tinker)
sobre `#9990502`. El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo
reenrutó a `aprobado_irving` + `excluir_pool_automatico=true`, liberando `worker_sid`/`claimed_at`
y dejando en el log tanto el evento `paraguas_abierto` ("le quedan 2 sub-item(s) abierto(s)") como
el cambio de flags, sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491` aprox.) lo complete solo cuando `#9990520` y `#9990521` cierren los dos.

## Resultado

Sin cambio de código de negocio. El trabajo técnico real de MR-12 Fase B (componente
`EmpalmeConfigDialog.vue`, enganche en Mufa/NAP, enganche en Rack) sigue en `#9990520` (en
progreso por `wt-4`) y `#9990521` (`pendiente_revision`, sin reclamar).

# Item #9990882 (CIRC-02, PARAGUAS) — duplicado de #9990856, ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

## Hallazgo

El item #9990882 ("CIRC-02 (PARAGUAS): El comentario de Irving en un item requiere_irving es la
respuesta — hilo que no se pisa + re-encolado automático") es uno de los 11 sub-items
(#9990881-#9990891) materializados el 2026-09-11 desde el documento "CIRC-00 a CIRC-08" que trajo
el item #9990874 ("Items de corrección del Circuito CC — para desatorar el flujo", ver
`docs/bitacora/2026-09-11-item-9990874.md`). Ese mismo documento ya se había materializado
**antes**, por otra vía, como el item **#9990856** — título, `description` y los tres sub-pasos
(diagnóstico → mecanismo → visibilidad) son el mismo texto, solo que #9990856 nació directo
("por": "claude-code (doc CIRC v2 de Irving 2026-09-12)", `origen_item_id=NULL`) 20 minutos antes
que #9990882 (`origen_item_id=9990874`). Mismo patrón que ya se resolvió para **#9990881** (CIRC-01,
duplicado de #9990855) y **#9990889** (CIRC-06, duplicado de #9990863) — ver
`docs/circuito-terminales-ociosas-duplicado-item-9990889-verificacion.md`.

`#9990874` ya decidió deliberadamente crear CIRC-02 como paraguas real ("para que su propio ciclo
de vida —guard de paraguas + cierre en cascada— sea el que ya usa el resto del circuito"), así
que #9990882 nació **con sus propios 3 hijos ya creados** (`origen_item_id=9990882`), no por un
`circuito:cabida` propio:

| CIRC | Hijo de #9990856 (original) | Hijo de #9990882 (este item, duplicado) |
|------|------------------------------|-------------------------------------------|
| 02a — Diagnóstico (solo lectura) | **#9990857** — `completado`, merge `2c23bad4` | **#9990883** — `completado`, merge `7a61737663f6412cf6833961beaa61de7a69acee` |
| 02b — Mecanismo (tabla `roadmap_item_respuestas` + re-encolado) | **#9990858** — `en_progreso` (wt-1) | **#9990884** — `requiere_irving`, sin reclamar |
| 02c — Visibilidad (hilo en la Torre + watchdog) | **#9990859** — `aprobado_revisor`, sin reclamar | **#9990885** — `aprobado_revisor`, sin reclamar |

`#9990884`/`#9990885` traen el mismo `description` palabra por palabra que `#9990858`/`#9990859`
(mismo nombre de tabla `roadmap_item_respuestas`, mismo orden de fases, mismo prompt) — confirmado
leyendo ambos pares directo de la BD.

**#9990883 ya fue resuelto por otra terminal durante esta misma sesión** (commit `5baaf864`,
"docs(circuito): documenta que #9990883 (CIRC-02a) es duplicado de #9990857") — cerrado como
`completado` señalando el trabajo real ya hecho en #9990857 (191 candidatos medidos, 0 pérdidas
reales de `comentarios_claude`, 3 puntos de código documentados para la Fase 3), sin repetir el
diagnóstico. Es la confirmación en vivo de que este mismo patrón de "cerrar como duplicado sin
tocar el original" es el correcto aquí.

## Por qué #9990882 se queda en bucle reap

El item nació ya descompuesto (3 hijos existen desde el 2026-09-11 18:35, junto con el propio
padre) pero **ningún proceso anterior intentó cerrarlo** — su `log` solo tenía 2 entradas
(`item_creado` + `destrabe(opus)` reaprobado), sin ningún intento de `estado_aprobacion=completado`.
Mismo síntoma que la larga familia de "bucle reap sobre paraguas ya descompuesto" documentada en
`CLAUDE.md` (#738/#745/#830/#816/.../#9990856 mismo): el guard de paraguas
(`RoadmapItem.php` bloque "(2b) PARAGUAS") solo actúa cuando algo *intenta* cerrar el item, y aquí
nadie lo había intentado — así que el pool lo repartía sin que hubiera ejecución pendiente por
hacer distinta de la que ya cubren sus 3 hijos (que a su vez duplican los hijos de #9990856).

## Resolución

No se crean sub-items nuevos (ya existen y son correctos, están duplicados pero eso no es algo que
yo deba corregir tocando esos 3 items — regla de aislamiento: "un item = un dueño", #9990884 y
#9990885 son items propios que un futuro ejecutor debe cerrar siguiendo el mismo patrón que ya
aplicó la sesión hermana a #9990883, sin que yo los reclame ni edite). Lo único que corresponde a
**este** item (#9990882) es el intento de cierre que faltaba: se ejecuta
`estado_aprobacion='completado'`; el guard de paraguas lo reencauza a `aprobado_irving` +
`excluir_pool_automatico=true` (quedan 2 sub-items abiertos: #9990884 y #9990885) — sacándolo del
pool/reaper, igual que su gemelo #9990856 (que está en el mismo estado, esperando a sus propios
#9990858/#9990859). El hook de cierre en cascada (`RoadmapItem.php:459-491`) lo completará solo
cuando #9990884 y #9990885 cierren — momento en el que, si siguen siendo hijos huérfanos idénticos
a #9990858/#9990859, la vía correcta es la misma que ya se usó con #9990883: cerrarlos como
duplicados apuntando al trabajo real, no reimplementar la Fase 2/3 dos veces.

**Sin cambio de código de aplicación.** El trabajo técnico real de CIRC-02 (tabla
`roadmap_item_respuestas`, disparo de re-encolado, inyección en el prompt, hilo visible en la
Torre + watchdog) sigue en la cadena #9990856 → #9990858 (en_progreso) → #9990859
(aprobado_revisor, sin reclamar).

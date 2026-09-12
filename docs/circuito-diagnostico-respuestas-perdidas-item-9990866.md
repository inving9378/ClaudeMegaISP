# CIRC-02a — Diagnóstico solo-lectura: ¿cuántas respuestas de Irving se han perdido? (item #9990866)

**Alcance:** solo-lectura, tal como pide el spec. No se escribió en BD, no se re-encoló nada, no
se tocó código de aplicación (solo se creó un script temporal en `/tmp`, fuera del repo, ya
borrado). Depende de CIRC-01 (#9990865, `posición 1` de la descomposición de #9990854) —
verificado `completado` y mergeado (`merge_commit 4d40e78823f1...`) antes de arrancar este
diagnóstico.

## Resumen ejecutivo (las tres cifras que pide el criterio de aceptación)

| Métrica | Valor |
|---|---|
| Items que alguna vez llegaron a `requiere_irving` **y** recibieron un comentario/decisión de un actor humano después | **209** |
| Grupo (a) — avanzaron tras la respuesta | **209** |
| Grupo (b) — siguen parados con una respuesta de Irving sin ejecutar | **0** |
| Casos de sobreescritura confirmada de una respuesta humana en `comentarios_claude` | **0** (y estructuralmente casi imposible hoy — ver §4) |

**El hoyo que el item pedía medir hoy está vacío: 0 items.** Esto no es "no se pudo medir", es
el resultado real contra la BD de dev. La sección §5 explica por qué el mecanismo teorizado en la
descripción del item (comentario escrito, nadie cambia el estado) nunca se ha materializado por la
única vía que deja rastro verificable, y dónde queda un punto ciego real que **no se puede medir
retroactivamente** (vale la pena leerlo antes de dar el hoyo por cerrado para siempre).

## Método

Todo el análisis usa **el propio historial `log` (JSON, append-only) de `roadmap_items`**, que es
la única fuente que registra CADA decisión humana con su timestamp, autor y efecto. No se usó
`storage/logs/roadmap-externo-*.log`: esos archivos son por-checkout (el que ve Irving en la Torre
corre en `/var/www/megaisp`, no en este worktree) y no contienen las decisiones reales — se
verificó (`grep` sobre 26 archivos, 2026-07-13→2026-09-11) que **0** llevan `"por":"irving:..."` ni
`decision-irving`; solo tienen actividad de terminales del circuito (`wt-*`, `auditor`). El `log` de
cada item, en cambio, sí quedó completo y es la fuente correcta.

**Quién escribe una decisión humana, y cómo se ve en el log** — `RoadmapController::decidir()`
(`app/Modules/Addons/Roadmap/Controllers/RoadmapController.php:1696`) es el ÚNICO endpoint
autenticado que fija `aprobado_irving`/`rechazado`/`completado`/`cancelado` sobre un item. Todo
usuario con permiso `circuito.decidir` (Irving u otro rol supervisor) queda registrado como
`irving:<login>` (`actorLabel()`/`actor()`, líneas 1681-1686 y 2934-2938) — así que `irving:admin`,
`irving:CARLOS`, `irving:Irving`, `irving:david_marsal` son TODOS la misma vía, distintas cuentas
(cubre el "o rol supervisor" del enunciado). Cada llamada agrega una entrada al `log` con
`ts`, `por`, `decision` (`aprobar|rechazar|cerrar|cancelar|comentar`), `estado` resultante,
`opcion_elegida`, `respuestas` y `comentario`.

Se escanearon los **1,673** items de la tabla. Para cada uno: (1) ¿alguna vez estuvo en
`requiere_irving`? (estado actual, o alguna entrada de `log` con `estado":"requiere_irving"`); (2)
¿tiene alguna entrada con `por` que empieza en `irving:` y trae `decision` o `comentario`?; si sí,
se toma la ÚLTIMA de esas entradas y se revisa si la MISMA acción ya movió el estado fuera de
`requiere_irving`, o si alguna entrada POSTERIOR lo hizo, o si el item sigue HOY en
`requiere_irving` sin que nada lo haya movido.

## 1-2. Los dos grupos

**209 items** entraron alguna vez a `requiere_irving` y después recibieron una decisión humana.
De esos:

- **Grupo (a), 209/209 — avanzaron.** En el 100% de los casos la MISMA llamada a `decidir()` que
  trajo la decisión de Irving **ya cambió el estado** en esa misma escritura (`aprobar` → 207
  casos, `rechazar` → 1, `cerrar` → 1). Ninguno necesitó un segundo paso separado para re-encolar.
  Esto tiene una explicación mecánica simple: `decidir()` no es "solo comentar" — excepto por la
  acción `accion=comentar` (que deja el estado sin tocar a propósito, para permitir anotar sin
  decidir), CUALQUIER acción real (`aprobar`/`rechazar`/`cerrar`/`cancelar`) muta
  `estado_aprobacion` en la misma transacción que graba el comentario (líneas 1787-1826). No hay
  "escribir la decisión" sin "ejecutarla" salvo que se use literalmente `comentar`.
- **Grupo (b), 0 — siguen parados.** Ninguno.

## 3. Grupo (b): cifras

No aplica — 0 items. Antigüedad promedio, más viejo, y trabajo bloqueado detrás: **N/A** (no hay
ningún item hoy con una respuesta humana escrita que nadie haya ejecutado).

## 4. ¿Alguna respuesta de Irving fue sobreescrita en `comentarios_claude` por una vuelta de Claude Code?

**No, y es estructural.** Se auditaron TODOS los puntos del código que escriben
`comentarios_claude` (`grep -rn "comentarios_claude\s*=" app`, 12 sitios):

- **2 sitios hacen reemplazo total** (`$item->comentarios_claude = $data['comentario'];`):
  `RoadmapController.php:1804` (`decidir()`) y `:1969` (`seguimiento()` con `cerrar_origen`).
  **Ambos están gateados por `circuito.decidir` y firman como `irving:<login>` — son las DOS únicas
  vías, y las dos son humanas.**
- **Los otros 10 sitios SIEMPRE concatenan** (`(string) $item->comentarios_claude . $sello`):
  `RevisorService.php` (5 sitios), `MergeRunner.php`, `RoadmapCircuitoService.php` (2),
  `PriorizarSeguridadCommand.php`. Ninguno de estos —que sí son 100% automatizados (revisor,
  merge runner, priorizador)— puede borrar lo que ya había: solo le pegan texto DESPUÉS.

Es decir: **ningún camino automatizado de Claude Code puede sobreescribir un comentario humano**;
solo otra llamada humana a `decidir()`/`seguimiento()` podría hacerlo (Irving reemplazando su
propio comentario anterior, o dos supervisores pisándose). Se buscaron esos casos en la BD real:
solo **1 item de 1,673** (`#9990335`) tiene 2+ comentarios humanos con texto. Se inspeccionó a
mano: el comentario `"opcion 2"` (escrito 2026-09-04 10:47:45) sigue **presente hoy, completo, al
inicio** de `comentarios_claude` — lo que vino después (5 notas automáticas de `jarvis` +
2 cierres de terminal `wt-1`) se **agregó a continuación**, no lo borró. Es exactamente la
evidencia empírica de que el patrón "concatenar, nunca reemplazar" se cumple en un caso real,
no solo en la lectura del código.

## 5. Punto ciego real (para no cerrar el hoyo con falsa certeza)

Existe una TERCERA vía que un humano puede usar para "contestar" un item en `requiere_irving` sin
que quede ningún rastro en `log`: **`RoadmapController::elegirOpcion()`**
(`RoadmapController.php:1893`) solo persiste `opcion_elegida`/`preguntas` y hace `save()` — no
agrega entrada al `log` del item (solo manda una línea a
`Log::channel('roadmap_externo')`, que es un archivo por-checkout, no la BD). Si un humano marca
la opción en la Torre pero nunca da clic en "Aprobar", **no queda ninguna huella recuperable**
después del hecho — ese es precisamente el escenario que el item describe ("Irving escribe la
decisión... nadie re-encoló"), y es el ÚNICO de los tres mecanismos posibles que **no se puede
medir retroactivamente con los datos de hoy**, solo detectar en el momento (item en
`requiere_irving` con `opcion_elegida` no nulo).

Se revisó el estado ACTUAL (único punto donde esto es observable): de los 4 items hoy en
`requiere_irving`, **uno** (`#9990890`) tiene `opcion_elegida` fijado
(`"9da64946005c0d47"`) — pero se confirmó en su `log` que quien lo fijó fue el **autopilot**
(entrada `"por":"autopilot"`, `"respuestas":{"q1":"9da64946005c0d47"}`, 2026-09-11 19:16:48), no
un humano; el item volvió a `requiere_irving` por un timeout posterior, no por inacción de Irving.
No es un caso del grupo (b). Los otros 3 items en `requiere_irving` hoy no tienen `opcion_elegida`.
**Conclusión: hoy, 0 candidatos reales también por esta vía — pero la vía en sí queda sin
instrumentar** (ver recomendación).

**Pista falsa descartada durante la investigación** (documentada para no repetirla): se exploró
`decision_resuelta`/`decision_fecha`/`decision_resumen` como posible proxy de "decisión escrita
sin ejecutar". Resultó **no servir**: de 238 items con `decision_resuelta=true`, 237 lo obtuvieron
del guard de nivel-C-sin-merge (`RoadmapItem.php:341`, no tiene relación con `requiere_irving`) y
solo 1 (`#753`) tiene `decision_fecha` real — pero su `decision_fuente` es
`"verificacion-bd-wt5"`, escrito a mano por una terminal del circuito para su propia
re-verificación (no por el flujo de Irving). El único sitio de código que fija `decision_fuente`
(`RoadmapExternalController.php:466`) siempre escribe `'irving'` literal — ese valor distinto
confirma que fue una escritura directa por tinker, no el flujo real. Campo con semántica mezclada,
no apto como proxy para este diagnóstico.

## Recomendación para CIRC-02b (no se ejecuta aquí — este item es solo-lectura)

El punto ciego real de §5 es la justificación más fuerte para construir CIRC-02b (#9990867,
"hilo de respuestas de Irving + re-encolado automático"): mientras `elegirOpcion()` no deje
rastro en el `log` del item, cualquier "click y me fui sin aprobar" es invisible hasta que alguien
mire la bandeja a mano. Con el hilo dedicado que propone CIRC-02b, este mismo diagnóstico se podría
correr de forma exacta (sin el punto ciego de §5) en cualquier momento futuro.

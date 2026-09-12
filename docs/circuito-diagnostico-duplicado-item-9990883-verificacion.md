# Item #9990883 (CIRC-02a) — duplicado exacto de #9990857, ya resuelto (RESUELTO — sin cambio de código)

## Contexto

`#9990883` ("CIRC-02a: Diagnóstico de solo lectura — cuántas respuestas de Irving se han perdido")
nació como sub-item de `#9990882` ("CIRC-02 (PARAGUAS): El comentario de Irving en un item
`requiere_irving` es la respuesta..."), que a su vez es un **duplicado completo** del paraguas
`#9990856` (mismo título, mismo `prompt`, creado antes). La tripleta de sub-items de `#9990882` es
un espejo exacto de la de `#9990856`:

| Duplicado (`#9990882` → …) | Original (`#9990856` → …) | Original — estado |
|---|---|---|
| `#9990883` CIRC-02a (este item) | `#9990857` CIRC-02a | `completado` |
| `#9990884` CIRC-02b | `#9990858` CIRC-02b | `en_progreso` (otro dueño, no se toca — #341) |
| `#9990885` CIRC-02c | `#9990859` CIRC-02c | `aprobado_revisor` (sin reclamar) |

Mismo patrón ya documentado repetidas veces en este repo para pares de items gemelos que nacen del
mismo documento origen (ver `docs/circuito-timeout-escalado-duplicado-item-9990881-verificacion.md`,
`docs/inventario-seguimiento-218-item-733-verificacion.md`, entre otros): el generador de items
creó dos veces el mismo trabajo, y uno de los dos gemelos avanzó primero.

`circuito:cabida 9990883` devolvió NO CABE (`historico_excede_umbral`, ETA histórico ~510s). Antes
de descomponer el prompt en sub-items nuevos se verificó si el trabajo ya existía — y sí: el
diagnóstico que pide este item **ya se hizo, en dos fases, y ya está mergeado a `main`**. No hay
nada que descomponer: la descomposición sería re-crear exactamente los mismos dos sub-items que ya
existen y cerraron (`#9990872` Fase 1, `#9990873` Fase 2), duplicando trabajo por segunda vez.

## Verificado — el diagnóstico ya existe y responde exactamente lo que pide el prompt de #9990883

`#9990857` (`completado`, merge `2c23bad4`) se descompuso en:

- **`#9990872`** (Fase 1, `completado`, merge `b7d6ed97`) — doc `docs/circuito-item-9990857-fase1-datos.md`:
  clasificó el universo completo de items con log no vacío (1573 en su corrida) en candidatos
  (`requiere_irving` + comentario humano posterior), grupo (a) avanzaron y grupo (b) siguen
  parados. Resultado: **191 candidatos, 191 en grupo (a), 0 en grupo (b)**.
- **`#9990873`** (Fase 2, `completado`, merge `ba6aabb7`) — doc
  `docs/circuito-item-9990857-diagnostico-respuestas-perdidas.md`: usa la Fase 1 como insumo (sin
  re-derivarla), investiga el punto 4 del método (sobreescritura de `comentarios_claude`) y cierra
  las tres cifras pedidas por el prompt original:
  - Items en grupo (b): **0**.
  - Tiempo promedio / caso más viejo parado: **no aplica** (0 casos).
  - Sobreescritura de `comentarios_claude`: **0 pérdidas reales confirmadas** en toda la historia
    del roadmap; sí se identificaron **3 puntos de código** que reemplazan el campo en vez de
    acumularlo (`RoadmapController.php:1762`, `:1927`, `RoadmapCircuitoService::applyWrite():1182`),
    con recomendación de blindaje documentada para una eventual Fase 3 (no ejecutada, por diseño:
    el spec de #9990857 pedía "reportar en Fase 2, remediar en Fase 3", que ninguno de los dos
    gemelos crea).

El prompt de `#9990883` pide **textualmente los mismos 5 pasos** que ya resolvió esta cadena: listar
items `requiere_irving` con comentario humano posterior, separar avanzaron vs. siguen parados,
cuantificar el grupo (b), revisar sobreescritura de `comentarios_claude`, y entregar un reporte en
`docs/` con las tres cifras. Ya está hecho.

## Recheck de frescura (2026-09-11, esta vuelta) — la conclusión sigue vigente

Como la Fase 2 corrió ~2 horas antes que esta vuelta, se repitió una clasificación ligera (mismo
criterio: item en `requiere_irving` con comentario de actor humano posterior en su `log`, ¿avanzó
después o sigue ahí?) sobre el universo actual (1594 items con log, vs. 1589 de la Fase 2 — 5 items
nuevos creados en el intervalo). Resultado: **192 candidatos, 191 avanzaron, 1 "parado" según el
`log` estructurado** — `#9990864` ("Bug de vista: la pestaña Hoja de ruta muestra 0/0/0/0...").

Investigado a mano — **no es un caso nuevo de grupo (b)**, y confirma además un matiz que la
Fase 1/2 no necesitaron cubrir porque no se les presentó: el campo `log` (JSON estructurado) y el
campo `comentarios_claude` (texto libre) **no siempre se actualizan juntos**. En `#9990864`:

1. `irving:admin` aprobó (`estado=aprobado_irving`) a las 18:28:08 — este es el comentario humano
   que mi recheck detectó, y en ese mismo instante el `log` YA registra el avance (`estado` cambia
   en la misma entrada) → correctamente contado como "avanzó" por la Fase 1/2, que corrieron antes
   de lo que sigue.
2. A las 18:36, un ejecutor (`wt-2`) tomó la aprobación, encontró que aplicarla al pie de la letra
   **reventaría una decisión de diseño deliberada** (el `scope backlog()` fue redefinido a propósito
   en el commit `34bde257` para que un item tomado desaparezca de la vista "Todos") y usó el
   mecanismo de `consulta`/escalación para volver a poner el item en `requiere_irving` con una
   pregunta NUEVA ("spec_contradictorio"). Ese evento **solo quedó en `comentarios_claude`** (dos
   líneas `[wt-2 · consulta]` / `[jarvis · escalacion]`) — **no generó una entrada nueva en el
   arreglo `log` con un campo `estado`**, que es justamente lo que mi script (y, por construcción,
   también el de la Fase 1/2) usa para detectar avances.
3. Por eso el item aparece con `estado_aprobacion=requiere_irving` otra vez, pero el `log`
   estructurado sigue mostrando la aprobación de Irving como el último evento — un "falso parado".

**Esto NO es el patrón que busca CIRC-02** ("la respuesta existe y nadie la ejecutó"): la respuesta
de Irving **sí se tomó y se intentó ejecutar**; el ejecutor encontró una contradicción real del
spec y escaló de nuevo, exactamente el mismo patrón de *near-miss* que la Fase 2 ya documentó para
`#9990853` ("el comentario fue reprocesado... y el reintento falló por su propia causa... no es un
comentario ignorado"). Además la escalación es de hace ~6 minutos respecto al arranque de esta
vuelta (18:36 vs. 18:42) — no hay "tiempo parado" que medir, es una pregunta recién nacida.

**Hallazgo colateral (fuera de alcance de este item, anotado para quien retome CIRC-02b/02c):** el
mecanismo de `consulta`/`escalacion` de un ejecutor no dejó rastro en el `log` estructurado del
item, solo en `comentarios_claude`. Cualquier análisis futuro que dependa del `log` para detectar
"¿este item volvió a moverse?" tiene este mismo punto ciego. No se toca código aquí — es
observación para `#9990858`/`#9990884` (mecanismo del hilo de respuestas), que sí construye
infraestructura nueva sobre este flujo.

## Veredicto

**RESUELTO — sin cambio de código.** El diagnóstico que pedía `#9990883` ya está hecho, con las
tres cifras exigidas, y mergeado a `main` bajo `#9990857`/`#9990872`/`#9990873`. El recheck de
frescura de esta vuelta confirma que la conclusión (0 respuestas de Irving perdidas / grupo (b)
vacío) sigue vigente; el único caso que aparentaba ser nuevo (`#9990864`) es un near-miss legítimo,
no una pérdida. No se crean sub-items nuevos ni se duplica el trabajo. Los duplicados hermanos
(`#9990884`/`#9990885`) quedan fuera de alcance de este item — cada uno tiene su propio original
(`#9990858` en progreso, `#9990859` sin reclamar) y su propio dueño eventual.

# Barrido de items sin contenido ejecutable (basura) en la Hoja de Ruta — 2026-09-12

Item: #9991009 (CIRC-04 PASO 2, sub-item de seguimiento de #9990870).

**Alcance:** este documento es SOLO LECTURA. No se borró ni modificó ninguna fila de
`roadmap_items`. Es un listado para que Irving decida item por item.

**Método:** se combinó (a) título corto/sin sentido, (b) `description` NULL o vacía,
(c) `prompt` NULL o vacío, (d) títulos duplicados exactos, (e) títulos con artefactos
de copy/paste (fragmentos markdown pegados literalmente, como `Título:` o `**Título:**`).

Se partió del caso de referencia ya confirmado por Irving (#185, "Más 8tems") y se
buscaron patrones similares en toda la tabla `roadmap_items` (1,580+ filas).

**Hallazgo general:** en este sistema `description` NULL/vacía **no es**, por sí sola,
señal de basura — es normal que el contenido real viva en el campo `prompt` en vez de
`description` (así nacen la mayoría de los items del circuito). Se verificó: **0 items
abiertos tienen description Y prompt simultáneamente vacíos** — no hay "cascarones"
puramente vacíos entre los items activos. La basura real encontrada cae en 5 grupos
distintos, descritos abajo.

---

## Grupo A — Duplicados por creación accidental (mismo prompt, creados en segundos)

Lote del 2026-08-24 17:36–17:41, prompt idéntico con errores de tecleo
("crea ítem **nuevis** para **asignat** trabajo a las terminales"):

| ID | Título | Estado | Nota |
|---|---|---|---|
| #184 | Más 8tems | completado | duplicado 1/3 — ya ejecutado |
| **#185** | Más 8tems | **aprobado_irving (ABIERTO)** | duplicado puro sobrante — el mismo trabajo ya corrió en #184 y #186 |
| #186 | Más 8tems | completado | duplicado 2/3 — ya ejecutado |
| #187 | Más items | completado | duplicado 1/4 — ya ejecutado |
| #188 | Más items | completado | duplicado 2/4 — ya ejecutado |
| #189 | Más items | completado | duplicado 3/4 — ya ejecutado |
| **#190** | Más items | **aprobado_irving (ABIERTO)** | **distinto**: SÍ trae un spec completo ("Barrido de trabajo atorado y realimentación de las terminales") — título genérico, pero NO es basura de contenido |

**Recomendación:** #185 es candidato a `cancelado` (redundante, el trabajo ya se hizo dos
veces). #190 no debería cancelarse — solo re-titularse; su contenido es real y útil.

---

## Grupo B — Artefactos de prueba sin contenido real

| ID | Título | Estado | description | prompt |
|---|---|---|---|---|
| #9990058 | TEST-9990012 padre nivel C | cancelado | NULL | NULL |
| #9990059 | TEST-9990012 hijo | completado | NULL | NULL |

Ambos totalmente vacíos (sin description ni prompt) — parecen haberse creado para
probar el propio mecanismo padre/hijo de nivel C del circuito, no representan trabajo
real. Ya están cerrados (uno cancelado, uno completado); son ruido histórico sin
impacto activo. Se listan por transparencia, no requieren acción.

---

## Grupo C — Títulos con artefactos de copy/paste rotos (contenido real, título ilegible)

| ID | Título actual (tal cual quedó guardado) | Estado |
|---|---|---|
| #921 | `Título: Mapas —` | completado |
| #9990833 | `**Título:** Clientes: consolidar el SN del equipo en un solo campo canónico (normalización ASCII↔hex) y preparar el pase a producción` | completado |
| #9990929 | `CIRC-07 · La pestaña "Hoja de ruta" muestra 0 items habiendo 1,580 Título:   Bug de vista: la pestaña "Hoja de ruta" muestra 0/0/0/0 con el filtro` | cancelado |

Los tres tienen `prompt`/contenido real y completo (con su propia sección de criterios
de aceptación) — el defecto es puramente cosmético: quedó pegado un fragmento markdown
o el título se concatenó con un texto de otra sección. Los tres ya están cerrados
(completado/cancelado), sin urgencia. Se listan porque, tal como quedaron, son
imposibles de encontrar por título en una búsqueda futura.

---

## Grupo D — Título de una sola palabra, contenido real, AÚN ABIERTO

| ID | Título | Estado |
|---|---|---|
| #9990721 | `permisos` | aprobado_irving |

El `prompt` es un spec de auditoría completo y detallado (causa raíz de por qué los
permisos de un rol no llegan al usuario que lo tiene). **No es basura** — el título de
una palabra solo lo hace invisible en cualquier listado o búsqueda. Vale la pena que se
le ponga un título descriptivo antes de que se pierda de vista en la cola.

---

## Grupo E — Título duplicado exacto entre dos "documentos semilla" del propio circuito

| ID | Título | Estado | Creado |
|---|---|---|---|
| #9990853 | `corrección del Circuito CC — para desatorar el flujo` | requiere_irving | 2026-09-11 17:58 |
| #9990854 | `corrección del Circuito CC — para desatorar el flujo` | aprobado_irving | 2026-09-11 18:05 |

No es un duplicado accidental: #9990854 es una "v2" del mismo documento semilla (agrega
la sección CIRC-05 que #9990853 no tiene) — pero comparten título idéntico, lo que
confunde en cualquier listado. Ambos son documentos-semilla largos (>30 KB de prompt)
que describen un lote CIRC-00..CIRC-05 "listo para pegarse como item nuevo" — no
ejecutable por sí mismos, son propuestas de items.

**Observación (sin verificar la relación padre→hijo exacta, solo lo que se ve en la
cola):** ya existen en la Hoja de Ruta items con títulos `CIRC-01` a `CIRC-09`
(`#9990931`, `#9990933`, `#9990934`, `#9990935`, `#9990937`, `#9990928`, `#9990929`)
en distintos estados (varios ya `completado`). Es decir, buena parte de lo que estos
dos documentos-semilla proponían ya tiene su propio item en la cola, con o sin relación
de parentesco declarada. Queda a criterio de Irving si #9990853/#9990854 siguen
aportando algo nuevo (ej. CIRC-05, que solo está en la v2) o si ya cumplieron su
función de "semilla" y pueden cerrarse.

---

## Resumen para decisión de Irving

| Prioridad de revisión | IDs | Acción sugerida |
|---|---|---|
| Alta (redundante, activo) | #185 | Cancelar — duplicado puro ya ejecutado en #184/#186 |
| Media (activo, solo cosmético) | #190, #9990721 | Re-titular, no cancelar — contenido real |
| Media (activo, requiere lectura de #9990853 vs #9990854) | #9990853, #9990854 | Decidir si alguno ya es redundante frente a los CIRC-01..09 existentes |
| Baja (ya cerrados, solo higiene histórica) | #921, #9990833, #9990929, #9990058, #9990059 | Ninguna acción urgente — listados por transparencia |

**No se tocó ninguna fila.** Este documento es el entregable completo del item #9991009.

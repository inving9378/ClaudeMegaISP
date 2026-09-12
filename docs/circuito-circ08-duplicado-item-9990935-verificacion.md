# Item #9990935 — CIRC-08 · Inventario de las ramas sin integrar (RESUELTO — duplicado exacto ya completado)

## Hallazgo

El item **#9990935** ("CIRC-08 · Inventario de las ramas sin integrar (SOLO LECTURA)") pide
exactamente el mismo trabajo, con el mismo prompt de 6 fases (censo, archivos que `main` no
tiene, cuatro cubetas del cruce con el roadmap, la pregunta del guard `verificarCierre`,
entregable `docs/inventario-ramas-sin-integrar-{fecha}.md` y propuesta —sin implementar— del fix
del guard si aplicara), que **ya fue ejecutado de punta a punta y está mergeado en `main`** desde
antes de que esta terminal reclamara #9990935.

Dos auditorías separadas del circuito llegaron a la misma conclusión (161/196 ramas sin
integrar) y crearon el mismo item por caminos distintos — el mismo patrón de carrera de timing
documentado repetidas veces en `CLAUDE.md` (#733/#741/#753/#9990003/#9990353/#9990658/#9990869).

## Cadena real ya completada (origen distinto: #9990874, no #9990935)

| Item | Título | Estado | merge_commit |
|---|---|---|---|
| #9990874 | Items de corrección del Circuito CC — para desatorar el flujo | `aprobado_irving` (paraguas) | `d56e245a` |
| #9990891 | CIRC-08 (SOLO LECTURA): Inventario de ramas sin integrar | `completado` | `9fdf6a4c` |
| #9990917 | CIRC-08 Fase 1 — Censo de ramas sin integrar | `completado` | `472218d8` |
| #9990920 | CIRC-08 Fase 2 — Archivos que `main` no tiene, por rama | `completado` | `40a1af45` |
| #9990921 | CIRC-08 Fase 3+4 — Cuatro cubetas + partir (b) por fecha del guard | `completado` | `bad5dad2` |
| #9990922 | CIRC-08 Fase 5+6 — Ensamblar el reporte final + proponer fix del guard | `completado` (paraguas de 9981/9985) | — |
| #9990981 | CIRC-08 Fase 5a — Fusiona los 3 raw en secciones 1, 2 y 4 | `completado` | `d3da085c` |
| #9990985 | CIRC-08 Fase 5b — Completa secciones 3/5/6 y cierra #9990891 | `completado` | `35e4de72` |

## Verificación contra el criterio de aceptación de #9990935

Confirmado leyendo el entregable real en `main` (`docs/inventario-ramas-sin-integrar-2026-09-11.md`,
992 líneas, más los 3 raw intermedios `censo-raw.md`/`archivos-raw.md`/`cubetas-raw.md`):

- **"El reporte dice, con número, cuántas ramas están sin integrar y cuántas caen en cada
  cubeta"** → Sección 1: tabla de 196 ramas, desglosada en (a) 82 (81 normal + 1 anomalía),
  (b) 25 (23 bandera roja + 2 justificadas), (c) 40, (d) 49.
- **"La cubeta (b) está partida entre anteriores y posteriores al guard"** → Sección 1 (nota de
  corrección de fecha) + Sección 5: el guard real que bloquea el patrón es `0f046eef` (#9990738,
  2026-09-10 18:13:12) — no `ce14a359` (2026-08-08) como asumía el prompt original (ese commit
  solo crea la clase `ThomasService`, no bloquea nada). Con el corte real, las 22 ramas de la
  cubeta (b) bandera roja son **0 posteriores / 22 anteriores** al guard vigente: deuda histórica,
  no un hoyo activo hoy.
- **"El Top 10 es accionable"** → Sección 3, ordenado por líneas de trabajo terminado sin mergear
  (encabezado por #758 y #734, ~950 y ~790 líneas).
- **El caso del reglamento de ventas y comisiones, trazado de punta a punta** → Sección 4.
- **"Ningún merge, ningún checkout en el repo vivo, ninguna terminal interrumpida"** → confirmado:
  la cadena completa es solo-lectura sobre `docs/`, sin tocar código de aplicación.
- **Fase 6 (propuesta, no ejecución) del fix del guard** → Sección 6 concluye que el hallazgo es
  severidad alta como *fenómeno* (22 items completados sin llegar a `main`) pero **no** como
  defecto activo del guard hoy (0 casos posteriores al check real) — por eso **no se abrió** un
  item de fix, evitando perseguir un falso positivo ya descartado con evidencia. Es la misma
  decisión que este item hubiera tenido que tomar de correrse desde cero.

## Por qué no se re-ejecuta

Correr de nuevo las 6 fases sobre las mismas ~196 ramas duplicaría exactamente el trabajo ya
mergeado (mismo censo, mismos archivos, mismas cubetas, mismo hallazgo del guard) sin agregar
información nueva — el estado del repo no cambió de forma relevante entre el cierre de #9990985
y este item. `circuito:cabida` marcó #9990935 como `NO CABE [ya_timeouteo_antes]`, pero la causa
real del timeout anterior no era que el trabajo fuera demasiado grande para descomponer: es que
el trabajo **ya no existía por hacer**, así que no hay fases nuevas que crear como sub-item.

## Conclusión

**Sin cambio de código de aplicación.** #9990935 se cierra como duplicado exacto, ya resuelto por
la cadena #9990874→#9990891→(9990917/9990920/9990921/9990922→9990981/9990985), toda mergeada a
`main` antes de que esta terminal reclamara el item.

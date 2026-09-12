# Item #9990928 (CIRC-06) — "El despachador no llena las terminales ociosas" (RESUELTO — duplicado exacto de #9990863, ya diagnosticado, decompuesto y en curso)

## Hallazgo

El item #9990928 (título "CIRC-06 · El despachador no llena las terminales ociosas") es,
palabra por palabra, el mismo diagnóstico que el item **#9990863** ("Terminales ociosas con
items aprobados: por qué el despachador no asigna y por qué 'Listos para terminal' miente"):
mismas cinco causas candidatas C1-C5 (detector de colisiones bloqueando por `CLAUDE.md`/docs,
evidencia #9990810; "Listos para terminal" listando bloqueados de verdad, evidencia #9990798
tomado tres veces por depender de #9990790; paraguas parqueados inflando el conteo ~23%; tope
de concurrencia/modo por-item; frenos/sin modelo), el mismo plan en 5 fases con la misma
numeración de pasos, y los mismos criterios de aceptación. Comparado línea por línea contra el
`prompt` real de #9990863 (leído vía tinker), el texto es sustancialmente idéntico salvo
formato/redacción menor.

Este NO es el primer duplicado de #9990863: el item **#9990889** (creado 21 minutos después de
#9990863, con el mismo diagnóstico) ya fue cerrado como duplicado el 2026-09-11 18:45
(`docs/circuito-terminales-ociosas-duplicado-item-9990889-verificacion.md`, merge `258ece44`).
#9990928 es un tercer disparo del mismo diagnóstico, creado más tarde (19:09:02, vía
`irving:admin`/Torre) sin decisión nueva ni evidencia distinta.

## Verificación

`circuito:cabida 9990928 --sid=wt-4` devolvió **CABE** — a diferencia de #9990889 (que dio NO
CABE y por eso investigó duplicados antes de decomponer), aquí se investigó igual antes de
tocar código, siguiendo la misma regla ("no dupliques lo que otro item ya está haciendo"), y el
resultado es el mismo.

Confirmado contra la BD real de dev (2026-09-12):

1. **#9990863 sigue `aprobado_irving` + `excluir_pool_automatico=1`** (parqueado como paraguas,
   `merge_commit=4c473d85`): su Fase 1 (instrumentación) está mergeada a `main` —
   `SchedulerCommand::persistirOciosidad()` (líneas 370-379) ya registra, por cada terminal
   ociosa y por ciclo, el código y motivo real (`sin_candidatos`, más los códigos de
   `RoadmapCircuitoService::diagnosticoCeroDespacho()` / `RoadmapItem::motivoNoDespachable()`:
   `dependencia_sin_cerrar`, `footprint_desconocido`, `tope_modulo`, `freno_humano`,
   `esperando_merge`, `agendado`, `bloqueado_por_bucle`, `sesion_supervisada`, `fuera_del_pool`,
   `desarrollo_humano`, `en_progreso`, `bloqueado_por_dependencia`) en el canal de log dedicado
   `circuito_despacho` (`config/logging.php:91`, driver daily, retención 14 días). Verificado
   leyendo el código real en este worktree, no de memoria.
2. **Las Fases 2-5 de CIRC-06 ya existen como sub-items de #9990863**, con specs más detallados
   (puntos de inserción exactos + decisiones de Irving ya tomadas vía preguntas estructuradas) y
   en distintos grados de avance:
   - **#9990892** (Fase 2/C1 — exentar docs del detector de colisiones): `aprobado_irving`,
     parqueado como paraguas de sus propios sub-items **#9990896** (Fase 2a — confirmar con
     evidencia real, `aprobado_irving`, parqueado a su vez de **#9990916** "reintento"),
     **#9990897** (Fase 2b — implementar la exención, `aprobado_revisor`, sin reclamar) y
     **#9990898** (Fase 2c — simulación histórica previa a activar, `aprobado_irving`).
   - **#9990893** (Fase 3/C2 — que "Listos para terminal" diga la verdad + cablear #9990798):
     **`en_progreso`** — otra terminal la tiene tomada ahora mismo (no se toca, un item = un
     dueño).
   - **#9990894** (Fase 4/C3-C4-C5): `aprobado_revisor`, sin reclamar.
   - **#9990895** (Fase 5/tablero permanente): `aprobado_revisor`, sin reclamar.
3. No hay ninguna pieza del alcance de #9990928 que no esté ya cubierta por esta cadena — ni
   siquiera con matices distintos: la redacción de los 12 pasos y los 3 criterios de aceptación
   coincide casi al carácter con el `prompt` de #9990863.

## Conclusión

Re-decomponer #9990928 en fases propias duplicaría exactamente el trabajo ya registrado y en
curso bajo #9990863 → #9990892 (→ #9990896 → #9990916, #9990897, #9990898) / #9990893 (en
progreso) / #9990894 / #9990895 — mismo diagnóstico, misma evidencia, mismos puntos de
inserción de código. Crear sub-items gemelos sería el desperdicio de trabajo que la regla
BALANCE/minimalismo del `CLAUDE.md` de arquitectura pide evitar, y competiría por las mismas
terminales que ya están (o estarán) trabajando la cadena real.

**Item #9990928 cerrado como duplicado, sin sub-items nuevos.** El trabajo real de las 5 fases
de esta épica sigue su curso bajo #9990863 y su descendencia. **Sin cambio de código de
aplicación** — solo esta verificación documental, mismo patrón que #9990889.

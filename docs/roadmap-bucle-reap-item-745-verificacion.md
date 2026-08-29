# Item #745 — [RESPUESTA] DocumentaciónCorporativa Fase 2 — bucle reap/escalación en paraguas ya descompuesto (RESUELTO — cerrado como no-accionable, decisión de Irving)

## Contexto

El item #664 ("DocumentaciónCorporativa — Fase 2") fue descompuesto correctamente en 4 sub-items
(#734 repositorio, #735 bandeja, #736 registros estructurados, #737 plantillas) por una vuelta
previa (2026-08-28 19:06). Los 4 quedaron `pending`/aprobados, con spec completo, **sin
`worker_sid` ni rama** — nadie los había tomado. #664 quedó correctamente como paraguas: no le
queda trabajo de implementación propio, solo espera a que cierren sus 4 hijos.

#745 es ya la 4ª+ vuelta que reclama #664 (vía el generador de seguimientos `[RESPUESTA]`) y
llega a la misma conclusión que las anteriores: nada que implementar en el padre sin duplicar el
trabajo ya delegado a los hijos. El propio item documentó el patrón del bucle: timeout con 0
commits → escala a `requiere_irving` → Irving aprueba → el reaper de huérfanos re-encola → otra
vuelta re-verifica (nota, sin cambios) → vuelve a timeoutear → repite (`reap_count` llegó a 4 en
#664, con 3 notas de sesiones distintas confirmando lo mismo).

## La pregunta y la decisión de Irving

El item trajo una pregunta estructurada (`preguntas[]`) con 3 opciones sobre **cómo procesar
#745 mismo**:

1. **Cerrar el item como no-accionable** (paraguas ya descompuesto; la respuesta es informativa;
   los sub-items hijos son los que llevan el trabajo real) y dejar nota en bitácora del roadmap.
   *(confianza media, reversible, recomendada)*
2. Re-descomponer el paraguas desde cero ignorando la descomposición previa. *(confianza alta,
   NO reversible, NO recomendada — duplicaría sub-items y probablemente re-dispararía el mismo
   bucle)*
3. Marcar el item como bloqueado y escalarlo a Irving. *(confianza alta, reversible, NO
   recomendada — es exactamente el bucle que hay que romper)*

Irving aprobó explícitamente la **Opción 1** (`opcion_elegida` en el log del item, decisión
`aprobar` del 2026-08-29 04:33 en `irving:admin`). Este item ejecuta esa decisión: cerrar #745
como no-accionable, sin re-implementar ni re-descomponer #664.

## Verificación contra la BD de dev (hoy, 2026-08-29)

| Item | status | estado_aprobacion | worker_sid | reap_count |
|---|---|---|---|---|
| 664 (padre/paraguas) | pending | aprobado_irving | wt-1 (en curso) | 4 |
| 734 (Fase 2a — repositorio) | pending | aprobado_irving | *(sin asignar)* | 0 |
| 735 (Fase 2b — bandeja) | pending | aprobado_irving | *(sin asignar)* | 0 |
| 736 (Fase 2c — registros estructurados) | pending | aprobado_irving | *(sin asignar)* | 0 |
| 737 (Fase 2d — plantillas) | pending | aprobado_revisor | *(sin asignar)* | 0 |

Confirma exactamente lo que describía #745: los 4 hijos siguen aprobados y sin reclamar, y el
padre #664 sigue siendo el que absorbe el bucle de reap/escalación porque el pool de despacho
sigue intentando trabajar directamente sobre el paraguas en vez de sobre los hijos.

## Qué NO se hizo, y por qué

- **No se tocó #664** (rama `circuito/item-664-...`, `worker_sid=wt-1`): está reclamado por otra
  sesión en curso — tocarlo violaría el protocolo de coordinación (un item, un dueño).
- **No se re-descompuso** el paraguas (Opción 2, explícitamente rechazada por Irving).
- **No se implementó código** para #734-#737: harían el trabajo del que son dueños esos 4 items
  cuando se reclamen; adelantarlo aquí duplicaría spec ya escrita y competiría con quien los tome.
- **No se tocó la mecánica del reaper/`circuito:cabida`** descrita en la sección "El hallazgo" del
  item (los 3 puntos A/B/C de la causa raíz del bucle). Eso es una mejora al propio circuito, un
  alcance distinto y más amplio que "cómo procesar #745" — la pregunta que Irving efectivamente
  respondió fue sobre el segundo, no el primero. Queda registrado aquí como deuda de fondo por si
  se decide abordarlo aparte.

## Conclusión

#745 se cierra como **no-accionable**, ejecutando la Opción 1 elegida por Irving: el trabajo real
de la Fase 2 vive en #734/#735/#736/#737, que siguen aprobados y esperando ser reclamados por el
pool de despacho. Cerrar #745 saca al paraguas de la cola de re-escalación para esta vuelta; no
resuelve por sí solo por qué el pool no toma los 4 hijos directamente.

## Deuda de fondo (para quien la retome — fuera de alcance de este item)

Los 3 puntos que planteaba #745 sobre la causa raíz del bucle siguen sin decidirse:

- **A)** `circuito:cabida` sobre un paraguas ya descompuesto con hijos pendientes hoy devuelve
  "procede a implementar" (verificado en esta misma vuelta: `circuito:cabida 745` → `CABE
  [sin_senal_de_riesgo] ... procede a implementar #745`) — contradictorio cuando el item es un
  paraguas sin trabajo propio.
- **B)** El reaper de huérfanos re-encola items que ya tienen hijos `origen_item_id=X` sin
  cerrar, en vez de excluirlos.
- **C)** La causa raíz probable: nada asigna directamente #734-#737 al pool de trabajo pese a
  estar aprobados desde el 2026-08-28. Si el pool los tomara solos, #664 cerraría solo cuando
  cierren sus hijos y el terreno del bucle desaparecería sin tocar A/B.

Si se retoma, la recomendación del propio #745 (diagnosticar C primero, A+B como mitigación del
síntoma mientras tanto) sigue siendo válida.

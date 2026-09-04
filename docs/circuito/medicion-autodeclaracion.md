# Medición de la autodeclaración del modelo — reversible/confianza vs. revert/escalada/reabertura (#674)

> Generado automáticamente por `php artisan circuito:medir-autodeclaracion` el 2026-08-28 23:46:28. Read-only:
> no modifica ítems ni la política del autopilot. Re-ejecutar este comando pisa este archivo con la
> medición más reciente (el dataset crece cada día; esta es una FOTO, no un valor fijo).

## Población medida

233 opciones `recomendada=true` en 76 items con brief completo (columna
`preguntas`, la que arma `RevisorService::proponerPreguntas()` y leería `AutopilotService` si el
tope lo dejara). Se mide sobre esta población y NO solo sobre `log[].decidido_por==='autopilot'`
porque el autopilot en dev solo ha decidido 2 items hasta hoy — muestra insuficiente para leer nada;
esta población es exactamente el dato que el autopilot consultaría si el tope se aflojara.

## Cruce 1 — `reversible=true` vs. revert real

| Nivel de medición | reversible=true | de esas, con revert después | tasa |
|---|---:|---:|---:|
| Por opción | 207 de 233 | 0 | 0% |
| Por item (evita contar 2-3 veces un item con varias preguntas) | 75 de 76 | 0 | 0% |

`revert` = evento `log[].evento === 'revert_merge'` (botón "Revertir" o rechazo de una rama ya
integrada). En **todo** el historial de dev (721 items) este evento tiene **0 ocurrencias** — nunca
se ha revertido código que ya llegó a main. La tasa de arriba es 0% no por sesgo de medición, sino
porque el hecho que mediría no ha pasado todavía.

## Cruce 2 — `confianza=alta` vs. escalada ∪ revertida ∪ reabierta

| Nivel de medición | confianza=alta | de esas, con problema después | tasa |
|---|---:|---:|---:|
| Por opción | 94 de 233 | 3 | 3.2% |
| Por item | 57 de 76 | 2 | 3.5% |

"Problema" = cualquiera de estos 3 eventos en `log[]` del item, buscados en la población completa
(no solo la de confianza alta): `revert_merge` **0**, `rechazo_reciclar` **0**, `merge_escalado`
**2** items (de 76). Los dos primeros están en 0 en todo el dataset —
ningún item de este circuito ha sido revertido ni regresado al backlog por rechazo humano todavía.
`merge_escalado` SÍ tiene datos reales, pero es una escalada **mecánica** de integración
(conflicto de git / regresión de verificación al mergear), no evidencia directa de que la opción
recomendada fuera la incorrecta — es la mejor señal disponible, no una prueba concluyente.

## Items de la población con revert, reabertura o escalada

| Item | Título | Estado actual | Motivo | ¿Tenía recomendada reversible=true? | ¿Tenía recomendada confianza=alta? |
|---|---|---|---|---|---|
| #146 | Retención de respaldos por versión (storage/backup_test) | requiere_irving | escalada | sí | sí |
| #165 | Manual: mover regeneración automática a job en cola (latencia/costo f… | requiere_irving | escalada | sí | sí |


## Lectura (dato, no la decisión — cambiar el techo del autopilot sigue siendo de Irving)

- Con **0 reverts y 0 reaperturas** en 721 items, la autodeclaración `reversible=true` no tiene
  todavía ni un solo caso confirmado de haber estado mal — no porque sea perfecta, sino porque el
  circuito nunca ha llegado al punto de necesitar deshacer algo integrado.
- Las únicas 2 escaladas de la población completa (76 items con brief)
  son de tipo mecánico (conflicto/regresión de merge), y de esas, 2
  tenían una opción recomendada con `confianza=alta` — es decir, la confianza alta autodeclarada NO
  libró a esos items de un tropiezo de integración, aunque el tropiezo no fue por el CONTENIDO de la
  decisión.
- **La muestra es chica** (2 de 76 items con algún problema) y el autopilot en sí casi no
  ha corrido (2 decisiones reales). Con este volumen, "el techo en C sigue siendo razonable" y "el
  techo en C ya no hace falta" son igual de defendibles con el dato de hoy — la medición no
  decide por Irving, solo dice que hasta ahora no hay evidencia de que la autodeclaración se
  equivoque, y tampoco evidencia suficiente para confiar en ella a ciegas. Re-correr este comando
  dentro de unas semanas, cuando haya más ciclos completos, es lo que movería esta lectura.
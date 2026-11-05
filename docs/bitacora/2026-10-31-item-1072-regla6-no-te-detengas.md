# Regla 6 «no te detengas» — diseño de contención de errores (solo diseño, NO implementado)

## 2026-10-31 — Item #1124 (fase única de #1072)

Estado: **propuesta para decisión de Irving**. Este documento no cambia código ni config.
Decisiones de alcance ya tomadas por Irving al aprobar el item: documento mínimo (q1), mecanismo
combinado reintentos + circuit-breaker + revert en rama + escalación (q2), archivo propio en
`docs/bitacora/` (q3), umbrales marcados «a validar» (q4).

## 0. Problema

Hoy una terminal que duda se detiene y escala. La Regla 6 pide lo contrario: **seguir con el
camino más probable** y contener el daño si resulta equivocado. Esto solo es seguro si el error
queda acotado, visible y deshacible.

## 1. Aislamiento de la «versión de prueba aparte»

- **Rama + worktree propios** (ya existen: `circuito:rama`, `/home/meganet/circuito/wt-K`). Es el
  único aislamiento necesario; no se introduce un mecanismo nuevo.
- Con duda, la terminal **sigue en su rama** y no ejecuta `circuito:integrar` hasta que la
  verificación (php -l, build, tinker) pase. Sin verificación verde no hay merge.
- Opcional (fase posterior): si la duda es de *comportamiento* (no de compilación), el cambio se
  entrega detrás de un **flag apagado por defecto**, de modo que llegar a main no lo active.
- Nunca: tocar main directo, `/var/www/megaisp`, prod, ni la BD con datos reales fuera de
  transacción/rollback.

## 2. «Duda» vs frontera dura

| Tipo | Ejemplo | Qué hace la terminal |
|---|---|---|
| **Duda de implementación** | nombre, ubicación, entre dos diseños razonables, texto | Decide sola (Regla 6) y registra |
| **Duda de spec** | el spec es ambiguo pero hay lectura más probable | Decide sola, registra, lo deja visible en el cierre |
| **Frontera dura** | producción · borrar datos · gastar dinero · credenciales/seguridad | **Escala siempre** (`circuito:consultar` → Thomas → Irving). La Regla 6 NO aplica |
| **Spec contradictorio** | dos requisitos imposibles a la vez | Consulta a Thomas |

Criterio operativo: la Regla 6 solo aplica si **todas** las opciones consideradas son reversibles
(aditivas, sin borrar datos, sin dinero ni permisos). Basta que una no lo sea → es frontera.
La válvula de contexto (mención vs acción) sigue siendo la que clasifica los términos de frontera.

## 3. Elección del «camino más probable» y registro

Orden de preferencia para elegir (el primero que aplique):
1. Lo que dice el spec / las respuestas ya dadas por Irving (`preguntas[].opcion_elegida`).
2. Lo que ya hace el código vecino (patrón existente).
3. La opción más simple, aditiva y reversible.

Registro obligatorio, en el momento de decidir:
`php artisan circuito:reportar <id> --sid=<wt> --tipo=decision --resumen="<qué se eligió, alternativa descartada, cómo revertir>"`

Se propone que el resumen incluya siempre la **alternativa descartada** y el **cómo revertir**:
sin eso la decisión no es auditable.

## 4. Cómo se le explica a Irving qué pasó y qué lo espera

- Cada decisión sola aparece en el historial del item (tipo `decision`) y en el `reporte_coloquial`
  del cierre, en lenguaje llano: «qué se veía roto → qué pasa ahora».
- Si hubo ≥1 decisión por duda, la Torre muestra una marca **«decidió sola (N)»** en la fila del
  item, para revisar *después*, no antes.
- Si una contención se disparó (sección 6), el item cae a la bandeja con motivo explícito
  `contencion_regla6` y la pregunta concreta: **mantener / revertir / cambiar de camino**, con la
  recomendada preseleccionada.

## 5. Interruptor y reversión

- Clave nueva en `config/circuito.php`: `regla6.enabled`, **`false` por defecto**
  (env `CIRCUITO_REGLA6_ENABLED`). Con `false` el comportamiento es el actual (se detiene y consulta).
- Parámetros bajo `regla6.*` (todos **«a validar»** por Irving al implementar, q4):
  `max_reintentos` (propuesto 2), `fallos_seguidos_pausa` (propuesto 3 terminales/vueltas),
  `max_decisiones_por_item` (propuesto 5; más que eso indica spec pobre → escalar).
- Reversión: apagar el flag (efecto inmediato en la siguiente vuelta, sin redeploy de código si se
  lee de config) y, por item, `git revert` del merge de la rama. El kill switch `circuito_pausado`
  sigue frenando todo, independiente de este flag.

## 6. Mecanismos de contención (q2: combinado)

1. **Límite de reintentos** por verificación: tras `max_reintentos` fallos del mismo chequeo,
   se deja de intentar.
2. **Circuit-breaker**: N vueltas seguidas con fallo de verificación o revert → la terminal/módulo
   se **pausa** y escala; no sigue consumiendo la cola.
3. **Revert automático en su rama**: si la verificación falla y no se arregla, se revierten los
   commits de la rama (ya es regla actual: «nunca dejar algo roto») y se escala.
4. **Escalación** a Irving con la pregunta concreta (sección 4).
5. **Tope de decisiones por item** (`max_decisiones_por_item`) para cortar specs que obligan a
   adivinar demasiado.

## 7. Riesgos y límites (lo que esto NO resuelve)

- **Error silencioso**: un cambio que compila y pasa la verificación pero hace lo incorrecto. La
  contención solo ve fallos detectables; el resto depende de que Irving revise las decisiones.
- **Decisiones encadenadas**: una mala decisión temprana contamina las siguientes dentro del mismo
  item; por eso el tope y registrar la alternativa descartada.
- **Clasificación errónea de frontera**: si la válvula etiqueta como «reversible» algo que no lo es,
  la Regla 6 lo ejecuta. Mitigación: ante cualquier término de frontera, aunque sea mención, se
  mantiene el comportamiento actual (escalar), tal como decidió Irving en #9990210.
- **Ruido en la bandeja** si los umbrales son bajos; por eso arrancan «a validar» y el flag apagado.
- **Colisión entre terminales** sobre los mismos archivos: ya cubierta por candado #438; la Regla 6
  no lo debilita.
- No cubre efectos fuera de dev (prod, dinero, credenciales): siguen siendo frontera dura.

## 8. Siguiente paso

Item `[RESPUESTA]` nivel B+ con las opciones para implementar (creado al cerrar #1124).

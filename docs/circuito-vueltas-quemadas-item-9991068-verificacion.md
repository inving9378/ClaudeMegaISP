# Item #9991068 — Política de "vueltas quemadas" (seguimiento de #9990893) — verificación

**Fecha:** 2026-09-13
**Item:** #9991068 (seguimiento auto-generado al cerrar #9990893 con 1 pregunta sin resolver)
**Resultado:** ✅ RESUELTO — la política que Irving eligió ya es el comportamiento real del sistema. Sin código nuevo.

## La pregunta

Al cerrar #9990893 ("Fase 3 (C2) — que 'Listos para terminal' diga la verdad + contador de
vueltas quemadas + cablear #9990798") quedó 1 pregunta (`q2`) sin `opcion_elegida`:

> ¿Qué política aplicar cuando un item alcanza el límite de "vueltas quemadas" (reintentos
> fallidos en el circuito)?

Con 3 opciones:
1. **Límite 3 vueltas → auto-escalar a Irving con resumen de los 3 intentos fallidos y sacar de
   la cola de terminal** (recomendada, confianza media, reversible).
2. Límite 5 vueltas antes de escalar.
3. Sin límite duro, solo badge visual.

Irving aprobó el item (log `2026-09-13T08:47:09-06:00`, actor `irving:admin`) con
`respuestas.q2 = "cbe80cc7bf368de6"`. Ese hash es `RoadmapItem::claveOpcion()` (sha1 truncado a
16 chars) del texto de la **Opción 1** — verificado calculándolo:

```
$ php -r '... substr(sha1(...texto Opción 1...), 0, 16) ...'
1: cbe80cc7bf368de6   ← coincide
2: 8142a6edc4233f2c
3: dce11243ae30d4bd
```

## Verificación: la Opción 1 YA es el comportamiento del sistema

No hay una sola pieza de código que implemente esto — hay DOS mecanismos independientes, cada
uno anteriores a esta pregunta, que juntos cubren exactamente el mismo objetivo:

### 1. Timeouts reales (causa `timeout`/`max_turns`/`sigkill`) — `ParquearTimeoutCommand`

`app/Modules/Addons/Roadmap/Console/ParquearTimeoutCommand.php` (decisión de Irving,
2026-08-20, documentada en el propio docblock del archivo):

- `TOPE_REANUDACIONES = 2` (línea 55): un item con AVANCE real (commits en su rama) se reanuda
  hasta 2 veces; al 3er timeout (`reanudaciones_timeout >= TOPE`), escala a `requiere_irving`
  con un motivo que resume el patrón ("Ya se reanudó N vez(ces): el item es más grande que una
  vuelta y necesita que lo dividas o lo acotes.", línea 303-304).
- Sin avance (0 commits) → escala **de inmediato** (más estricto que 3).
- `sigkill` (guard de vida máxima que tuvo que forzar el cierre) → escala **siempre**, nunca
  reanuda solo (línea 243, decisión de Irving en #9990295 q3).
- Al escalar: `worker_sid = null` y `claimed_at = null` (línea 314-315, comentario explícito
  "#927 — SOLTAR EL CLAIM TAMBIÉN AQUÍ... un item en la bandeja no lo está trabajando ninguna
  terminal, por definición") — esto es literalmente "sacar de la cola de terminal".

### 2. Reclamos huérfanos (worker muerto/colgado) — `RoadmapCircuitoService::reencolarHuerfano`

`app/Modules/Addons/Roadmap/Services/RoadmapCircuitoService.php:3519-3548` (usado por
`circuito:reap-stuck` y el watchdog, decisión de #561):

- `config('circuito.reaper.max_reintentos', 3)` — `config/circuito.php:911`, default **3**.
- `reap_count` se incrementa en cada reclamo fallido; al superar el tope escala a
  `requiere_irving` con un motivo que resume el conteo ("escalado a tu bandeja tras N reclamos
  fallidos (tope 3)", línea 3529) y suelta `claimed_at`/`worker_sid` (líneas 3523-3524).

### 3. "Sacar de la cola de terminal" — confirmado en el query del pool

`RoadmapCircuitoService.php:2404`:

```php
$q->whereIn('estado_aprobacion', ['aprobado_claude', 'aprobado_revisor', 'aprobado_irving'])
```

`requiere_irving` **no** está en esa lista → un item escalado queda automáticamente excluido del
despacho automático hasta que Irving lo apruebe de nuevo. Confirma la tercera parte de la Opción
1 ("sacar de la cola de terminal").

### 4. Capa adicional (más estricta aún) — `JarvisService::caberEnVuelta()`

`app/Modules/Addons/Roadmap/Services/JarvisService.php:1340`: `veces_timeouteo >= 1` ya fuerza
`circuito:cabida` a devolver NO CABE en el intento siguiente — un solo timeout previo obliga a
descomponer antes de volver a picar código, un candado más conservador que el límite de 3.

## Conclusión

Las tres piezas —tope de reanudaciones con resumen, tope de reclamos huérfanos con resumen, y
exclusión del pool vía `estado_aprobacion`— ya entregan, en conjunto, exactamente lo que pedía
la Opción 1 que Irving eligió. No hay brecha que cerrar con código nuevo: es el mismo patrón ya
documentado varias veces en `CLAUDE.md` para items de seguimiento cuya pregunta resulta ya
resuelta por trabajo anterior (ver ahí #733/#741/#753/#9990003/#9990353/#9990658/#9990869).

**Nota sobre `circuito:cabida` en este item:** devolvió `NO CABE [historico_excede_umbral]` —
heurística basada en la mediana histórica de items previos de este mismo módulo
("Roadmap / Circuito CC"), sesgada por la cantidad de items de "bucle reap" (ver la extensa
lista en `CLAUDE.md`) que sí tomaron mucho tiempo. Este item, en cambio, era una verificación
puntual de una pregunta ya contestada por Irving contra código ya existente — no había fases
reales que descomponer, así que se optó por cerrar directo con esta documentación en vez de
crear sub-items artificiales para trabajo que no existe (decisión registrada vía
`circuito:reportar --tipo=decision`, reporte #8079).

**Sin cambio de código de negocio** — los tres mecanismos (`ParquearTimeoutCommand`,
`reencolarHuerfano`, filtro del pool) ya estaban en `main` desde antes de que existiera esta
pregunta.

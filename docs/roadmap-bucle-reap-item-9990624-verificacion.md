# Item #9990624 — Crear versión se ve como "no funciona" (changelog IA cuelga el request): bucle reap sobre paraguas ya descompuesto

## Contexto

`#9990624` ("Crear versión se ve como \"no funciona\": el changelog IA cuelga el request 2-4 min
sin timeout") es la misma familia de bug documentada repetidamente en `CLAUDE.md` (#738, #745,
#830, #816, #818, #848, #852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936, #9990408,
#962, #9990554, #9990549, entre otros): un item se descompone correctamente en sub-items, y en
este caso incluso su propia Fase 1 quedó implementada y merge-ada, pero nadie ejecuta el intento
de cierre que dispara el guard de paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS"), así que el
item se queda colgado hasta que el reaper lo re-encola y el pool lo vuelve a repartir sin que haya
trabajo propio que hacer.

## Lo que ya se hizo bien (sesión previa)

El item fue reportado por David (2026-09-08): "no puedo hacer una versión". El diagnóstico
original distinguía dos causas — `ClaudeApiClient::sendMessage()` sin timeout, y un rango de
commits gigante (V1.32..HEAD, 1122 commits) — y el prompt pedía 5 fases (timeout, mover a job,
que Guardar no dependa de la IA, corregir `next-version`, verificar end-to-end).

La vuelta previa (`wt-1`, 2026-09-08 12:36) ya hizo lo correcto:

- **Fase 1** (timeout de 90s en `ClaudeApiClient::sendMessage()`) quedó commiteada en la rama del
  propio item (`754631d6`) — y, verificado en esta vuelta, **ya está en `main`** (commit
  `c201ad00`, íntegro: `REQUEST_TIMEOUT_SECONDS` aplicado a la llamada HTTP + captura de
  `ConnectionException` con mensaje claro en vez de colgar).
- **Fases 2/3, 4 y 5** se descompusieron en sub-items (`origen_item_id=9990624`) con el detalle ya
  investigado (archivos/líneas exactas), para que el próximo ejecutor no repitiera la exploración:
  - **#9990626** — "Changelog IA: mover generación a job en cola + que Guardar no dependa de la IA
    (Fase 2+3)".
  - **#9990627** — "next-version: considerar tags de git además de la tabla releases + reportar
    divergencia (Fase 4)".
  - **#9990628** — "Verificar end-to-end changelog+versión con el rango real V1.32..HEAD (Fase 5)".

## Por qué se quedó colgado

Justo después de escribir la decisión de descomposición en `comentarios_claude`, esa vuelta
terminó sin intentar el cierre del padre. El log lo confirma: el evento inmediato siguiente es
`reaper-rapido` ("el slot wt-1 está libre... reclamo huérfano → re-encolado, intento 1/3, vuelve a
aprobado_revisor"). Después hubo una pausa por colisión de archivo con `#9990626`
(`ClaudeApiClient.php`, resuelta sola) y el item volvió a reclamarse (`wt-1`, esta vuelta) sin que
quedara trabajo propio pendiente — Fase 1 ya en `main`, y las fases restantes ya delegadas.

## Verificación del estado real (esta vuelta)

```
9990624 (yo) | en_progreso     | excl=false | sid=wt-1
  9990626    | completado      | merge_commit=c65d422f (Fase 2+3: job en cola + polling, YA EN MAIN)
  9990627    | requiere_irving | sin reclamar (Fase 4: next-version con tags de git)
  9990628    | aprobado_revisor| sin reclamar (Fase 5: verificar end-to-end V1.32..HEAD)
```

`c201ad00` (Fase 1, timeout) confirmado en `main` con `grep` directo sobre
`ClaudeApiClient.php:58` (`->timeout(self::REQUEST_TIMEOUT_SECONDS)`). Los 3 hijos
(`origen_item_id=9990624`) están intactos — 1 ya cerrado y mergeado, 2 sin reclamar — la
descomposición original sigue siendo correcta, nadie más la tocó.

## Corrección aplicada

Se ejecuta el intento de cierre faltante sobre `#9990624` (`estado_aprobacion='completado'`). El
guard de paraguas del modelo lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (log
`paraguas_abierto`), liberando `worker_sid`/`claimed_at` y sacándolo del pool/reaper hasta que el
hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990627 y #9990628
cierren.

**Sin cambio de código de negocio propio de esta vuelta** (más allá de documentar el hallazgo).
Fase 1 ya estaba en `main` de una vuelta previa; Fase 2+3 ya cerrada y mergeada vía #9990626; el
trabajo real que falta (Fase 4 — `next-version` con tags de git — y Fase 5 — verificación
end-to-end) sigue en #9990627 (`requiere_irving`, pendiente de aprobación de Irving) y #9990628
(`aprobado_revisor`, listo para reclamarse).

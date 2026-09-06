# Item #9990412 — cierre del bucle reap sobre el paraguas FASE 4 (pausar el scheduler con causa=limite_cuenta)

## Contexto

#9990412 ("FASE 4: pausar el scheduler mientras la cuenta esté sin límite") depende de que
#9990411 (FASE 1+2, detección de causa=limite_cuenta) esté mergeado a main. Una vuelta previa
(`wt-6`, 2026-09-06 16:41) ya hizo el trabajo de diagnóstico correcto:

1. Corrió `circuito:cabida 9990412` → **NO CABE** (histórico ~482s) y además detectó que la
   dependencia real (#9990411) no estaba mergeada.
2. En vez de picar código a medias, descompuso el trabajo en dos sub-items independientes:
   - **#9990417** — "FASE 4a: expiración opcional en el centinela FrenoCircuito (campo
     `expira_en` + autolimpieza)". Independiente de #9990411 (no necesita la causa
     `limite_cuenta`, solo la capacidad de expiración del centinela).
   - **#9990418** — "FASE 4b: enganchar el freno-con-expiración en `vuelta.sh` cuando
     causa=limite_cuenta". Correctamente declarado `depende_de=[9990411, 9990417]`.
3. No creó rama para el paraguas (correcto: un paraguas no tiene trabajo de código propio).

Esa parte fue correcta. Lo que faltó: **esa vuelta nunca intentó cerrar #9990412**. El item se
quedó `en_progreso` con el `worker_sid` de esa sesión, sin que nadie liberara el claim. El reaper
de huérfanos (`reaper-rapido`) lo vio con el slot `wt-6` libre, lo re-encoló a `aprobado_revisor`
(`reap_count=1`), y el pool lo volvió a repartir (a mí, `wt-4`) sin que hubiera trabajo propio que
hacer — mismo síntoma que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#910: un
paraguas correctamente descompuesto que nunca recibió el intento de cierre que activa el guard de
"no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- `circuito:cabida 9990412 --sid=wt-4` → `CABE [ya_descompuesto]` (no re-descompone; solo dice
  "procede a implementar" — en este caso, intentar el cierre y dejar que el guard de paraguas
  decida).
- Query directa: `RoadmapItem::where('origen_item_id', 9990412)->get()` → dos hijos intactos,
  **#9990417** (`estado_aprobacion=aprobado_irving`, `merge_commit=7b74322e...`, ya mergeado a
  main) y **#9990418** (`estado_aprobacion=aprobado_revisor`, sin reclamar) — ambos siguen
  contando como "abiertos" para el guard de paraguas porque ninguno llegó a `completado` todavía.
- Intento de cierre: `RoadmapItem::find(9990412)->estado_aprobacion = 'completado'; ->save();` →
  el guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true`, agregando al log el evento
  `paraguas_abierto` ("le queda 1 sub-item abierto: no se completa"). Confirmado leyendo
  `$item->log` tras el save. El conteo real es 1 y no 2 porque `subItemsAbiertos()`
  (`RoadmapItem.php:1758-1764`) excluye con `whereNull('archivado_at')` a los hijos ya
  archivados — #9990417 tiene `archivado_at` seteado (se archivó al mergear `7b74322e`), así que
  no cuenta como "abierto" para este guard aunque su `estado_aprobacion` siga en
  `aprobado_irving` en vez de `completado`. El único hijo que sí bloquea el conteo es #9990418.

### Hallazgo adicional (fuera de alcance de #9990412, NO tocado)

Al verificar la cadena completa se encontró que **#9990417 tiene el mismo problema, un nivel más
abajo, con una variante distinta**: el trabajo real de #9990417 (expira_en + autolimpieza en
`isPaused()`) ya fue implementado y mergeado DIRECTAMENTE a `main` por otra sesión (`wt-1`,
commits `3b443dc5` + `ad65a898`, merge `7b74322e`), pero en el mismo instante (`16:58`) una
sesión distinta (`wt-4`, en una vuelta anterior de este mismo slot) decidió por su cuenta que
#9990417 "NO CABE" y lo descompuso en **#9990424**/**#9990425** — dos sub-items que describen
exactamente el trabajo que `wt-1` ya estaba terminando de mergear. El guard de paraguas de
#9990417 ve esos 2 hijos como "abiertos" y lo bloquea igual que a #9990412, aunque su trabajo real
ya esté en `main`. Esto **no se corrige aquí** (no es mi item — regla "un item = un dueño"); queda
anotado para que el próximo item que reclame #9990417/#9990424/#9990425 lo resuelva (cerrar
#9990424/#9990425 como redundantes, ya que su descripción coincide con el código ya mergeado en
`7b74322e`, y dejar que #9990417 cascadee solo).

## Resultado

#9990412 queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta
que #9990418 cierre — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente y
verificado en las sesiones de #738/#745/#830) completa #9990412 solo, sin intervención manual
(el guard de conteo ya no espera a #9990417 por estar archivado, ver arriba).

**Sin cambio de código de negocio.** El trabajo técnico real (enganchar el freno-con-expiración
en `vuelta.sh`) sigue en #9990418, bloqueado hasta que #9990411 y #9990417 cierren — y #9990417
a su vez está bloqueado por el hallazgo adicional documentado arriba (#9990424/#9990425
redundantes).

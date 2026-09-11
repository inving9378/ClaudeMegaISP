# Item #9990740 — MR flujo animado Fase 2 — bucle reap sobre paraguas ya descompuesto

## Resumen

Mismo patrón documentado repetidamente en `CLAUDE.md` (familia de items #738, #745, #830, #816,
#818, #848, #852, #905, #878, #906, #907, #924, #9990012, #917, #910, #936, #9990408, #962,
#9990554, #9990549, #9990624, #9990650, #9990733): un item se descompone correctamente en
sub-items, pero la vuelta que lo hizo termina (muere/timeoutea) **sin intentar cerrar al padre** —
y sin ese intento el guard de paraguas de `RoadmapItem.php` nunca dispara, así que el item queda
`en_progreso`/`aprobado_revisor` sin `excluir_pool_automatico`, y el pool/reaper lo vuelve a
repartir una y otra vez sin que quede trabajo propio por hacer.

## Línea de tiempo real (BD)

1. **2026-09-10 18:09** — #9990740 nace como sub-item de seguimiento de #9990733 (Fase 2: los 4
   estados simulados a mano sobre el NAP piloto), con `depende_de` implícito en la Fase 1
   (#9990739, wiring + feature flag).
2. **2026-09-10 18:12** — Revisor autoriza (`aprobado_revisor`, confianza alta): UI/animación
   aditiva sin tocar frontera dura.
3. **2026-09-10 18:15** (`wt-4`) — Intenta trabajarlo, encuentra que Fase 1 (#9990739) seguía
   `en_progreso` sin `merge_commit` y las clases CSS `est-ok/est-degradado/est-critico/est-caido`
   aún no existían en `main` (grep vacío). Fija `depende_de=[9990739]` y libera el reclamo.
4. **2026-09-10 18:41** (`wt-1`) — Antes de poder implementar Fase 2, encuentra y arregla una
   regresión real: Fase 1a (#9990752) y Fase 1b (#9990753) dejaron `flujoAnimadoConfig`/
   `setFlujoAnimadoConfig` declarados DOS VECES en `mapUtils.js` (bloques no solapados, el merge
   no lo detectó como conflicto) → rompía TODO el build del frontend. Deja el fix commiteado en
   la propia rama del item (`4390c0d0`), verificado con `npm-build.sh` (pasa de fallar a
   compilar OK) — pero **no lo mergea** todavía (seguía con la Fase 2 real por hacer).
5. **2026-09-10 18:45** — Timeout por `max_turns`; como la rama tenía 1 commit (avance real), se
   reanuda (`reanudacion 1 de 2`) en vez de escalar.
6. **2026-09-10 18:49** (`wt-1`) — `circuito:cabida` devuelve NO CABE (ya había timeouteado
   antes). Investiga el estado real: Fase 1 completa (#9990739/#9990752/#9990753 ya en `main`,
   wiring config→blade→Vue→`mapUtils.js` confirmado) y descompone el trabajo real en **#9990754**
   (mecanismo multi-piloto: varias rutas con estado propio, generalizando el `pilotRouteId` único
   de Fase 1) y **#9990755** (elegir NAP real con varias rutas + repartir los 4 estados + activar
   flag + verificar, depende de #9990754). Sin código propio de negocio nuevo en esta vuelta (el
   fix del paso 4 seguía sin mergear).
7. **2026-09-10 18:49:54** — La vuelta termina (`soltar-claim`, "muerte del proceso") **sin
   intentar cerrar** al padre. El pool la vuelve a reclamar (reclamo actual, `wt-1`, esta vuelta)
   sin trabajo propio que hacer — mismo síntoma que toda la familia de items listada arriba.

## Verificación de esta vuelta

- Los 2 hijos (`origen_item_id=9990740`) siguen intactos: `#9990754` y `#9990755`, ambos
  `pendiente_revision`, sin `worker_sid` — nadie los reclamó todavía. La descomposición original
  seguía siendo correcta; nadie más la tocó.
- **Hallazgo adicional verificado esta vuelta:** el commit `4390c0d0` (fix de la redeclaración
  duplicada) seguía **sin mergear** a `main` (`git merge-base --is-ancestor 4390c0d0 main` →
  falso). Se confirmó que el bug es real y sigue vivo en `main` ahora mismo:
  ```
  $ bash deploy/circuito/npm-build.sh
  ERROR in ./resources/js/components/module/mapared/helper/mapUtils.js
  SyntaxError: ... Identifier 'flujoAnimadoConfig' has already been declared. (376:6)
  [npm-build] build falló (rc=1) — bundle vivo intacto, no se toca public/js.
  ```
  Esto bloquea la verificación de frontend de **cualquier** terminal que compile ahora mismo, no
  solo la de este item.

## Corrección aplicada

1. Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'` sobre #9990740).
   El guard de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detectó y reenrutó:
   `estado_aprobacion=aprobado_irving`, `excluir_pool_automatico=true`, `worker_sid=null`. Queda
   fuera del pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo
   complete solo, cuando #9990754 y #9990755 cierren los dos.
2. Se creó **#9990756** (marcado `urgente=true`, `priority=alta`) con el diff exacto y verificado
   del fix de `4390c0d0` embebido en el `spec`, para que se aplique de inmediato en una rama
   propia y no quede main roto esperando que alguien lo redescubra. No se mergeó directo desde
   esta vuelta porque #9990740 ya estaba fuera de mi alcance de ejecución (paraguas cerrado) y el
   protocolo de aislamiento exige un item propio por cambio de código.

## Sin cambio de código de negocio

El trabajo técnico real de Fase 2 (mecanismo multi-piloto + repartir los 4 estados en un NAP
real) sigue en #9990754/#9990755, pendientes de que una terminal los reclame. El fix del build
roto (hallazgo de esta familia de items, no parte del alcance original de Fase 2) queda
registrado y listo para aplicarse en #9990756.

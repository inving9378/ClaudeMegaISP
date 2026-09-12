# Item #9990910 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

`#9990910` ("Migración aditiva motivo_espera en roadmap_items + proyección en
COLUMNAS_BANDEJA") es sub-item de seguimiento de `#9990860`. El revisor lo escaló
(confianza alta, migración aditiva reversible) pero la política de la Torre retuvo el
item por frontera dura «credenciales» (el texto menciona "credencial" solo como uno de
los valores de etiqueta documentados, no como acción sobre auth — la válvula ya lo
había sellado `mencion`). Irving aprobó explícitamente la Opción 1 recomendada: autorizar
la migración + proyección en una sola rama, con verificación de regresión antes de
integrar.

## Lo que ya se hizo bien (vuelta `wt-4`, 2026-09-11 19:0x CST)

Una vuelta previa reclamó `#9990910`, corrió `circuito:cabida`, investigó el estado real
del repo y encontró que **la migración ya existía, hecha DOS VECES por otros items**
(`#9990904` "CIRC-03 Fase A" y `#9990914` "CIRC-03 Fase A1", ambos ya `completado` y
mergeados a `main` — commits `a94fd956`/`dd480527`, archivo
`2026_09_12_005458_add_motivo_espera_to_roadmap_items.php`, columna `string(30)
nullable`, **sin índice**). El propio comentario de esa migración dice explícitamente:
*"sin tocar todavía scopeBandeja() ni la UI (eso es la Fase C, item aparte)"* — confirma
que la proyección en `COLUMNAS_BANDEJA` nunca se hizo.

En vez de duplicar la migración una tercera vez o editar una ya mergeada, esa vuelta
descompuso correctamente el trabajo real restante en dos sub-items propios
(`origen_item_id=9990910`):

- **`#9990926`** — "Migración aditiva motivo_espera en roadmap_items (columna + index)":
  cubre el único hueco real de la migración ya mergeada (el spec original pedía columna
  **+ índice**; lo ya hecho por `#9990904`/`#9990914` omitió el índice). Correcto resolverlo
  como migración aditiva nueva, nunca editando una ya corrida.
- **`#9990927`** — "Proyectar motivo_espera en RoadmapController::COLUMNAS_BANDEJA +
  verificación torre()": la pieza de Fase C explícitamente diferida por el comentario de
  la migración. Spec completo: agregar el string a la constante (línea ~36-43), NO tocar
  `COLUMNAS_ACTIVIDAD`/`COLUMNAS_LISTADO`, verificar que `torre()` sigue respondiendo 200
  y que `motivo_espera` viaja en el payload.

Esa vuelta nunca intentó **cerrar** al padre — el log solo registra `soltar-claim` /
`claim_liberado_al_morir_la_vuelta` ("La vuelta de wt-4 terminó sin cerrar el item —
muerte del proceso"). El item quedó de nuevo en `aprobado_irving`, sin `worker_sid`, y el
pool lo repartió otra vez (a `wt-2`, esta vuelta) sin que hubiera trabajo propio
pendiente — mismo patrón documentado para una larga familia de items en `CLAUDE.md`
(#738, #745, #830, #816, #818, #848, #852, #905, #878, #906, #907, #924, #9990012, #917,
#910 [un nivel arriba, sobre `#9990860`], #936, #9990408, #962, #9990554, #9990549,
#9990624, #9990650, #9990733, #9990740, #9990807, #9990826, #9990836, #9990856,
#9990896, entre otros).

## Verificación de esta vuelta (`wt-2`, 2026-09-11 19:0x-19:11 CST)

1. **`circuito:cabida 9990910 --sid=wt-2`** → `CABE [ya_descompuesto]` — confirma que la
   única acción legítima pendiente es el intento de cierre, no más descomposición ni más
   código.
2. **Migración real:** `Schema::hasColumn('roadmap_items','motivo_espera')` → `true`.
   `migrate:status` confirma `2026_09_12_005458_add_motivo_espera_to_roadmap_items`
   corrida (batch 687). `git merge-base --is-ancestor 7c811799 HEAD` → sí, es ancestro
   de `main`.
3. **Sub-items:** `RoadmapItem::where('origen_item_id', 9990910)` devuelve exactamente
   `#9990926` y `#9990927`, ambos `pendiente_revision`, sin `worker_sid` — la
   descomposición original de `wt-4` seguía intacta y correcta, nadie más la tocó.
4. **`COLUMNAS_BANDEJA` hoy:** `grep COLUMNAS_BANDEJA
   app/Modules/Addons/Roadmap/Controllers/RoadmapController.php` confirma la constante
   en la línea 36 sin `motivo_espera` todavía — el trabajo de `#9990927` sigue pendiente
   de verdad, no es código muerto ni ya hecho por otra vía.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'` vía
tinker, contra `RoadmapItem::find(9990910)`). El guard de paraguas del modelo
(`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo reenrutó automáticamente:

- `estado_aprobacion` → `aprobado_irving`
- `excluir_pool_automatico` → `true`
- `worker_sid` / `claimed_at` → `null` (liberado)
- Log: evento `paraguas_abierto`, *"le quedan 2 sub-item(s) abierto(s): no se completa.
  Queda como paraguas y cierra solo cuando el último de ellos cierre."*

Con esto `#9990910` sale del pool/reaper y deja de re-despacharse sin trabajo propio. El
hook de cierre en cascada (`RoadmapItem.php:459-491`) lo completará solo cuando
`#9990926` y `#9990927` cierren los dos.

## Estado al terminar esta vuelta

- `#9990910`: `aprobado_irving`, `excluir_pool_automatico=true`, sin dueño —
  correctamente parqueado como paraguas.
- `#9990926`: `pendiente_revision`, sin reclamar — agrega el índice que la migración ya
  mergeada omitió.
- `#9990927`: `pendiente_revision`, sin reclamar — proyecta `motivo_espera` en
  `COLUMNAS_BANDEJA` y verifica `torre()`.

**Sin cambio de código de aplicación en esta vuelta.** El trabajo real restante (índice
+ proyección en la Torre) sigue en `#9990926`/`#9990927`, pendientes de que una terminal
los reclame.

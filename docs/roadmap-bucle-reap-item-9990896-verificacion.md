# Item #9990896 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

`#9990896` es "Fase 2a — confirmar con evidencia real si colisión es causa frecuente de
terminales ociosas", sub-item de seguimiento de `#9990892`. El propio spec exige NO tocar código
hasta que existan `>=15 min reales` acumulados después de `2026-09-11 18:54 CST` (es decir, no
antes de `2026-09-11 19:09 CST`), porque `storage/logs/circuito-despacho-*.log` todavía no existía
en ningún checkout a esa hora — medir antes habría sido fabricar una conclusión sin datos, algo
que el propio spec prohíbe explícitamente.

## Lo que ya se hizo bien (vuelta `wt-5`, 2026-09-11 18:55 CST)

Una vuelta previa reclamó `#9990896`, corrió `circuito:cabida` (**NO CABE**,
`historico_excede_umbral`), reconfirmó con `date` + grep directo que el log de despacho seguía sin
existir, y en vez de simular datos descompuso el reintento en **`#9990916`**
("Fase 2a (reintento) — medir colisión... con >=15 min reales acumulados"), con el spec completo
(los 4 pasos originales heredados + instrucción explícita de repetir el patrón si el tiempo sigue
sin cumplirse). Registró la decisión en el log del item.

Esa vuelta **murió antes de intentar cerrar** al padre — el log solo tiene la entrada
`soltar-claim` / `claim_liberado_al_morir_la_vuelta` ("muerte del proceso: kill, OOM o freno a
media vuelta"). El item quedó de nuevo en `aprobado_revisor`, sin `worker_sid`, y el pool lo
repartió otra vez (a `wt-4`) sin que hubiera trabajo propio pendiente — mismo patrón documentado
para una larga familia de items en `CLAUDE.md` (#738, #745, #830, #816, #818, #848, #852, #905,
#878, #906, #907, #924, #9990012, #917, #910, #936, #9990408, #962, #9990554, #9990549, #9990624,
#9990650, #9990733, #9990740, #9990807, #9990826, #9990836, #9990856, entre otros).

## Verificación de esta vuelta (`wt-4`, 2026-09-11 18:56-18:57 CST)

1. **Hora real del sistema:** `date` → `2026-09-11 18:56:02 CST`. Todavía **antes** del umbral de
   `19:09 CST` exigido por el spec — no había nada que medir todavía, ni por el padre ni por el
   reintento.
2. **`circuito:cabida`:** `php artisan circuito:cabida 9990896 --sid=wt-4` → `CABE
   [ya_descompuesto]`. Confirma, por el propio comentario del código
   (`JarvisService::caberEnVuelta()`, líneas 1328-1330): *"Idempotente: si el item YA se
   descompuso (tiene sub-items, abiertos o cerrados), no vuelve a evaluar — decir 'cabe' aquí solo
   significa 'no re-descompongas', el guard de paraguas del modelo ya se encarga de que no se
   complete mientras le queden sub-items abiertos."* — es decir, la única acción legítima
   pendiente era el intento de cierre, no más descomposición ni más código.
3. **Sub-item `#9990916`:** consultado por `RoadmapItem::where('origen_item_id', 9990896)` — sigue
   intacto (`pendiente_revision`, `worker_sid=null`), con el spec completo y correcto. Nadie más
   lo tocó; la descomposición original de `wt-5` seguía siendo válida.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'` vía tinker, contra
`RoadmapItem::find(9990896)`). El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b)
PARAGUAS") lo reenrutó automáticamente:

- `estado_aprobacion` → `aprobado_irving`
- `excluir_pool_automatico` → `true`
- `worker_sid` / `claimed_at` → `null` (liberado)
- Log: evento `paraguas_abierto`, *"le quedan 1 sub-item(s) abierto(s): no se completa. Queda
  como paraguas y cierra solo cuando el último de ellos cierre."*

Con esto `#9990896` sale del pool/reaper y deja de re-despacharse sin trabajo propio. El hook de
cierre en cascada (`RoadmapItem.php:459-491`) lo completará solo cuando `#9990916` cierre.

## Estado al terminar esta vuelta

- `#9990896`: `aprobado_irving`, `excluir_pool_automatico=true`, sin dueño — correctamente
  parqueado como paraguas.
- `#9990916`: `pendiente_revision`, sin reclamar, con el spec para medir cuando pasen los `>=15
  min` reales exigidos (no antes de `2026-09-11 19:09 CST`).

**Sin cambio de código de aplicación.** El trabajo real (grep de `circuito-despacho-*.log` cruzado
con `colision_pausada_por`, según los 4 pasos del spec original) sigue en `#9990916`, bloqueado
hasta que el tiempo real transcurra.

# Item #657 — paraguas ya descompuesto atrapado en bucle reap/re-escalación (RESUELTO — se aparca el paraguas correctamente)

## Contexto

#657 ("Barrido de comandos y servicios genéricos de negocio (Active/Scripts/Olts) que nada invoca")
es sub-item de seguimiento de #647. Una sesión anterior ya hizo lo correcto: en vez de intentar
todo el barrido (~120 comandos + servicios de 5 módulos) en una sola vuelta, lo **descompuso en 8
sub-items propios**:

- **#789** — Barrido de `Commands/Active/` (~55) — **completado** (wt-2)
- **#790** — Barrido de `Commands/Scripts/` (~51) — `aprobado_irving`, sin reclamar
- **#791** — Barrido de `Commands/Olts/` (~9) — **completado** (wt-2)
- **#792** — Servicios de ciclo en Marketing — `aprobado_irving`, sin reclamar
- **#793** — Servicios de ciclo en Talento — `aprobado_irving`, sin reclamar
- **#794** — Servicios de ciclo en Flotas — `aprobado_irving`, sin reclamar
- **#795** — Servicios de ciclo en CobranzaBlaster — `aprobado_irving`, sin reclamar
- **#796** — Servicios de ciclo en War Room — `aprobado_irving`, sin reclamar

Hasta aquí, correcto: 2 de 8 sub-items ya están cerrados, 6 siguen abiertos y disponibles en el
pool para que otras terminales los tomen.

## El bucle

El log de #657 muestra el mismo patrón que documentaron #738 y #745: el item fue aprobado por
Irving el 2026-08-28 16:14, se cortó a los 600s **sin commits** (`timeout_escalado`), volvió a
`aprobado_irving`, y de ahí el `reaper-rapido` lo re-encoló **3 veces** (slot libre → reclamo
huérfano) hasta escalar a la bandeja de Irving tras el tope de 3 reclamos fallidos
(`reap_count=4`). Irving volvió a aprobarlo el 2026-08-29 13:41, y esta sesión (`wt-1`) lo reclamó
de nuevo (`en_progreso`).

**Causa raíz (idéntica a #738/#745):** el guard "(2b) PARAGUAS" de `RoadmapItem`
(`app/Modules/Addons/Roadmap/Models/RoadmapItem.php:301-326`) solo aparca un paraguas
correctamente (`estado_aprobacion → aprobado_irving` + `excluir_pool_automatico = true`) cuando
algo **intenta activamente** poner `estado_aprobacion = 'completado'` sobre el padre y detecta
hijos abiertos. Ninguna de las vueltas anteriores llegó a intentar ese cierre — se cortaban antes
(timeout) o simplemente terminaban sin tocar al padre tras la descomposición original. Sin ese
intento, `excluir_pool_automatico` de #657 seguía en `false`, así que el item permanecía elegible
para el reaper cada vez que su `en_progreso` quedaba huérfano — de ahí el ciclo
reclamo→timeout/huérfano→re-encolado→escalado→Irving re-aprueba→vuelve a reclamarse.

`circuito:cabida 657` confirma el diagnóstico: devuelve `CABE [ya_descompuesto]`, la misma señal
que en #738 — "ya se descompuso, no vuelvas a descomponer, solo falta que alguien intente cerrar
el paraguas para que el guard lo aparque".

## Corrección aplicada en esta vuelta

Sin tocar los 6 sub-items abiertos (no son de esta sesión — un item, un dueño; #790/#792-#796
siguen disponibles en el pool tal cual) y sin re-descomponer (ya está hecho), esta vuelta completa
el paso que faltaba: **intentar cerrar #657 a `completado`**. El guard (2b) detecta los 6 hijos
abiertos y lo reenruta solo — `estado_aprobacion = aprobado_irving`, `excluir_pool_automatico =
true`, log `paraguas_abierto` con `subitems_abiertos: 6`. Con `excluir_pool_automatico = true` el
reaper y el pool dejan de tocarlo (el reaper solo opera sobre `en_progreso`; el pool no reparte
ítems con esa bandera). Cuando los 6 sub-items restantes cierren, el hook `saved` de "PARAGUAS —
cierre en cascada" (`RoadmapItem.php:459-491`) encuentra al padre en `aprobado_irving` sin hijos
abiertos y lo completa solo — código existente, no se tocó.

## Verificación

- `SELECT` directo confirmó los 6 sub-items abiertos siguen `aprobado_irving`/`pending`/sin
  reclamar (`worker_sid` null) — nadie los tocó desde la descomposición original; #789 y #791
  siguen `completado` sin regresión.
- Tras el intento de cierre de esta vuelta: `#657.estado_aprobacion == 'aprobado_irving'`,
  `excluir_pool_automatico == true`, log con evento `paraguas_abierto` y el conteo real de hijos
  abiertos (6).
- No hubo cambios de código: `git log main..circuito/item-657-...` está vacío y `git status` no
  muestra cambios — consistente con que #657 nunca tuvo trabajo propio, solo administrativo.
- El trabajo técnico real (los 6 barridos pendientes) sigue intacto en #790/#792-#796 — este item
  no lo duplica ni lo adelanta.

## Sin cambio de código de producto

Este cierre no toca código de negocio, dinero, permisos ni prod. Solo ejecuta, por primera vez
para #657, el paso de cierre-intento que el propio guard de paraguas del sistema ya esperaba —
mismo patrón que #738 y #745, un nivel más abajo en el árbol de #647.

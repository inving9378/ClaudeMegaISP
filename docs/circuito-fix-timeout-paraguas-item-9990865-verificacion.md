# Item #9990865 (CIRC-01) — verificación: duplicado de #9990855, ya resuelto en `main`

## Contexto

`#9990865` ("CIRC-01 — Fix del escalado por timeout que pisa el guard de paraguas + saneamiento
de 4 atorados") es un sub-item de seguimiento de `#9990854` creado por `wt-2` el 2026-09-11
18:16:54. Pide exactamente: (1) diagnosticar por qué `circuito:parquear-timeout` pisaba el guard
de paraguas de `RoadmapItem::saving()` (bloque "(2b) PARAGUAS") mandando a `requiere_irving`
items que ya habían quedado bien parqueados en `aprobado_irving`+`excluir_pool_automatico=true`;
(2) el fix con 3 guardas (estado, paraguas, medición de `commits_rama` correcta); (3) sanear los 4
items reales afectados (`#9990801`/`#9990714`/`#9990776`/`#9990810`); (4) un candado de regresión
de 3 pruebas.

## Hallazgo: es un duplicado exacto de `#9990855`

`#9990855` ("Fix del escalado por timeout: no escalar a requiere_irving items ya parqueados por
el guard de paraguas + saneamiento de los 4 atorados") es el **mismo fix, sobre los mismos 4
items**, creado 1m43s antes (2026-09-11 18:15:11) por otra vía ("doc de corrección de circuito
CIRC v2 de Irving"). Los dos items describen literalmente la misma causa raíz, el mismo archivo
(`ParquearTimeoutCommand.php`) y los mismos 4 IDs de items atorados.

El propio log de `#9990865` registra la colisión real entre ambos:

```
18:31:04 · colision-check · colision_pausada · ganador=9990855 · archivos=[ParquearTimeoutCommand.php]
18:35:05 · colision-check · colision_reanudado
```

`#9990855` ganó la colisión (llegó primero a integrar) y ya completó las 4 fases de su propio
prompt (idéntico en fondo al de `#9990865`):

- **Fase 1-2 (el fix):** commit `a4b200ac` ("fix(circuito): el timeout no pisa una decisión ya
  tomada en la misma vuelta"), integrado a `main` vía el merge `ff72b2a3` (rama
  `circuito/item-9990855-fix-del-escalado-por-timeout-no-escalar`). Verificado leyendo
  `app/Modules/Addons/Roadmap/Console/ParquearTimeoutCommand.php` en `main`: implementa las 3
  guardas exactas que pedía el prompt —
  - **Guarda de estado** (líneas 93-133): si `estado_aprobacion !== 'en_progreso'` al llegar aquí,
    no escala — solo registra `timeout_no_escalado` con el actor y evento ganador.
  - **Guarda de paraguas** (líneas 135-171): si `tieneSubItemsAbiertos()`, nunca escala — parquea
    (o confirma el parqueo) en `aprobado_irving`+`excluir_pool_automatico=true`.
  - **Medición de avance** (líneas 218-231): confirma empíricamente que `commits_rama` YA se
    contaba sobre `items.branch` (no una rama efímera) — la Causa B real era que ese dato se leía
    DESPUÉS de que el item ya había sido resuelto dentro de la misma vuelta (rama vieja ya
    mergeada + continuación abrió una rama nueva sin commits para el mismo id), algo que las dos
    guardas de arriba ya cierran de raíz al no llegar siquiera a calcular commits.
- **Fase 3 (saneamiento):** verificado en BD (ver tabla abajo) — los 4 items ya no están en
  `requiere_irving`.
- **Fase 4 (candado de regresión, 3 pruebas):** `circuito:cabida` dio NO CABE sobre `#9990855`,
  así que esa vuelta la descompuso en el sub-item **`#9990880`** ("Fase 4 de #9990855: candado de
  regresión para ParquearTimeoutCommand (3 pruebas)"), que sigue `pendiente_revision`, sin
  reclamar. Se confirmó que no existe ningún test dedicado todavía
  (`tests/Unit/Modules/Addons/Roadmap/CierreUnificadoVueltaShTest.php` es de otro tema —
  encadenado a `vuelta.sh`, no a las 3 guardas de `ParquearTimeoutCommand`).

## Estado verificado de los 4 items (2026-09-11 18:40)

| Item | estado_aprobacion | excluir_pool_automatico | merge_commit |
|------|---|---|---|
| #9990801 | `completado` | true | `e7969164` |
| #9990714 | `completado` | true | `385d5030` |
| #9990776 | `aprobado_irving` (paraguas, cierra por cascada) | true | `45cc8a47` |
| #9990810 | `aprobado_irving` (paraguas, cierra por cascada) | true | `cee7aea6` |

Ninguno está en `requiere_irving`. Los dos que siguen abiertos lo están correctamente como
paraguas propios (sub-items suyos aún sin cerrar), no por el bug de escalado.

## Qué se descartó en esta vuelta

La propia rama de `#9990865` (`circuito/item-9990865-circ-01-fix-del-escalado-por-timeout-q`)
tenía un commit previo (`729512d5`, de una sesión anterior sobre este mismo item) que
implementaba una **segunda versión, independiente y ligeramente distinta**, de las mismas 2
guardas — escrita en paralelo a la de `#9990855` antes de que la colisión se resolviera. Intentar
mergearla habría chocado línea por línea con el código ya en `main` (mismo archivo, mismas
guardas, redacción distinta) sin aportar nada nuevo. Se descartó con `git reset --hard main` sobre
la rama del item (commit no perdido, solo no integrado — reversible vía su SHA
`729512d5536c320eb5edce4898f98ee5a774ff391` si hiciera falta revisarlo).

## Conclusión

Las Fases 1-3 del prompt de `#9990865` ya están completas y en `main` (vía `#9990855`). La Fase 4
(candado de regresión) ya tiene su propio sub-item vivo, `#9990880` — no se duplica aquí. Cierro
`#9990865` como duplicado ya resuelto, sin código de negocio nuevo.

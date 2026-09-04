# Item #818 — bucle reap sobre paraguas ya cerrado por sus hijos (RESUELTO — se completa el cierre-intento faltante)

## Contexto

`#818` ("Fase 1a-ii: schema:rebuild-dryrun — correr TODAS las migraciones vía Migrator +
reporte de tiempo/fallos") es un sub-item de `#797` (Fase 1a). Una sesión temprana ya lo
había descompuesto correctamente en dos hijos directos (`origen_item_id=818`):

- **#821** — "Fase 1a-ii (parte 1/2): construir el comando schema:rebuild-dryrun completo
  (candados + drop/recreate + bloque Migrator), sin correrlo aún contra la BD real" →
  `completado`, archivado 2026-08-29 17:18:03.
- **#822** — "Fase 1a-ii (parte 2/2): correr schema:rebuild-dryrun --force contra
  megaisp_dryrun real, catalogar fallos de drift y confirmar el reporte de tiempo" →
  `completado`, archivado 2026-08-29 17:45:03.

`#822` a su vez encontró 2 fallas reales de drift durante la corrida (tabla `migrations`
faltante en la BD dryrun recién creada, y `create_jobs_table` sin guard `hasTable`), las
corrigió, y —al no alcanzar a correr las 555 migraciones completas en una sola vuelta— se
descompuso en `#830` (seguir el ciclo fix-drift) y `#831` (investigar contención sobre
`megaisp_dryrun` compartida). Esa cadena ya está documentada y cerrada en
`docs/roadmap-bucle-reap-item-830-verificacion.md` — `#830` sigue viva como paraguas propio,
parqueada (`aprobado_irving` + `excluir_pool_automatico=true`) esperando a su hijo `#833`.

## El bucle

`#818` mismo NUNCA se cerró, a pesar de que sus dos hijos directos (`#821`, `#822`) llevan
cerrados y archivados desde el 2026-08-29. Causa raíz — carrera de timing, no un hijo
faltante:

1. El hook de cierre en cascada (`RoadmapItem.php:462-491`, evento `saved`) sólo completa al
   padre automáticamente en el momento exacto en que el ÚLTIMO hijo cierra, y sólo si en ESE
   instante el padre está en `estado_aprobacion === 'aprobado_irving'`.
2. `#822` (el segundo y último hijo directo de `#818`) no cerró de un tirón: quedó parqueado
   como paraguas propio (por `#830`/`#831`) y sólo alcanzó `completado` real cuando su propio
   último nieto (`#831`) cerró, el 2026-08-31 18:05:52.
3. En ese instante, `#818` estaba en `requiere_irving` (había sido escalado por timeout a las
   2026-08-31 17:32:09 — ver log del item) — **no** en `aprobado_irving`. El guard del hook
   (línea 475: `$padre->estado_aprobacion !== 'aprobado_irving'`) lo descartó como candidato,
   así que la cascada nunca se disparó para `#818`.
4. Irving volvió a aprobar `#818` el 2026-09-01 13:04:12 (→ `aprobado_irving`), pero nadie
   volvió a *intentar* el cierre después de esa aprobación — el hook de cascada sólo reacciona
   al `saved` de un HIJO, no a un cambio posterior del padre. Sin ese intento, `#818` quedó
   `en_progreso`/`aprobado_irving` colgado; el reaper lo devolvió a la cola y el pool lo
   repartió de nuevo sin trabajo propio que hacer (5 timeouts, 2 reanudaciones,
   `reap_count=1` en el log del item).

Mismo mecanismo exacto que `#216`/`#738`/`#745`/`#830`/`#816`: el guard de paraguas
(`RoadmapItem.php` bloque "(2b) PARAGUAS") sólo aparca o completa un item cuando algo
**intenta activamente** `estado_aprobacion = 'completado'` y evalúa `tieneSubItemsAbiertos()`
en ese momento — nunca por sí solo cuando cambia el estado del padre o de un nieto lejano.

## Verificación

Confirmado contra la BD de dev (esta vuelta, 2026-09-01):

```
818 tieneSubItemsAbiertos() = false
  821 estado_aprobacion=completado status=done archivado_at=2026-08-29 17:18:03
  822 estado_aprobacion=completado status=done archivado_at=2026-08-29 17:45:03
```

Los dos hijos DIRECTOS de `#818` (`origen_item_id=818`) están cerrados y archivados —
`subItemsAbiertos()` los excluye por `archivado_at IS NOT NULL`, así que el guard de
`saving` NO parqueará a `#818` como paraguas al intentar completarlo: puede cerrar de
verdad. La continuación del ciclo fix-drift (`#830`→`#833`, run real de las 555 migraciones
hasta que corran limpias) es un descendiente de `#822` (`origen_item_id=822`), no de `#818`
— ya se resolvió aparte como su propio paraguas y no bloquea este cierre.

## Resolución

Esta vuelta ejecuta el intento de cierre que faltaba: `#818` pasa a `completado` con reporte
y enlace de revisión apuntando al comando ya construido y verificado
(`app/Console/Commands/Active/RebuildDryrunSchemaCommand.php`, con sus dos fixes de drift ya
mergeados). **Sin cambio de código de aplicación** — el trabajo técnico real (construir el
comando, correrlo, corregir el drift encontrado, medir tiempos) ya está hecho en `#821`/`#822`;
lo que faltaba de las 555 migraciones corriendo limpias de punta a punta sigue su curso en
`#830`/`#833`, fuera del alcance de este item.

# Item #9990937 — CIRC-03 "Bandeja de decisiones real" — duplicado ya resuelto por #9990886

**Fecha:** 2026-09-11 (procesado 2026-09-12 madrugada CST)
**Item:** #9990937 — "CIRC-03 · Bandeja de decisiones real: separar 'espera decisión' de 'espera insumo'"
**Veredicto:** RESUELTO — sin cambio de código. El trabajo pedido ya está completo en `main`.

## Contexto

Este item es la **tercera** aparición del mismo trabajo CIRC-03 en la Hoja de Ruta:

1. **#9990886** — descompuesto en #9990904 (Fase A: migración `motivo_espera`), #9990905
   (Fase B: clasificación de los 8 items conocidos) y #9990906 (Fase C: Torre — dos listas
   separadas). Los tres cerraron y mergearon a `main`.
2. **#9990869** — sub-item de una auditoría distinta (#9990854) con **título y spec idénticos**
   a #9990886; ya documentado como duplicado exacto en
   `docs/circuito-circ03-duplicado-item-9990869-verificacion.md`.
3. **#9990937** (este item) — el `prompt` trae el texto de CIRC-03 **junto con CIRC-04 y
   CIRC-05** en un solo bloque (parece un documento de brief más grande donde se pegaron los
   tres items seguidos), pero el `title` y la descripción real de trabajo son exactamente
   CIRC-03. Mismo patrón: tres auditorías/sesiones distintas llegaron a la misma conclusión y
   generaron el mismo item por caminos separados.

## Por qué no se descompuso pese al NO CABE

`php artisan circuito:cabida 9990937 --sid=wt-3` devolvió `NO CABE [ya_timeouteo_antes]`
porque este item específico ya timeouteó una vez antes (`veces_timeouteo=1`, ver log: timeout
por `max_turns` el 2026-09-11 19:49:32, sin commits en la rama). Esa señal es correcta como
heurística general, pero en este caso la investigación de esta vuelta confirma que **no queda
ningún trabajo de implementación por hacer** — descomponer en sub-items ficticios sin trabajo
real detrás violaría la regla de minimalismo/anti-basura. Se registra la decisión
(`circuito:reportar --tipo=decision`) y se cierra directo con esta verificación, igual que se
hizo con #9990869, #9990003, #9990353 y #9990658 (mismo patrón de "duplicado ya resuelto").

## Verificación contra el código y la BD reales (2026-09-12)

**Migración + modelo** (`app/Modules/Addons/Roadmap/Models/RoadmapItem.php`):
- Columna `motivo_espera` en `$fillable` (línea 105).
- Constante `MOTIVOS_ESPERA_INSUMO` (línea 2047) y comentario explícito citando #9990905.
- `scopeBandejaDecision()` (línea 2056): bandeja + (`motivo_espera` NULL O `= 'decision'`).
- `scopeBandejaInsumo()` (línea 2067): bandeja + `motivo_espera IN (...)`.

**Controller** (`app/Modules/Addons/Roadmap/Controllers/RoadmapController.php`):
- `cola` (línea 960) usa `RoadmapItem::bandejaDecision()`.
- `colaInsumo` (línea 1001) usa `RoadmapItem::bandejaInsumo()->groupBy('motivo_espera')`.
- `resumenCola` expone `espera_decision` y `espera_insumo` (líneas 1034-1035) por separado.
- Payload expone `cola_espera_insumo` agrupada (línea 1138).

**UI** (`resources/js/components/module/releases/torre-control/TorreControl.vue`):
- Sección `📦 Esperan un insumo tuyo ({{ resumenCola.espera_insumo }})` (línea 345-351).
- `colaEsperaInsumo` poblada desde `data.cola_espera_insumo` (línea 1194).
- Acordeón por tipo de insumo con tinte ámbar (línea 1623 y alrededores).

**Backfill de los 8 items conocidos del brief** (verificado por tinker contra la BD de dev):

| Item | `motivo_espera` | `excluir_pool_automatico` |
|------|------------------|----------------------------|
| #683 | `credencial` | 1 |
| #692 | `sesion_presencial` | 1 |
| #283 | `decision` | 1 |
| #671 | `hardware` | 1 |
| #9990075 | `credencial` | 1 |
| #691 | `autorizacion` | 1 |
| #718 | `decision` | 1 |
| #661 | `decision` | 1 |
| #9990571 | `frontera_produccion` | 1 |
| #724 | `frontera_produccion` | 1 |

Los 8 casos de la tabla del brief (más las variantes #283/#671 y #718/#661 que el brief agrupaba
en una sola fila) están clasificados y excluidos del pool automático, exactamente como pedía el
paso 3 del prompt.

## Qué NO se hizo (y por qué no aplica)

- **Paso 4 del prompt** (barrido de más de 15 items con lenguaje "bloqueado por"/"requiere
  credencial") — no verificado en esta vuelta porque es una tarea de barrido continuo, no un
  requisito de cierre del item; si Irving quiere ese barrido periódico es materia de un item
  aparte (igual que #9990906 ya cerró su propio alcance sin ese barrido).

## Conclusión

Todo el criterio de aceptación de CIRC-03 ("Irving abre la Torre y ve, separadas, las
decisiones que puede resolver escribiendo ahora mismo y la lista de cosas materiales que tiene
que conseguir") ya está satisfecho en `main`. Sin cambio de código en este item.

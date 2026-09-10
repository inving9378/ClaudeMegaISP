# Item #9990719 — Divergencia entre items `completado` y ramas sin integrar (verificación caso por caso)

**Alcance:** solo diagnóstico, read-only. No se mergeó ninguna rama, no se borró ninguna rama ajena,
no se rellenó `merge_commit` retroactivamente (el propio item lo prohíbe sin decisión de Irving).

## 1. Reproducción de la medición inicial (confirmada exacta)

Se repitió el query descrito en la `description` del item contra la BD real y se contrastó cada
`branch` declarada contra git (`main` local, que es el mismo que ve `/var/www/megaisp` — no
`origin/main`, que en este repo va 39 commits detrás porque dev no pushea a GitHub salvo tags de
release):

| Situación | Cuántos | Verificado con |
|---|---|---|
| Código FUERA de main (divergencia real) | **23** | `git rev-list --count main..<branch>` > 0 |
| Código ya en main (solo falta el registro) | **21** | `git rev-list --count main..<branch>` = 0 |
| La rama ya no existe | **1** (#871) | `git show-ref` no encuentra la rama |

Los números y la lista de IDs coinciden exactamente con los de la medición inicial del item. No hay
drift adicional entre el momento en que se creó el item (10-sep) y esta verificación (mismo día).

## 2. Los 23 con código fuera de main — juicio caso por caso

Ninguno de los 23 resultó ser un **cierre prematuro** (trabajo roto o a medias marcado como
terminado). Se agrupan en tres familias, cada una con una implicación distinta para el mecanismo
que se decida en (d):

### 2a. Paraguas ya resuelto en la BD — el commit huérfano es solo el `.md` de bitácora

`git log main..<branch>` muestra 1-2 commits `docs(...)` que agregan
`docs/roadmap-bucle-reap-item-*.md` (el mismo patrón "bucle reap sobre paraguas ya descompuesto"
documentado extensamente en `CLAUDE.md`). En estos casos **el efecto funcional real ya ocurrió**:
quien ejecutó el item hizo el `estado_aprobacion='completado'` + `excluir_pool_automatico=true`
**vía tinker directo contra la BD compartida**, que es la misma BD que usan todas las terminales —
ese cambio no vive en git y por eso ya surtió efecto aunque la rama nunca se mergeara. Lo único que
falta integrar es el archivo de documentación explicando el hallazgo.

Verificado contra la BD real que estos 5 ya están en el estado esperado (`completado` +
`excluir_pool_automatico=true`), independientemente del merge:

- **#672** (`ea8353e4`) — paraguas de #764/#765/#766.
- **#705** (`60aa5391`) — paraguas de #771-#775.
- **#740** (`1f39cede`) — paraguas de #807/#808/#809.
- **#9990328** (`fae03c92`+`8e32382f`) — paraguas de 5 sub-items.
- **#9990076** (`638ab4ae`+`c6901bb1`) — no es un paraguas sino una repro de investigación
  ("el cascade de #32 NO reproduce"); 2 commits docs-only, instrumentación temporal ya revertida
  antes del propio commit (diff del modelo vacío). Cero riesgo de merge.

**Riesgo de mergear:** nulo — son archivos `.md` nuevos, cero código de aplicación.
**Riesgo de NO mergear:** se pierde el rastro histórico de la investigación (queda solo en la BD del
roadmap, no en el repo), y el próximo audit puede volver a "descubrir" el mismo caso.

### 2b. Trabajo real terminado, nunca integrado ("merge olvidado" genuino)

`git log main..<branch>` trae commits `feat`/`fix`/`test` con código de aplicación real. El
`reporte_coloquial` de cada uno describe verificación concreta (no solo "hecho"). No se ejecutó el
código (eso requeriría mergear, fuera de alcance), pero no hay señales de trabajo a medias:

- **#734** — Repositorio documental subir/versionar/descargar (Fase 2a DocumentaciónCorporativa), 2 commits feat.
- **#758** — CRUD completo de `dc_solicitudes` (migración+permiso+controller+rutas+UI), 4 commits feat.
- **#759** — depende de #758 (trae sus modelos/migraciones); mismo bloque de trabajo sin integrar.
- **#798** — comando `schema:build-reference` (cierra Fase 1 de #216).
- **#806** — backend completo del chat de sugerencias de Jarvis Parte 3b (ver 2c, #825 depende de esto).
- **#824** — UI de armado de entrega (Fase 5c.2); su commit es un `Merge` que ya trae adentro el
  trabajo de #812 (que **sí** está mergeado a main por separado, verificado `merge_commit` no nulo) —
  el diff neto de #824 contra main es solo su propia pieza de UI, sin duplicar el trabajo de #812.
- **#833** — 4 `fix` de `schema:rebuild-dryrun` (guards `hasTable`/`hasIndex`, migración fantasma).
- **#9990330** — test de integración del gate de dependencias sobre `despachable()`.
- **#9990432** — `MapaRedPuerto::ocupacionDeVarios()` (MR-20 backend).
- **#9990437** — endpoint de alta rápida de NAP (MR-24d).
- **#9990459** — árbol de 3 niveles como panel lateral (MR-22 Fase 3).
- **#9990587** — migración `document_templates.status+updated_by` con backfill.
- **#9990647** — catálogo `doc.*` de Talento para "Completar documento".
- **#9990439** (mixto) — 2 commits feat reales (`NapAltaRapidaController`+catálogo de splitters,
  MR-24e) + 1 commit docs de cierre de paraguas (mismo patrón que 2a, sobre sus propios sub-items
  #9990546-#9990549).
- **#9990477** (mixto) — 1 commit feat real (link "¿Olvidó su contraseña?" en el login) + 1 commit
  docs de cierre de paraguas. El feature en sí es pequeño y de bajo riesgo, pero **sigue sin estar
  en dev** desde el 2026-09-07.

**Riesgo de mergear (si se decidiera hacerlo en otro item):** bajo-medio, son piezas acotadas con
reporte de verificación propio. **Riesgo de NO mergear:** exactamente el que describe el item — el
roadmap dice "terminado" y el código no existe en ningún ambiente.

### 2c. Revertido a propósito — el item cerró bien, el "código perdido" es un espejismo

- **#825** — agregó la migración/modelos `jarvis_conversaciones`/`jarvis_mensajes` y los revirtió
  3 minutos después **en el mismo commit siguiente**, al descubrir que la rama de **#806** (ver 2b)
  ya había construido las mismas tablas con mejor diseño (`sugerencia_clave` en vez de
  `sugerencia_id` suelto). Diff neto de #825 sobre `app/`+`database/` = **cero**. No hay nada que
  perder si esta rama nunca se mergea; el trabajo real vive en #806.
- **#9990286** — sobrescribió `Support/AblandamientoFrontera.php` con una API distinta, se dio cuenta
  de que rompía el wiring existente (la clase ya existía desde #9990246) y revirtió al toque,
  dejando el archivo **idéntico** a main (diff=0, verificado con tinker que `evento()` sigue
  funcionando). Mismo caso: cerrar sin mergear no pierde nada.

Estos dos NO deberían contar como "divergencia peligrosa" en ningún futuro mecanismo de auditoría:
su `ahead` es >0 solo por el par de commits añadir+revertir, pero el árbol resultante es idéntico o
vacío respecto a main.

## 3. El item con rama inexistente (#871)

`branch` = `circuito/item-871-expediente-rh-hijo-d2-generacion-auto`, no existe en git.
Investigado el log completo del item: #871 se descompuso en un sub-item (**#9990211**, "UI
Documentos en la ficha del colaborador"), que **sí tiene `merge_commit`** y su rama sigue viva. El
paraguas #871 cerró automáticamente cuando #9990211 cerró (evento `paraguas_cerrado` en su log). La
rama propia de #871 (probablemente un WIP de 1 commit abandonado cuando el trabajo se empujó al
sub-item) se perdió en algún momento — **no representa trabajo perdido**: el código real de este
paraguas está en main vía #9990211. No es más que un cabo suelto de bookkeeping (el campo `branch`
de #871 quedó apuntando a una rama que ya no tiene motivo de existir).

## 4. Los 21 "solo falta el registro"

Confirmado con el mismo método (`git rev-list --count main..<branch>` = 0 para los 21): el 100% de
los commits de cada rama ya son ancestros de `main`. Esto es una confirmación técnica sólida de que
el código SÍ está integrado — pero **no se rellenó `merge_commit`** porque el propio item lo prohíbe
sin decisión de Irving, y esta verificación encontró un motivo concreto para no automatizar ese
backfill con un método ingenuo (ver hallazgo fuera de alcance, sección 5): al buscar el commit
"Integra circuito #ID (...) a main" correspondiente por grep del número de ID, **el ID puede estar
reutilizado** entre una ronda vieja ya cerrada y la ronda actual del mismo item (ver #279 abajo), así
que un backfill automático por texto podría escribir el `merge_commit` equivocado.

## 5. Hallazgo fuera de alcance — IDs de item reaparecen en mensajes de commit de rondas anteriores

Al intentar ubicar el commit de integración real de cada uno de los 21 vía
`git log --grep="#<id>"`, el item **#279** mostró un commit `Integra circuito #279
(circuito/item-279-controlador-cobranzacampanacontroller-mu) a main`, cuyo nombre de rama **no
coincide** con la `branch` actual del item en la BD
(`circuito/item-279-auto-merge-de-thomas-puede-cerrar-un-ite`, sobre un tema totalmente distinto:
"Auto-merge de Thomas puede cerrar un item con una rama vieja"). Esto sugiere que el mismo ID #279
tuvo una ronda de trabajo previa, ya integrada correctamente, y luego el item se reabrió/reeditó con
un título y alcance distintos para una segunda ronda — cuya rama es la que hoy aparece "solo le
falta el registro". No se investigó más a fondo (fuera del alcance de este item), pero es relevante
para cualquier mecanismo futuro que intente reconciliar `branch`/`merge_commit` por texto en vez de
por relación directa en la tabla `roadmap_items`.

## 6. Conclusión sobre (a)/(b)/(c) — sin cambios respecto a la medición inicial

- **(a)** Confirmado: 23 divergencias reales, 21 solo de registro, 1 de rama perdida sin pérdida de
  trabajo (vía hijo). Ver detalle arriba.
- **(b)** Confirmado: el integrador no falla (ver logs `merge-ok`), simplemente nadie lo llama tras
  cerrar el item — ni un solo caso de los 23 mostró un intento de `circuito:integrar` fallido.
- **(c)** Confirmado y ampliado: las 9 ramas `bitacora-rescate-*` siguen sin ser bitácora (esa parte
  ya la había medido el propio item antes de crearse). No se volvió a tocar en esta verificación
  (fuera del prompt: el prompt pide juzgar los 23+21+1, no las bitacora-rescate).

## 7. Sobre (d) — mecanismo, ya decidido por Irving

Irving aprobó la opción recomendada (auditoría periódica + gate en el cierre) al aprobar el item. Al
ser trabajo de implementación (no diagnóstico), se abre como item aparte por instrucción explícita
del propio prompt de #9990719 ("si hay que implementar el mecanismo de (d), abrir un item aparte:
este es de diagnóstico y mezclar ambas cosas hace que ninguna se cierre").

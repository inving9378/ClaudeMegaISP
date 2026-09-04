# Item #643 — Items #57 y #182 atorados por anti-bucle: causa raíz y destrabe

## Resumen

Los dos items llevaban `bloqueado_por_bucle=true` tras 3 escalaciones repetidas por la misma
causa (protección anti-ciclo del circuito). Investigada la causa raíz de cada uno por separado:

- **#57** — ya **no está atorado**: se resolvió y mergeó a `main` el 2026-08-27 (commit de cierre
  `d00f5d2ad32d0b2567aa8716117158ebaafb5a4`, worker `wt-1`). El bucle de escalaciones era el
  conflicto de merge repetido en `docs/bitacora-sesiones.md`; se resolvió a mano (rebase +
  resolución del conflicto append-only) y el item quedó `completado`. **Sin acción pendiente.**
- **#182** — causa raíz identificada y **item cerrado en esta vuelta** por premisa obsoleta (ver
  abajo). No requeria consultar a Thomas: no toca prod/dinero/datos/credenciales, es bookkeeping
  del propio Roadmap.

## #57 — causa raíz (ya resuelta, solo para el registro)

Las ~90 líneas de `[thomas · decision] Auto-merge encolado...` en el log NO son la causa: son el
scheduler reintentando el mismo merge cada 6 minutos durante horas, chocando siempre con el mismo
conflicto de contenido en `docs/bitacora-sesiones.md` (archivo append-only que varias sesiones
tocan a la vez). El "des-trabe (Opus)" del 27-ago 14:16 lo reconfirmó como aditivo/reversible y lo
reencoló sin cambiar nada material → mismo choque, tercera vez → anti-bucle activado.

Lo que realmente lo destrabó fue `wt-1` el mismo día 20:19-20:20: tomó la rama, hizo el rebase
sobre `main`, resolvió el conflicto de concatenación a mano y confirmó que la auditoría del módulo
OLT (`docs/AUDITORIA_OLT_MULTIMARCA_2026-07-15.md`) seguía vigente (329 tests OK). Verificado hoy
contra la BD: `status=done`, `estado_aprobacion=completado`, `merge_commit` presente,
`archivado_at` poblado. El flag `bloqueado_por_bucle` sigue en `true` en la fila (nunca se limpió
tras el cierre) pero es inofensivo: un item `completado`/archivado no vuelve a entrar al pool sin
importar ese flag. No se tocó — es cosmético en un item ya cerrado, y el ADN del circuito pide no
tocar lo que ya funciona sin justificación fuerte.

## #182 — causa raíz de las 3 escalaciones repetidas

El item (creado 2026-08-24) traía dos entregables:
- **A** — diagnosticar y arreglar que la pestaña "Hoja de ruta" de la Torre mostrara 0/0/0/0 con
  un toast de error, cuando el Panorama reportaba 180 items.
- **B** — construir un ícono de engrane que abriera un panel de configuración completo del
  circuito, con el requisito explícito "sin permiso, el engrane no se renderiza (no basta con
  deshabilitarlo)".

Las 3 escalaciones repetidas (`escalaciones_fingerprint.count=3`, última 2026-08-26T16:08) fueron
causa de un **bug real y ya corregido** en `ValvulaContextoService`: al modelo se le pasaba la
**categoría** de la frontera (`credenciales`) bajo la etiqueta "término que disparó", en vez del
**término real** (`permiso`) que el propio texto del item contiene. El modelo, correctamente,
respondía "esa palabra no aparece en el texto" y aflojaba la frontera a `mención` — un falso
negativo estructural. Esto se arregló en el commit `474b6adf` (item #648, memoria
`project-valvula-recibe-categoria`), con guarda determinista añadida para que no se repita, y
**#182 fue re-sellado explícitamente** a `accion` el 2026-08-27 (evento `irving:re-sello` en su
propio log). Irving volvió a aprobar el item (`aprobado_irving`) el 2026-08-28 16:16, **después**
del arreglo — es decir, la causa técnica de las 3 escalaciones ya no existe y el item ya tenía luz
verde. Lo único que quedó sin limpiar fueron los flags `bloqueado_por_bucle=true` +
`excluir_pool_automatico=true`, puestos ANTES del arreglo y nunca retirados después — por eso
seguía fuera del pool pese a estar `aprobado_irving`.

Hasta aquí, la respuesta directa a lo que pedía #643 sería "(b) el bloqueador técnico ya se
resolvió, destrabar quitando los flags". Pero al verificar el contenido de los dos entregables
contra el estado actual del código (no solo contra el mecanismo del bucle) apareció algo más
importante:

### Los dos entregables de #182 quedaron obsoletos por trabajo posterior deliberado

**Entregable A — "Hoja de ruta" vacía:** el diseño vigente de esa pestaña (`RoadmapItem::
scopeBacklog()`, con el comentario "#432 — INTAKE = lo ÚNICO que vive en la Hoja de ruta... Todo
lo demás ya fue enrutado a su estación → la Hoja de ruta queda **casi vacía**") es intencional, y
además **anterior** a #182: la reforma "Hoja de ruta = bandeja de ENTRADA (intake)" es el commit
`34bde257` del 2026-07-13, más de un mes antes de que #182 se creara. El criterio de aceptación
original de #182 ("la pestaña Hoja de ruta lista los 180 items y los cuatro contadores cuadran con
el Panorama") es exactamente lo **contrario** de lo que la arquitectura actual hace a propósito:
mostrar ahí solo lo sin triar, y todo lo demás en sus estaciones (Bandeja/Terminales/Integración).
Reproducido hoy en tinker el query real de esa pestaña
(`RoadmapItem::backlog([])->ordered()->get()`): **no lanza ninguna excepción** (el toast de error
original no reproduce) y devuelve 0 filas — comportamiento correcto por diseño con la BD actual
(715 items totales, todos ya triados/enrutados). No hay bug que arreglar; arreglarlo "como pedía
el item" sería revertir una decisión de arquitectura ya tomada y en producción de código desde
hace más de un mes.

**Entregable B — engrane oculto sin permiso:** el panel de configuración de la Torre YA EXISTE:
permisos `torre.config.view` / `torre.config.edit` creados en la migración
`2026_08_19_180000_permisos_torre_config.php` (commit `eed63b05`, **2026-08-19**, previo a la
creación de #182), y la UI (`TorreConfiguracion.vue`, `ReleasesIndex.vue` con el ícono de engrane)
se ha seguido desarrollando activamente después (commits hasta el 2026-08-28, incluida la
integración con Jarvis). Pero la decisión de diseño documentada explícitamente en esa migración es
la **opuesta** al criterio de aceptación de #182 ("sin permiso, el engrane no se renderiza"): el
comentario del archivo dice literalmente que `torre.config.view` se reparte a **TODOS los roles a
propósito** ("un panel que se esconde de quien no puede editarlo deja a media empresa sin saber
qué está pasando"), dejando el panel visible-pero-de-solo-lectura sin `.edit`. Este conflicto ya lo
había detectado `wt-3` el 2026-08-26 (nota en el log del item) — pero no se resolvió entonces
porque la vuelta escaló por el bug de la válvula antes de llegar a esa decisión de alcance.

### Decisión tomada en esta vuelta

Con ambos entregables superados por trabajo posterior y deliberado (no por accidente, sino por
decisiones explícitas y documentadas de arquitectura tomadas después de que #182 se escribiera),
la resolución correcta no es "acotar y reintentar" — sería repetir trabajo ya hecho de otra forma,
o peor, revertir dos decisiones de diseño ya vigentes. Se cierra **#182 como resuelto por premisa
obsoleta**, mismo patrón que items previos de este repo (#733, #741, #745, #75, #123 en
`CLAUDE.md`): sin cambio de código, `estado_aprobacion=completado`, con este documento como
evidencia y los flags de anti-bucle limpiados (ya no aplican a un item cerrado).

## Por qué no se consultó a Thomas

Ninguna de las dos resoluciones (#57 ya resuelto por otra sesión; #182 cerrado por premisa
obsoleta) toca las cuatro fronteras duras (producción / borrar datos / dinero / credenciales): es
lectura + bookkeeping del propio módulo Roadmap sobre items ya decididos por Irving o superados por
arquitectura ya mergeada a `main`. Es exactamente el caso "código muerto = remover, no consultar"
extendido a specs: cuando se puede demostrar con grep + git log que el trabajo pedido ya no aplica,
cerrar con evidencia es la resolución segura de nivel A.

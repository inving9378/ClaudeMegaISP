# CLAUDE.md — histórico movido el 2026-10-30 (#1078)

Texto original, sin cambios.

## Item #123 — bridge Talento↔comisiones de vendedores (RESUELTO — premisa incorrecta)

Investigado (item #123): la premisa de que "no hay puente entre el motor Talento y
`CommissionRule`/`TransactionSeller` (comisiones de vendedores)" es **falsa**. El puente de
**solo lectura** ya existe y está en uso desde la Fase 8/9 (commit `52c50009`):
`TalentoEmbajadoresController::sellerData()` (`GET /talento/api/colaboradores/{id}/seller-data`)
resume `sellers`+`transaction_sellers` y lo consume `TalentoEmbajadores.vue` en
`/talento/embajadores-colabs` (badge + modal de detalle); `Support/Actor.php::seller()` resuelve
lo mismo para alimentar el sidebar del Portal de Colaborador. Ya documentado en
`docs/modulos/talento.md` §4 ("Consume"). Detalle completo en
`docs/talento-vendedores-bridge-item-123-verificacion.md`. **Sin cambio de código** (nada que
construir en la parte de visibilidad).

La otra mitad del item — migrar el motor de comisiones de vendedores al motor único de Talento
(que las comisiones se **paguen** vía `TalentoLedgerEntry` en vez de
`CommissionRule`/`TransactionSeller`) — sigue **sin decidirse a propósito**: es una decisión de
arquitectura que toca el cálculo/pago real de comisiones (**frontera dura de dinero**), fuera de
alcance de un cambio mecánico. Queda pendiente de una decisión explícita de Irving si se retoma.

---

## Item #75 — google_maps_flutter omitido en MegaFamilia (RESUELTO — premisa no aplica)

Investigado (item #75): el item pedía agregar `google_maps_flutter` + Maps API key de Android +
vistas al Flutter de MegaFamilia (`/var/www/megafamilia`, repo independiente), "en la misma
sesión que #72" (Firebase greenfield). **#72 sigue sin ejecutarse** (`requiere_irving`) → ejecutar
#75 aislado contradice el propio plan del item. Además la premisa "afecta vista de mapas en APK"
no es cierta: **no existe ninguna pantalla de mapa de MegaFamilia** en el Flutter actual (el único
uso de mapa en todo el código es Flotas/conductor, con `flutter_map`+OSM, **sin API key**, patrón
agregado después de que se escribiera el comentario de omisión en v0.1). El propio Flutter está
además en migración a React Native (`megafamilia-rn`, ~95% portado — confirmado el mismo día en
el item #23, ver `docs/megafamilia-apk-auditoria-2026-07-14.md` §7), que **ya trae
`react-native-maps` con el mismo patrón OSM sin key**. Configurar una key de Maps SDK for Android
nueva, sin ninguna pantalla que la necesite y en un codebase que se está reemplazando, es la
frontera dura de credenciales que el revisor señaló — no se justifica hoy. Cerrado documentando
la ruta correcta para cuando se retome (mapa del menor en `megafamilia-rn` con `react-native-maps`
+ OSM, no `google_maps_flutter`; si algún día se necesita Google Maps real, pasar por el módulo
compartido `Mapas`, no una key aparte). Detalle completo en
`docs/megafamilia-google-maps-item-75-verificacion.md`. **Sin cambio de código.**

---

## Item #639 — Vista Hijo APK: mocks de Logros/Apps permitidas/Tiempo de pantalla (RESUELTO — decompuesto en sub-items)

El item pedía reemplazar 3 mocks de la Vista Hijo en `megafamilia-rn` por datos reales:
`LogrosScreen.tsx` (arreglo `BADGES` hardcodeado, sin llamar a `loadLogros()`), "Apps
permitidas" en `HijoDashboard.tsx` (arreglo `APPS` hardcodeado, sin relación con
`parental_app_blocks`, que no tiene UI admin todavía) y `minutesUsedToday`/`minutesLimitToday`
en `useHijoStore.ts` (hardcodeados, requieren instrumentación nativa Android). No cabía en una
sola vuelta — una sesión previa del circuito (`wt-1`) ya lo descompuso siguiendo el propio
"orden sugerido" del item: **#658** (fase 1 — el WIP de `LogrosScreen.tsx`+`useHijoStore.ts`
ya está escrito y verificado con `tsc --noEmit` limpio, pero **sin commitear**: el clasificador
de auto-mode bloquea git fuera del worktree del circuito en el repo `megafamilia-rn`, que no
tiene worktree dedicado — requiere que alguien con permiso de git ahí lo comitee), **#659**
(fase 2+3 — Apps permitidas, requiere construir primero la UI admin de `parental_app_blocks`) y
**#661** (fase 4 — tiempo de pantalla real, instrumentación nativa Android, decisión de
alcance/plataforma pendiente con Irving). Esta vuelta (`wt-5`) re-verificó ambos hallazgos de
forma independiente (WIP sigue correcto, bloqueo de git sigue vigente) y cerró #639 como
paraguas resuelto — el trabajo real vive en los 3 sub-items. Detalle completo en
`docs/megafamilia-hijo-mocks-item-639-verificacion.md`. **Sin cambio de código propio** (el
código del WIP ya existía de una sesión anterior a `wt-1`).

---

## Item #103 — Celular conductor como tracker GPS en APK (RESUELTO — ya implementado)

El item daba por hecho que faltaba la UI de APK (botón activar tracking, permiso de ubicación,
loop de envío) sobre un backend `POST /conductor/posicion` que asumía ya existía en
`FleetPositionService`/Flotas. Investigado (item #103): **backend y UI ya están construidos,
mergeados y funcionando de punta a punta** — repartidos entre este repo y `megafamilia-rn`
(React Native, checkout separado en `/var/www/megafamilia-rn`, solo leído — no tocado, mismo
criterio de aislamiento #334 que #639). Backend real:
`ConductorApiController::reportarPosicion` (`app/Modules/Addons/MegaFamilia/`, NO en
`Flotas/FleetGpsController` como asumía la premisa), self-scoped por `FleetAssignment`, con
anti-jump (descarta saltos >1km en <5s) y reuso de `FleetPositionService::saveBatch` sin tocar
su lógica. Un commit previo (`6ff9bad1`) agregó por error un endpoint duplicado en `Flotas` y
el commit siguiente (`a3d38742`, mismo día) lo revirtió al encontrar el real vía grep amplio —
regla "servicios compartidos únicos"; se conservó solo la migración del enum `'phone'` de
`fleet_devices.brand`, que corregía un bug real de `STRICT_TRANS_TABLES`. UI real en
`megafamilia-rn`: rol `conductor` → `ConductorNavigator` → `ConductorDashboard` → tab "Mapa"
(`MapaTab.tsx`) con botón "▶ Iniciar tracking", `PermissionsAndroid.request(ACCESS_FINE_LOCATION)`
y `setInterval` cada 30s (comentario propio del código: *"mismo intervalo que Flutter"* — se
portó tal cual de la app Flutter predecesora al hacer el RN rewrite). El rol `conductor` existe
en BD pero con 0 usuarios asignados hoy — rollout de negocio (asignar choferes reales), fuera de
alcance del item (que pedía la capacidad técnica, no el alta). Detalle completo en
`docs/flotas-conductor-gps-item-103-verificacion.md`. **Sin cambio de código.**
## Item #733 — Seguimiento de la pregunta sin resolver de #218 (RESUELTO — la pregunta ya tenía respuesta)

Seguimiento auto-generado al cerrar #218 con 1 pregunta `requiere_irving` sin `opcion_elegida`
("¿cómo se clasifica cada tipo dudoso de inventario: herramienta/material/tercera categoría
'equipo de cliente'?"). Investigado: nació **37 segundos después** de que el commit de cierre del
sub-item **#684** (el que resolvía justo esa última pieza, "POWER") quedara en `main` — carrera del
generador de seguimientos contra su propio sub-item. Verificado directo contra la BD de dev: los
17 tipos originales de #218 están **todos** clasificados (`equipo_cliente`: ONT/MODEM/TELEFONOS DE
CASA/ELIMINADOR; `material`: ACOPLADOR/SPLITTER/CONECTOR/CONECTORES/CABLE/PILAS/PAPELERIA/FLYERS/
CARRETE/TENSOR/TENSORES/HOJAS), y "POWER" quedó `categoria=NULL` **a propósito** (#684 encontró que
era un tipo mezclado — 3 de sus 5 artículos no encajaban en una sola categoría — y movió cada
artículo a su tipo real en vez de forzar una categoría al tipo). Las categorías 3ª (`equipo_cliente`)
y 4ª (`equipo_red`, decisión de Irving en #1007) que la pregunta planteaba como opción **ya existen
y están aplicadas**. Nada pendiente de la lista original. Detalle completo en
`docs/inventario-seguimiento-218-item-733-verificacion.md`. **Sin cambio de código** (el cambio ya
estaba en `main` desde antes de que el item existiera).

## Item #741 — Seguimiento de la pregunta sin resolver de #733 (RESUELTO — misma carrera del generador, un nivel más abajo)

Mismo patrón que #733, repetido: al cerrar #733 (`completado`, con la respuesta completa ya en su
`reporte_coloquial`), el generador de seguimientos leyó el arreglo estructurado `preguntas[]` (que
seguía con `opcion_elegida:null`) en el **mismo segundo** del merge y creó #741 sin mirar que el
padre ya había resuelto la pregunta. Reverificado contra la BD de dev (2026-08-29): las 17 entradas
originales de #218 siguen clasificadas igual que documentó #733 (sin regresiones); `POWER` sigue
`NULL` a propósito. Nada nuevo que decidir. Detalle en
`docs/inventario-seguimiento-733-item-741-verificacion.md`. **Sin cambio de código.** Causa raíz
(la carrera del generador entre cierre del padre y lectura de `preguntas[]`) queda anotada como
deuda de bajo costo si se repite una tercera vez — no se toca en este item.

## Item #753 — Seguimiento de la pregunta sin resolver de #741 (RESUELTO — tercera repetición; generador CORREGIDO)

Tercera vuelta de la misma carrera (#218→#733→#741→#753), sobre la pregunta textualmente idéntica.
Reverificado contra la BD de dev (2026-08-29): las 17 entradas originales de #218 siguen
clasificadas igual (sin regresiones); `POWER` sigue `NULL` a propósito. A diferencia de #733/#741,
aquí SÍ se tocó código: `JarvisService::cadenaSeguimientoRepetida()` (nuevo) camina la cadena
`origen_item_id` y, si la misma pregunta textual ya generó 3+ seguimientos en cadena, el hook
`RoadmapItem::saving()` (`#1008`) deja de crear un hijo más — solo anota
`seguimiento_omitido_cadena_repetida` en el log del item que se cierra. Además #753 se cerró con
`preguntas[0].opcion_elegida` fijado a la respuesta real, así que aunque el guard fallara la
pregunta ya no cuenta como "sin resolver". Detalle completo en
`docs/inventario-seguimiento-741-item-753-verificacion.md`.
## Item #738 — Deriva de esquema #216 Fase 1 — bucle reap sobre paraguas nunca aparcado (RESUELTO — se completa el cierre-intento faltante)

Una sesión previa ya había hecho lo correcto: al recibir `circuito:cabida` = NO CABE, descompuso
#738 en **#797** (Fase 1a: vaciar+migrar `megaisp_dryrun` desde cero) y **#798** (Fase 1b: exportar
a `storage/schema/reference.sql` vía `schema:build-reference`), y terminó su vuelta sin ejecutar
más código. Pero nunca **intentó cerrar** al padre — y el guard de paraguas del modelo
(`RoadmapItem.php` bloque "(2b) PARAGUAS") solo aparca un ítem descompuesto (`aprobado_irving` +
`excluir_pool_automatico`) cuando algo intenta activamente `estado_aprobacion = 'completado'` y
detecta hijos abiertos. Sin ese intento, #738 se quedó en `en_progreso` con el `worker_sid` de esa
sesión, sin nadie liberando el claim; el reaper (que solo sabe re-encolar huérfanos a su estado
previo) lo devolvió a `aprobado_revisor` dos veces (`reap_count=2`), y el pool lo repartió de nuevo
sin que hubiera trabajo propio que hacer — mismo síntoma que #745/#664, un nivel más abajo (ahí el
paraguas SÍ estaba aparcado y el problema era que nadie despachaba a los hijos; aquí el paraguas
nunca llegó a aparcarse). Verificado: #797/#798 seguían intactos (`aprobado_revisor`, `pending`,
sin reclamar) — nadie los tocó, la descomposición original seguía siendo la correcta. Corrección:
esta vuelta ejecuta el paso que faltaba (intento de cierre a `completado`), el guard lo reenruta
solo a `aprobado_irving`+`excluir_pool_automatico=true`, sacándolo del pool/reaper para siempre
hasta que el hook de cierre en cascada (ya existente, `RoadmapItem.php:459-491`) lo complete solo
cuando #797 y #798 cierren. Detalle en `docs/roadmap-bucle-reap-item-738-verificacion.md`. **Sin
cambio de código de negocio** — el trabajo técnico real del esquema de referencia sigue en #797/#798.

## Item #745 — [RESPUESTA] DocumentaciónCorporativa Fase 2 — bucle reap/escalación en paraguas ya descompuesto (RESUELTO — cerrado no-accionable, decisión de Irving)

El item #664 ("DocumentaciónCorporativa — Fase 2") ya fue descompuesto en 4 sub-items (#734
repositorio, #735 bandeja, #736 registros estructurados, #737 plantillas), todos aprobados y
**sin reclamar** (`worker_sid` vacío) desde el 2026-08-28. #745 documentó el bucle: cada vuelta
que reclama el paraguas #664 llega a la misma conclusión (nada que implementar sin duplicar el
trabajo de los hijos) → timeoutea → el reaper de huérfanos lo re-encola → escala a Irving otra
vez → se repite (`reap_count`=4 en #664, 3+ notas idénticas). Irving aprobó explícitamente la
**Opción 1** de la pregunta estructurada del item: cerrar #745 como no-accionable (el paraguas ya
descompuesto no tiene trabajo propio; el trabajo real vive en #734-#737) en vez de re-descomponer
(Opción 2, rechazada) o volver a escalar (Opción 3, rechazada — es el propio bucle). Reverificado
contra la BD de dev (2026-08-29): #664 sigue reclamado por otra sesión en curso (no se tocó, un
item = un dueño) y los 4 hijos siguen aprobados sin `worker_sid`. Detalle completo, incluida la
deuda de fondo sobre por qué el pool no despacha los hijos solos, en
`docs/roadmap-bucle-reap-item-745-verificacion.md`. **Sin cambio de código** (cierre documental
ejecutando la decisión ya tomada por Irving).

## Item #830 — Fase 1a-ii parte 2/2 (ciclo fix-drift, 555 migraciones) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745, un nivel más abajo en la Fase 1a-ii (ciclo fix-drift de
`schema:rebuild-dryrun` sobre las 555 migraciones, continuación de #822). Una vuelta previa
(2026-08-31 17:36) ya había hecho lo correcto: verificó que los fixes de #822 (`ab7804e0` crea la
tabla `migrations` en dryrun, `8c938dd1` guard `Schema::hasTable` en `create_jobs_table`) ya están
en `main`, corrió `circuito:cabida` (NO CABE, `ya_timeouteo_antes`) y descompuso el siguiente tramo
de trabajo en **#833** (resolver la colisión `failed_jobs` — posible carrera con #831 sobre
`megaisp_dryrun` compartida, o drift real con el mismo guard `hasTable` — y seguir el ciclo por el
siguiente lote), con la política de ejecución ya aprobada por Irving (lotes, un commit por fix,
stop-and-escalate en frontera dura). Pero nunca intentó **cerrar** al padre — el guard de paraguas
del modelo (`RoadmapItem.php` bloque "(2b) PARAGUAS") solo aparca un item descompuesto cuando algo
intenta activamente `estado_aprobacion = 'completado'` y detecta hijos abiertos. Sin ese intento,
#830 se quedó `en_progreso` colgado; el reaper lo devolvió a `aprobado_revisor`, el pool lo repartió
de nuevo sin trabajo propio que hacer, timeouteó otra vez sin commits y volvió a escalar — mismo
síntoma que #738/#745. Verificado: #833 sigue `requiere_irving`, único hijo (`origen_item_id=830`),
sin reclamar — la descomposición original seguía siendo correcta, nadie más la tocó. Corrección:
esta vuelta ejecuta el intento de cierre faltante; el guard lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `paraguas_abierto` en el log), sacándolo del pool/reaper
hasta que el hook de cierre en cascada (ya existente, `RoadmapItem.php:459-491`) lo complete solo
cuando #833 cierre. Detalle en `docs/roadmap-bucle-reap-item-830-verificacion.md`. **Sin cambio de
código de negocio** — el trabajo técnico real del ciclo fix-drift sigue en #833, pendiente de que
Irving resuelva sus preguntas estructuradas.

## Item #816 — DocumentaciónCorporativa Fase 5d-2 (checklist offboarding, ítems sin tabla propia) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830, esta vez en la Fase 5d-2 de DocumentaciónCorporativa (ampliar el
checklist de offboarding de #815 con los 6 ítems sin tabla propia: correo, VPN, WhatsApp, equipo,
respaldo, finiquito RH). Una vuelta previa (2026-09-01 13:09) ya había hecho lo correcto: corrió
`circuito:cabida` (NO CABE, `ya_timeouteo_antes`) y descompuso el trabajo en **#839** (backend de
los 6 ítems fijos, ejecutable ya) + **#840** (wire de "Otros pendientes" en `DcOffboarding.vue`,
bloqueado a propósito hasta que #815 tenga `merge_commit` en `main` — la contradicción de spec
"#816 depende de #815 cerrada" ya la había escalado una vuelta aún anterior, `wt-2`, e Irving la
resolvió reaprobando #816 sin mergear #815). Pero nunca intentó **cerrar** al padre — el guard de
paraguas del modelo (`RoadmapItem.php` bloque "(2b) PARAGUAS") solo aparca un item descompuesto
cuando algo intenta activamente `estado_aprobacion = 'completado'` y detecta hijos abiertos. Sin
ese intento, #816 se quedó `en_progreso` colgado con el `worker_sid` de esa sesión; el reaper de
huérfanos lo devolvió a `aprobado_irving` (`reap_count=1`) sin que hubiera trabajo propio que
hacer — mismo síntoma que #738/#745/#830. Verificado: #839 y #840 siguen `requiere_irving`, únicos
hijos (`origen_item_id=816`), sin reclamar — la descomposición original seguía siendo correcta,
nadie más la tocó; #815 sigue `aprobado_irving` con rama propia pero sin `merge_commit`, confirma
que #840 sigue correctamente bloqueado. Corrección: esta vuelta ejecuta el intento de cierre
faltante; el guard lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true`, sacándolo del
pool/reaper hasta que el hook de cierre en cascada (ya existente, `RoadmapItem.php:459-491`) lo
complete solo cuando #839 y #840 cierren. Detalle en
`docs/roadmap-bucle-reap-item-816-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo técnico real (backend de los 6 ítems y su wire en la UI de #815) sigue en #839/#840,
pendiente de que Irving los resuelva.

## Item #818 — Fase 1a-ii (schema:rebuild-dryrun completo) — bucle reap sobre paraguas cuyos hijos YA cerraron (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816, pero con una variante de timing: aquí los dos hijos
directos (`origen_item_id=818`) llevaban **días** cerrados y archivados (**#821** "construir el
comando completo", `completado` 2026-08-29 17:18; **#822** "correrlo contra megaisp_dryrun real
+ catalogar drift", `completado` 2026-08-29 17:45) — no es que faltara descomponer nada. El
bucle nació de una carrera de timing distinta: el hook de cierre en cascada
(`RoadmapItem.php:462-491`) sólo completa al padre en el instante exacto en que el ÚLTIMO hijo
cierra, y sólo si en ESE momento el padre ya está en `aprobado_irving`. `#822` no cerró de un
tirón — quedó parqueado como paraguas propio de sus propios nietos (`#830`/`#831`, ver
`docs/roadmap-bucle-reap-item-830-verificacion.md`) y sólo alcanzó `completado` real cuando
`#831` cerró el 2026-08-31 18:05:52. En ese instante `#818` estaba en `requiere_irving` (lo
había escalado un timeout a las 17:32:09) — la cascada lo descartó como candidato y nunca se
disparó. Irving volvió a aprobar `#818` el 2026-09-01 13:04:12, pero nadie volvió a *intentar*
el cierre después: el hook sólo reacciona al `saved` de un hijo, no a un cambio posterior del
padre. Sin ese intento, `#818` quedó colgado (5 timeouts, 2 reanudaciones, `reap_count=1`) y el
pool lo repartió una y otra vez sin trabajo propio que hacer, porque el trabajo real ya estaba
hecho. Verificado esta vuelta: `tieneSubItemsAbiertos()` para #818 = `false` (#821 y #822 ambos
`completado`/`done`/archivados). Corrección: esta vuelta ejecuta el intento de cierre faltante;
como ya no quedan hijos abiertos, el guard NO lo parquea esta vez — cierra de verdad a
`completado`. Detalle en `docs/roadmap-bucle-reap-item-818-verificacion.md`. **Sin cambio de
código de aplicación** — el comando `schema:rebuild-dryrun` (candados + drop/recreate + bloque
Migrator + 2 fixes de drift) ya está completo y mergeado desde #821/#822; la continuación del
ciclo fix-drift hasta que las 555 migraciones corran limpias de punta a punta es descendiente de
`#822` (no de #818) y sigue su curso aparte en `#830`/`#833`.

## Item #848 — Fase 2 del menú de una sola fuente de verdad — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818, sub-item de seguimiento de #843 (ocultar módulo
completo del sidebar si 0 entradas visibles). Una vuelta previa (`wt-2`, 2026-09-01 15:56) ya hizo
lo correcto: `circuito:cabida` devolvió NO CABE (ya había timeouteado 2 veces sin commits) →
descompuso el trabajo real en **#855** (envolver el `<li>`/`<ul>` completo del bloque hardcodeado
en el `@if` de permiso, evita shell huérfano vacío), **#856** (los `dynamic_children` de
`module_sidebar_config` no tienen chequeo de permiso individual — hallazgo nuevo, fila
`gestion-red-mikrotik-sync` sin permiso ni guard de ruta) y **#857** (aplicar la decisión de
Irving de la pregunta `q2` sobre módulos con landing propia sin hijos visibles). Pero el proceso se
cortó a media escritura del reporte (comentario truncado en "Verificado y") antes de intentar
cerrar al padre — el reaper (`reaper-rapido`) vio el slot `wt-2` libre, re-encoló #848 a
`aprobado_irving` (`reap_count=1`), y el pool lo repartió de nuevo sin trabajo propio que hacer.
Verificado esta vuelta: los 3 hijos (`origen_item_id=848`) existen intactos, todos
`requiere_irving`, sin reclamar — la descomposición original seguía siendo correcta, nadie más la
tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php`
bloque "(2b) PARAGUAS", ~301-326) lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true`
(evento `paraguas_abierto` en el log, confirmado: "le quedan 3 sub-item(s) abierto(s)"), sacándolo
del pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete
solo cuando #855, #856 y #857 cierren. Detalle en
`docs/roadmap-bucle-reap-item-848-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo real de la Fase 2 del sidebar sigue en #855/#856/#857, pendiente de triaje/aprobación.

## Item #852 — Item 2 Fase A (permisos.description + sync-roles + 34 módulos) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848, sub-item de seguimiento de #842 (fix de la columna
`permissions.description` faltante + comando de sincronización + registro de permisos de los 34
módulos que ya los declaran en su `module.json`). Una vuelta previa (`wt-3`, 2026-09-01 15:59) ya
hizo lo correcto: `circuito:cabida` devolvió NO CABE (ya había timeouteado sin commits) →
descompuso el trabajo real en **#858** (fix de columna + backfill + extender el glob de
`PermissionSyncService` a `Core/*/module.json`), **#859** (correr `permissions:sync-roles
--manifests` en dev tras el fix + verificar diff), **#860** (exponer `description` en el catálogo
`/administracion/permisos/catalog`) y **#861** (documentar la convivencia de nomenclaturas de
permisos). De paso corrigió una premisa falsa del spec original: el comando
`permissions:sync-roles` **ya existía** (`SyncPermissionsCommand.php`, con `--manifests`) — no
había que crearlo desde cero, solo corría el fix de columna antes. Pero el proceso nunca intentó
cerrar al padre tras descomponerlo — el item se quedó `en_progreso` con el `worker_sid` de esa
sesión; el reaper (`reaper-rapido`) vio el slot libre, re-encoló #852 a `aprobado_irving`
(`reap_count=1`), y el pool lo repartió de nuevo sin trabajo propio que hacer. Verificado esta
vuelta: los 4 hijos (`origen_item_id=852`) existen intactos, sin reclamar — la descomposición
original seguía siendo correcta, nadie más la tocó. Corrección: esta vuelta ejecuta el intento de
cierre faltante; el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a
`aprobado_irving` + `excluir_pool_automatico=true`, sacándolo del pool/reaper hasta que el hook de
cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #858, #859, #860 y #861
cierren. Detalle en `docs/roadmap-bucle-reap-item-852-verificacion.md`. **Sin cambio de código de
negocio** — el trabajo real (columna, comando, UI, documentación) sigue en
#858/#859/#860/#861, pendiente de que una terminal los reclame.
## Item #905 — Válvula: sellar frontera_valvula + backfill + test de regresión (Defecto 1 de #902) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#878. #905 pedía sellar `frontera_valvula` en el
mismo acto que el log de válvula (`RevisorService::aplicarTriajeNull()`, que arma el log de
`valvula_contexto` pero nunca setea la columna, a diferencia de la válvula de nacimiento en
`RoadmapController.php:2688-2689` que sí la sella), el backfill de los 112 items ya afectados y un
test de regresión. Una vuelta previa (`wt-2`, 2026-09-03 15:18) ya hizo lo correcto: corrió
`circuito:cabida` (NO CABE, `historico_excede_umbral`) y descompuso el trabajo en **#975** (Fase 2
— sellar la columna en `aplicarTriajeNull()`, cubriendo tanto `mencion` como `accion`), **#976**
(Fase 3 — backfill de los 112 items con `frontera_valvula` NULL, depende de que #975 esté
commiteado) y **#977** (Fase 5 — test de regresión que falle si un veredicto de válvula escribe el
log sin sellar la columna). Pero esa vuelta nunca intentó **cerrar** al padre — el guard de
paraguas del modelo (`RoadmapItem.php` bloque "(2b) PARAGUAS") solo aparca un item descompuesto
cuando algo intenta activamente `estado_aprobacion = 'completado'` y detecta hijos abiertos. Sin
ese intento, #905 se quedó `en_progreso` colgado; el reaper lo devolvió a `aprobado_revisor`
(`reap_count=1`, log `huerfano_reencolado`), y el pool lo repartió de nuevo sin trabajo propio que
hacer — mismo síntoma que los items anteriores de esta misma familia. Verificado: los 3 hijos
(`origen_item_id=905`) siguen intactos y sin reclamar (`#975` en `requiere_irving`, `#976`/`#977`
en `pendiente_revision`) — la descomposición original seguía siendo correcta, nadie más la tocó.
Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard lo reenruta a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le
quedan 3 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #975, #976 y #977 cierren. Detalle en
`docs/roadmap-bucle-reap-item-905-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo técnico real (sellado de `frontera_valvula`, backfill y test de regresión) sigue en
#975/#976/#977, pendiente de que Irving apruebe #975 (`requiere_irving`) y de que corra el resto
de la cadena.

## Item #878 — Torre 24/7 Pieza 2, deadlock del freno de sequía — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848. #878 pedía documentar el deadlock del freno de
sequía Nivel 2 (#712) de `circuito:auditor` y diseñar+implementar una vía de re-armado que no
dependa de completar un item real. Una vuelta previa (`wt-2`, 2026-09-03 11:06-11:12) ya hizo lo
correcto: FASE 1 (documentó `AuditorService::gastoApagado()`/`rachaSeca()`/`debeCorrer()`) + FASE 2
(diseño — eligió candidato **(c) caducidad temporal/half-open**, descartando (a)/(b)/(d) con
justificación) en modo solo-lectura, y descompuso FASE 3 (implementar) + FASE 4 (verificar) en
**#891** con spec completo (archivos/líneas exactas: `config/circuito.php`,
`AuditorService.php`, migración de `torre_config`, `TorreAutomationPolicy`, `RoadmapController`,
`TorreConfiguracion.vue`). #891 pasó el triaje y el revisor lo **escaló a Irving** (categoría
negocio: define umbrales/tiempos de un guardrail del propio circuito) con un brief completo de 4
preguntas estructuradas (TTL, mecanismo de disparo, qué exponer en Torre, dónde persistir el
estado), cada una con opciones/pros/contras/recomendación. Pero esa vuelta nunca intentó **cerrar**
#878 tras crear el sub-item — quedó `en_progreso` colgado con el `worker_sid` de esa sesión; el
reaper lo vio con el slot libre y lo re-encoló (`reap_count=1`), y el pool lo repartió de nuevo sin
trabajo propio que hacer. Verificado esta vuelta: #891 (único hijo, `origen_item_id=878`) sigue
intacto, `requiere_irving`, brief completo, sin reclamar — la descomposición original seguía
siendo correcta, nadie más la tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante;
el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS", ~301-326) lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le quedan 1 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #891 cierre. Detalle en
`docs/roadmap-bucle-reap-item-878-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo técnico real (implementar el re-armado del freno de sequía + exponerlo en Torre →
Configuración) sigue en #891, pendiente de que Irving decida su brief de 4 preguntas.

## Item #880 — Torre 24/7 Pieza 4, auto-corregir hardening de código — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#878. #880 pedía partir la clasificación de
"seguridad" en dos carriles (AUTO endurece sin tocar autorización; BANDEJA queda intacto para
permisos/auth/dinero) siguiendo 5 fases. Una vuelta previa (`wt-1`, 2026-09-03 12:29-12:44) ya
hizo lo correcto: corrió `circuito:cabida` (NO CABE, ya había timeouteado antes con la rama sin
commits) y descompuso el trabajo en **#918** (Fase 2 — declarar el criterio AUTO/BANDEJA en
`config/circuito.php`, sin wiring), **#919** (Fase 3+4 — activar el carril AUTO en
`circuito:priorizar-seguridad` + candado de regresión, bloqueante en el mismo merge) y **#920**
(Fase 5 — verificar el clasificador contra items de seguridad reales ya cerrados), documentando
la Fase 1 (lectura de la política actual) directamente en `comentarios_claude` del propio #880
para que ningún hijo tuviera que re-investigar. Pero esa vuelta nunca intentó **cerrar** #880
tras crear los sub-items — quedó `en_progreso` colgado con el `worker_sid` de esa sesión; el
reaper lo vio con el slot libre y lo re-encoló (`reap_count=1`), y el pool lo repartió de nuevo
(a la misma terminal `wt-1`) sin trabajo propio que hacer. Verificado esta vuelta: los 3 hijos
(`origen_item_id=880`) siguen intactos, `requiere_irving`, `nivel_riesgo=B`, sin reclamar — la
descomposición original seguía siendo correcta, nadie más la tocó; la rama del item existe pero
sin commits propios (coincide con el `commits_rama:0` del timeout previo). Corrección: esta
vuelta ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS",
~301-326) lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (+
`esperando_merge_irving=true`), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #918, #919 y #920 cierren los tres. Detalle en
`docs/roadmap-bucle-reap-item-880-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo técnico real (criterio AUTO/BANDEJA + carril AUTO con su candado de regresión +
verificación contra casos reales) sigue en #918/#919/#920, pendiente de triaje/aprobación.
## Item #906 — Defecto 2 de #902 (mensajes de escalada nombran el candado equivocado) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#905/#878. #906 pedía corregir los 4 mensajes de
escalada del circuito que siempre dicen "la política no permite este nivel" aunque la causa real
sea una frontera dura y no un techo de nivel (`RevisorService.php:224` y `:1077`,
`JarvisService.php:725` y `:830`). Una vuelta previa (`wt-2`, 2026-09-03 15:24) ya hizo lo correcto:
corrió `circuito:cabida` (NO CABE, histórico ~2399s) y descompuso el trabajo **por archivo** para
que no se pisaran entre sí — **#978** (`JarvisService.php`: carriles "ya decidido" y "mecánico") y
**#979** (`RevisorService.php`: `aplicarVeredicto()` y el carril des-trabador), cada uno con el
detalle de `fronteraDuraDeItemDetalle()`/`nivelEfectivo()` a usar y su propia verificación. Pero esa
vuelta nunca intentó **cerrar** #906 tras crear los sub-items — quedó `en_progreso` colgado con el
`worker_sid` de esa sesión; el reaper lo vio con el slot libre y lo re-encoló (`reap_count=1`), y el
pool lo repartió de nuevo sin trabajo propio que hacer. Verificado esta vuelta: #978
(`origen_item_id=906`) sigue `requiere_irving` sin reclamar, #979 sigue `aprobado_revisor` sin
reclamar — la descomposición original seguía siendo correcta, nadie más la tocó. Corrección: esta
vuelta ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS",
~301-326) lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (evento
`paraguas_abierto` en el log, "le quedan 2 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta
que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #978 y #979
cierren. Detalle en `docs/roadmap-bucle-reap-item-906-verificacion.md`. **Sin cambio de código de
negocio** — el trabajo técnico real (distinguir frontera dura vs. techo de nivel en los 4 mensajes)
sigue en #978 (pendiente de que Irving lo apruebe) y #979 (`aprobado_revisor`, listo para tomarse).

## Item #907 — Torre 24/7 Pieza 5a (slots_libres como disparador de primera clase) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#905/#878/#906. #907 (sub-item de #904) pedía tratar
`slots_libres` como disparador de primera clase en `AuditorService::debeCorrer()`: (a) condición de
disparo, (b) toggle configurable en Torre → Configuración con el patrón de
`auditor_activo`/`auditor_cooldown_min`, (c) métrica "N de 6 terminales trabajando" en la Torre. Una
vuelta previa (`wt-2`, 2026-09-03 12:10) ya hizo lo correcto: corrió `circuito:cabida` (NO CABE) y
descompuso el trabajo por fase — **#980** (condición de disparo), **#981** (toggle configurable),
**#982** (métrica de ocupación en la Torre). Pero esa vuelta nunca intentó **cerrar** #907 tras crear
los sub-items — quedó colgado; el reaper lo vio con el slot libre dos veces y lo re-encoló
(`reap_count=2`), y el pool lo repartió de nuevo sin trabajo propio que hacer. Verificado esta vuelta:
#980/#981/#982 (`origen_item_id=907`) siguen intactos, sin reclamar — la descomposición original
seguía siendo correcta, nadie más la tocó. Corrección: esta vuelta ejecuta el intento de cierre
faltante; el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS", ~301-326) lo reenruta a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le quedan 3
sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #980, #981 y #982 cierren. Detalle en
`docs/roadmap-bucle-reap-item-907-verificacion.md`. **Sin cambio de código de negocio** — el trabajo
técnico real (condición de disparo, toggle configurable, métrica de ocupación) sigue en #980
(pendiente de que Irving lo apruebe) y #981/#982 (`aprobado_revisor`, listos para tomarse).

## Item #9990003 — Candado de esquema de #915 usaba `storage_path()` por-worktree (RESUELTO — ya corregido bajo #915)

Sub-item de seguimiento de #915 (creado 2026-09-03 16:27), reportaba que
`GuardedMigrateCommand::conCandadoDeEsquema()` usaba `storage_path('app/circuito')` — ruta que
resuelve DENTRO de cada worktree (`readlink -f` confirmó rutas físicas distintas en `wt-1..wt-6`)
→ el candado no serializaba nada entre terminales, el mismo riesgo que #915 debía cerrar. Al
llegar a este item, el fix **ya estaba en `main`**: commit `1e83e7cc` (mismo día, 16:29:38 —
minutos después de que este sub-item se creara), integrado vía `1631905d`, cambió la ruta a
`config('circuito.candado_migraciones')` = ruta absoluta al checkout principal
(`/var/www/megaisp/storage/app/circuito/migrate-esquema.lock`), mismo patrón que
`freno.centinela`. Carrera de timing entre el sub-item de seguimiento y la sesión que ya estaba
corrigiendo el item padre — el trabajo real se hizo ANTES de que el seguimiento terminara de
escalarse (variante inversa del gap de bookkeeping de #733/#738/#745/etc.). Reverificado en esta
vuelta: `php -l` limpio + **prueba de concurrencia real** (dos procesos PHP lanzados desde `wt-3`
y `wt-1`, `flock(LOCK_EX)` sobre la misma ruta resuelta por `config()`) confirmó exclusión mutua
real entre worktrees; fallback silencioso (archivo no abrible → warn + continúa) intacto. Detalle
en `docs/circuito-candado-esquema-storage-path-item-9990003-verificacion.md`. **Sin cambio de
código** — el fix ya estaba aplicado.

## Item #930 — FASE 4 de #927: ¿debe agotar los 60 turnos disparar la misma descomposición (`circuito:cabida`) que un timeout? (RESUELTO — el mecanismo ya estaba en vigor)

Sub-item de evaluación/reporte de #927 (causa raíz de los claims huérfanos por `Reached max turns`).
Irving aprobó la Opción 1 de la pregunta estructurada: "sí, tratar max-turns como timeout y disparar
`circuito:cabida`". Verificado contra el código real de `main` (ya con #927-FASE1-3 y #928
mergeados): **esa decisión ya está implementada, sin necesitar código nuevo**. `deploy/circuito/vuelta.sh`
(líneas 262-278) ya llama `circuito:parquear-timeout --causa=max_turns` al detectar `Reached max
turns` en el log, igual que hace con timeout; `ParquearTimeoutCommand` incrementa `veces_timeouteo`
para ambas causas por igual; y `JarvisService::caberEnVuelta()` (línea 1279) devuelve `NO CABE
[ya_timeouteo_antes]` en cuanto `veces_timeouteo >= 1`, sin mirar la causa — y `circuito:cabida` es
paso obligatorio (2) de cada vuelta (`prompt-item.txt:103-106`). Efecto neto: un item que agota sus
60 turnos topa con la descomposición obligatoria en su siguiente intento, sea cual sea el motivo por
el que se reencola. **Q1** (¿max-turns = item grande?): no por sí solo — criterio de distinción es el
artefacto dejado (commits/decisión/sub-item), no el conteo de turnos; el único caso medido de #871
agotó 60 turnos en ~5 min (~5s/turno), ritmo compatible con bucle/exploración, no con trabajo
sostenido. **Q3** (aplicación a #871, caso de prueba real con `reap_count=10`): revisado su historial
completo — solo 1 de ~10 ciclos agotó max-turns (con el `vuelta.sh` viejo, sin registro por el propio
bug que #927 corrigió); los otros ~9 fueron un bloqueo externo distinto (#870 sin mergear, ya
resuelto el 2026-09-03 14:22) + bucle reaper↔jarvis-ya-decidido, fuera de alcance de este item
(#897/#898). No se fuerza la descomposición de #871 (el análisis de Q1 no la hace inequívoca —
forzarla sería el falso-positivo que la propia Opción 1 señala como contra); se dejó la recomendación
de corte en 3 fases escrita en su log para el próximo ejecutor, si vuelve a agotar turnos. Detalle
completo en `docs/circuito-maxturns-cabida-item-930-verificacion.md`. **Sin cambio de código de
aplicación** — el mecanismo que satisface la Opción 1 ya existía en `main` antes de este item.

## Item #924 — Root-cause del paraguas cierre-en-cascada que dejó pasar a #32 (nivel-C sin merge) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#905/#878/#906/#907. #924 (sub-item de #883) pedía
investigar por qué el hook de cierre en cascada dejó completar a `#32` (nivel-C, con rama propia y
sin `merge_commit`) esquivando el guard bloque (1), y — según la causa confirmada — endurecer el
punto exacto + test de regresión. Irving aprobó las 3 preguntas estructuradas del item, todas con
la Opción 1 recomendada (investigar primero, reabrir #32 y re-cerrarlo por el flujo normal, y
agregar un guard pre-cierre que valide `merge_commit` de todos los sub-items nivel-C). Una vuelta
previa (`wt-2`) ya hizo lo correcto: corrió `circuito:cabida` (NO CABE) y descompuso el trabajo en
**#9990012** (reproducir en dev la carrera exacta que esquivó el guard) y **#9990013** (endurecer
el punto confirmado + test de regresión, correctamente bloqueado hasta tener la causa exacta de
#9990012). Pero esa vuelta nunca intentó **cerrar** #924 tras crear los sub-items — quedó colgado;
el reaper lo re-encoló y un timeout lo escaló de nuevo sin trabajo propio que hacer. Verificado esta
vuelta: #9990012/#9990013 siguen intactos, sin reclamar — la descomposición original seguía siendo
correcta, nadie más la tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard
(`RoadmapItem.php` bloque "(2b) PARAGUAS", ~301-326) lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le quedan 2 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #9990012 y #9990013 cierren. Detalle en
`docs/roadmap-bucle-reap-item-924-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo real de investigación (reproducir la carrera de #32 y endurecer el guard con test de
regresión) sigue en #9990012 (`aprobado_revisor`, listo para tomarse) y #9990013 (`requiere_irving`,
bloqueado hasta tener la causa confirmada).

## Item #9990012 — Reproducir la carrera del cierre-en-cascada de #32 — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#905/#878/#906/#907/#924, un nivel más abajo:
#9990012 (sub-item de #924) pedía reproducir en dev la carrera del cierre-en-cascada de #32 con
transacción+rollback e instrumentación temporal del guard, y confirmar el mecanismo exacto que lo
esquivó. Una vuelta previa (`wt-2`) ya hizo lo correcto: corrió `circuito:cabida` (NO CABE,
`ya_timeouteo_antes`), hizo el forense estático completo (localizó guard(1)/guard(2b)/hook de
cascada en `RoadmapItem.php` y los 3 `save()` de `MergeRunner.php`, confirmó en el log real de #32
que el bug sí se escribió en BD) y descompuso el repro ejecutable en **#9990063** con spec
detallado (transacción+rollback, `Log::debug` temporal, descarta ya la hipótesis de merge directo
del padre). Pero el proceso murió a media escritura del comentario de decisión, antes de intentar
**cerrar** #9990012 — el log solo registra `claim_liberado_al_morir_la_vuelta`, y el pool lo
repartió de nuevo sin trabajo propio que hacer. Verificado esta vuelta: #9990063 sigue intacto,
`pendiente_revision`, sin reclamar — la descomposición original seguía siendo correcta, nadie más
la tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php`
bloque "(2b) PARAGUAS", ~301-332) lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true`
(evento `paraguas_abierto` en el log, "le quedan 1 sub-item(s) abierto(s)"), sacándolo del
pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo
cuando #9990063 cierre. Detalle en `docs/roadmap-bucle-reap-item-9990012-verificacion.md`. **Sin
cambio de código de negocio** — el trabajo real (reproducir la carrera y confirmar el mecanismo
exacto) sigue en #9990063 (`pendiente_revision`, pendiente de que el revisor lo trie).

## Item #917 — Circuito CC #911 Fase 6 (verificación con números del aflojo) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012. #917
(sub-item de #911) pedía medir 4 números del aflojo del scheduler (ocupación media, timing de
colisiones, vueltas perdidas vs. ganancia, incidentes de merge no detectados) tras varias horas de
operación real con la Fase 5 (#916) integrada. Una vuelta previa (`wt-2`, 2026-09-03 22:40) ya hizo
lo correcto: confirmó que la ventana real ya estaba disponible (perilla en 2 desde `c1ee65f1`,
~7h de operación), corrió `circuito:cabida` (NO CABE, histórico ~501s) y descompuso el trabajo en
**#9990195** (Fase 6a: métricas 1+2, ocupación/colisiones), **#9990196** (Fase 6b: métricas 3+4,
vueltas perdidas/merge no detectado) y **#9990197** (Fase 6c: redactar el doc final, depende de 6a
y 6b). Pero esa vuelta nunca intentó **cerrar** #917 tras crear los sub-items — quedó `en_progreso`
colgado con su `worker_sid`; al morir el proceso, `soltar-claim` lo devolvió a `aprobado_revisor`
(log: `claim_liberado_al_morir_la_vuelta`), y el pool lo repartió de nuevo sin trabajo propio que
hacer. Verificado esta vuelta: los 3 hijos (`origen_item_id=917`) siguen intactos,
`pendiente_revision`, sin reclamar — la descomposición original seguía siendo correcta, nadie más
la tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php`
bloque "(2b) PARAGUAS", ~301-332) lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true`
(evento `paraguas_abierto` en el log), sacándolo del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990195, #9990196 y #9990197 cierren.
Detalle en `docs/roadmap-bucle-reap-item-917-verificacion.md`. **Sin cambio de código de negocio**
— el trabajo real (medir ocupación/colisiones/vueltas perdidas/incidentes de merge y redactar la
conclusión sobre la perilla del aflojo) sigue en #9990195/#9990196/#9990197, pendientes de que el
revisor los tríe.

## Item #210 — El latido sobrevive al proceso que lo lanzó y renueva el lease de un item abandonado (RESUELTO — ya corregido por trabajo posterior)

Item MEDIDO el 2026-08-25 (auditoría de #191 Fase 3): dos fallos del propio Circuito CC — (1) el
pool continuo abandonaba el item anterior sin limpiar `worker_sid`/`estado_aprobacion` al saltar
al siguiente, y (2) el latido (`circuito:vivo --watch`, hijo en background de `vuelta.sh`) no
moría con su padre y seguía renovando el `claimed_at` del item abandonado indefinidamente,
blindándolo frente al reaper. Pedía decidir si el latido debe morir con su padre y si el pool debe
liberar el item anterior antes de reclamar el siguiente. Verificado contra el código real de
`main`: la causa raíz (fallo 2) ya está corregida por **#640** (sub-item de #188 que citó
explícitamente a #210) — `RoadmapCircuitoService::renovarLease()` acota el `UPDATE` de
`claimed_at` al `current_item` de la vuelta activa, no solo `worker_sid`+estado, así que un item
abandonado deja de recibir renovaciones falsas desde el instante mismo del abandono. Con eso, el
reaper existente (`circuito:reap-stuck`, que ya exige `claimed_at` **y** `updated_at` fríos,
`#507` sub-paso 3) lo detecta y re-encola dentro de su ventana normal (~25 min), resolviendo
también el fallo 1 sin necesitar lógica nueva en `vuelta.sh` — es una corrección más general que
"liberar antes de avanzar" porque cubre cualquier camino que deje un item `en_progreso` sin
cerrar. Refuerzos adicionales ya en `main`: **#927** (trap + manejo de fin anormal en
`vuelta.sh` suelta el reclamo), **#215** (`circuito:cortar-vuelta`, corte manual seguro por PGID
que mata el heartbeat CON la vuelta, reemplazó el `pkill -f` que causó el incidente original) y
**#704** (`JarvisVigilarCommand::medirReclamos()`, detección activa de cualquier variante
residual del patrón). La pregunta "A DECIDIR" del item quedó resuelta por una vía más robusta que
depender de que el proceso muera limpio: se quitó el efecto dañino de que el latido siguiera vivo,
en vez de intentar matarlo de forma fiable. Detalle completo en
`docs/circuito-heartbeat-lease-item-210-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo real ya está hecho y mergeado en `main` desde antes de que se tomara este item.

## Item #9990353 — Guard de path duro en `backups:purge-test` (RESUELTO — ya aplicado por #9990352)

Sub-item de seguimiento de #9990352 (mismo item raíz del #146 de retención de respaldos de
release), con spec exacto: reordenar `PurgeTestBackups::handle()` para calcular `$baseDir`
antes de abrir el log channel, e insertar un guard de path duro que aborte si `$baseDir` no
resuelve dentro de `storage_path()`/termina en `/backup_test`. Al llegar a este item, el código
**ya tenía exactamente ese cambio** — commit `aaa74666` ("Agrega guard de path duro en
backups:purge-test"), mergeado a `main` vía `926e1823` ("Integra circuito #9990352 ... a main")
por la sesión que trabajó el propio item padre. Mismo patrón de carrera de timing ya documentado
varias veces arriba (#733/#741/#738/#745/#830/#816/#818/#848/#905/#878/#906/#907/#9990003): el
seguimiento se generó a partir del spec original sin ver que ya se había resuelto en la misma
vuelta. Reverificado: `php -l` limpio; `php artisan backups:purge-test --dry-run` con el path
real (`storage_path('backup_test')`) **no bloquea** — cae directo al mensaje normal "no existe,
nada que hacer", sin el error del guard; orden y lógica del resto del método intactos. Detalle en
`docs/backups-purge-test-guard-path-item-9990353-verificacion.md`. **Sin cambio de código** — el
fix ya estaba aplicado.

## Item #910 — Torre 24/7 Pieza 5d (medición honesta de ocupación y ratio señal/ruido del barrido) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917.
#910 (Fase 5d de #904) depende de que las Piezas 5a (#907), 5b (#908) y 5c (#909) estén
mergeadas a `main` y corriendo un tiempo antes de poder medir algo real — el propio spec pide
esperar y no forzar si alguna sigue abierta, y varias vueltas anteriores (`wt-3`/`wt-2`/`wt-1`)
respetaron eso correctamente, liberando el claim y reagendando mientras 5b/5c seguían
incompletas. Un timeout por `max_turns` sin commits disparó el mecanismo "DES-TRABE (Opus)"
("ANTI-LOOP: ya corrió 5× y no lo ejecutó"), y la vuelta siguiente (`wt-1`, 2026-09-06 06:20) ya
hizo lo correcto: reverificó que #907/#908/#909 estaban `completado`+mergeados desde
2026-09-03 (908 vía sus sub-items #985/#986/#987) y, sin tocar código, descompuso la medición
en **#9990393** (inventario del barrido + snapshots de ocupación) y **#9990394** (ratio
útil/ruido + reporte final y cierre de #910). Pero ese proceso **murió a media escritura** del
comentario de decisión (texto cortado en `comentarios_claude`) antes de intentar **cerrar** al
padre — el log solo registra `claim_liberado_al_morir_la_vuelta`, y el pool lo repartió de
nuevo sin trabajo propio que hacer. Verificado esta vuelta: #9990393 y #9990394 siguen intactos,
`pendiente_revision`, sin reclamar — la descomposición original seguía siendo correcta, nadie
más la tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard
(`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le quedan 2 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #9990393 y #9990394 cierren. Detalle en
`docs/roadmap-bucle-reap-item-910-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo real (inventario del barrido, snapshots de ocupación, ratio útil/ruido, reporte final)
sigue en #9990393/#9990394, pendientes de que una terminal los reclame.

## Item #9990385 — Seguimiento: 2 preguntas sin resolver de #9990373 (MR-36 depende_de sobre paraguas) (RESUELTO — ya aplicado antes de que Irving aprobara)

Sub-item de seguimiento de #9990373 con 2 preguntas `requiere_irving` sin `opcion_elegida`: (q1)
cómo resolver que `depende_de` no cierre sobre un paraguas sin `merge_commit` propio, y (q2) cómo
desbloquear `#9990329`→`#942` MIENTRAS se aplica el fix. Irving aprobó la Opción 1 recomendada en
ambas (paraguas "transparente" para q1; override manual puntual para q2). Verificado: **q1 ya
estaba en `main`** — commit `1f872e15` (integrado vía `93a88800`), el mismo día que se generó
`#9990385` y ANTES de que Irving llegara a aprobarlo, así que la decisión de Irving coincidió con
lo ya implementado por pura carrera de timing (mismo patrón que #733/#741/#753/#9990003/#9990353).
El método único `RoadmapCircuitoService::estaCerradoParaDependencia()` ya implementa exactamente la
Opción 1: un id de `depende_de` cuenta como cerrado si es hoja con `merge_commit` propio, o si es un
paraguas `completado` sin sub-items abiertos. **q2 resultó innecesaria** — no se aplicó ningún
override manual (verificado: el log de `#942`/`#9990329` no tiene esa entrada); ambos ya tienen
`merge_commit` propio (llegaron a `main` por sus propias ramas) y hoy la cadena de dependencias
resuelve correctamente por razones reales de trabajo pendiente (`#943` espera a `#942`, que espera
a `#941`; `#9990329` espera a su último hijo `#9990391`), no por el bug de MR-36. Detalle completo
en `docs/circuito-mr36-seguimiento-item-9990385-verificacion.md`. **Sin cambio de código de
negocio** — solo se dejó constancia escrita de la verificación.

## Item #936 — bucle reap sobre el paraguas raíz de la épica MAPA DE RED (MR-00) (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#878/#905/#906/#907/#924/#9990012/#917/#910, esta
vez sobre el paraguas RAÍZ de toda la épica MAPA DE RED. #936 llegó a reclamarse pese a que su
propio `prompt` advierte explícitamente "no lo reclames para 'hacerlo'" y cita la lista de items
previos con este mismo bug. Causa: el 2026-09-04 `circuito:liberar-cascada-mapa-red` detuvo la
cascada en un item atascado (techo en MR-07); el driver `#9990366` que debía subir ese techo
cerró en falso ("no registró rama de trabajo"); Irving, el 2026-09-06, dio la directiva
`destapado_mapa` — "construir el mapa completo sin pacing" — y **quitó `excluir_pool_automatico`
de #936 mismo** para destrabarlo. Minutos después el pool reclamó **al padre**, no a sus hijos:
con el freno quitado y `estado_aprobacion=aprobado_irving`, el paraguas volvió a ser un
candidato normal de dispatch. Verificado contra la BD real: cada hijo (`origen_item_id=936`)
tiene su **propio** `excluir_pool_automatico` independiente del padre — la mayoría de los
liberados por la cascada (MR-04 en adelante) ya estaban en `false` desde antes de la directiva
(solo MR-33/34/35 siguen congelados a propósito, como dice la propia `description` de #936); el
padre nunca debió ser el objetivo de "destapar". Corrección: se ejecutó el intento de cierre
faltante (`estado_aprobacion='completado'`); el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS")
lo reenrutó a `aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto`, "le
quedan 29 sub-item(s) abierto(s)"), liberando `worker_sid`/`claimed_at` y sacándolo del
pool/reaper otra vez — sin tocar el flag individual de ningún hijo, así que la directiva de
Irving de avanzar "sin pacing" en MR-04 en adelante sigue vigente. Detalle en
`docs/roadmap-bucle-reap-item-936-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo técnico real de la épica sigue en sus ~29 sub-items abiertos (MR-05 y MR-07 en
adelante).

## Item #9990471 — Vendedores: `sales`/`commissions`/`prospects` vacías en dev (RESUELTO — investigación, veredicto entregado)

Investigado (solo lectura, sin tocar datos): en dev `sales` y `prospects` tienen 0 filas **y
cero consumidores en el código** — sin modelo Eloquent, sin migración versionada (solo existen
en dumps SQL de mayo 2024), sin ningún controller/Vue que las lea. La UI real de Vendedores lee
Ventas de `client_main_information` y Prospectos del módulo **CRM**
(`crm_lead_information`/`crm_main_information`) — ambas tablas son remanentes del diseño
original, reemplazadas antes de usarse: **veredicto (c) descontinuadas**. `commissions` es un
caso distinto: el listener que la poblaría (`CalculateClientCommission`/
`CalculateProspectCommission`) existe pero tiene el cuerpo entero comentado (el evento sí se
dispara, el listener no actúa), y su tabla hija `commissions_details` tiene **566,780 filas
reales** — pero las 566,780 son huérfanas (100% sin padre en `commissions`, que está en 0).
Migraciones de julio 2024 muestran que `commissions`+`commissions_details` se truncaron juntas
el 2024-07-12 y `commissions_details` se repobló después (created_at desde 2024-07-15) sin que
`commissions` volviera a llenarse — import/sync parcial. Por eso el endpoint real de Comisiones
(`ComissionController::getCommissionsBySeller`, JOIN `commissions`+`commissions_details`)
devuelve vacío hoy pese a las 566K filas de detalle. No se puede afirmar sin más si `commissions`
es (a) falta importar de prod o (c) descontinuada tras julio 2024 — depende de si prod tiene esa
tabla poblada hoy, algo que sólo Irving puede verificar. Detalle completo, evidencia por
archivo:línea y recomendación en
`docs/vendedores-sales-commissions-prospects-item-9990471-verificacion.md` — este veredicto
condiciona a #9990448/#9990449/#9990450 (vistas en Talento) y #9990453 (migración de
comisiones). **Sin cambio de código** (item nivel A, solo-lectura por spec).

## Item #9990496 — MR-16 Fase 2b: verificar DoD con enlace real de Tultitlán (RESUELTO — bloqueado por ausencia total de datos, no solo de Tultitlán)

Dependencia (Fase 2a, `#9990495` — botón "Trazar ruta a OLT" + capa Leaflet resaltada) confirmada
mergeada a main (commit `9fe6cfd3`, integrada vía `609c50d7`). Al buscar un `MapaRedEnlaceServicio`
real de Tultitlán para correr `GET /mapa-red/api/enlaces-servicio/{id}/trazo`, se confirma que
`mapared_enlaces_servicio` está en **0 filas en dev — no solo sin Tultitlán, sin ninguna zona**. El
fallback del propio item ("si no hay de Tultitlán, usar el más completo disponible") no tiene sobre
qué aplicarse. Se rastreó la causa un nivel más abajo de lo que documentaba
`docs/mapared-comparativa-item-963-verificacion.md` (agosto, bloqueado entonces por `#941`/MR-05
sin correr): aunque **hoy** tanto `#941` (MR-05, espejo legado) como `#951` (MR-15, comando de
backfill) están marcados `completado`, el backfill real (`mapared:backfill`, sin `--dry-run`)
**nunca se ejecutó** contra la BD de dev — `mapared_puertos`/`mapared_hilos` siguen en 0 filas, y
`EnlacesServicioController::store()` exige `puerto_nap_id` real (`exists:mapared_puertos,id`), así
que ni siquiera se podría dar de alta un enlace de prueba (y el item lo prohíbe explícitamente: solo
lectura, no modificar datos). Un `--dry-run` del backfill (100% de solo lectura) confirma que, de
correrse, la zona TULTITLAN solo llegaría al **50% de cobertura** de puertos (5 de 10 cajas) — por
debajo del 80% que el propio DoD de `#951` exige. Item cerrado documentando el hallazgo (mismo
patrón que `#963`, reconfirmado sin cambios el 2026-09-07); el DoD de MR-16 (`#952`, sigue
`requiere_irving`) sigue sin poder verificarse con datos reales hasta que alguien corra el backfill
real + dé de alta un enlace real vía la UI existente. Detalle completo en
`docs/mapared-mr16-fase2b-item-9990496-verificacion.md`. **Sin cambio de código** — investigación
read-only, tal como pedía el spec.

## Item #9990408 — MR-12 UI panel de unión de hilos — bucle reap sobre paraguas ya descompuesto, con re-apertura espuria del guard (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936,
con una variante nueva. Una vuelta previa (2026-09-07 10:43-10:44) ya descompuso correctamente el
trabajo en **#9990501** (Fase A: `EmpalmesController`+rutas, backend) y **#9990502** (Fase B: doble
clic + modal Quasar en `LeafletMapRed.vue`, frontend), y también mergeó a la propia rama de
`#9990408` los componentes UI compartidos (`ElementSidePanel.vue`+`EmpalmesPanel.vue`+
`helper/empalmes-request.js`, commit `e5d2c8b4`) antes de repartir el resto. Esa vuelta **sí**
intentó el cierre — el guard de paraguas lo parqueó bien (`aprobado_irving`+
`excluir_pool_automatico=true`). La variante: 8 minutos después, `jarvis-ya-decidido` procesó un
brief pendiente del propio item ("la decisión ya estaba tomada y el item seguía retenido sin que
faltara nadie") y lo devolvió a `aprobado_revisor`; el scheduler, al despachar, **limpió
`excluir_pool_automatico`** (log índice 226) — sacándolo de su parqueo correcto y devolviéndolo al
pool sin que hubiera trabajo propio pendiente. A diferencia de los precedentes (donde el
cierre-intento simplemente nunca se hacía), aquí sí se hizo bien y otro mecanismo lo deshizo; se
deja anotado como observación para quien investigue la maquinaria del circuito, sin tomarlo como
objeto de este item (fuera de alcance de "MR-12 UI panel de unión de hilos"). Verificado en esta
vuelta: `#9990502` sigue paraguas de sus propios hijos — `#9990520` (Fase B1: `EmpalmeConfigDialog.vue`
+ enganche en Mufa/NAP) cerró y mergeó **durante esta misma vuelta** (commit `bf860bb5`); `#9990521`
(Fase B2: enganche en Rack/`RackConfiguration.vue`) sigue `aprobado_revisor` sin reclamar — es la
única pieza real pendiente. Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard
(`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true`, sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando `#9990502` cierre (que a su vez depende de
`#9990521`). Detalle en `docs/roadmap-bucle-reap-item-9990408-verificacion.md`. **Sin cambio de
código de negocio** — el trabajo real de UI (Rack) sigue en `#9990521`, listo para reclamarse.

## Item #962 — MR-26 Cobertura comercial derivada de la infraestructura — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408. #962 (MR-26: polígono de cobertura vendible = unión de radios de drop de NAPs con
puertos libres + endpoint de consulta por coordenada + capa de sectores inalámbricos) ya estaba
descompuesto correctamente en 4 sub-items (`origen_item_id=962`) por una vuelta previa: **#9990522**
(Fase 1 — motor de cobertura, `completado`, merge `9681a91f`), **#9990523** (Fase 2 — endpoint de
consulta por coordenada, `requiere_irving`, sin reclamar), **#9990524** (Fase 3 — capa de sectores
inalámbricos, `requiere_irving`, sin reclamar) y **#9990525** (Fase 4 — UI + DoD final,
`en_progreso` en `wt-3`). El historial de #962 muestra el ciclo típico de esta familia (escalación
por anti-loop, la directiva de Irving `destapado_mapa` que le quitó `excluir_pool_automatico`,
timeout por `max_turns`, dos `soltar-claim` por muerte de proceso, varios `limite_cuenta_detectado`
y finalmente `reaper-rapido` re-encolándolo como huérfano) **sin que en ningún punto se intentara
cerrarlo** — la descomposición ya existía desde antes de la mayoría de esos ciclos, pero nadie
disparó el guard de paraguas. Verificado: los 4 hijos siguen intactos (3 abiertos, 1 mergeado), sin
que esta vuelta tocara el que ya tiene dueño (`#9990525` en `wt-3`, aislamiento #334). Corrección:
esta vuelta ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php` bloque "(2b)
PARAGUAS") lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (evento
`paraguas_abierto` en el log, "le quedan 3 sub-item(s) abierto(s)"), sacándolo del pool/reaper
hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando
#9990523, #9990524 y #9990525 cierren los tres. Detalle en
`docs/roadmap-bucle-reap-item-962-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo real de MR-26 (endpoint de consulta, sectores inalámbricos, UI + DoD con dirección real de
Tultitlán) sigue en #9990523/#9990524/#9990525, pendientes de aprobación/reclamo.

## Item #9990554 — MR-17 Fase 3 (endpoint + botón "¿Quién depende de esto?" + panel de impacto) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990549. #9990554 (sub-item de seguimiento de #953, DoD: cortar virtualmente
el troncal `T-TULTITLAN-FO96-1` debe devolver clientes + monto mensual en riesgo) fue escalado por
el DES-TRABE de Opus (ANTI-LOOP, 2 corridas sin ejecutar) con un brief de 3 preguntas que Irving
aprobó el 2026-09-07 22:33 (todas con la opción recomendada). Una vuelta previa (`wt-3`, 2026-09-07
22:37) ya hizo lo correcto: investigó el código real (`RedGraphService::fanOutDesde` ya soporta
cable/hilo/puerto/splitter/nap/mufa —Fase 1 #9990552—, `MapaRedEnlaceMrrService::calcular` ya
resuelve cliente+MRR —Fase 2 #9990553—) y descompuso el trabajo en **#9990593** (Fase 3a: endpoint
`GET /mapa-red/api/impacto`, backend), **#9990594** (Fase 3b: botón + panel de impacto en
`ElementSidePanel.vue`, frontend) y **#9990595** (Fase 3c: verificar el DoD del padre #953 con
datos sintéticos o reales). Pero esa vuelta nunca intentó **cerrar** #9990554 tras crear los
sub-items ("Sin codigo propio en esta vuelta") — quedó `en_progreso` colgado; `reaper-rapido` lo
vio con el slot libre y lo re-encoló (`reap_count=1`), y el pool lo repartió de nuevo sin trabajo
propio que hacer. Verificado esta vuelta: los 3 hijos (`origen_item_id=9990554`) siguen intactos
— #9990593/#9990595 `requiere_irving` sin reclamar, #9990594 `en_progreso` en `wt-2` (con dueño,
no se toca) — la descomposición original seguía siendo correcta, nadie más la tocó. Corrección:
esta vuelta ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php` bloque "(2b)
PARAGUAS") lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (evento
`paraguas_abierto` en el log, "le quedan 3 sub-item(s) abierto(s)"), sacándolo del pool/reaper
hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando
#9990593, #9990594 y #9990595 cierren los tres. Detalle en
`docs/roadmap-bucle-reap-item-9990554-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo real de MR-17 Fase 3 (endpoint de impacto, botón+panel jerárquico, verificación del DoD)
sigue en #9990593/#9990594/#9990595, pendientes de aprobación/reclamo.

## Item #9990549 — MR-24e Fase 3 (modo dibujo cable/troncal, frontend) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962. #9990549 (MR-24e Fase 3: modo dibujo de cable/troncal con snap a extremos en
`LeafletMapRed.vue`, hermano del flujo de NAP ya implementado como Fase 1a/1b) fue escalado
inicialmente por el DES-TRABE de Opus por un brief de 4 preguntas (implementación, distinción
cable/troncal, elementos válidos de snap, alcance de persistencia); Irving las aprobó el
2026-09-07 18:39, incluida `q4` = "solo frontend en esta fase, persistencia en fase 4". Una vuelta
previa (`wt-5`, 2026-09-07 18:51) ya hizo lo correcto: investigó el código real (ubicó el patrón
NAP en `LeafletMapRed.vue` ~1438-1566, confirmó que la Fase 2 —`CableAltaRapidaController::store`—
ya está mergeada, commit `75ad6d05`) y descompuso el trabajo respetando `q4` en **#9990581** (Fase
3a: toggle + selector Cable\|Troncal + trazo + snap), **#9990582** (Fase 3b: finalizar trazo +
formulario mínimo + vista previa local, sin persistencia) y **#9990583** (Fase 4: conectar la
vista previa al POST real). Pero el proceso murió a media escritura del comentario de decisión
(texto cortado en `comentarios_claude`) antes de intentar **cerrar** al padre — el log solo
registra `soltar-claim` ("terminó sin cerrar el item... se libera el reclamo"), y el pool lo
repartió de nuevo (a `wt-3`, esta vuelta) sin trabajo propio que hacer. Verificado esta vuelta: los
3 hijos (`origen_item_id=9990549`) siguen intactos, `pendiente_revision`, sin reclamar — la
descomposición original seguía siendo correcta, nadie más la tocó. Corrección: esta vuelta ejecuta
el intento de cierre faltante; el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le quedan
3 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #9990581, #9990582 y #9990583 cierren los tres.
Detalle en `docs/roadmap-bucle-reap-item-9990549-verificacion.md`. **Sin cambio de código de
negocio** — el trabajo real del modo dibujo de cable/troncal sigue en
#9990581/#9990582/#9990583, pendientes de que una terminal los reclame.

## Item #9990624 — Crear versión se ve como "no funciona" (changelog IA cuelga sin timeout) — bucle reap sobre paraguas ya descompuesto, con Fase 1 ya mergeada (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549. #9990624 (reportado por David: "no puedo hacer una versión"
— el botón «Generar changelog con IA» tarda 2-4 min sin timeout y el navegador corta el request)
ya estaba descompuesto correctamente por una vuelta previa (`wt-1`, 2026-09-08 12:36): **Fase 1**
(timeout de 90s en `ClaudeApiClient::sendMessage()`) quedó commiteada en la rama del propio item
(`754631d6`) y **Fases 2/3, 4 y 5** se delegaron con detalle exacto a **#9990626** ("mover
generación a job en cola + que Guardar no dependa de la IA"), **#9990627** ("next-version:
considerar tags de git + reportar divergencia") y **#9990628** ("verificar end-to-end con el rango
real V1.32..HEAD"). Pero esa vuelta nunca intentó **cerrar** al padre — el log solo registra
`reaper-rapido` re-encolándolo como huérfano, y el pool lo repartió de nuevo sin trabajo propio que
hacer. Verificado esta vuelta: la Fase 1 **ya está en `main`** (commit `c201ad00`, confirmado por
grep directo del `->timeout()` en `ClaudeApiClient.php:58`); #9990626 (Fase 2+3) también **ya
cerró y mergeó** (`completado`, merge_commit `c65d422f`) durante el tiempo en que #9990624 seguía
colgado; solo quedan abiertos #9990627 (`requiere_irving`, sin reclamar) y #9990628
(`aprobado_revisor`, sin reclamar) — la descomposición original seguía siendo correcta, nadie más
la tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard
(`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le quedan 2 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #9990627 y #9990628 cierren los dos. Detalle en
`docs/roadmap-bucle-reap-item-9990624-verificacion.md`. **Sin cambio de código de negocio propio
de esta vuelta** — el trabajo real que falta (Fase 4 — `next-version` con tags de git — y Fase 5 —
verificación end-to-end) sigue en #9990627/#9990628, pendientes de aprobación/reclamo.

## Item #9990650 — Talento: placeholders de firma en plantillas + colocación en el render (empresa/trabajador) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624. #9990650 (Fase 3+4 de #9990646: agregar
`{{firma.empresa}}`/`{{firma.trabajador}}` a las 11 plantillas de Talento con slots declarados en
`talento_document_template_signature_slots`, que el renderer inserte la imagen por slot o deje
línea en blanco si falta, y marcar el documento "completo" solo cuando todos los slots requeridos
tengan firma) ya había sido escalado por anti-loop (DES-TRABE Opus) y reaprobado por Irving con
sus 3 preguntas resueltas. Una vuelta previa (`wt-3`, 2026-09-09 08:45) ya hizo lo correcto: corrió
`circuito:cabida` (NO CABE, ya había timeouteado) y descompuso el trabajo en **#9990653**
(placeholders en las 11 plantillas + nueva versión, sin deps), **#9990654** (renderer con imagen
inline base64 por slot + `EmployeeDocumentPackageService` alimenta `firma.*`, decisión propia:
data URI en vez de URL para no depender de la ruta `slot_key` de #9990649) y **#9990655**
(`status_efectivo` reusado de #9990649 + verificación final E2E, correctamente bloqueado con
`depende_de=[9990649]` hasta que ese item mergee). Pero esa vuelta nunca intentó **cerrar** al
padre — el log solo registra `soltar-claim` ("la vuelta de wt-3 terminó sin cerrar el item...
se libera el reclamo y vuelve a la cola como aprobado_irving"), y el pool lo repartió de nuevo sin
trabajo propio que hacer. Verificado esta vuelta: los 3 hijos (`origen_item_id=9990650`) siguen
intactos, `pendiente_revision`, sin reclamar — la descomposición original seguía siendo correcta,
nadie más la tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard
(`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le quedan 3 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #9990653, #9990654 y #9990655 cierren los tres
(el último sigue bloqueado por `depende_de=[9990649]`). Detalle en
`docs/roadmap-bucle-reap-item-9990650-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo técnico real de Talento (placeholders en plantillas, renderer con imagen inline,
`status_efectivo` por slots) sigue en #9990653/#9990654/#9990655, pendientes de que una terminal
los reclame.

## Item #9990658 — Fix leak de `.modal-backdrop` en 3 componentes Flotas (candado permisos + aplicar pago se ven gris) (RESUELTO — ya aplicado por commit directo antes de reclamarse)

Sub-item de seguimiento de #126 (creado 2026-09-09 09:16), con el diagnóstico exacto ya hecho:
`FleetGeofenceShow.vue`, `FleetGeofenceList.vue` y `FleetRuleList.vue` tenían un `<style>`
NO-scoped con la regla global `.modal-backdrop.show { z-index: 9998; opacity: .5 }`, que se
inyecta en `<head>` para TODA la app y sube el backdrop sobre otros modales (candado de
administración/permisos, aplicar pago en clientes), dejando la pantalla en gris. Al llegar a
ejecutarlo, el fix **ya estaba en `main`**: commit `29156e4c` ("evita que el backdrop de 3
modales tape ModalSimple/pagos"), aplicado directo por Irving 2 minutos después de crearse el
item (mismo acto en que se diagnosticó #126) y ya integrado antes de que el pool repartiera
#9990658 — mismo patrón de carrera de timing documentado para #733/#741/#753/#9990003/#9990353.
Verificado línea por línea: los 3 archivos tienen `<style scoped>` (no `<style>` a secas) y usan
clases propias del componente (`flt-geofence-backdrop`, `flt-rule-backdrop`) en vez de la clase
global en el div del backdrop; `grep` confirma que no queda ninguna regla `.modal-backdrop.show`
NO-scoped en esos 3 archivos; `ModalSimple.vue` intacto (fuera de alcance). Detalle en
`docs/flotas-modal-backdrop-leak-item-9990658-verificacion.md`. **Sin cambio de código** — el fix
ya estaba aplicado.

## Item #9990733 — Mapa de Red: flujo animado de puntos en los enlaces OLT — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650. #9990733 (feature visual/UX del módulo
nuevo MAPA DE RED: puntos animados en los enlaces OLT→NAP→cliente, donde velocidad=potencia
óptica, color=umbral y sin-movimiento=caído) fue escalado por el DES-TRABE de Opus (ANTI-LOOP, ya
se había re-escalado sin ejecutar) y reaprobado por Irving con sus 5 preguntas resueltas —
verificado por hash: **las 5** corresponden a la opción recomendada (rollout piloto en 1 NAP tras
feature flag OFF; potencia óptica desde el último valor cacheado en BD, sin consultas nuevas en
vivo a la OLT; umbrales dBm = los mismos de Torre de Control V2; forzar `preferCanvas:false` solo
si hiciera falta; combo completo de guardas de rendimiento). Una vuelta previa (`wt-2`,
2026-09-10 16:30-18:09) ya hizo lo correcto: completó el PASO 0 de auditoría exigido por el propio
prompt (mapa real = `LeafletMapRed.vue`, Leaflet geográfico con renderer SVG por default —la
pregunta q4 resulta un no-op—, estado de ONT vía `OLTsService::getSignalAndStatus` cacheado en
`olt_onus`, y el hallazgo crítico de que los 3084 enlaces que dibuja el mapa hoy son polylines
genéricas de infraestructura sin asociación real a cliente/ONT porque `mapared_enlaces_servicio`
sigue en 0 filas, igual que documentaron los items #9990496/#963), dejó commiteada en su propia
rama la hoja de estilos completa del efecto (`91f51cf8`, CSS puro e inerte hasta el wiring), y
descompuso el trabajo real en **#9990739** (Fase 1: wiring + feature flag sobre un NAP piloto),
**#9990740** (Fase 2: los 4 estados simulados a mano), **#9990741** (Fase 3: guardas de
rendimiento + botón congelar) y **#9990742** (Fase 4: conectar a la potencia real, con instrucción
explícita de reportar bloqueo si `mapared_enlaces_servicio` sigue vacía en vez de simular datos
falsos). Pero esa vuelta nunca intentó **cerrar** al padre — el log solo registra `soltar-claim`
("terminó sin cerrar el item... se libera el reclamo"), y el pool lo repartió de nuevo sin trabajo
propio que hacer. Verificado esta vuelta: los 4 hijos (`origen_item_id=9990733`) siguen intactos,
sin reclamar — la descomposición original seguía siendo correcta, nadie más la tocó; el commit
`91f51cf8` seguía sin mergear a `main`, CSS aditivo e inerte. Corrección: esta vuelta ejecuta el
intento de cierre faltante; el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le
quedan 4 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990739, #9990740, #9990741 y
#9990742 cierren los cuatro. Detalle en `docs/roadmap-bucle-reap-item-9990733-verificacion.md`.
**Sin cambio de código de negocio** — el trabajo técnico real del efecto (wiring del flag, los 4
estados, guardas de rendimiento, conexión a la potencia óptica real) sigue en
#9990739/#9990740/#9990741/#9990742, pendientes de que una terminal los reclame.

## Item #9990740 — MR flujo animado Fase 2 (cuatro estados simulados) — bucle reap sobre paraguas ya descompuesto + hallazgo de build roto en main (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990733. #9990740 (Fase 2 del flujo
animado: aplicar a mano los 4 estados CSS `est-ok/est-degradado/est-critico/est-caido` sobre el
NAP piloto de la Fase 1) ya había sido descompuesto correctamente por una vuelta previa (`wt-1`,
2026-09-10 18:49) tras confirmar que la Fase 1 (#9990739/#9990752/#9990753) ya estaba en `main`:
**#9990754** (mecanismo multi-piloto, generalizar el `pilotRouteId` único a varias rutas) y
**#9990755** (elegir NAP real + repartir los 4 estados + verificar, depende de #9990754). Pero esa
vuelta nunca intentó **cerrar** al padre — terminó por muerte del proceso (`soltar-claim`), y el
pool la repartió de nuevo sin trabajo propio que hacer. **Hallazgo adicional de esta vuelta:** al
verificar el build con `npm-build.sh` se confirmó que **`main` está roto ahora mismo** —
`SyntaxError: Identifier 'flujoAnimadoConfig' has already been declared` en `mapUtils.js:376` —
residuo de que las Fases 1a (#9990752) y 1b (#9990753) declararon cada una su propio
`flujoAnimadoConfig`/`setFlujoAnimadoConfig` en bloques no solapados, así que el merge no lo
detectó como conflicto. Ya existía un fix verificado (commit `4390c0d0`, build pasa de fallar a
compilar OK) dejado por una vuelta anterior en la propia rama de #9990740, pero nunca se mergeó a
`main`. Corrección: (1) se ejecutó el intento de cierre faltante sobre #9990740 — el guard de
paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenrutó a `aprobado_irving` +
`excluir_pool_automatico=true`, sacándolo del pool/reaper hasta que #9990754 y #9990755 cierren
los dos; (2) se creó **#9990756** (marcado `urgente=true`/`priority=alta`) con el diff exacto del
fix embebido en el `spec`, para que se aplique de inmediato en vez de dejar el build roto para el
resto de terminales. Detalle completo en
`docs/roadmap-bucle-reap-item-9990740-verificacion.md`. **Sin cambio de código de negocio propio**
— el trabajo real de Fase 2 sigue en #9990754/#9990755, y el fix del build roto en #9990756,
pendientes de que una terminal los reclame.

## Item #9990742 — MR flujo animado Fase 4 (conectar a la potencia óptica real) — bloqueado por ausencia real de datos (RESUELTO — sin código, mismo bloqueo ya documentado 3 veces)

Fase 4 del flujo animado (sub-item de `#9990733`): el propio spec exige verificar el bloqueo de
datos ANTES de picar código y, si `mapared_enlaces_servicio` sigue en 0 filas, reportarlo como
bloqueo real en vez de simular datos falsos (regla explícita del prompt, y decisión ya aprobada
por Irving en `q3`: "abortar la Fase 4 y escalar con reporte del estado del bloqueo"). Verificado
en dev (2026-09-11): **`mapared_enlaces_servicio` sigue en 0 filas** — el mismo hallazgo ya
documentado en `docs/mapared-comparativa-item-963-verificacion.md` (agosto) y
`docs/mapared-mr16-fase2b-item-9990496-verificacion.md` (2026-09-07). `olt_onus` sí tiene señal
óptica cacheada (2954 filas, vía `smartolt:sync-critical`, la fuente ya fijada por `q2`), pero sin
la tabla de enlaces de servicio no existe forma de saber qué route del mapa corresponde a qué fila
de `olt_onus` — conectar un ONT "cualquiera" sin ese enlace real sería el dato falso disfrazado de
real que el propio item prohíbe. Adicionalmente, la Fase 3 (`#9990741`, guardas de rendimiento)
todavía no tiene `merge_commit` (tiene rama, sin mergear), así que la dependencia declarada
("Fases 1-3 mergeadas") tampoco está completa — secundario frente al bloqueo de datos, que
impediría la Fase 4 de todos modos. Documentado el punto exacto donde engancharía la Fase 4 cuando
el bloqueo se resuelva (`flujoAnimadoConfig`/`isFlujoAnimadoPilot()` en
`resources/js/components/module/mapared/helper/mapUtils.js:369-397`, reglas de `classList`/
umbrales dBm/fuente ya fijas, solo falta el join real enlace↔ONT) para que el próximo ejecutor no
repita la investigación. Detalle completo en
`docs/mapared-flujo-animado-fase4-item-9990742-verificacion.md`. **Sin cambio de código de
aplicación** — el bloqueo real es la falta de `mapared_enlaces_servicio` poblada, fuera de alcance
de este item (depende de correr `mapared:backfill` real y/o dar de alta enlaces reales vía UI).

## Item #9990807 — Pantalla del colaborador (pendientes/firmados) + tablero de pendientes para Irving + endpoints API-first — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante, con merge del commit sobrante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650. #9990807 (sub-item de #9990792: endpoints
API-first + pantalla del colaborador para pendientes/firmados + tablero admin de antigüedad para
Irving) ya había sido escalado por el revisor (ambigüedad de alcance) y re-aprobado por el
DES-TRABE (Opus). Una vuelta previa (`wt-1`, 2026-09-11 14:21) ya hizo lo correcto: resolvió las 6
preguntas estructuradas con la opción recomendada de cada una (vista única con tabs, tablero
agrupado por colaborador, solo-lectura + botón recordar, endpoints internos bajo
`/talento/portal/*` y `/talento/api/colaboradores/*`) y descompuso el trabajo en **#9990813**
(endpoints self-scoped de documentos), **#9990814** (pantalla del colaborador) y **#9990816**
(tablero admin de pendientes). Antes de descomponer dejó un commit propio en la rama del padre
(`4095fcc8`: helpers `SignatureImageInput`/`SignatureSlotStatus`, pensados explícitamente para el
endpoint de #9990813). Pero esa vuelta agotó sus turnos (`max_turns`), se reanudó una vez y nunca
llegó a intentar **cerrar** al padre — `reaper-rapido` lo vio con el slot libre y lo re-encoló
(`reap_count=1`), y el pool lo repartió de nuevo sin trabajo propio que hacer. Verificado esta
vuelta: los 3 hijos (`origen_item_id=9990807`) siguen intactos, `requiere_irving`, sin reclamar —
la descomposición original seguía siendo correcta, nadie más la tocó. Corrección (variante de los
precedentes): a diferencia de la mayoría de estos casos, aquí el commit sobrante de la rama del
padre SÍ se integró primero (`circuito:integrar 9990807`, merge `1f65a01a`, verificado con los 2
archivos ya en `main`) por ser reusable y de bajo riesgo — así `#9990813` no tiene que redescubrir
ni recrear esos helpers. Después se ejecutó el intento de cierre faltante; el guard
(`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le quedan 3 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #9990813, #9990814 y #9990816 cierren los
tres. Detalle en `docs/roadmap-bucle-reap-item-9990807-verificacion.md`. **Sin código de negocio
nuevo** — el trabajo real (endpoints, pantalla del colaborador, tablero admin) sigue en
#9990813/#9990814/#9990816, pendientes de que una terminal los reclame.

## Item #9990826 — Reapertura de acuses, test E2E — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807. #9990826 (implementar
`tests/Feature/Modules/Talento/ReaperturaAcusesTest.php` contra `AcuseReopeningService`/
`TemplateVersionService`/`EmployeeDocumentPackageService`, ya en `main`) ya había sido
descompuesto correctamente por una vuelta previa (`wt-4`, 2026-09-11 15:39): tras un
`circuito:cabida` en NO CABE, investigó a fondo el código real (guard de tipo `acuse` en
`AcuseReopeningService.php:36`, filtro `status='completo'` en líneas 40-42, disparo solo con
`tipoCambio='mayor'` en `TemplateVersionService::createVersion()`, columnas reales de
`talento_colaboradores`/`talento_employee_document_signatures`) y dejó la receta **completa**
(setup exacto, asserts, caso negativo, 2 bordes) en el sub-item **#9990834**, para que el
próximo ejecutor no re-investigara nada. Pero esa vuelta murió a media escritura del comentario
final sin intentar **cerrar** al padre — el log solo registra `soltar-claim` ("terminó sin
cerrar el item... se libera el reclamo"), y el pool lo repartió de nuevo sin trabajo propio que
hacer. Verificado esta vuelta: `AcuseReopeningService.php`/`TemplateVersionService.php` siguen en
`app/Modules/Addons/Talento/Services/`; #9990834 sigue intacto, `pendiente_revision`, sin
reclamar — la descomposición original seguía siendo correcta, nadie más la tocó; la rama del
padre no tenía commits propios (`commits_rama:0`, consistente con el timeout previo). Corrección:
esta vuelta ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php` bloque "(2b)
PARAGUAS") lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (evento
`paraguas_abierto` en el log, "le quedan 1 sub-item(s) abierto(s)"), sacándolo del pool/reaper
hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando
#9990834 cierre. Detalle en `docs/roadmap-bucle-reap-item-9990826-verificacion.md`. **Sin cambio
de código de negocio** — el trabajo real (implementar y correr `ReaperturaAcusesTest.php` con la
receta ya documentada) sigue en #9990834, pendiente de que el revisor lo tríe y una terminal lo
reclame.

## Item #9990836 — Fase 3 de #9990833 (buscador usa serie_equipo_norm) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826. #9990836 (Fase 3 de
#9990833: que el buscador de Clientes use `serie_equipo_norm` para encontrar el SN en cualquier
formato) ya había sido descompuesto correctamente por una vuelta previa (mismo `wt-1`, 2026-09-11
17:10): tras un `circuito:cabida` en NO CABE (ya había timeouteado antes), y de verificar que la
dependencia (Fase 2, #9990835) ya estaba `completado` y mergeada y que el backfill ya había
corrido en dev (5153/5606 filas con `serie_equipo_norm` poblado), descompuso el trabajo en
**#9990847** (Fase 3a: `config/clientes_busqueda.php` + `ClienteSearchService::normalizar()` con
el case `sn_canonico`, backend) y **#9990848** (Fase 3b: listado de Clientes muestra
`serie_equipo` + indicador de `serie_equipo_origen`, UI). Pero esa vuelta murió sin intentar
**cerrar** al padre — el log solo registra `claim_liberado_al_morir_la_vuelta` ("muerte del
proceso: kill, OOM o freno a media vuelta"), y el pool lo repartió de nuevo (otra vez a `wt-1`,
esta vuelta) sin trabajo propio que hacer. Verificado esta vuelta: #9990847 y #9990848
(`origen_item_id=9990836`) siguen intactos, `pendiente_revision`, sin reclamar — la descomposición
original seguía siendo correcta, nadie más la tocó. Corrección: esta vuelta ejecuta el intento de
cierre faltante; el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le
quedan 2 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990847 y #9990848 cierren los dos.
Detalle en `docs/roadmap-bucle-reap-item-9990836-verificacion.md`. **Sin cambio de código de
negocio** — el trabajo técnico real (columna normalizada en el buscador de clientes + indicador
visual de origen en el listado) sigue en #9990847/#9990848, pendientes de que una terminal los
reclame.

## Item #9990856 — CIRC-02 (paraguas: comentario de Irving = respuesta, hilo + re-encolado) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836. #9990856 es
un PARAGUAS explícito por título y `prompt` ("no lo trabajes directo... agrupa CIRC-02a, CIRC-02b
y CIRC-02c... cierra por cascada"): objetivo del feature (que escribir un comentario de Irving en
un item `requiere_irving` sea, por sí solo, el acto de responder — sin segundo clic, sin pisar
`comentarios_claude`, con re-encolado y precedencia sobre el prompt original). El revisor lo
escaló por tocar arquitectura de decisión del propio circuito; Irving aprobó la opción
recomendada (PARAGUAS no-ejecutable, cierra por cascada, el circuito solo trabaja los
sub-items). Una vuelta previa ya había descompuesto correctamente el trabajo en **#9990857**
(CIRC-02a — diagnóstico solo-lectura de respuestas perdidas), **#9990858** (CIRC-02b — mecanismo:
tabla `roadmap_item_respuestas` + re-encolado automático) y **#9990859** (CIRC-02c — visibilidad
en la Torre + watchdog de respuestas sin consumir). Pero esa vuelta nunca intentó **cerrar** al
padre — quedó `en_progreso` colgado, y el pool lo repartió de nuevo sin trabajo propio que hacer.
Verificado esta vuelta: los 3 hijos siguen intactos (#9990857 `en_progreso` reclamado por `wt-3`,
no se tocó; #9990858 `requiere_irving` sin reclamar; #9990859 `pendiente_revision` sin reclamar)
— la descomposición original seguía siendo correcta, nadie más la tocó. Corrección: esta vuelta
ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo
reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el
log, "le quedan 3 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre
en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990857, #9990858 y #9990859
cierren los tres. Detalle en `docs/roadmap-bucle-reap-item-9990856-verificacion.md`. **Sin cambio
de código de negocio** — el trabajo técnico real del mecanismo de respuestas de Irving sigue en
#9990857/#9990858/#9990859, pendientes de que sus dueños los cierren.

## Item #9990892 — Fase 2 (C1) exención de docs del detector de colisiones — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/#9990856.
#9990892 (Fase 2 de #9990863: exentar documentación —`CLAUDE.md`, `docs/**`, `CHANGELOG*`,
bitácora, `*.md`— del detector de colisiones en vuelo, con simulación histórica previa a
activar) exige como precondición leer `>=15 min` reales acumulados en
`storage/logs/circuito-despacho-*.log` (canal `circuito_despacho`, recién mergeado por la Fase
1) ANTES de tocar código. Una vuelta previa (`wt-6`, 2026-09-11 18:46) ya hizo lo correcto: corrió
`circuito:cabida` (NO CABE, `histórico`), confirmó que el canal aún no tenía ningún archivo de
log (precondición sin cumplirse) y descompuso el trabajo en **#9990896** (Fase 2a — confirmar con
evidencia real si colisión es causa frecuente de terminales ociosas), **#9990897** (Fase 2b —
implementar la exención en `footprintDeRama()`/`footprintEnVivo()`) y **#9990898** (Fase 2c —
simulación histórica antes de activar). Pero esa vuelta murió por timeout sin intentar **cerrar**
al padre — el reaper lo devolvió a `aprobado_revisor` (`reap_count=1`), y el pool lo repartió de
nuevo sin trabajo propio que hacer. Verificado esta vuelta: los 3 hijos (`origen_item_id=9990892`)
siguen intactos — #9990896 ya está a su vez parqueado por otra vuelta como paraguas de su propio
hijo #9990916 (ver entrada siguiente), #9990897 en `aprobado_revisor` sin reclamar, #9990898 en
`aprobado_irving` sin reclamar — la descomposición original seguía siendo correcta, nadie más la
tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php`
bloque "(2b) PARAGUAS") lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (evento
`paraguas_abierto` en el log, "le quedan 2 sub-item(s) abierto(s)"), sacándolo del pool/reaper
hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando
#9990897 y #9990898 cierren (y, en cascada, #9990896→#9990916). Detalle en
`docs/roadmap-bucle-reap-item-9990892-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo técnico real (exención de docs + simulación histórica) sigue en #9990897/#9990898, y la
precondición de evidencia real sigue en #9990896→#9990916.

## Item #9990896 — Fase 2a de #9990892 (colisión como causa de terminales ociosas) — bucle reap sobre paraguas ya descompuesto, esperando ventana de tiempo real (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/#9990856, con
una variante: aquí el bloqueo no es de decisión sino de **tiempo real transcurrido**. #9990896
("confirmar con evidencia real si colisión es causa frecuente de terminales ociosas") exige NO
tocar código hasta que existan `>=15 min reales` después de `2026-09-11 18:54 CST` (cuando se
confirmó que `storage/logs/circuito-despacho-*.log` aún no existía en ningún checkout) — medir
antes sería fabricar una conclusión sin datos, justo lo que el propio spec prohíbe. Una vuelta
previa (`wt-5`, 2026-09-11 18:55 CST) ya hizo lo correcto: corrió `circuito:cabida` (NO CABE,
`historico_excede_umbral`), reconfirmó con `date` que el bloqueo seguía vigente, y descompuso el
reintento en **#9990916** ("Fase 2a (reintento) — medir colisión... con >=15 min reales
acumulados", bloqueado hasta después de `2026-09-11 19:09 CST`). Pero esa vuelta murió
(`soltar-claim`, "muerte del proceso") antes de intentar **cerrar** al padre — el pool lo repartió
de nuevo (a `wt-4`) sin trabajo propio que hacer. Verificado esta vuelta: la hora real seguía en
`18:56:02 CST` (antes del umbral, nada que medir todavía); `circuito:cabida` devolvió `CABE
[ya_descompuesto]` (según el propio código, ese motivo solo significa "no re-descompongas", no
"hay trabajo nuevo"); `#9990916` sigue intacto, `pendiente_revision`, sin reclamar — la
descomposición original seguía siendo correcta, nadie más la tocó. Corrección: esta vuelta ejecuta
el intento de cierre faltante; el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a
`aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le
quedan 1 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990916 cierre. Detalle en
`docs/roadmap-bucle-reap-item-9990896-verificacion.md`. **Sin cambio de código de aplicación** —
la medición real (grep de `circuito-despacho-*.log` cruzado con `colision_pausada_por`) sigue en
#9990916, bloqueada hasta que pasen los `>=15 min` reales exigidos.

## Item #9990886 — CIRC-03 (bandeja de decisiones: separar espera decisión de espera insumo material) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/
#9990892/#9990896. #9990886 (CIRC-03: distinguir en la bandeja de Irving el item que espera una
decisión del que espera un insumo material — credencial, hardware, sesión presencial,
autorización) ya había sido descompuesto correctamente por una vuelta previa (`wt-6`, 2026-09-11
18:50): tras un `circuito:cabida` en NO CABE, lo partió en **#9990904** (Fase A: migración
aditiva `motivo_espera`), **#9990905** (Fase B: clasificar los 8 items conocidos + barrido) y
**#9990906** (Fase C: Torre — separar la bandeja en 2 listas + ajustar métricas). Pero esa vuelta
nunca intentó **cerrar** al padre — el log solo registra `reaper` re-encolándolo como huérfano
(`huerfano_reencolado`, `reap_count=1`), y el pool lo repartió de nuevo sin trabajo propio que
hacer. Verificado esta vuelta que el trabajo real sí siguió avanzando por los hijos correctos:
**#9990905 ya cerró y mergeó** (`merge_commit=ea6705a8`); **#9990904** también tiene su código
mergeado (`merge_commit=a94fd956`) pero quedó **parqueado como paraguas de sus propios nietos**
(#9990914 `completado` + #9990915 `en_progreso`, reclamado por `wt-4` — con dueño, no se toca);
**#9990906** sigue `aprobado_irving` sin reclamar, listo para tomarse. Corrección: esta vuelta
ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo
reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el
log, "le quedan 2 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre
en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990904 (vía #9990915) y #9990906
cierren. Detalle en `docs/roadmap-bucle-reap-item-9990886-verificacion.md`. **Sin cambio de
código de negocio** — el trabajo real de CIRC-03 (exponer `motivo_espera` en el modelo con
verificación e2e, y la Torre con las 2 listas separadas) sigue en #9990915/#9990906, pendientes de
que sus dueños los cierren.

## Item #9990869 — CIRC-03 "Bandeja de decisiones real" (sub-item de #9990854) — RESUELTO (duplicado exacto ya completado por #9990886)

#9990869 es un segundo sub-item, generado por otra pasada de auditoría del circuito (sub-item de
#9990854 "corrección del Circuito CC", en vez de sub-item de #9990860 como #9990886), con **título
y spec idénticos** a `#9990886` (misma tabla de 8 items bloqueados, mismo prompt de 5 pasos,
mismo criterio de aceptación) — dos auditorías separadas del circuito llegaron a la misma
conclusión y crearon el mismo item por caminos distintos. Verificado contra la BD y `main`
reales: `#9990886` y sus 3 hijos (`#9990904` Fase A migración, `#9990905` Fase B clasificación,
`#9990906` Fase C Torre) están **todos `completado` con `merge_commit`** desde antes de que se
reclamara `#9990869`. Confirmado punto por punto: columna `motivo_espera` existe
(`Schema::hasColumn`); `RoadmapItem::scopeBandejaDecision()`/`scopeBandejaInsumo()`
(`RoadmapItem.php:1938-1960`) separan la bandeja; `TorreControl.vue` tiene la tarjeta "📦 Esperan
un insumo tuyo" con acordeón por `motivo_espera`; los 10 items de la tabla (283/661/671/683/691/
692/718/724/9990075/9990571) tienen `motivo_espera` poblado + `excluir_pool_automatico=1`;
`RoadmapController::index()` calcula `espera_decision`/`espera_insumo` por separado y el KPI
"Requiere Irving" ya solo cuenta `bandejaDecision()`. Todo el criterio de aceptación ya estaba
satisfecho. Detalle completo en `docs/circuito-circ03-duplicado-item-9990869-verificacion.md`.
**Sin cambio de código** — nada que implementar, el trabajo ya existía en `main`.

## Item #9990893 — Fase 3 (C2) "Listos para terminal" + contador de vueltas quemadas + cablear #9990798 — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/
#9990892/#9990896/#9990886. #9990893 (sub-item de #9990863: corregir la semántica de "Listos
para terminal" en `SupervisorService` para que respete dependencias/`motivo_espera` igual que el
despachador real, sumar el contador de "vueltas quemadas" y cablear #9990798) ya había sido
descompuesto correctamente por una vuelta previa (`wt-3`, 2026-09-11 19:02): verificó primero que
la pieza "cablear #9990798" ya estaba aplicada (`depende_de=[9990790]` ya en BD, solo faltaba que
`SupervisorService` lo respetara), corrió `circuito:cabida` (NO CABE, `historico_excede_umbral`)
y partió el resto en **#9990923** (Fase 3a: excluir `depende_de` sin resolver / `motivo_espera`
en `listosParaTerminal()`/`listosParaTerminalTotal()`, reusando `estaCerradoParaDependencia()` de
MR-36), **#9990924** (Fase 3b: heurística de texto sobre bloqueos declarados en el prompt) y
**#9990925** (Fase 3c: mostrar en la Torre el contador `reap_count`/`veces_timeouteo` que ya
existe, sin crear columna nueva). Pero el comentario de esa vuelta se cortó a media oración
("...contador de vueltas quemadas que YA EXISTE como reap_count/veces") sin intentar **cerrar**
al padre — el reaper lo detectó huérfano 27 minutos después y lo re-encoló (`reap_count=1`), y el
pool lo repartió de nuevo sin trabajo propio que hacer. Verificado esta vuelta: #9990923 sigue
reclamado activamente por `wt-3` (con dueño, no se toca), #9990924 y #9990925 siguen
`aprobado_revisor` sin reclamar — la descomposición original seguía siendo correcta, nadie más la
tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php`
bloque "(2b) PARAGUAS") lo reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (evento
`paraguas_abierto` en el log, "le quedan 3 sub-item(s) abierto(s)"), sacándolo del pool/reaper
hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando
#9990923, #9990924 y #9990925 cierren los tres. Detalle en
`docs/roadmap-bucle-reap-item-9990893-verificacion.md`. **Sin cambio de código de negocio** — el
trabajo técnico real (SupervisorService, heurística de texto, contador en la Torre) sigue en
#9990923/#9990924/#9990925, pendientes de que sus dueños los cierren.

## Item #9990878 — Identidad unificada Fase 3 (doble escritura colaborador_id) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878-torre/#906/#907/#924/#9990012/
#917/#910/#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/
#9990856/#9990892/#9990896/#9990886/#9990893. #9990878 (Fase 3 de identidad unificada: escribir
`colaborador_id` en paralelo a `seller_id` en `client_main_information`) ya había sido
descompuesto correctamente por una vuelta previa (`wt-5`, 2026-09-11 20:16): tras confirmar por
hash sha1 que las 4 preguntas de Irving eligieron la opción recomendada, y encontrar que
`client_main_information` se escribe por **al menos 2 vías distintas** (Eloquent en alta normal
vs `DB::table` raw en import) — invalidando la premisa de `q1` de que un observer "no requiere
tocar callsites" —, partió el trabajo en **#9990962** (Fase 3a: mapear TODOS los callsites
reales, Eloquent vs raw, `completado`+mergeado), **#9990963** (Fase 3b: implementar la doble
escritura con observer+parches raw+feature flag), **#9990964** (Fase 3c: comando
`identidad:verificar-consistencia`+KPI card) y **#9990965** (Fase 3d: activar el flag en dev +
validación end-to-end + cerrar el padre). Pero el comentario de esa vuelta se cortó a media
oración (texto truncado justo al listar los sub-items) sin intentar **cerrar** al padre — el
reaper lo detectó huérfano 25 minutos después y lo re-encoló (`reap_count=1`), y el pool lo
repartió de nuevo sin trabajo propio que hacer. Verificado esta vuelta: #9990962 sigue
`completado`+mergeado; #9990963 y #9990964 siguen **activamente reclamados por `wt-5`**
(aislamiento #334, no se tocaron); #9990965 sigue `aprobado_revisor` sin reclamar — la
descomposición original seguía siendo correcta, nadie más la tocó. Corrección: esta vuelta
ejecuta el intento de cierre faltante; el guard (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo
reenruta a `aprobado_irving` + `excluir_pool_automatico=true` (evento `paraguas_abierto` en el
log, "le quedan 3 sub-item(s) abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre
en cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990963, #9990964 y #9990965
cierren los tres. Detalle en `docs/roadmap-bucle-reap-item-9990878-verificacion.md`. **Sin
cambio de código de negocio** — el trabajo técnico real de la Fase 3 sigue en
#9990963/#9990964 (reclamados por `wt-5`) y #9990965, pendientes de cierre.

## Item #9990870 — CIRC-04 (re-triaje de la cola: cancelados, basura, 430 items sin nivel de riesgo) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/
#9990892/#9990896/#9990886/#9990893/#9990878. #9990870 (CIRC-04, sub-item de #9990854: PASO 0
backup, PASO 1 coherencia de cancelados, PASO 2 reportar basura sin ejecutar, PASO 3 clasificar
nivel_riesgo de los 430 sin nivel, PASO 4 proponer re-priorización sin aplicarla) ya había sido
descompuesto correctamente por una vuelta previa, uno a uno según el propio prompt del item, en
**#9991008** (PASO 0+1 — backup + coherencia, `completado`), **#9991009** (PASO 2 — reportar
basura incluyendo #185, `en_progreso`), **#9991010** (PASO 3 — confirmar nivel_riesgo de los 430,
`completado`) y **#9991011** (PASO 4 — propuesta de re-priorización en `docs/`, `en_progreso`).
Esa vuelta timeouteó por `max_turns` sin volver a intentar **cerrar** al padre tras descomponerlo
— el item quedó `en_progreso` colgado, escaló a Irving por "DES-TRABE (Opus)" bajo la categoría
`ejecutor_no_pudo`, Irving lo reaprobó, y `reaper-rapido` lo detectó huérfano (slot libre) y lo
repartió de nuevo sin trabajo propio que hacer. Verificado esta vuelta: #9991008 y #9991010 siguen
`completado`; #9991009 y #9991011 siguen **activamente reclamados** por `wt-3` y `wt-4`
respectivamente (aislamiento #334, no se tocaron) — la descomposición original seguía siendo
correcta, nadie más la tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante; el
guard (`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le quedan 2 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #9991009 y #9991011 cierren los dos. Detalle
en `docs/roadmap-bucle-reap-item-9990870-verificacion.md`. **Sin cambio de código de negocio** —
el trabajo real de CIRC-04 (reportar la basura del PASO 2 y redactar la propuesta de
re-priorización del PASO 4) sigue en #9991009/#9991011, a cargo de `wt-3`/`wt-4`.

## Item #9991072 — Fix de contraste dark-mode en Panorama Árbol (5 selectores) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/
#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/
#9990892/#9990896/#9990886/#9990893/#9990878/#9990870. #9991072 (sub-item de seguimiento de
#9991071: aplicar el fix de contraste dark-mode en Panorama Árbol, 5 selectores con diff ya
identificado por cálculo real de contraste WCAG) fue triado (nivel B, autorizado por el revisor
con confianza alta) y ya había sido descompuesto correctamente por una vuelta previa (`wt-4`,
2026-09-13 10:44): corrió `circuito:cabida` (NO CABE, `historico_excede_umbral`) y partió el
trabajo por archivo en **#9991073** (Fase A — CSS vars `--pa-warn`/`--pa-danger` + fix
`.pa-error-raiz` en `TorrePanoramaArbol.vue`) y **#9991074** (Fase B — fix
`.pa-error`/`.pa-c-cola`/`.pa-c-detenido`/`.pa-decision-aviso` en `TorreArbolNodo.vue` +
verificación final y cierre del padre #9991071). Pero esa vuelta murió antes de intentar
**cerrar** a #9991072 — el log solo registra `soltar-claim`/`claim_liberado_al_morir_la_vuelta`
("muerte del proceso: kill, OOM o freno a media vuelta"), y el pool lo repartió de nuevo sin
trabajo propio que hacer. Verificado esta vuelta: #9991073 y #9991074 siguen intactos,
`pendiente_revision`, sin reclamar — la descomposición original seguía siendo correcta, nadie más
la tocó. Corrección: esta vuelta ejecuta el intento de cierre faltante; el guard
(`RoadmapItem.php` bloque "(2b) PARAGUAS") lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true` (evento `paraguas_abierto` en el log, "le quedan 2 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo cuando #9991073 y #9991074 cierren los dos. Detalle
en `docs/bitacora/2026-09-13-item-9991072.md`. **Sin cambio de código de negocio** — el trabajo
técnico real (las 6 variables/selectores CSS del fix de contraste, especificados letra por letra
en la description del item, y el cierre en cascada de #9991071) sigue en #9991073/#9991074,
pendientes de que una terminal los reclame.

## Item #9991073 — Fase A CSS vars --pa-warn/--pa-danger en TorrePanoramaArbol.vue (RESUELTO — ya aplicado por commit directo antes de reclamarse)

Sub-item de seguimiento de #9991072 (que a su vez descompuso #9991071), con el diff exacto ya
identificado: agregar `--pa-warn:#d97706; --pa-danger:#dc2626;` a `.pa-wrap`, el par oscuro
`--pa-warn:#fbbf24; --pa-danger:#f87171;` a `.pa-wrap.pa-dark`, y cambiar `.pa-error-raiz{
color:#dc2626; }` por `color:var(--pa-danger,#dc2626);` en
`resources/js/components/module/releases/torre-control/TorrePanoramaArbol.vue`. Al leer el
archivo real, las tres piezas **ya estaban aplicadas**: commit `2f6b2cf2` ("fix(torre-control):
corrige contraste del árbol en modo oscuro") las aplicó completas (junto con el fix hermano de
`TorreArbolNodo.vue`) apenas **un minuto después** de que se crearan los sub-items de seguimiento
(`#9991073`/su hermano de Fase B), directo en la rama de `#9991071`, y quedó mergeado a `main` vía
`d4867cf8` antes de que el pool repartiera este item — mismo patrón de carrera de timing
documentado para `#733`/`#741`/`#753`/`#9990003`/`#9990353`/`#9990658`. Verificado con `grep`
directo: los 3 pares de valores y el `color:var(--pa-danger,#dc2626)` coinciden exactamente con
lo pedido. Detalle en `docs/torrepanorama-arbol-css-vars-item-9991073-verificacion.md`. **Sin
cambio de código** — el fix ya estaba aplicado. NO cierra al padre #9991072 (sigue esperando a su
hermano de Fase B en `TorreArbolNodo.vue`).

## Item #9991082 — Hoja de ruta: `subs.filter is not a function` (RESUELTO) + hallazgo: el checkout principal NO se sincroniza tras los merges (#9991086)

Sesión directa con Irving (2026-09-14). La Hoja de ruta de la Torre V2 tronaba con `subs.filter is
not a function`. **Causa raíz:** `roadmap_items.subtasks` carga dos formas incompatibles — la
lista de sub-tareas de la UI `[{title,completed,completed_at}]` y, desde `circuito:sub-item`
(`SubItemCommand.php:117` / `DependenciaGate`), el objeto `{"descomposicion":{"depende_de":[…]}}`
(492 items desde el 09-04). Detonador: `c96b81c0` (#9990890, viernes 09-11) pasó la pestaña de
`?vista=backlog` al universo completo. **Fix (`c0d8ce1f`):** Capa 1 `RoadmapController::
normalizarSubtasks()` — `subtasks` SIEMPRE lista + `descomposicion` aparte en
index/show/store/update/start/complete + detalle, solo en memoria (columna y DependenciaGate
intactos); Capa 2 `RoadmapTab.subtasksDe()` + `RoadmapItemDetalle` normalizan antes de iterar.
Deuda estructural (columna propia + backfill, endpoints crudos restantes, `PATCH …/subtasks`
borraría `depende_de`) → **#9991084**. Detalle en `docs/bitacora/2026-09-14-item-9991082.md`.

⚠️ **Lo que de verdad explicaba el "apareció el sábado": `/var/www/megaisp` (nginx dev + cron
del circuito) llevaba desde el 09-10 sin sincronizarse con `main`.** `MergeRunner::
syncCheckoutPrincipal()` **nunca ha funcionado** (0 `ok` / 74 `sucio` / 190 `omitido`): hace
`update-ref` de `main` ANTES de `git status --porcelain` en el principal, así que el índice viejo
siempre parece sucio. El rebuild post-merge tampoco corre desde el 09-09. → item urgente
**#9991086**. Mientras no se mergee: al arrancar en `/var/www/megaisp`, `git diff HEAD --stat` —
si difiere, `git stash push` (no `reset --hard`) + `CIRCUITO_BUILD_MODE=prod bash
deploy/circuito/npm-build.sh` + `view:clear && route:clear && view:cache`. **Nunca commitear desde
un árbol desincronizado** (revertiría lo mergeado). `public/.well-known/` (reto ACME, root) cuenta
como sucio: excluido en `.git/info/exclude` del principal.

## Item #9991083 — Autorrenovación TLS de dev: el banner "expira en 24 días" decía la verdad (E2, pendiente root)

El cert de `dev.meganett.com.mx` SÍ se renovó el 2026-09-11 16:08 (`authenticator = webroot`,
`live/*.pem → cert2`), pero **nginx nunca recargó** (worker desde el 09-01) y `:443` sigue
sirviendo `cert1` (vence **2026-10-08**). El banner (`EnvironmentHealthService::certificado()`)
lee el cert **en vivo** con caché de 30 s: no miente. `certbot.timer` está vivo (`renew` de hoy:
exit 0 en 2 s = cert2 no toca). Faltaba el **deploy-hook**: versionado en
`deploy/letsencrypt/renewal-hooks/deploy/reload-nginx.sh` + runbook en `deploy/README-dev-tls.md`
(instalar hook, `nginx -t && systemctl reload nginx`, re-probar con `openssl s_client`, `certbot
renew --dry-run`) — **es nginx, no Apache**. Todo requiere root → item de respuesta **#9991085**.


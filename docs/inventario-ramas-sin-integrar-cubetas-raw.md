# CIRC-08 Fase 3+4 — Cuatro cubetas del cruce con el roadmap (raw)

Item #9990921 (padre #9990891, CIRC-08). Insumo: censo de la Fase 1 (#9990917,
`docs/inventario-ramas-sin-integrar-censo-raw.md`, 196 ramas únicas) + archivos agregados de la
Fase 2 (#9990920, `docs/inventario-ramas-sin-integrar-archivos-raw.md`). Archivo INTERMEDIO (no
prosa final), para que el sub-item de Fase 5+6 del padre lo funda en el reporte definitivo.
Generado en solo-lectura desde el worktree `wt-2`, sin merge/rebase/push/checkout en el repo
principal ni en worktree ajeno.

## Método

Cada una de las 196 ramas del censo se clasificó así:

1. `item_id` no resuelve a una fila viva de `roadmap_items` (ni por columna `branch` ni por patrón
   del nombre), o el id parseado del nombre ya no existe en la tabla → **cubeta (d)**.
2. `estado_aprobacion` ∈ {`cancelado`, `rechazado`} → **cubeta (d)**.
3. `estado_aprobacion = completado`:
   - si `roadmap_items.merge_commit` está poblado y **es ancestro de `main`** (`git merge-base
     --is-ancestor <merge_commit> main`) → **cubeta (a)**.
   - si `merge_commit` está poblado pero **NO** es ancestro de `main` → **cubeta (a), variante
     anomalía** (evidencia completa abajo).
   - si `merge_commit` está vacío → **cubeta (b)**, salvo que el item tenga
     `sin_merge_esperado=true` o `cierre_sin_codigo_motivo` poblado (cierre legítimamente
     justificado sin merge — se documentan aparte, no cuentan como bandera roja).
4. Cualquier otro `estado_aprobacion` (abierto: `en_progreso`, `aprobado_irving`,
   `aprobado_revisor`, `pendiente_revision`, …) → **cubeta (c)**.

Verificación de ancestría hecha con `git cat-file -e` (el commit existe como objeto) +
`git merge-base --is-ancestor` contra `main` real de este worktree (`9519d35d…`, sincronizado al
arrancar). Fechas de cierre tomadas de `roadmap_items.completed_at` (todas las filas de la cubeta
(b) tenían este campo poblado; no hizo falta caer a `revisado_at`/`updated_at`).

## Totales (196 ramas)

| cubeta | conteo (filas) | conteo (items únicos) |
|---|---|---|
| (a) completado + mergeado, normal (squash-merge, sin problema) | 81 | 81 |
| (a) completado + mergeado, **anomalía a investigar** | 1 | 1 |
| (b) completado SIN merge, bandera roja nominal (corte 2026-08-08) | 23 | 22 (1 rama es copia divergente del mismo item) |
| (b) completado SIN merge, **justificado** (`sin_merge_esperado`/`cierre_sin_codigo_motivo`) | 2 | 2 |
| (c) abierto, avance en curso | 40 | 38 (2 items con 2 intentos de rama) |
| (d) huérfana (sin item o item cancelado) | 49 | 49 |
| **TOTAL** | **196** | — |

---

## Cubeta (a) — completado + rama mergeada

### (a-normal) — 81 ramas, sin acción

Todas tienen `estado_aprobacion=completado` y `merge_commit` poblado y verificado como ancestro
real de `main`. Aparecen en el censo de "ramas sin integrar" (`git branch --no-merged main`)
únicamente porque **el circuito integra por squash**: `MergeRunner` aplica el contenido de la
rama a `main` en un commit nuevo ("Integra circuito #N … a main"), no hace un merge de git en el
sentido estricto — así que la rama original, con sus propios commits, **nunca** será ancestro de
`main` aunque su contenido sí lo esté. Esto es el comportamiento **esperado** del pipeline, no un
hallazgo. No se lista la tabla completa de las 81 (ruido — ya están en el censo de la Fase 1 con
su `estado_aprobacion=completado`/`status=done`); el criterio de arriba basta para que la Fase 5+6
las excluya sin más trabajo.

### (a-anomalía) — 1 caso: item #9990210, evidencia completa

| campo | valor |
|---|---|
| item | #9990210 — "Que los hallazgos del barrido se arreglen solos: una MENCIÓN de frontera dura deja de retener, salvo dinero y credenciales" |
| rama | `circuito/item-9990210-que-los-hallazgos-del-barrido-se-arregle` (tip `dffb301b70`) |
| `merge_commit` en BD | `37b7143e4083c4a3975bcafadc20ca37d67d818d` |
| `completed_at` | 2026-09-04T18:14:35Z |
| `aprobado_por` | irving:CARLOS |

Verificación: `37b7143e…` **existe** como objeto git (`git cat-file -e` OK) pero **no es ancestro
de `main`** (`git merge-base --is-ancestor` → NO), y tampoco lo es la propia rama (`dffb301b70`
tampoco es ancestro de `main`). Sin embargo, `main` sí contiene el trabajo real del item, bajo
**otro hash**:

```
git log main --grep="9990210" --oneline
791a23fd feat(circuito#9990210): una MENCION de frontera dura deja de retener, salvo dinero y credenciales
```

Y sus dos items-hijos de seguimiento (Fase 3 y Fase 4 de #9990210) también están en `main`:

```
7c2e7be3 Integra circuito #9990246 (…fase-3-de-9990210-registrar-en-torre) a main
53b4758d Integra circuito #9990247 (…fase-4-de-9990210-candado-de-regresio) a main
```

**Interpretación:** el trabajo de #9990210 sí llegó a `main` — no es un item completado en falso
— pero el `merge_commit` que quedó registrado en `roadmap_items` (`37b7143e…`) apunta a un commit
que ya no existe en el historial de `main` (verosímilmente una reescritura de historia posterior:
rebase/amend del propio `MergeRunner` o de una corrección manual que cambió el hash del commit de
integración sin actualizar la columna `merge_commit` del item). Es el caso exacto que la cubeta (a)
pedía "investigar" — **sin ser un hueco de trabajo perdido**, sí es un dato de auditoría (`merge_commit`)
desactualizado que podría confundir a cualquier verificación automática futura que confíe en ese
campo literal en vez de buscar el contenido en `main`. Se deja como hallazgo aislado para que la
Fase 5+6 decida si vale la pena un backfill de `merge_commit` en este caso puntual (bajo impacto:
1 de 149 items con `merge_commit`).

---

## Cubeta (b) — completado sin merge (bandera roja + partición por fecha)

**Corrección importante encontrada durante esta fase (afecta directamente el hallazgo
principal):** el prompt del item da como dato ya medido que "el guard `verificarCierre` … se
introdujo el 2026-08-08 (commit `ce14a359`)" y pide partir la cubeta (b) usando esa fecha. Al
rastrear el código real, `ce14a359` (2026-08-08) solo **crea la clase** `ThomasService` con el
concepto de verificación de cierre — **no bloquea nada todavía**. El guard real tiene DOS checks
independientes, cada uno cableado/endurecido en un commit y fecha DISTINTOS a los del prompt:

| check | qué bloquea | commit que lo activó | fecha real |
|---|---|---|---|
| cierre sin rama y sin `merge_commit` (ninguno de los dos) | exige `cierre_sin_codigo_motivo` | `97754d38` (wiring) → `b83722f3` (endurecido, #9990403) | wiring 2026-08-21, endurecido **2026-09-06 16:18** |
| cierre **con rama pero sin `merge_commit`** (el que aplica a las 22 filas de abajo) | exige `sin_merge_esperado=true` | `0f046eef` (#9990738) | **2026-09-10 18:13:12** |

El caso que importa para las 22 filas de esta cubeta es el segundo (todas tienen `branch` poblado
en BD y `merge_commit` vacío) — y ese check **no existió hasta ayer, 2026-09-10 a las 18:13**, no
desde el 2026-08-08 que da el prompt como corte. Verificado con `git log -p -S` sobre
`JarvisService.php`/`ThomasService.php` y `git show -s --format='%ad'` de cada commit.

**Consecuencia:** con el corte 2026-08-08 (el que pide el prompt) las 22 filas caen TODAS en
"posterior al guard" (`b2`) porque sus fechas de cierre son de agosto-septiembre. Pero con el
corte REAL del check que de verdad les aplica (2026-09-10 18:13), las 22 caen TODAS **antes** de
que ese check existiera — es decir, **cero de los 22 son un hueco real del guard hoy vigente**;
son deuda histórica de cuando ese check todavía no se escribía, exactamente como la cubeta
`b1_anterior_al_guard` que el prompt anticipaba (solo que con la fecha de corte corregida). Se
registra esta corrección vía `circuito:reportar --tipo=decision` porque cambia el diagnóstico que
consumirá la Fase 5+6: sin esta corrección, el reporte final anunciaría "23 huecos reales en el
guard de cierre" cuando la evidencia dice que son 0.

Se deja la partición nominal pedida por el prompt (usando 2026-08-08 literal) para que la Fase 5+6
decida cuál usar, con ambas lecturas documentadas:

- **Partición nominal (corte 2026-08-08, tal como pide el prompt):** las 22 caen en
  `b2_posterior_al_guard` (0 en `b1_anterior_al_guard`).
- **Partición corregida (corte real del check aplicable, 2026-09-10 18:13):** las 22 caen en
  "anterior al check real" — 0 huecos vigentes hoy.

### Evidencia completa — 22 items (23 filas, 1 rama es copia divergente)

Todos con `estado_aprobacion=completado`, `roadmap_items.branch` poblado, `merge_commit` vacío,
`sin_merge_esperado=false`, `cierre_sin_codigo_motivo` vacío:

| item | fecha_cierre (completed_at) | cerrado_por (aprobado_por) | rama | título |
|---|---|---|---|---|
| #9990647 | 2026-09-09T15:51:04Z | revisor:brief-async(#437) | `circuito/item-9990647-talento-completar-documento-formula-2` (+1 copia divergente: `…-formula`) | Talento: "Completar documento" — formulario de campos fillables (colaborador vs doc-específico) que guarda donde debe + regenera |
| #9990477 | 2026-09-07T21:27:26Z | irving:CARLOS | `circuito/item-9990477-recuperacion-de-contrasena-agregar-el-l` | Recuperación de contraseña: agregar el link 'Olvidé mi contraseña' en el login y verificar el ciclo completo |
| #9990459 | 2026-09-07T17:05:36Z | jarvis-ya-decidido | `circuito/item-9990459-mr-22-fase-3-arbol-derivado-de-max-3-n` | MR-22 Fase 3 — Árbol derivado de máx 3 niveles como panel lateral colapsable (D22) |
| #9990439 | 2026-09-08T01:53:24Z | irving:admin | `circuito/item-9990439-mr-24e-modo-dibujo-en-el-mapa-alta-de` | MR-24e — Modo dibujo en el mapa: alta de NAP en 3 pasos + cable con snap a extremos |
| #9990437 | 2026-09-07T00:07:22Z | revisor:backlog | `circuito/item-9990437-mr-24d-endpoint-de-alta-rapida-de-nap` | MR-24d — Endpoint de alta rápida de NAP (tipo + splitter, sin nombre a mano) |
| #9990330 | 2026-09-04T16:09:46Z | revisor:backlog | `circuito/item-9990330-fase-2c-escribir-y-verificar-el-test-d` | Fase 2c — Escribir y verificar el test de integración de la cadena depende_de sobre RoadmapItem::despachable() |
| #9990328 | 2026-09-04T19:11:03Z | irving:CARLOS | `circuito/item-9990328-mr-06a-backend-portar-los-6-controlle` | MR-06a — Backend: portar los 6 controllers Geo (grupo vivo) a MapaRed apuntando a mapared_* |
| #9990286 | 2026-09-04T15:33:48Z | irving:admin | `circuito/item-9990286-fase-3a-i-de-9990246-crear-la-clase-p` | Fase 3a-i de #9990246 — Crear la clase pura Support/AblandamientoFrontera.php |
| #9990076 | 2026-09-04T14:06:58Z | irving:admin | `circuito/item-9990076-fase-1-repro-secuencial-mismo-proceso` | Fase 1 - Repro secuencial (mismo proceso) del cascade sobre #32 + instrumentacion temporal |
| #833 | 2026-09-04T14:47:59Z | irving:CARLOS | `circuito/item-833-fase-1a-ii-parte-3n-resolver-colision` | Fase 1a-ii parte 3/N: resolver colisión failed_jobs (aislado de #831) y continuar el ciclo fix-drift por el siguiente lote |
| #824 | 2026-09-05T00:24:58Z | timeout:reanudado | `circuito/item-824-documentacioncorporativa-fase-5c2-ui` | DocumentaciónCorporativa Fase 5c.2 — UI: armar entrega desde una solicitud |
| #825 | 2026-08-29T23:50:02Z | revisor:backlog | `circuito/item-825-jarvis-parte-3b-fase-1-migracion-mo` | Jarvis Parte 3b — Fase 1: migración + modelos jarvis_conversaciones/jarvis_mensajes |
| #806 | 2026-08-31T23:22:04Z | irving:admin | `circuito/item-806-jarvis-parte-3b-decidir-donde-vive-el` | Jarvis Parte 3b — Decidir dónde vive 'el chat' de las sugerencias (brief de Irving) y cablearlo |
| #798 | 2026-09-08T17:37:40Z | irving:admin | `circuito/item-798-deriva-216-fase-1b-exportar-el-esquema` | Deriva #216 Fase 1b: exportar el esquema de megaisp_dryrun a storage/schema/reference.sql (comando schema:build-reference) |
| #759 | 2026-09-04T02:12:50Z | timeout:reanudado | `circuito/item-759-documentacioncorporativa-fase-5b-servi` | DocumentaciónCorporativa Fase 5b — servicio de armado de entrega (ZIP + acta PDF + hash SHA-256) |
| #758 | 2026-09-04T13:51:35Z | irving:admin | `circuito/item-758-documentacioncorporativa-fase-5a-dc-so` | DocumentaciónCorporativa Fase 5a — dc_solicitudes: correr migraciones + CRUD |
| #740 | 2026-09-01T20:13:04Z | irving:admin | `circuito/item-740-deriva-de-esquema-216-fase-3-consumi` | Deriva de esquema #216 — Fase 3: consumidores + entregable final + cierre |
| #734 | 2026-09-04T13:51:35Z | irving:admin | `circuito/item-734-documentacioncorporativa-fase-2a-repos` | DocumentacionCorporativa Fase 2a — Repositorio documental: carga, versionado y descarga |
| #705 | 2026-09-03T19:26:28Z | irving:admin | `circuito/item-705-vigilante-on-box-208-invariantes-de` | Vigilante on-box (#208) — invariantes de Cola y discrepancias Sistema/Supervisor |
| #681 | 2026-08-29T04:49:08Z | irving:admin | `circuito/item-681-respuesta-documentacioncorporativa-fas` | [RESPUESTA] DocumentaciónCorporativa Fase 0 — commits en main + 6 desviaciones a ratificar |
| #672 | 2026-08-29T17:00:50Z | autopilot | `circuito/item-672-pieza-1-instrumentar-la-valvula-conta` | Pieza 1 — Instrumentar la válvula: contador de aperturas de frontera dura en la Torre |
| #875 | 2026-09-03T23:29:26Z | irving:CARLOS | `circuito/item-875-consola-fase-1-semaforo-de-motores-en` | Torre 24/7 · Pieza 3 — detectores de errores reales en el Motor de Auditoría (hoy sólo ve gaps de completitud y ya se agotaron) |

Nota: las 22 fechas de cierre van de 2026-08-29 a 2026-09-09 — todas antes de 2026-09-10 18:13:12
(alta del check real). Ninguna requiere acción de "hoyo del guard"; son candidatas, si se quiere,
a un backfill retroactivo de `merge_commit` (buscando en `main` el commit real que aterrizó cada
una, como se hizo arriba para la anomalía de #9990210) — trabajo de bajo riesgo, opcional, fuera
de alcance de esta fase de solo-clasificación.

### 2 casos justificados (no cuentan como bandera roja)

| item | justificación (`cierre_sin_codigo_motivo`, resumen) |
|---|---|
| #9990587 | Duplicado ya entregado por otro item: la migración pedida ya existía (creada por #9990572/#9990578, commit `e665c134`, ya corrida) — nada que mergear en esta rama. |
| #9990432 | Duplicado ya entregado por el item padre #956: el objetivo real (ocupación de puertos por NAP) ya está mergeado (`701b3d12`+`0b033f1b`) vía un endpoint distinto y mejor fundamentado que el que proponía el spec de este sub-item. |

---

## Cubeta (c) — abierto, avance en curso (40 filas / 38 items únicos)

Para la próxima vuelta que tome cualquiera de estos items: **reanudar en la rama existente**
listada abajo, no crear una nueva — ya tiene commits propios que se perderían de rehacerse desde
cero.

⚠️ **1 excepción marcada en la tabla:** #9990899 tiene 0 commits propios (su rama nace idéntica a
`main`) — no hay "avance real" que reanudar todavía, solo el nombre de rama reservado; tratarla
como si fuera un item recién aprobado, no como WIP.

⚠️ **2 items con 2 ramas distintas** (mismo id, dos intentos en fechas distintas — no son la misma
rama con hash divergente como los casos de la cubeta (d), son *dos nombres de rama diferentes*
para el mismo item): #652 (`item-652-preguntas-en-llano` y `item-652-bandeja-lenguaje-natural`,
mismo título) y #155 (`item-155-client-main-informationuser-normalizar`, aparece dos veces con
distinto conteo de commits — 1 y 3 — probablemente sesiones de trabajo distintas sobre el mismo
nombre de rama en momentos diferentes). Quien retome estos dos items debe revisar am­bas ramas
antes de continuar, para no perder el intento con más avance.

| item | estado | commits_adelante | rama | título |
|---|---|---|---|---|
| #9990899 ⚠️sin commits propios | aprobado_irving | 0 | `circuito/item-9990899-circ-02b-paso-1-tabla-roadmap-item-res` | CIRC-02b PASO 1 — Tabla roadmap_item_respuestas (migración aditiva + modelo) |
| #9990890 | en_progreso | 1 | `circuito/item-9990890-circ-07-bug-de-vista-la-pestana-hoja` | CIRC-07: Bug de vista — la pestaña Hoja de ruta muestra 0/0/0/0 con el filtro Todos habiendo 1,580 items |
| #9990834 | aprobado_revisor | 1 | `circuito/item-9990834-reapertura-de-acuses-implementar-y-pas` | Reapertura de acuses — implementar y pasar ReaperturaAcusesTest.php (happy path + negativo + 2 bordes) |
| #9990830 | aprobado_irving | 1 | `circuito/item-9990830-tablero-pendientes-fase-1-permiso-tale` | Tablero pendientes Fase 1 — permiso talento.documentos.ver-todos + endpoint admin GET /talento/api/documentos/pendientes |
| #9990825 | aprobado_irving | 1 | `circuito/item-9990825-voip-fase-3-emitir-version-publicar-g` | VoIP Fase 3 — emitir version, publicar GitHub Release y documentar el runbook de despliegue |
| #9990816 | aprobado_irving | 2 | `circuito/item-9990816-tablero-admin-para-irving-que-colaborad` | Tablero admin para Irving: qué colaborador tiene qué documento pendiente (con antigüedad + recordar) |
| #9990808 | aprobado_irving | 3 | `circuito/item-9990808-motor-de-ventas-catalogos-backend-migr` | Motor de ventas: catálogos backend (migraciones + modelos + permisos + CRUD API) — Fase 1 de #9990781 |
| #9990767 | aprobado_irving | 1 | `circuito/item-9990767-fase-4c-i-mergear-rama-backend-existen` | Fase 4c-i — mergear rama backend existente + migrar + vista Blade+Vue de la matriz de permisos por rol |
| #9990766 | aprobado_irving | 6 | `circuito/item-9990766-fase-4c-cerrar-vista-bladevue-de-la` | Fase 4c — cerrar: vista Blade+Vue de la matriz de permisos por rol + correr real + poblar reporte |
| #9990765 | aprobado_irving | 3 | `circuito/item-9990765-fase-4c-matriz-por-rol-permisos-asign` | Fase 4c — Matriz por rol: permisos asignados vs. permisos que surten efecto (punto 4) |
| #9990743 | aprobado_irving | 1 | `circuito/item-9990743-fase-01-diagnostico-confirmado-trans` | Fase 0+1 — diagnóstico confirmado (transacción+rollback): diff SUPERVISOR_MOSTRADOR vs Diana |
| #9990729 | aprobado_irving | 1 | `circuito/item-9990729-f3-9990674-ejecutar-a-mano-el-ensayo` | F3 (#9990674): ejecutar a mano el ensayo real de red cortada (push+GitHub Release TEST-) |
| #9990725 | aprobado_irving | 1 | `circuito/item-9990725-fase-4-extensiones-sembradas-por-depar` | Fase 4 — Extensiones sembradas por departamento (sección 8) |
| #9990723 | aprobado_irving | 2 | `circuito/item-9990723-fase-2-correcciones-minimas-del-modulo` | Fase 2 — Correcciones mínimas del módulo VoIP (sección 6) |
| #9990674 | aprobado_irving | 2 | `circuito/item-9990674-f3-releasepublisher-emision-atomica-c` | F3 — ReleasePublisher: emisión atómica con compensación |
| #9990519 | aprobado_irving | 1 | `circuito/item-9990519-mr-23-fase-4a-22-frontend-accion` | MR-23 fase 4a (2/2) — Frontend: acción 'Trazar' en LeafletMapRed.vue (click secuencial + render inmediato) |
| #9990631 | aprobado_irving | 1 | `circuito/item-9990631-seguimiento-pregunta-sin-resolver-de-2` | Seguimiento: pregunta sin resolver de #285 |
| #9990630 | aprobado_irving | 2 | `circuito/item-9990630-seguimiento-pregunta-sin-resolver-de-2` | Seguimiento: pregunta sin resolver de #274 |
| #9990607 | aprobado_irving | 1 | `circuito/item-9990607-fase3-parqueo-shadow-mode` | Fase 3 — Conmutación del origen del pago a TalentoLedgerEntry (FRONTERA DURA DE DINERO, requiere go explícito de Irving) |
| #9990559 | aprobado_irving | 3 | `circuito/item-9990559-mr-23-fase-4d-a-tabla-mapa-red-histori` | MR-23 fase 4d-A — tabla mapa_red_historial + modelo + helper de registro + permiso |
| #9990555 | aprobado_irving | 3 | `circuito/item-9990555-mr-23-fase-4b-i-impactoanalysisservice` | MR-23 Fase 4b-i — ImpactoAnalysisService: recorrido recursivo aguas abajo (backend + endpoint + permiso) |
| #9990539 | aprobado_irving | 2 | `circuito/item-9990539-mr-22-fase-2c-1-backend-drops-tabla-n` | MR-22 Fase 2c-1 — Backend Drops: tabla network_drops (punto lat/lng) + modelo + CRUD |
| #9990536 | aprobado_irving | 1 | `circuito/item-9990536-mr-08-fase-2a-catalogoscontroller-per` | MR-08 Fase 2a — CatalogosController: permisos + cables/conectores (index/store/update/destroy) |
| #9990455 | aprobado_irving | 3 | `circuito/item-9990455-mr-23-fase-4c-seccion-fotos-por-nodo` | MR-23 fase 4c — sección 'Fotos' por nodo/enlace del Mapa de Red |
| #9990359 | aprobado_irving | 10 | `circuito/item-9990359-hijo-e2-regenerar-y-subir-escaneado-fi` | Hijo E2 — Regenerar y Subir escaneado firmado con congelamiento de versión |
| #9990411 | aprobado_irving | 1 | `circuito/item-9990411-fase-12-detectar-causalimite-cuenta-e` | FASE 1+2: detectar causa=limite_cuenta en vuelta.sh y no castigar el item |
| #629 | aprobado_irving | 1 | `circuito/item-629-ramas-huerfanas-pieza-c-descomposicion` | Ramas huerfanas Pieza C (descomposicion+watchdog+DependenciaGate, jul-11) — evaluar rescate del gap real |
| #652 | aprobado_irving | 4 | `circuito/item-652-preguntas-en-llano` | Bandeja de Irving en lenguaje natural: resumen legible arriba, lo técnico en un desplegable |
| #652 | aprobado_irving | 2 | `circuito/item-652-bandeja-lenguaje-natural` | Bandeja de Irving en lenguaje natural: resumen legible arriba, lo técnico en un desplegable |
| #283 | aprobado_irving | 1 | `circuito/item-283-decision-de-negocio-priorizar-driver-z` | Decisión de negocio: ¿priorizar driver ZTE y/o V-SOL? confirmar acceso a hardware piloto |
| #230 | aprobado_irving | 2 | `circuito/item-230-megaisp-test-no-se-puede-construir-solo` | megaisp_test no se puede construir sólo con migraciones: queda en 236 tablas de 502 |
| #155 | aprobado_irving | 1 | `circuito/item-155-client-main-informationuser-normalizar` | client_main_information.user: normalizar formato de número de cliente (padding 004981 vs 4981) |
| #842 | aprobado_irving | 1 | `circuito/item-842-item-canal-de-respuesta-de-cc-hacia` | Item 2 — Catálogo completo de permisos por módulo (nivel B) |
| #190 | aprobado_irving | 1 | `circuito/item-190-pendiente-negocio-definir-asesor-cobra` | Más items |
| #19 | aprobado_irving | 1 | `circuito/item-19-cerrar-modulo-megafamilia-contra-checkli` | Cerrar modulo MegaFamilia contra checklist (bloqueado por infra Padre-Hijo y motor de servicios) |
| #117 | aprobado_irving | 3 | `circuito/item-117-integracion-pac-para-cfdi-40-factura-f` | Integración PAC para CFDI 4.0 (factura fiscal) |
| #185 | aprobado_irving | 2 | `circuito/item-185-arquitectura-voicegateway-unico-via-am` | Más 8tems |
| #155 | aprobado_irving | 3 | `circuito/item-155-client-main-informationuser-normalizar` | client_main_information.user: normalizar formato de número de cliente (padding 004981 vs 4981) |
| #170 | aprobado_revisor | 1 | `circuito/item-170-vendedores-renderizar-saldo-null-como` | Circuito: freno de mano fuera de la BD (centinela en archivo + isPaused fail-closed) |
| #159 | aprobado_irving | 1 | `circuito/item-159-blocked-negocio-deuda-dos-tablas-de-f` | Deuda: dos tablas de facturas (invoices vs client_invoices) |

---

## Cubeta (d) — huérfana, candidata a archivar (49 filas)

**NO se borra nada aquí** — solo se señala. Mezcla dos motivos distintos:

- **40 ramas sin ningún item de roadmap que las respalde** (bitácoras de rescate `circuito/bitacora-rescate-*`
  del propio circuito, ramas de trabajo pre-circuito de otros desarrolladores —`romelio_suarez`,
  `carlos.rodriguez`, `Carlos`— y ramas exploratorias sueltas de Irving sin item asociado). Incluye
  6 casos donde el nombre de rama parsea un id numérico (`item-1044`, `item-1020`, `item-1015`,
  `item-265`, `item-251`, `item-244`) que **ya no existe** en `roadmap_items` — no son items
  activos rebautizados, son ids que se borraron o nunca se migraron a la tabla actual.
- **9 ramas de items con `estado_aprobacion=cancelado`** — su trabajo se descartó a propósito
  (decisión de Irving u otro cierre editorial), la rama es residuo esperado del descarte.

| item | estado | motivo | rama | título |
|---|---|---|---|---|
| — | (sin item) | sin item_id resoluble | `circuito/bitacora-rescate-20260911-144206-wt-1` | — |
| — | (sin item) | sin item_id resoluble | `circuito/bitacora-rescate-20260910-182904-wt-1` | — |
| — | (sin item) | sin item_id resoluble | `voip/asterisk-22-esquema-limpio` | — |
| — | (sin item) | sin item_id resoluble | `circuito/bitacora-rescate-20260909-102903-wt-1` | — |
| — | (sin item) | sin item_id resoluble | `circuito/bitacora-rescate-20260909-102339-wt-4` | — |
| — | (sin item) | sin item_id resoluble | `circuito/bitacora-rescate-20260909-082610-wt-1` | — |
| — | (sin item) | sin item_id resoluble | `fase-ab-versionador-changelog` | — |
| — | (sin item) | sin item_id resoluble | `fase-a-versionador-changelog` | — |
| — | (sin item) | sin item_id resoluble | `circuito/bitacora-rescate-20260908-111405-wt-2` | — |
| #9990500 | cancelado | item cancelado | `circuito/item-9990500-header-los-badges-de-contador-salen-com` | Header: los badges de contador salen como LÍNEA en modo oscuro (regla dark_mode.scss con !important) + posición y alineación del engrane |
| — | (sin item) | sin item_id resoluble | `circuito/bitacora-rescate-20260907-170405-wt-2` | — |
| — | (sin item) | sin item_id resoluble | `circuito/bitacora-rescate-20260907-164904-wt-3` | — |
| — | (sin item) | sin item_id resoluble | `circuito/bitacora-rescate-20260907-095904-wt-2` | — |
| — | (sin item) | sin item_id resoluble | `circuito/bitacora-rescate-20260906-181154-wt-4` | — |
| — | (sin item) | sin item_id resoluble | `circuito/bitacora-rescate-20260906-060204-wt-1` | — |
| — | (sin item) | sin item_id resoluble | `trabajo/mr-33-34-35-calidad-de-red` | — |
| #9990085 | cancelado | item cancelado | `circuito/item-9990085-frontend-vista-admin-crm-documentos-hu` | Frontend: vista admin CRM 'Documentos huérfanos' (tabla Quasar + export CSV) — decisión oficial de Irving q3 |
| — | (sin item) | sin item_id resoluble | `fix/jarvis-chat-solo-navegacion` | — |
| #720 | cancelado | item cancelado | `circuito/item-720-flotas-saas-linea-de-facturacion-en-cal` | Flotas SaaS: línea de facturación en calculateAmounts() (patrón PULL, precedente Contratables) |
| #667 | cancelado | item cancelado | `circuito/item-667-documentacioncorporativa-fase-5-entre` | DocumentaciónCorporativa — Fase 5: entrega-recepción, bitácora y offboarding |
| #179 | cancelado | item cancelado | `circuito/item-179-contrasenas-legacy-en-base64-9-de-11-cu` | Contrasenas legacy en base64: 9 de 11 cuentas privilegiadas guardan la contrasena de forma recuperable |
| — | (sin item) | sin item_id resoluble | `torre-diff-dividido-por-defecto` | — |
| — | (sin item) | sin item_id resoluble | `rescate/bitacora-item-191` | — |
| — | (sin item) | sin item_id resoluble | `piezaC-gate-dependencia` | — |
| — | (sin item) | sin item_id resoluble | `piezaC-descomposicion` | — |
| #1044 | (sin item) | id parseado del nombre ya no existe en roadmap_items | `circuito/item-1044-ipv6-12b-migraciones-clientes-ipv6-p` | — |
| #1020 | (sin item) | id parseado del nombre ya no existe en roadmap_items | `circuito/item-1020-ventana-de-reversibilidad-medida-en-fila` | — |
| #1015 | (sin item) | id parseado del nombre ya no existe en roadmap_items | `circuito/item-1015-auditor-559-memoria-de-cobertura-por` | — |
| #879 | cancelado | item cancelado | `circuito/item-879-torre-de-control-fase-1-semaforo-de-m` | Torre 24/7 · Pieza 3 — detectores de errores reales en el Motor de Auditoría (hoy sólo ve gaps de completitud y ya se agotaron) |
| #876 | cancelado | item cancelado | `circuito/item-876-consola-fase-3-catalogo-de-acciones` | Torre 24/7 · Pieza 4 — auto-corregir seguridad EN CÓDIGO sin preguntar, dejando permisos/credenciales/dinero en la bandeja |
| — | (sin item) | sin item_id resoluble | `circuito/fase1-metadata-decisiones` | — |
| — | (sin item) | sin item_id resoluble | `circuito/fase-a-anti-bucle` | — |
| #265 | (sin item) | id parseado del nombre ya no existe en roadmap_items | `circuito/item-265-trescuatro-mecanismos-de-autorizacion-c` | — |
| — | (sin item) | sin item_id resoluble | `circuito/c2` | — |
| #251 | (sin item) | id parseado del nombre ya no existe en roadmap_items | `circuito/item-251-contrasenas-de-cliente-almacenadas-y-c` | — |
| #244 | (sin item) | id parseado del nombre ya no existe en roadmap_items | `circuito/item-244-inyeccion-de-comandos-por-el-campo-versi` | — |
| #206 | cancelado | item cancelado | `circuito/item-206-checkroutepermission-lee-solo-permisos-d` | Vigilante on-box: que los atascos se avisen solos en vez de buscarlos a mano |
| — | (sin item) | sin item_id resoluble | `fix/smart-import-autoincrement-gaps` (2 copias con hashes divergentes) | — |
| — | (sin item) | sin item_id resoluble | `feature/auto-deploy-on-release` | — |
| — | (sin item) | sin item_id resoluble | `chore/scrub-github-token-docs` | — |
| — | (sin item) | sin item_id resoluble | `fixes-migraciones` (2 copias con hashes divergentes) | — |
| — | (sin item) | sin item_id resoluble | `wip-main-local-changes-2026-05-26` | — |
| — | (sin item) | sin item_id resoluble | `ajustes-import-bd` (2 copias con hashes divergentes) | — |
| — | (sin item) | sin item_id resoluble | `migrations-from-meganet` (2 copias con hashes divergentes) | — |
| — | (sin item) | sin item_id resoluble | `feature/modular-arch` | — |

---

## Resumen para la Fase 5+6 (sub-item final del padre)

- **(a):** 81 normales sin acción + 1 anomalía documentada arriba (#9990210, `merge_commit`
  desactualizado en BD — bajo impacto, backfill opcional).
- **(b):** el "hallazgo principal" que el padre CIRC-08 anticipaba **no es tal, con la fecha de
  corte correcta**: las 22 items completados-sin-merge son deuda histórica de ANTES de que el
  check "rama sin `merge_commit`" existiera (2026-09-10 18:13), no huecos del guard vigente hoy.
  Recomendado para el reporte final: encabezar esta sección con la corrección de fecha (arriba)
  para no reportar un falso hallazgo de seguridad de proceso.
- **(c):** 38 items abiertos con rama viva — pasar la lista a quien retome cada uno, ojo con
  #9990899 (0 commits) y los 2 items con rama duplicada (#652, #155).
- **(d):** 49 ramas huérfanas — candidatas a archivar/borrar en una fase de limpieza aparte (fuera
  de alcance de CIRC-08 según su propio spec: "NO borrar aquí, solo señalar").

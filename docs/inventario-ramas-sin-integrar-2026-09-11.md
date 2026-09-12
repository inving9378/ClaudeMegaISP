# CIRC-08 — Ramas sin integrar a `main`: reporte final (Fase 5a)

Item #9990981 (Fase 5a de #9990891, CIRC-08). Fusiona en un solo documento el resultado de las
fases previas — Fase 1 censo (#9990917), Fase 2 archivos por rama (#9990920) y Fase 3+4 cuatro
cubetas (#9990921) — todas ya `completado` y mergeadas a `main`. Los 3 raw intermedios que
alimentan este reporte siguen en `docs/`:

- `docs/inventario-ramas-sin-integrar-censo-raw.md` (196 ramas, rama → item_id/estado)
- `docs/inventario-ramas-sin-integrar-archivos-raw.md` (8804 filas rama\|hash\|archivo\|tipo)
- `docs/inventario-ramas-sin-integrar-cubetas-raw.md` (clasificación a/b/c/d por rama)

Este documento cubre las **secciones 1, 2 y 4**. Las secciones 3, 5 y 6 quedan como placeholder
para la Fase 5b (sub-item siguiente del padre #9990891), que además es quien cierra al padre.

---

## SECCIÓN 1 — Tabla resumen (196 ramas)

Transcrita literal de la tabla "Totales (196 ramas)" de `inventario-ramas-sin-integrar-cubetas-raw.md`:

| cubeta | conteo (filas) | conteo (items únicos) |
|---|---|---|
| (a) completado + mergeado, normal (squash-merge, sin problema) | 81 | 81 |
| (a) completado + mergeado, **anomalía a investigar** | 1 | 1 |
| (b) completado SIN merge, bandera roja nominal (corte 2026-08-08) | 23 | 22 (1 rama es copia divergente del mismo item) |
| (b) completado SIN merge, **justificado** (`sin_merge_esperado`/`cierre_sin_codigo_motivo`) | 2 | 2 |
| (c) abierto, avance en curso | 40 | 38 (2 items con 2 intentos de rama) |
| (d) huérfana (sin item o item cancelado) | 49 | 49 |
| **TOTAL** | **196** | — |

**Nota de corrección de fecha del guard (afecta directamente el hallazgo de la fila "bandera
roja" de arriba):** el spec original de CIRC-08 asumía que el guard `verificarCierre` que
bloquea "cierre completado con rama pero sin `merge_commit`" existía desde 2026-08-08 (commit
`ce14a359`). Al rastrear el código real (Fase 3+4, #9990921), `ce14a359` solo **crea la clase**
`ThomasService` — no bloquea nada. El check que de verdad aplica a las 22 filas de la cubeta (b)
bandera roja (rama poblada, `merge_commit` vacío) es uno **distinto**, activado por el commit
`0f046eef` (item #9990738) el **2026-09-10 a las 18:13:12** — no el 2026-08-08. Las 22 fechas de
cierre de esas ramas van de 2026-08-29 a 2026-09-09, es decir **todas anteriores** a que ese
check existiera. Conclusión: con el corte real (2026-09-10 18:13), **0 de las 22 son un hueco
vigente del guard hoy** — son deuda histórica de cuando el check todavía no se escribía, no una
falla de proceso activa. Se deja la partición nominal (corte 2026-08-08, que da las 22 como
"posteriores al guard") documentada junto con la partición corregida (que las da como
"anteriores al check real") para que quien decida qué reportar como hallazgo de seguridad de
proceso tenga ambas lecturas.


---

## SECCIÓN 2 — Archivos que `main` no tiene, agrupados por rama

Cubetas **(a)** (82 ramas: 81 normales + 1 anomalía #9990210) se EXCLUYEN de esta sección a propósito: su contenido ya está en `main` (ver Sección 1) — no hay "archivo que main no tenga" que listar ahí, listarlas sería ruido. Esta sección cubre únicamente las cubetas **(b)**, **(c)** y **(d)** (114 ramas), donde sí hay código que main no tiene.

**Decisión propia (registrada, aditiva):** 7 de las 49 ramas de la cubeta (d) son importaciones/volcados pre-circuito de mayo-2024 (`fix/smart-import-autoincrement-gaps` 1834 archivos, `feature/auto-deploy-on-release` 1709, `fixes-migraciones` 1333, `wip-main-local-changes-2026-05-26` 1329, `ajustes-import-bd` 955, `migrations-from-meganet` 792, `feature/modular-arch` 570 — **8522 de los 8804 archivos del universo, 97%**). Listarlas completas (aun las 'prioritario') convertiría este reporte en un volcado de miles de líneas sin valor de auditoría: son candidatas a archivar (cubeta d), no código a revisar. Para ramas (d) con más de 100 archivos agregados se muestra solo el conteo agregado por tipo, sin enumerar. El resto de la cubeta (d), y TODA (b) y (c), sí listan completo (prioritario siempre, ruido colapsado solo si >5 por rama, como pide el spec).


### (b) completado sin merge — deuda histórica pre-guard — 23 rama(s)

#### `circuito/item-9990647-talento-completar-documento-formula-2` — #9990647 — completado
Talento: "Completar documento" — formulario de campos fillables (colaborador vs doc-específico) que guarda donde debe + regenera

**Prioritario (2):**
- `app/Modules/Addons/Talento/migrations/2026_09_09_150000_add_completar_documento_columns.php`
- `app/Modules/Addons/Talento/migrations/2026_09_09_150100_seed_reglamento_doc_fillable_fields.php`

#### `circuito/item-9990647-talento-completar-documento-formula` — #9990647 — completado
Talento: "Completar documento" — formulario de campos fillables (colaborador vs doc-específico) que guarda donde debe + regenera

**Prioritario (2):**
- `app/Modules/Addons/Talento/migrations/2026_09_09_150000_add_completar_documento_columns.php`
- `app/Modules/Addons/Talento/migrations/2026_09_09_150100_seed_reglamento_doc_fillable_fields.php`

#### `circuito/item-9990477-recuperacion-de-contrasena-agregar-el-l` — #9990477 — completado
Recuperación de contraseña: agregar el link 'Olvidé mi contraseña' en el login y verificar el ciclo completo


**Ruido (1):**
- `docs/roadmap-bucle-reap-item-9990477-verificacion.md`

#### `circuito/item-9990459-mr-22-fase-3-arbol-derivado-de-max-3-n` — #9990459 — completado
MR-22 Fase 3 — Árbol derivado de máx 3 niveles como panel lateral colapsable (D22)

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-9990439-mr-24e-modo-dibujo-en-el-mapa-alta-de` — #9990439 — completado
MR-24e — Modo dibujo en el mapa: alta de NAP en 3 pasos + cable con snap a extremos

**Prioritario (2):**
- `app/Modules/Addons/MapaRed/Controllers/NapAltaRapidaController.php`
- `resources/js/components/module/mapared/helper/elementos-request.js`

**Ruido (1):**
- `docs/roadmap-bucle-reap-item-9990439-verificacion.md`

#### `circuito/item-9990437-mr-24d-endpoint-de-alta-rapida-de-nap` — #9990437 — completado
MR-24d — Endpoint de alta rápida de NAP (tipo + splitter, sin nombre a mano)

**Prioritario (1):**
- `app/Modules/Addons/MapaRed/Controllers/NapAltaRapidaController.php`

#### `circuito/item-9990330-fase-2c-escribir-y-verificar-el-test-d` — #9990330 — completado
Fase 2c — Escribir y verificar el test de integración de la cadena depende_de sobre RoadmapItem::despachable()

**Prioritario (1):**
- `tests/Feature/Roadmap/DependenciaGateDespachoTest.php`

#### `circuito/item-9990328-mr-06a-backend-portar-los-6-controlle` — #9990328 — completado
MR-06a — Backend: portar los 6 controllers Geo (grupo vivo) a MapaRed apuntando a mapared_*


**Ruido (1):**
- `docs/roadmap-bucle-reap-item-9990328-verificacion.md`

#### `circuito/item-9990286-fase-3a-i-de-9990246-crear-la-clase-p` — #9990286 — completado
Fase 3a-i de #9990246 — Crear la clase pura Support/AblandamientoFrontera.php

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-9990076-fase-1-repro-secuencial-mismo-proceso` — #9990076 — completado
Fase 1 - Repro secuencial (mismo proceso) del cascade sobre #32 + instrumentacion temporal


**Ruido (2):**
- `docs/roadmap-cascade-repro-item-9990076-verificacion.md`
- `docs/roadmap-cascade-repro-secuencial-item-9990076-verificacion.md`

#### `circuito/item-833-fase-1a-ii-parte-3n-resolver-colision` — #833 — completado
Fase 1a-ii parte 3/N: resolver colisión failed_jobs (aislado de #831) y continuar el ciclo fix-drift por el siguiente lote

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-824-documentacioncorporativa-fase-5c2-ui` — #824 — completado
DocumentaciónCorporativa Fase 5c.2 — UI: armar entrega desde una solicitud

**Prioritario (2):**
- `advertencia: main...2d7bba96ed: múltiples bases de fusión, usando 49a6b2d6bdbc0737e2a4260fdf096957d1257550`
- `app/Modules/Addons/DocumentacionCorporativa/Controllers/EntregaController.php`

#### `circuito/item-825-jarvis-parte-3b-fase-1-migracion-mo` — #825 — completado
Jarvis Parte 3b — Fase 1: migración + modelos jarvis_conversaciones/jarvis_mensajes

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-806-jarvis-parte-3b-decidir-donde-vive-el` — #806 — completado
Jarvis Parte 3b — Decidir dónde vive 'el chat' de las sugerencias (brief de Irving) y cablearlo

**Prioritario (5):**
- `app/Modules/Addons/Roadmap/Controllers/JarvisChatController.php`
- `app/Modules/Addons/Roadmap/Models/JarvisConversacion.php`
- `app/Modules/Addons/Roadmap/Models/JarvisMensaje.php`
- `app/Modules/Addons/Roadmap/Services/JarvisChatService.php`
- `app/Modules/Addons/Roadmap/migrations/2026_08_29_220000_crea_jarvis_chat_tablas.php`

#### `circuito/item-798-deriva-216-fase-1b-exportar-el-esquema` — #798 — completado
Deriva #216 Fase 1b: exportar el esquema de megaisp_dryrun a storage/schema/reference.sql (comando schema:build-reference)

**Prioritario (1):**
- `app/Console/Commands/Schema/BuildReferenceCommand.php`

#### `circuito/item-759-documentacioncorporativa-fase-5b-servi` — #759 — completado
DocumentaciónCorporativa Fase 5b — servicio de armado de entrega (ZIP + acta PDF + hash SHA-256)

**Prioritario (5):**
- `app/Modules/Addons/DocumentacionCorporativa/Models/DcEntrega.php`
- `app/Modules/Addons/DocumentacionCorporativa/Models/DcEntregaItem.php`
- `app/Modules/Addons/DocumentacionCorporativa/Models/DcSolicitud.php`
- `app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_150000_create_dc_solicitudes_table.php`
- `app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_150100_create_dc_entregas_tables.php`

#### `circuito/item-758-documentacioncorporativa-fase-5a-dc-so` — #758 — completado
DocumentaciónCorporativa Fase 5a — dc_solicitudes: correr migraciones + CRUD

**Prioritario (9):**
- `app/Modules/Addons/DocumentacionCorporativa/Controllers/DcSolicitudController.php`
- `app/Modules/Addons/DocumentacionCorporativa/Models/DcEntrega.php`
- `app/Modules/Addons/DocumentacionCorporativa/Models/DcEntregaItem.php`
- `app/Modules/Addons/DocumentacionCorporativa/Models/DcSolicitud.php`
- `app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_150000_create_dc_solicitudes_table.php`
- `app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_150100_create_dc_entregas_tables.php`
- `app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_150200_add_revocacion_a_offboarding.php`
- `app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_170000_grant_solicitud_manage_permission.php`
- `resources/js/components/module/documentacion-corporativa/DcSolicitudes.vue`

#### `circuito/item-740-deriva-de-esquema-216-fase-3-consumi` — #740 — completado
Deriva de esquema #216 — Fase 3: consumidores + entregable final + cierre


**Ruido (1):**
- `docs/roadmap-bucle-reap-item-740-verificacion.md`

#### `circuito/item-734-documentacioncorporativa-fase-2a-repos` — #734 — completado
DocumentacionCorporativa Fase 2a — Repositorio documental: carga, versionado y descarga

**Prioritario (4):**
- `app/Modules/Addons/DocumentacionCorporativa/Controllers/DocumentoController.php`
- `app/Modules/Addons/DocumentacionCorporativa/Services/DocumentoService.php`
- `app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_160000_add_ruta_archivo_a_dc_documentos.php`
- `config/documentacion_corporativa.php`

#### `circuito/item-705-vigilante-on-box-208-invariantes-de` — #705 — completado
Vigilante on-box (#208) — invariantes de Cola y discrepancias Sistema/Supervisor


**Ruido (1):**
- `docs/vigilante-cola-sistema-item-705-verificacion.md`

#### `circuito/item-681-respuesta-documentacioncorporativa-fas` — #681 — completado
[RESPUESTA] DocumentaciónCorporativa Fase 0 — commits en main + 6 desviaciones a ratificar

**Prioritario (1):**
- `docs/documentacion-corporativa-fase0-ratificacion-item-681.md`

#### `circuito/item-672-pieza-1-instrumentar-la-valvula-conta` — #672 — completado
Pieza 1 — Instrumentar la válvula: contador de aperturas de frontera dura en la Torre

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-875-consola-fase-1-semaforo-de-motores-en` — #875 — completado
Torre 24/7 · Pieza 3 — detectores de errores reales en el Motor de Auditoría (hoy sólo ve gaps de completitud y ya se agotaron)

**Prioritario (2):**
- `app/Modules/Addons/Roadmap/Models/CircuitoMotorPulso.php`
- `app/Modules/Addons/Roadmap/migrations/2026_08_20_210000_create_circuito_motor_pulsos_table.php`


### (b) completado sin merge — JUSTIFICADO (duplicado ya entregado por otro item) — 2 rama(s)

#### `circuito/item-9990587-fase-1a-i-escribir-migracion-document` — #9990587 — completado
Fase 1a-i — Escribir migración document_templates.status+updated_by+backfill

**Prioritario (1):**
- `database/migrations/2026_09_07_190900_add_status_and_updated_by_to_document_templates_table.php`

#### `circuito/item-9990432-mr-20-backend-anexar-ocupacion-de-puer` — #9990432 — completado
MR-20 backend — anexar ocupación de puertos por NAP al endpoint del mapa

_(sin archivos agregados — todo lo que toca ya existe en main)_


### (c) abierto, avance en curso — 40 rama(s)

#### `circuito/item-9990899-circ-02b-paso-1-tabla-roadmap-item-res` — #9990899 — aprobado_irving
CIRC-02b PASO 1 — Tabla roadmap_item_respuestas (migración aditiva + modelo)

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-9990890-circ-07-bug-de-vista-la-pestana-hoja` — #9990890 — en_progreso
CIRC-07: Bug de vista — la pestaña Hoja de ruta muestra 0/0/0/0 con el filtro Todos habiendo 1,580 items

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-9990834-reapertura-de-acuses-implementar-y-pas` — #9990834 — aprobado_revisor
Reapertura de acuses — implementar y pasar ReaperturaAcusesTest.php (happy path + negativo + 2 bordes)

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-9990830-tablero-pendientes-fase-1-permiso-tale` — #9990830 — aprobado_irving
Tablero pendientes Fase 1 — permiso talento.documentos.ver-todos + endpoint admin GET /talento/api/documentos/pendientes

**Prioritario (1):**
- `app/Modules/Addons/Talento/migrations/2026_09_11_230000_create_talento_documentos_ver_todos_permission.php`

#### `circuito/item-9990825-voip-fase-3-emitir-version-publicar-g` — #9990825 — aprobado_irving
VoIP Fase 3 — emitir version, publicar GitHub Release y documentar el runbook de despliegue


**Ruido (1):**
- `docs/roadmap-bucle-reap-item-9990825-verificacion.md`

#### `circuito/item-9990816-tablero-admin-para-irving-que-colaborad` — #9990816 — aprobado_irving
Tablero admin para Irving: qué colaborador tiene qué documento pendiente (con antigüedad + recordar)

**Prioritario (2):**
- `app/Modules/Addons/Talento/migrations/2026_09_11_230000_add_ultimo_recordatorio_at_to_talento_employee_documents.php`
- `app/Modules/Addons/Talento/migrations/2026_09_11_230100_create_talento_documentos_ver_todos_permission.php`

#### `circuito/item-9990808-motor-de-ventas-catalogos-backend-migr` — #9990808 — aprobado_irving
Motor de ventas: catálogos backend (migraciones + modelos + permisos + CRUD API) — Fase 1 de #9990781

**Prioritario (9):**
- `app/Http/Controllers/Module/Ventas/CatalogosVentasController.php`
- `app/Models/Ventas/VentaModalidad.php`
- `app/Models/Ventas/VentaModoPago.php`
- `app/Models/Ventas/VentaParametroReglamento.php`
- `database/migrations/2026_09_11_210000_create_ventas_modalidades_table.php`
- `database/migrations/2026_09_11_210100_create_ventas_modos_pago_table.php`
- `database/migrations/2026_09_11_210200_create_ventas_parametros_reglamento_table.php`
- `database/migrations/2026_09_11_210300_seed_ventas_modos_pago_from_method_of_payments.php`
- `database/migrations/2026_09_11_210400_create_ventas_catalogos_permissions.php`

#### `circuito/item-9990767-fase-4c-i-mergear-rama-backend-existen` — #9990767 — aprobado_irving
Fase 4c-i — mergear rama backend existente + migrar + vista Blade+Vue de la matriz de permisos por rol


**Ruido (1):**
- `docs/auditoria-permisos-matriz-item-9990767-verificacion.md`

#### `circuito/item-9990766-fase-4c-cerrar-vista-bladevue-de-la` — #9990766 — aprobado_irving
Fase 4c — cerrar: vista Blade+Vue de la matriz de permisos por rol + correr real + poblar reporte

**Prioritario (7):**
- `app/Modules/Core/Auditoria/Console/AuditoriaPermisosMatrizRolesCommand.php`
- `app/Modules/Core/Auditoria/Controllers/AuditoriaPermisosMatrizController.php`
- `app/Modules/Core/Auditoria/Services/PermisosMatrizRolesService.php`
- `app/Modules/Core/Auditoria/views/auditoria_permisos_matriz/index.blade.php`
- `database/migrations/2026_09_11_150000_create_auditoria_permisos_matriz_ver_permission.php`
- `docs/auditoria/permisos-matriz-roles.csv`
- `resources/js/components/module/auditoria/AuditoriaPermisosMatriz.vue`

#### `circuito/item-9990765-fase-4c-matriz-por-rol-permisos-asign` — #9990765 — aprobado_irving
Fase 4c — Matriz por rol: permisos asignados vs. permisos que surten efecto (punto 4)

**Prioritario (3):**
- `app/Modules/Core/Auditoria/Console/AuditoriaPermisosMatrizRolesCommand.php`
- `app/Modules/Core/Auditoria/Services/PermisosMatrizRolesService.php`
- `database/migrations/2026_09_11_150000_create_auditoria_permisos_matriz_ver_permission.php`

#### `circuito/item-9990743-fase-01-diagnostico-confirmado-trans` — #9990743 — aprobado_irving
Fase 0+1 — diagnóstico confirmado (transacción+rollback): diff SUPERVISOR_MOSTRADOR vs Diana


**Ruido (1):**
- `docs/permisos-diana-supervisor-mostrador-item-9990743-verificacion.md`

#### `circuito/item-9990729-f3-9990674-ejecutar-a-mano-el-ensayo` — #9990729 — aprobado_irving
F3 (#9990674): ejecutar a mano el ensayo real de red cortada (push+GitHub Release TEST-)


**Ruido (1):**
- `docs/releases-atomico-ensayo-item-9990729-verificacion.md`

#### `circuito/item-9990725-fase-4-extensiones-sembradas-por-depar` — #9990725 — aprobado_irving
Fase 4 — Extensiones sembradas por departamento (sección 8)

**Prioritario (1):**
- `app/Modules/Addons/VoIP/Seeders/ExtensionesArranqueSeeder.php`

#### `circuito/item-9990723-fase-2-correcciones-minimas-del-modulo` — #9990723 — aprobado_irving
Fase 2 — Correcciones mínimas del módulo VoIP (sección 6)


**Ruido (1):**
- `docs/voip-fase2-correcciones-minimas-item-9990723-verificacion.md`

#### `circuito/item-9990674-f3-releasepublisher-emision-atomica-c` — #9990674 — aprobado_irving
F3 — ReleasePublisher: emisión atómica con compensación

**Prioritario (4):**
- `app/Console/Commands/Active/ReleasePublishAtomicCommand.php`
- `app/Services/Deploy/ReleasePublisherService.php`
- `config/releases.php`
- `database/migrations/2026_09_10_180000_add_publicacion_atomica_to_releases_table.php`

#### `circuito/item-9990519-mr-23-fase-4a-22-frontend-accion` — #9990519 — aprobado_irving
MR-23 fase 4a (2/2) — Frontend: acción 'Trazar' en LeafletMapRed.vue (click secuencial + render inmediato)

**Prioritario (1):**
- `resources/js/components/module/mapared/helper/enlace-alta-request.js`

#### `circuito/item-9990631-seguimiento-pregunta-sin-resolver-de-2` — #9990631 — aprobado_irving
Seguimiento: pregunta sin resolver de #285


**Ruido (1):**
- `docs/payments-seguimiento-285-item-9990631-verificacion.md`

#### `circuito/item-9990630-seguimiento-pregunta-sin-resolver-de-2` — #9990630 — aprobado_irving
Seguimiento: pregunta sin resolver de #274

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-9990607-fase3-parqueo-shadow-mode` — #9990607 — aprobado_irving
Fase 3 — Conmutación del origen del pago a TalentoLedgerEntry (FRONTERA DURA DE DINERO, requiere go explícito de Irving)


**Ruido (1):**
- `docs/talento-fase3-cutover-item-9990607-verificacion.md`

#### `circuito/item-9990559-mr-23-fase-4d-a-tabla-mapa-red-histori` — #9990559 — aprobado_irving
MR-23 fase 4d-A — tabla mapa_red_historial + modelo + helper de registro + permiso

**Prioritario (2):**
- `app/Modules/Addons/MapaRed/Models/MapaRedHistorial.php`
- `app/Modules/Addons/MapaRed/migrations/2026_09_08_010000_create_mapa_red_historial_table.php`

#### `circuito/item-9990555-mr-23-fase-4b-i-impactoanalysisservice` — #9990555 — aprobado_irving
MR-23 Fase 4b-i — ImpactoAnalysisService: recorrido recursivo aguas abajo (backend + endpoint + permiso)

**Prioritario (3):**
- `app/Modules/Addons/MapaRed/Controllers/ImpactoController.php`
- `app/Modules/Addons/MapaRed/Services/ImpactoAnalysisService.php`
- `app/Modules/Addons/MapaRed/migrations/2026_09_08_000000_create_mapared_ver_impacto_permission.php`

#### `circuito/item-9990539-mr-22-fase-2c-1-backend-drops-tabla-n` — #9990539 — aprobado_irving
MR-22 Fase 2c-1 — Backend Drops: tabla network_drops (punto lat/lng) + modelo + CRUD

**Prioritario (3):**
- `app/Modules/Addons/MapaRed/Controllers/NetworkDropsController.php`
- `app/Modules/Addons/MapaRed/Models/NetworkDrop.php`
- `app/Modules/Addons/MapaRed/migrations/2026_09_08_000000_create_network_drops_table.php`

#### `circuito/item-9990536-mr-08-fase-2a-catalogoscontroller-per` — #9990536 — aprobado_irving
MR-08 Fase 2a — CatalogosController: permisos + cables/conectores (index/store/update/destroy)

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-9990455-mr-23-fase-4c-seccion-fotos-por-nodo` — #9990455 — aprobado_irving
MR-23 fase 4c — sección 'Fotos' por nodo/enlace del Mapa de Red

**Prioritario (5):**
- `app/Modules/Addons/MapaRed/Controllers/FotosController.php`
- `app/Modules/Addons/MapaRed/Models/MapaRedFoto.php`
- `app/Modules/Addons/MapaRed/migrations/2026_09_07_150000_create_mapared_fotos_table.php`
- `resources/js/components/module/mapared/components/others/FotosPanel.vue`
- `resources/js/components/module/mapared/helper/fotos-request.js`

#### `circuito/item-9990359-hijo-e2-regenerar-y-subir-escaneado-fi` — #9990359 — aprobado_irving
Hijo E2 — Regenerar y Subir escaneado firmado con congelamiento de versión

**Prioritario (1):**
- `app/Modules/Addons/Talento/migrations/2026_09_07_150000_add_signing_columns_to_talento_employee_documents.php`

#### `circuito/item-9990411-fase-12-detectar-causalimite-cuenta-e` — #9990411 — aprobado_irving
FASE 1+2: detectar causa=limite_cuenta en vuelta.sh y no castigar el item

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-629-ramas-huerfanas-pieza-c-descomposicion` — #629 — aprobado_irving
Ramas huerfanas Pieza C (descomposicion+watchdog+DependenciaGate, jul-11) — evaluar rescate del gap real

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-652-preguntas-en-llano` — #652 — aprobado_irving
Bandeja de Irving en lenguaje natural: resumen legible arriba, lo técnico en un desplegable

**Prioritario (5):**
- `app/Modules/Addons/Roadmap/Console/ResumenNaturalCommand.php`
- `app/Modules/Addons/Roadmap/Services/ResumenNaturalService.php`
- `app/Modules/Addons/Roadmap/migrations/2026_08_28_100000_add_resumen_natural_to_roadmap_items.php`
- `app/Modules/Addons/Roadmap/migrations/2026_08_28_110000_add_preguntas_natural_to_roadmap_items.php`
- `tests/Unit/Modules/Addons/Roadmap/PreguntasNaturalNoCambiaClavesTest.php`

#### `circuito/item-652-bandeja-lenguaje-natural` — #652 — aprobado_irving
Bandeja de Irving en lenguaje natural: resumen legible arriba, lo técnico en un desplegable

**Prioritario (3):**
- `app/Modules/Addons/Roadmap/Console/ResumenNaturalCommand.php`
- `app/Modules/Addons/Roadmap/Services/ResumenNaturalService.php`
- `app/Modules/Addons/Roadmap/migrations/2026_08_28_100000_add_resumen_natural_to_roadmap_items.php`

#### `circuito/item-283-decision-de-negocio-priorizar-driver-z` — #283 — aprobado_irving
Decisión de negocio: ¿priorizar driver ZTE y/o V-SOL? confirmar acceso a hardware piloto

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-230-megaisp-test-no-se-puede-construir-solo` — #230 — aprobado_irving
megaisp_test no se puede construir sólo con migraciones: queda en 236 tablas de 502

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-155-client-main-informationuser-normalizar` — #155 — aprobado_irving
client_main_information.user: normalizar formato de número de cliente (padding 004981 vs 4981)

**Prioritario (1):**
- `docs/client-user-padding-item-155-auditoria-consumidores.md`

#### `circuito/item-842-item-canal-de-respuesta-de-cc-hacia` — #842 — aprobado_irving
Item 2 — Catálogo completo de permisos por módulo (nivel B)

**Prioritario (1):**
- `app/Modules/Addons/Roadmap/migrations/2026_08_20_161800_add_tipo_to_roadmap_items.php`

#### `circuito/item-190-pendiente-negocio-definir-asesor-cobra` — #190 — aprobado_irving
Más items

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-19-cerrar-modulo-megafamilia-contra-checkli` — #19 — aprobado_irving
Cerrar modulo MegaFamilia contra checklist (bloqueado por infra Padre-Hijo y motor de servicios)

**Prioritario (1):**
- `docs/megafamilia-checklist-clasificado-2026-07-15.md`

#### `circuito/item-117-integracion-pac-para-cfdi-40-factura-f` — #117 — aprobado_irving
Integración PAC para CFDI 4.0 (factura fiscal)

**Prioritario (4):**
- `app/Models/ClientCfdiInvoice.php`
- `database/migrations/2026_07_14_190000_create_client_cfdi_invoices_table.php`
- `database/migrations/2026_07_15_120000_drop_unique_payment_id_from_client_cfdi_invoices.php`
- `docs/pac-cfdi-auditoria-fase0.md`

#### `circuito/item-185-arquitectura-voicegateway-unico-via-am` — #185 — aprobado_irving
Más 8tems

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-155-client-main-informationuser-normalizar` — #155 — aprobado_irving
client_main_information.user: normalizar formato de número de cliente (padding 004981 vs 4981)

**Prioritario (2):**
- `app/Console/Commands/Active/NormalizarUsuarioWebCommand.php`
- `app/Support/ClienteNumeroFormatter.php`

#### `circuito/item-170-vendedores-renderizar-saldo-null-como` — #170 — aprobado_revisor
Circuito: freno de mano fuera de la BD (centinela en archivo + isPaused fail-closed)

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-159-blocked-negocio-deuda-dos-tablas-de-f` — #159 — aprobado_irving
Deuda: dos tablas de facturas (invoices vs client_invoices)

**Prioritario (1):**
- `docs/deuda-invoices-vs-client-invoices.md`


### (d) huérfana / cancelada — candidata a archivar — 49 rama(s)

#### `circuito/bitacora-rescate-20260911-144206-wt-1` — (sin item) — (sin item)
—

**Prioritario (1):**
- `app/Modules/Addons/Talento/migrations/2026_09_11_220000_add_parcial_status_and_scan_requirement.php`

#### `circuito/bitacora-rescate-20260910-182904-wt-1` — (sin item) — (sin item)
—


**Ruido (1):**
- `docs/roadmap-bucle-reap-item-9990739-verificacion.md`

#### `voip/asterisk-22-esquema-limpio` — (sin item) — (sin item)
—

**Prioritario (2):**
- `deploy/provision/lib/50-asterisk.sh`
- `deploy/provision/provision-asterisk.sh`

#### `circuito/bitacora-rescate-20260909-102903-wt-1` — (sin item) — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/bitacora-rescate-20260909-102339-wt-4` — (sin item) — (sin item)
—

**Prioritario (1):**
- `app/Modules/Addons/Talento/Support/SignatureSlotStatus.php`

#### `circuito/bitacora-rescate-20260909-082610-wt-1` — (sin item) — (sin item)
—

**Prioritario (5):**
- `app/Modules/Addons/Talento/Models/TalentoDocumentTemplateField.php`
- `app/Modules/Addons/Talento/migrations/2026_09_09_150000_create_talento_document_template_fields_table.php`
- `app/Modules/Addons/Talento/migrations/2026_09_09_150100_add_datos_extra_to_talento_employee_documents.php`
- `app/Modules/Addons/Talento/migrations/2026_09_09_150200_seed_talento_document_template_fields_reglamento.php`
- `app/Modules/Addons/Talento/migrations/2026_09_09_150300_new_version_talento_document_template_reglamento_campos.php`

#### `fase-ab-versionador-changelog` — (sin item) — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `fase-a-versionador-changelog` — (sin item) — (sin item)
—

**Prioritario (9):**
- `advertencia: main...6c06411589: múltiples bases de fusión, usando e19ea358dcf44a3d7b337dc75f63392f6e4d961b`
- `app/Modules/Addons/MapaRed/Controllers/EnlacesController.php`
- `app/Modules/Addons/MapaRed/Controllers/FotosController.php`
- `app/Modules/Addons/MapaRed/Models/MapaRedEnlace.php`
- `app/Modules/Addons/MapaRed/Models/MapaRedFoto.php`
- `app/Modules/Addons/MapaRed/Models/MapaRedHistorial.php`
- `app/Modules/Addons/MapaRed/migrations/2026_09_07_150000_create_mapared_fotos_table.php`
- `app/Modules/Addons/MapaRed/migrations/2026_09_07_210000_create_mapared_enlaces_table.php`
- `app/Modules/Addons/MapaRed/migrations/2026_09_07_224500_create_mapared_historial_table.php`

#### `circuito/bitacora-rescate-20260908-111405-wt-2` — (sin item) — (sin item)
—


**Ruido (1):**
- `docs/talento-comisiones-vendedor-fase1-sombra-item-9990614-verificacion.md`

#### `circuito/item-9990500-header-los-badges-de-contador-salen-com` — #9990500 — cancelado
Header: los badges de contador salen como LÍNEA en modo oscuro (regla dark_mode.scss con !important) + posición y alineación del engrane

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/bitacora-rescate-20260907-170405-wt-2` — (sin item) — (sin item)
—

**Prioritario (4):**
- `app/Modules/Addons/MapaRed/Controllers/SectoresController.php`
- `app/Modules/Addons/MapaRed/Models/MapaRedSectorInalambrico.php`
- `app/Modules/Addons/MapaRed/Services/SectorInalambricoImportadorService.php`
- `app/Modules/Addons/MapaRed/migrations/2026_09_07_230000_create_mapared_sectores_inalambricos_table.php`

#### `circuito/bitacora-rescate-20260907-164904-wt-3` — (sin item) — (sin item)
—

**Prioritario (4):**
- `app/Modules/Addons/MapaRed/Controllers/SectoresController.php`
- `app/Modules/Addons/MapaRed/Models/MapaRedSectorInalambrico.php`
- `app/Modules/Addons/MapaRed/Services/SectorInalambricoImportadorService.php`
- `app/Modules/Addons/MapaRed/migrations/2026_09_07_230000_create_mapared_sectores_inalambricos_table.php`

#### `circuito/bitacora-rescate-20260907-095904-wt-2` — (sin item) — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/bitacora-rescate-20260906-181154-wt-4` — (sin item) — (sin item)
—

**Prioritario (1):**
- `resources/js/composables/useNapSaludDialog.js`

#### `circuito/bitacora-rescate-20260906-060204-wt-1` — (sin item) — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `trabajo/mr-33-34-35-calidad-de-red` — (sin item) — (sin item)
—

**Prioritario (1):**
- `docs/circuito/mapa-red-mr33-mr35-calidad-y-bonos.md`

#### `circuito/item-9990085-frontend-vista-admin-crm-documentos-hu` — #9990085 — cancelado
Frontend: vista admin CRM 'Documentos huérfanos' (tabla Quasar + export CSV) — decisión oficial de Irving q3

**Prioritario (3):**
- `app/Modules/Core/CRM/Controllers/CrmOrphanDocumentController.php`
- `app/Modules/Core/CRM/views/orphan-documents.blade.php`
- `resources/js/components/module/crm/CrmOrphanDocuments.vue`

#### `fix/jarvis-chat-solo-navegacion` — (sin item) — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-720-flotas-saas-linea-de-facturacion-en-cal` — #720 — cancelado
Flotas SaaS: línea de facturación en calculateAmounts() (patrón PULL, precedente Contratables)

**Prioritario (1):**
- `config/flotas_billing.php`

#### `circuito/item-667-documentacioncorporativa-fase-5-entre` — #667 — cancelado
DocumentaciónCorporativa — Fase 5: entrega-recepción, bitácora y offboarding

**Prioritario (6):**
- `app/Modules/Addons/DocumentacionCorporativa/Models/DcEntrega.php`
- `app/Modules/Addons/DocumentacionCorporativa/Models/DcEntregaItem.php`
- `app/Modules/Addons/DocumentacionCorporativa/Models/DcSolicitud.php`
- `app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_150000_create_dc_solicitudes_table.php`
- `app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_150100_create_dc_entregas_tables.php`
- `app/Modules/Addons/DocumentacionCorporativa/migrations/2026_08_29_150200_add_revocacion_a_offboarding.php`

#### `circuito/item-179-contrasenas-legacy-en-base64-9-de-11-cu` — #179 — cancelado
Contrasenas legacy en base64: 9 de 11 cuentas privilegiadas guardan la contrasena de forma recuperable

**Prioritario (1):**
- `app/Console/Commands/Active/AuditLegacyPasswordsCommand.php`

#### `torre-diff-dividido-por-defecto` — (sin item) — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `rescate/bitacora-item-191` — (sin item) — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `piezaC-gate-dependencia` — (sin item) — (sin item)
—

**Prioritario (10):**
- `app/Modules/Addons/Roadmap/Console/PlanDescomposicionCommand.php`
- `app/Modules/Addons/Roadmap/Console/WatchdogSupervisorCommand.php`
- `app/Modules/Addons/Roadmap/Services/Descomposicion/DependenciaGate.php`
- `app/Modules/Addons/Roadmap/Services/Descomposicion/DescomposicionPlanner.php`
- `app/Modules/Addons/Roadmap/Services/Descomposicion/EstrategiaInterface.php`
- `app/Modules/Addons/Roadmap/Services/Descomposicion/Estrategias/EstrategiaFases.php`
- `app/Modules/Addons/Roadmap/Services/Descomposicion/PlanDescomposicion.php`
- `app/Modules/Addons/Roadmap/Services/Descomposicion/SeccionPlan.php`
- `app/Modules/Addons/Roadmap/Services/Watchdog/SupervisorWatchdog.php`
- `tests/Unit/Roadmap/DependenciaGateTest.php`

#### `piezaC-descomposicion` — (sin item) — (sin item)
—

**Prioritario (8):**
- `app/Modules/Addons/Roadmap/Console/PlanDescomposicionCommand.php`
- `app/Modules/Addons/Roadmap/Console/WatchdogSupervisorCommand.php`
- `app/Modules/Addons/Roadmap/Services/Descomposicion/DescomposicionPlanner.php`
- `app/Modules/Addons/Roadmap/Services/Descomposicion/EstrategiaInterface.php`
- `app/Modules/Addons/Roadmap/Services/Descomposicion/Estrategias/EstrategiaFases.php`
- `app/Modules/Addons/Roadmap/Services/Descomposicion/PlanDescomposicion.php`
- `app/Modules/Addons/Roadmap/Services/Descomposicion/SeccionPlan.php`
- `app/Modules/Addons/Roadmap/Services/Watchdog/SupervisorWatchdog.php`

#### `circuito/item-1044-ipv6-12b-migraciones-clientes-ipv6-p` — #1044 — (sin item)
—

**Prioritario (4):**
- `database/migrations/2026_08_22_080000_create_clientes_ipv6_prefijos_table.php`
- `database/migrations/2026_08_22_080100_create_clientes_ipv6_config_table.php`
- `database/migrations/2026_08_22_080200_create_clientes_ipv6_excepciones_table.php`
- `database/migrations/2026_08_22_080300_create_ipv6_despliegues_table.php`

#### `circuito/item-1020-ventana-de-reversibilidad-medida-en-fila` — #1020 — (sin item)
—

**Prioritario (5):**
- `app/Models/ReleaseReversibilityThreshold.php`
- `app/Models/ReleaseSnapshot.php`
- `config/release_reversibility.php`
- `database/migrations/2026_08_22_020000_create_release_reversibility_thresholds_table.php`
- `database/migrations/2026_08_22_020100_create_release_snapshots_table.php`

#### `circuito/item-1015-auditor-559-memoria-de-cobertura-por` — #1015 — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-879-torre-de-control-fase-1-semaforo-de-m` — #879 — cancelado
Torre 24/7 · Pieza 3 — detectores de errores reales en el Motor de Auditoría (hoy sólo ve gaps de completitud y ya se agotaron)

**Prioritario (1):**
- `resources/js/components/module/releases/torre-control/TorreSemaforoMotores.vue`

#### `circuito/item-876-consola-fase-3-catalogo-de-acciones` — #876 — cancelado
Torre 24/7 · Pieza 4 — auto-corregir seguridad EN CÓDIGO sin preguntar, dejando permisos/credenciales/dinero en la bandeja

**Prioritario (6):**
- `app/Modules/Addons/Roadmap/Services/ConsolaAcciones/ConsolaAccion.php`
- `app/Modules/Addons/Roadmap/Services/ConsolaAcciones/ConsolaAccionCatalogo.php`
- `app/Modules/Addons/Roadmap/Services/ConsolaAcciones/ConsolaAccionExecutor.php`
- `app/Modules/Addons/Roadmap/Services/ConsolaAcciones/TorreAccionesCatalogo.php`
- `app/Modules/Addons/Roadmap/migrations/2026_08_20_220000_crea_consola_acciones_ejecuciones.php`
- `docs/circuito/acciones-de-consola.md`

#### `circuito/fase1-metadata-decisiones` — (sin item) — (sin item)
—

**Prioritario (2):**
- `app/Modules/Addons/Roadmap/migrations/2026_07_14_193000_add_decision_metadata_to_roadmap_items.php`
- `tests/Unit/Roadmap/DecisionMetadataHelpersTest.php`

#### `circuito/fase-a-anti-bucle` — (sin item) — (sin item)
—

**Prioritario (2):**
- `app/Modules/Addons/Roadmap/Console/FaseASelftestCommand.php`
- `app/Modules/Addons/Roadmap/migrations/2026_07_14_180000_add_fase_a_anti_bucle_to_roadmap_items.php`

#### `circuito/item-265-trescuatro-mecanismos-de-autorizacion-c` — #265 — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/c2` — (sin item) — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-251-contrasenas-de-cliente-almacenadas-y-c` — #251 — (sin item)
—

**Prioritario (2):**
- `app/Modules/Addons/PortalCliente/migrations/2026_07_12_090000_add_password_hash_to_client_main_information.php`
- `app/Services/Security/ClientPasswordService.php`

#### `circuito/item-244-inyeccion-de-comandos-por-el-campo-versi` — #244 — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `circuito/item-206-checkroutepermission-lee-solo-permisos-d` — #206 — cancelado
Vigilante on-box: que los atascos se avisen solos en vez de buscarlos a mano

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `fix/smart-import-autoincrement-gaps` — (sin item) — (sin item)
—


**Ruido (1):**
- `public/js/node_modules_vue3-apexcharts_dist_apexcharts_ssr_esm-fe46cd2d_js.js`

#### `fix/smart-import-autoincrement-gaps` — (sin item) — (sin item)
—

**1833 archivos agregados totales** (1828 prioritario / 5 ruido) — rama huérfana de importación/volcado histórico pre-circuito; no se listan por volumen (ver decisión arriba).

#### `feature/auto-deploy-on-release` — (sin item) — (sin item)
—

**1709 archivos agregados totales** (1705 prioritario / 4 ruido) — rama huérfana de importación/volcado histórico pre-circuito; no se listan por volumen (ver decisión arriba).

#### `chore/scrub-github-token-docs` — (sin item) — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `fixes-migraciones` — (sin item) — (sin item)
—

_(sin archivos agregados — todo lo que toca ya existe en main)_

#### `fixes-migraciones` — (sin item) — (sin item)
—

**1332 archivos agregados totales** (1328 prioritario / 4 ruido) — rama huérfana de importación/volcado histórico pre-circuito; no se listan por volumen (ver decisión arriba).

#### `wip-main-local-changes-2026-05-26` — (sin item) — (sin item)
—

**1329 archivos agregados totales** (1325 prioritario / 4 ruido) — rama huérfana de importación/volcado histórico pre-circuito; no se listan por volumen (ver decisión arriba).

#### `ajustes-import-bd` — (sin item) — (sin item)
—

**116 archivos agregados totales** (116 prioritario / 0 ruido) — rama huérfana de importación/volcado histórico pre-circuito; no se listan por volumen (ver decisión arriba).

#### `ajustes-import-bd` — (sin item) — (sin item)
—

**839 archivos agregados totales** (835 prioritario / 4 ruido) — rama huérfana de importación/volcado histórico pre-circuito; no se listan por volumen (ver decisión arriba).

#### `migrations-from-meganet` — (sin item) — (sin item)
—

**111 archivos agregados totales** (111 prioritario / 0 ruido) — rama huérfana de importación/volcado histórico pre-circuito; no se listan por volumen (ver decisión arriba).

#### `migrations-from-meganet` — (sin item) — (sin item)
—

**681 archivos agregados totales** (681 prioritario / 0 ruido) — rama huérfana de importación/volcado histórico pre-circuito; no se listan por volumen (ver decisión arriba).

#### `feature/modular-arch` — (sin item) — (sin item)
—

**570 archivos agregados totales** (570 prioritario / 0 ruido) — rama huérfana de importación/volcado histórico pre-circuito; no se listan por volumen (ver decisión arriba).


---

## SECCIÓN 3 — (pendiente Fase 5b, sub-item #<el-que-sigue>)

## SECCIÓN 4 — Caso `reglamento-ventas-comisiones.md` (ya investigado, sin re-investigar)

El archivo `app/Modules/Addons/Talento/docs/reglamento-ventas-comisiones.md` **no existe** en
ninguna rama local ni remota del repositorio (verificado sobre las 3098 refs locales+remotas con
`git log --all --diff-filter=A` y `git cat-file -e` contra ese path exacto en cada rama: cero
resultados en ambos casos). El item #9990775 ("Subir el reglamento de ventas y comisiones al
repo", `completado` 2026-09-11) ya investigó a fondo el porqué: el reglamento con reglas
numeradas R1-R30 y casos de prueba TC1-TC12 —citado por los items #9990782 (motor de
comisiones), #9990783 (motor de maduración), #9990784 (Convenio Individual de Comisiones) y
#9990787 (migrar Embajadores)— **solo existe como conocimiento verbal/externo de Irving**; nunca
se pegó en ningún sistema interno (repo, roadmap, bitácora). Siguiendo la regla explícita del
propio item ("no inventes reglas de negocio que no estén ahí"), #9990775 decidió correctamente
**no crear el archivo con contenido inventado** y en su lugar generó el item de respuesta
**#9990790** ("[RESPUESTA] Subir el reglamento… falta el texto fuente de Irving", sigue
`aprobado_irving`, sin resolver) pidiendo el documento fuente real. Los 4 items que dependen de
ese reglamento como fuente única de verdad (#9990782, #9990783, #9990784, #9990787) siguen
abiertos, **bloqueados** en espera de que Irving responda #9990790 — esta es la "ola
Ventas/Talento bloqueada" que menciona la descripción del padre #9990891.

## SECCIÓN 5 — (pendiente Fase 5b, sub-item #<el-que-sigue>)

## SECCIÓN 6 — (pendiente Fase 5b, sub-item #<el-que-sigue>)

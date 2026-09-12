# CIRC-08 Fase 1 — Censo de ramas sin integrar a main (raw)

Item #9990917. Archivo INTERMEDIO (no prosa) para que la Fase 4 (sub-item final de CIRC-08) lo funda en el reporte definitivo. Generado en solo-lectura desde el worktree `wt-5`, sin merge/rebase/push/checkout en el repo principal.

## Método

- Universo: `git branch --no-merged main` (locales) + `git branch -r --no-merged main` (remotas: `origin/*` y `local/*`, este último es un remote-tracking hacia el checkout principal usado por el propio circuito para ver ramas creadas en otros worktrees).
- Refs crudas (antes de deduplicar) = **363**.
- Ramas únicas tras deduplicar por hash de commit (`git rev-parse`) = **196**.
- Excluida de la tabla la propia rama de este item (`circuito/item-9990917-...`), que nace idéntica a `main` y es ruido auto-referencial del censo.
- Cuando el mismo nombre de rama aparece dos veces con distinto hash (p. ej. `ajustes-import-bd`, `fixes-migraciones`, `fix/smart-import-autoincrement-gaps`, `migrations-from-meganet`) es porque la copia local y la remota **divergieron** — son punteros distintos con historia distinta, no un duplicado a colapsar.
- `item_id`: primero se intenta resolver por la columna `roadmap_items.branch` (match exacto contra cualquiera de las variantes de nombre — bare / `origin/...` / `local/...`); si no hay match, se cae al patrón `circuito/item-(\d+)-` del nombre. `(sin item)` = no hay fila en `roadmap_items` que la referencie por ninguna de las dos vías (incluye ids parseados del nombre que ya no existen en la tabla, p. ej. `item-244`, `item-1015`, `item-1020`, `item-1044`, `item-251`, `item-265` — no son items de la Hoja de Ruta actual).
- `estado_item` / `status_item`: valores actuales de `roadmap_items.estado_aprobacion` / `.status` para el id resuelto, al momento de este censo (2026-09-12).

## Tabla (196 ramas únicas)

| rama | hash | fecha | autor | commits_adelante | archivos_tocados | item_id | estado_aprobacion | status | título del item |
|---|---|---|---|---|---|---|---|---|---|
| circuito/item-9990899-circ-02b-paso-1-tabla-roadmap-item-res | 984bd6c6ee | 2026-09-11 | Irving MegaISP | 0 | 0 | 9990899 | aprobado_irving | pending | CIRC-02b PASO 1 — Tabla roadmap_item_respuestas (migración aditiva + modelo) |
| circuito/item-9990890-circ-07-bug-de-vista-la-pestana-hoja | eeab17ab00 | 2026-09-11 | Irving MegaISP | 1 | 1 | 9990890 | en_progreso | pending | CIRC-07: Bug de vista — la pestaña Hoja de ruta muestra 0/0/0/0 con el filtro Todos habiendo 1,580 items |
| circuito/item-9990834-reapertura-de-acuses-implementar-y-pas | 1e403ba558 | 2026-09-11 | Irving MegaISP | 1 | 1 | 9990834 | aprobado_revisor | pending | Reapertura de acuses — implementar y pasar ReaperturaAcusesTest.php (happy path + negativo + 2 bordes) |
| circuito/item-9990830-tablero-pendientes-fase-1-permiso-tale | a8c4b7ba2c | 2026-09-11 | Irving MegaISP | 1 | 4 | 9990830 | aprobado_irving | pending | Tablero pendientes Fase 1 — permiso talento.documentos.ver-todos + endpoint admin GET /talento/api/documentos/pendientes |
| circuito/item-9990825-voip-fase-3-emitir-version-publicar-g | 4a781064db | 2026-09-11 | Irving MegaISP | 1 | 1 | 9990825 | aprobado_irving | pending | VoIP Fase 3 — emitir version, publicar GitHub Release y documentar el runbook de despliegue |
| circuito/item-9990819-fase-2-de-9990805-hoja-de-constancia-d | 821dfff3fd | 2026-09-11 | Irving MegaISP | 1 | 1 | 9990819 | completado | done | Fase 2 de #9990805: hoja de constancia de firma electrónica en el HTML/PDF del documento |
| circuito/item-9990816-tablero-admin-para-irving-que-colaborad | c09fee04c1 | 2026-09-11 | Irving MegaISP | 2 | 2 | 9990816 | aprobado_irving | pending | Tablero admin para Irving: qué colaborador tiene qué documento pendiente (con antigüedad + recordar) |
| circuito/item-9990813-endpoints-api-first-self-scoped-del-port | 0a3f2ebf8d | 2026-09-11 | Irving MegaISP | 2 | 5 | 9990813 | completado | done | Endpoints API-first self-scoped del Portal de Colaborador para documentos (pendientes/firmados/descarga/firmar) |
| circuito/item-9990808-motor-de-ventas-catalogos-backend-migr | bbafff999f | 2026-09-11 | Irving MegaISP | 3 | 11 | 9990808 | aprobado_irving | pending | Motor de ventas: catálogos backend (migraciones + modelos + permisos + CRUD API) — Fase 1 de #9990781 |
| circuito/item-9990767-fase-4c-i-mergear-rama-backend-existen | 39b5ac0949 | 2026-09-11 | Irving MegaISP | 1 | 1 | 9990767 | aprobado_irving | pending | Fase 4c-i — mergear rama backend existente + migrar + vista Blade+Vue de la matriz de permisos por rol |
| circuito/item-9990766-fase-4c-cerrar-vista-bladevue-de-la | c89222997c | 2026-09-11 | Irving MegaISP | 6 | 12 | 9990766 | aprobado_irving | pending | Fase 4c — cerrar: vista Blade+Vue de la matriz de permisos por rol + correr real + poblar reporte |
| circuito/item-9990765-fase-4c-matriz-por-rol-permisos-asign | 607c59830d | 2026-09-11 | Irving MegaISP | 3 | 4 | 9990765 | aprobado_irving | pending | Fase 4c — Matriz por rol: permisos asignados vs. permisos que surten efecto (punto 4) |
| circuito/bitacora-rescate-20260911-144206-wt-1 | a1741e3022 | 2026-09-11 | Irving MegaISP | 1 | 3 |  | (sin item) | (sin item) |  |
| circuito/item-9990743-fase-01-diagnostico-confirmado-trans | f24add0cfb | 2026-09-10 | Irving MegaISP | 1 | 3 | 9990743 | aprobado_irving | pending | Fase 0+1 — diagnóstico confirmado (transacción+rollback): diff SUPERVISOR_MOSTRADOR vs Diana |
| circuito/item-9990729-f3-9990674-ejecutar-a-mano-el-ensayo | c12844767d | 2026-09-10 | Irving MegaISP | 1 | 1 | 9990729 | aprobado_irving | pending | F3 (#9990674): ejecutar a mano el ensayo real de red cortada (push+GitHub Release TEST-) |
| circuito/item-9990725-fase-4-extensiones-sembradas-por-depar | b9c2365ae4 | 2026-09-10 | Irving MegaISP | 1 | 2 | 9990725 | aprobado_irving | pending | Fase 4 — Extensiones sembradas por departamento (sección 8) |
| circuito/item-9990723-fase-2-correcciones-minimas-del-modulo | b3e4438fc7 | 2026-09-10 | Irving MegaISP | 2 | 3 | 9990723 | aprobado_irving | pending | Fase 2 — Correcciones mínimas del módulo VoIP (sección 6) |
| circuito/item-9990674-f3-releasepublisher-emision-atomica-c | e67149344b | 2026-09-10 | Irving MegaISP | 2 | 6 | 9990674 | aprobado_irving | pending | F3 — ReleasePublisher: emisión atómica con compensación |
| circuito/bitacora-rescate-20260910-182904-wt-1 | 90469bedc8 | 2026-09-10 | Irving MegaISP | 1 | 3 |  | (sin item) | (sin item) |  |
| voip/asterisk-22-esquema-limpio | f1290a51a1 | 2026-09-09 | Irving MegaISP | 1 | 3 |  | (sin item) | (sin item) |  |
| circuito/item-9990649-talento-ui-endpoint-de-firma-por-slot | 2e87e51a7e | 2026-09-09 | Irving MegaISP | 1 | 1 | 9990649 | completado | done | Talento firmas: 1 botón "Firmar" por doc → modal con N recuadros (uno por firmante) |
| circuito/item-9990647-talento-completar-documento-formula-2 | b023d877a1 | 2026-09-09 | Irving MegaISP | 1 | 6 | 9990647 | completado | done | Talento: "Completar documento" — formulario de campos fillables (colaborador vs doc-específico) que guarda donde debe + regenera |
| circuito/item-9990647-talento-completar-documento-formula | 71c3f31761 | 2026-09-09 | Irving MegaISP | 1 | 6 | 9990647 | completado | done | Talento: "Completar documento" — formulario de campos fillables (colaborador vs doc-específico) que guarda donde debe + regenera |
| circuito/item-9990519-mr-23-fase-4a-22-frontend-accion | e32f3c7bf0 | 2026-09-09 | Irving MegaISP | 1 | 2 | 9990519 | aprobado_irving | pending | MR-23 fase 4a (2/2) — Frontend: acción 'Trazar' en LeafletMapRed.vue (click secuencial + render inmediato) |
| circuito/bitacora-rescate-20260909-102903-wt-1 | 77416f03d2 | 2026-09-09 | Irving MegaISP | 1 | 1 |  | (sin item) | (sin item) |  |
| circuito/bitacora-rescate-20260909-102339-wt-4 | dd5e1bf0c8 | 2026-09-09 | Irving MegaISP | 1 | 3 |  | (sin item) | (sin item) |  |
| circuito/bitacora-rescate-20260909-082610-wt-1 | 064f82120a | 2026-09-09 | Irving MegaISP | 1 | 8 |  | (sin item) | (sin item) |  |
| fase-ab-versionador-changelog | d1e64a0ca4 | 2026-09-08 | Irving MegaISP | 2 | 3 |  | (sin item) | (sin item) |  |
| fase-a-versionador-changelog | 6c06411589 | 2026-09-08 | Irving MegaISP | 3 | 17 |  | (sin item) | (sin item) |  |
| circuito/item-9990631-seguimiento-pregunta-sin-resolver-de-2 | 829da97d2b | 2026-09-08 | Irving MegaISP | 1 | 1 | 9990631 | aprobado_irving | pending | Seguimiento: pregunta sin resolver de #285 |
| circuito/item-9990630-seguimiento-pregunta-sin-resolver-de-2 | 3722488e18 | 2026-09-08 | Irving MegaISP | 2 | 2 | 9990630 | aprobado_irving | pending | Seguimiento: pregunta sin resolver de #274 |
| circuito/item-9990607-fase3-parqueo-shadow-mode | 583ab12325 | 2026-09-08 | Irving MegaISP | 1 | 3 | 9990607 | aprobado_irving | pending | Fase 3 — Conmutación del origen del pago a TalentoLedgerEntry (FRONTERA DURA DE DINERO, requiere go explícito de Irving) |
| circuito/bitacora-rescate-20260908-111405-wt-2 | 3b2f9a0fb2 | 2026-09-08 | Irving MegaISP | 1 | 1 |  | (sin item) | (sin item) |  |
| circuito/item-9990587-fase-1a-i-escribir-migracion-document | cce768ac20 | 2026-09-07 | Irving MegaISP | 1 | 1 | 9990587 | completado | done | Fase 1a-i — Escribir migración document_templates.status+updated_by+backfill |
| circuito/item-9990569-build-atomico-fase-1-parametrizar-mix-superseded | 405f1d92b6 | 2026-09-07 | Irving MegaISP | 3 | 2 | 9990569 | completado | done | Build atómico Fase 1 — parametrizar Mix a un directorio de staging |
| circuito/item-9990559-mr-23-fase-4d-a-tabla-mapa-red-histori | 0bc2212c46 | 2026-09-07 | Irving MegaISP | 3 | 3 | 9990559 | aprobado_irving | pending | MR-23 fase 4d-A — tabla mapa_red_historial + modelo + helper de registro + permiso |
| circuito/item-9990555-mr-23-fase-4b-i-impactoanalysisservice | 543d069876 | 2026-09-07 | Irving MegaISP | 3 | 5 | 9990555 | aprobado_irving | pending | MR-23 Fase 4b-i — ImpactoAnalysisService: recorrido recursivo aguas abajo (backend + endpoint + permiso) |
| circuito/item-9990539-mr-22-fase-2c-1-backend-drops-tabla-n | dc112a342e | 2026-09-07 | Irving MegaISP | 2 | 5 | 9990539 | aprobado_irving | pending | MR-22 Fase 2c-1 — Backend Drops: tabla network_drops (punto lat/lng) + modelo + CRUD |
| circuito/item-9990536-mr-08-fase-2a-catalogoscontroller-per | 7a4372d642 | 2026-09-07 | Irving MegaISP | 1 | 3 | 9990536 | aprobado_irving | pending | MR-08 Fase 2a — CatalogosController: permisos + cables/conectores (index/store/update/destroy) |
| circuito/item-9990500-header-los-badges-de-contador-salen-com | ac08e87ed0 | 2026-09-07 | Irving MegaISP | 1 | 4 | 9990500 | cancelado | cancelled | Header: los badges de contador salen como LÍNEA en modo oscuro (regla dark_mode.scss con !important) + posición y alineación del engrane |
| circuito/item-9990477-recuperacion-de-contrasena-agregar-el-l | aa045c5587 | 2026-09-07 | Irving MegaISP | 2 | 2 | 9990477 | completado | done | Recuperación de contraseña: agregar el link 'Olvidé mi contraseña' en el login y verificar el ciclo completo |
| circuito/item-9990459-mr-22-fase-3-arbol-derivado-de-max-3-n | d888298441 | 2026-09-07 | Irving MegaISP | 1 | 1 | 9990459 | completado | done | MR-22 Fase 3 — Árbol derivado de máx 3 niveles como panel lateral colapsable (D22) |
| circuito/item-9990455-mr-23-fase-4c-seccion-fotos-por-nodo | 8b9b297e64 | 2026-09-07 | Irving MegaISP | 3 | 8 | 9990455 | aprobado_irving | pending | MR-23 fase 4c — sección 'Fotos' por nodo/enlace del Mapa de Red |
| circuito/item-9990439-mr-24e-modo-dibujo-en-el-mapa-alta-de | a8e0755ddc | 2026-09-07 | Irving MegaISP | 3 | 5 | 9990439 | completado | done | MR-24e — Modo dibujo en el mapa: alta de NAP en 3 pasos + cable con snap a extremos |
| circuito/item-9990408-mr-12-ui-panel-de-union-de-hilos-doble | 0759a0fe70 | 2026-09-07 | Irving MegaISP | 1 | 4 | 9990408 | completado | done | MR-12 UI: panel de unión de hilos (doble clic en Rack/Mufa/NAP) |
| circuito/item-9990359-hijo-e2-regenerar-y-subir-escaneado-fi | becf65f54e | 2026-09-07 | Irving MegaISP | 10 | 7 | 9990359 | aprobado_irving | pending | Hijo E2 — Regenerar y Subir escaneado firmado con congelamiento de versión |
| circuito/bitacora-rescate-20260907-170405-wt-2 | 59a097369d | 2026-09-07 | Irving MegaISP | 5 | 6 |  | (sin item) | (sin item) |  |
| circuito/bitacora-rescate-20260907-164904-wt-3 | 8c0e135b48 | 2026-09-07 | Irving MegaISP | 5 | 6 |  | (sin item) | (sin item) |  |
| circuito/bitacora-rescate-20260907-095904-wt-2 | f36a289cf0 | 2026-09-07 | Irving MegaISP | 4 | 4 |  | (sin item) | (sin item) |  |
| circuito/item-9990437-mr-24d-endpoint-de-alta-rapida-de-nap | af4e76194a | 2026-09-06 | Irving MegaISP | 1 | 2 | 9990437 | completado | done | MR-24d — Endpoint de alta rápida de NAP (tipo + splitter, sin nombre a mano) |
| circuito/item-9990432-mr-20-backend-anexar-ocupacion-de-puer | b8b9d83a9c | 2026-09-06 | Irving MegaISP | 1 | 1 | 9990432 | completado | done | MR-20 backend — anexar ocupación de puertos por NAP al endpoint del mapa |
| circuito/item-9990411-fase-12-detectar-causalimite-cuenta-e | 8d8da4ce66 | 2026-09-06 | Irving MegaISP | 1 | 2 | 9990411 | aprobado_irving | pending | FASE 1+2: detectar causa=limite_cuenta en vuelta.sh y no castigar el item |
| circuito/bitacora-rescate-20260906-181154-wt-4 | 014bf403ba | 2026-09-06 | Irving MegaISP | 1 | 2 |  | (sin item) | (sin item) |  |
| circuito/bitacora-rescate-20260906-060204-wt-1 | fa87f32a03 | 2026-09-05 | Irving MegaISP | 1 | 1 |  | (sin item) | (sin item) |  |
| trabajo/mr-33-34-35-calidad-de-red | 14d432a641 | 2026-09-04 | Irving MegaISP | 1 | 1 |  | (sin item) | (sin item) |  |
| circuito/item-9990337-mr-06a-5-portar-proyectscontroller-s | b3c0bddd3d | 2026-09-04 | Irving MegaISP | 1 | 2 | 9990337 | completado | done | MR-06a-5 — Portar ProyectsController + ServiceBoxController a MapaRed |
| circuito/item-9990330-fase-2c-escribir-y-verificar-el-test-d | 71bbf8511f | 2026-09-04 | Irving MegaISP | 1 | 1 | 9990330 | completado | done | Fase 2c — Escribir y verificar el test de integración de la cadena depende_de sobre RoadmapItem::despachable() |
| circuito/item-9990328-mr-06a-backend-portar-los-6-controlle | fae03c927f | 2026-09-04 | Irving MegaISP | 2 | 1 | 9990328 | completado | done | MR-06a — Backend: portar los 6 controllers Geo (grupo vivo) a MapaRed apuntando a mapared_* |
| circuito/item-9990286-fase-3a-i-de-9990246-crear-la-clase-p | 73c899a903 | 2026-09-04 | Irving MegaISP | 2 | 0 | 9990286 | completado | done | Fase 3a-i de #9990246 — Crear la clase pura Support/AblandamientoFrontera.php |
| circuito/item-9990210-que-los-hallazgos-del-barrido-se-arregle | dffb301b70 | 2026-09-04 | Irving MegaISP | 3 | 3 | 9990210 | completado | done | Que los hallazgos del barrido se arreglen solos: una MENCIÓN de frontera dura deja de retener, salvo dinero y credenciales |
| circuito/item-9990085-frontend-vista-admin-crm-documentos-hu | 2bc5acaace | 2026-09-04 | Irving MegaISP | 2 | 9 | 9990085 | cancelado | done | Frontend: vista admin CRM 'Documentos huérfanos' (tabla Quasar + export CSV) — decisión oficial de Irving q3 |
| circuito/item-989-obsoleta-pre-refactor-verificarvuelta | 1f6eca4873 | 2026-09-04 | Irving MegaISP | 1 | 1 | 989 | completado | done | circuito:verificar-vuelta — capa de acción: revertir la rama de la vuelta o escalar via circuito:consultar cuando falla |
| circuito/item-809-fase3c-tabla-final-v2 | e190931ee7 | 2026-09-04 | Irving MegaISP | 1 | 1 | 809 | completado | done | Deriva #216 Fase 3c: ensamblar tabla markdown final + cerrar el paraguas #216 |
| circuito/item-629-ramas-huerfanas-pieza-c-descomposicion | f5685a4e57 | 2026-09-04 | Irving MegaISP | 1 | 1 | 629 | aprobado_irving | pending | Ramas huerfanas Pieza C (descomposicion+watchdog+DependenciaGate, jul-11) — evaluar rescate del gap real |
| circuito/item-195-la-compuerta-de-reservas-no-ve-reclamos | f426811aa5 | 2026-09-04 | Irving MegaISP | 1 | 1 | 195 | completado | done | La compuerta de reservas no ve reclamos huerfanos sin worker_sid |
| circuito/item-9990076-fase-1-repro-secuencial-mismo-proceso | 638ab4aef6 | 2026-09-03 | Irving MegaISP | 2 | 2 | 9990076 | completado | done | Fase 1 - Repro secuencial (mismo proceso) del cascade sobre #32 + instrumentacion temporal |
| circuito/item-929-fases-235-cierre-unificado-de-vuelta | 444a65c9ee | 2026-09-03 | Irving MegaISP | 1 | 1 | 929 | completado | done | FASES 2+3+5 — Cierre unificado de vuelta.sh (trap/finally) + motivo real en el log + verificación forzando el fallo |
| circuito/item-923-expediente-rh-catalogo-de-puestos-prop | f5cca6cab7 | 2026-09-03 | Irving MegaISP | 3 | 11 | 923 | completado | done | Expediente RH — catálogo de puestos propio: hoy son texto libre y los 28 colaboradores lo tienen vacío, así que el selector de paquetes sale sin opciones |
| circuito/item-923-catalogo-puestos | fb40245553 | 2026-09-03 | Irving MegaISP | 3 | 11 | 923 | completado | done | Expediente RH — catálogo de puestos propio: hoy son texto libre y los 28 colaboradores lo tienen vacío, así que el selector de paquetes sale sin opciones |
| circuito/item-865-fase-b-global-scope-de-alcance-propio | b448a5a78d | 2026-09-01 | Irving MegaISP | 3 | 5 | 865 | completado | done | Fase B — Global Scope de alcance propio para Inventario (piloto) + mensaje sin-acceso + ocultar botón crear |
| circuito/item-833-fase-1a-ii-parte-3n-resolver-colision | 213a55ec78 | 2026-09-01 | Irving MegaISP | 4 | 3 | 833 | completado | done | Fase 1a-ii parte 3/N: resolver colisión failed_jobs (aislado de #831) y continuar el ciclo fix-drift por el siguiente lote |
| fix/jarvis-chat-solo-navegacion | aa76574c70 | 2026-08-31 | Irving MegaISP | 1 | 5 |  | (sin item) | (sin item) |  |
| circuito/item-824-documentacioncorporativa-fase-5c2-ui | 2d7bba96ed | 2026-08-31 | Irving MegaISP | 1 | 3 | 824 | completado | done | DocumentaciónCorporativa Fase 5c.2 — UI: armar entrega desde una solicitud |
| circuito/item-825-jarvis-parte-3b-fase-1-migracion-mo | 8be75c59a0 | 2026-08-29 | Irving MegaISP | 2 | 0 | 825 | completado | done | Jarvis Parte 3b — Fase 1: migración + modelos jarvis_conversaciones/jarvis_mensajes |
| circuito/item-806-jarvis-parte-3b-decidir-donde-vive-el | a676994ac8 | 2026-08-29 | Irving MegaISP | 1 | 6 | 806 | completado | done | Jarvis Parte 3b — Decidir dónde vive 'el chat' de las sugerencias (brief de Irving) y cablearlo |
| circuito/item-800-deriva-216-fase-2b-extender-el-diff-co | 5bc06a26b5 | 2026-08-29 | Irving MegaISP | 1 | 1 | 800 | completado | done | Deriva #216 Fase 2b: extender el diff con índices y llaves foráneas |
| circuito/item-798-deriva-216-fase-1b-exportar-el-esquema | 170e811ed8 | 2026-08-29 | Irving MegaISP | 1 | 2 | 798 | completado | done | Deriva #216 Fase 1b: exportar el esquema de megaisp_dryrun a storage/schema/reference.sql (comando schema:build-reference) |
| circuito/item-759-documentacioncorporativa-fase-5b-servi | 214404cba2 | 2026-08-29 | Irving MegaISP | 1 | 5 | 759 | completado | done | DocumentaciónCorporativa Fase 5b — servicio de armado de entrega (ZIP + acta PDF + hash SHA-256) |
| circuito/item-758-documentacioncorporativa-fase-5a-dc-so | e178cd0557 | 2026-08-29 | Irving MegaISP | 4 | 15 | 758 | completado | done | DocumentaciónCorporativa Fase 5a — dc_solicitudes: correr migraciones + CRUD |
| circuito/item-752-doc-corporativa-fase-33-mapa-leaflet-wt2 | 7a5eb9a88e | 2026-08-29 | Irving MegaISP | 1 | 3 | 752 | completado | done | Doc. Corporativa Fase 3.3 — mapa Leaflet y formularios de alta para activos, activos digitales e inventario de accesos |
| circuito/item-743-p0-no-mergeados-tabla-de-auditoria-para | 851da7775c | 2026-08-29 | Irving MegaISP | 1 | 1 | 743 | completado | done | P0 no-mergeados: tabla de auditoría para que Irving decida cuáles reconstruir |
| circuito/item-740-deriva-de-esquema-216-fase-3-consumi | 1f39cedef9 | 2026-08-29 | Irving MegaISP | 1 | 3 | 740 | completado | done | Deriva de esquema #216 — Fase 3: consumidores + entregable final + cierre |
| circuito/item-734-documentacioncorporativa-fase-2a-repos | 48c45971ff | 2026-08-29 | Irving MegaISP | 2 | 10 | 734 | completado | done | DocumentacionCorporativa Fase 2a — Repositorio documental: carga, versionado y descarga |
| circuito/item-705-vigilante-on-box-208-invariantes-de | 60aa539111 | 2026-08-29 | Irving MegaISP | 1 | 3 | 705 | completado | done | Vigilante on-box (#208) — invariantes de Cola y discrepancias Sistema/Supervisor |
| circuito/item-720-flotas-saas-linea-de-facturacion-en-cal | 811a2de16e | 2026-08-28 | Irving MegaISP | 2 | 2 | 720 | cancelado | done | Flotas SaaS: línea de facturación en calculateAmounts() (patrón PULL, precedente Contratables) |
| circuito/item-695-talento-comision-kpi-fase-a-motor-act | 8e48e8f067 | 2026-08-28 | Irving MegaISP | 1 | 1 | 695 | completado | done | Talento comisión-KPI — Fase A: motor activo + evaluación real de clawback |
| circuito/item-681-respuesta-documentacioncorporativa-fas | 1abee1bd84 | 2026-08-28 | Irving MegaISP | 1 | 3 | 681 | completado | done | [RESPUESTA] DocumentaciónCorporativa Fase 0 — commits en main + 6 desviaciones a ratificar |
| circuito/item-672-pieza-1-instrumentar-la-valvula-conta | ea8353e4f5 | 2026-08-28 | Irving MegaISP | 1 | 1 | 672 | completado | done | Pieza 1 — Instrumentar la válvula: contador de aperturas de frontera dura en la Torre |
| circuito/item-667-documentacioncorporativa-fase-5-entre | e02554722a | 2026-08-28 | Irving MegaISP | 1 | 8 | 667 | cancelado | done | DocumentaciónCorporativa — Fase 5: entrega-recepción, bitácora y offboarding |
| circuito/item-652-preguntas-en-llano | cfae8c49cd | 2026-08-28 | Irving MegaISP | 4 | 8 | 652 | aprobado_irving | pending | Bandeja de Irving en lenguaje natural: resumen legible arriba, lo técnico en un desplegable |
| circuito/item-652-bandeja-lenguaje-natural | 9d78ec7982 | 2026-08-28 | Irving MegaISP | 2 | 6 | 652 | aprobado_irving | pending | Bandeja de Irving en lenguaje natural: resumen legible arriba, lo técnico en un desplegable |
| circuito/item-631-item-171-atorado-rama-ya-implementada | 633280b45f | 2026-08-28 | Irving MegaISP | 1 | 1 | 631 | completado | done | Item #171 atorado: rama ya implementada y probada, estado contradictorio, nunca integrada |
| circuito/item-291-auditar-90-llamadas-modalshow | ee1516f719 | 2026-08-28 | Irving MegaISP | 1 | 1 | 291 | completado | done | Auditar ~90 llamadas $(...).modal("show"/"hide") jQuery aún sin migrar a BS5 nativo |
| circuito/item-284-gr-6-opcional-encapsular-los-13-mode | 3ac0483376 | 2026-08-28 | Irving MegaISP | 1 | 1 | 284 | completado | done | GR-6 (opcional) — encapsular los 13 modelos OLT dentro del módulo GestionRed |
| circuito/item-283-decision-de-negocio-priorizar-driver-z | a7b4ba2997 | 2026-08-28 | Irving MegaISP | 1 | 1 | 283 | aprobado_irving | pending | Decisión de negocio: ¿priorizar driver ZTE y/o V-SOL? confirmar acceso a hardware piloto |
| circuito/item-230-megaisp-test-no-se-puede-construir-solo | 206b5c84cd | 2026-08-28 | Irving MegaISP | 2 | 2 | 230 | aprobado_irving | pending | megaisp_test no se puede construir sólo con migraciones: queda en 236 tablas de 502 |
| circuito/item-218-inventario-18-tipos-de-articulo-siguen | 6235352591 | 2026-08-28 | Irving MegaISP | 1 | 1 | 218 | completado | done | Inventario: ~18 tipos de artículo siguen sin categoría (herramienta/material) |
| circuito/item-179-contrasenas-legacy-en-base64-9-de-11-cu | 1a00266bdb | 2026-08-28 | Irving MegaISP | 2 | 3 | 179 | cancelado | done | Contrasenas legacy en base64: 9 de 11 cuentas privilegiadas guardan la contrasena de forma recuperable |
| circuito/item-155-client-main-informationuser-normalizar | 7c870ee6df | 2026-08-28 | Irving MegaISP | 1 | 1 | 155 | aprobado_irving | pending | client_main_information.user: normalizar formato de número de cliente (padding 004981 vs 4981) |
| torre-diff-dividido-por-defecto | 8a5172473b | 2026-08-27 | Irving MegaISP | 1 | 1 |  | (sin item) | (sin item) |  |
| circuito/item-193-caberenvuelta-dice-cabe-sin-senal-empiri | 852df3ae70 | 2026-08-26 | Irving MegaISP | 1 | 1 | 193 | completado | done | caberEnVuelta dice CABE sin senal empirica, y no existe planificador de descomposicion: partir un item depende de que el modelo obedezca la prosa |
| circuito/item-192-el-pool-real-es-0-mientras-el-tablero-mu | 91f4ade786 | 2026-08-26 | Irving MegaISP | 1 | 1 | 192 | completado | done | El pool real es 0 mientras el tablero muestra 12: un footprint desconocido en vuelo bloquea las 6 terminales y la compuerta lo pinta verde |
| circuito/item-181-sqlinjectionprotection-lanza-antes-del-a | d61e532eb8 | 2026-08-26 | Irving MegaISP | 1 | 1 | 181 | completado | done | SqlInjectionProtection lanza antes del abort(403) si su registro falla |
| circuito/item-108-definir-modelo-de-cobro-flotas-con-socio | 9c1fb823f4 | 2026-08-26 | Irving MegaISP | 1 | 1 | 108 | completado | done | Definir modelo de cobro Flotas con socio (bloquea Fase 6.2) |
| rescate/bitacora-item-191 | 1983a88885 | 2026-08-25 | Irving MegaISP | 1 | 1 |  | (sin item) | (sin item) |  |
| piezaC-gate-dependencia | 9cff6f6605 | 2026-08-25 | Irving MegaISP | 4 | 10 |  | (sin item) | (sin item) |  |
| piezaC-descomposicion | e55eeee2ba | 2026-08-25 | Irving MegaISP | 4 | 8 |  | (sin item) | (sin item) |  |
| circuito/item-209-el-boton-agregar-item-de-la-torre-revien | 185355bee7 | 2026-08-25 | Irving MegaISP | 1 | 1 | 209 | completado | done | El boton Agregar item de la Torre revienta y crea el item igual: eta_minutos/eta_asignada_at no existen en roadmap_items y no hay migracion |
| circuito/item-1044-ipv6-12b-migraciones-clientes-ipv6-p | 87aa536b92 | 2026-08-22 | Irving MegaISP | 1 | 4 | 1044 | (sin item) | (sin item) |  |
| circuito/item-984-ipv6-11-registro-del-modulo-addonsipv | 694d27b930 | 2026-08-21 | Irving MegaISP | 1 | 2 | 984 | completado | done | Circuito: 29 llamada(s) a env() en tiempo de ejecución fuera de config/ |
| circuito/item-978-retirar-el-fallback-legado-config-view-s | 51113ce030 | 2026-08-21 | Irving MegaISP | 1 | 2 | 978 | completado | done | Defecto 2 de #902 (FASE 4a) — JarvisService: distinguir frontera dura vs techo de nivel en 2 mensajes de escalada |
| circuito/item-977-configroute-permissionphp-paths-de-up | 0ae3ebdaf4 | 2026-08-21 | Irving MegaISP | 1 | 1 | 977 | completado | done | Válvula Fase 5: test de regresión — veredicto de válvula debe sellar frontera_valvula |
| circuito/item-943-torre-config-reorganizar-el-panel-por-a | 7f4fd3e38d | 2026-08-21 | Irving MegaISP | 1 | 1 | 943 | completado | done | MR-07 — Prueba de no regresión del módulo Mapas viejo |
| circuito/item-939-backend-890-orden-real-de-la-cola-en | a004039b01 | 2026-08-21 | Irving MegaISP | 1 | 1 | 939 | completado | done | MR-03 — Crear el módulo MapaRed (esqueleto, registro, sidebar, permisos) |
| circuito/item-875-consola-fase-1-semaforo-de-motores-en | 395a05befb | 2026-08-21 | Irving MegaISP | 2 | 4 | 875 | completado | done | Torre 24/7 · Pieza 3 — detectores de errores reales en el Motor de Auditoría (hoy sólo ve gaps de completitud y ya se agotaron) |
| circuito/item-1020-ventana-de-reversibilidad-medida-en-fila | 9dc640806d | 2026-08-21 | Irving MegaISP | 2 | 7 | 1020 | (sin item) | (sin item) |  |
| circuito/item-1015-auditor-559-memoria-de-cobertura-por | 872164bb3a | 2026-08-21 | Irving MegaISP | 1 | 1 | 1015 | (sin item) | (sin item) |  |
| circuito/item-935-diagnostico-wt1 | 06486b42a0 | 2026-08-20 | Irving MegaISP | 1 | 1 | 935 | completado | done | Detector A jQuery sin .off(): verificar en vivo, medir volumen y confirmar 3 citas al azar |
| circuito/item-935-diagnostico-item-service-v2 | aa34a382c7 | 2026-08-20 | Irving MegaISP | 1 | 1 | 935 | completado | done | Detector A jQuery sin .off(): verificar en vivo, medir volumen y confirmar 3 citas al azar |
| circuito/item-920-flotas-ui-de-documentos-de-conductor-s | 5ccf7e24f4 | 2026-08-20 | Irving MegaISP | 1 | 4 | 920 | completado | done | Torre 24/7 Pieza 4 — Fase 5: verificar el clasificador AUTO/BANDEJA contra items de seguridad reales ya cerrados |
| circuito/item-882-torre-de-control-fase-5-terminales | e6075f6d69 | 2026-08-20 | Irving MegaISP | 3 | 6 | 882 | completado | done | Torre 24/7 · soltar reclamos worker_sid huérfanos sin proceso vivo |
| circuito/item-881-torre-de-control-fase-3-catalogo-de-a | c6e0d015d7 | 2026-08-20 | Irving MegaISP | 1 | 3 | 881 | completado | done | Torre 24/7 · cerrar en cascada los 9 paraguas cuyos hijos ya cerraron |
| circuito/item-879-torre-de-control-fase-1-semaforo-de-m | 678b2d79f6 | 2026-08-20 | Irving MegaISP | 2 | 5 | 879 | cancelado | pending | Torre 24/7 · Pieza 3 — detectores de errores reales en el Motor de Auditoría (hoy sólo ve gaps de completitud y ya se agotaron) |
| circuito/item-876-consola-fase-3-catalogo-de-acciones | 7b1e1dde6c | 2026-08-20 | Irving MegaISP | 3 | 8 | 876 | cancelado | pending | Torre 24/7 · Pieza 4 — auto-corregir seguridad EN CÓDIGO sin preguntar, dejando permisos/credenciales/dinero en la bandeja |
| circuito/item-850-core-documentos-dar-permisos-granulares | 956d743014 | 2026-08-20 | Irving MegaISP | 1 | 1 | 850 | completado | done | Fase 4 - Controladores/APIs: checks explicitos por accion en modulos de alto riesgo |
| circuito/item-842-item-canal-de-respuesta-de-cc-hacia | 3019db0993 | 2026-08-20 | Irving MegaISP | 1 | 1 | 842 | aprobado_irving | pending | Item 2 — Catálogo completo de permisos por módulo (nivel B) |
| circuito/item-595-modulo-inversiones-fase-1-nucleo-ri | e37c84e249 | 2026-08-20 | Irving MegaISP | 2 | 20 | 595 | completado | done | [Reconstruido #1009] Items con canal de respuesta correcc |
| circuito/item-542-los-permisos-editados-en-un-rol-no-se-re | 6b92158baf | 2026-08-20 | Irving MegaISP | 4 | 4 | 542 | completado | done | [Reconstruido #923] Roadmap circuito cc amplia api endpoi |
| circuito/item-541-buscador-con-ia-en-panorama-reemplazar | 8cfb4be461 | 2026-08-20 | Irving MegaISP | 3 | 10 | 541 | completado | done | [Reconstruido #921] Resolucion de irving items agendados |
| circuito/item-872-mapa-de-las-seis-listas-de-terminos-del | adbb586d03 | 2026-08-19 | Irving MegaISP | 1 | 1 | 872 | completado | done | Expediente RH — Hijo D3: completar documentos pendientes al asignar vehículo/herramienta |
| circuito/item-863-agregar-item-ventana-de-deshacer-de-15s | 0b5988ccc7 | 2026-08-19 | Irving MegaISP | 1 | 3 | 863 | completado | done | Fase 4 (cont.) — checks log-only por acción en nómina (Talento/Liquidaciones) |
| circuito/item-844-triaje-match-por-substring-sin-distingu | fec8257189 | 2026-08-19 | Irving MegaISP | 1 | 1 | 844 | completado | done | Ejecutar schema:build-reference en dev, commitear reference.sql y cerrar #819 |
| circuito/item-835-modulemanager-declara-sus-endpoints-en | ae999b2476 | 2026-08-18 | Irving MegaISP | 1 | 1 | 835 | completado | done | DocumentaciónCorporativa Fase 5c.2b — UI: pantalla 'armar entrega' desde una solicitud |
| circuito/item-794-env-a-config-resto-no-critico-depl | bd23a8344f | 2026-08-18 | Irving MegaISP | 7 | 15 | 794 | completado | done | Barrido de servicios con métodos de ciclo en módulo Flotas que nada invoca |
| circuito/item-587-usuarios-getpermissionuser-y-updateperm | 02bc6c7252 | 2026-08-09 | Irving MegaISP | 1 | 1 | 587 | completado | done | [Reconstruido #1000] Ipv6 17b vuequasar pantalla 1 alta |
| circuito/item-559-item-madre-motor-de-auditoria-continua | d49b4f2d54 | 2026-08-09 | Irving MegaISP | 1 | 1 | 559 | completado | done | [Reconstruido #943] Torre config reorganizar el panel por a |
| circuito/item-558-registrar-addon-talento-en-module-regist | 28d2e44fa1 | 2026-08-09 | Irving MegaISP | 1 | 1 | 558 | completado | done | [Reconstruido #942] Torre config mostrar cadencias del cron |
| circuito/item-492-megafamilia-app-padre-facturas-pdf-x | 27b8a5bfb8 | 2026-08-09 | Irving MegaISP | 1 | 1 | 492 | completado | done | [Reconstruido #817] Finanzas amplia api endpoints en module |
| circuito/item-585-payments-resolvesystemuserid-busca-el-r | 3cfe4a2c38 | 2026-08-08 | Irving MegaISP | 1 | 1 | 585 | completado | done | [Reconstruido #992] Ipv6 fase 32 politica de trafico entra |
| circuito/item-499-megafamilia-backend-endpoints-de-vinc | d0483fdaf2 | 2026-08-08 | Irving MegaISP | 3 | 5 | 499 | completado | done | [Reconstruido #825] Megafamilia amplia api endpoints en mod |
| circuito/item-171-9-fase-2-chat-ia-ejecuta-acciones-too | 3b45454664 | 2026-08-08 | Irving MegaISP | 1 | 4 | 171 | completado | done | Guardrail de migraciones falla abierto en la ruta web de Ignition |
| circuito/item-531-bloqueador-48-migraciones-aplicadas-en | 3a97e2a784 | 2026-08-06 | Irving MegaISP | 1 | 2 | 531 | completado | done | [Reconstruido #885] Torre de control fase 8 historial de |
| circuito/item-526-normalizar-el-drift-del-campo-modulo-te | 0efe2a164e | 2026-08-06 | Irving MegaISP | 2 | 2 | 526 | completado | done | [Reconstruido #872] Mapa de las seis listas de terminos del |
| circuito/item-505-rutas-de-testscriptcontroller-script | e8a1f47beb | 2026-08-04 | Irving MegaISP | 1 | 3 | 505 | completado | done | [Reconstruido #831] Usuarios declara sus endpoints en modul |
| circuito/item-496-megafamilia-infra-otp-permisos-de-ca | 803b90aba8 | 2026-08-04 | Irving MegaISP | 3 | 8 | 496 | completado | done | [Reconstruido #822] Flotas amplia api endpoints en modulej |
| circuito/item-486-asesorcobranza-auditar-los-metodos-de | 2129d68c3f | 2026-08-04 | Irving MegaISP | 1 | 1 | 486 | completado | done | [Reconstruido #810] Item modulo centro de proyecto |
| circuito/item-479-temporizador | 2f6ea72cc5 | 2026-08-04 | Irving MegaISP | 1 | 2 | 479 | completado | done | [Reconstruido #790] Recuperar el warm up configcache 97 ll |
| circuito/item-473-circuitoseguridad-portal-cliente-r | 358fe0262f | 2026-08-04 | Irving MegaISP | 3 | 11 | 473 | completado | done | [Reconstruido #590] Configuracion cerrar 1 marcadores tod |
| circuito/item-471-parked-espec-megafamilia-brief-fluj | 8471281c06 | 2026-08-04 | Irving MegaISP | 1 | 1 | 471 | completado | done | [Reconstruido #588] Clientes cerrar 1 marcadores todofix |
| circuito/item-190-pendiente-negocio-definir-asesor-cobra | 769c800da0 | 2026-08-04 | Irving MegaISP | 1 | 1 | 190 | aprobado_irving | pending | Más items |
| circuito/item-416-olt-decision-de-arquitectura-saas-mult | 5e3ed09886 | 2026-07-15 | Irving MegaISP | 1 | 1 | 416 | completado | done | [Reconstruido #456] Investigar causa raiz por que la bandej |
| circuito/item-344-item-roadmap-consejo-asesor-equipo | 2a31a99b7f | 2026-07-15 | Irving MegaISP | 1 | 3 | 344 | completado | done | [Reconstruido #341] /#342 (candados de proceso) + #337 (disparo manual/urgente) |
| circuito/item-19-cerrar-modulo-megafamilia-contra-checkli | 255986f008 | 2026-07-15 | Irving MegaISP | 1 | 1 | 19 | aprobado_irving | pending | Cerrar modulo MegaFamilia contra checklist (bloqueado por infra Padre-Hijo y motor de servicios) |
| circuito/item-167-manual-doble-fuente-de-modulos-modules | 673314c821 | 2026-07-15 | Irving MegaISP | 1 | 1 | 167 | completado | done | Manual: doble fuente de módulos (modules legacy vs module_registry) — consolidar |
| circuito/item-117-integracion-pac-para-cfdi-40-factura-f | ba1710448c | 2026-07-15 | Irving MegaISP | 3 | 4 | 117 | aprobado_irving | pending | Integración PAC para CFDI 4.0 (factura fiscal) |
| circuito/item-57-modulo-olt-propio-multi-marca-auditori | d536194bbc | 2026-07-14 | Irving MegaISP | 1 | 1 | 57 | completado | done | Módulo OLT propio multi-marca — auditoría y plan de trabajo |
| circuito/item-474-arquitecturaclientesmulti-terminal | 0947e13764 | 2026-07-14 | Irving MegaISP | 3 | 4 | 474 | completado | done | [Reconstruido #591] Crm eliminar 15 metodos de andamiaje |
| circuito/item-23-auditoria-de-la-apk-34-inventario-de-p | 3d17beb0b5 | 2026-07-14 | Irving MegaISP | 1 | 1 | 23 | completado | done | Auditoria de la APK 3.4: inventario de pantallas (funcional/parcial/pendiente), endpoints, stack y perfil de usuario |
| circuito/item-185-arquitectura-voicegateway-unico-via-am | de3de15131 | 2026-07-14 | Irving MegaISP | 2 | 2 | 185 | aprobado_irving | pending | Más 8tems |
| circuito/item-155-client-main-informationuser-normalizar | f3f66b4bc0 | 2026-07-14 | Irving MegaISP | 3 | 4 | 155 | aprobado_irving | pending | client_main_information.user: normalizar formato de número de cliente (padding 004981 vs 4981) |
| circuito/fase1-metadata-decisiones | 4f324a19b4 | 2026-07-14 | Irving MegaISP | 1 | 6 |  | (sin item) | (sin item) |  |
| circuito/fase-a-anti-bucle | df82f7e4c6 | 2026-07-14 | Irving MegaISP | 1 | 9 |  | (sin item) | (sin item) |  |
| circuito/item-411-titulo-boton-escuchar-tts-en-las-ta | 77cbaa94f8 | 2026-07-13 | Irving MegaISP | 1 | 1 | 411 | completado | done | [Reconstruido #436] Circuito ccui agregar item priori |
| circuito/item-265-trescuatro-mecanismos-de-autorizacion-c | 30ce5ffd74 | 2026-07-13 | Irving MegaISP | 3 | 2 | 265 | (sin item) | (sin item) |  |
| circuito/c2 | 59d45bdac0 | 2026-07-13 | Irving MegaISP | 1 | 4 |  | (sin item) | (sin item) |  |
| circuito/item-57-modulo-olt-propio-multi-marca-audit | 3ef7342d86 | 2026-07-12 | Irving MegaISP | 1 | 1 | 57 | completado | done | Módulo OLT propio multi-marca — auditoría y plan de trabajo |
| circuito/item-331-accion-validado-archivo-en-integracion | 98dd663f02 | 2026-07-12 | Irving MegaISP | 1 | 1 | 331 | completado | done | [Reconstruido #309] Desactivar auto grant de view a roles b |
| circuito/item-328-reporte-dual-tecnico-coloquial | 2e6a254b0e | 2026-07-12 | Irving MegaISP | 5 | 5 | 328 | completado | done | [Reconstruido #295] Deuda todos sin resolver en inventario |
| circuito/item-308-auditoria-de-modulos-registro-de-contr | 32915c52c8 | 2026-07-12 | Irving MegaISP | 3 | 4 | 308 | completado | done | [Reconstruido #248] Credentialupdatecontrollerupload dir |
| circuito/item-282-flotas-y-gestionred-montan-su-propio-lea | 07f424edac | 2026-07-12 | Irving MegaISP | 1 | 5 | 282 | completado | done | OLT Huawei — implementar capacidades Supports* opcionales para paridad con SmartOLT |
| circuito/item-271-get-client-status-expuesto-a-cualqu | 591e485d9a | 2026-07-12 | Irving MegaISP | 2 | 4 | 271 | completado | done | Payments: cerrar 1 marcador(es) TODO/FIXME en PaymentApplicationService.php |
| circuito/item-251-contrasenas-de-cliente-almacenadas-y-c | 90e990322a | 2026-07-12 | Irving MegaISP | 3 | 6 | 251 | (sin item) | (sin item) |  |
| circuito/item-244-inyeccion-de-comandos-por-el-campo-versi | fe22de605d | 2026-07-12 | Irving MegaISP | 3 | 4 | 244 | (sin item) | (sin item) |  |
| circuito/item-232-clientes-http-propios-a-claude-leyendo-c | b9b14c9c3f | 2026-07-12 | Irving MegaISP | 2 | 2 | 232 | completado | done | Falta la migración del motor auditor: roadmap_items no tiene tipo, hallazgo_firma ni auditoria_ciclo |
| circuito/item-224-captura-de-pago-en-mostrador-sin-idempot | 45493d3d19 | 2026-07-12 | Irving MegaISP | 1 | 3 | 224 | completado | done | Flotas: el OCR de documentos es un placeholder rotulado "Fase 7" |
| circuito/item-212-whatsappiaservice-reimplementa-su-propio | 31d39a29f2 | 2026-07-12 | Irving MegaISP | 1 | 2 | 212 | completado | done | INANICION: los items sin footprint nunca se despachan si hay cualquier cosa en vuelo — 5 urgentes inalcanzables |
| circuito/item-206-checkroutepermission-lee-solo-permisos-d | e9b257fce3 | 2026-07-12 | Irving MegaISP | 1 | 2 | 206 | cancelado | cancelled | Vigilante on-box: que los atascos se avisen solos en vez de buscarlos a mano |
| circuito/item-196-whatsapp-fase-3-a-reiniciar-desconectar | f6bfb6277c | 2026-07-12 | Irving MegaISP | 2 | 4 | 196 | completado | done | cEstaciones cuenta mal por select corto: 11 items A/B se pintan sin triar |
| circuito/item-174-visibilidad-de-modulos-fase-4b-bloque | f4f9ed0b8f | 2026-07-12 | Irving MegaISP | 2 | 3 | 174 | completado | done | vuelta.sh: timeout al proceso padre, tope de iteraciones y deteccion de huerfano |
| circuito/item-170-vendedores-renderizar-saldo-null-como | c827fffb77 | 2026-07-12 | Irving MegaISP | 1 | 1 | 170 | aprobado_revisor | pending | Circuito: freno de mano fuera de la BD (centinela en archivo + isPaused fail-closed) |
| circuito/item-159-blocked-negocio-deuda-dos-tablas-de-f | 756e03441a | 2026-07-12 | Irving MegaISP | 1 | 1 | 159 | aprobado_irving | pending | Deuda: dos tablas de facturas (invoices vs client_invoices) |
| circuito/item-137-spa-v2-re-ejecucion-controlada-de-at-push | 4433ad0040 | 2026-07-12 | Irving MegaISP | 1 | 3 | 137 | completado | done | SPA v2: re-ejecución controlada de @push('scripts') |
| circuito/item-332-voz-natural-selector-de-voz-en-escu | 6b6cb6b0fa | 2026-07-11 | Irving MegaISP | 2 | 1 | 332 | completado | done | [Reconstruido #311] Aislamiento por rama |
| circuito/item-289-inventario-assigntouser-y-changestore-n | 5382471563 | 2026-07-11 | Irving MegaISP | 2 | 2 | 289 | completado | done | Configuracion: amplía api_endpoints en module.json (25/176 = 14%, umbral 30%) |
| fix/smart-import-autoincrement-gaps | 2d94cad3c3 | 2026-06-13 | romelio_suarez | 2 | 6 |  | (sin item) | (sin item) |  |
| fix/smart-import-autoincrement-gaps | e30ea037db | 2026-06-13 | romelio_suarez | 501 | 2539 |  | (sin item) | (sin item) |  |
| feature/auto-deploy-on-release | fc73632b98 | 2026-06-09 | romeliosuarez | 358 | 2404 |  | (sin item) | (sin item) |  |
| chore/scrub-github-token-docs | 6bd7e11ddc | 2026-06-02 | Irving MegaISP | 1 | 2 |  | (sin item) | (sin item) |  |
| fixes-migraciones | 9f65804c23 | 2026-06-01 | romeliosuarez | 2 | 7 |  | (sin item) | (sin item) |  |
| fixes-migraciones | bffbc0da0b | 2026-06-01 | romeliosuarez | 208 | 1965 |  | (sin item) | (sin item) |  |
| wip-main-local-changes-2026-05-26 | 0c64062c8d | 2026-05-30 | Carlos | 204 | 1960 |  | (sin item) | (sin item) |  |
| ajustes-import-bd | 87ac84f724 | 2026-05-28 | carlos.rodriguez | 2 | 169 |  | (sin item) | (sin item) |  |
| ajustes-import-bd | 96de88ebf8 | 2026-05-28 | carlos.rodriguez | 155 | 1408 |  | (sin item) | (sin item) |  |
| migrations-from-meganet | 34ea0b3a6b | 2026-05-26 | romeliosuarez | 3 | 169 |  | (sin item) | (sin item) |  |
| migrations-from-meganet | d6c619271b | 2026-05-26 | romeliosuarez | 106 | 1243 |  | (sin item) | (sin item) |  |
| feature/modular-arch | 5241dc2f9d | 2026-05-21 | Irving MegaISP | 103 | 1109 |  | (sin item) | (sin item) |  |

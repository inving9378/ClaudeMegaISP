# CIRC-04 PASO 4 — Propuesta de re-priorización de la cola viva (#9990909)

_Documento generado automáticamente. NO aplica ningún cambio en la base de datos — es solo la propuesta para que Irving la apruebe de un golpe. El UPDATE masivo, si se aprueba, se ejecuta en un item aparte._

**Fecha de medición:** 2026-09-11 19:33 · **Total de items en cola viva** (estado_aprobación NOT IN [completado, cancelado]): 255

## Criterio aplicado (definido por el prompt de #9990887, PASO 4)

- **alta** = el item está en uno de los **tres frentes de cierre del mes** (ver abajo), **o** desbloquea a otros items vivos al cerrarse.
- **media** = todo lo demás que sigue vigente, sin frente activo ni desbloqueo detectado.
- **baja** = vigente pero sin fecha/urgencia real: paraguas ya parqueado esperando a sus hijos (no es trabajo accionable hoy), o esperando una decisión de Irving sin pertenecer a ningún frente activo.

### Cómo se midió "desbloquea a otros" (importante para leer la tabla)

Solo cuenta la dependencia **declarada explícitamente** (`depende_de`): si el item A tiene `depende_de=[B]`, cerrar B es lo que permite a A avanzar → el crédito de "desbloquea" es para **B**. Es una señal selectiva: 33 de los 256 items vivos declaran `depende_de`.

**Se descartó** usar la relación paraguas→sub-item (`origen_item_id`) como señal de desbloqueo, aunque en sentido estricto cerrar un sub-item sí ayuda a que su paraguas cierre. Razón: **190 de 256 items vivos (74%) son hijos de algún paraguas** — es la forma estructural dominante de la cola hoy (ver el patrón "bucle reap sobre paraguas ya descompuesto" repetido decenas de veces en `CLAUDE.md`). Usar esa relación como criterio de "alta" vuelve a producir casi todo-alta, exactamente el problema que #9990887 pide corregir — una versión preliminar de este documento lo intentó y dio 220 de 256 en alta (86%), peor que el 56% actual. Se corrigió antes de entregar la propuesta.

El paraguas en sí, mientras espera a sus hijos, no es trabajo accionable — por eso, si no pertenece a ningún frente y no tiene otro motivo, esta propuesta lo manda a **baja** cuando ya está `excluir_pool_automatico` (parqueado).

### Los tres frentes de cierre del mes (criterio propio, a validar por Irving)

El item padre (#9990887) no enumera cuáles son los "tres frentes" — se identificaron aquí con evidencia objetiva: concentración de trabajo vigente por módulo + actividad real de los últimos commits + directivas explícitas de Irving ya registradas en el roadmap. **Si Irving tiene otros tres frentes en mente, se ajusta esta sección y se recalcula la tabla — es la única pieza subjetiva de la propuesta.**

1. **Circuito CC — auto-mejora e higiene del propio circuito** (módulos `Roadmap / Circuito CC`, `Auditoria`, `Roadmap/Circuito`, `Roadmap / Torre de control`). Evidencia: de los ~25 commits más recientes en `main`, la inmensa mayoría son CIRC-01 a CIRC-08 (paraguas destrabados, hilo de respuestas, motivo_espera, censo de ramas). Es, medido, el frente con más actividad real ahora mismo.
2. **Mapa de Red** (módulos `Mapa de Red`, `GestionRed`, `Gestión de Red`). Evidencia: épica activa desde el item #936, con la directiva explícita de Irving del 2026-09-06 ("destapado_mapa" — construir el mapa completo sin pacing) todavía vigente sobre ~29 sub-items abiertos.
3. **Talento — Expediente digital del colaborador + Identidad unificada + Motor de comisiones** (módulos `Talento`, `Ventas / Talento`). Evidencia: dos épicas raíz activas (#9990792 Expediente digital, #9990778 Identidad unificada) con commits mergeados a diario esta semana (documentos/firmas/acuses, `colaborador_id` en 6 tablas legado).

**Nota honesta:** incluso con el crédito corregido, los tres frentes por sí solos ya cubren 179 de 255 items vivos. Eso es alto porque hoy la cola está dominada por esos tres frentes activos — no porque el criterio siga inflando. Si Irving considera que aun así "alta" sigue siendo demasiado, la palanca correcta es acotar cuántos módulos entran en cada frente (por ejemplo, separar "Auditoria" de "Circuito CC" en dos frentes distintos en vez de uno), no relajar el criterio de desbloqueo (que ya quedó estricto).

### Distribución actual vs. propuesta

| Prioridad | Actual | Propuesta |
|---|---|---|
| alta | 143 | 182 |
| media | 71 | 14 |
| baja | 6 | 59 |
| (sin prioridad) | 35 | — |

**Items que cambiarían de prioridad:** 137 de 255.

### Por frente

| Frente | Items vivos |
|---|---|
| Circuito CC | 102 |
| Mapa de Red | 37 |
| Talento | 40 |
| (sin frente) | 76 |

## Tabla completa (una fila por item, ordenada alta → media → baja, luego por id)

| ID | Módulo | Título | Estado | Actual | Propuesta | ¿Cambia? | Motivo |
|---|---|---|---|---|---|---|---|
| 170 | Roadmap / Circuito CC | Circuito: freno de mano fuera de la BD (centinela en archivo + isPaused fail-closed) | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 185 | Roadmap / Circuito CC | Más 8tems | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 190 | Auditoria | Más items | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 191 | Talento | Expediente digital del personal: plantillas por puesto y generación al alta | aprobado_irving | alta | alta | no | frente activo: Talento |
| 203 | Talento | Expediente RH — Hijo E: pestana Documentos en perfil colaborador y vendedor | aprobado_irving | alta | alta | no | frente activo: Talento |
| 231 | Roadmap / Circuito CC | Podar los worktrees muertos: 3 no se pueden mergear y 5 llevan más de 700 commits de at... | aprobado_irving | media | alta | SÍ | frente activo: Circuito CC |
| 281 | GestionRed | OLT Huawei — cerrar escritura real en laboratorio (alta/baja/suspensión de ONU, Fase A+B) | aprobado_irving | (sin) | alta | SÍ | frente activo: Mapa de Red |
| 283 | GestionRed | Decisión de negocio: ¿priorizar driver ZTE y/o V-SOL? confirmar acceso a hardware piloto | aprobado_irving | (sin) | alta | SÍ | frente activo: Mapa de Red |
| 629 | Roadmap / Circuito CC | Ramas huerfanas Pieza C (descomposicion+watchdog+DependenciaGate, jul-11) — evaluar res... | aprobado_irving | (sin) | alta | SÍ | frente activo: Circuito CC |
| 652 | Roadmap / Circuito CC | Bandeja de Irving en lenguaje natural: resumen legible arriba, lo técnico en un despleg... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 671 | GestionRed | Implementar ZteDriver real (lectura + provisioning básico) — bloqueado por hardware piloto | aprobado_irving | (sin) | alta | SÍ | frente activo: Mapa de Red |
| 692 | GestionRed | OLT Huawei Fase A — sesión presencial con Irving: validar authorizeOnu/deauthorizeOnu/s... | aprobado_irving | (sin) | alta | SÍ | frente activo: Mapa de Red |
| 924 | Roadmap / Circuito CC | Root-cause: paraguas cierre-en-cascada dejó pasar un nivel-C sin merge a 'completado' (... | aprobado_irving | (sin) | alta | SÍ | frente activo: Circuito CC |
| 936 | Mapa de Red | MR-00 — Épica: módulo MAPA DE RED (convivencia con Mapas, migración y corte) | aprobado_irving | alta | alta | no | frente activo: Mapa de Red |
| 944 | Mapa de Red | MR-08 — Catálogos: tipos de cable, tipos de splitter, tipos de caja, conectores | aprobado_irving | alta | alta | no | frente activo: Mapa de Red |
| 958 | Mapa de Red | MR-22 — Nueva navegación: buscador, filtros por capa, clustering, render por zoom | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 959 | Mapa de Red | MR-23 — Ficha lateral del elemento (reemplaza los 5 iconos por fila) | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 961 | Mapa de Red | MR-25 — Importador KML/KMZ + GeoJSON + CSV y exportadores | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990013 | Roadmap / Circuito CC | Endurecer el punto exacto que dejó pasar a #32 sin merge (según causa confirmada en el ... | aprobado_irving | (sin) | alta | SÍ | frente activo: Circuito CC |
| 9990228 | Roadmap / Circuito CC | Cablear DependenciaGate al scheduler vivo + circuito:sub-item --depende-de | aprobado_irving | (sin) | alta | SÍ | frente activo: Circuito CC |
| 9990260 | Roadmap / Circuito CC | Fase 1 — circuito:sub-item --depende-de + posición + detección de ciclos | aprobado_irving | (sin) | alta | SÍ | frente activo: Circuito CC |
| 9990263 | Roadmap / Circuito CC | Fase 1b — SubItemCommand: --depende-de + position + integración con DependenciaGate::ti... | aprobado_revisor | (sin) | alta | SÍ | frente activo: Circuito CC |
| 9990359 | Talento | Hijo E2 — Regenerar y Subir escaneado firmado con congelamiento de versión | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990361 | Talento | Hijo E4 — acceso Documentos en perfil de Colaborador de Talento (modal reusando compone... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990409 | Mapa de Red | [RESPUESTA] MR-13 — Splitter como objeto — catálogo adelantado + supuestos de diseño a ... | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990410 | Roadmap / Circuito CC | El circuito no distingue «la cuenta se quedó sin límite» de «este item es demasiado gra... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990411 | Roadmap / Circuito CC | FASE 1+2: detectar causa=limite_cuenta en vuelta.sh y no castigar el item | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990412 | Roadmap / Circuito CC | FASE 4: pausar el scheduler (no lanzar más vueltas) mientras la cuenta esté sin límite | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990413 | Roadmap / Circuito CC | FASE 3: visibilidad en la Torre del estado «cuenta sin límite» | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990415 | Roadmap / Circuito CC | FASE 1: vuelta.sh detecta causa=limite_cuenta (grep 'session limit') | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990417 | Roadmap / Circuito CC | FASE 4a: expiración opcional en el centinela FrenoCircuito (campo expira_en + autolimpi... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990418 | Roadmap / Circuito CC | FASE 4b: enganchar el freno-con-expiración en vuelta.sh cuando causa=limite_cuenta | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990419 | Roadmap / Circuito CC | Backend: exponer causa/expira_en del freno en pausedInfo() cuando lo puso #9990412 (lim... | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990420 | Roadmap / Circuito CC | Frontend: banner distintivo 'Cuenta de Claude sin límite hasta HH:MM' en TorreControl.vue | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990421 | Mapa de Red | [RESPUESTA] MR-15 — Backfill al modelo nuevo — mirror vacío bloquea toda conversión real | aprobado_revisor | media | alta | SÍ | frente activo: Mapa de Red |
| 9990424 | Roadmap / Circuito CC | FASE 4a-1: FrenoCircuito::poner() con expira_en opcional + método expirado() | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990425 | Roadmap / Circuito CC | FASE 4a-2: autolimpieza en isPaused() + candado de regresión (3 casos) | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990429 | Mapa de Red | MR-23 fase 4 — acciones nuevas Mover/Trazar/Ver impacto y secciones Fotos/Historial de ... | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990445 | Mapa de Red | MR-25 Fase 4 — Exportadores KML/GeoJSON/carta de empalme PDF/BOM XLSX (D21) | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990451 | Mapa de Red | MR-23 fase 4a — acción 'Trazar' (dibujar y guardar enlace entre 2 nodos) | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990452 | Talento | Migrar el motor de comisiones y pagos de vendedor a Talento — EJECUCIÓN (continúa #123) | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990454 | Mapa de Red | MR-23 fase 4b — acción 'Ver impacto' (clientes/nodos afectados aguas abajo) | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990455 | Mapa de Red | MR-23 fase 4c — sección 'Fotos' por nodo/enlace del Mapa de Red | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990456 | Mapa de Red | MR-23 fase 4d — sección 'Historial de cambios' por nodo/enlace del Mapa de Red | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990457 | Mapa de Red | MR-22 Fase 1 — Buscador global del mapa (nombre/cliente/serie ONT/dirección) | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990460 | Mapa de Red | MR-22 Fase 4 — Verificación DoD final (apertura <3s con 6 zonas, buscar NAP <5s) y cier... | aprobado_revisor | media | alta | SÍ | frente activo: Mapa de Red |
| 9990491 | Roadmap / Circuito CC | Rebuild del bundle no es atómico: pantallas dan "no se puede pintar" mientras se recompila | aprobado_irving | media | alta | SÍ | frente activo: Circuito CC |
| 9990494 | Mapa de Red | MR-22 Fase 2c — Capas de Drops y Cobertura: no existen hoy como capas geográficas (requ... | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990504 | Mapa de Red | MR-08 Fase 2 — CRUD backend + permisos mapa_red_catalogo_* de los 4 catálogos | aprobado_irving | alta | alta | no | frente activo: Mapa de Red |
| 9990505 | Mapa de Red | MR-08 Fase 3 — UI de administración de los 4 catálogos | aprobado_irving | alta | alta | no | frente activo: Mapa de Red |
| 9990509 | Mapa de Red | MR-22 Fase 1a — Endpoint backend de búsqueda global del mapa | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990519 | Mapa de Red | MR-23 fase 4a (2/2) — Frontend: acción 'Trazar' en LeafletMapRed.vue (click secuencial ... | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990534 | Mapa de Red | MR-08 Fase 3 — UI de administración de los 4 catálogos (retomar cuando Fase 2 #9990504 ... | aprobado_revisor | alta | alta | no | frente activo: Mapa de Red |
| 9990536 | Mapa de Red | MR-08 Fase 2a — CatalogosController: permisos + cables/conectores (index/store/update/d... | aprobado_irving | alta | alta | no | frente activo: Mapa de Red |
| 9990538 | Mapa de Red | MR-08 Fase 2b — CatalogosController: cajas/splitters (index/store/update/destroy), comp... | aprobado_irving | alta | alta | no | frente activo: Mapa de Red |
| 9990539 | Mapa de Red | MR-22 Fase 2c-1 — Backend Drops: tabla network_drops (punto lat/lng) + modelo + CRUD | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990542 | Mapa de Red | MR-22 Fase 2c-3 — Render read-only de capas Drops y Cobertura declarada en el mapa Leaflet | aprobado_revisor | media | alta | SÍ | frente activo: Mapa de Red |
| 9990543 | Mapa de Red | MR-22 Fase 2d — Edición visual de Drops/Cobertura + captura automática de Drop al activ... | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990550 | Auditoria | Documentación Corporativa — fecha de inicio del plazo de 180 días hábiles (columna nueva) | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990555 | Mapa de Red | MR-23 Fase 4b-i — ImpactoAnalysisService: recorrido recursivo aguas abajo (backend + en... | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990556 | Mapa de Red | MR-23 Fase 4b-ii — UI 'Ver impacto': menú contextual + panel Quasar con nodos/clientes ... | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990559 | Mapa de Red | MR-23 fase 4d-A — tabla mapa_red_historial + modelo + helper de registro + permiso | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990560 | Mapa de Red | MR-23 fase 4d-B — instrumentar mover/editar/eliminar + endpoint GET de listado | aprobado_revisor | media | alta | SÍ | frente activo: Mapa de Red |
| 9990561 | Mapa de Red | MR-23 fase 4d-C — pestaña/sección 'Historial' en ElementSidePanel.vue | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990571 | Roadmap / Circuito CC | Build atómico — aplicar el mismo patrón al deploy de PROD (RemoteDeployCommand npm_buil... | aprobado_irving | media | alta | SÍ | frente activo: Circuito CC |
| 9990576 | Auditoria | DC plazo 180d hábiles — Fase 4 (futura, explícitamente fuera de esta iteración): alerta... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990578 | Auditoria | Fase 1 — document_templates: columna status borrador/publicada + updated_by + campo en ... | aprobado_irving | (sin) | alta | SÍ | frente activo: Circuito CC |
| 9990584 | Auditoria | Fase 1a — Migración: document_templates.status + updated_by + backfill | aprobado_irving | (sin) | alta | SÍ | frente activo: Circuito CC |
| 9990585 | Auditoria | Fase 1b — Modelo DocumentTemplate: fillable status + updated_by | aprobado_revisor | (sin) | alta | SÍ | frente activo: Circuito CC |
| 9990586 | Auditoria | Fase 1c — CRUD: select Borrador/Publicada en form de plantilla + validación | aprobado_revisor | (sin) | alta | SÍ | frente activo: Circuito CC |
| 9990607 | Talento | Fase 3 — Conmutación del origen del pago a TalentoLedgerEntry (FRONTERA DURA DE DINERO,... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990608 | Talento | Fase 4 — Migración histórica y limpieza de los modelos dormidos de comisiones de vendedor | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990612 | Talento | Fase 4a — Migración histórica (72 pagos + 39 reglas) con dry-run y conciliación al cent... | aprobado_revisor | alta | alta | no | frente activo: Talento |
| 9990613 | Talento | Fase 4b — Deprecar (no borrar) los modelos dormidos de comisiones de vendedor tras vent... | aprobado_revisor | alta | alta | no | frente activo: Talento |
| 9990657 | Roadmap/Circuito | Fase A verificación en vivo (e): confirmar aislamiento real contra /var/www/megaisp | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990659 | Roadmap/Circuito | Fase B — verificacion en vivo REAL contra /var/www/megaisp (rama ajena a mano), requier... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990719 | Roadmap / Circuito CC | Divergencia entre items completados y ramas sin integrar | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990721 | Auditoria | permisos | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990730 | Roadmap / Circuito CC | Implementar auditoría periódica + gate en el cierre para items completado sin merge (de... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990743 | Auditoria | Fase 0+1 — diagnóstico confirmado (transacción+rollback): diff SUPERVISOR_MOSTRADOR vs ... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990744 | Auditoria | Fase 2/3 — verificar hipótesis H1-H10 según la rama de Fase 1 | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990745 | Auditoria | Fase 4 — inventario y matriz completa de permisos (obligatorio, en paralelo) | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990746 | Auditoria | Documento final docs/auditoria-permisos-2026-09.md + canal de respuesta | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990765 | Auditoria | Fase 4c — Matriz por rol: permisos asignados vs. permisos que surten efecto (punto 4) | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990766 | Auditoria | Fase 4c — cerrar: vista Blade+Vue de la matriz de permisos por rol + correr real + pobl... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990767 | Auditoria | Fase 4c-i — mergear rama backend existente + migrar + vista Blade+Vue de la matriz de p... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990768 | Auditoria | Fase 4c-ii — correr auditoria:permisos-matriz-roles contra BD real de dev y cerrar el i... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990776 | Permisos / Roles | Auditoría de permisos: rol con permisos asignados que no se reflejan en el administrado... | aprobado_irving | alta | alta | no | desbloquea 1 item(s) vivo(s) al cerrarse |
| 9990777 | Permisos / Roles | CheckRoutePermission debe leer permisos de rol, no solo permisos directos | aprobado_irving | alta | alta | no | desbloquea 1 item(s) vivo(s) al cerrarse |
| 9990778 | Ventas / Talento | Identidad unificada: colaborador único, sellers como puente, migración aditiva de selle... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990779 | Ventas / Talento | Catálogo único de prospectos: consolidar CRM, Vendedores y Portal Colaborador en una so... | aprobado_revisor | media | alta | SÍ | frente activo: Talento |
| 9990781 | Ventas / Talento | Catálogos parametrizables: modalidades, modos de pago y parámetros del reglamento | aprobado_irving | media | alta | SÍ | frente activo: Talento |
| 9990782 | Ventas / Talento | Motor de comisiones sobre talento_ledger_entries (devengo, congelado por venta, sin cam... | aprobado_irving | media | alta | SÍ | frente activo: Talento |
| 9990783 | Ventas / Talento | Motor de maduración: meta, candado de suficiencia, faltante, netting y reverso por pago... | aprobado_irving | media | alta | SÍ | frente activo: Talento |
| 9990784 | Ventas / Talento | Convenio prellenado desde plantilla | aprobado_revisor | media | alta | SÍ | frente activo: Talento |
| 9990785 | Ventas / Talento | Pestañas de Talento según rol de acceso + perfil comercial del colaborador | aprobado_irving | media | alta | SÍ | frente activo: Talento |
| 9990786 | Ventas / Talento | Comisión por recuperación de equipo: registro con foto, serie y reingreso a inventario | pendiente_revision | baja | alta | SÍ | frente activo: Talento |
| 9990787 | Embajadores | Migrar Embajadores al motor unificado de comisiones (programa, no motor aparte) | aprobado_irving | media | alta | SÍ | desbloquea 1 item(s) vivo(s) al cerrarse |
| 9990790 | Ventas / Talento | [RESPUESTA] Subir el reglamento de ventas y comisiones al repo — falta el texto fuente ... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990792 | Talento | Expediente digital del colaborador: tipos de documento, bloqueo proporcional y firma en... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990793 | Talento | Manuales y cursos obligatorios por puesto sobre la Academia de Talento existente | aprobado_revisor | media | alta | SÍ | frente activo: Talento |
| 9990794 | Talento | Escalafón: avance documental y académico → propuesta de incremento y mejora de comisión | aprobado_irving | media | alta | SÍ | frente activo: Talento |
| 9990795 | Talento | App del colaborador (PWA o nativa): firma, documentos, cursos y operación en campo | aprobado_irving | baja | alta | SÍ | frente activo: Talento |
| 9990798 | Ventas / Talento | Calculadora de comisiones: servicio puro con los casos TC1–TC12 como tests unitarios | aprobado_revisor | alta | alta | no | frente activo: Talento |
| 9990803 | Auditoria | Clientes: el buscador del listado debe buscar únicamente en las columnas visibles de ca... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990805 | Talento | Firma en pantalla con metadata legal completa (IP, dispositivo, hash, trazos) y hoja de... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990806 | Talento | Reapertura automática de acuses al cambiar de versión de plantilla | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990807 | Talento | Pantalla del colaborador (pendientes/firmados) + tablero de pendientes para Irving + en... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990808 | Ventas / Talento | Motor de ventas: catálogos backend (migraciones + modelos + permisos + CRUD API) — Fase... | aprobado_irving | media | alta | SÍ | frente activo: Talento |
| 9990809 | Ventas / Talento | Motor de ventas: UI CRUD de catálogos (modalidades/modos de pago/parámetros reglamento)... | aprobado_irving | media | alta | SÍ | frente activo: Talento |
| 9990815 | Auditoria | Fase 4 — Medición p50/p95, criterios de aceptación y encendido del flag en dev | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990816 | Talento | Tablero admin para Irving: qué colaborador tiene qué documento pendiente (con antigüeda... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990817 | Talento | Reapertura de acuses — verificación end-to-end con datos sintéticos (rollback) | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990820 | Talento | Fase 3 de #9990805: regla especial del Convenio de Comisiones (escaneado + estado 'parc... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990826 | Talento | Reapertura de acuses — implementar y correr el test E2E (Feature, DB de test) | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990827 | Talento | Reapertura de acuses — cerrar #9990806 con reporte_coloquial + enlace_revision | aprobado_revisor | alta | alta | no | frente activo: Talento |
| 9990830 | Talento | Tablero pendientes Fase 1 — permiso talento.documentos.ver-todos + endpoint admin GET /... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990831 | Talento | Tablero pendientes Fase 2 — endpoint POST /talento/api/documentos/{docId}/recordar (Wha... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990832 | Talento | Tablero pendientes Fase 3 — pantalla admin del tablero (tabla + KPIs + boton Recordar c... | aprobado_revisor | alta | alta | no | frente activo: Talento |
| 9990834 | Talento | Reapertura de acuses — implementar y pasar ReaperturaAcusesTest.php (happy path + negat... | aprobado_revisor | alta | alta | no | frente activo: Talento |
| 9990839 | Auditoria | Fase 4a — Medir p50/p95 real (nombre vs SN) y repasar los 10 criterios de aceptación de... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990840 | Auditoria | Fase 4b — Encender CLIENTES_BUSQUEDA_V2 en dev + config:auditar-env/cache + cierre de #... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990853 | Gestión de Red | corrección del Circuito CC — para desatorar el flujo | requiere_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990854 | Auditoria | corrección del Circuito CC — para desatorar el flujo | aprobado_irving | media | alta | SÍ | frente activo: Circuito CC |
| 9990856 | Roadmap / Circuito CC | El comentario de Irving en un item requiere_irving es la respuesta: hilo que no se pisa... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990858 | Roadmap / Circuito CC | CIRC-02b Mecanismo: hilo de respuestas (roadmap_item_respuestas) + re-encolado automático | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990859 | Roadmap / Circuito CC | CIRC-02c Visibilidad: hilo de respuestas en la Torre + watchdog de respuestas sin consumir | aprobado_revisor | media | alta | SÍ | frente activo: Circuito CC |
| 9990860 | Roadmap / Circuito CC | Bandeja de decisiones: distinguir el item que espera una decisión del que espera un ins... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990861 | Roadmap / Circuito CC | Re-triaje de la cola: prioridad real, limpieza de basura y clasificación de los 430 ite... | aprobado_irving | media | alta | SÍ | frente activo: Circuito CC |
| 9990862 | Roadmap / Circuito CC | API roadmap-externo: creación de items con token propio (RC) + hilo de reportes que no ... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990863 | Roadmap / Circuito CC | Terminales ociosas con items aprobados: por qué el despachador no asigna y por qué "Lis... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990866 | Auditoria | CIRC-02a — Diagnóstico solo-lectura: cuántas respuestas de Irving se han perdido | aprobado_revisor | media | alta | SÍ | frente activo: Circuito CC |
| 9990867 | Auditoria | CIRC-02b — Hilo de respuestas de Irving + re-encolado automático (tabla roadmap_item_re... | aprobado_revisor | media | alta | SÍ | frente activo: Circuito CC |
| 9990868 | Auditoria | CIRC-02c — Visibilidad del hilo en la Torre + watchdog de respuestas sin consumir | aprobado_revisor | media | alta | SÍ | frente activo: Circuito CC |
| 9990869 | Auditoria | CIRC-03 — Bandeja de decisiones real: separar "espera decisión" de "espera insumo mater... | aprobado_revisor | media | alta | SÍ | frente activo: Circuito CC |
| 9990870 | Auditoria | CIRC-04 — Re-triaje de la cola: coherencia cancelados, basura y 430 items sin nivel de ... | aprobado_revisor | media | alta | SÍ | frente activo: Circuito CC |
| 9990871 | Auditoria | CIRC-05 — API externa roadmap-externo: creación de items (token RC) + hilo unificado de... | aprobado_irving | media | alta | SÍ | frente activo: Circuito CC |
| 9990874 | Auditoria | Items de corrección del Circuito CC — para desatorar el flujo | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990875 | Roadmap / Circuito CC | PASO 3 de #9990861: clasificar nivel_riesgo de los 428 items CERRADOS sin nivel (metada... | aprobado_revisor | media | alta | SÍ | frente activo: Circuito CC |
| 9990876 | Roadmap / Circuito CC | PASO 4 de #9990861: propuesta de re-priorización de la cola viva (docs/roadmap/, NO apl... | aprobado_revisor | media | alta | SÍ | frente activo: Circuito CC |
| 9990878 | Ventas / Talento | Identidad unificada — Fase 3: doble escritura de colaborador_id junto a seller_id | en_progreso | alta | alta | no | frente activo: Talento |
| 9990879 | Ventas / Talento | Identidad unificada — Fase 4: corte de lectura de seller_id a colaborador_id, módulo po... | aprobado_irving | alta | alta | no | frente activo: Talento |
| 9990887 | Auditoria | CIRC-04: Re-triaje de la cola — prioridad real, limpieza de basura y clasificación de l... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990888 | Auditoria | CIRC-05: Extender la API externa roadmap-externo — creación de items con token propio (... | en_progreso | alta | alta | no | frente activo: Circuito CC |
| 9990890 | Auditoria | CIRC-07: Bug de vista — la pestaña Hoja de ruta muestra 0/0/0/0 con el filtro Todos hab... | requiere_irving | alta | alta | no | frente activo: Circuito CC |
| 9990891 | Auditoria | CIRC-08 (SOLO LECTURA): Inventario de ramas sin integrar — qué trabajo terminado existe... | en_progreso | alta | alta | no | frente activo: Circuito CC |
| 9990892 | Roadmap / Circuito CC | Fase 2 (C1) — exentar documentación del detector de colisiones en vuelo + simulación hi... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990893 | Roadmap / Circuito CC | Fase 3 (C2) — que 'Listos para terminal' diga la verdad + contador de vueltas quemadas ... | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990894 | Roadmap / Circuito CC | Fase 4 (C3/C4/C5) — leer el log de despacho y decidir tope de concurrencia/frenos según... | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990895 | Roadmap / Circuito CC | Fase 5 — tablero permanente 'terminales ociosas x minutos' y 'elegibles reales' en la T... | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990896 | Roadmap / Circuito CC | Fase 2a — confirmar con evidencia real si colisión es causa frecuente de terminales oci... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990897 | Roadmap / Circuito CC | Fase 2b — implementar exención de docs en footprintDeRama()/footprintEnVivo() | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990898 | Roadmap / Circuito CC | Fase 2c — simulación histórica antes de activar la exención de docs | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990899 | Roadmap / Circuito CC | CIRC-02b PASO 1 — Tabla roadmap_item_respuestas (migración aditiva + modelo) | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990900 | Roadmap / Circuito CC | CIRC-02b PASO 2 — Disparo: comentario humano sobre requiere_irving crea respuesta + re-... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990901 | Roadmap / Circuito CC | CIRC-02b PASO 3 — Inyección de la respuesta en el prompt con precedencia | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990902 | Roadmap / Circuito CC | CIRC-02b PASO 4 — Pruebas end-to-end de los 3 pasos anteriores | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990903 | Roadmap / Circuito CC | [RESPUESTA] CIRC-02b — 3 cadenas duplicadas del mismo trabajo (CIRC-02a/b/c) | aprobado_irving | media | alta | SÍ | frente activo: Circuito CC |
| 9990909 | Auditoria | CIRC-04 PASO 4 — Propuesta (documento, sin aplicar) de re-priorización de la cola viva | en_progreso | alta | alta | no | frente activo: Circuito CC |
| 9990910 | Roadmap / Circuito CC | Migración aditiva motivo_espera en roadmap_items + proyección en COLUMNAS_BANDEJA | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990911 | Roadmap / Circuito CC | Backfill motivo_espera para los 10 items ya identificados por Irving (aprobado_irving +... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990912 | Roadmap / Circuito CC | Torre — bandeja partida en dos listas (Esperan tu decisión / Esperan un insumo tuyo) + ... | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990913 | Roadmap / Circuito CC | Barrido de aprobado_irving por texto de bloqueo (candidatos adicionales a motivo_espera) | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990916 | Roadmap / Circuito CC | Fase 2a (reintento) — medir colision como causa de terminales ociosas con >=15 min real... | aprobado_irving | alta | alta | no | frente activo: Circuito CC |
| 9990919 | Roadmap / Circuito CC | CIRC-02b PASO 1b — Modelo RoadmapItemRespuesta + relacion en RoadmapItem | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990921 | Auditoria | CIRC-08 Fase 3+4 — Cuatro cubetas del cruce con el roadmap + partir la cubeta (b) por l... | en_progreso | alta | alta | no | frente activo: Circuito CC |
| 9990922 | Auditoria | CIRC-08 Fase 5+6 — Ensamblar el reporte final docs/inventario-ramas-sin-integrar-{fecha... | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990923 | Roadmap / Circuito CC | Fase 3a — SupervisorService: excluir de 'Listos para terminal' items con depende_de sin... | en_progreso | alta | alta | no | frente activo: Circuito CC |
| 9990924 | Roadmap / Circuito CC | Fase 3b — SupervisorService: ocultar de 'Listos para terminal' items con bloqueo declar... | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990925 | Roadmap / Circuito CC | Fase 3c — Torre: mostrar el contador de 'vueltas quemadas' ya existente (reap_count/vec... | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990927 | Roadmap / Circuito CC | Proyectar motivo_espera en RoadmapController::COLUMNAS_BANDEJA + verificación torre() | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990933 | Gestión de Red | CIRC-02 · El comentario de Irving en un item requiere_irving es la respuesta: | aprobado_irving | media | alta | SÍ | frente activo: Mapa de Red |
| 9990935 | Talento | CIRC-08 · Inventario de las ramas sin integrar (SOLO LECTURA) | aprobado_revisor | media | alta | SÍ | frente activo: Talento |
| 9990936 | Roadmap / Circuito CC | Fase 2a (segundo reintento) — verdicto de colision con >=1h real desde el merge de #999... | en_progreso | alta | alta | no | frente activo: Circuito CC |
| 9990937 | Auditoria | CIRC-03 · Bandeja de decisiones real: separar "espera decisión" de "espera insumo" | aprobado_revisor | media | alta | SÍ | frente activo: Circuito CC |
| 9990938 | Roadmap / Circuito CC | Fase 3a-i — depende_de: filtrar 'Listos para terminal' con el mismo criterio MR-36 de R... | en_progreso | alta | alta | no | frente activo: Circuito CC |
| 9990939 | Roadmap / Circuito CC | Fase 3a-ii — motivo_espera: excluir de 'Listos para terminal' items con motivo_espera a... | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990942 | Roadmap / Circuito CC | Fase 2a (tercer reintento) — verdicto de colision con >=1h real desde el merge de #9990863 | en_progreso | alta | alta | no | frente activo: Circuito CC |
| 9990943 | Roadmap / Circuito CC | Fase A — RoadmapCircuitoService::filtrarConDependenciasCerradas() público (MR-36 reusab... | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990944 | Roadmap / Circuito CC | Fase B — SupervisorService: aplicar filtrarConDependenciasCerradas() en listosParaTermi... | aprobado_revisor | alta | alta | no | frente activo: Circuito CC |
| 9990945 | Auditoria | Seguimiento: pregunta sin resolver de #9990882 | aprobado_revisor | (sin) | alta | SÍ | frente activo: Circuito CC |
| 9990946 | Auditoria | CIRC-05 pieza A — Idempotencia de creación externa vía clave_externa | pendiente_revision | alta | alta | no | frente activo: Circuito CC |
| 754 | Finanzas | Fase 4 (real): migrar KpiController/HomeController/AuditController a leer invoices — bl... | aprobado_irving | (sin) | media | SÍ | vigente, sin frente activo ni desbloqueo detectado |
| 788 | Flotas | Cablear FlotasProrrateoService en ClientRepository::resolveFleetSubscriptionLines() tra... | aprobado_revisor | (sin) | media | SÍ | vigente, sin frente activo ni desbloqueo detectado |
| 9990642 | Deploy/Releases | Acceso de www-data a GitHub (deploy key READ-ONLY) para el resolver de versión | aprobado_irving | media | media | no | vigente, sin frente activo ni desbloqueo detectado |
| 9990671 | Deploy / Releases | F1 — Estado de publicación visible por versión en el historial de la Torre | aprobado_irving | alta | media | SÍ | vigente, sin frente activo ni desbloqueo detectado |
| 9990672 | Deploy / Releases | F1·B — La pantalla de actualización de producción muestra el salto completo y acumulado | aprobado_irving | alta | media | SÍ | vigente, sin frente activo ni desbloqueo detectado |
| 9990675 | Deploy / Releases | F4 — Candado en base de datos: no puede existir una versión sin Release publicado | aprobado_irving | alta | media | SÍ | vigente, sin frente activo ni desbloqueo detectado |
| 9990677 | Deploy / Releases | F6 — Reconciliar la historia: publicar retroactivo o marcar histórica | aprobado_irving | alta | media | SÍ | vigente, sin frente activo ni desbloqueo detectado |
| 9990678 | Deploy / Releases | F7 — Dejar la regla escrita y separar el permiso de emitir | aprobado_irving | alta | media | SÍ | vigente, sin frente activo ni desbloqueo detectado |
| 9990679 | Deploy / Releases | F8 — Emitir V1.35 con el motor nuevo: la prueba de fuego | aprobado_irving | alta | media | SÍ | vigente, sin frente activo ni desbloqueo detectado |
| 9990727 | VoIP | Fase 6 — El provisionador de Asterisk + especificación de compilación (secciones 3 y 4) | aprobado_irving | alta | media | SÍ | vigente, sin frente activo ni desbloqueo detectado |
| 9990728 | VoIP | Fase 7 — Prueba obligatoria de punta a punta en dev + cierre del paraguas #9990718 | aprobado_irving | alta | media | SÍ | vigente, sin frente activo ni desbloqueo detectado |
| 9990788 | Vendedores | Apagado del módulo Vendedores y limpieza de la tabla puente sellers | aprobado_irving | media | media | no | vigente, sin frente activo ni desbloqueo detectado |
| 9990797 | Permisos / Roles | Ejecutar fix de Diana: asignar rol SUPERVISOR_MOSTRADOR + resync (bloqueado hasta que c... | aprobado_irving | alta | media | SÍ | vigente, sin frente activo ni desbloqueo detectado |
| 9990934 | Core / Permisos | CIRC-09 · Panorama en árbol (y baja de tres pestañas) | aprobado_revisor | media | media | no | vigente, sin frente activo ni desbloqueo detectado |
| 19 | MegaFamilia | Cerrar modulo MegaFamilia contra checklist (bloqueado por infra Padre-Hijo y motor de s... | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 30 | MegaFamilia | Vista Hijo en la APK (perfil restringido segun permisos del padre) | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 66 | Flotas | Flotas Fase 7 — IA agrupada (OCR, prediccion, asistente conversacional) | aprobado_irving | baja | baja | no | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 72 | MegaFamilia | Greenfield: configurar Firebase desde cero + integrar FCM HTTP v1 en MegaFamilia | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 82 | Flotas | Verificar supuestos del protocolo Ruptela con GPS físico real (⚠️ VERIFY de Sub-fase 2.3a) | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 117 | Payments | Integración PAC para CFDI 4.0 (factura fiscal) | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 149 | PortalCliente | Portal: cobro/tarifas premium MegaFamilia | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 154 | PortalCliente | Portal Cliente: contraseñas en texto plano — evaluar migración a bcrypt (Opción B) | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 155 | Clientes | client_main_information.user: normalizar formato de número de cliente (padding 004981 v... | aprobado_irving | baja | baja | no | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 159 | Finanzas | Deuda: dos tablas de facturas (invoices vs client_invoices) | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 164 | Finanzas | Fase 2 Medussa: cuota de software por transacción conciliada | aprobado_irving | baja | baja | no | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 226 | Infra | [INFRA] Renovar el certificado de dev.meganett.com.mx con hook DNS-01 automatizado | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 227 | Infra | [INFRA] Exentar de la detección de DOS del MikroTik el tráfico a dst=38.123.192.199 dpo... | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 230 | Infra | megaisp_test no se puede construir sólo con migraciones: queda en 236 tablas de 502 | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 625 | MegaFamilia | MegaFamilia/Portal: flujo OTP real de login (hoy solo existe el toggle sin flujo) | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 632 | Finanzas | Decidir y ejecutar la unificación invoices/client_invoices (dinero en vivo — nivel C) | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 635 | PortalCliente | Portal Cliente: ejecutar migración a bcrypt (Opción B) — decisión de diseño | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 638 | MegaFamilia | Vista Hijo APK: flujo de vinculación/login del hijo en megafamilia-rn | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 639 | MegaFamilia | Vista Hijo APK: reemplazar mocks de Logros/Apps permitidas/Tiempo de pantalla por datos... | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 661 | MegaFamilia | Tiempo de pantalla real en la Vista Hijo: tracking nativo Android (decisión de alcance/... | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 662 | DocumentacionCorporativa | DocumentaciónCorporativa — Fase 0: cimiento del addon (catálogo, permisos, bitácora, ta... | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 679 | Auth | Ola escalonada: convertir las 4043 cuentas no-privilegiadas con password legacy base64 | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 680 | Auth | Retirar el fallback legacy de PasswordService::check() una vez agotada la ola no-privil... | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 683 | Payments | PAC CFDI 4.0 — integrar Facturama real (bloqueado por credenciales de Irving) | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 687 | Flotas | Flotas — Asistente conversacional sobre la flota | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 691 | Finanzas | Implementar cálculo real de cuota Medussa por transacción conciliada (BLOQUEADO sin aut... | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 718 | MegaFamilia | Decidir alcance/plataforma de tiempo de pantalla real (Vista Hijo) y ejecutar cuando ex... | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 723 | Finanzas | Unificación invoices/client_invoices — Fase 4: migrar lectores de bajo riesgo a invoices | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 724 | Finanzas | Unificación invoices/client_invoices — Fase 5: ventana de monitoreo en prod con dual-wr... | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 725 | Finanzas | Unificación invoices/client_invoices — Fase 6: cortar escritura primaria a invoices (5 ... | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 726 | Finanzas | Unificación invoices/client_invoices — Fase 7: decommission client_invoices | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 755 | Flotas | Flotas SaaS: prorrateo diario por vehículo en la línea de facturación | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 842 | ModuleManager | Item 2 — Catálogo completo de permisos por módulo (nivel B) | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990075 | Infra | [INFRA] Hook DNS-01 automatizado para dev.meganett.com.mx (bloqueado: falta credencial ... | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990288 | Mapas | MR-33 — Potencia de referencia por caja e histórico de mediciones | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990289 | Mapas | MR-34 — Motor de bonos de calidad de red hacia Talento | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990290 | Mapas | MR-35 — Sentido de la luz animado en el mapa | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990347 | Infra | Seguimiento: pregunta sin resolver de #146 | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990350 | Infra | Guard explícito de path duro en backups:purge-test (storage/backup_test) | aprobado_revisor | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990600 | Core / Permisos | FRONTERA DURA — Crear cuenta personal DESARROLLADOR para David (decisión de Irving) | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990629 | Payments | Seguimiento: pregunta sin resolver de #272 | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990630 | Usuarios | Seguimiento: pregunta sin resolver de #274 | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990631 | Payments | Seguimiento: pregunta sin resolver de #285 | aprobado_irving | (sin) | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990635 | Deploy / Releases | Versionado dev↔prod: el consecutivo salía de la tabla releases y retrocedía (RESUELTO F... | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990667 | VozMayorista | Voz Mayorista — Sprint 1: plano de control comercial (bloque 1: esqueleto, blindaje de ... | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990668 | Deploy / Releases | Emisión atómica de versiones: una versión existe si y sólo si está publicada | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990670 | Deploy / Releases | SEG-1 — Rotar/asegurar el token de GitHub antes de que el path web lo use en F1 | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990674 | Deploy / Releases | F3 — ReleasePublisher: emisión atómica con compensación | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990713 | VoIP / Infraestructura | Empaquetar Asterisk como .deb propio y montar el repositorio APT de Meganet | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990715 | VoIP / Infraestructura | Voz Fase 2 — Sanear el esquema realtime: volver a Alembic estándar y no parchear tablas... | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990718 | VoIP | Provisionador del módulo VoIP: que la actualización deje Asterisk instalado y funcionando | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990723 | VoIP | Fase 2 — Correcciones mínimas del módulo VoIP (sección 6) | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990725 | VoIP | Fase 4 — Extensiones sembradas por departamento (sección 8) | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990729 | Deploy / Releases | F3 (#9990674): ejecutar a mano el ensayo real de red cortada (push+GitHub Release TEST-) | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990735 | VoIP / Infraestructura | [RESPUESTA] Voz Fase 3 — Cerrar la seguridad: Asterisk sin root, sin directorio 777 y s... | aprobado_irving | media | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990789 | Facturación | Cambiar el concepto del CFDI al suspenderse el servicio: de servicio de Internet a rent... | aprobado_irving | baja | baja | no | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990791 | Permisos / Roles | [RESPUESTA] Auditoría de permisos: rol con permisos asignados que no se reflejan en el ... | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990810 | Core / Permisos | Cerrar VoIP: de dev hasta que suene el teléfono en producción | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |
| 9990825 | Core / Permisos | VoIP Fase 3 — emitir version, publicar GitHub Release y documentar el runbook de despli... | aprobado_irving | alta | baja | SÍ | paraguas parqueado esperando el cierre de sus hijos (no es trabajo accionable) |

## Siguiente paso

Si Irving aprueba esta propuesta (tal cual, o con ajustes a los "tres frentes"), el cambio masivo de `priority` se ejecuta en un item aparte de este documento — este item (#9990909) solo entrega la propuesta.


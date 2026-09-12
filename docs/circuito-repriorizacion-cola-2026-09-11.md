# Propuesta de re-priorización de la cola viva del Circuito CC

**Item:** #9991011 (CIRC-04 PASO 4, sub-item de seguimiento de #9990870)
**Medición:** 2026-09-11 ~21:15 CST (2026-09-12 ~03:15 UTC, hora de guardado en BD)
**Alcance:** propuesta únicamente. **Este documento NO ejecuta ningún cambio de `priority`.** El UPDATE real, si Irving aprueba la tabla, se hace en un item aparte (así lo exige el padre #9990870/#9991011).

## Resumen ejecutivo

Cola viva (`estado_aprobacion NOT IN ('completado','cancelado')`) al momento de la medición: **241 items** (la descripción original del item citaba 244 y una distribución alta=131/media=71/baja=6/sin-prioridad=36; la cola es dinámica — el circuito cierra y crea items constantemente — así que el conteo exacto ya cambió, pero el sesgo hacia 'alta' que motivó el item **sigue presente**: 127 de 241 items live, el 52.7%, estaban marcados 'alta' antes de esta propuesta).

| Prioridad actual | Cantidad |
|---|---|
| alta | 127 |
| media | 72 |
| baja | 6 |
| sin-prioridad | 36 |
| **Total** | **241** |

| Prioridad propuesta | Cantidad |
|---|---|
| alta | 133 |
| media | 105 |
| baja | 3 |
| **Total** | **241** |

**Movimiento neto:** el conteo de 'alta' casi no cambia (127→133), pero la **composición sí cambia a fondo**: 62 items que hoy están en 'alta' **bajan a media** (no cumplen ningún criterio objetivo de alta) y 57 items que hoy NO están en 'alta' **suben** porque sí cumplen un criterio objetivo. El problema real no era solo "hay demasiados en alta" — era que 'alta' no correspondía a ningún criterio verificable. Esta propuesta ata cada 'alta' a una razón concreta y revisable.

## Criterio aplicado (heredado del padre #9990870, literal)

- **alta** = está en un frente de cierre del mes (épica MAPA DE RED activa — `modulo='Mapa de Red'` — O es un item CIRC-0x del propio circuito — título con patrón `CIRC-\d+`) **O** tiene otros items vivos apuntándole vía `depende_de`/`origen_item_id` (en ese sentido "desbloquea a otros").
- **media** = vigente, sin esas condiciones.
- **baja** = vigente, sin fecha/urgencia. **No se demota nada a baja en esta propuesta** por falta de una señal objetiva de "sin fecha/urgencia" en el esquema actual (no hay campo de fecha límite) — los 6 items que ya eran 'baja' se mantienen tal cual salvo que sí cumplan un criterio de alta (3 casos, ver tabla). Bajar más granularidad a 'baja' queda a criterio manual de Irving al revisar esta tabla.

### Nota metodológica importante — el criterio de `origen_item_id` cuenta ambos sentidos igual

El criterio del padre dice literalmente "tiene otros items apuntándole vía `depende_de`/`origen_item_id`". Para `depende_de` es inequívoco: si Z tiene `depende_de=[Y]`, Z está bloqueado esperando a Y, así que Y cerrando desbloquea a Z. Para `origen_item_id` la relación es al revés en la práctica: si Z tiene `origen_item_id=Y`, Z es un sub-item nacido de un paraguas Y (Y es quien espera a Z para cerrar por cascada, no Z quien espera a Y). Aun así, contar esa arista como señal de "hub activo" es razonable — son justo los paraguas documentados en `CLAUDE.md` (la familia de items "bucle reap sobre paraguas ya descompuesto": #936, #9990733, #9990650, etc.) los que concentran trabajo real pendiente en sus hijos. Se aplicó el criterio tal cual lo definió el padre, pero **queda anotado aquí por transparencia**: varios de los 'alta' propuestos son paraguas administrativos (ya `aprobado_irving`+`excluir_pool_automatico`, aparcados esperando a sus hijos) y no trabajo directo — su urgencia real vive en sus hijos, que a su vez pueden o no calificar para alta por sí mismos según esta misma tabla.

## Tabla completa — 241 items vivos

Orden: prioridad propuesta (alta → media → baja), luego id ascendente.

| ID | Título | Actual | Propuesta | Motivo |
|---|---|---|---|---|
| 19 | Cerrar modulo MegaFamilia contra checklist (bloqueado por infra Padre-Hijo y motor de servicios) | media | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 30 | Vista Hijo en la APK (perfil restringido segun permisos del padre) | media | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 66 | Flotas Fase 7 — IA agrupada (OCR, prediccion, asistente conversacional) | baja | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 117 | Integración PAC para CFDI 4.0 (factura fiscal) | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 154 | Portal Cliente: contraseñas en texto plano — evaluar migración a bcrypt (Opción B) | media | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 159 | Deuda: dos tablas de facturas (invoices vs client_invoices) | media | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 164 | Fase 2 Medussa: cuota de software por transacción conciliada | baja | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 185 | Más 8tems | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 191 | Expediente digital del personal: plantillas por puesto y generación al alta | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 203 | Expediente RH — Hijo E: pestana Documentos en perfil colaborador y vendedor | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 226 | [INFRA] Renovar el certificado de dev.meganett.com.mx con hook DNS-01 automatizado | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 281 | OLT Huawei — cerrar escritura real en laboratorio (alta/baja/suspensión de ONU, Fase A+B) | sin-prioridad | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 283 | Decisión de negocio: ¿priorizar driver ZTE y/o V-SOL? confirmar acceso a hardware piloto | sin-prioridad | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 629 | Ramas huerfanas Pieza C (descomposicion+watchdog+DependenciaGate, jul-11) — evaluar rescate del gap real | sin-prioridad | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 632 | Decidir y ejecutar la unificación invoices/client_invoices (dinero en vivo — nivel C) | alta | **alta** | desbloquea a otros items (4 apuntan a él vía depende_de/origen_item_id) |
| 639 | Vista Hijo APK: reemplazar mocks de Logros/Apps permitidas/Tiempo de pantalla por datos reales | sin-prioridad | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 661 | Tiempo de pantalla real en la Vista Hijo: tracking nativo Android (decisión de alcance/plataforma pendiente con Irving) | sin-prioridad | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 723 | Unificación invoices/client_invoices — Fase 4: migrar lectores de bajo riesgo a invoices | sin-prioridad | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 755 | Flotas SaaS: prorrateo diario por vehículo en la línea de facturación | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 924 | Root-cause: paraguas cierre-en-cascada dejó pasar un nivel-C sin merge a 'completado' (item #32) | sin-prioridad | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 936 | MR-00 — Épica: módulo MAPA DE RED (convivencia con Mapas, migración y corte) | alta | **alta** | épica MAPA DE RED activa; desbloquea a otros items (7 apuntan a él vía depende_de/origen_item_id) |
| 944 | MR-08 — Catálogos: tipos de cable, tipos de splitter, tipos de caja, conectores | alta | **alta** | épica MAPA DE RED activa; desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 958 | MR-22 — Nueva navegación: buscador, filtros por capa, clustering, render por zoom | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 959 | MR-23 — Ficha lateral del elemento (reemplaza los 5 iconos por fila) | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 961 | MR-25 — Importador KML/KMZ + GeoJSON + CSV y exportadores | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990228 | Cablear DependenciaGate al scheduler vivo + circuito:sub-item --depende-de | sin-prioridad | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990260 | Fase 1 — circuito:sub-item --depende-de + posición + detección de ciclos | sin-prioridad | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990347 | Seguimiento: pregunta sin resolver de #146 | sin-prioridad | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990409 | [RESPUESTA] MR-13 — Splitter como objeto — catálogo adelantado + supuestos de diseño a ratificar | media | **alta** | épica MAPA DE RED activa |
| 9990410 | El circuito no distingue «la cuenta se quedó sin límite» de «este item es demasiado grande»: 36 vueltas culparon al item | alta | **alta** | desbloquea a otros items (3 apuntan a él vía depende_de/origen_item_id) |
| 9990411 | FASE 1+2: detectar causa=limite_cuenta en vuelta.sh y no castigar el item | alta | **alta** | desbloquea a otros items (3 apuntan a él vía depende_de/origen_item_id) |
| 9990412 | FASE 4: pausar el scheduler (no lanzar más vueltas) mientras la cuenta esté sin límite | alta | **alta** | desbloquea a otros items (3 apuntan a él vía depende_de/origen_item_id) |
| 9990413 | FASE 3: visibilidad en la Torre del estado «cuenta sin límite» | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990417 | FASE 4a: expiración opcional en el centinela FrenoCircuito (campo expira_en + autolimpieza) | alta | **alta** | desbloquea a otros items (3 apuntan a él vía depende_de/origen_item_id) |
| 9990421 | [RESPUESTA] MR-15 — Backfill al modelo nuevo — mirror vacío bloquea toda conversión real | media | **alta** | épica MAPA DE RED activa |
| 9990429 | MR-23 fase 4 — acciones nuevas Mover/Trazar/Ver impacto y secciones Fotos/Historial de cambios | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (4 apuntan a él vía depende_de/origen_item_id) |
| 9990445 | MR-25 Fase 4 — Exportadores KML/GeoJSON/carta de empalme PDF/BOM XLSX (D21) | media | **alta** | épica MAPA DE RED activa |
| 9990451 | MR-23 fase 4a — acción 'Trazar' (dibujar y guardar enlace entre 2 nodos) | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990452 | Migrar el motor de comisiones y pagos de vendedor a Talento — EJECUCIÓN (continúa #123) | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990454 | MR-23 fase 4b — acción 'Ver impacto' (clientes/nodos afectados aguas abajo) | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990455 | MR-23 fase 4c — sección 'Fotos' por nodo/enlace del Mapa de Red | media | **alta** | épica MAPA DE RED activa |
| 9990456 | MR-23 fase 4d — sección 'Historial de cambios' por nodo/enlace del Mapa de Red | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (3 apuntan a él vía depende_de/origen_item_id) |
| 9990457 | MR-22 Fase 1 — Buscador global del mapa (nombre/cliente/serie ONT/dirección) | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990460 | MR-22 Fase 4 — Verificación DoD final (apertura <3s con 6 zonas, buscar NAP <5s) y cierre de #958 | media | **alta** | épica MAPA DE RED activa |
| 9990491 | Rebuild del bundle no es atómico: pantallas dan "no se puede pintar" mientras se recompila | media | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990494 | MR-22 Fase 2c — Capas de Drops y Cobertura: no existen hoy como capas geográficas (requiere decisión de diseño antes de UI) | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (3 apuntan a él vía depende_de/origen_item_id) |
| 9990504 | MR-08 Fase 2 — CRUD backend + permisos mapa_red_catalogo_* de los 4 catálogos | alta | **alta** | épica MAPA DE RED activa; desbloquea a otros items (3 apuntan a él vía depende_de/origen_item_id) |
| 9990505 | MR-08 Fase 3 — UI de administración de los 4 catálogos | alta | **alta** | épica MAPA DE RED activa; desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990509 | MR-22 Fase 1a — Endpoint backend de búsqueda global del mapa | media | **alta** | épica MAPA DE RED activa |
| 9990519 | MR-23 fase 4a (2/2) — Frontend: acción 'Trazar' en LeafletMapRed.vue (click secuencial + render inmediato) | media | **alta** | épica MAPA DE RED activa |
| 9990534 | MR-08 Fase 3 — UI de administración de los 4 catálogos (retomar cuando Fase 2 #9990504 esté mergeada a main) | alta | **alta** | épica MAPA DE RED activa |
| 9990536 | MR-08 Fase 2a — CatalogosController: permisos + cables/conectores (index/store/update/destroy) | alta | **alta** | épica MAPA DE RED activa; desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990538 | MR-08 Fase 2b — CatalogosController: cajas/splitters (index/store/update/destroy), completa MR-08 Fase 2 | alta | **alta** | épica MAPA DE RED activa |
| 9990539 | MR-22 Fase 2c-1 — Backend Drops: tabla network_drops (punto lat/lng) + modelo + CRUD | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990542 | MR-22 Fase 2c-3 — Render read-only de capas Drops y Cobertura declarada en el mapa Leaflet | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990543 | MR-22 Fase 2d — Edición visual de Drops/Cobertura + captura automática de Drop al activar cliente | media | **alta** | épica MAPA DE RED activa |
| 9990550 | Documentación Corporativa — fecha de inicio del plazo de 180 días hábiles (columna nueva) | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990555 | MR-23 Fase 4b-i — ImpactoAnalysisService: recorrido recursivo aguas abajo (backend + endpoint + permiso) | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990556 | MR-23 Fase 4b-ii — UI 'Ver impacto': menú contextual + panel Quasar con nodos/clientes afectados | media | **alta** | épica MAPA DE RED activa |
| 9990559 | MR-23 fase 4d-A — tabla mapa_red_historial + modelo + helper de registro + permiso | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990560 | MR-23 fase 4d-B — instrumentar mover/editar/eliminar + endpoint GET de listado | media | **alta** | épica MAPA DE RED activa; desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990561 | MR-23 fase 4d-C — pestaña/sección 'Historial' en ElementSidePanel.vue | media | **alta** | épica MAPA DE RED activa |
| 9990578 | Fase 1 — document_templates: columna status borrador/publicada + updated_by + campo en el CRUD | sin-prioridad | **alta** | desbloquea a otros items (3 apuntan a él vía depende_de/origen_item_id) |
| 9990607 | Fase 3 — Conmutación del origen del pago a TalentoLedgerEntry (FRONTERA DURA DE DINERO, requiere go explícito de Irving) | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990608 | Fase 4 — Migración histórica y limpieza de los modelos dormidos de comisiones de vendedor | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990657 | Fase A verificación en vivo (e): confirmar aislamiento real contra /var/www/megaisp | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990668 | Emisión atómica de versiones: una versión existe si y sólo si está publicada | alta | **alta** | desbloquea a otros items (8 apuntan a él vía depende_de/origen_item_id) |
| 9990674 | F3 — ReleasePublisher: emisión atómica con compensación | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990718 | Provisionador del módulo VoIP: que la actualización deje Asterisk instalado y funcionando | alta | **alta** | desbloquea a otros items (4 apuntan a él vía depende_de/origen_item_id) |
| 9990719 | Divergencia entre items completados y ramas sin integrar | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990721 | permisos | alta | **alta** | desbloquea a otros items (4 apuntan a él vía depende_de/origen_item_id) |
| 9990745 | Fase 4 — inventario y matriz completa de permisos (obligatorio, en paralelo) | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990746 | Documento final docs/auditoria-permisos-2026-09.md + canal de respuesta | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990765 | Fase 4c — Matriz por rol: permisos asignados vs. permisos que surten efecto (punto 4) | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990766 | Fase 4c — cerrar: vista Blade+Vue de la matriz de permisos por rol + correr real + poblar reporte | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990776 | Auditoría de permisos: rol con permisos asignados que no se reflejan en el administrador (caso Supervisor de mostrador → Diana) | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990777 | CheckRoutePermission debe leer permisos de rol, no solo permisos directos | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990778 | Identidad unificada: colaborador único, sellers como puente, migración aditiva de seller_id | alta | **alta** | desbloquea a otros items (4 apuntan a él vía depende_de/origen_item_id) |
| 9990779 | Catálogo único de prospectos: consolidar CRM, Vendedores y Portal Colaborador en una sola fuente | media | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990781 | Catálogos parametrizables: modalidades, modos de pago y parámetros del reglamento | media | **alta** | desbloquea a otros items (4 apuntan a él vía depende_de/origen_item_id) |
| 9990782 | Motor de comisiones sobre talento_ledger_entries (devengo, congelado por venta, sin campos mutables) | media | **alta** | desbloquea a otros items (5 apuntan a él vía depende_de/origen_item_id) |
| 9990783 | Motor de maduración: meta, candado de suficiencia, faltante, netting y reverso por pago tardío | media | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990784 | Convenio prellenado desde plantilla | media | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990785 | Pestañas de Talento según rol de acceso + perfil comercial del colaborador | media | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990786 | Comisión por recuperación de equipo: registro con foto, serie y reingreso a inventario | baja | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990787 | Migrar Embajadores al motor unificado de comisiones (programa, no motor aparte) | media | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990790 | [RESPUESTA] Subir el reglamento de ventas y comisiones al repo — falta el texto fuente de Irving | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990791 | [RESPUESTA] Auditoría de permisos: rol con permisos asignados que no se reflejan en el administrador (caso Supervisor de mostrador → Diana) — decidir corrección + evitar duplicar #9990721 | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990792 | Expediente digital del colaborador: tipos de documento, bloqueo proporcional y firma en pantalla | alta | **alta** | desbloquea a otros items (6 apuntan a él vía depende_de/origen_item_id) |
| 9990793 | Manuales y cursos obligatorios por puesto sobre la Academia de Talento existente | media | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990794 | Escalafón: avance documental y académico → propuesta de incremento y mejora de comisión | media | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990798 | Calculadora de comisiones: servicio puro con los casos TC1–TC12 como tests unitarios | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990803 | Clientes: el buscador del listado debe buscar únicamente en las columnas visibles de cada administrador | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990805 | Firma en pantalla con metadata legal completa (IP, dispositivo, hash, trazos) y hoja de constancia en el PDF | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990806 | Reapertura automática de acuses al cambiar de versión de plantilla | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990807 | Pantalla del colaborador (pendientes/firmados) + tablero de pendientes para Irving + endpoints API-first | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990810 | Cerrar VoIP: de dev hasta que suene el teléfono en producción | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990815 | Fase 4 — Medición p50/p95, criterios de aceptación y encendido del flag en dev | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990816 | Tablero admin para Irving: qué colaborador tiene qué documento pendiente (con antigüedad + recordar) | alta | **alta** | desbloquea a otros items (3 apuntan a él vía depende_de/origen_item_id) |
| 9990817 | Reapertura de acuses — verificación end-to-end con datos sintéticos (rollback) | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990826 | Reapertura de acuses — implementar y correr el test E2E (Feature, DB de test) | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990854 | corrección del Circuito CC — para desatorar el flujo | media | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990856 | El comentario de Irving en un item requiere_irving es la respuesta: hilo que no se pisa + re-encolado automático (PARAGUAS) | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990858 | CIRC-02b Mecanismo: hilo de respuestas (roadmap_item_respuestas) + re-encolado automático | alta | **alta** | frente de cierre CIRC-0x del propio circuito; desbloquea a otros items (5 apuntan a él vía depende_de/origen_item_id) |
| 9990859 | CIRC-02c Visibilidad: hilo de respuestas en la Torre + watchdog de respuestas sin consumir | media | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9990860 | Bandeja de decisiones: distinguir el item que espera una decisión del que espera un insumo material de Irving | alta | **alta** | desbloquea a otros items (4 apuntan a él vía depende_de/origen_item_id) |
| 9990861 | Re-triaje de la cola: prioridad real, limpieza de basura y clasificación de los 430 items sin nivel de riesgo | media | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990863 | Terminales ociosas con items aprobados: por qué el despachador no asigna y por qué "Listos para terminal" miente | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990870 | CIRC-04 — Re-triaje de la cola: coherencia cancelados, basura y 430 items sin nivel de riesgo | media | **alta** | frente de cierre CIRC-0x del propio circuito; desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990871 | CIRC-05 — API externa roadmap-externo: creación de items (token RC) + hilo unificado de reportes | media | **alta** | frente de cierre CIRC-0x del propio circuito; desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990874 | Items de corrección del Circuito CC — para desatorar el flujo | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990878 | Identidad unificada — Fase 3: doble escritura de colaborador_id junto a seller_id | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990887 | CIRC-04: Re-triaje de la cola — prioridad real, limpieza de basura y clasificación de los 430 items sin nivel de riesgo | alta | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9990888 | CIRC-05: Extender la API externa roadmap-externo — creación de items con token propio (RC) + hilo de reportes que no se pisa | alta | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9990893 | Fase 3 (C2) — que 'Listos para terminal' diga la verdad + contador de vueltas quemadas + cablear #9990798 | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990900 | CIRC-02b PASO 2 — Disparo: comentario humano sobre requiere_irving crea respuesta + re-encola | alta | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9990901 | CIRC-02b PASO 3 — Inyección de la respuesta en el prompt con precedencia | alta | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9990902 | CIRC-02b PASO 4 — Pruebas end-to-end de los 3 pasos anteriores | alta | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9990903 | [RESPUESTA] CIRC-02b — 3 cadenas duplicadas del mismo trabajo (CIRC-02a/b/c) | media | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9990910 | Migración aditiva motivo_espera en roadmap_items + proyección en COLUMNAS_BANDEJA | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990923 | Fase 3a — SupervisorService: excluir de 'Listos para terminal' items con depende_de sin resolver o motivo_espera activo | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990933 | CIRC-02 · El comentario de Irving en un item requiere_irving es la respuesta: | media | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9990934 | CIRC-09 · Panorama en árbol (y baja de tres pestañas) | media | **alta** | frente de cierre CIRC-0x del propio circuito; desbloquea a otros items (3 apuntan a él vía depende_de/origen_item_id) |
| 9990937 | CIRC-03 · Bandeja de decisiones real: separar "espera decisión" de "espera insumo" | media | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9990938 | Fase 3a-i — depende_de: filtrar 'Listos para terminal' con el mismo criterio MR-36 de RoadmapCircuitoService | alta | **alta** | desbloquea a otros items (1 apuntan a él vía depende_de/origen_item_id) |
| 9990944 | Fase B — SupervisorService: aplicar filtrarConDependenciasCerradas() en listosParaTerminal()/listosParaTerminalTotal() | alta | **alta** | desbloquea a otros items (2 apuntan a él vía depende_de/origen_item_id) |
| 9990971 | CIRC-09 Fase 4 - Paneles desplegables de lectura: Trabajando y Decidido sin ti | media | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9990973 | CIRC-09 Fase 5 - Panel de decision con placeholder (sin esperar a CIRC-02b) | media | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9990974 | CIRC-09 Fase 6 - Acciones por estado detras de permiso Spatie + refresco en vivo sin perder scroll | media | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9991009 | CIRC-04 PASO 2 — Reportar items sin contenido ejecutable (basura), incluye #185 | media | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9991011 | CIRC-04 PASO 4 — Propuesta de re-priorización de la cola viva (doc en docs/, NO aplicar el cambio) | media | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9991015 | CIRC-05 — rate limit dedicado de creación (10/min + tope 60/hora), separado del rate_write compartido | media | **alta** | frente de cierre CIRC-0x del propio circuito |
| 9991016 | CIRC-05 — guardrail de entorno (PASO 6): verificar/documentar que roadmap-externo no queda montada en prod + validar host de la petición | media | **alta** | frente de cierre CIRC-0x del propio circuito |
| 72 | Greenfield: configurar Firebase desde cero + integrar FCM HTTP v1 en MegaFamilia | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 82 | Verificar supuestos del protocolo Ruptela con GPS físico real (⚠️ VERIFY de Sub-fase 2.3a) | media | **media** | vigente sin condiciones de alta ni de baja |
| 149 | Portal: cobro/tarifas premium MegaFamilia | media | **media** | vigente sin condiciones de alta ni de baja |
| 170 | Circuito: freno de mano fuera de la BD (centinela en archivo + isPaused fail-closed) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 190 | Más items | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 227 | [INFRA] Exentar de la detección de DOS del MikroTik el tráfico a dst=38.123.192.199 dport=443 | media | **media** | vigente sin condiciones de alta ni de baja |
| 230 | megaisp_test no se puede construir sólo con migraciones: queda en 236 tablas de 502 | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 231 | Podar los worktrees muertos: 3 no se pueden mergear y 5 llevan más de 700 commits de atraso | media | **media** | vigente sin condiciones de alta ni de baja |
| 625 | MegaFamilia/Portal: flujo OTP real de login (hoy solo existe el toggle sin flujo) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 635 | Portal Cliente: ejecutar migración a bcrypt (Opción B) — decisión de diseño | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 638 | Vista Hijo APK: flujo de vinculación/login del hijo en megafamilia-rn | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 652 | Bandeja de Irving en lenguaje natural: resumen legible arriba, lo técnico en un desplegable | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 662 | DocumentaciónCorporativa — Fase 0: cimiento del addon (catálogo, permisos, bitácora, tablero) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 671 | Implementar ZteDriver real (lectura + provisioning básico) — bloqueado por hardware piloto | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 679 | Ola escalonada: convertir las 4043 cuentas no-privilegiadas con password legacy base64 | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 680 | Retirar el fallback legacy de PasswordService::check() una vez agotada la ola no-privilegiada | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 683 | PAC CFDI 4.0 — integrar Facturama real (bloqueado por credenciales de Irving) | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 687 | Flotas — Asistente conversacional sobre la flota | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 691 | Implementar cálculo real de cuota Medussa por transacción conciliada (BLOQUEADO sin autorización) | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 692 | OLT Huawei Fase A — sesión presencial con Irving: validar authorizeOnu/deauthorizeOnu/setOnuEnabled reales en lab | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 718 | Decidir alcance/plataforma de tiempo de pantalla real (Vista Hijo) y ejecutar cuando exista worktree para megafamilia-rn | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 724 | Unificación invoices/client_invoices — Fase 5: ventana de monitoreo en prod con dual-write ON | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 725 | Unificación invoices/client_invoices — Fase 6: cortar escritura primaria a invoices (5 subsistemas) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 726 | Unificación invoices/client_invoices — Fase 7: decommission client_invoices | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 754 | Fase 4 (real): migrar KpiController/HomeController/AuditController a leer invoices — bloqueado hasta #719/#721/#722 en main | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 788 | Cablear FlotasProrrateoService en ClientRepository::resolveFleetSubscriptionLines() tras el merge de #720 | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 842 | Item 2 — Catálogo completo de permisos por módulo (nivel B) | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990013 | Endurecer el punto exacto que dejó pasar a #32 sin merge (según causa confirmada en el sub-item de reproducción) + test de regresión | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 9990075 | [INFRA] Hook DNS-01 automatizado para dev.meganett.com.mx (bloqueado: falta credencial + delegación NS) | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 9990263 | Fase 1b — SubItemCommand: --depende-de + position + integración con DependenciaGate::tieneCiclo() | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 9990288 | MR-33 — Potencia de referencia por caja e histórico de mediciones | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990289 | MR-34 — Motor de bonos de calidad de red hacia Talento | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990290 | MR-35 — Sentido de la luz animado en el mapa | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990350 | Guard explícito de path duro en backups:purge-test (storage/backup_test) | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 9990359 | Hijo E2 — Regenerar y Subir escaneado firmado con congelamiento de versión | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990361 | Hijo E4 — acceso Documentos en perfil de Colaborador de Talento (modal reusando componente, sin ficha con tabs) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990415 | FASE 1: vuelta.sh detecta causa=limite_cuenta (grep 'session limit') | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990418 | FASE 4b: enganchar el freno-con-expiración en vuelta.sh cuando causa=limite_cuenta | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990419 | Backend: exponer causa/expira_en del freno en pausedInfo() cuando lo puso #9990412 (limite_cuenta) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990420 | Frontend: banner distintivo 'Cuenta de Claude sin límite hasta HH:MM' en TorreControl.vue | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990424 | FASE 4a-1: FrenoCircuito::poner() con expira_en opcional + método expirado() | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990425 | FASE 4a-2: autolimpieza en isPaused() + candado de regresión (3 casos) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990571 | Build atómico — aplicar el mismo patrón al deploy de PROD (RemoteDeployCommand npm_build) [FRONTERA DURA — requiere Irving] | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990576 | DC plazo 180d hábiles — Fase 4 (futura, explícitamente fuera de esta iteración): alertas por proximidad al vencimiento | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990584 | Fase 1a — Migración: document_templates.status + updated_by + backfill | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 9990585 | Fase 1b — Modelo DocumentTemplate: fillable status + updated_by | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 9990586 | Fase 1c — CRUD: select Borrador/Publicada en form de plantilla + validación | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 9990600 | FRONTERA DURA — Crear cuenta personal DESARROLLADOR para David (decisión de Irving) | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990612 | Fase 4a — Migración histórica (72 pagos + 39 reglas) con dry-run y conciliación al centavo, legacy intacto | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990613 | Fase 4b — Deprecar (no borrar) los modelos dormidos de comisiones de vendedor tras ventana de operación estable | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990629 | Seguimiento: pregunta sin resolver de #272 | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 9990630 | Seguimiento: pregunta sin resolver de #274 | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 9990631 | Seguimiento: pregunta sin resolver de #285 | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 9990635 | Versionado dev↔prod: el consecutivo salía de la tabla releases y retrocedía (RESUELTO Fases 1+3) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990642 | Acceso de www-data a GitHub (deploy key READ-ONLY) para el resolver de versión | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990659 | Fase B — verificacion en vivo REAL contra /var/www/megaisp (rama ajena a mano), requiere sesion supervisada | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990667 | Voz Mayorista — Sprint 1: plano de control comercial (bloque 1: esqueleto, blindaje de instancia y permisos) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990670 | SEG-1 — Rotar/asegurar el token de GitHub antes de que el path web lo use en F1 | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990671 | F1 — Estado de publicación visible por versión en el historial de la Torre | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990672 | F1·B — La pantalla de actualización de producción muestra el salto completo y acumulado | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990675 | F4 — Candado en base de datos: no puede existir una versión sin Release publicado | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990677 | F6 — Reconciliar la historia: publicar retroactivo o marcar histórica | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990678 | F7 — Dejar la regla escrita y separar el permiso de emitir | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990679 | F8 — Emitir V1.35 con el motor nuevo: la prueba de fuego | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990713 | Empaquetar Asterisk como .deb propio y montar el repositorio APT de Meganet | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990715 | Voz Fase 2 — Sanear el esquema realtime: volver a Alembic estándar y no parchear tablas de Asterisk | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990723 | Fase 2 — Correcciones mínimas del módulo VoIP (sección 6) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990725 | Fase 4 — Extensiones sembradas por departamento (sección 8) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990727 | Fase 6 — El provisionador de Asterisk + especificación de compilación (secciones 3 y 4) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990728 | Fase 7 — Prueba obligatoria de punta a punta en dev + cierre del paraguas #9990718 | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990729 | F3 (#9990674): ejecutar a mano el ensayo real de red cortada (push+GitHub Release TEST-) | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990730 | Implementar auditoría periódica + gate en el cierre para items completado sin merge (decisión de #9990719) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990735 | [RESPUESTA] Voz Fase 3 — Cerrar la seguridad: Asterisk sin root, sin directorio 777 y sin código muerto — falta correr runbook del directorio 777 residual | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990743 | Fase 0+1 — diagnóstico confirmado (transacción+rollback): diff SUPERVISOR_MOSTRADOR vs Diana | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990744 | Fase 2/3 — verificar hipótesis H1-H10 según la rama de Fase 1 | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990767 | Fase 4c-i — mergear rama backend existente + migrar + vista Blade+Vue de la matriz de permisos por rol | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990768 | Fase 4c-ii — correr auditoria:permisos-matriz-roles contra BD real de dev y cerrar el item padre #9990765 | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990788 | Apagado del módulo Vendedores y limpieza de la tabla puente sellers | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990797 | Ejecutar fix de Diana: asignar rol SUPERVISOR_MOSTRADOR + resync (bloqueado hasta que cierre #9990746) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990808 | Motor de ventas: catálogos backend (migraciones + modelos + permisos + CRUD API) — Fase 1 de #9990781 | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990809 | Motor de ventas: UI CRUD de catálogos (modalidades/modos de pago/parámetros reglamento) — Fase 2 de #9990781 | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990820 | Fase 3 de #9990805: regla especial del Convenio de Comisiones (escaneado + estado 'parcial') | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990825 | VoIP Fase 3 — emitir version, publicar GitHub Release y documentar el runbook de despliegue | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990827 | Reapertura de acuses — cerrar #9990806 con reporte_coloquial + enlace_revision | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990830 | Tablero pendientes Fase 1 — permiso talento.documentos.ver-todos + endpoint admin GET /talento/api/documentos/pendientes | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990831 | Tablero pendientes Fase 2 — endpoint POST /talento/api/documentos/{docId}/recordar (WhatsApp, reusa WhatsAppGateway compartido) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990832 | Tablero pendientes Fase 3 — pantalla admin del tablero (tabla + KPIs + boton Recordar con modal) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990834 | Reapertura de acuses — implementar y pasar ReaperturaAcusesTest.php (happy path + negativo + 2 bordes) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990839 | Fase 4a — Medir p50/p95 real (nombre vs SN) y repasar los 10 criterios de aceptación de #9990803 | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990840 | Fase 4b — Encender CLIENTES_BUSQUEDA_V2 en dev + config:auditar-env/cache + cierre de #9990803 | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990853 | corrección del Circuito CC — para desatorar el flujo | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990862 | API roadmap-externo: creación de items con token propio (RC) + hilo de reportes que no se pisa | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990876 | PASO 4 de #9990861: propuesta de re-priorización de la cola viva (docs/roadmap/, NO aplicar) | media | **media** | vigente sin condiciones de alta ni de baja |
| 9990879 | Identidad unificada — Fase 4: corte de lectura de seller_id a colaborador_id, módulo por módulo | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990894 | Fase 4 (C3/C4/C5) — leer el log de despacho y decidir tope de concurrencia/frenos según lo que diga | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990911 | Backfill motivo_espera para los 10 items ya identificados por Irving (aprobado_irving + excluir_pool ya en true) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990912 | Torre — bandeja partida en dos listas (Esperan tu decisión / Esperan un insumo tuyo) + ajuste de métricas | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990913 | Barrido de aprobado_irving por texto de bloqueo (candidatos adicionales a motivo_espera) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990924 | Fase 3b — SupervisorService: ocultar de 'Listos para terminal' items con bloqueo declarado en el prompt (heurística de texto) | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990927 | Proyectar motivo_espera en RoadmapController::COLUMNAS_BANDEJA + verificación torre() | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990945 | Seguimiento: pregunta sin resolver de #9990882 | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 9990954 | Fase B1 — SupervisorService::listosParaTerminal(): aplicar filtrarConDependenciasCerradas() | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990957 | Fase B2 — SupervisorService::listosParaTerminalTotal(): aplicar filtrarConDependenciasCerradas() + verificación final | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9990965 | Fase 3d: activar el flag de doble escritura en dev + validación end-to-end + cerrar #9990878 | alta | **media** | vigente pero no está en frente de cierre del mes (Mapa de Red/CIRC-0x) ni desbloquea otros items — no justifica alta |
| 9991001 | Seguimiento: pregunta sin resolver de #9990968 | sin-prioridad | **media** | vigente sin prioridad asignada; no cumple criterio de alta, se fija media por default |
| 155 | client_main_information.user: normalizar formato de número de cliente (padding 004981 vs 4981) | baja | **baja** | vigente, sin criterio de alta, se conserva su baja actual (sin señal de fecha/urgencia) |
| 9990789 | Cambiar el concepto del CFDI al suspenderse el servicio: de servicio de Internet a renta de equipo en comodato | baja | **baja** | vigente, sin criterio de alta, se conserva su baja actual (sin señal de fecha/urgencia) |
| 9990795 | App del colaborador (PWA o nativa): firma, documentos, cursos y operación en campo | baja | **baja** | vigente, sin criterio de alta, se conserva su baja actual (sin señal de fecha/urgencia) |

## Siguiente paso

Este documento es solo la propuesta. Si Irving aprueba la tabla (con o sin ajustes manuales), el cambio real de `priority` se ejecuta en un item aparte que lea esta tabla y aplique el UPDATE fila por fila — no aquí, y no automáticamente.

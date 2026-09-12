# Propuesta de re-priorizacion de la cola viva (20260911)

Continuacion de #9990861 PASO 4 (via sub-item #9990876). Snapshot generado el 2026-09-11 20:54:20 -- la cola es viva, los conteos cambiaran; esto es una PROPUESTA, NO se aplico nada a la BD.

## Metodologia

- alta = target_version poblado O el item aparece en depende_de de otro item vivo (desbloquea a otros).
- baja = sin esas senales Y su prioridad actual ya es baja (se conserva, nunca se degrada nada).
- media = todo lo demas (incluye los items sin prioridad previa).

## Hallazgo de calidad de datos

- 36 de 247 items vivos no tienen prioridad asignada (NULL).
- Solo 5 de 247 items vivos tienen target_version poblado, y esos valores no siguen el esquema real de la tabla releases (ejemplo de release real reciente: V1.34-09.09.2026) -- la senal de 'frente de cierre del mes' es casi inexistente hoy.

## Propuesta: alta (36 items)

| ID | titulo | prioridad actual | propuesta | razon |
|---|---|---|---|---|
| 19 | Cerrar modulo MegaFamilia contra checklist (bloqueado por infra Padre-Hijo y motor de servicios) | media | alta | target_version=v1.0 |
| 30 | Vista Hijo en la APK (perfil restringido segun permisos del padre) | media | alta | target_version=v1.0 |
| 66 | Flotas Fase 7 — IA agrupada (OCR, prediccion, asistente conversacional) | baja | alta | target_version=v1.2 |
| 72 | Greenfield: configurar Firebase desde cero + integrar FCM HTTP v1 en MegaFamilia | alta | alta | target_version=v0.9 |
| 82 | Verificar supuestos del protocolo Ruptela con GPS físico real (⚠️ VERIFY de Sub-fase 2.3a) | media | alta | target_version=v0.9 |
| 9990411 | FASE 1+2: detectar causa=limite_cuenta en vuelta.sh y no castigar el item | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990412 | FASE 4: pausar el scheduler (no lanzar más vueltas) mientras la cuenta esté sin límite | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990417 | FASE 4a: expiración opcional en el centinela FrenoCircuito (campo expira_en + autolimpieza) | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990504 | MR-08 Fase 2 — CRUD backend + permisos mapa_red_catalogo_* de los 4 catálogos | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990536 | MR-08 Fase 2a — CatalogosController: permisos + cables/conectores (index/store/update/destroy) | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990539 | MR-22 Fase 2c-1 — Backend Drops: tabla network_drops (punto lat/lng) + modelo + CRUD | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990542 | MR-22 Fase 2c-3 — Render read-only de capas Drops y Cobertura declarada en el mapa Leaflet | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990555 | MR-23 Fase 4b-i — ImpactoAnalysisService: recorrido recursivo aguas abajo (backend + endpoint + permiso) | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990559 | MR-23 fase 4d-A — tabla mapa_red_historial + modelo + helper de registro + permiso | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990560 | MR-23 fase 4d-B — instrumentar mover/editar/eliminar + endpoint GET de listado | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990607 | Fase 3 — Conmutación del origen del pago a TalentoLedgerEntry (FRONTERA DURA DE DINERO, requiere go explícito de Irving) | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990746 | Documento final docs/auditoria-permisos-2026-09.md + canal de respuesta | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990776 | Auditoría de permisos: rol con permisos asignados que no se reflejan en el administrador (caso Supervisor de mostrador → Diana) | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990777 | CheckRoutePermission debe leer permisos de rol, no solo permisos directos | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990778 | Identidad unificada: colaborador único, sellers como puente, migración aditiva de seller_id | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990779 | Catálogo único de prospectos: consolidar CRM, Vendedores y Portal Colaborador en una sola fuente | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990781 | Catálogos parametrizables: modalidades, modos de pago y parámetros del reglamento | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990782 | Motor de comisiones sobre talento_ledger_entries (devengo, congelado por venta, sin campos mutables) | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990783 | Motor de maduración: meta, candado de suficiencia, faltante, netting y reverso por pago tardío | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990784 | Convenio prellenado desde plantilla | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990785 | Pestañas de Talento según rol de acceso + perfil comercial del colaborador | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990786 | Comisión por recuperación de equipo: registro con foto, serie y reingreso a inventario | baja | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990787 | Migrar Embajadores al motor unificado de comisiones (programa, no motor aparte) | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990790 | [RESPUESTA] Subir el reglamento de ventas y comisiones al repo — falta el texto fuente de Irving | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990792 | Expediente digital del colaborador: tipos de documento, bloqueo proporcional y firma en pantalla | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990793 | Manuales y cursos obligatorios por puesto sobre la Academia de Talento existente | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990794 | Escalafón: avance documental y académico → propuesta de incremento y mejora de comisión | media | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990798 | Calculadora de comisiones: servicio puro con los casos TC1–TC12 como tests unitarios | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990826 | Reapertura de acuses — implementar y correr el test E2E (Feature, DB de test) | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990858 | CIRC-02b Mecanismo: hilo de respuestas (roadmap_item_respuestas) + re-encolado automático | alta | alta | desbloquea a otro(s) item(s) vivo(s) |
| 9990923 | Fase 3a — SupervisorService: excluir de 'Listos para terminal' items con depende_de sin resolver o motivo_espera activo | alta | alta | desbloquea a otro(s) item(s) vivo(s) |

## Propuesta: media (207 items)

| ID | titulo | prioridad actual | propuesta | razon |
|---|---|---|---|---|
| 117 | Integración PAC para CFDI 4.0 (factura fiscal) | alta | media | sin señales adicionales; se conserva media |
| 149 | Portal: cobro/tarifas premium MegaFamilia | media | media | sin señales adicionales; se conserva media |
| 154 | Portal Cliente: contraseñas en texto plano — evaluar migración a bcrypt (Opción B) | media | media | sin señales adicionales; se conserva media |
| 159 | Deuda: dos tablas de facturas (invoices vs client_invoices) | media | media | sin señales adicionales; se conserva media |
| 170 | Circuito: freno de mano fuera de la BD (centinela en archivo + isPaused fail-closed) | alta | media | sin señales adicionales; se conserva media |
| 185 | Más 8tems | alta | media | sin señales adicionales; se conserva media |
| 190 | Más items | alta | media | sin señales adicionales; se conserva media |
| 191 | Expediente digital del personal: plantillas por puesto y generación al alta | alta | media | sin señales adicionales; se conserva media |
| 203 | Expediente RH — Hijo E: pestana Documentos en perfil colaborador y vendedor | alta | media | sin señales adicionales; se conserva media |
| 226 | [INFRA] Renovar el certificado de dev.meganett.com.mx con hook DNS-01 automatizado | alta | media | sin señales adicionales; se conserva media |
| 227 | [INFRA] Exentar de la detección de DOS del MikroTik el tráfico a dst=38.123.192.199 dport=443 | media | media | sin señales adicionales; se conserva media |
| 230 | megaisp_test no se puede construir sólo con migraciones: queda en 236 tablas de 502 | alta | media | sin señales adicionales; se conserva media |
| 231 | Podar los worktrees muertos: 3 no se pueden mergear y 5 llevan más de 700 commits de atraso | media | media | sin señales adicionales; se conserva media |
| 281 | OLT Huawei — cerrar escritura real en laboratorio (alta/baja/suspensión de ONU, Fase A+B) | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 283 | Decisión de negocio: ¿priorizar driver ZTE y/o V-SOL? confirmar acceso a hardware piloto | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 625 | MegaFamilia/Portal: flujo OTP real de login (hoy solo existe el toggle sin flujo) | alta | media | sin señales adicionales; se conserva media |
| 629 | Ramas huerfanas Pieza C (descomposicion+watchdog+DependenciaGate, jul-11) — evaluar rescate del gap real | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 632 | Decidir y ejecutar la unificación invoices/client_invoices (dinero en vivo — nivel C) | alta | media | sin señales adicionales; se conserva media |
| 635 | Portal Cliente: ejecutar migración a bcrypt (Opción B) — decisión de diseño | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 638 | Vista Hijo APK: flujo de vinculación/login del hijo en megafamilia-rn | alta | media | sin señales adicionales; se conserva media |
| 639 | Vista Hijo APK: reemplazar mocks de Logros/Apps permitidas/Tiempo de pantalla por datos reales | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 652 | Bandeja de Irving en lenguaje natural: resumen legible arriba, lo técnico en un desplegable | alta | media | sin señales adicionales; se conserva media |
| 661 | Tiempo de pantalla real en la Vista Hijo: tracking nativo Android (decisión de alcance/plataforma pendiente con Irving) | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 662 | DocumentaciónCorporativa — Fase 0: cimiento del addon (catálogo, permisos, bitácora, tablero) | alta | media | sin señales adicionales; se conserva media |
| 671 | Implementar ZteDriver real (lectura + provisioning básico) — bloqueado por hardware piloto | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 679 | Ola escalonada: convertir las 4043 cuentas no-privilegiadas con password legacy base64 | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 680 | Retirar el fallback legacy de PasswordService::check() una vez agotada la ola no-privilegiada | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 683 | PAC CFDI 4.0 — integrar Facturama real (bloqueado por credenciales de Irving) | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 687 | Flotas — Asistente conversacional sobre la flota | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 691 | Implementar cálculo real de cuota Medussa por transacción conciliada (BLOQUEADO sin autorización) | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 692 | OLT Huawei Fase A — sesión presencial con Irving: validar authorizeOnu/deauthorizeOnu/setOnuEnabled reales en lab | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 718 | Decidir alcance/plataforma de tiempo de pantalla real (Vista Hijo) y ejecutar cuando exista worktree para megafamilia-rn | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 723 | Unificación invoices/client_invoices — Fase 4: migrar lectores de bajo riesgo a invoices | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 724 | Unificación invoices/client_invoices — Fase 5: ventana de monitoreo en prod con dual-write ON | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 725 | Unificación invoices/client_invoices — Fase 6: cortar escritura primaria a invoices (5 subsistemas) | alta | media | sin señales adicionales; se conserva media |
| 726 | Unificación invoices/client_invoices — Fase 7: decommission client_invoices | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 754 | Fase 4 (real): migrar KpiController/HomeController/AuditController a leer invoices — bloqueado hasta #719/#721/#722 en main | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 755 | Flotas SaaS: prorrateo diario por vehículo en la línea de facturación | alta | media | sin señales adicionales; se conserva media |
| 788 | Cablear FlotasProrrateoService en ClientRepository::resolveFleetSubscriptionLines() tras el merge de #720 | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 842 | Item 2 — Catálogo completo de permisos por módulo (nivel B) | media | media | sin señales adicionales; se conserva media |
| 924 | Root-cause: paraguas cierre-en-cascada dejó pasar un nivel-C sin merge a 'completado' (item #32) | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 936 | MR-00 — Épica: módulo MAPA DE RED (convivencia con Mapas, migración y corte) | alta | media | sin señales adicionales; se conserva media |
| 944 | MR-08 — Catálogos: tipos de cable, tipos de splitter, tipos de caja, conectores | alta | media | sin señales adicionales; se conserva media |
| 958 | MR-22 — Nueva navegación: buscador, filtros por capa, clustering, render por zoom | media | media | sin señales adicionales; se conserva media |
| 959 | MR-23 — Ficha lateral del elemento (reemplaza los 5 iconos por fila) | media | media | sin señales adicionales; se conserva media |
| 961 | MR-25 — Importador KML/KMZ + GeoJSON + CSV y exportadores | media | media | sin señales adicionales; se conserva media |
| 9990013 | Endurecer el punto exacto que dejó pasar a #32 sin merge (según causa confirmada en el sub-item de reproducción) + test de regresión | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990075 | [INFRA] Hook DNS-01 automatizado para dev.meganett.com.mx (bloqueado: falta credencial + delegación NS) | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990228 | Cablear DependenciaGate al scheduler vivo + circuito:sub-item --depende-de | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990260 | Fase 1 — circuito:sub-item --depende-de + posición + detección de ciclos | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990263 | Fase 1b — SubItemCommand: --depende-de + position + integración con DependenciaGate::tieneCiclo() | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990288 | MR-33 — Potencia de referencia por caja e histórico de mediciones | media | media | sin señales adicionales; se conserva media |
| 9990289 | MR-34 — Motor de bonos de calidad de red hacia Talento | media | media | sin señales adicionales; se conserva media |
| 9990290 | MR-35 — Sentido de la luz animado en el mapa | media | media | sin señales adicionales; se conserva media |
| 9990347 | Seguimiento: pregunta sin resolver de #146 | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990350 | Guard explícito de path duro en backups:purge-test (storage/backup_test) | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990359 | Hijo E2 — Regenerar y Subir escaneado firmado con congelamiento de versión | alta | media | sin señales adicionales; se conserva media |
| 9990361 | Hijo E4 — acceso Documentos en perfil de Colaborador de Talento (modal reusando componente, sin ficha con tabs) | alta | media | sin señales adicionales; se conserva media |
| 9990409 | [RESPUESTA] MR-13 — Splitter como objeto — catálogo adelantado + supuestos de diseño a ratificar | media | media | sin señales adicionales; se conserva media |
| 9990410 | El circuito no distingue «la cuenta se quedó sin límite» de «este item es demasiado grande»: 36 vueltas culparon al item | alta | media | sin señales adicionales; se conserva media |
| 9990413 | FASE 3: visibilidad en la Torre del estado «cuenta sin límite» | alta | media | sin señales adicionales; se conserva media |
| 9990415 | FASE 1: vuelta.sh detecta causa=limite_cuenta (grep 'session limit') | alta | media | sin señales adicionales; se conserva media |
| 9990418 | FASE 4b: enganchar el freno-con-expiración en vuelta.sh cuando causa=limite_cuenta | alta | media | sin señales adicionales; se conserva media |
| 9990419 | Backend: exponer causa/expira_en del freno en pausedInfo() cuando lo puso #9990412 (limite_cuenta) | alta | media | sin señales adicionales; se conserva media |
| 9990420 | Frontend: banner distintivo 'Cuenta de Claude sin límite hasta HH:MM' en TorreControl.vue | alta | media | sin señales adicionales; se conserva media |
| 9990421 | [RESPUESTA] MR-15 — Backfill al modelo nuevo — mirror vacío bloquea toda conversión real | media | media | sin señales adicionales; se conserva media |
| 9990424 | FASE 4a-1: FrenoCircuito::poner() con expira_en opcional + método expirado() | alta | media | sin señales adicionales; se conserva media |
| 9990425 | FASE 4a-2: autolimpieza en isPaused() + candado de regresión (3 casos) | alta | media | sin señales adicionales; se conserva media |
| 9990429 | MR-23 fase 4 — acciones nuevas Mover/Trazar/Ver impacto y secciones Fotos/Historial de cambios | media | media | sin señales adicionales; se conserva media |
| 9990445 | MR-25 Fase 4 — Exportadores KML/GeoJSON/carta de empalme PDF/BOM XLSX (D21) | media | media | sin señales adicionales; se conserva media |
| 9990451 | MR-23 fase 4a — acción 'Trazar' (dibujar y guardar enlace entre 2 nodos) | media | media | sin señales adicionales; se conserva media |
| 9990452 | Migrar el motor de comisiones y pagos de vendedor a Talento — EJECUCIÓN (continúa #123) | alta | media | sin señales adicionales; se conserva media |
| 9990454 | MR-23 fase 4b — acción 'Ver impacto' (clientes/nodos afectados aguas abajo) | media | media | sin señales adicionales; se conserva media |
| 9990455 | MR-23 fase 4c — sección 'Fotos' por nodo/enlace del Mapa de Red | media | media | sin señales adicionales; se conserva media |
| 9990456 | MR-23 fase 4d — sección 'Historial de cambios' por nodo/enlace del Mapa de Red | media | media | sin señales adicionales; se conserva media |
| 9990457 | MR-22 Fase 1 — Buscador global del mapa (nombre/cliente/serie ONT/dirección) | media | media | sin señales adicionales; se conserva media |
| 9990460 | MR-22 Fase 4 — Verificación DoD final (apertura <3s con 6 zonas, buscar NAP <5s) y cierre de #958 | media | media | sin señales adicionales; se conserva media |
| 9990491 | Rebuild del bundle no es atómico: pantallas dan "no se puede pintar" mientras se recompila | media | media | sin señales adicionales; se conserva media |
| 9990494 | MR-22 Fase 2c — Capas de Drops y Cobertura: no existen hoy como capas geográficas (requiere decisión de diseño antes de UI) | media | media | sin señales adicionales; se conserva media |
| 9990505 | MR-08 Fase 3 — UI de administración de los 4 catálogos | alta | media | sin señales adicionales; se conserva media |
| 9990509 | MR-22 Fase 1a — Endpoint backend de búsqueda global del mapa | media | media | sin señales adicionales; se conserva media |
| 9990519 | MR-23 fase 4a (2/2) — Frontend: acción 'Trazar' en LeafletMapRed.vue (click secuencial + render inmediato) | media | media | sin señales adicionales; se conserva media |
| 9990534 | MR-08 Fase 3 — UI de administración de los 4 catálogos (retomar cuando Fase 2 #9990504 esté mergeada a main) | alta | media | sin señales adicionales; se conserva media |
| 9990538 | MR-08 Fase 2b — CatalogosController: cajas/splitters (index/store/update/destroy), completa MR-08 Fase 2 | alta | media | sin señales adicionales; se conserva media |
| 9990543 | MR-22 Fase 2d — Edición visual de Drops/Cobertura + captura automática de Drop al activar cliente | media | media | sin señales adicionales; se conserva media |
| 9990550 | Documentación Corporativa — fecha de inicio del plazo de 180 días hábiles (columna nueva) | alta | media | sin señales adicionales; se conserva media |
| 9990556 | MR-23 Fase 4b-ii — UI 'Ver impacto': menú contextual + panel Quasar con nodos/clientes afectados | media | media | sin señales adicionales; se conserva media |
| 9990561 | MR-23 fase 4d-C — pestaña/sección 'Historial' en ElementSidePanel.vue | media | media | sin señales adicionales; se conserva media |
| 9990571 | Build atómico — aplicar el mismo patrón al deploy de PROD (RemoteDeployCommand npm_build) [FRONTERA DURA — requiere Irving] | media | media | sin señales adicionales; se conserva media |
| 9990576 | DC plazo 180d hábiles — Fase 4 (futura, explícitamente fuera de esta iteración): alertas por proximidad al vencimiento | alta | media | sin señales adicionales; se conserva media |
| 9990578 | Fase 1 — document_templates: columna status borrador/publicada + updated_by + campo en el CRUD | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990584 | Fase 1a — Migración: document_templates.status + updated_by + backfill | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990585 | Fase 1b — Modelo DocumentTemplate: fillable status + updated_by | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990586 | Fase 1c — CRUD: select Borrador/Publicada en form de plantilla + validación | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990600 | FRONTERA DURA — Crear cuenta personal DESARROLLADOR para David (decisión de Irving) | media | media | sin señales adicionales; se conserva media |
| 9990608 | Fase 4 — Migración histórica y limpieza de los modelos dormidos de comisiones de vendedor | alta | media | sin señales adicionales; se conserva media |
| 9990612 | Fase 4a — Migración histórica (72 pagos + 39 reglas) con dry-run y conciliación al centavo, legacy intacto | alta | media | sin señales adicionales; se conserva media |
| 9990613 | Fase 4b — Deprecar (no borrar) los modelos dormidos de comisiones de vendedor tras ventana de operación estable | alta | media | sin señales adicionales; se conserva media |
| 9990629 | Seguimiento: pregunta sin resolver de #272 | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990630 | Seguimiento: pregunta sin resolver de #274 | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990631 | Seguimiento: pregunta sin resolver de #285 | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990635 | Versionado dev↔prod: el consecutivo salía de la tabla releases y retrocedía (RESUELTO Fases 1+3) | alta | media | sin señales adicionales; se conserva media |
| 9990642 | Acceso de www-data a GitHub (deploy key READ-ONLY) para el resolver de versión | media | media | sin señales adicionales; se conserva media |
| 9990657 | Fase A verificación en vivo (e): confirmar aislamiento real contra /var/www/megaisp | alta | media | sin señales adicionales; se conserva media |
| 9990659 | Fase B — verificacion en vivo REAL contra /var/www/megaisp (rama ajena a mano), requiere sesion supervisada | alta | media | sin señales adicionales; se conserva media |
| 9990667 | Voz Mayorista — Sprint 1: plano de control comercial (bloque 1: esqueleto, blindaje de instancia y permisos) | alta | media | sin señales adicionales; se conserva media |
| 9990668 | Emisión atómica de versiones: una versión existe si y sólo si está publicada | alta | media | sin señales adicionales; se conserva media |
| 9990670 | SEG-1 — Rotar/asegurar el token de GitHub antes de que el path web lo use en F1 | alta | media | sin señales adicionales; se conserva media |
| 9990671 | F1 — Estado de publicación visible por versión en el historial de la Torre | alta | media | sin señales adicionales; se conserva media |
| 9990672 | F1·B — La pantalla de actualización de producción muestra el salto completo y acumulado | alta | media | sin señales adicionales; se conserva media |
| 9990674 | F3 — ReleasePublisher: emisión atómica con compensación | alta | media | sin señales adicionales; se conserva media |
| 9990675 | F4 — Candado en base de datos: no puede existir una versión sin Release publicado | alta | media | sin señales adicionales; se conserva media |
| 9990677 | F6 — Reconciliar la historia: publicar retroactivo o marcar histórica | alta | media | sin señales adicionales; se conserva media |
| 9990678 | F7 — Dejar la regla escrita y separar el permiso de emitir | alta | media | sin señales adicionales; se conserva media |
| 9990679 | F8 — Emitir V1.35 con el motor nuevo: la prueba de fuego | alta | media | sin señales adicionales; se conserva media |
| 9990713 | Empaquetar Asterisk como .deb propio y montar el repositorio APT de Meganet | alta | media | sin señales adicionales; se conserva media |
| 9990715 | Voz Fase 2 — Sanear el esquema realtime: volver a Alembic estándar y no parchear tablas de Asterisk | alta | media | sin señales adicionales; se conserva media |
| 9990718 | Provisionador del módulo VoIP: que la actualización deje Asterisk instalado y funcionando | alta | media | sin señales adicionales; se conserva media |
| 9990719 | Divergencia entre items completados y ramas sin integrar | alta | media | sin señales adicionales; se conserva media |
| 9990721 | permisos | alta | media | sin señales adicionales; se conserva media |
| 9990723 | Fase 2 — Correcciones mínimas del módulo VoIP (sección 6) | alta | media | sin señales adicionales; se conserva media |
| 9990725 | Fase 4 — Extensiones sembradas por departamento (sección 8) | alta | media | sin señales adicionales; se conserva media |
| 9990727 | Fase 6 — El provisionador de Asterisk + especificación de compilación (secciones 3 y 4) | alta | media | sin señales adicionales; se conserva media |
| 9990728 | Fase 7 — Prueba obligatoria de punta a punta en dev + cierre del paraguas #9990718 | alta | media | sin señales adicionales; se conserva media |
| 9990729 | F3 (#9990674): ejecutar a mano el ensayo real de red cortada (push+GitHub Release TEST-) | media | media | sin señales adicionales; se conserva media |
| 9990730 | Implementar auditoría periódica + gate en el cierre para items completado sin merge (decisión de #9990719) | alta | media | sin señales adicionales; se conserva media |
| 9990735 | [RESPUESTA] Voz Fase 3 — Cerrar la seguridad: Asterisk sin root, sin directorio 777 y sin código muerto — falta correr runbook del directorio 777 residual | media | media | sin señales adicionales; se conserva media |
| 9990743 | Fase 0+1 — diagnóstico confirmado (transacción+rollback): diff SUPERVISOR_MOSTRADOR vs Diana | alta | media | sin señales adicionales; se conserva media |
| 9990744 | Fase 2/3 — verificar hipótesis H1-H10 según la rama de Fase 1 | alta | media | sin señales adicionales; se conserva media |
| 9990745 | Fase 4 — inventario y matriz completa de permisos (obligatorio, en paralelo) | alta | media | sin señales adicionales; se conserva media |
| 9990765 | Fase 4c — Matriz por rol: permisos asignados vs. permisos que surten efecto (punto 4) | alta | media | sin señales adicionales; se conserva media |
| 9990766 | Fase 4c — cerrar: vista Blade+Vue de la matriz de permisos por rol + correr real + poblar reporte | alta | media | sin señales adicionales; se conserva media |
| 9990767 | Fase 4c-i — mergear rama backend existente + migrar + vista Blade+Vue de la matriz de permisos por rol | alta | media | sin señales adicionales; se conserva media |
| 9990768 | Fase 4c-ii — correr auditoria:permisos-matriz-roles contra BD real de dev y cerrar el item padre #9990765 | alta | media | sin señales adicionales; se conserva media |
| 9990788 | Apagado del módulo Vendedores y limpieza de la tabla puente sellers | media | media | sin señales adicionales; se conserva media |
| 9990791 | [RESPUESTA] Auditoría de permisos: rol con permisos asignados que no se reflejan en el administrador (caso Supervisor de mostrador → Diana) — decidir corrección + evitar duplicar #9990721 | alta | media | sin señales adicionales; se conserva media |
| 9990797 | Ejecutar fix de Diana: asignar rol SUPERVISOR_MOSTRADOR + resync (bloqueado hasta que cierre #9990746) | alta | media | sin señales adicionales; se conserva media |
| 9990803 | Clientes: el buscador del listado debe buscar únicamente en las columnas visibles de cada administrador | alta | media | sin señales adicionales; se conserva media |
| 9990805 | Firma en pantalla con metadata legal completa (IP, dispositivo, hash, trazos) y hoja de constancia en el PDF | alta | media | sin señales adicionales; se conserva media |
| 9990806 | Reapertura automática de acuses al cambiar de versión de plantilla | alta | media | sin señales adicionales; se conserva media |
| 9990807 | Pantalla del colaborador (pendientes/firmados) + tablero de pendientes para Irving + endpoints API-first | alta | media | sin señales adicionales; se conserva media |
| 9990808 | Motor de ventas: catálogos backend (migraciones + modelos + permisos + CRUD API) — Fase 1 de #9990781 | media | media | sin señales adicionales; se conserva media |
| 9990809 | Motor de ventas: UI CRUD de catálogos (modalidades/modos de pago/parámetros reglamento) — Fase 2 de #9990781 | media | media | sin señales adicionales; se conserva media |
| 9990810 | Cerrar VoIP: de dev hasta que suene el teléfono en producción | alta | media | sin señales adicionales; se conserva media |
| 9990815 | Fase 4 — Medición p50/p95, criterios de aceptación y encendido del flag en dev | alta | media | sin señales adicionales; se conserva media |
| 9990816 | Tablero admin para Irving: qué colaborador tiene qué documento pendiente (con antigüedad + recordar) | alta | media | sin señales adicionales; se conserva media |
| 9990817 | Reapertura de acuses — verificación end-to-end con datos sintéticos (rollback) | alta | media | sin señales adicionales; se conserva media |
| 9990820 | Fase 3 de #9990805: regla especial del Convenio de Comisiones (escaneado + estado 'parcial') | alta | media | sin señales adicionales; se conserva media |
| 9990825 | VoIP Fase 3 — emitir version, publicar GitHub Release y documentar el runbook de despliegue | alta | media | sin señales adicionales; se conserva media |
| 9990827 | Reapertura de acuses — cerrar #9990806 con reporte_coloquial + enlace_revision | alta | media | sin señales adicionales; se conserva media |
| 9990830 | Tablero pendientes Fase 1 — permiso talento.documentos.ver-todos + endpoint admin GET /talento/api/documentos/pendientes | alta | media | sin señales adicionales; se conserva media |
| 9990831 | Tablero pendientes Fase 2 — endpoint POST /talento/api/documentos/{docId}/recordar (WhatsApp, reusa WhatsAppGateway compartido) | alta | media | sin señales adicionales; se conserva media |
| 9990832 | Tablero pendientes Fase 3 — pantalla admin del tablero (tabla + KPIs + boton Recordar con modal) | alta | media | sin señales adicionales; se conserva media |
| 9990834 | Reapertura de acuses — implementar y pasar ReaperturaAcusesTest.php (happy path + negativo + 2 bordes) | alta | media | sin señales adicionales; se conserva media |
| 9990839 | Fase 4a — Medir p50/p95 real (nombre vs SN) y repasar los 10 criterios de aceptación de #9990803 | alta | media | sin señales adicionales; se conserva media |
| 9990840 | Fase 4b — Encender CLIENTES_BUSQUEDA_V2 en dev + config:auditar-env/cache + cierre de #9990803 | alta | media | sin señales adicionales; se conserva media |
| 9990853 | corrección del Circuito CC — para desatorar el flujo | media | media | sin señales adicionales; se conserva media |
| 9990854 | corrección del Circuito CC — para desatorar el flujo | media | media | sin señales adicionales; se conserva media |
| 9990856 | El comentario de Irving en un item requiere_irving es la respuesta: hilo que no se pisa + re-encolado automático (PARAGUAS) | alta | media | sin señales adicionales; se conserva media |
| 9990859 | CIRC-02c Visibilidad: hilo de respuestas en la Torre + watchdog de respuestas sin consumir | media | media | sin señales adicionales; se conserva media |
| 9990860 | Bandeja de decisiones: distinguir el item que espera una decisión del que espera un insumo material de Irving | alta | media | sin señales adicionales; se conserva media |
| 9990861 | Re-triaje de la cola: prioridad real, limpieza de basura y clasificación de los 430 items sin nivel de riesgo | media | media | sin señales adicionales; se conserva media |
| 9990862 | API roadmap-externo: creación de items con token propio (RC) + hilo de reportes que no se pisa | alta | media | sin señales adicionales; se conserva media |
| 9990863 | Terminales ociosas con items aprobados: por qué el despachador no asigna y por qué "Listos para terminal" miente | alta | media | sin señales adicionales; se conserva media |
| 9990870 | CIRC-04 — Re-triaje de la cola: coherencia cancelados, basura y 430 items sin nivel de riesgo | media | media | sin señales adicionales; se conserva media |
| 9990871 | CIRC-05 — API externa roadmap-externo: creación de items (token RC) + hilo unificado de reportes | media | media | sin señales adicionales; se conserva media |
| 9990874 | Items de corrección del Circuito CC — para desatorar el flujo | alta | media | sin señales adicionales; se conserva media |
| 9990876 | PASO 4 de #9990861: propuesta de re-priorización de la cola viva (docs/roadmap/, NO aplicar) | media | media | sin señales adicionales; se conserva media |
| 9990878 | Identidad unificada — Fase 3: doble escritura de colaborador_id junto a seller_id | alta | media | sin señales adicionales; se conserva media |
| 9990879 | Identidad unificada — Fase 4: corte de lectura de seller_id a colaborador_id, módulo por módulo | alta | media | sin señales adicionales; se conserva media |
| 9990887 | CIRC-04: Re-triaje de la cola — prioridad real, limpieza de basura y clasificación de los 430 items sin nivel de riesgo | alta | media | sin señales adicionales; se conserva media |
| 9990888 | CIRC-05: Extender la API externa roadmap-externo — creación de items con token propio (RC) + hilo de reportes que no se pisa | alta | media | sin señales adicionales; se conserva media |
| 9990893 | Fase 3 (C2) — que 'Listos para terminal' diga la verdad + contador de vueltas quemadas + cablear #9990798 | alta | media | sin señales adicionales; se conserva media |
| 9990894 | Fase 4 (C3/C4/C5) — leer el log de despacho y decidir tope de concurrencia/frenos según lo que diga | alta | media | sin señales adicionales; se conserva media |
| 9990895 | Fase 5 — tablero permanente 'terminales ociosas x minutos' y 'elegibles reales' en la Torre | alta | media | sin señales adicionales; se conserva media |
| 9990900 | CIRC-02b PASO 2 — Disparo: comentario humano sobre requiere_irving crea respuesta + re-encola | alta | media | sin señales adicionales; se conserva media |
| 9990901 | CIRC-02b PASO 3 — Inyección de la respuesta en el prompt con precedencia | alta | media | sin señales adicionales; se conserva media |
| 9990902 | CIRC-02b PASO 4 — Pruebas end-to-end de los 3 pasos anteriores | alta | media | sin señales adicionales; se conserva media |
| 9990903 | [RESPUESTA] CIRC-02b — 3 cadenas duplicadas del mismo trabajo (CIRC-02a/b/c) | media | media | sin señales adicionales; se conserva media |
| 9990910 | Migración aditiva motivo_espera en roadmap_items + proyección en COLUMNAS_BANDEJA | alta | media | sin señales adicionales; se conserva media |
| 9990911 | Backfill motivo_espera para los 10 items ya identificados por Irving (aprobado_irving + excluir_pool ya en true) | alta | media | sin señales adicionales; se conserva media |
| 9990912 | Torre — bandeja partida en dos listas (Esperan tu decisión / Esperan un insumo tuyo) + ajuste de métricas | alta | media | sin señales adicionales; se conserva media |
| 9990913 | Barrido de aprobado_irving por texto de bloqueo (candidatos adicionales a motivo_espera) | alta | media | sin señales adicionales; se conserva media |
| 9990924 | Fase 3b — SupervisorService: ocultar de 'Listos para terminal' items con bloqueo declarado en el prompt (heurística de texto) | alta | media | sin señales adicionales; se conserva media |
| 9990927 | Proyectar motivo_espera en RoadmapController::COLUMNAS_BANDEJA + verificación torre() | alta | media | sin señales adicionales; se conserva media |
| 9990933 | CIRC-02 · El comentario de Irving en un item requiere_irving es la respuesta: | media | media | sin señales adicionales; se conserva media |
| 9990934 | CIRC-09 · Panorama en árbol (y baja de tres pestañas) | media | media | sin señales adicionales; se conserva media |
| 9990935 | CIRC-08 · Inventario de las ramas sin integrar (SOLO LECTURA) | media | media | sin señales adicionales; se conserva media |
| 9990937 | CIRC-03 · Bandeja de decisiones real: separar "espera decisión" de "espera insumo" | media | media | sin señales adicionales; se conserva media |
| 9990938 | Fase 3a-i — depende_de: filtrar 'Listos para terminal' con el mismo criterio MR-36 de RoadmapCircuitoService | alta | media | sin señales adicionales; se conserva media |
| 9990944 | Fase B — SupervisorService: aplicar filtrarConDependenciasCerradas() en listosParaTerminal()/listosParaTerminalTotal() | alta | media | sin señales adicionales; se conserva media |
| 9990945 | Seguimiento: pregunta sin resolver de #9990882 | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |
| 9990949 | CIRC-05 pieza D — Pruebas Feature de la API externa de creación + hilo (PASO 7) | alta | media | sin señales adicionales; se conserva media |
| 9990954 | Fase B1 — SupervisorService::listosParaTerminal(): aplicar filtrarConDependenciasCerradas() | alta | media | sin señales adicionales; se conserva media |
| 9990957 | Fase B2 — SupervisorService::listosParaTerminalTotal(): aplicar filtrarConDependenciasCerradas() + verificación final | alta | media | sin señales adicionales; se conserva media |
| 9990963 | Fase 3b: implementar doble escritura de colaborador_id (observer + parches raw + feature flag) | alta | media | sin señales adicionales; se conserva media |
| 9990964 | Fase 3c: comando identidad:verificar-consistencia + KPI card en dashboard admin | alta | media | sin señales adicionales; se conserva media |
| 9990965 | Fase 3d: activar el flag de doble escritura en dev + validación end-to-end + cerrar #9990878 | alta | media | sin señales adicionales; se conserva media |
| 9990970 | CIRC-09 Fase 3 - Componente de arbol jerarquico solo lectura (filtros + buscador + persistencia local) | media | media | sin señales adicionales; se conserva media |
| 9990971 | CIRC-09 Fase 4 - Paneles desplegables de lectura: Trabajando y Decidido sin ti | media | media | sin señales adicionales; se conserva media |
| 9990973 | CIRC-09 Fase 5 - Panel de decision con placeholder (sin esperar a CIRC-02b) | media | media | sin señales adicionales; se conserva media |
| 9990974 | CIRC-09 Fase 6 - Acciones por estado detras de permiso Spatie + refresco en vivo sin perder scroll | media | media | sin señales adicionales; se conserva media |
| 9990977 | Fase 5a — SupervisorService: métricas de ociosidad+elegibles reales | alta | media | sin señales adicionales; se conserva media |
| 9990978 | Fase 5b — Torre: panel permanente ociosidad+elegibles reales (UI) | alta | media | sin señales adicionales; se conserva media |
| 9990999 | CIRC-02 prevencion (q2): dedupe de items al crearse en la bandeja por similitud de titulo/descripcion | media | media | sin señales adicionales; se conserva media |
| 9991000 | PASO 4 de #9990861 (mecanizado): generar docs/roadmap/repriorizacion-YYYYMMDD.md con script, sin razonar fila por fila | media | media | sin señales adicionales; se conserva media |
| 9991001 | Seguimiento: pregunta sin resolver de #9990968 | (sin prioridad) | media | sin prioridad previa; sin señales de urgencia -> media por default |

## Propuesta: baja (4 items)

| ID | titulo | prioridad actual | propuesta | razon |
|---|---|---|---|---|
| 155 | client_main_information.user: normalizar formato de número de cliente (padding 004981 vs 4981) | baja | baja | sin señales de urgencia; se conserva baja |
| 164 | Fase 2 Medussa: cuota de software por transacción conciliada | baja | baja | sin señales de urgencia; se conserva baja |
| 9990789 | Cambiar el concepto del CFDI al suspenderse el servicio: de servicio de Internet a renta de equipo en comodato | baja | baja | sin señales de urgencia; se conserva baja |
| 9990795 | App del colaborador (PWA o nativa): firma, documentos, cursos y operación en campo | baja | baja | sin señales de urgencia; se conserva baja |


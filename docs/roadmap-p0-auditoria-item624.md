# Auditoría P0 no-mergeados — consolidación final (#624 / #743 / #782)

**Fecha:** 2026-08-29

Este documento es la **Fase 2 de la reconstrucción de #624** (sub-item #743, dividido en 4 lotes de auditoría + esta consolidación final, sub-item #782). Referencias:

- Inventario crudo (fase 1, #742): `docs/roadmap-p0-inventario-crudo-item624.md`
- Lotes de auditoría (fase 2a-2d, #778/#779/#780/#781): `docs/roadmap-p0-auditoria-item624-batch{A,B,C,D}.json` (24 candidatos cada uno, 96 en total). El lote A se ejecutó en dos partes (`batchA-part1.json` + `batchA-part2.json`, sub-items #802/#803) y se consolidó en `batchA.json` (sub-item #804).
- Esta consolidación (fase 2e, #782): el documento presente.

## Metodología

Los 96 candidatos salen del inventario crudo de #742: items del roadmap marcados como "P0" (crítico/urgente en algún momento de su historia) que **nunca llegaron a `merge_commit`** — es decir, quedaron con rama sin mergear, escalados, bloqueados, o abandonados antes de integrarse a `main`.

Para cada uno de los 4 lotes, un ejecutor del circuito revisó el `log`/`comentarios_claude` histórico de cada id (los únicos snippets disponibles — no se re-ejecutó ni re-investigó el trabajo original) y determinó:

- `estado_final`: qué pasó con el item (escalado, rechazado, bloqueado, duplicado, etc.)
- `razon_no_merge`: por qué nunca llegó a main, en una frase
- `nivel_riesgo`: A/B/C si algún snippet lo declaraba explícitamente, o `indeterminado` si ningún log documentaba una letra de riesgo explícita para ese id
- `brief_completo`: si el item llegó a tener un brief (preguntas/opciones) evaluado, o se quedó sin llegar a esa etapa

**Filtro aplicado (criterio de Irving):** *"Solo items con brief completo y nivel A/B que no llegaron a merge_commit"* → `incluir=true` únicamente si `nivel_riesgo` ∈ {A, B} **y** `brief_completo=true`. Todo lo demás queda excluido de la tabla principal (nivel C explícito, brief incompleto, o evidencia insuficiente para determinar el nivel).

## Tabla principal — candidatos a reconstruir

**2 de 96** candidatos cumplen el filtro (brief completo + nivel A/B) y quedan aquí para que **Irving marque manualmente** cuáles quiere reconstruir. Este documento NO crea items nuevos — es solo el reporte.

| id | título | estado_final | razón no-merge | nivel_riesgo |
|---|---|---|---|---|
| #186 | Habilitar AMI (puerto 5038) en Asterisk DEV — editar manager.conf y "manager reload" | escalado_a_irving | Requiere sesión EN VIVO con Irving (frontera dura de credenciales de infraestructura VoIP): editar /etc/asterisk/manager.conf y ejecutar 'manager reload' está fuera del repo git, requiere sudo, y hay instrucción explícita de Irving ('No ejecutar autónomamente', vigente desde 2026-07-14/15, nunca revocada). Escalado 17+ veces consecutivas sin poder tocar código. | B |
| #795 | Documentos Oficiales — Parte A (repositorio) | escalado_a_irving | Múltiples intentos sucesivos (18 al 21-ago, wt-1/wt-4) sobre la rama circuito/item-795-documentos-oficiales-parte-a-reposito timeoutearon a los 600s con la rama en 0 commits; tras agotar reanudaciones (0/2) el sistema lo envió a la bandeja de Irving (destino=aprobado_irving) y decidió no reencolarlo para no repetir el mismo timeout sin avance. | B |

## Excluidos (nivel C o brief incompleto)

19 ids excluidos del filtro por ser nivel C explícito (decisión de diseño/negocio exclusiva de Irving, o frontera dura de producción) o por no haber llegado a tener un brief completo evaluado. Listado breve, sin detalle adicional:

- **#308** — Registro de contratos de módulo (tablas module_contracts / module_contract_consumers): nivel_riesgo=C explícito (merge nivel C, botón manual de Irving) — excluido por el filtro (solo A/B)
- **#463** — Tarea dependiente del registro de contratos de módulo (bloqueada por #308 sin mergear): nivel_riesgo=C (la única acción pendiente, mergear #308, es nivel C) — excluido por el filtro pese a brief_completo=true
- **#485** — Decisión de negocio de cobranza (no ejecutable por código): nivel_riesgo=C (decisión de negocio, diseño exclusivo de Irving) — excluido por el filtro
- **#528** — Motor auditor auto-encadenado (superado por #559 / circuito:auditor): nivel_riesgo=C (guardrail nivel-C del modelo) — excluido por el filtro pese a brief_completo=true
- **#542** — Fix role_permission_overrides — evitar que un sync re-inyecte permisos de rol revocados a mano: nivel_riesgo=C explícito — excluido por el filtro pese a brief_completo=true y código ya implementado/verificado
- **#811** — Épica IPv6 (5 fases) — paraguas: nivel_riesgo=C explícito ('frontera dura de producción'; varios de sus hijos requieren requiere_irving) — excluido por el filtro pese a brief_completo=true
- **#875** — Consola / Torre de Control — Fase 1: Semáforo de motores: brief_completo=false
- **#876** — Consola — Fase 3: Catálogo de acciones (fundamento): brief_completo=false
- **#879** — Torre de Control — Fase 1: Semáforo de motores (endpoint + pestaña): brief_completo=false
- **#881** — Consola — Fase 3: Catálogo de acciones real (TorreAccionCatalogo + endpoint): brief_completo=false
- **#882** — Torre de Control — Fase 5: Terminales, botón Liberar: brief_completo=false
- **#939** — Torre de Control — backend: orden real de la cola + endpoint GET /torre/cola: brief_completo=false
- **#977** — Corregir 4 paths rotos de update (estado/municipio/colonia/sucursal) en config/route_permission.php: brief_completo=false
- **#978** — Deprecar (no retirar) el permiso legado config_view_system — opción 2 aprobada por Irving (q3): brief_completo=false
- **#984** — Scaffold del módulo app/Modules/Addons/Ipv6 + 8 permisos ipv6.*: brief_completo=false
- **#993** — IPv6 Fase 3.3 — Simple queues VLANs 600-700 con target IPv6 (bloqueado por #953 y frontera de producción MikroTik): brief_completo=false
- **#998** — IPv6 Fase 4.4 — Prevuelo/plantillas/reversión sobre equipos MikroTik reales: brief_completo=false
- **#1013** — Auditoría de 13 items cerrados con una decisión requiere_irving enterrada: brief_completo=false
- **#1044** — IPv6 — tablas cliente/despliegue: clientes_ipv6_prefijos/config/excepciones + ipv6_despliegues — sub-item de #985: brief_completo=false

## Excluidos por evidencia insuficiente

75 ids donde ningún log disponible declaraba explícitamente un nivel A/B/C — quedaron como `nivel_riesgo=indeterminado`. Se listan aparte para que Irving sepa que hay candidatos que **merecerían revisión manual más a fondo** si le interesan (varios tienen trabajo ya completado/mergeado pero sin la letra de riesgo documentada en los logs consultados):

- **#254** — FcmService legacy — reactivar push notifications Firebase (FASE 0 diagnóstico): evidencia insuficiente para nivel_riesgo — el bloqueo es una credencial externa faltante, los logs no mencionan nivel A/B/C explícito
- **#541** — indeterminado: evidencia insuficiente para nivel_riesgo y para brief_completo — solo hay registros de toma del item, ningún resultado documentado en el JSON fuente
- **#544** — Fase 2 — tabla de clasificación real (rama circuito/item-544-fase-2-tabla-de-clasificacion-real-ver…): evidencia insuficiente para nivel_riesgo — el registro solo documenta un timeout sin commits, sin mención de nivel A/B/C ni de brief evaluado
- **#559** — Motor de Auditoría Continua (AuditorService / AuditorCommand, comando circuito:auditor): evidencia insuficiente para nivel_riesgo (ningún snippet menciona nivel A/B/C para #559) — aunque brief_completo=true y el trabajo está funcionalmente completado e integrado en main
- **#566** — indeterminado (regla preexistente "crear es aprobar" — items creados desde la Torre se auto-aprueban): evidencia insuficiente — única mención es de paso dentro del log de un item distinto, no hay registro de trabajo directo, brief ni nivel de riesgo sobre #566
- **#595** — indeterminado: evidencia insuficiente para nivel_riesgo y para brief_completo — solo hay registros de toma del item, ningún resultado documentado en el JSON fuente
- **#794** — Migración env()→config() — parte "resto" (junto a #792 IA y #793 WhatsApp/CobranzaBlaster/MikroTik): evidencia insuficiente para nivel_riesgo — ningún snippet menciona nivel A/B/C ni frontera dura para #794 (solo describe la migración como 'sin riesgo' en prosa, no es una declaración explícita de nivel)
- **#820** — Duplicado de #795 (Documentos Oficiales) — rechazado: evidencia insuficiente para nivel_riesgo — ningún snippet menciona nivel A/B/C para #820; además fue rechazado como duplicado, su alcance ya está cubierto por #795
- **#835** — ModuleManager — declarar los 13 api_endpoints reales en su module.json: evidencia insuficiente para nivel_riesgo — ningún snippet menciona nivel A/B/C para #835
- **#836** — Paquete administrativo: decisiones de Irving sobre la Hoja de Ruta: evidencia insuficiente para nivel_riesgo — ningún snippet menciona nivel A/B/C para #836; además era administrativo y no requería merge propio
- **#842** — Canal de Respuesta de CC hacia Cowork/Irving: evidencia insuficiente para nivel_riesgo — ningún snippet menciona nivel A/B/C para #842, pese a tener un commit real aprobado que quedó huérfano
- **#850** — core-documentos — retirar el permiso legado config_view_system de document_template/document_type_template: evidencia insuficiente para nivel_riesgo — ningún snippet menciona nivel A/B/C para #850
- **#853** — Duplicado de #852 (Épica #874): evidencia insuficiente para nivel_riesgo — ningún snippet menciona nivel A/B/C para #853; además su contenido ya está en main vía #852/Épica #874
- **#857** — Registro de trabajo ya mergeado a main (fuera del circuito): evidencia insuficiente para nivel_riesgo — ningún snippet menciona nivel A/B/C para #857; además su contenido ya está en main
- **#862** — Torre de Control — borrado definitivo a 30 días de los bloques Sub-tareas/Bitácora/Memoria retirados del detalle de item: evidencia insuficiente para nivel_riesgo — ningún snippet menciona nivel A/B/C para #862; además su ejecución está agendada a futuro (2026-09-18) por diseño, no es una omisión
- **#866** — Registro retroactivo de 4 documentos de la Torre (ya en main): evidencia insuficiente para nivel_riesgo — ningún snippet menciona nivel A/B/C para #866; además su contenido ya está en main
- **#867** — TEST #863 undo (residuo de prueba del botón Deshacer): evidencia insuficiente para nivel_riesgo — item de prueba sin spec real, no aplica clasificación de riesgo
- **#874** — Épica Torre de Control / Consola — paraguas de fases: evidencia insuficiente para nivel_riesgo — item paraguas, decomposición sin evaluación de riesgo propia
- **#877** — Torre de Control — Diagnóstico de items (backend + ficha + tablero): evidencia insuficiente para nivel_riesgo — item paraguas resuelto vía sub-items, sin evaluación de riesgo propia
- **#883** — Torre de Control — Fase 6 (dependiente del Catálogo de acciones): evidencia insuficiente para nivel_riesgo — nunca llegó a ejecutar código ni a evaluarse, bloqueado desde el primer intento por su prerrequisito interno (#881)
- **#888** — Torre de Control — Fase (rename de controles, dependiente de #881): evidencia insuficiente para nivel_riesgo — item paraguas descompuesto sin código propio, bloqueado por dependencia (#881)
- **#892** — Consola — Fase 8: Historial de acciones: evidencia insuficiente para nivel_riesgo — nunca se generó código ni brief evaluado, solo timeouts repetidos sin commits
- **#909** — Consola — Catálogo de acciones: migración de ~30 botones restantes: evidencia insuficiente para nivel_riesgo — paraguas descompuesto sin código propio, dependiente de #876 (nivel C) sin mergear
- **#915** — MegaFamilia RN — cablear UI de tickets (detalle/foto/calificación): evidencia insuficiente para nivel_riesgo — trabajo completado pero en repo externo (megafamilia-rn), sin evaluación de nivel_riesgo documentada en este repo
- **#916** — MegaFamilia RN — UI de tracking GPS del conductor: evidencia insuficiente para nivel_riesgo — bloqueado estructuralmente (repo externo) antes de cualquier evaluación de riesgo
- **#919** — Item con título "REVISAR" migrado al mecanismo agendado_para (vía #921): evidencia insuficiente para nivel_riesgo — solo se registró su reprogramación (agendado_para), sin evaluación de contenido
- **#920** — Flotas — UI de "ficha de conductor" (documentos sin vehículo): evidencia insuficiente para nivel_riesgo — múltiples intentos sin meta_tocado de cierre registrado en los logs disponibles
- **#922** — Agendar items a futuro (columna reactivación): evidencia insuficiente para nivel_riesgo — cerrado como duplicado de trabajo ya mergeado por otros items, sin evaluación propia
- **#930** — Torre de Control — Fase 6, Paso 0 (plan detallado, dependiente de #881): evidencia insuficiente para nivel_riesgo — el item tiene brief respondido (pregunta q1 de Irving) pero ningún log declara su propio nivel_riesgo; queda bloqueado indefinidamente por el prerrequisito #881 (nivel C, nunca mergeado)
- **#932** — Item referenciado de pasada junto a #934 en el cierre de #938: evidencia insuficiente para nivel_riesgo — solo 1 mención de pasada, sin contexto propio suficiente
- **#952** — Épica IPv6 (#811) — Fase 1.7: configuración IPv6: evidencia insuficiente para nivel_riesgo — item paraguas descompuesto sin código propio, bloqueado por dependencias
- **#953** — Épica IPv6 (#811) — Fase 2: spike RADIUS PD (dev vs prod): evidencia insuficiente para nivel_riesgo — nunca se ejecutó código, bloqueado por precondición propia (definición de Irving sobre conflicto RADIUS) desde el primer intento
- **#954** — Épica IPv6 (#811) — Fase 3: evidencia insuficiente para nivel_riesgo — item paraguas bloqueado por precondición (#953), sin código propio
- **#955** — Épica IPv6 (#811) — Fase 4: evidencia insuficiente para nivel_riesgo — item paraguas bloqueado por fronteras duras y dependencia, sin código propio
- **#956** — Épica IPv6 — Fase 5 (paraguas, sub-item de #811): evidencia insuficiente para nivel_riesgo — item paraguas sin código propio, la evaluación de riesgo vive en sus sub-items
- **#959** — Migrar los 4 casos reales de #921 a agendado_para (incluye renovación de certificado SSL): evidencia insuficiente para nivel_riesgo — operación de datos sobre el roadmap sin evaluación de riesgo de código explícita
- **#961** — Migración de acciones a ConsolaAcciones — Lote 1 por permiso/riesgo (bloqueado por fundamento #876): evidencia insuficiente para nivel_riesgo — bloqueado por la frontera de #876 antes de alcanzar una evaluación de riesgo propia
- **#962** — Migración de acciones a ConsolaAcciones — Lote 2 por permiso/riesgo (bloqueado por fundamento #876): evidencia insuficiente para nivel_riesgo — bloqueado por la frontera de #876 antes de alcanzar una evaluación de riesgo propia
- **#963** — Migración de acciones a ConsolaAcciones — Lote 3 por permiso/riesgo (bloqueado por fundamento #876): evidencia insuficiente para nivel_riesgo — bloqueado por la frontera de #876 antes de alcanzar una evaluación de riesgo propia
- **#964** — Migración de acciones a ConsolaAcciones — Lote 4 por permiso/riesgo (bloqueado por fundamento #876): evidencia insuficiente para nivel_riesgo — bloqueado por la frontera de #876 antes de alcanzar una evaluación de riesgo propia
- **#969** — Renovar certificado SSL de dev.meganett.com.mx (vencimiento real 2026-10-08): evidencia insuficiente para nivel_riesgo — item agendado a futuro, aún no procesado en el rango de logs disponible
- **#973** — Comando circuito:barrer-reclamos-huerfanos + pulso #808: evidencia insuficiente para nivel_riesgo — sin mención explícita de nivel en el log pese a la implementación completa reportada
- **#981** — DiagnosticoItemService — investigar y agregar las 3 causas restantes (8 causas totales, plan de #877): evidencia insuficiente para nivel_riesgo — sin mención explícita de nivel pese a implementación y commit verificados en git
- **#985** — Migraciones IPv6 — 7 tablas de persistencia (incluye clientes_ipv6_prefijos): evidencia insuficiente para nivel_riesgo — item paraguas sin evaluación de riesgo propia
- **#986** — Modelos Eloquent IPv6 (dependen de #984 módulo y #985 migraciones): evidencia insuficiente para nivel_riesgo — item paraguas sin evaluación de riesgo propia
- **#991** — IPv6 Fase 3.1 — orquestación dry-run / asignación (sub-fase de Fase 3, bloqueada por #953): evidencia insuficiente para nivel_riesgo — item paraguas sin código propio
- **#994** — IPv6 Fase 3.4 — Detección de deriva y recuperación (bloqueado por #953): evidencia insuficiente para nivel_riesgo — bloqueado por precondición #953 antes de alcanzar una evaluación de riesgo propia
- **#995** — IPv6 Fase 4.1 — Búsqueda inversa (cliente por dirección IPv6): evidencia insuficiente para nivel_riesgo — descrito como 'bajo riesgo' en prosa sin letra explícita, y bloqueado antes de poder ejecutarse
- **#996** — IPv6 Fase 4.2 — Salud/alertas (depende del job de detección de deriva, Fase 3.4): evidencia insuficiente para nivel_riesgo — bloqueado de facto antes de alcanzar una evaluación propia
- **#997** — IPv6 Fase 4.3 — Geofeed público (depende de que Fase 2 y Fase 3 estén en main): evidencia insuficiente para nivel_riesgo — diferido por Thomas antes de una evaluación de riesgo propia
- **#1002** — IPv6 — Pantalla 4: Historial de despliegues (bloqueada hasta que #949 aporte persistencia real): evidencia insuficiente para nivel_riesgo — bloqueado por dependencia de persistencia antes de una evaluación propia
- **#1012** — Rollback a versión previa del deploy — paraguas de 5 fases: evidencia insuficiente para nivel_riesgo — item paraguas sin evaluación de riesgo propia (sus 5 hijos son nivel B/C)
- **#1019** — Sub-item de #1012 (paraguas "rollback a versión previa", 5 fases) — tema específico no capturado en los snippets disponibles: evidencia insuficiente para nivel_riesgo — dos timeouts consecutivos sin ejecutar código ni generar preguntas de brief
- **#1031** — IPv6 Fase 5.1 — Esquema de datos aditivo (tablas propias, sin FK a la fundación inexistente) — sub-item de #956: evidencia insuficiente para nivel_riesgo — item paraguas re-descompuesto en sub-items ajenos al lote (#1064/#1065), sin evaluación de riesgo propia
- **#1034** — IPv6 Fase 5.4 — Enganche real con el módulo Addons/Ipv6 + simulacro de aceptación — sub-item de #956: evidencia insuficiente para nivel_riesgo — bloqueado por dependencias sin cerrar, deferido repetidamente sin código ni brief propio evaluado
- **#1039** — IPv6 — crear y registrar el módulo Addons/Ipv6 + permisos — sub-item de #984: evidencia insuficiente para nivel_riesgo — brief con q2 ya respondida por Irving, pero sin nivel A/B/C explícito en los logs disponibles; escalado por contradicción de spec antes de evaluar riesgo
- **#1040** — IPv6 — verificar y cerrar el registro del módulo (depende de que #1039 esté mergeado) — sub-item de #984: evidencia insuficiente para nivel_riesgo — bloqueado por precondición de otro item sin cerrar (#1039), sin ejecución propia
- **#1042** — Fix accordeón procedencia/consecuencia — pintar los 4 controles verdes faltantes — sub-item de #989: evidencia insuficiente para nivel_riesgo — cerrado por redundancia (fix ya shippeado vía #989), sin evaluación de riesgo propia sobre #1042
- **#1048** — IPv6 — integración real de despliegue con el router (frontera dura: producción + seguridad de red) — sub-item de #991: evidencia insuficiente para nivel_riesgo — frontera dura de producción/seguridad descrita en prosa ("nivel B o C" típico de este patrón) pero sin la letra explícita en los logs disponibles; brief con respuestas de Irving ya registradas
- **#1052** — IPv6 — retomar cuando existan datos reales de #952/#953/#954 — sub-item diferido, spec no detallado en los snippets: evidencia insuficiente para nivel_riesgo — brief propio queda descrito como "pendiente" (no completo); deferido por dependencias sin cerrar
- **#1054** — Fase 4.4a — prevuelo read-only — sub-item de #998: evidencia insuficiente para nivel_riesgo — sub-item recién creado, nunca reclamado de nuevo en los logs disponibles
- **#1055** — Fase 4.4b — plantillas versionadas en BD — sub-item de #998: evidencia insuficiente para nivel_riesgo — sub-item recién creado, nunca reclamado de nuevo en los logs disponibles
- **#1056** — Fase 4.4c — reversión acotada por despliegue — sub-item de #998: evidencia insuficiente para nivel_riesgo — sub-item recién creado, nunca reclamado de nuevo en los logs disponibles
- **#1057** — Fase 4.4d — estado visible del proyecto — sub-item de #998: evidencia insuficiente para nivel_riesgo — sub-item recién creado, nunca reclamado de nuevo en los logs disponibles
- **#1058** — IPv6 — Backend: Ipv6ConfigController + rutas (routers/detectar-version/mapear-zonas/vista-previa) — sub-item de descomposición IPv6: evidencia insuficiente para nivel_riesgo — sub-item recién creado, nunca reclamado de nuevo en los logs disponibles
- **#1060** — IPv6 — Pantalla 1: Alta del bloque — sub-item de #1000: evidencia insuficiente para nivel_riesgo — sub-item recién creado, nunca reclamado de nuevo en los logs disponibles
- **#1063** — IPv6 — Pantalla 4 real con datos reales (bloqueada hasta #949/984/985/986) — sub-item de #1002: evidencia insuficiente para nivel_riesgo — bloqueado por dependencias sin cerrar y por decisión ya tomada por Irving de esperar, sin evaluación de riesgo propia
- **#1066** — IPv6 Fase 5.2 — esquema propio del motor de renumeración — sub-item de #1032: evidencia insuficiente para nivel_riesgo — sub-item de un paraguas, pendiente sin implementar, sin evaluación de riesgo propia
- **#1068** — IPv6 Fase 5.2 — timers + flag del motor de renumeración — sub-item de #1032: evidencia insuficiente para nivel_riesgo — sub-item de un paraguas, pendiente sin implementar, sin evaluación de riesgo propia
- **#1069** — IPv6 Fase 5.3 — servicio simulador + endpoint (backend, 4 fases RFC4192 + comandos generados) — sub-item de #1033: evidencia insuficiente para nivel_riesgo — sub-item backend nunca implementado (confirmado por el commit del hermano #1070 que lo da explícitamente por pendiente)
- **#1074** — IPv6 — backend (bloqueado, tablas clientes_ipv6_* inexistentes en dev) — sub-item de #995: evidencia insuficiente para nivel_riesgo — bloqueado por fundación IPv6 sin mergear, sin evaluación de riesgo propia
- **#1075** — IPv6 — frontend (bloqueado, tablas clientes_ipv6_* inexistentes en dev) — sub-item de #995: evidencia insuficiente para nivel_riesgo — bloqueado por fundación IPv6 sin mergear, sin evaluación de riesgo propia
- **#1076** — IPv6 — reglas de firewall v6 — sub-item de #1048: evidencia insuficiente para nivel_riesgo — sub-item técnico de una frontera dura de producción, pendiente sin evaluación de riesgo propia
- **#1077** — IPv6 — corte transaccional dual-stack v4+v6 atómico — sub-item de #1048: evidencia insuficiente para nivel_riesgo — sub-item técnico de una frontera dura de producción, pendiente sin evaluación de riesgo propia
- **#1078** — IPv6 — pruebas ping -4/-6 — sub-item de #1048: evidencia insuficiente para nivel_riesgo — sub-item técnico de una frontera dura de producción, pendiente sin evaluación de riesgo propia

## Caveat — reúso de ids (heredado de #742)

11 ids de este documento están **reusados hoy en `roadmap_items`** por items sin ninguna relación con la auditoría original (el roadmap recicla ids al crecer): **186, 308, 463, 485, 528, 541, 542, 544, 559, 566, 595**.

El título y estado que aparecen arriba para esos ids **salen únicamente de los logs históricos** citados en los lotes de auditoría — **NUNCA** de la fila actual de esos ids en la base de datos. Si Irving consulta esos ids hoy en la Torre/Hoja de Ruta, va a ver un item distinto y no relacionado; eso es esperado, no un error de este documento.

## Verificación de cobertura

- Tabla principal (incluidos): 2
- Excluidos nivel C / brief incompleto: 19
- Excluidos por evidencia insuficiente: 75
- **Total: 96 / 96** — los 96 ids de #742 quedan contabilizados sin ninguno perdido.

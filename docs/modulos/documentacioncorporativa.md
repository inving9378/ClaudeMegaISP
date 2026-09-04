# Módulo Documentación Corporativa

> `app/Modules/Addons/DocumentacionCorporativa/` · slug `addon-documentacion-corporativa` · módulo **addon** (activo) · Fase 0 entregada (item #662).

## 0. En simple
Es el expediente corporativo de la empresa, vivo y en un solo lugar: los 14 apartados de información que un miembro del consejo pidió formalmente, cada uno mostrando lo que el sistema ya sabe, guardando los papeles que sólo existen impresos, y generando desde plantilla los que nunca se redactaron. Responder la solicitud se vuelve un subproducto de tener la casa en orden.

## 1. Qué es
Addon **multi-empresa** que organiza la documentación corporativa y societaria en **14 apartados (I a XIV)** con **139 conceptos**. Cada concepto se resuelve por uno de seis caminos (dato vivo del sistema, documento cargado, plantilla, gráfica, inventario propio, o pendiente asignado a un responsable), y todo acceso queda registrado en una bitácora append-only.

Nace multi-empresa porque el mismo problema lo tiene cualquier ISP que arriende MegaISP: es pieza candidata para Medussa.

## 2. Para qué sirve
Le da a dirección y al consejo un tablero que dice **qué tan completo está el expediente** —por apartado, con semáforo, porcentaje y responsable— y un lugar único donde vive cada acta, poder, contrato, título de concesión o estado de cuenta, con sus versiones, su vigencia y su hash. Cuando llega una solicitud de información, el paquete se arma desde ahí (Fase 5) en vez de juntar papeles a mano.

**Separación no negociable:** el expediente interno y el paquete de entrega son cosas distintas. El módulo guarda todo lo que la empresa necesita para gobernarse; lo que sale hacia un tercero se arma apartado por apartado y se documenta en acta. El sistema **nunca decide qué se entrega**.

## 3. Cómo funciona

- **Catálogo sembrado, no capturado.** `CatalogoSeeder` siembra los 14 apartados y los 139 conceptos con `updateOrCreate` por `(empresa_id, clave)` y `(empresa_id, slug)`; es idempotente y verifica en tiempo de ejecución que sean exactamente 139 sin slugs repetidos. Los conceptos sembrados **no se borran**: se desactivan con `activo = false`. El usuario puede agregar los suyos, que el seeder no toca.
- **Seis resolvedores tras un contrato único.** `ConceptoResolver` (`disponible`/`resolver`/`exportar`) devuelve un `ResultadoConcepto` con estado `resuelto|parcial|vacio|sin_fuente`. `ResolverFactory` elige por `dc_conceptos.tipo_resolvedor` y **cae a `PendienteResolver`** cuando el declarado no está disponible — por eso ningún apartado puede quedar en blanco ni reventar: el peor caso es una tarjeta que explica qué falta.
  - `SistemaResolver` / `GraficaResolver` — leen de OTROS módulos a través de `FuenteRegistry` (registro **singleton**), nunca directo. Una fuente es siempre de solo lectura: usa el servicio público del módulo destino si existe, o consulta el modelo; jamás duplica lógica ni escribe fuera. `GraficaResolver` **extiende** a `SistemaResolver` a propósito: si la resolución de fuentes se duplicara, una gráfica y su tabla podrían contar cosas distintas del mismo dato.
  - `DocumentoResolver` — lee `dc_documentos`. Resuelto = al menos un documento no vencido.
  - `PlantillaResolver` — resuelve contra `document_templates` (**consume** el motor de `core-documentos`, no lo reemplaza).
  - `InventarioResolver` — lee una tabla `dc_*` propia declarada en `config.tabla`, contra una allowlist. Memoiza `Schema::hasTable` por request.
  - `PendienteResolver` — siempre disponible; es el fondo de red.
- **Criterio de `tipo_resolvedor`:** se mapea por la **fuente real** del dato. Lo que se lee de una tabla `dc_*` propia es `inventario` (un driver genérico); lo que se lee de otro módulo es `sistema`/`grafica` con su clave en `FuenteRegistry`.
- **El estado de vigencia se deriva, no se persiste.** `DcDocumento::getEstadoAttribute()` es la única fuente de verdad: `vencido` si `vigencia_fin < hoy`, `por_vencer` dentro de los 30 días, `vigente` en otro caso o si es nulo. No hay columna `estado` a propósito — una copia persistida envejece sola a medianoche. Las consultas filtran por `vigencia_fin`, indexada justo para eso.
- **Completitud con una sola fórmula.** `CompletitudService`: conceptos resueltos ÷ conceptos activos **obligatorios** del apartado. Le pregunta al resolvedor si cuenta como resuelto en vez de reimplementar la regla. En vivo por apartado; cacheado 15 min en el tablero (que recorre los 139, ~0.8 s). Un apartado **sin conceptos obligatorios no es medible**: devuelve `medible=false`, semáforo `gris` y la interfaz pinta `—`. No devuelve 100 % verde, porque un tablero que dice "completo" sobre un apartado vacío no se vuelve a revisar.
- **Doble puerta de permisos.** `check_route_permission` (fail-closed) gatea la ENTRADA con `documentacion-corporativa.view`, declarado en `config/route_permission.php`. El permiso **por apartado** (`.apartado.{i..xiv}.view`) lo aplica `ExpedienteController`, porque el middleware mapea ruta→permiso y las 14 claves comparten una ruta. El tablero recalcula su global sobre lo que ESE usuario puede ver.
- **Bitácora obligatoria.** `BitacoraService` es el punto único de escritura de `dc_accesos_log` y se llama **antes** de servir el contenido. La tabla es append-only (sin `updated_at`, sin `deleted_at`) y **no hay flag para desactivarla**: un interruptor para apagar la bitácora la convierte en una sugerencia. Lo denegado no se registra como visto.
- **Regla de credenciales (Fase 3).** Ninguna tabla del módulo lleva columna de contraseña, token, CLABE completa, tarjeta ni llave privada — ni cifrada, ni nullable. El campo de credencial **existe y se ve** en la interfaz, rendido como constante `********` más su leyenda ("No almacenada en el sistema por política de seguridad · Custodio · Resguardo · Última revisión"), y esa leyenda viaja en toda exportación. Los asteriscos solos insinuarían "la tenemos y no te la damos", que es justo el reproche del apartado XII.
- **Empresa activa** en sesión vía `EmpresaContextService`, que se autocorrige si la sesión apunta a una empresa desactivada; con una sola empresa activa el selector se oculta.
- **Archivos** en disco local privado `storage/app/documentacion_corporativa/{empresa_id}/{apartado}/{concepto}/`, nombre en disco = UUID, servidos sólo por controlador que valida permiso y registra en bitácora. Nunca en `public/`, nunca por URL directa.
- **Frontend:** `dc-expediente` (`resources/js/components/module/documentacion-corporativa/DcExpediente.vue`), Vue 3 + Quasar UMD, montado desde `views/index.blade.php`. Registrado en `resources/js/app.js`.
- **Navegación:** el ítem del sidebar y la tarjeta de `/admin/administracion` son **declarativos** (`menu[]` y `admin_cards[]` del `module.json`). No se toca ningún Blade.
- **`keep_data: true`** es protección real desde el item #669: `ModuleLifecycleService::resolveKeepData()` fuerza el modo conservador aunque quien desinstale pida lo contrario. Desinstalar apaga la pantalla, no borra el expediente.

### Tablas (Fase 0)
`dc_empresas` · `dc_apartados` · `dc_conceptos` (catálogo) · `dc_documentos` · `dc_documento_versiones` (inmutable) · `dc_pendientes` (contenido) · `dc_accesos_log` (bitácora, append-only). Todas con `empresa_id`.

Las fases siguientes agregan: `dc_accionistas`, `dc_capital_variaciones`, `dc_actas`, `dc_poderes`, `dc_contratos` (Fase 2); `dc_activos`, `dc_activos_digitales`, `dc_inventario_accesos` (Fase 3); `dc_concesiones`, `dc_concesion_pagos` (Fase 4); `dc_solicitudes`, `dc_entregas`, `dc_entrega_items` (Fase 5).

### Roles
`super-administrator` y `DESARROLLADOR` reciben los 23 permisos (automático al instalar). El rol **`consejo`** (migración aditiva, resuelve por nombre nunca por id) recibe 14: el módulo y 13 apartados — **nunca el XI** (bancos), sin `.documento.download`, sin `.inventario.accesos.view` y sin `.bitacora.view`: quien es auditado no audita el registro de auditoría. Ningún otro rol base recibe permisos de este módulo.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- Pantalla `/documentacion-corporativa` (índice de apartados + tablero de completitud).
- API interna: `GET /documentacion-corporativa/api/tablero`, `GET .../api/apartado/{clave}`, `POST .../api/empresa`.
- `FuenteRegistry` — punto de extensión para que cualquier fase o módulo registre una fuente de datos viva sin tocar los resolvedores.
- `ConceptoResolver` — contrato para sumar un séptimo driver si algún día hace falta.
- 23 permisos y el rol `consejo`.

**Consume**
- `core-documentos` (`document_templates` + `DocumentTemplateService` + dompdf) para generar documentos desde plantilla. **No lo reemplaza ni lo duplica** (ver `docs/analisis-modulo-plantillas-item-840.md`).
- `company_information` — sólo como fuente de siembra de razón social y RFC en `EmpresaSeeder`, y sólo si están poblados.
- `ModuleRegistry` / `ModuleLifecycleService` / `PermissionSyncService` — registro del módulo, permisos y navegación declarativa.
- Desde la Fase 1, vía `FuenteRegistry`: Clientes, Finanzas (incluido `App\Services\Cobranza\InvoiceAgingService`, punto único de antigüedad de saldos), Talento, Flotas y GestionRed/OLT.

## 5. Estado por fases
| Fase | Contenido | Item | Estado |
|------|-----------|------|--------|
| 0 | Cimiento: catálogo, resolvedores, permisos, bitácora, tablero | #662 | ✅ entregada |
| 1 | Apartados con datos vivos (IV, X, VII, V parcial) + exportación | #663 | ⏳ pendiente |
| 2 | Repositorio documental, bandeja de captura y plantillas (I, II, III, VI, IX) | #664 | ⏳ pendiente |
| 3 | Inventarios físicos, digitales y de accesos (V, VIII, XI) | #665 | ⏳ pendiente |
| 4 | Concesiones y calendario regulatorio (XIII) | #666 | ⏳ pendiente |
| 5 | Entrega-recepción, bitácora y offboarding (XII, XIV) | #667 | ⏳ pendiente — nivel C, decisión legal previa |

Ver también: `docs/documentacion-corporativa-absorbe-documentos-oficiales.md` (por qué no existe un addon `DocumentosOficiales` aparte).

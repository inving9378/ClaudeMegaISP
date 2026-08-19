# Análisis — ¿Conviene un módulo "Plantillas" real? (item #840)

**Origen:** detectado en el Paso 0 del item #795 (Documentos Oficiales, Parte A) — se
asumía la existencia de un módulo "Plantillas" y no existe. Este documento **es el
entregable completo del item #840**: solo análisis, sin cambios de código.

---

## 1. Inventario de lo que hay hoy

Hay tres piezas que hoy se relacionan con "documentos"/"plantillas", pero son en
realidad **dos sistemas**, no tres:

### 1.a — `core-documentos` + catálogo DB `modules` (son LA MISMA pieza, dos caras)

- **Admin CRUD:** `app/Modules/Core/Documentos/` — controllers
  `DocumentTemplateController` / `DocumentTypeTemplateController`, rutas bajo
  `/administracion/document_template*` y `/administracion/document_type_template*`.
  Modelos `App\Models\DocumentTemplate` (tabla `document_templates`) y
  `App\Models\DocumentTypeTemplate` (tabla `document_type_templates`).
- **Selector embebido:** el modal "Generar Contrato" en CRM (`CrmTemplate.vue`) y en
  Clientes (`PlantillasClientes.vue`) consulta el catálogo DB-driven `modules`/`fields`
  (fila `DocumentTemplateClient`, module id=62) para pintar el selector
  tipo→plantilla. Ese selector (`Select2TypeTemplateSelectComponent.vue`) lee
  **las mismas tablas** `document_templates`/`document_type_templates` de arriba.
  No es un sistema distinto: es la UI de consumo del mismo par de tablas que
  administra `core-documentos`.
- **Generación del PDF:** `ContractClientService::generateContractClient()` y
  `ContractCrmService::generateContractClient()` (uno por dominio, casi idénticos)
  resuelven la plantilla, reemplazan variables (`DocumentTemplateService`) y generan
  el PDF con dompdf.
- **Dato histórico relevante:** SÍ existió un módulo de catálogo llamado
  literalmente `Plantillas` (tabla `contract_templates`, modelo `ContractTemplate`),
  creado el 2024-07-21 y **borrado deliberadamente** el 2024-07-23
  (`database/migrations_old/2024_07_23_151106_delete_contract_templates_table.php`),
  reemplazado días después por el par `DocumentTemplate`/`DocumentTypeTemplate`
  (`core-documentos`). El modelo `ContractTemplate` sigue en `app/Models/` pero está
  **huérfano** (sin tabla, sin consumidores) — candidato a limpieza, fuera de alcance
  de este análisis.

### 1.b — Addon `DocumentosOficiales` (item #795) — **no existe código aún**

- Solo existe como plan aprobado (item #795, Parte A), con rama creada
  (`circuito/item-795-documentos-oficiales`) pero sin ningún commit.
- Su propio prompt ya resolvió el Paso 0 con la misma conclusión de esta vuelta:
  *"no colgarse de core-documentos"* y *"no crear el módulo Plantillas en esta
  vuelta"* — es decir, #795 ya nace sabiendo que `core-documentos` existe y decidió
  no fusionarse con él.
- Su dominio es distinto: repositorio **versionado de archivos** (actas, poderes,
  cédulas, contratos marco — PDF/Word subidos a mano, con historial de versiones),
  no un motor de generación de PDF desde plantillas HTML con variables. Tablas
  previstas `docof_*`, permisos previstos `documentos_oficiales.*` (granulares,
  estilo addon limpio — el patrón correcto, a diferencia de 1.a).

---

## 2. ¿Conviene unificar bajo un módulo "Plantillas" real?

**No.** `core-documentos`/catálogo DB (1.a) y `DocumentosOficiales` (1.b) resuelven
problemas de negocio genuinamente distintos:

| | 1.a (core-documentos) | 1.b (DocumentosOficiales) |
|---|---|---|
| Qué es | Motor de generación de PDF desde plantilla HTML + variables | Repositorio de archivos oficiales con versionado |
| Insumo | Plantilla editable (HTML) | Archivo ya terminado (PDF/Word) subido a mano |
| Output | Un PDF nuevo por cliente/CRM, cada vez | El mismo archivo, con historial de versiones |
| Consumidor | CRM, Clientes (contratos por cliente) | Administración (actas, poderes, cédulas de la empresa) |

Juntarlos bajo un solo módulo "Plantillas" forzaría una abstracción común que no
existe en el negocio — sería la clase de reescritura que el ADN del circuito pide
evitar ("no reescribas lo que sirve por estética"). La separación actual es
**preferible**. La premisa del item #795 (que ya decidió no fusionarse con
`core-documentos`) fue la decisión correcta y no requiere corrección.

Tampoco tiene sentido meter en el paquete a `list-template-verification`
(listas de verificación) ni `template-task` (plantillas de tareas de Scheduling):
comparten la palabra "plantilla" y por accidente el mismo permiso monolítico
`config_view_system`, pero son dominios sin relación con documentos/PDF.

---

## 3. Deuda real encontrada (no es el módulo — son los permisos)

Lo que sí es un problema real de `core-documentos`, expuesto por esta investigación:
sus rutas de administración (`/administracion/document_template*`,
`/administracion/document_type_template*`) cuelgan del permiso monolítico legado
`config_view_system` (`config/route_permission.php:1402-1443`), compartido sin
relación con `additional-fields`, `list-template-verification` y `template-task`.
Mientras tanto, el *uso* de esas plantillas para generar contratos sí tiene permisos
granulares propios (`crm_document_add_crm`, `client_document_add_client`, etc.).
Resultado: hoy un usuario puede tener permiso para generar contratos sin tener
permiso para administrar las plantillas que usa, y viceversa — dos fronteras de
permiso distintas sobre el mismo dato, con una de ellas (la de administración)
sin poder revocarse selectivamente porque va empaquetada con otras tres features
no relacionadas.

Esto **no es un problema de organización de módulos** (por eso no amerita "plan de
migración de rutas" que unifique nada) — es un problema de permisos granulares que
se puede resolver sin tocar la estructura de carpetas: dar a
`core-documentos` sus propios permisos por acción (`document_template.view/.manage`,
`document_type_template.view/.manage`, estilo `documentos_oficiales.*` de #795) y
retirar esas 2 rutas del permiso monolítico. Se registra como item de seguimiento
(#841 en adelante, ver Hoja de Ruta) porque es un cambio de permisos con blast radius
acotado pero real (toca acceso de usuarios), no algo para ejecutar en esta vuelta de
solo-análisis.

---

## 4. Conclusión / recomendación

1. **No crear un módulo "Plantillas" unificado.** La separación actual
   (`core-documentos` para generación de contratos vs. `DocumentosOficiales` para
   repositorio versionado) es la correcta; #795 ya lo decidió bien en su propio
   Paso 0.
2. **`DocumentosOficiales` (#795) sigue su curso como está planeado**, sin fusión.
3. **Deuda real a trackear aparte:** dar permisos granulares propios a
   `core-documentos` (retirarlo de `config_view_system`) — registrado como sub-item.
4. **Nota menor, sin acción en esta vuelta:** `app/Models/ContractTemplate.php` es
   código huérfano (sin tabla, sin consumidores) desde 2024-07-23; limpieza cosmética
   opcional para una vuelta futura de minimalismo, no urgente.

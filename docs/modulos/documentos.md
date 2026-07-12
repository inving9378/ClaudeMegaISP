# Módulo Documentos

> Plantillas de documentos (contratos, recibos, facturas, cartas) y sus tipos. `app/Modules/Core/Documentos/` · slug `core-documentos` · módulo **core**.

## 0. En simple
Es la máquina de plantillas: ahí se diseña una sola vez el formato de un contrato o recibo (con espacios que se llenan solos, como el nombre y la dirección del cliente), y luego cualquier ficha de cliente o prospecto lo genera en PDF con un clic.

## 1. Qué es
Módulo **core** que administra las **plantillas de documentos** (`DocumentTemplate`) y sus **tipos/categorías** (`DocumentTypeTemplate` — contrato, factura, recibo, carta…). No genera documentos por sí mismo para un cliente concreto; provee el catálogo de plantillas HTML con variables (`${data.campo}`) que otros módulos (Clientes, CRM) rellenan y convierten a PDF.

## 2. Para qué sirve
Le ahorra a administración tener que redactar cada contrato/recibo a mano: se diseña la plantilla una vez (con el editor de contenido enriquecido) usando variables como `${data.full_name}` o `${data.amount}`, y luego, desde la ficha de un cliente o de un prospecto CRM, se elige la plantilla y el sistema genera el PDF final ya con los datos reales sustituidos. También resuelve la vista previa del documento antes de guardarlo.

## 3. Cómo funciona
- **Modelos:** `App\Models\DocumentTemplate` (tabla `document_templates`, soft-deletes; campos `name`, `html`, `type` → FK lógica a `DocumentTypeTemplate`, `created_by`) y `App\Models\DocumentTypeTemplate` (tabla `document_type_templates`, catálogo simple de categorías).
- **Controllers** (`app/Modules/Core/Documentos/Controllers/`): `DocumentTemplate\DocumentTemplateController` (CRUD de plantillas + `loadContentTemplate`/`showContentTemplate`/`getVariables`/`getDataTemplate`, listado vía datatable) y `DocumentTypeTemplate\DocumentTypeTemplateController` (CRUD simple de tipos, extiende el `CrudModalController` base compartido).
- **Repositorio:** `App\Http\Repository\DocumentTemplateRepository` — queries simples sobre `DocumentTemplate` (`getHtmlById`, `getNameById`, `getModelById`, `createDocumentTemplate`).
- **Servicio central:** `App\Services\DocumentTemplateService` — concentra dos cosas: (1) el **catálogo de variables** disponibles por módulo consumidor (`DATA_CLIENT_VARIABLES_VALUE`, con secciones `Client`, `Crm` y `Comun` — datos de empresa, pagos/facturas dinámicos), y (2) `validateAndReplaceTemplate()`, que recorre el HTML de la plantilla, sustituye cada `${data.campo}` por su valor real (o reporta como error las variables no reconocidas), arma tablas HTML anidadas para servicios/pagos pendientes, y delega en `Barryvdh\DomPDF` (`Pdf::loadHTML()`) para producir el PDF final (guardado en `storage/app/public/document_template/document/` o devuelto inline como preview).
- **Rutas:** `app/Modules/Core/Documentos/routes.php`, bajo `administracion/document_template` y `administracion/document_type_template`, con middleware `['web', 'auth', 'check_route_permission']` (autorización por URL, patrones registrados en `config/route_permission.php`). Accesibles desde Configuración → Plantillas / Tipos de Plantillas (gate `config_view_system`).
- **Frontend:** `resources/js/components/module/adminstration/document_template/` (`DocumentTemplateListar.vue` listado + `TemplateManager.vue` editor con `TextTemplate.vue` como editor de contenido enriquecido compartido) y el selector reusable `resources/js/shared/Select2TypeTemplateSelectComponent.vue`. El catálogo de campos del formulario "Generar Contrato" (módulo `DocumentTemplateClient`, consumido por `CrmTemplate.vue` y `PlantillasClientes.vue`) es **DB-driven** vía la tabla `modules`/`fields` (ver nota en `CLAUDE.md` sobre data-drift dev↔prod de este catálogo).

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Rutas web/AJAX** bajo `administracion/document_template/*` y `administracion/document_type_template/*`: listar, tabla (datatable), cargar/mostrar contenido de una plantilla, obtener variables disponibles por módulo, alta/edición/borrado (soft-delete), preview PDF inline.
- **`DocumentTemplateService`** como servicio compartido: `getVariables($module)` (catálogo de variables por módulo) y `validateAndReplaceTemplate($html, $data, $module)` (motor de sustitución + validación), consumidos directamente por otros módulos que generan documentos.
- **Modelos `DocumentTemplate`/`DocumentTypeTemplate`** — punto de anclaje (`template`/`type`) que referencian los generadores de contrato de Clientes y CRM.
- **2 secciones de configuración** (`plantillas_doc`, `tipos_plantillas`) en Configuración → Sistema, gateadas por `config_view_system`.

**Consume**
- **`Barryvdh\DomPDF`** (`Pdf::loadHTML()`) — motor de renderizado HTML→PDF, único punto de conversión.
- **`Storage::disk('public')`** — persiste el PDF generado en `document_template/document/{nombre}.pdf`.
- **`CompanyInformationRepository`** (módulo Configuración) — datos de la empresa (`data.company_name`, `data.rfc`, `data.url_logo`…) para las variables `Comun`.
- **Servicios de datos de otros módulos** para armar el contexto de sustitución: `App\Modules\Core\Clientes\Services\ContractClientService::getDataClient()` (módulo Clientes) y `App\Modules\Core\CRM\Services\ContractCrmService::getData()` (módulo CRM) — estos dos son los consumidores reales que invocan `DocumentTemplateController::showContentTemplate` para generar el contrato final de un cliente o un prospecto concreto.

> _Servicio compartido único: este módulo no monta su propio motor de PDF por caso de uso; DomPDF vive centralizado aquí y Clientes/CRM lo consumen a través de `DocumentTemplateService`, en línea con la regla de "servicios compartidos únicos, prohibido duplicar" del proyecto._

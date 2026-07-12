# Módulo CRM

> Prospectos, leads y seguimiento comercial. `app/Modules/Core/CRM/` · slug `core-crm` · módulo core, activo.

**En simple:** es la agenda donde el vendedor anota a la gente interesada en contratar, le da seguimiento hasta que decide contratar, y ahí mismo lo convierte en cliente.

## 1. Qué es
Módulo de **gestión de prospectos (leads)**: captura contactos interesados, registra su seguimiento comercial (documentos, contrato, último contacto) y los **convierte en cliente** cuando cierran la venta.

## 2. Para qué sirve
Le da al equipo de **ventas/vendedores** un lugar único para llevar un prospecto desde que llega (referido, campaña, web, WhatsApp) hasta que se convierte en cliente activo: capturar sus datos, adjuntar/generar documentos (incluye contrato en PDF), marcar cuándo se le contactó por última vez, y al cerrar la venta pasar toda su información a un `Client` real sin recapturar. El **dashboard** (`/crm`) da la vista general del embudo.

## 3. Cómo funciona
- **Modelos** (`app/Modules/Core/CRM/Models/`), todos `BaseModel`:
  - `Crm` (tabla `crms`) — registro raíz del prospecto; agrupa por relación 1:1 a `CrmMainInformation` (tabla `crm_main_information`, datos de contacto/captura) y `CrmLeadInformation` (tabla `crm_lead_information`, datos del seguimiento comercial: `owner_id`→`Seller`, `last_contacted`, comisiones/pagos asociados). `DocumentCrm` (tabla `document_crms`) cuelga de `Crm` para los documentos/contratos del prospecto. `DealCrm` (tabla `deal_crms`) es un modelo de apoyo con scope de búsqueda, sin controller propio en este módulo.
  - Alta (`CrmController::store`): crea `Crm` → `crm_main_information` → `crm_lead_information` en una transacción, dispara el evento `App\Events\ProspectRegistered` y registra un `log_activities` (morph a `LogActivity`).
- **Controllers** (`app/Modules/Core/CRM/Controllers/`):
  - `CrmController` — listar/crear/editar/eliminar el prospecto, tabla server-side (`table` vía `CrmDatatableHelper`), `updateLastContacted`, y `convertToClient` (ver abajo).
  - `CrmInformationController` — actualiza los datos de contacto/seguimiento (`update`) y también puede disparar `ProspectRegistered`.
  - `DocumentCrmController` — CRUD de documentos del prospecto, subida de archivo, **generación de contrato en PDF** (`generateContract`, delega en `ContractCrmService`) y plantillas de contenido.
  - `DashboardController` — pantalla `/crm` (KPIs/vista general; solo `index` implementado, el resto del resource está vacío).
- **Servicio:** `ContractCrmService::generateContractClient` — toma una plantilla (`DocumentTemplateService`), reemplaza los campos del prospecto, renderiza HTML→PDF (`barryvdh/laravel-dompdf`), lo guarda en `storage/public/client/{id}/document/` y lo registra como `DocumentCrm`.
- **Repositorios:** `App\Modules\Core\CRM\Repositories\{Crm,DocumentCrm}Repository` (queries no triviales). Existe además un sistema paralelo en `app/Repositories/Crm*Repository.php` (basado en `BaseRepository`) sin consolidar — ver `MIGRATION.md` del módulo.
- **Conversión a cliente** (`CrmController::convertToClient`, transacción completa): crea un `Client`, copia los campos configurados de los módulos de catálogo `ClientMainInformation`/`ClientAdditionalInformation` (resueltos vía la tabla `modules`/`fields`, igual que el resto del sistema de formularios dinámicos), migra los documentos del CRM al cliente (`addDocumentFromOlderCrm`), corre los pasos de alta de cliente (`ClientHelperController::stepNeededWhenNewClientIsCreated`), registra actividad y **elimina el registro `Crm`** (el prospecto deja de existir; su rastro queda en el `Client` y en el log de actividad).
- **Rutas:** todas bajo `/crm/*`, con middleware `['web', 'auth', 'check_route_permission']` (autorización por URL).
- **Frontend:** un solo bundle Vue (`resources/js/components/module/crm/`) — `CrmCrud`/`AddCrmCrud` (alta/edición), `CrmDatatable` (listado), `InformationCrmCrud` (tab de datos), `document/*` (tab de documentos + plantilla de contrato), `components/ConvertToClient.vue` (modal de conversión), `dashboard/DashboardCrm.vue`.

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas web** `/crm` (dashboard), `/crm/listar`, `/crm/crear`, `/crm/editar/{id}`, `/crm/view-of-convert-crm-to-client/{id}`, + endpoints POST de datos (`/crm/add`, `/crm/update/{id}`, `/crm/convert-to-client/{id}`, `/crm/update-last-contacted/{id}`, `/crm/destroy/{id}`, `/crm/table`) y el sub-grupo `/crm/document/*` (alta, edición, subida, tabla, borrado, generar contrato, plantillas).
- **Permisos** `crm_view_crm`, `crm_add_crm`, `crm_information_view_tab_crm`, `crm_document_view_tab_crm` (+ pares edit/delete/export análogos del mismo patrón que otros módulos core).
- **Evento** `App\Events\ProspectRegistered` — se dispara al crear/actualizar la información del prospecto.
- **Menú** en el sidebar (`module-sidebar.crm`).

**Consume**
- **Listener `App\Listeners\CalculateProspectCommission`** (suscrito a `ProspectRegistered` en `EventServiceProvider`) — calcula comisión del vendedor al registrarse el prospecto; el CRM no calcula comisiones directamente, solo emite el evento.
- **Módulo Clientes** (`App\Models\Client`, `ClientMainInformation`, `ClientAdditionalInformation`, `App\Modules\Core\Clientes\Controllers\ClientHelperController`) — destino de la conversión prospecto→cliente.
- **Sistema de módulos/campos dinámicos** (`App\Models\Module` + tabla `fields`) — de ahí resuelve qué columnas copiar al convertir a cliente.
- **Plantillas de documentos** (`DocumentTemplateRepository`/`DocumentTemplateService`) y **DomPDF** — para generar el contrato del prospecto.
- **Vendedores** (`App\Models\Seller`, vía `CrmLeadInformation::owner_id`) — dueño/responsable del seguimiento.

> _Servicios compartidos únicos: este módulo no monta WhatsApp/IA/Mapas propios; cualquier futura notificación al prospecto debe usar el gateway único `WhatsAppGateway` (ver #308 / contratos inter-módulos)._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._

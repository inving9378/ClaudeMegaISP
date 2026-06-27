require("./bootstrap");

// Fix 2026-06-04: el bundle viejo del tema Minia controlaba .right-bar-enabled.
// Al eliminarlo, la clase puede quedar atascada en body activando un overlay z-index 9998.
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.remove('right-bar-enabled');
});

import { createApp } from "vue";
import store from "./store";
import hasPermission from "./directives/hasPermission";
import QTableResizable from "./directives/QTableResizable";

//Dashboard
import Dashboard from "./components/module/dashboard/Dashboard";

//Planes
import InternetCrud from "./components/module/planes/InternetCrud";
import VozCrud from "./components/module/planes/VozCrud";
import CustomCrud from "./components/module/planes/CustomCrud";
import BundleCrud from "./components/module/planes/BundleCrud";

//CRM
import CrmCrud from "./components/module/crm/CrmCrud";
import AddCrmCrud from "./components/module/crm/AddCrmCrud";
import ConvertToClient from "./components/module/crm/components/ConvertToClient";
import CrmDatatable from "./components/module/crm/CrmDatatable";

//Client
import ClientCrud from "./components/module/client/ClientCrud";
import AddClientCrud from "./components/module/client/AddClientCrud";
import DatatableClient from "./components/module/client/DatatableClient.vue";
import DashboardClient from "./components/module/client/DashboardClient.vue";

import Datatable from "./components/base/shared/Datatable";

import Message from "./shared/Message";
import Breadcrumb from "./components/base/shared/Breadcrumb";

//Router
import AddRouterCrud from "./components/module/router/AddRouterCrud";
import RouterCrud from "./components/module/router/RouterCrud";

//network
import NetworkListar from "./components/module/network/NetworkListar";
import AddNetworkCrud from "./components/module/network/AddNetworkCrud";
import NetworkVer from "./components/module/network/NetworkVer";

//Administracion
import ListarRol from "./components/module/adminstration/rol/ListarRol.vue";
import PartnerListar from "./components/module/adminstration/partner/PartnerListar";
import LocationListar from "./components/module/adminstration/location/LocationListar";
import SucursalListar from "./components/module/adminstration/sucursal/SucursalListar.vue";
import StateListar from "./components/module/adminstration/state/StateListar";
import MunicipalityListar from "./components/module/adminstration/municipality/MunicipalityListar";
import ColonyListar from "./components/module/adminstration/colony/ColonyListar";
import WorkFlowListar from "./components/module/setting/workflow/WorkFlowListar.vue";
import TypeTemplateListar from "./components/module/adminstration/document_type_template/TypeTemplateListar.vue";
import PackageList from "./components/module/adminstration/package/PackageList";
import MethodOfPaymentListar from "./components/module/adminstration/methodofpayment/MethodOfPaymentListar";
import IftListar from "./components/module/adminstration/ift/IftListar";
import IndexAdministration from "./components/module/adminstration/IndexAdministration.vue";
import DocumentTemplateListar from "./components/module/adminstration/document_template/DocumentTemplateListar.vue";

//Planes — Servicios contratables
import ContratableCatalogList from "./components/module/planes/ContratableCatalogList.vue";
import ContratableCatalogForm from "./components/module/planes/ContratableCatalogForm.vue";
import ContratablesClientTab from "./components/module/planes/ContratablesClientTab.vue";

//Configuracion
import CatalogoApiPanel from "./components/module/configuracion/CatalogoApiPanel.vue";
import DebtPaymentClient from "./components/module/setting/DebtPaymentClient";
import DebitCustomListar from "./components/module/setting/DebitCustomListar";
import CommandConfig from "./components/module/setting/CommandConfig";
import FieldModuleListar from "./components/module/setting/FieldModuleListar";
import IndexSetting from "./components/module/setting/IndexSetting.vue";
import ImportListar from "./components/module/setting/ImportListar.vue";
import ImportCrud from "./components/module/setting/components/tools/ImportCrud.vue";
import SmartImport from "./components/module/setting/SmartImport.vue";
import SmartExport from "./components/module/setting/SmartExport.vue";
import ImportExportHistory from "./components/module/setting/ImportExportHistory.vue";
import ShowActivity from "./components/module/adminstration/activity_log/ShowActivity.vue";
import ApiMovilConfig from "./components/module/setting/api-movil/ApiMovilConfig.vue";
import ApiMovilTokens from "./components/module/setting/api-movil/ApiMovilTokens.vue";
import ApiMovilDocs from "./components/module/setting/api-movil/ApiMovilDocs.vue";
import ApiMovilLogs from "./components/module/setting/api-movil/ApiMovilLogs.vue";
import ModuleManager from "./components/module/admin/modules/ModuleManager.vue";
import ModuleVisibilityConfig from "./components/admin/ModuleVisibilityConfig.vue";
import ChangePasswordForm from "./components/profile/ChangePasswordForm.vue";
import AdminPanel from "./components/module/admin/AdminPanel.vue";
import ModuleConfigPanel from "./components/module/setting/ModuleConfigPanel.vue";
import DevtoolsPanel from "./components/module/devtools/DevtoolsPanel.vue";

// MegaFamilia (addon-megafamilia) — 1 dashboard + 17 scaffolds
import MegaFamiliaDashboard from "./components/module/megafamilia/MegaFamiliaDashboard.vue";
import MegaFamiliaClientes from "./components/module/megafamilia/MegaFamiliaClientes.vue";
import MegaFamiliaLicencias from "./components/module/megafamilia/MegaFamiliaLicencias.vue";
import MegaFamiliaPlanes from "./components/module/megafamilia/MegaFamiliaPlanes.vue";
// Portal de Pago (addon-portal-pago)
import PagosDashboard from "./components/module/portalpago/PagosDashboard.vue";
import PagosConciliacion from "./components/module/portalpago/PagosConciliacion.vue";
import PagosCuentas from "./components/module/portalpago/PagosCuentas.vue";
import PagosLinks from "./components/module/portalpago/PagosLinks.vue";
import MegaFamiliaIngresos from "./components/module/megafamilia/MegaFamiliaIngresos.vue";
import MegaFamiliaAlertas from "./components/module/megafamilia/MegaFamiliaAlertas.vue";
import MegaFamiliaSolicitudes from "./components/module/megafamilia/MegaFamiliaSolicitudes.vue";
import MegaFamiliaPerfiles from "./components/module/megafamilia/MegaFamiliaPerfiles.vue";
import MegaFamiliaDispositivos from "./components/module/megafamilia/MegaFamiliaDispositivos.vue";
import MegaFamiliaTareas from "./components/module/megafamilia/MegaFamiliaTareas.vue";
import MegaFamiliaUbicaciones from "./components/module/megafamilia/MegaFamiliaUbicaciones.vue";
import MegaFamiliaGeofences from "./components/module/megafamilia/MegaFamiliaGeofences.vue";
import MegaFamiliaReportes from "./components/module/megafamilia/MegaFamiliaReportes.vue";
import MegaFamiliaAuditoria from "./components/module/megafamilia/MegaFamiliaAuditoria.vue";
import MegaFamiliaMikrotik from "./components/module/megafamilia/MegaFamiliaMikrotik.vue";
import MegaFamiliaNotificaciones from "./components/module/megafamilia/MegaFamiliaNotificaciones.vue";
import MegaFamiliaSoporte from "./components/module/megafamilia/MegaFamiliaSoporte.vue";
import MegaFamiliaTerminos from "./components/module/megafamilia/MegaFamiliaTerminos.vue";
import MegaFamiliaConfiguracion from "./components/module/megafamilia/MegaFamiliaConfiguracion.vue";
// Pestaña en la ficha de cliente (infra de ficha extensible — roadmap #5)
import MegaFamiliaClientTab from "./components/module/megafamilia/MegaFamiliaClientTab.vue";
// Domiciliación a Tarjeta (addon-domiciliacion)
import DomiciliacionClientTab from "./components/module/domiciliacion/DomiciliacionClientTab.vue";
// Embajadores Meganet (addon-embajadores) — programa de referidos multinivel
import EmbajadoresDashboard from "./components/module/embajadores/EmbajadoresDashboard.vue";
import EmbajadoresConfiguracion from "./components/module/embajadores/EmbajadoresConfiguracion.vue";
import EmbajadoresTiers from "./components/module/embajadores/EmbajadoresTiers.vue";
import EmbajadoresClientes from "./components/module/embajadores/EmbajadoresClientes.vue";
import EmbajadoresComisiones from "./components/module/embajadores/EmbajadoresComisiones.vue";
import EmbajadoresArbol from "./components/module/embajadores/EmbajadoresArbol.vue";

// War Room Ejecutivo (addon-warroom)
import WarroomDashboard from "./components/module/warroom/WarroomDashboard.vue";
import WarroomLineSeries from "./components/module/warroom/views/WarroomLineSeries.vue";

// Flotas (addon-flotas) — gestión de flota vehicular
import FleetDashboard from "./components/module/flotas/FleetDashboard.vue";
import FleetVehicleForm from "./components/module/flotas/FleetVehicleForm.vue";
import FleetVehicleShow from "./components/module/flotas/FleetVehicleShow.vue";
import FleetTabInfo from "./components/module/flotas/FleetTabInfo.vue";
import FleetTabAsignacion from "./components/module/flotas/FleetTabAsignacion.vue";
import FleetTabMantenimientos from "./components/module/flotas/FleetTabMantenimientos.vue";
import FleetTabDocumentos from "./components/module/flotas/FleetTabDocumentos.vue";
import FleetTabCombustible from "./components/module/flotas/FleetTabCombustible.vue";
import FleetTabGps from "./components/module/flotas/FleetTabGps.vue";
import FleetTabHistorial from "./components/module/flotas/FleetTabHistorial.vue";
import FleetTabFotos from "./components/module/flotas/FleetTabFotos.vue";
import FleetMap from "./components/module/flotas/FleetMap.vue";
import FleetGeofenceList from "./components/module/flotas/FleetGeofenceList.vue";
import FleetGeofenceForm from "./components/module/flotas/FleetGeofenceForm.vue";
import FleetGeofenceShow from "./components/module/flotas/FleetGeofenceShow.vue";
import FleetNotificationLog from "./components/module/flotas/FleetNotificationLog.vue";
import FleetRuleList from "./components/module/flotas/FleetRuleList.vue";
import FleetDocumentsDashboard from "./components/module/flotas/FleetDocumentsDashboard.vue";
import FleetClientPlanTab from "./components/module/flotas/FleetClientPlanTab.vue";
import FleetSubscriptionDashboard from "./components/module/flotas/FleetSubscriptionDashboard.vue";
import TalentoColaboradores from "./components/module/talento/TalentoColaboradores.vue";
import TalentoCustodia from "./components/module/talento/TalentoCustodia.vue";
import TalentoDispositivos from "./components/module/talento/TalentoDispositivos.vue";
import TalentoRoadmap from "./components/module/talento/TalentoRoadmap.vue";
import TalentoOrdenes from "./components/module/talento/TalentoOrdenes.vue";
import TalentoCompensacion from "./components/module/talento/TalentoCompensacion.vue";
import TalentoLiquidaciones from "./components/module/talento/TalentoLiquidaciones.vue";
import TalentoAsistencia from "./components/module/talento/TalentoAsistencia.vue";
import TalentoMapaVivo from "./components/module/talento/TalentoMapaVivo.vue";
import TalentoSitios from "./components/module/talento/TalentoSitios.vue";
import TalentoCampo from "./components/module/talento/TalentoCampo.vue";
import TalentoCajas from "./components/module/talento/TalentoCajas.vue";
import TalentoRutas from "./components/module/talento/TalentoRutas.vue";
import TalentoProyectos from "./components/module/talento/TalentoProyectos.vue";
import TalentoCalidad from "./components/module/talento/TalentoCalidad.vue";
import TalentoPenalizaciones from "./components/module/talento/TalentoPenalizaciones.vue";
import TalentoCredenciales from "./components/module/talento/TalentoCredenciales.vue";
import TalentoFiniquito from "./components/module/talento/TalentoFiniquito.vue";
import TalentoAcademia from "./components/module/talento/TalentoAcademia.vue";
import TalentoNiveles from "./components/module/talento/TalentoNiveles.vue";
import TalentoDashboard from "./components/module/talento/TalentoDashboard.vue";
import TalentoEscalafon from "./components/module/talento/TalentoEscalafon.vue";
import TalentoEmbajadores from "./components/module/talento/TalentoEmbajadores.vue";
import TalentoEvidenciaConfig from "./components/module/talento/TalentoEvidenciaConfig.vue";

//Mapas
import GoogleMap from "./components/base/googlemap/GoogleMap";
import LeafletMap from "./components/module/maps/LeafletMap.vue";

//Ticket
import DashboardTicket from "./components/module/tickets/DashboardTicket";
import TicketCrud from "./components/module/tickets/TicketCrud";
import VerTicket from "./components/module/tickets/VerTicket";

//Topbar
import NotificationTopbar from "./shared/NotificationTopbar";
import ModeVisualBody from "./shared/ModeVisualBody";

//Perfil
import Perfil from "./components/perfil/Perfil";
import PerfilEdit from "./components/perfil/PerfilEdit";

//utils
import MessageResponse from "./components/base/utils/MessageResponse";

//Finance setting
import MethodPayment from "./components/module/finance/config/MethodPayment.vue";

//Vendedores
import DashboardSellers from "./components/module/vendors/Dashboard.vue";
import VendedorListar from "./components/module/vendors/VendedorListar.vue";
import MenuSeller from "./components/module/vendors/Menu.vue";
import AddPayment from "./components/module/vendors/billing/AddPayment.vue";
import CredentialConfig from "./components/module/vendors/information/CredentialConfig.vue";
import Panel from "./components/module/vendors/Panel.vue";

// config sales
import MediumSale from "./components/module/vendors/medium/MediumSale.vue";
import Commission from "./components/module/vendors/commissions/Index.vue";
import AddRule from "./components/module/vendors/commissions/AddRule.vue";
import EditRule from "./components/module/vendors/commissions/EditRule.vue";
import ListSellers from "./components/module/vendors/commissions/ListSellers.vue";
import TypeSeller from "./components/module/vendors/type-seller/Index.vue";
import StatusSeller from "./components/module/vendors/status-sellers/Index.vue";
import RangeSaleConfig from "./components/module/vendors/ranges/RangeSaleConfig.vue";
import RegisterExternal from "./components/module/vendors/RegisterExternal.vue";

// Administradores
import UserListar from "./components/module/adminstration/user/UserListar.vue";
import UserAdd from "./components/module/adminstration/user/UserAdd.vue";
import UserEdit from "./components/module/adminstration/user/UserEdit.vue";

//maps
import ApiKeyConfig from "./components/module/maps/ApiKey.vue";

//scheduling
import ProjectListar from "./components/module/scheduling/project/ProjectListar.vue";
import TaskListar from "./components/module/scheduling/task/TaskListar.vue";

import VueApexCharts from "vue3-apexcharts";
import QCalendar from "./shared/QCalendar.vue";

//OLTS
import OltsPanel from "./components/module/olts/OltsPanel.vue";
import OltRedMap from "./components/module/olts/OltRedMap.vue";
import OltProvisionPreview from "./components/module/olts/components/OltProvisionPreview.vue";

//Importar componentes quasar
import {
    QBtn,
    QInput,
    QDialog,
    QCard,
    QSpace,
    QCardSection,
    Dialog,
    QImg,
    QFile,
    QAvatar,
    QBtnGroup,
    QEditor,
    QIcon,
    QItem,
    QItemSection,
    QList,
    QBtnDropdown,
    QTable,
    QSelect,
    QTd,
    QTr,
    QTh,
    QPagination,
    QCheckbox,
    QRating,
    QBadge,
    QItemLabel,
    QToggle,
    QPage,
    QLayout,
    QPageContainer,
    QSeparator,
    QHeader,
    QBar,
    QMenu,
    QTab,
    QTabs,
    QTabPanel,
    QTabPanels,
    QOptionGroup,
    QBtnToggle,
    QColor,
    QPopupProxy,
    QScrollArea,
    QToolbar,
    QToolbarTitle,
    QTooltip,
    QForm,
    QCardActions,
    QExpansionItem,
    QChip,
    QDate,
    QInnerLoading,
    QSpinnerFacebook,
    QTree,
    QSplitter,
    QSlider,
    QVirtualScroll,
    useQuasar,
    Screen,
    QBanner,
    QDrawer,
    QFooter,
    QPageSticky,
    QPageScroller,
    LocalStorage,
    QField,
    QLinearProgress,
    QSkeleton,
    QMarkupTable,
    QRadio,
    QSpinner,
    QSpinnerIos,
    QStepper,
    QStep,
    QUploader,
} from "./../../public/plugins/quasar/js/quasar.umd.prod";
import PaymentListar from "./components/module/finance/payment/PaymentListar.vue";
import ShowScripts from "./components/module/adminstration/show_scripts/ShowScripts.vue";
import TaskEdit from "./components/module/scheduling/task/TaskEdit.vue";
import ListTemplateVerificationListar from "./components/module/setting/list_template_verification/ListTemplateVerificationListar.vue";
import CalendarIndex from "./components/module/scheduling/calendar/CalendarIndex.vue";
import TemplateTaskListar from "./components/module/setting/template_task/TemplateTaskListar.vue";
import NomenclatureListar from "./components/module/setting/nomenclature/NomenclatureListar.vue";
import TeamListar from "./components/module/setting/team/TeamListar.vue";
import ServiceInAddressListListar from "./components/module/setting/service_in_address_list/ServiceInAddressListListar.vue";
import CompanyInformationIndex from "./components/module/setting/companyinformation/CompanyInformationIndex.vue";
import CompanyInformationEdit from "./components/module/setting/companyinformation/CompanyInformationEdit.vue";

// Documentation
import DocumentationMenuListar from "./components/module/adminstration/documentation/documentation_menu/DocumentationMenuListar.vue";
import DocumentationSubmenuListar from "./components/module/adminstration/documentation/documentation_submenu/DocumentationSubmenuListar.vue";
import DocumentationContent from "./components/module/adminstration/documentation/documentation_content/DocumentationContent.vue";
import DocumentationTreeMenu from "./components/module/adminstration/documentation/DocumentationTreeMenu.vue";

import InventoryItemTypeListar from "./components/module/inventory/inventory_item_type/InventoryItemTypeListar.vue";
import InventoryMovementListar from "./components/module/inventory/inventory_movement/InventoryMovementListar.vue";
import InventoryStoreListar from "./components/module/inventory/inventory_store/InventoryStoreListar.vue";
import InventoryItemStockListar from "./components/module/inventory/inventory_item_stock/InventoryItemStockListar.vue";
import SellerListar from "./components/module/sellers/seller/SellerListar.vue";
import RulesListar from "./components/module/setting/rules/RulesListar.vue";
import FormRule from "./components/module/setting/rules/FormRule.vue";
import Store from "./components/module/inventory/inventory_store/Store.vue";
import BillingReminderEdit from "./components/module/setting/billing_reminder/BillingReminderEdit.vue";
import EmailSettingEdit from "./components/module/setting/email_setting/EmailSettingEdit.vue";
import StoreZoneListar from "./components/module/inventory/store_zone/StoreZoneListar.vue";
import ConfigFinanceNotificationIndex from "./components/module/setting/finance/notifications/ConfigFinanceNotificationIndex.vue";
import Inbox from "./components/module/message/inbox/Inbox.vue";
import MediaItem from "./components/module/inventory/inventory_item_media/MediaItem.vue";
import InvoiceListar from "./components/module/finance/invoice/InvoiceListar.vue";
import GeneralAccountingIndex from "./components/module/finance/general_accounting/GeneralAccountingIndex.vue";
import ReleasesIndex from "./components/module/releases/ReleasesIndex.vue";
import ReleasesDescription from "./components/module/releases/ReleasesDescription.vue";
import RoadmapTab from "./components/module/releases/RoadmapTab.vue";
import DeployProgressModal from "./components/module/releases/DeployProgressModal.vue";
import UpdateBanner from "./components/module/releases/UpdateBanner.vue";
import InventoryItemCustomModelListar from "./components/module/inventory/inventory_item_custom_model/InventoryItemCustomModelListar.vue";
import InventoryItemCustomListar from "./components/module/inventory/inventory_item_custom/InventoryItemCustomListar.vue";

//IA — portado desde MEGANET 2026-05-19
import IAChatIndex from "./components/module/ia/IAChatIndex.vue";
import IAHistorial from "./components/module/ia/IAHistorial.vue";
import IAPrompts from "./components/module/ia/IAPrompts.vue";
import IAConfiguracion from "./components/module/ia/IAConfiguracion.vue";

//Manual de Usuario (addon-manual)
import ManualIndex from "./components/module/manual/ManualIndex.vue";
//Ayuda contextual por pantalla (panel flotante estilo Splynx)
import HelpFloat from "./components/ayuda/HelpFloat.vue";

//Evaluador Empresarial — portado desde MEGANET 2026-05-22
import EvaluadorEmpresarial from "./components/module/sellers/EvaluadorEmpresarial.vue";
import EvaluacionesDashboard from "./components/module/sellers/EvaluacionesDashboard.vue";

//Marketing — addon-marketing 2026-05-25
import MarketingDashboard from "./components/module/marketing/MarketingDashboard.vue";
import MarketingCampaigns from "./components/module/marketing/MarketingCampaigns.vue";
import MarketingCampaignShow from "./components/module/marketing/MarketingCampaignShow.vue";
import MarketingContentGenerator from "./components/module/marketing/MarketingContentGenerator.vue";
import MarketingLeads from "./components/module/marketing/MarketingLeads.vue";
// Marketing Fase 2 — leads & forms
import MarketingLeadsView from "./components/module/marketing/MarketingLeadsView.vue";
import MarketingLeadDetail from "./components/module/marketing/MarketingLeadDetail.vue";
import MarketingLeadFormsView from "./components/module/marketing/MarketingLeadFormsView.vue";
import MarketingLeadFormEditor from "./components/module/marketing/MarketingLeadFormEditor.vue";
import MarketingLeadForm from "./components/module/marketing/MarketingLeadForm.vue";
import LeadScoreBadge from "./components/module/marketing/LeadScoreBadge.vue";
import LeadStatusBadge from "./components/module/marketing/LeadStatusBadge.vue";
import LeadSourceChip from "./components/module/marketing/LeadSourceChip.vue";
import LeadActivityTimeline from "./components/module/marketing/LeadActivityTimeline.vue";
// Marketing Fase 3 — conversaciones WhatsApp
import MarketingConversationsView from "./components/module/marketing/MarketingConversationsView.vue";
import ConversationListItem from "./components/module/marketing/ConversationListItem.vue";
import MessageBubble from "./components/module/marketing/MessageBubble.vue";
// Marketing Fase 4 — Motor de Video Medussa (MVM)
import MarketingVideoTemplatesView from "./components/module/marketing/MarketingVideoTemplatesView.vue";
import MarketingVideoGeneratorView from "./components/module/marketing/MarketingVideoGeneratorView.vue";
import MarketingVideoQueueView from "./components/module/marketing/MarketingVideoQueueView.vue";
import MarketingBrandKitView from "./components/module/marketing/MarketingBrandKitView.vue";
// Marketing Fase 4.5b — Director Creativo IA
import MarketingCampaignGeneratorView from "./components/module/marketing/MarketingCampaignGeneratorView.vue";
import VoiceComparatorView from "./components/module/marketing/VoiceComparatorView.vue";

// Marketing Fase 5 — Publicador Multicanal
import MarketingPublishingDashboardView from "./components/module/marketing/publishing/PublishingDashboardView.vue";
import MarketingPublishCampaignView from "./components/module/marketing/publishing/PublishCampaignView.vue";
import MarketingPublicationQueueView from "./components/module/marketing/publishing/PublicationQueueView.vue";
import MarketingChannelsSetupView from "./components/module/marketing/publishing/ChannelsSetupView.vue";
import IntegrationsHubView from "./components/module/hub/IntegrationsHubView.vue";
import MessageDayDivider from "./components/module/marketing/MessageDayDivider.vue";
import AgentToggleButton from "./components/module/marketing/AgentToggleButton.vue";

//WhatsApp Agent — 2026-05-22
import WhatsAppPanel from "./components/module/whatsapp/WhatsAppPanel.vue";
import WhatsAppInstanceManager from "./components/module/whatsapp/WhatsAppInstanceManager.vue";

//Payments (SPEI / OpenPay) — 2026-05-23
import PaymentMethods from "./components/module/finance/PaymentMethods.vue";
import ClientClabeCard from "./components/module/client/ClientClabeCard.vue";

// CobranzaBlaster (Fase 6) — 2026-05-29
import CobranzaVoipConfig from "./components/module/cobranza/CobranzaVoipConfig.vue";
import CobranzaCampanas from "./components/module/cobranza/CobranzaCampanas.vue";

// VoIP — addon-voip Fase A+B+D2 (Troncales + Extensiones + Grupos + IA Bot) — 2026-06-09
import VoipTroncales      from "./components/module/voip/VoipTroncales.vue";
import VoipExtensiones    from "./components/module/voip/VoipExtensiones.vue";
import VoipGruposTimbrado from "./components/module/voip/VoipGruposTimbrado.vue";
import VoipIaBotManager   from "./components/module/voip/VoipIaBotManager.vue";


import SupplierListar from "./components/module/inventory/supplier/SupplierListar.vue";
import SupplierCrear from "./components/module/inventory/supplier/SupplierCrear.vue";
import SupplierShow from "./components/module/inventory/supplier/SupplierShow.vue";
import SupplierInvoiceListar from "./components/module/inventory/supplier_invoice/SupplierInvoiceListar.vue";
import SupplierInvoiceCreate from "./components/module/inventory/supplier_invoice/SupplierInvoiceCreate.vue";
import SupplierInvoiceShow from "./components/module/inventory/supplier_invoice/SupplierInvoiceShow.vue";
import InventoryValuation from "./components/module/inventory/valuation/InventoryValuation.vue";
import SupplierVendorCrear from "./components/module/inventory/supplier_vendor/SupplierVendorCrear.vue";
import DataPlanPromotionTable from "./components/module/setting/data_plan_promotion/DataPlanPromotionTable.vue";

//Promociones de cliente
import PromotionsComponent from "./components/module/client/PromotionsComponent.vue";
import ClientsToPromotionComponent from "./components/module/client/ClientsToPromotionComponent.vue";
import ColorPicker from "./shared/ColorPicker.vue";


function createMainApp() {
const app = createApp({
    components: {
        //Dashboard
        Dashboard,

        // Planes
        InternetCrud,
        VozCrud,
        CustomCrud,
        BundleCrud,

        // CRM
        CrmCrud,
        AddCrmCrud,
        ConvertToClient,
        CrmDatatable,

        //CLIENT
        ClientCrud,
        AddClientCrud,
        DatatableClient,
        DashboardClient,

        //Router
        AddRouterCrud,
        RouterCrud,

        //network
        AddNetworkCrud,
        NetworkListar,
        NetworkVer,

        //Mapa
        GoogleMap,
        LeafletMap,

        CatalogoApiPanel,
        'contratable-catalog-list': ContratableCatalogList,
        'contratable-catalog-form': ContratableCatalogForm,
        DebtPaymentClient,

        //Administracion
        //Rol
        ListarRol,
        //Socio
        PartnerListar,
        //Ubicacion
        LocationListar,
        SucursalListar,
        //Estado
        StateListar,
        //Municipio
        MunicipalityListar,
        //Colonia
        ColonyListar,
        MethodOfPaymentListar,
        //Paquete
        PackageList,
        //Ift
        IftListar,
        TypeTemplateListar,

        DocumentTemplateListar,

        WorkFlowListar,
        ListTemplateVerificationListar,

        //Ticket
        DashboardTicket,
        TicketCrud,
        VerTicket,

        NotificationTopbar,
        ModeVisualBody,
        Datatable,
        Message,
        Breadcrumb,

        //Perfil
        Perfil,
        PerfilEdit,

        //Setting
        DebitCustomListar,
        CommandConfig,
        FieldModuleListar,
        'module-manager': ModuleManager,
        'module-visibility-config': ModuleVisibilityConfig,
        'change-password-form': ChangePasswordForm,
        'admin-panel': AdminPanel,
        'module-config-panel': ModuleConfigPanel,
        'devtools-panel': DevtoolsPanel,
        'manual-index': ManualIndex,
        'help-float': HelpFloat,
        'api-movil-config': ApiMovilConfig,
        'api-movil-tokens': ApiMovilTokens,
        'api-movil-docs': ApiMovilDocs,
        'api-movil-logs': ApiMovilLogs,
        'mega-familia-dashboard': MegaFamiliaDashboard,
        'mega-familia-clientes': MegaFamiliaClientes,
        'mega-familia-licencias': MegaFamiliaLicencias,
        'mega-familia-planes': MegaFamiliaPlanes,
        'mega-familia-ingresos': MegaFamiliaIngresos,
        'mega-familia-alertas': MegaFamiliaAlertas,
        'mega-familia-solicitudes': MegaFamiliaSolicitudes,
        'mega-familia-perfiles': MegaFamiliaPerfiles,
        'mega-familia-dispositivos': MegaFamiliaDispositivos,
        'mega-familia-tareas': MegaFamiliaTareas,
        'mega-familia-ubicaciones': MegaFamiliaUbicaciones,
        'mega-familia-geofences': MegaFamiliaGeofences,
        'mega-familia-reportes': MegaFamiliaReportes,
        'mega-familia-auditoria': MegaFamiliaAuditoria,
        'mega-familia-mikrotik': MegaFamiliaMikrotik,
        'mega-familia-notificaciones': MegaFamiliaNotificaciones,
        'mega-familia-soporte': MegaFamiliaSoporte,
        'mega-familia-terminos': MegaFamiliaTerminos,
        'mega-familia-configuracion': MegaFamiliaConfiguracion,
        'embajadores-dashboard': EmbajadoresDashboard,
        'embajadores-configuracion': EmbajadoresConfiguracion,
        'embajadores-tiers': EmbajadoresTiers,
        'embajadores-clientes': EmbajadoresClientes,
        'embajadores-comisiones': EmbajadoresComisiones,
        'embajadores-arbol': EmbajadoresArbol,
        'warroom-dashboard': WarroomDashboard,
        'warroom-line-series': WarroomLineSeries,
        'fleet-dashboard': FleetDashboard,
        'fleet-vehicle-form': FleetVehicleForm,
        'fleet-vehicle-show': FleetVehicleShow,
        'fleet-map': FleetMap,
        'fleet-geofence-list': FleetGeofenceList,
        'fleet-geofence-form': FleetGeofenceForm,
        'fleet-geofence-show': FleetGeofenceShow,
        'fleet-notification-log': FleetNotificationLog,
        'fleet-rule-list': FleetRuleList,
        'fleet-documents-dashboard': FleetDocumentsDashboard,
        'FleetClientPlanTab': FleetClientPlanTab,
        'fleet-subscription-dashboard': FleetSubscriptionDashboard,
        'talento-colaboradores': TalentoColaboradores,
        'talento-custodia': TalentoCustodia,
        'talento-dispositivos': TalentoDispositivos,
        'talento-roadmap': TalentoRoadmap,
        'talento-ordenes': TalentoOrdenes,
        'talento-compensacion': TalentoCompensacion,
        'talento-liquidaciones': TalentoLiquidaciones,
        'talento-asistencia': TalentoAsistencia,
        'talento-mapa-vivo': TalentoMapaVivo,
        'talento-sitios': TalentoSitios,
        'talento-campo': TalentoCampo,
        'talento-cajas': TalentoCajas,
        'talento-rutas': TalentoRutas,
        'talento-proyectos': TalentoProyectos,
        'talento-calidad': TalentoCalidad,
        'talento-penalizaciones': TalentoPenalizaciones,
        'talento-credenciales': TalentoCredenciales,
        'talento-finiquito': TalentoFiniquito,
        'talento-academia': TalentoAcademia,
        'talento-niveles': TalentoNiveles,
        'talento-dashboard': TalentoDashboard,
        'talento-escalafon': TalentoEscalafon,
        'talento-embajadores': TalentoEmbajadores,
        'talento-evidencia-config': TalentoEvidenciaConfig,
        ImportListar,
        ImportCrud,
        ServiceInAddressListListar,
        CompanyInformationIndex,
        CompanyInformationEdit,
        BillingReminderEdit,
        EmailSettingEdit,
        //utils
        MessageResponse,
        IndexAdministration,
        IndexSetting,
        TemplateTaskListar,
        NomenclatureListar,
        TeamListar,

        //Finance setting
        MethodPayment,
        PaymentListar,
        InvoiceListar,
        ConfigFinanceNotificationIndex,
        GeneralAccountingIndex,

        //Vendedores
        DashboardSellers,
        VendedorListar,
        MenuSeller,
        AddPayment,
        Panel,

        //config sales
        CredentialConfig,
        MediumSale,
        Commission,
        AddRule,
        EditRule,
        ListSellers,
        TypeSeller,
        StatusSeller,
        RangeSaleConfig,
        RegisterExternal,

        // Administracion
        UserListar,
        UserAdd,
        UserEdit,
        ShowActivity,
        ShowScripts,
        
        // Documentation
        DocumentationMenuListar,
        DocumentationSubmenuListar,
        DocumentationContent,
        DocumentationTreeMenu,

        
        //maps
        ApiKeyConfig,
        //scheduling
        ProjectListar,
        TaskListar,
        TaskEdit,
        QCalendar,
        CalendarIndex,
        //Inventario
        InventoryItemTypeListar,
        InventoryMovementListar,
        InventoryStoreListar,
        Store,
        InventoryItemStockListar,
        StoreZoneListar,
        MediaItem,
        InventoryItemCustomModelListar,
        InventoryItemCustomListar,
        //Supplier / Proveedores
        SupplierListar,
        SupplierCrear,
        SupplierShow,
        SupplierInvoiceListar,
        SupplierInvoiceCreate,
        SupplierInvoiceShow,
        InventoryValuation,
        SupplierVendorCrear,
        DataPlanPromotionTable,
        //Promociones
        PromotionsComponent,
        ClientsToPromotionComponent,
        ColorPicker,
        ///Vendedores
        SellerListar,
        ///Reglas
        RulesListar,
        FormRule,
        //Message
        Inbox,

        //OLTS
        OltsPanel,
        OltRedMap,
        OltProvisionPreview,

        //RELEASES
        ReleasesIndex,
        ReleasesDescription,
        RoadmapTab,
        DeployProgressModal,
        UpdateBanner,

        //IA — portado desde MEGANET 2026-05-19
        'ia-chat-index': IAChatIndex,
        'ia-historial': IAHistorial,
        'ia-prompts': IAPrompts,
        'ia-configuracion': IAConfiguracion,

        //Smart Import/Export — addon-smart-import-export (portado de MEGANET 2026-05-21)
        'smart-import': SmartImport,
        'smart-export': SmartExport,
        'import-export-history': ImportExportHistory,

        //Evaluador Empresarial — addon-evaluador-empresarial (portado de MEGANET 2026-05-22)
        'evaluador-empresarial': EvaluadorEmpresarial,
        'evaluaciones-dashboard': EvaluacionesDashboard,

        //Marketing — addon-marketing (2026-05-25)
        'marketing-dashboard': MarketingDashboard,
        'marketing-campaigns': MarketingCampaigns,
        'marketing-campaign-show': MarketingCampaignShow,
        'marketing-content-generator': MarketingContentGenerator,
        'marketing-leads': MarketingLeads,
        // Marketing Fase 2
        'marketing-leads-view': MarketingLeadsView,
        'marketing-lead-detail': MarketingLeadDetail,
        'marketing-lead-forms-view': MarketingLeadFormsView,
        'marketing-lead-form-editor': MarketingLeadFormEditor,
        'marketing-lead-form': MarketingLeadForm,
        'lead-score-badge': LeadScoreBadge,
        'lead-status-badge': LeadStatusBadge,
        'lead-source-chip': LeadSourceChip,
        'lead-activity-timeline': LeadActivityTimeline,
        // Marketing Fase 3
        'marketing-conversations-view': MarketingConversationsView,
        'conversation-list-item': ConversationListItem,
        'message-bubble': MessageBubble,
        'message-day-divider': MessageDayDivider,
        'agent-toggle-button': AgentToggleButton,
        // Marketing Fase 4 — MVM
        'marketing-video-templates-view': MarketingVideoTemplatesView,
        'marketing-video-generator-view': MarketingVideoGeneratorView,
        'marketing-video-queue-view': MarketingVideoQueueView,
        'marketing-brand-kit-view': MarketingBrandKitView,
        'integrations-hub-view': IntegrationsHubView,
        // Portal de Pago (addon-portal-pago)
        'pagos-dashboard': PagosDashboard,
        'pagos-conciliacion': PagosConciliacion,
        'pagos-cuentas': PagosCuentas,
        'pagos-links': PagosLinks,
        // Marketing Fase 4.5b — Director Creativo IA
        'marketing-campaign-generator-view': MarketingCampaignGeneratorView,
        'voice-comparator-view': VoiceComparatorView,

        // Marketing Fase 5 — Publicador Multicanal
        'marketing-publishing-dashboard-view': MarketingPublishingDashboardView,
        'marketing-publish-campaign-view': MarketingPublishCampaignView,
        'marketing-publication-queue-view': MarketingPublicationQueueView,
        'marketing-channels-setup-view': MarketingChannelsSetupView,

        //WhatsApp Agent — addon-whatsapp-agent (2026-05-22)
        'whatsapp-panel': WhatsAppPanel,
        'whatsapp-instance-manager': WhatsAppInstanceManager,

        // Payments (SPEI / OpenPay)
        'payment-methods': PaymentMethods,
        'client-clabe-card': ClientClabeCard,

        // CobranzaBlaster (Fase 6)
        'cobranza-voip-config': CobranzaVoipConfig,
        'cobranza-campanas':    CobranzaCampanas,
        'voip-troncales':        VoipTroncales,
        'voip-extensiones':      VoipExtensiones,
        'voip-grupos-timbrado':  VoipGruposTimbrado,
        'voip-ia-bot-manager':   VoipIaBotManager,
    },
});

app.use(Quasar, {
    components: [
        QBtn,
        QInput,
        QDialog,
        QCard,
        QSpace,
        QCardSection,
        QImg,
        QFile,
        QAvatar,
        QBtnGroup,
        QEditor,
        QIcon,
        QItem,
        QItemSection,
        QList,
        QBtnDropdown,
        QTable,
        QSelect,
        QTd,
        QTr,
        QTh,
        QPagination,
        QCheckbox,
        QRating,
        QBadge,
        QItemLabel,
        QToggle,
        QPage,
        QLayout,
        QPageContainer,
        QSeparator,
        QHeader,
        QBar,
        QMenu,
        QTab,
        QTabs,
        QTabPanel,
        QTabPanels,
        QOptionGroup,
        QBtnToggle,
        QColor,
        QPopupProxy,
        QScrollArea,
        QToolbar,
        QToolbarTitle,
        QTooltip,
        QForm,
        QCardActions,
        QExpansionItem,
        QChip,
        QDate,
        QInnerLoading,
        QSpinnerFacebook,
        QTree,
        QSplitter,
        QSlider,
        QVirtualScroll,
        Dialog,
        useQuasar,
        Screen,
        QBanner,
        QDrawer,
        QFooter,
        QPageSticky,
        QPageScroller,
        LocalStorage,
        QField,
        QLinearProgress,
        QSkeleton,
        QMarkupTable,
        QRadio,
        QSpinner,
        QSpinnerIos,
        QStepper,
        QStep,
        QUploader,
    ],
});
app.directive("hasPermission", hasPermission);
app.directive("table-resizable", QTableResizable);

// Pestañas de ficha de cliente aportadas por módulos (infra extensible — roadmap #5).
// DEBEN registrarse como componentes GLOBALES (no en el `components` del root):
// 1) así ClientCrud — un descendiente — puede resolverlas vía <component :is>;
// 2) así aparecen en appContext.components, que es lo que ClientCrud consulta para
//    montar sólo las pestañas cuyo componente realmente existe.
// El nombre debe coincidir EXACTO con `client_tab.component` del module.json.
app.component("MegaFamiliaClientTab", MegaFamiliaClientTab);
app.component("DomiciliacionClientTab", DomiciliacionClientTab);
app.component("ContratablesClientTab", ContratablesClientTab);

// Fleet tab sub-components: deben ser globales porque los usa FleetVehicleShow (descendiente)
app.component("fleet-tab-info",           FleetTabInfo);
app.component("fleet-tab-asignacion",     FleetTabAsignacion);
app.component("fleet-tab-mantenimientos", FleetTabMantenimientos);
app.component("fleet-tab-documentos",     FleetTabDocumentos);
app.component("fleet-tab-combustible",    FleetTabCombustible);
app.component("fleet-tab-gps",            FleetTabGps);
app.component("fleet-tab-historial",      FleetTabHistorial);
app.component("fleet-tab-fotos",          FleetTabFotos);

app.use(VueApexCharts);

app.use(store);
app.mount("#init-vue");
window.__megaVueApp = app;
return app;
}

window.createMainApp = createMainApp;
Quasar.iconSet = Quasar.iconSet.fontawesomeV5;

store
    .dispatch("fetchPermissions")
    .then(() => {
        createMainApp();

        const topbarEl = document.querySelector('#topbar-vue-root');
        if (topbarEl) {
            const topbarApp = createApp({});
            topbarApp.use(Quasar, {
                components: [
                    QCard, QCardSection, QCardActions,
                    QBtn, QIcon,
                    QItem, QItemSection, QItemLabel,
                    QList, QSeparator, QTooltip, QBadge,
                ],
            });
            topbarApp.use(store);
            topbarApp.directive('hasPermission', hasPermission);
            topbarApp.component('notification-topbar', NotificationTopbar);
            topbarApp.component('mode-visual-body', ModeVisualBody);
            topbarApp.component('documentation-tree-menu', DocumentationTreeMenu);
            topbarApp.mount('#topbar-vue-root');
        }

        const helpFloatEl = document.querySelector('#help-float-root');
        if (helpFloatEl) {
            const helpFloatApp = createApp({});
            helpFloatApp.use(store);
            helpFloatApp.component('help-float', HelpFloat);
            helpFloatApp.mount('#help-float-root');
        }
    })
    .catch((error) => {
        console.error("Error:", error);
    });

import './spa-nav';

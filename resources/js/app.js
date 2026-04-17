require("./bootstrap");
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
import RolListar from "./components/module/adminstration/rol/RolListar";
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

//Configuracion
import DebtPaymentClient from "./components/module/setting/DebtPaymentClient";
import DebitCustomListar from "./components/module/setting/DebitCustomListar";
import CommandConfig from "./components/module/setting/CommandConfig";
import FieldModuleListar from "./components/module/setting/FieldModuleListar";
import IndexSetting from "./components/module/setting/IndexSetting.vue";
import ImportListar from "./components/module/setting/ImportListar.vue";
import ImportCrud from "./components/module/setting/components/tools/ImportCrud.vue";
import ShowActivity from "./components/module/adminstration/activity_log/ShowActivity.vue";

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
import InventoryItemCustomModelListar from "./components/module/inventory/inventory_item_custom_model/InventoryItemCustomModelListar.vue";
import InventoryItemCustomListar from "./components/module/inventory/inventory_item_custom/InventoryItemCustomListar.vue";


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

        DebtPaymentClient,

        //Administracion
        //Rol
        RolListar,
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
        ///Vendedores
        SellerListar,
        ///Reglas
        RulesListar,
        FormRule,
        //Message
        Inbox,

        //OLTS
        OltsPanel,

        //RELEASES
        ReleasesIndex,
        ReleasesDescription,
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
    ],
});
Quasar.iconSet = Quasar.iconSet.fontawesomeV5;

app.directive("hasPermission", hasPermission);
app.directive("table-resizable", QTableResizable);

app.use(VueApexCharts);

app.use(store);

store
    .dispatch("fetchPermissions")
    .then(() => {
        app.mount("#init-vue");
    })
    .catch((error) => {
        console.error("Error:", error);
    });

<template>
    <div>
        <!-- Tabs principales -->
        <TabNavigation
            :tabs="mainTabs"
            :active-tab="activeTab"
            @tab-change="setActiveTab"
        />

        <!-- Subtabs dinámicos -->
        <TabNavigation
            v-if="activeSubTabs[activeTab]"
            :tabs="activeSubTabs[activeTab]"
            :active-tab="activeSubTab"
            @tab-change="setActiveSubTab"
        />

        <!-- Contenido dinámico -->
        <div class="accordion" :id="`accordionPanelsStayOpen${activeTab}`">
            <ContentConfig
                :key="contentKey"
                :module="currentContentModule"
                :title="currentContentTitle"
                :url_base="currentContentUrl"
                :id="activeSubTab"
            />
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from "vue";
import { showLoading, hideLoading } from "../../../../helpers/loading";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";
import ContentConfig from "./components/ContentConfig.vue";
import TabNavigation from "./components/TabNavigation.vue";

export default {
    name: "Inbox",
    components: {
        ContentConfig,
        TabNavigation,
    },
    props: {
    },
    setup(props, { emit }) {
        // Estado reactivo
        const state = reactive({
            hasPermission: new Permission({}),
            dataTabs: [
                {
                    id: "Reminder",
                    label: "Recordatorios",
                    type_config: "Reminder",
                    group: "Reminder",
                },
                {
                    id: "PaymentEmail",
                    label: "Pagos",
                    type_config: "PaymentEmail",
                    group: "PaymentEmail",
                },
                {
                    id: "InvoiceEmail",
                    label: "Facturas",
                    type_config: "InvoiceEmail",
                    group: "InvoiceEmail",
                },
                {
                    id: "ProformaInvoiceEmail",
                    label: "Facturas Proformas",
                    type_config: "ProformaInvoiceEmail",
                    group: "ProformaInvoiceEmail",
                },
            ],
        });

        const activeTab = ref("Reminder");
        const activeSubTab = ref("reminder");

        // Configuración de tabs en una estructura de datos
        const mainTabs = [
            { id: "Reminder", label: "Recordatorios" },
            { id: "PaymentEmail", label: "Pagos" },
            { id: "InvoiceEmail", label: "Facturas" },
            { id: "ProformaInvoiceEmail", label: "Facturas Proformas" },
        ];

        const subTabsConfig = {
            Reminder: [
                {
                    id: "reminder",
                    label: "Recordatorios de Pago",
                    module: "Reminder",
                    url_base: "message/reminder",
                },
            ],
            PaymentEmail: [
                {
                    id: "payment",
                    label: "Ticket de Pagos",
                    module: "PaymentEmail",
                    url_base: "message/payment_email",
                },
            ],
            InvoiceEmail: [
                {
                    id: "invoice",
                    label: "Facturas",
                    module: "InvoiceEmail",
                    url_base: "message/invoice_email",
                },
            ],
            ProformaInvoiceEmail: [
                {
                    id: "proforma_invoice",
                    label: "Facturas Proformas",
                    module: "ProformaInvoiceEmail",
                    url_base: "message/proforma_invoice_email",
                },
            ],
        };

        // Computed properties
        const activeSubTabs = computed(() => subTabsConfig);

        const currentContentId = computed(() =>
            getIdByTypeConfigAndGroup(activeSubTab.value, activeTab.value)
        );

        const currentContentModule = computed(
            () =>
                subTabsConfig[activeTab.value]?.find(
                    (tab) => tab.id === activeSubTab.value
                )?.module || ""
        );

        const currentContentTitle = computed(
            () =>
                subTabsConfig[activeTab.value]?.find(
                    (tab) => tab.id === activeSubTab.value
                )?.label || ""
        );

        const currentContentUrl = computed(
            () =>
                subTabsConfig[activeTab.value]?.find(
                    (tab) => tab.id === activeSubTab.value
                )?.url_base || ""
        );

        const shouldShowContent = computed(
            () => state.dataTabs.length > 0 && currentContentId.value
        );

        const contentKey = computed(
            () =>
                `${activeTab.value}-${activeSubTab.value}-${currentContentId.value}`
        );

        // Métodos
        const setActiveTab = (tab) => {
            activeTab.value = tab;
            // Resetear al primer subtab cuando cambia el tab principal
            const firstSubTab = subTabsConfig[tab]?.[0]?.id;
            if (firstSubTab) {
                activeSubTab.value = firstSubTab;
            }
        };

        const setActiveSubTab = (subTab) => {
            activeSubTab.value = subTab;
        };

        const getIdByTypeConfigAndGroup = (type_config, group) => {
            const tab = state.dataTabs.find(
                (tab) => tab.type_config === type_config && tab.group === group
            );
            return tab?.id || null;
        };

        // Lifecycle hooks
        onMounted(async () => {
            state.hasPermission = new Permission(await allViewHasPermission());
        });

        return {
            state,
            mainTabs,
            activeSubTabs,
            activeTab,
            activeSubTab,
            setActiveTab,
            setActiveSubTab,
            currentContentId,
            currentContentModule,
            currentContentTitle,
            shouldShowContent,
            contentKey,
            currentContentUrl,
        };
    },
};
</script>

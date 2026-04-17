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
                v-if="shouldShowContent"
                :key="contentKey"
                :id="currentContentId"
                :module="currentContentModule"
                :title="currentContentTitle"
            />
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from "vue";
import { showLoading, hideLoading } from "../../../../../helpers/loading";
import Permission from "../../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../../helpers/Request";
import ContentConfig from "./components/ContentConfig.vue";
import TabNavigation from "./components/TabNavigation.vue";

export default {
    name: "ConfigFinanceNotificationIndex",
    components: {
        ContentConfig,
        TabNavigation,
    },
    setup() {
        // Estado reactivo
        const state = reactive({
            hasPermission: new Permission({}),
            dataTabs: [],
        });

        const activeTab = ref("Global");
        const activeSubTab = ref("invoice");

        // Configuración de tabs en una estructura de datos
        const mainTabs = [
            { id: "Global", label: "Global" },
            { id: "Recurrent", label: "Recurrentes" },
            { id: "Prepaid", label: "Prepagos Personalizados" },
        ];

        const subTabsConfig = {
            Global: [
                {
                    id: "invoice",
                    label: "Facturas",
                    module: "ConfigFinanceNotificationInvoice",
                },
                {
                    id: "proforma_invoice",
                    label: "Facturas proforma",
                    module: "ConfigFinanceNotificationProformaInvoice",
                },
                {
                    id: "payment",
                    label: "Pagos",
                    module: "ConfigFinanceNotificationPayment",
                },
                {
                    id: "credit_notes",
                    label: "Notas de crédito",
                    module: "ConfigFinanceNotificationCreditNotes",
                },
            ],
            Recurrent: [
                {
                    id: "recurrent_blocking_wave",
                    label: "Ola de Bloqueo",
                    module: "ConfigFinanceNotificationRecurrentBlockingWave",
                },
                {
                    id: "recurrent_inactive_wave",
                    label: "Ola de Inactivos",
                    module: "ConfigFinanceNotificationRecurrentInactiveWave",
                },
            ],
            Prepaid: [
                {
                    id: "prepaid_blocking_wave",
                    label: "Ola de Bloqueo",
                    module: "ConfigFinanceNotificationPrepaidBlockingWave",
                },
                {
                    id: "prepaid_first_blocking_wave",
                    label: "Primera Ola de Notificaciones",
                    module: "ConfigFinanceNotificationPrepaidFirstBlockingWave",
                },
                {
                    id: "prepaid_second_blocking_wave",
                    label: "Segunda Ola de Notificaciones",
                    module: "ConfigFinanceNotificationPrepaidSecondBlockingWave",
                },
                {
                    id: "prepaid_third_blocking_wave",
                    label: "Tercera Ola de Notificaciones",
                    module: "ConfigFinanceNotificationPrepaidThirdBlockingWave",
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

        const getDataTabs = async () => {
            showLoading("showTextDef");
            try {
                const response = await axios.post(
                    "/configuracion/finance-notification/get-data-tabs"
                );
                state.dataTabs = response.data.tabs;
            } finally {
                hideLoading();
            }
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
            await getDataTabs();
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
        };
    },
};
</script>

<template>
    <div>
        <!-- Tabs principales -->
        <TabNavigation
            :tabs="mainTabs"
            :active-tab="activeTab"
            @tab-change="setActiveTab"
        />

        <!-- Contenido dinámico -->
        <div
            class="accordion"
            :id="`accordionPanelsStayOpenAccounting${activeTab}`"
        >
            <ContentConfig
                v-if="shouldShowContent"
                :id="currentContentId"
                :module="currentContentModule"
                :title="currentContentTitle"
            />
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from "vue";
import ContentConfig from "./components/ContentConfig.vue";
import TabNavigation from "./components/TabNavigation.vue";

export default {
    name: "GeneralAccountingIndex",
    components: {
        ContentConfig,
        TabNavigation,
    },
    setup() {
        // Estado reactivo
        const state = reactive({
            dataTabs: [],
        });

        const activeTab = ref("Balance");

        // Configuración de tabs principales
        const mainTabs = [
            { id: "Global", label: "Global", module: "Dashboard", staticId: 1 },
            { id: "Balance", label: "Balance", module: "Balance", staticId: 2 },
            // { id: "Expense", label: "Gastos", module: "Expense", staticId: 3 },
            // { id: "Income", label: "Ingresos", module: "Income", staticId: 4 },
        ];

        // Datos estáticos para reemplazar la base de datos
        const staticDataTabs = [
            { id: 1, type_config: "dashboard_default", group: "Global" },
            { id: 2, type_config: "balance", group: "Balance" },
            // { id: 3, type_config: "expense", group: "Expense" },
            // { id: 4, type_config: "income", group: "Income" },
        ];

        // Función simplificada que usa datos estáticos
        const getDataTabs = () => {
            state.dataTabs = staticDataTabs;
        };

        const getIdByTypeConfigAndGroup = (group) => {
            const tab = state.dataTabs.find((tab) => tab.group === group);
            return tab?.id || null;
        };

        // Computed properties
        const currentContentId = computed(() =>
            getIdByTypeConfigAndGroup(activeTab.value)
        );

        const currentContentModule = computed(
            () =>
                mainTabs.find((tab) => tab.id === activeTab.value)?.module || ""
        );

        const currentContentTitle = computed(
            () =>
                mainTabs.find((tab) => tab.id === activeTab.value)?.label || ""
        );

        const shouldShowContent = computed(
            () => state.dataTabs.length > 0 && currentContentId.value
        );

        const contentKey = computed(
            () => `${activeTab.value}-${currentContentId.value}`
        );

        // Métodos
        const setActiveTab = (tab) => {
            activeTab.value = tab;
        };

        // Lifecycle hooks
        onMounted(() => {
            getDataTabs();
        });

        return {
            state,
            mainTabs,
            activeTab,
            setActiveTab,
            currentContentId,
            currentContentModule,
            currentContentTitle,
            shouldShowContent,
            contentKey,
        };
    },
};
</script>

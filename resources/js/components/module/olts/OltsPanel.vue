<template>
    <q-tabs
        v-model="tab"
        dense
        no-caps
        inline-label
        class="bg-grey-3 text-grey-7"
        active-color="primary"
        indicator-color="primary"
        align="justify"
        content-class="no-gutter-x width-auto"
        @update:model-value="
            (tab) => {
                currentOlt = null;
                filters = {};
                setActiveTab('tab-olt-panel', tab);
            }
        "
    >
        <q-tab name="dashboard" label="Dashboard" />
        <q-tab name="unconfigured" label="ONUs desconfiguradas" />
        <q-tab name="configured" label="ONUs configuradas" />
        <q-tab name="graphic" label="Gráficos" />
        <q-tab name="diagnostics" label="Diagnósticos" />
        <q-tab name="settings" label="Configuración" />
    </q-tabs>

    <q-separator />

    <q-tab-panels v-model="tab" animated>
        <q-tab-panel name="dashboard">
            <dashboard-panel
                @change-tab="onChangeTab"
                @change-olt="(o) => (currentOlt = o)"
            />
        </q-tab-panel>
        <q-tab-panel name="unconfigured">
            <olt-unconfigured-onus
                :olt="currentOlt"
                :has-permission="hasPermission"
            />
        </q-tab-panel>
        <q-tab-panel name="configured">
            <olt-onus
                :default-filters="filters"
                :has-permission="hasPermission"
            />
        </q-tab-panel>
        <q-tab-panel name="diagnostics">
            <olt-diagnostics
                :default-filters="filters"
                :has-permission="hasPermission"
            />
        </q-tab-panel>
        <q-tab-panel name="settings" style="padding: 0">
            <settings-panel :has-permission="hasPermission" />
        </q-tab-panel>
    </q-tab-panels>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import DashboardPanel from "./components/dashboard/DashboardPanel.vue";
import OltUnconfiguredOnus from "./components/OltUnconfiguredOnus.vue";
import OltOnus from "./components/OltOnus.vue";
import OltDiagnostics from "./components/OltDiagnostics.vue";
import SettingsPanel from "./components/settings/SettingsPanel.vue";
import { setActiveTab } from "../../../hook/appConfig";
import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";

defineOptions({
    name: "OltsPanel",
});

const hasPermission = reactive({
    data: new Permission({}),
});

const tab = ref(localStorage.getItem("tab-olt-panel") || "dashboard");
const filters = ref({});
const currentOlt = ref(null);

const onChangeTab = (t, f) => {
    filters.value = f ?? {};
    if (currentOlt.value) {
        filters.value["olt_id"] = currentOlt.value;
    }
    tab.value = t;
};

onMounted(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
});

watch(currentOlt, (n) => {
    if (n) {
        filters.value["olt_id"] = n;
    } else {
        delete filters.value["olt_id"];
    }
});
</script>

<template>
    <q-tabs
        v-model="currentTab"
        no-caps
        dense
        indicator-color="transparent"
        class="cc-folder-tabs"
        :class="{ 'cc-folder-dark': darkMode }"
        style="margin-right: 35px"
        align="left"
        :breakpoint="0"
        @update:model-value="onChangeTab"
    >
        <q-tab
            name="information"
            label="Información"
            v-if="tabs.includes('information')"
        />
        <q-tab
            name="documents"
            label="Documentos"
            v-if="tabs.includes('documents')"
        />
        <q-tab
            name="services"
            label="Servicios"
            v-if="tabs.includes('services')"
        />
        <q-tab
            name="facture"
            label="Facturación"
            v-if="tabs.includes('facture')"
        />
        <q-tab
            name="statistics"
            label="Estadísticas"
            v-if="tabs.includes('statistics')"
        />
        <q-tab
            name="promotions"
            label="Promociones"
            v-if="tabs.includes('promotions')"
        />
        <q-tab
            v-for="mt in moduleTabsList"
            :key="mt.name"
            :name="mt.name"
            :label="mt.label"
        />
    </q-tabs>
    <q-tab-panels v-model="currentTab" animated :dark="darkMode">
        <q-tab-panel name="information" v-if="tabs.includes('information')">
            <InformationClientCrud
                :action="`update/${id}`"
                :id="id"
                :office-lat="officeLat"
                :office-lng="officeLng"
                @getTypeOfBilling="getTypeOfBilling"
            />
        </q-tab-panel>

        <q-tab-panel name="documents" v-if="tabs.includes('documents')">
            <DocumentClientCrud :id="id" :editModal="editModal" />
        </q-tab-panel>

        <q-tab-panel name="services" v-if="tabs.includes('services')">
            <ClientService :id="id" :editModal="editModal" />
        </q-tab-panel>

        <q-tab-panel name="facture" v-if="tabs.includes('facture')">
            <ClientBilling
                :id="id"
                :typeOfBilling="typeBilling"
                :editModal="editModal"
                :authuserid="authuserid"
            />
        </q-tab-panel>
        <q-tab-panel name="statistics" v-if="tabs.includes('statistics')">
            <Statistics :id="id" />
        </q-tab-panel>
        <q-tab-panel name="promotions">
            <promotions-component :client-id="id" />
        </q-tab-panel>

        <q-tab-panel
            v-for="mt in moduleTabsList"
            :key="mt.name"
            :name="mt.name"
        >
            <component :is="mt.component" :id="id" :client-id="Number(id)" />
        </q-tab-panel>
    </q-tab-panels>
</template>

<script setup>
import InformationClientCrud from "./InformationClientCrud";
import ClientService from "./service/ClientService.vue";
import { ref, onMounted, onUnmounted, watch, computed, getCurrentInstance } from "vue";
import { editModal, showEditModal } from "../../../hook/modalHook";
import { resetCrudForm } from "../../../hook/crudHook";
import ClientBilling from "./billing/ClientBilling.vue";
import DocumentClientCrud from "./document/DocumentClientCrud";
import { configTabsHook } from "../../../hook/configTabsHook";
import { darkMode } from "../../../hook/appConfig";
import Statistics from "./statistics/Statistics.vue";
import PromotionsComponent from "./PromotionsComponent.vue";

const ns = `.leak983-clientCrud-${getCurrentInstance().uid}`;
onUnmounted(() => {
    $(document).off(ns);
});

defineOptions({ name: "ClientCrud" });

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
    tabs: String,
    moduleTabs: {
        type: String,
        default: "[]",
    },
    authuserid: Number | String,
    action: String,
    officeLat: { type: String, default: null },
    officeLng: { type: String, default: null },
});

const currentTab = ref(null);
const tabs = ref(JSON.parse(props.tabs));
const percentage = tabs.value ? `${100 / tabs.value.length}%` : null;
const typeBilling = ref("");

// ── Infra de ficha de cliente extensible ──────────────────────────────────
// Pestañas declaradas por módulos addon (client_tab en module.json), ya
// filtradas por permiso en el backend. Sólo montamos las cuyo componente Vue
// está registrado globalmente; así una declaración cuyo componente aún no
// existe (p.ej. tabs `deferred`) no rompe la ficha — simplemente no aparece.
const instance = getCurrentInstance();
const isRegistered = (name) => {
    if (!name) return false;
    const comps = instance?.appContext?.components || {};
    return Boolean(comps[name]);
};

const parseModuleTabs = () => {
    let raw = [];
    try {
        raw = JSON.parse(props.moduleTabs || "[]");
    } catch (e) {
        console.warn("ClientCrud: moduleTabs JSON inválido", e);
        return [];
    }
    return raw
        .filter((t) => isRegistered(t.component))
        .map((t) => ({
            name: `mod-${t.module || t.component}`,
            label: t.label,
            component: t.component,
            module: t.module,
        }));
};

const moduleTabsList = ref(parseModuleTabs());

const availableTabs = computed(() => {
    return tabs.value.map((tabName) => allTabs[tabName]).filter(Boolean);
});

const allTabs = {
    information: {
        name: "information",
        label: "Información",
        component: InformationClientCrud,
    },
    documents: {
        name: "documents",
        label: "Documentos",
        component: DocumentClientCrud,
    },
    services: {
        name: "services",
        label: "Servicios",
        component: ClientService,
    },
    facture: {
        name: "facture",
        label: "Facturación",
        component: ClientBilling,
    },
    statistics: {
        name: "statistics",
        label: "Estadísticas",
        component: Statistics,
    },
    promotions: {
        name: "promotions",
        label: "Promociones",
        component: PromotionsComponent,
    },
};

onMounted(() => {
    setInitialTab();
    $(document).on("click" + ns, ".uil-pen-modal", function () {
        let idItem = $(this).parent().attr("id-item");
        let modal = $(this).parent().attr("toggle-modal");
        showEditModal(idItem, modal);
    });
});

// Al salir de la ficha completa (navegar a otra pantalla) limpiamos el singleton compartido
// dataForm para que la próxima ficha no muestre los datos del cliente anterior. Va en
// ClientCrud (raíz de la ficha) y NO en InformationClientCrud a propósito: q-tab-panels
// desmonta el panel inactivo, así que ponerlo en el tab dispararía el reset al cambiar de
// pestaña. ClientCrud solo se desmonta al navegar fuera de la ficha.
onUnmounted(() => {
    resetCrudForm();
});

const onChangeTab = (tab) => {
    configTabsHook.data.setNewConfig({
        tabs: "clients",
        active: tab,
    });
};

const getTypeOfBilling = ({ typeOfBilling }) => {
    typeBilling.value = typeOfBilling;
};

const setTab = async () => {
    let tab = await configTabsHook.data.getFromDB("clients");
    currentTab.value = tab ? tab : tabs.value.length > 0 ? tabs.value[0] : null;
};

const setInitialTab = async () => {
    // Override por URL (?tab=pagos, ?tab=services...): permite abrir la ficha directo en una
    // pestaña concreta sin pisar la preferencia guardada del usuario en DB (lo usan los
    // enlaces de JARVIS). "pagos" = pestaña externa Facturación (el sub-tab Pagos lo
    // resuelve ClientBilling); cualquier otro valor debe ser el nombre de una pestaña visible.
    const urlTab = new URLSearchParams(window.location.search).get("tab");
    // "facturacion" = Facturación › Vista de facturación (enlace del resumen de Datos de facturación, FR-01).
    const urlTabName = urlTab === "pagos" || urlTab === "facturacion" ? "facture" : urlTab;
    if (urlTabName && tabs.value.includes(urlTabName)) {
        currentTab.value = urlTabName;
        return;
    }
    try {
        const savedTab = await configTabsHook.data.getFromDB("clients");
        const cleanSavedTab = savedTab.trim();

        currentTab.value = cleanSavedTab
            ? cleanSavedTab
            : availableTabs.value.length > 0
            ? availableTabs.value[0].name
            : null;

        console.log("Current tab initialized:", currentTab.value);
    } catch (error) {
        console.error("Error initializing tab:", error);
        currentTab.value =
            availableTabs.value.length > 0 ? availableTabs.value[0].name : null;
    }
};
</script>

<style scoped>
/* Pestañas tipo carpeta: la activa en índigo y unida a la línea de abajo. */
.cc-folder-tabs { border-bottom: 3px solid #4f46e5; }
.cc-folder-tabs :deep(.q-tabs__content) { align-items: flex-end; gap: 4px; }
.cc-folder-tabs :deep(.q-tab) {
    /* bootstrap .row > * { width: 100% } alcanza a las pestañas (q-tabs__content es .row) */
    width: auto;
    padding: 0 16px;
    background: #f3f4f6;
    color: #4b5563;
    border: 1px solid #e5e7eb;
    border-bottom: 0;
    border-radius: 8px 8px 0 0;
}
.cc-folder-tabs :deep(.q-tab:hover) { background: #e0e7ff; color: #4338ca; }
.cc-folder-tabs :deep(.q-tab--active),
.cc-folder-tabs :deep(.q-tab--active:hover) { background: #4f46e5; border-color: #4f46e5; color: #fff; }
.cc-folder-dark :deep(.q-tab) { background: #26262c; color: #9ca3af; border-color: #2c2c34; }
.cc-folder-dark :deep(.q-tab:hover) { background: #312e81; color: #c7d2fe; }
.cc-folder-dark :deep(.q-tab--active),
.cc-folder-dark :deep(.q-tab--active:hover) { background: #4f46e5; border-color: #4f46e5; color: #fff; }
</style>

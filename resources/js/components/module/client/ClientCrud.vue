<template>
    <q-tabs
        v-model="currentTab"
        no-caps
        dense
        active-color="indigo-6"
        style="margin-right: 35px"
        @update:model-value="onChangeTab"
    >
        <q-tab
            name="information"
            label="Información"
            :style="{
                width: percentage,
            }"
            v-if="tabs.includes('information')"
        />
        <q-tab
            name="documents"
            label="Documentos"
            :style="{
                width: percentage,
            }"
            v-if="tabs.includes('documents')"
        />
        <q-tab
            name="services"
            label="Servicios"
            :style="{
                width: percentage,
            }"
            v-if="tabs.includes('services')"
        />
        <q-tab
            name="facture"
            label="Facturación"
            :style="{
                width: percentage,
            }"
            v-if="tabs.includes('facture')"
        />
        <q-tab
            name="statistics"
            label="Estadísticas"
            :style="{
                width: percentage,
            }"
            v-if="tabs.includes('statistics')"
        />
    </q-tabs>
    <q-tab-panels v-model="currentTab" animated :dark="darkMode">
        <q-tab-panel name="information" v-if="tabs.includes('information')">
            <InformationClientCrud
                :action="`update/${id}`"
                :id="id"
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
            <Statistics :id="id"/>
        </q-tab-panel>
    </q-tab-panels>
</template>

<script>
import InformationClientCrud from "./InformationClientCrud";
import ClientService from "./service/ClientService.vue";
import { ref, onMounted, watch, computed } from "vue";
import { editModal, showEditModal } from "../../../hook/modalHook";
import ClientBilling from "./billing/ClientBilling.vue";
import DocumentClientCrud from "./document/DocumentClientCrud";
import { configTabsHook } from "../../../hook/configTabsHook";
import { darkMode } from "../../../hook/appConfig";
import Statistics from "./statistics/Statistics.vue";

export default {
    name: "ClientCrud",
    props: {
        id: {
            type: String,
            default: null,
        },
        tabs: String,
        authuserid: Number | String,
        action: String,
    },
    components: {
        DocumentClientCrud,
        ClientBilling,
        InformationClientCrud,
        ClientService,
        Statistics,
    },
    setup(props) {
        const currentTab = ref(null);
        const tabs = ref(JSON.parse(props.tabs));
        const percentage = tabs.value ? `${100 / tabs.value.length}%` : null;
        const typeBilling = ref("");

        const availableTabs = computed(() => {
            return tabs.value
                .map((tabName) => allTabs[tabName])
                .filter(Boolean);
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
        };

        onMounted(() => {
            setInitialTab();
            $(document).on("click", ".uil-pen-modal", function () {
                let idItem = $(this).parent().attr("id-item");
                let modal = $(this).parent().attr("toggle-modal");
                showEditModal(idItem, modal);
            });
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
            currentTab.value = tab
                ? tab
                : tabs.value.length > 0
                ? tabs.value[0]
                : null;
        };

        const setInitialTab = async () => {
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
                    availableTabs.value.length > 0
                        ? availableTabs.value[0].name
                        : null;
            }
        };

        return {
            currentTab,
            tabs,
            percentage,
            editModal,
            typeBilling,
            getTypeOfBilling,
            onChangeTab,
            darkMode,
        };
    },
};
</script>

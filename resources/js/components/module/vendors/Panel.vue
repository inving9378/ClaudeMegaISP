<template>
    <div v-if="seller_id && user_id" class="col-md-12">
        <div class="row">
            <q-tabs
                v-model="activeTab"
                dense
                no-caps
                :dark="darkMode"
                :class="!darkMode ? 'bg-grey-3 text-grey-7' : null"
                active-color="primary"
                indicator-color="primary"
                align="justify"
                content-class="no-gutter-x width-auto"
                @update:model-value="setActiveTab"
            >
                <q-tab
                    name="#navs-pills-justified-information"
                    label="Información"
                    icon="fas fa-user"
                />
                <q-tab
                    name="#navs-pills-justified-prospects"
                    label="Prospectos"
                    icon="fas fa-users"
                />
                <q-tab
                    name="#navs-pills-justified-sales"
                    label="Ventas"
                    icon="fas fa-wallet"
                />
                <q-tab
                    name="#navs-pills-justified-statistics"
                    label="Estadísticas"
                    icon="fas fa-chart-bar"
                />
                <q-tab
                    name="#navs-pills-justified-billing"
                    label="Facturación"
                    icon="fas fa-money-bill"
                />
                <q-tab
                    name="#navs-pills-justified-cutting"
                    label="Corte mostrador"
                    icon="fas fa-calendar"
                    v-if="is_counter"
                />
                <q-tab
                    name="#navs-pills-justified-inventory-items"
                    label="Artículos"
                    icon="fas fa-file-invoice"
                />
            </q-tabs>
            <q-tab-panels v-model="activeTab" animated :dark="darkMode">
                <q-tab-panel name="#navs-pills-justified-information">
                    <InformationSeller :id="seller_id" />
                </q-tab-panel>
                <q-tab-panel name="#navs-pills-justified-prospects">
                    <ListProspects :id="user_id" />
                </q-tab-panel>
                <q-tab-panel name="#navs-pills-justified-sales">
                    <ListSales :id="user_id" />
                </q-tab-panel>
                <q-tab-panel name="#navs-pills-justified-statistics">
                    <Dashboard
                        :id="user_id"
                        :mediums_of_sales="mediums_of_sales"
                    />
                </q-tab-panel>
                <q-tab-panel name="#navs-pills-justified-billing">
                    <Billing :user_id="user_id" :seller_id="seller_id" />
                </q-tab-panel>
                <q-tab-panel
                    name="#navs-pills-justified-cutting"
                    v-if="is_counter"
                >
                    <cuts-component
                        :seller-id="seller_id"
                        :user-id="user_id"
                        :sucursal-id="sucursal_id"
                        :is-counter="is_counter"
                        :has-permission="hasPermission"
                    />
                </q-tab-panel>
                <q-tab-panel name="#navs-pills-justified-inventory-items">
                    <InventoryItemSeller v-if="user_id" :user_id="user_id" />
                </q-tab-panel>
            </q-tab-panels>
        </div>
    </div>
    <div v-else class="alert alert-danger">
        No se encontraron los datos necesarios para mostrar esta sección.
    </div>
</template>

<script setup>
import InformationSeller from "./information/InformationSeller.vue";
import ListProspects from "./prospects/ListProspects.vue";
import ListSales from "./sales/Sales.vue";
import Dashboard from "./statistics/Dashboard.vue";
import Billing from "./billing/index.vue";
import InventoryItemSeller from "../sellers/inventory_items/index.vue";
import CutsComponent from "./billing/components/cuts/CutsComponent.vue";
import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";
import { activeTab, setActiveTab, setUserId } from "../sellers/comun_variables";
import { onMounted, reactive } from "vue";
import { darkMode } from "../../../hook/appConfig";

defineOptions({
    name: "Panel",
});

const props = defineProps({
    user_id: Number,
    seller_id: Number,
    sucursal_id: Number,
    is_counter: Boolean,
});

const hasPermission = reactive({
    data: new Permission({}),
});

onMounted(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
    const savedTab = localStorage.getItem("activeTab");
    if (savedTab) {
        activeTab.value = savedTab;
    } else {
        setActiveTab("#navs-pills-justified-information");
    }
    if (props.user_id) {
        setUserId(props.user_id);
    }
});
</script>

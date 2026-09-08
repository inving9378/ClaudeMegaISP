<template>
    <div class="d-flex justify-content-between">
        <Breadcrumb :list="breadcrumbList" />
        <div class="d-flex gap-2 mb-3">
            <button
                @click="goToPreviousSeller"
                class="btn btn-outline-primary"
                :disabled="isFirstSeller"
            >
                <i class="fas fa-chevron-left"></i>
            </button>
            <button
                @click="goToNextSeller"
                class="btn btn-outline-primary"
                :disabled="isLastSeller"
            >
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
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
                v-if="hasPermission.data.canView('seller_cuts') && is_counter"
            />
            <q-tab
                name="#navs-pills-justified-inventory-items"
                label="Artículos"
                icon="fas fa-file-invoice"
            />
        </q-tabs>
        <!-- #9990601 — QUIÉN se está viendo. Sin esto, abrir el panel del vendedor equivocado
             (o de uno dado de baja) se ve idéntico a que el módulo esté roto: tablas en blanco y
             ninguna pista. Pasó el 2026-09-08 y costó media hora de diagnóstico. -->
        <div v-if="seller_nombre" class="col-12 mb-2">
            <div
                class="d-flex align-items-center gap-2 px-3 py-2 rounded"
                :class="seller_activo ? 'bg-light' : 'bg-warning-subtle border border-warning'"
            >
                <i class="fas fa-user-tie text-muted"></i>
                <strong>{{ seller_nombre }}</strong>
                <span class="text-muted small">· vendedor #{{ seller_id }} · usuario #{{ user_id }}</span>
                <span v-if="!seller_activo" class="badge bg-warning text-dark ms-auto">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    Vendedor {{ seller_estado || "inactivo" }} — es normal que no tenga movimientos
                </span>
            </div>
        </div>

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
                <Dashboard :id="user_id" :mediums_of_sales="mediums_of_sales" />
            </q-tab-panel>
            <q-tab-panel name="#navs-pills-justified-billing">
                <Billing :user_id="user_id" :seller_id="seller_id" />
            </q-tab-panel>
            <q-tab-panel
                name="#navs-pills-justified-cutting"
                v-if="hasPermission.data.canView('seller_cuts') && is_counter"
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
</template>

<script setup>
import { ref, onMounted, computed, reactive } from "vue";
import Breadcrumb from "../../base/shared/Breadcrumb.vue";
import InformationSeller from "./information/InformationSeller.vue";
import ListProspects from "./prospects/ListProspects.vue";
import ListSales from "./sales/Sales.vue";
import Dashboard from "./statistics/Dashboard.vue";
import Billing from "./billing/index.vue";
import CutsComponent from "./billing/components/cuts/CutsComponent.vue";
import { getAll } from "./helper/request";
import InventoryItemSeller from "../sellers/inventory_items/index.vue";
import { activeTab, setActiveTab, setUserId } from "../sellers/comun_variables";
import { darkMode } from "../../../hook/appConfig";
import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";

defineOptions({
    name: "Menu",
});

const props = defineProps({
    user_id: Number,
    seller_id: Number,
    sucursal_id: Number,
    is_counter: Boolean,
    // #9990601 — identidad y estado del vendedor que se está viendo.
    seller_nombre: { type: String, default: "" },
    seller_estado: { type: String, default: "" },
    seller_activo: { type: Boolean, default: true },
    mediums_of_sales: {
        type: Array,
        default: [],
    },
});

const hasPermission = reactive({
    data: new Permission({}),
});

const sellers = ref([]);

const breadcrumbList = ref([
    { title: "Dashboard", a: "/vendedores/dashboard" },
    { title: "Vendedores", a: "/sellers/seller" },
    { title: "", active: true },
]);

const isFirstSeller = computed(() => {
    const currentIndex = sellers.value.findIndex(
        (seller) => seller.seller_id === props.seller_id
    );
    return currentIndex === 0;
});

const isLastSeller = computed(() => {
    const currentIndex = sellers.value.findIndex(
        (seller) => seller.seller_id === props.seller_id
    );
    return currentIndex === sellers.value.length - 1;
});

onMounted(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
    const savedTab = localStorage.getItem("activeTab");
    if (savedTab) {
        activeTab.value =
            !props.is_counter && savedTab === "#navs-pills-justified-cutting"
                ? "#navs-pills-justified-information"
                : savedTab;
    } else {
        setActiveTab("#navs-pills-justified-information");
    }
    getSellers();
    if (props.user_id) {
        setUserId(props.user_id);
    }
});

const getSellers = async () => {
    try {
        sellers.value = await getAll();
        updateBreadcrumb();
    } catch (error) {
        console.log(error);
    }
};

const updateBreadcrumb = () => {
    const seller = sellers.value.find(
        (seller) => seller.seller_id === props.seller_id
    );
    if (seller) {
        breadcrumbList.value[2] = {
            title: `${seller.name} ${seller.father_last_name} ${seller.mother_last_name} - ${seller.seller_id}`,
            active: true,
        };
    }
};

const goToPreviousSeller = () => {
    const currentIndex = sellers.value.findIndex(
        (seller) => seller.seller_id === props.seller_id
    );
    if (currentIndex > 0) {
        const previousSeller = sellers.value[currentIndex - 1];
        window.location.href = `/vendedores/${previousSeller.seller_id}/seguimiento-vendedor/${previousSeller.id}`;
    }
};

const goToNextSeller = () => {
    const currentIndex = sellers.value.findIndex(
        (seller) => seller.seller_id === props.seller_id
    );
    if (currentIndex < sellers.value.length - 1) {
        const nextSeller = sellers.value[currentIndex + 1];
        window.location.href = `/vendedores/${nextSeller.seller_id}/seguimiento-vendedor/${nextSeller.id}`;
    }
};
</script>

<style scoped></style>

<template>
    <div>
        <Breadcrumb :list="breadcrumb" />

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary"></div>
        </div>

        <template v-else-if="supplier">
            <SupplierHeader :supplier="supplier" />

            <ul class="nav nav-tabs mb-3">
                <li class="nav-item" v-for="tab in tabs" :key="tab.key">
                    <button
                        class="nav-link"
                        :class="{ active: activeTab === tab.key }"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </li>
            </ul>

            <div v-if="activeTab === 'vendors'" class="tab-pane active">
                <SupplierVendors :supplierId="supplier.id" />
            </div>

            <div v-if="activeTab === 'prices'" class="tab-pane active">
                <SupplierProducts :supplierId="supplier.id" :prices="prices" />
            </div>

            <div v-if="activeTab === 'invoices'" class="tab-pane active">
                <SupplierInvoices :supplierId="supplier.id" />
            </div>
        </template>
    </div>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import Breadcrumb from "../../../base/shared/Breadcrumb.vue";
import SupplierHeader from "./components/SupplierHeader.vue";
import SupplierProducts from "./components/SupplierProducts.vue";
import SupplierInvoices from "./components/SupplierInvoices.vue";
import SupplierVendors from "./components/SupplierVendors.vue";

export default {
    name: "SupplierShow",
    components: {
        Breadcrumb,
        SupplierHeader,
        SupplierProducts,
        SupplierInvoices,
        SupplierVendors,
    },
    props: {
        supplier: Object,
    },
    setup(props) {

        const supplier = props.supplier;
        const loading = ref(false);
        const activeTab = ref("vendors");

        const vendors = ref([]);
        const prices = ref([]);

        const breadcrumb = computed(() => [
            { title: "Pagina" },
            { title: "Inventario" },
            { title: "Proveedores", href: "/inventory/supplier" },
            { title: supplier.name ?? "Detalle", active: true },
        ]);

        const tabs = computed(() => [
            { key: "vendors", label: "Vendedores" },
            { key: "prices", label: "Catálogo de Productos y Precios" },
            { key: "invoices", label: "Facturas" },
        ]);

        return {
            supplier,
            loading,
            activeTab,
            tabs,
            vendors,
            prices,
            breadcrumb,
        };
    },
};
</script>
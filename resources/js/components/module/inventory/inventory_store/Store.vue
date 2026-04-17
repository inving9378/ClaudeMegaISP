<template>
    <div class="d-flex justify-content-between">
        <Breadcrumb :list="breadcrumbList" />
    </div>

    <div class="q-pa-md">
        <div class="q-gutter-y-md" style="max-width: 600px">
            <q-tabs
                v-model="activeTab"
                dense
                align="left"
                :breakpoint="0"
            >
                <q-tab
                    name="inventory"
                    label="Inventario"
                    v-if="tabs.includes('inventory')"
                />
                <q-tab
                    name="pending"
                    label="Pedidos pendientes"
                    v-if="tabs.includes('pending')"
                />
                <q-tab
                    name="custom_models"
                    label="Articulos Custom"
                    v-if="tabs.includes('custom_models')"
                />
            </q-tabs>
        </div>
    </div>
    <q-tab-panels v-model="activeTab" animated>
        <q-tab-panel name="inventory" v-if="tabs.includes('inventory')">
            <InventoryItems :url_base="url_base" :store_id="store_id"></InventoryItems>
        </q-tab-panel>

        <q-tab-panel name="pending" v-if="tabs.includes('pending')">
            <ItemsPending :store_id="store_id"></ItemsPending>
        </q-tab-panel>

        <q-tab-panel name="custom_models" v-if="tabs.includes('custom_models')">
            <InventoryItemCustomModelListar></InventoryItemCustomModelListar>
        </q-tab-panel>
    </q-tab-panels>
</template>

<script>
import { ref, onMounted, computed } from "vue";
import Breadcrumb from "../../../base/shared/Breadcrumb.vue";
import InventoryItems from "./components/InventoryItems.vue";
import ItemsPending from "./components/ItemsPending.vue";
import InventoryItemCustomModelListar from "../inventory_item_custom_model/InventoryItemCustomModelListar.vue";

export default {
    name: "Store",
    components: {
        Breadcrumb,
        InventoryItems,
        ItemsPending,
        InventoryItemCustomModelListar
    },
    props: {
        store_id: String | Number,
        url_base: String
    },
    setup(props) {
        const breadcrumbList = ref([
            { title: "Almacenes", a: "/inventory/inventory_store" },
            { title: "", active: true },
        ]);

        const tabs = ref(["inventory", "pending","custom_models"]);

        const activeTab = ref("inventory");

        const setActiveTab = (tab) => {
            activeTab.value = tab;
        };

        onMounted(() => {});

        return {
            breadcrumbList,
            activeTab,
            setActiveTab,
            tabs,
        };
    },
};
</script>

<style scoped>
.q-tab {
    flex: 0 1 auto; /* Esto hace que las pestañas se ajusten al contenido */
    white-space: nowrap; /* Evita que el texto se divida en varias líneas */
}
</style>

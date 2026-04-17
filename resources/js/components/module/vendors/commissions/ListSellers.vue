<template>
    <div>
        <div class="d-flex justify-content-start mb-3">
            <!-- <button class="btn btn-primary" @click="goBack">Regresar</button> -->
            <button class="btn btn-outline-primary" @click="goBack">
                <i class="fas fa-arrow-left"></i> Regresar
            </button>
        </div>
        <h3 class="text-center my-3">Lista de vendedores</h3>
        <div class="q-pa-md">
            <q-table
                v-table-resizable
                :rows="sellers"
                :columns="columns"
                :loading="loading"
                :dark="darkMode"
                v-model:pagination="pagination"
                :rows-per-page-label="'Elementos por página'"
                :rows-per-page-options="rowPerPageOptions"
                no-data-label="No hay elementos para mostrar"
                row-key="name"
            >
                <template v-slot:body-cell-seller="props">
                    <q-td :props="props">
                        <a
                            :href="
                                '/vendedores/' +
                                props.row.seller_id +
                                '/seguimiento-vendedor/' +
                                props.row.user_id
                            "
                            >{{ props.row.seller }}</a
                        >
                        <!-- <a :href="'vendedores/12/seguimiento-vendedor/3638' + props.row.id">{{
                            props.row.seller
                        }}</a> -->
                    </q-td>
                </template>
                <template v-slot:body-cell-amount="props">
                    <q-td :props="props"> $ {{ props.row.amount }} </q-td>
                </template>
                <template v-slot:body-cell-fixed_sales_commission="props">
                    <q-td :props="props">
                        $ {{ props.row.fixed_sales_commission }}
                    </q-td>
                </template>
                <template v-slot:body-cell-commission_percentage="props">
                    <q-td :props="props">
                        {{ props.row.commission_percentage }}%
                    </q-td>
                </template>
                <template v-slot:body-cell-total_bonus="props">
                    <q-td :props="props"> $ {{ props.row.total_bonus }} </q-td>
                </template>
                <template v-slot:body-cell-installation_cost="props">
                    <q-td :props="props">
                        $ {{ props.row.installation_cost }}
                    </q-td>
                </template>
            </q-table>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { getSellersByRule } from "./helper/helper";
import { darkMode } from "../../../../hook/appConfig";

const props = defineProps({
    id_rule: {
        type: Number,
        required: true,
    },
});

const columns = ref([
    {
        name: "id",
        label: "Id de la regla",
        align: "center",
        field: "id",
        sortable: true,
        visible: true,
    },
    {
        name: "seller",
        label: "Nombre del vendedor",
        align: "left",
        field: "seller",
        sortable: true,
        visible: true,
    },
    {
        name: "zone",
        label: "Zona",
        align: "left",
        field: "zone",
        sortable: true,
        visible: true,
    },
    {
        name: "amount",
        label: "Sueldo",
        align: "left",
        field: "amount",
        sortable: true,
        visible: true,
    },
    {
        name: "fixed_sales_commission",
        label: "Comision (Fija)",
        align: "left",
        field: "fixed_sales_commission",
        sortable: true,
        visible: true,
    },
    {
        name: "commission_percentage",
        label: "Comision (Porcentaje)",
        align: "center",
        field: "commission_percentage",
        sortable: true,
        visible: true,
    },
    {
        name: "period",
        label: "Periodo",
        align: "left",
        field: "period",
        sortable: true,
        visible: true,
    },
    {
        name: "number_of_prospects",
        label: "Número de prospectos",
        align: "center",
        field: "number_of_prospects",
        sortable: true,
        visible: true,
    },
    {
        name: "minimum_sales",
        label: "Minimo de ventas",
        align: "center",
        field: "minimum_sales",
        sortable: true,
        visible: true,
    },
    {
        name: "total_bonus",
        label: "Bono mensual",
        align: "left",
        field: "total_bonus",
        sortable: true,
        visible: true,
    },
    {
        name: "number_sales_required",
        label: "Número de ventas requerido",
        align: "center",
        field: "number_sales_required",
        sortable: true,
        visible: true,
    },
    {
        name: "installation_cost",
        label: "Costo de instalacion",
        align: "left",
        field: "installation_cost",
        sortable: true,
        visible: true,
    },
]);

const sellers = ref([]);
const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 0]);
const loading = ref(false);

const pagination = ref({
    page: 1,
    rowsPerPage: 50,
    rowsNumber: 0,
});

onMounted(() => {
    getListSellers();
});

const getListSellers = async () => {
    try {
        loading.value = true;
        sellers.value = await getSellersByRule(props.id_rule);
        loading.value = false;
    } catch (error) {
        loading.value = false;
        console.log(error);
    }
};

const goBack = () => {
    window.history.back();
};
</script>

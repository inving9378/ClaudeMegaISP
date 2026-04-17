<template>
    <span
        class="badge-primary cursor-pointer"
        @click="showDialog = true"
        v-if="badge"
    >
        {{ payments }}
    </span>
    <a href="javascript:;" @click="showDialog = true" v-else>{{ payments }}</a>
    <q-dialog v-model="showDialog" persistent>
        <q-card style="width: 900px; max-width: 90vw">
            <q-card-section>
                <div class="text-h6">
                    Primeros pagos realizados por {{ client }}
                </div>
            </q-card-section>
            <q-separator />
            <q-card-section style="max-height: 60vh" class="scroll">
                <q-table
                    v-table-resizable
                    :dark="darkMode"
                    :columns="columns"
                    :rows="data"
                    wrap-cells
                    color="primary"
                    flat
                    hide-bottom
                ></q-table>
            </q-card-section>
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Cerrar"
                    no-caps
                    @click="showDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref } from "vue";
import { darkMode } from "../../../../../hook/appConfig";

const props = defineProps({
    data: {
        type: Array,
        default: [],
    },
    badge: {
        type: Boolean,
        default: true,
    },
    payments: {
        type: Number,
        default: 0,
    },
    client: String,
});

const showDialog = ref(false);

const columns = ref([
    {
        name: "id",
        field: "id",
        align: "start",
        label: "ID",
        sortable: true,
        visible: true,
    },
    {
        name: "date",
        field: "date",
        align: "start",
        label: "Fecha",
        sortable: true,
        visible: true,
    },
    {
        name: "amount",
        field: "amount",
        align: "start",
        label: "Cantidad pagada",
        sortable: true,
        visible: true,
    },
    {
        name: "method",
        field: "method",
        align: "start",
        label: "Método de pago",
        sortable: true,
        visible: true,
    },
    {
        name: "period",
        field: "period",
        align: "start",
        label: "Período",
        sortable: true,
        visible: true,
    },
    {
        name: "receipt",
        field: "receipt",
        align: "start",
        label: "Recibo",
        sortable: true,
        visible: true,
    },
    {
        name: "created_by",
        field: "created_by",
        align: "start",
        label: "Creado por",
        sortable: true,
        visible: true,
    },
]);
</script>

<style scoped>
.badge-primary {
    background-color: #357bf2;
    color: #ffffff;
    padding: 0 8px;
    padding-top: 2px;
    padding-bottom: 2px;
    border-radius: 3px;
    font-weight: 500;
}
</style>

<template>
    <div class="q-pa-md">
        <div class="row items-center justify-between q-mb-md">
            <div>
                <div class="text-h6">Revisión de nomenclatura de ONU</div>
                <div class="text-caption text-grey">
                    Clientes con nomenclatura vieja capturada pero sin la nomenclatura real
                    todavía. Captúrala una vez y desaparece de esta lista.
                </div>
            </div>
            <q-input
                v-model="buscar"
                dense
                outlined
                debounce="400"
                placeholder="Buscar por cliente o nomenclatura vieja…"
                style="min-width: 280px"
                clearable
                @update:model-value="cargar(1)"
            >
                <template v-slot:prepend><q-icon name="search" /></template>
            </q-input>
        </div>

        <q-banner v-if="!cargando && filas.length === 0" class="bg-positive text-white">
            <template v-slot:avatar><q-icon name="mdi-check-circle" /></template>
            No quedan clientes pendientes de revisar.
        </q-banner>

        <q-table
            v-else
            :rows="filas"
            :columns="columnas"
            row-key="client_id"
            :loading="cargando"
            v-model:pagination="paginacion"
            flat
            bordered
            @request="onRequest"
        >
            <template v-slot:body-cell-nueva="props">
                <q-td :props="props">
                    <div class="row items-center no-wrap q-gutter-xs">
                        <q-input
                            v-model="edicion[props.row.client_id]"
                            dense
                            outlined
                            placeholder="Nomenclatura real"
                            style="min-width: 180px"
                            @keyup.enter="guardar(props.row)"
                        />
                        <q-btn
                            flat
                            dense
                            round
                            icon="mdi-content-save"
                            color="primary"
                            :loading="guardando[props.row.client_id]"
                            :disable="!edicion[props.row.client_id]"
                            @click="guardar(props.row)"
                        />
                    </div>
                </q-td>
            </template>
        </q-table>
    </div>
</template>

<script setup>
// Sesión 2026-10-09 (decisión de Irving) — reconciliación manual de box_nomenclator_old
// (vieja, desordenada) contra box_nomenclator (la real). No hay forma mecánica de derivar
// la nueva a partir de la vieja, así que esta pantalla es solo-lectura de la vieja + captura
// manual de la nueva, una por una.
import { ref, reactive, onMounted } from "vue";
import { useQuasar } from "../../../../../public/plugins/quasar/js/quasar.umd.prod";

defineOptions({ name: "NomenclaturaOnuReview" });

const $q = useQuasar();

const filas = ref([]);
const cargando = ref(false);
const buscar = ref("");
const edicion = reactive({});
const guardando = reactive({});

const paginacion = ref({
    page: 1,
    rowsPerPage: 25,
    rowsNumber: 0,
});

const columnas = [
    {
        name: "cliente",
        label: "Cliente",
        align: "left",
        field: (row) => [row.name, row.father_last_name, row.mother_last_name].filter(Boolean).join(" "),
    },
    { name: "client_id", label: "ID", field: "client_id", align: "left" },
    { name: "vieja", label: "Nomenclatura vieja", field: "box_nomenclator_old", align: "left" },
    { name: "nueva", label: "Nomenclatura real (capturar)", align: "left" },
];

const cargar = async (page = paginacion.value.page) => {
    cargando.value = true;
    try {
        const response = await axios.get("/cliente/nomenclatura-onu/pendientes", {
            params: {
                page,
                per_page: paginacion.value.rowsPerPage,
                buscar: buscar.value || undefined,
            },
        });
        filas.value = response.data.data;
        paginacion.value.page = response.data.current_page;
        paginacion.value.rowsNumber = response.data.total;
    } catch (e) {
        $q.notify({ type: "negative", message: "No se pudo cargar la lista." });
    }
    cargando.value = false;
};

const onRequest = (requestProp) => {
    paginacion.value.rowsPerPage = requestProp.pagination.rowsPerPage;
    cargar(requestProp.pagination.page);
};

const guardar = async (row) => {
    const valor = (edicion[row.client_id] || "").trim();
    if (!valor) return;

    guardando[row.client_id] = true;
    try {
        await axios.put(`/cliente/nomenclatura-onu/${row.client_id}`, {
            box_nomenclator: valor,
        });
        $q.notify({ type: "positive", message: "Guardado correctamente." });
        filas.value = filas.value.filter((f) => f.client_id !== row.client_id);
        paginacion.value.rowsNumber = Math.max(0, paginacion.value.rowsNumber - 1);
    } catch (e) {
        $q.notify({
            type: "negative",
            message: e?.response?.data?.message || "No se pudo guardar.",
        });
    }
    guardando[row.client_id] = false;
};

onMounted(() => cargar(1));
</script>

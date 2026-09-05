<template>
    <q-btn round dense icon="mdi-account" color="primary" @click="dialog = true"
        ><q-tooltip> Administrar clientes </q-tooltip></q-btn
    >
    <q-dialog
        v-model="dialog"
        persistent
        full-width
        full-height
        @before-show="onShow"
        @hide="emits('change', change)"
    >
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section><h6>Clientes</h6></q-item-section>
                    <q-item-section avatar>
                        <q-btn
                            icon="close"
                            flat
                            round
                            dense
                            @click="dialog = false"
                        />
                    </q-item-section>
                </q-item>
            </q-card-section>

            <q-separator />

            <q-card-section>
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-xl-6 col-sm-12 col-xs-12">
                        <div class="row q-pa-xs">
                            <div class="col shadow-1 no-padding">
                                <p class="text-h6" style="margin: 10px 17px">
                                    Disponibles
                                </p>
                                <q-table
                                    v-table-resizable
                                    flat
                                    title="Disponibles"
                                    v-model:pagination="avaiablesPagination"
                                    :columns="columns"
                                    :rows="avaiableClients"
                                    :loading="avaiablesLoading"
                                    :dark="darkMode"
                                    wrap-cells
                                    row-key="id"
                                    color="primary"
                                    loading-label="Obteniendo clientes, por favor espere..."
                                    no-data-label="No existen clientes disponibles"
                                    no-results-label="No se encontraron coincidencias"
                                    rows-per-page-label="Clientes por página"
                                    :pagination-label="
                                        (start, end, total) =>
                                            `${start}-${end} de ${total}`
                                    "
                                    :pagination="{
                                        rowsPerPage: 20,
                                    }"
                                    selection="multiple"
                                    v-model:selected="avaiableClientsSelected"
                                    :selected-rows-label="
                                        getAvaiableSelectedString
                                    "
                                    :rows-per-page-options="[20, 30, 50, 100]"
                                    @request="onRequestAvaiables"
                                >
                                    <template v-slot:top-left>
                                        <q-input
                                            outlined
                                            dense
                                            debounce="300"
                                            v-model="queryAvaiables"
                                            placeholder="Buscar"
                                            :dark="darkMode"
                                            @keypress.enter="onSearch(false)"
                                        >
                                            <template v-slot:after>
                                                <q-btn
                                                    :disable="avaiablesLoading"
                                                    icon="search"
                                                    color="primary"
                                                    padding="8px"
                                                    @click="onSearch(false)"
                                                />
                                                <q-btn
                                                    :disable="avaiablesLoading"
                                                    icon="mdi-eraser"
                                                    color="brown-5"
                                                    padding="8px"
                                                    @click="onClear(false)"
                                                    v-if="serchedAvaiables"
                                                />
                                            </template>
                                        </q-input>
                                    </template>
                                    <template v-slot:top-right>
                                        <div
                                            class="d-flex justify-content-end align-items-center"
                                        >
                                            <q-btn
                                                :loading="avaiablesLoading"
                                                color="primary"
                                                class="q-mr-sm"
                                                icon="mdi-sync"
                                                @click="
                                                    () => {
                                                        onRequestAvaiables();
                                                    }
                                                "
                                                ><q-tooltip
                                                    >Actualizar</q-tooltip
                                                ></q-btn
                                            >
                                            <q-btn
                                                :disable="
                                                    avaiablesLoading ||
                                                    avaiableClientsSelected.length ===
                                                        0
                                                "
                                                :icon="
                                                    $q.screen.xs || $q.screen.sm
                                                        ? 'mdi-arrow-down-circle-outline'
                                                        : 'mdi-arrow-right-circle-outline'
                                                "
                                                color="primary"
                                                @click="
                                                    addSelected(
                                                        avaiableClientsSelected.map(
                                                            (c) => c.id
                                                        )
                                                    )
                                                "
                                            >
                                                <q-tooltip
                                                    v-if="
                                                        avaiableClientsSelected.length >
                                                        0
                                                    "
                                                    >Pasar a
                                                    seleccionados</q-tooltip
                                                >
                                            </q-btn>
                                        </div>
                                    </template>
                                    <template v-slot:body-cell-actions="props">
                                        <q-td class="text-center">
                                            <q-btn
                                                flat
                                                padding="0px"
                                                :disable="avaiablesLoading"
                                                :icon="
                                                    $q.screen.xs || $q.screen.sm
                                                        ? 'mdi-arrow-down-circle-outline'
                                                        : 'mdi-arrow-right-circle-outline'
                                                "
                                                round
                                                color="primary"
                                                @click="
                                                    addSelected([props.row.id])
                                                "
                                            >
                                                <q-tooltip
                                                    >Pasar a
                                                    seleccionados</q-tooltip
                                                >
                                            </q-btn>
                                        </q-td>
                                    </template>
                                </q-table>
                            </div>
                        </div>
                    </div>
                    <div
                        class="col-lg-6 col-md-6 col-xl-6 col-sm-12 col-xs-12"
                        :class="$q.screen.xs || $q.screen.sm ? 'q-pt-lg' : null"
                    >
                        <div class="row q-pa-xs">
                            <div class="col shadow-1 no-padding">
                                <p class="text-h6" style="margin: 10px 17px">
                                    Seleccionados
                                </p>
                                <q-table
                                    v-table-resizable
                                    flat
                                    title="Seleccionados"
                                    v-model:pagination="selectedPagination"
                                    :columns="columns"
                                    :rows="selectedClients"
                                    :loading="selectedLoading"
                                    :dark="darkMode"
                                    wrap-cells
                                    row-key="id"
                                    color="primary"
                                    loading-label="Obteniendo clientes, por favor espere..."
                                    no-data-label="No existen clientes disponibles"
                                    no-results-label="No se encontraron coincidencias"
                                    rows-per-page-label="Clientes por página"
                                    :pagination-label="
                                        (start, end, total) =>
                                            `${start}-${end} de ${total}`
                                    "
                                    :rows-per-page-options="[
                                        5, 10, 20, 30, 50, 100,
                                    ]"
                                    :pagination="{
                                        rowsPerPage: 20,
                                    }"
                                    :selected-rows-label="
                                        getSelectedSelectedString
                                    "
                                    @request="onRequestSelected"
                                >
                                    <template v-slot:top-left>
                                        <q-input
                                            outlined
                                            dense
                                            debounce="300"
                                            v-model="querySelected"
                                            placeholder="Buscar"
                                            :dark="darkMode"
                                            @keypress.enter="onSearch(true)"
                                        >
                                            <template v-slot:after>
                                                <q-btn
                                                    :disable="selectedLoading"
                                                    icon="search"
                                                    color="primary"
                                                    padding="8px"
                                                    @click="onSearch(true)"
                                                />
                                                <q-btn
                                                    :disable="selectedLoading"
                                                    icon="mdi-eraser"
                                                    color="brown-5"
                                                    padding="8px"
                                                    @click="onClear(true)"
                                                    v-if="serchedSelected"
                                                />
                                            </template>
                                        </q-input>
                                    </template>
                                    <template v-slot:top-right>
                                        <div
                                            class="d-flex justify-content-end align-items-center"
                                        >
                                            <q-btn
                                                :loading="selectedLoading"
                                                color="primary"
                                                class="q-mr-sm"
                                                icon="mdi-sync"
                                                @click.stop="
                                                    () => {
                                                        onRequestSelected();
                                                    }
                                                "
                                                ><q-tooltip
                                                    >Actualizar</q-tooltip
                                                ></q-btn
                                            >
                                            <!-- <q-btn
                                                :disable="
                                                    selectedLoading ||
                                                    selectedClientsSelected.length ===
                                                        0
                                                "
                                                :icon="
                                                    $q.screen.xs || $q.screen.sm
                                                        ? 'mdi-arrow-up-circle-outline'
                                                        : 'mdi-arrow-left-circle-outline'
                                                "
                                                color="primary"
                                                @click="
                                                    removeSelected(
                                                        selectedClientsSelected.map(
                                                            (c) => c.id
                                                        )
                                                    )
                                                "
                                            >
                                                <q-tooltip
                                                    v-if="
                                                        selectedClientsSelected.length >
                                                        0
                                                    "
                                                    >Pasar a
                                                    disponibles</q-tooltip
                                                >
                                            </q-btn> -->
                                        </div>
                                    </template>
                                    <!-- <template v-slot:body-cell-actions="props">
                                        <q-td class="text-center">
                                            <q-btn
                                                padding="0px"
                                                :disable="selectedLoading"
                                                :icon="
                                                    $q.screen.xs || $q.screen.sm
                                                        ? 'mdi-arrow-up-circle-outline'
                                                        : 'mdi-arrow-left-circle-outline'
                                                "
                                                flat
                                                round
                                                color="primary"
                                                @click="
                                                    removeSelected([
                                                        props.row.id,
                                                    ])
                                                "
                                                ><q-tooltip
                                                    >Pasar a
                                                    disponibles</q-tooltip
                                                ></q-btn
                                            >
                                        </q-td>
                                    </template> -->
                                </q-table>
                            </div>
                        </div>
                    </div>
                </div>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right" style="margin: 0px !important">
                <q-btn
                    no-caps
                    color="primary"
                    label="Cerrar"
                    @click="dialog = false"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, watch } from "vue";
import { darkMode } from "../../../../hook/appConfig";
import {
    addClientsToServiceBox,
    getAvaiablesClients,
    getSelectedClients,
    removeClientsFromServiceBox,
} from "../helper/request";
import { useQuasar } from "../../../../../../public/plugins/quasar/js/quasar.umd.prod";

defineOptions({
    name: "ClientToServiceBoxComponent",
});

const props = defineProps({
    object: Object,
    parent_id: {
        type: Number,
        default: null,
    },
});

const emits = defineEmits(["change"]);

const $q = useQuasar();

const dialog = ref(false);
const selectedLoading = ref(false);
const avaiablesLoading = ref(false);
const change = ref(false);

const columns = ref([
    {
        name: "client_id",
        field: "client_id",
        label: "Id del cliente",
        align: "left",
        sortable: true,
    },
    {
        name: "client_name_with_fathers_names",
        field: "client_name_with_fathers_names",
        label: "Nombre del cliente",
        align: "left",
        sortable: true,
    },
    {
        name: "estado",
        field: "estado",
        label: "Estado",
        align: "right",
        sortable: true,
    },
    {
        name: "actions",
        field: "actions",
        label: "Acciones",
        align: "center",
        sortable: false,
    },
]);

const avaiableClients = ref([]);
const selectedClients = ref([]);
const avaiableClientsSelected = ref([]);
const selectedClientsSelected = ref([]);
const serchedSelected = ref(false);
const serchedAvaiables = ref(false);
const queryAvaiables = ref(null);
const querySelected = ref(null);

const avaiablesPagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 1,
});

const selectedPagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 1,
});

watch(
    () => props.show,
    (n) => {
        if (n) {
            dialog.value = true;
        }
    }
);

const onShow = () => {
    change.value = false;
    avaiableClients.value = [];
    selectedClients.value = [];
    avaiableClientsSelected.value = [];
    selectedClientsSelected.value = [];
    serchedSelected.value = false;
    serchedAvaiables.value = false;
    avaiablesPagination.value = {
        descending: false,
        page: 1,
        rowsPerPage: 20,
        rowsNumber: 1,
    };
    selectedPagination.value = {
        descending: false,
        page: 1,
        rowsPerPage: 20,
        rowsNumber: 1,
    };
    queryAvaiables.value = null;
    querySelected.value = null;
    loadClients();
};

const loadClients = () => {
    onRequestSelected();
    onRequestAvaiables();
};

const onSearch = (selected = false) => {
    const search = selected ? querySelected.value : queryAvaiables.value;
    if (search !== null && search.trim() !== "") {
        if (selected) {
            serchedSelected.value = true;
            onRequestSelected();
        } else {
            serchedAvaiables.value = true;
            onRequestAvaiables();
        }
    }
};

const onClear = (selected = false) => {
    if (selected) {
        querySelected.value = null;
        serchedSelected.value = false;
        onRequestSelected();
    } else {
        queryAvaiables.value = null;
        serchedAvaiables.value = false;
        onRequestAvaiables();
    }
};

const onRequestSelected = async (attrs) => {
    selectedLoading.value = true;
    if (attrs) {
        selectedPagination.value = attrs.pagination;
    }
    let data = await getSelectedClients(props.object.id, {
        ...selectedPagination.value,
        search: querySelected.value,
        device_id: props.parent_id ?? null,
    });
    selectedLoading.value = false;
    if (data !== null) {
        selectedClients.value = data.data;
        selectedPagination.value.rowsNumber = data.total;
    } else {
        selectedClients.value = [];
    }
};

const onRequestAvaiables = async (attrs) => {
    avaiablesLoading.value = true;
    if (attrs) {
        avaiablesPagination.value = attrs.pagination;
    }
    let data = await getAvaiablesClients({
        ...avaiablesPagination.value,
        search: queryAvaiables.value,
    });
    avaiablesLoading.value = false;
    if (data !== null) {
        avaiableClients.value = data.data;
        avaiablesPagination.value.rowsNumber = data.total;
    } else {
        avaiableClients.value = [];
    }
};

const addSelected = async (clients) => {
    avaiablesLoading.value = true;
    selectedLoading.value = true;
    const data = await addClientsToServiceBox(props.object.id, {
        parent_id: props.parent_id ?? null,
        clients,
    });
    avaiablesLoading.value = false;
    selectedLoading.value = false;
    if (data) {
        change.value = true;
        loadClients();
    }
};

const removeSelected = async (ids) => {
    avaiablesLoading.value = true;
    selectedLoading.value = true;
    const data = await removeClientsFromServiceBox(ids);
    avaiablesLoading.value = false;
    selectedLoading.value = false;
    if (data) {
        change.value = true;
        loadClients();
    }
};

const getAvaiableSelectedString = () => {
    return avaiableClientsSelected.value.length === 0
        ? ""
        : `${avaiableClientsSelected.value.length} cliente${
              avaiableClientsSelected.value.length > 1 ? "s" : ""
          } seleccionados de ${avaiableClients.value.length}`;
};

const getSelectedSelectedString = () => {
    return selectedClientsSelected.value.length === 0
        ? ""
        : `${selectedClientsSelected.value.length} cliente${
              selectedClientsSelected.value.length > 1 ? "s" : ""
          } seleccionados de ${selectedClients.value.length}`;
};
</script>

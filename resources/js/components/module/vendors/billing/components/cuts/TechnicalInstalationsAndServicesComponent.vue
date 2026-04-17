<template>
    <q-card class="my-card" flat bordered>
        <q-item>
            <q-item-section>
                <q-item-label
                    >Instalaciones y servicios técnicos</q-item-label
                > </q-item-section
            ><q-item-section class="no-padding" avatar>
                <form-installation-component
                    :box-id="box.id"
                    :branchs="branchList"
                    :technicals="technicals"
                    :clients="clients"
                    :sucursal-id="sucursalId"
                    @created="(r) => rows.push(r)"
                    v-if="hasAdd && !closing && !box.closed"
                />
            </q-item-section>
            <q-item-section class="no-padding" avatar>
                <q-btn
                    color="primary"
                    class="q-mr-sm"
                    label="..."
                    @click="showModal = true"
                />
            </q-item-section>
            <q-item-section class="no-padding" avatar>
                <q-btn
                    color="primary"
                    class="q-mr-sm"
                    icon="history"
                    :loading="loading"
                    @click="onRequest"
                />
            </q-item-section>
        </q-item>

        <q-separator />

        <q-card-section>
            <q-table
                v-table-resizable="visibleColumns"
                :columns="visibleColumns"
                :rows="filteredRows"
                :loading="loading"
                :dark="darkMode"
                flat
                wrap-cells
                row-key="id"
                loading-label="Obteniendo registros, por favor espere..."
                no-data-label="No existen registros disponibles"
                no-results-label="No se encontraron coincidencias"
                rows-per-page-label="registros por página"
                :pagination-label="
                    (start, end, total) => `${start}-${end} de ${total}`
                "
                :rows-per-page-options="[20, 30, 50, 100]"
            >
                <template v-slot:top>
                    <div class="row no-padding">
                        <div class="col">
                            <label for="search">ID/Cliente/Técnico</label>
                            <q-input
                                dense
                                outlined
                                color="primary"
                                v-model="filters.search"
                                clearable
                                :dark="darkMode"
                            />
                        </div>
                        <div class="col">
                            <label for="activated">Activó</label>
                            <q-select
                                v-model="filters.activated"
                                outlined
                                for="activated"
                                dense
                                options-dense
                                emit-value
                                :clearable="true"
                                map-options
                                :options="[
                                    {
                                        label: 'Si',
                                        value: true,
                                    },
                                    {
                                        label: 'No',
                                        value: false,
                                    },
                                ]"
                                :rules="[(val) => val !== null || 'Requerido']"
                                :dark="darkMode"
                            >
                                <template v-slot:selected-item="scope">
                                    <q-item-label
                                        lines="1"
                                        style="margin-top: 5px"
                                        >{{ scope.opt.label }}</q-item-label
                                    >
                                </template>
                            </q-select>
                        </div>
                        <div class="col">
                            <label for="branch_id">Sucursal</label>
                            <q-select
                                v-model="filters.branch"
                                outlined
                                for="branch_id"
                                dense
                                options-dense
                                option-label="name"
                                option-value="id"
                                emit-value
                                :clearable="true"
                                map-options
                                :options="branchList"
                                :rules="[(val) => !!val || 'Requerido']"
                                :dark="darkMode"
                            >
                                <template v-slot:selected-item="scope">
                                    <q-item-label
                                        lines="1"
                                        style="margin-top: 5px"
                                        >{{ scope.opt.name }}</q-item-label
                                    >
                                </template>
                            </q-select>
                        </div>
                    </div>
                </template>
                <template v-slot:body-cell-actions="props">
                    <q-td class="text-center" style="width: 100px">
                        <form-installation-component
                            :object="props.row"
                            :branchs="branchList"
                            :technicals="technicals"
                            :clients="clients"
                            @updated="onUpdateRow"
                            v-if="hasEdit"
                        />
                        <q-btn
                            icon="delete"
                            flat
                            round
                            dense
                            color="danger"
                            size="12px"
                            :loading="props.row.loading"
                            @click="destroy(props.row)"
                            v-if="hasDelete"
                        />
                    </q-td>
                </template>
                <template
                    v-slot:bottom-row
                    v-if="
                        visibleColumns
                            .map((c) => c.name)
                            .includes('service_amount')
                    "
                >
                    <tr>
                        <td
                            v-for="c in visibleColumns.length -
                            (hasActions ? 3 : 2)"
                            :key="`col-payment-installation-${c}`"
                        ></td>
                        <td
                            class="text-right text-bold"
                            style="padding-right: 0"
                        >
                            Total:
                        </td>
                        <td>{{ totalAmount }}</td>
                        <td v-if="hasActions"></td>
                    </tr>
                </template>
            </q-table>
        </q-card-section>
    </q-card>

    <modal
        :show="showModal"
        :size="'xs'"
        @update:show="showModal = $event"
        title="Mostrar columnas/Ocultar columnas"
    >
        <template #body>
            <div class="my-3">
                <p>
                    Para mostrar los campos de la tabla, seleccione la casilla
                    de verificación correspondiente.
                </p>
            </div>
            <div
                class="form-check form-switch form-switch-md"
                v-for="(column, index) in columns.filter((c) => !c.required)"
                :key="index"
            >
                <input
                    class="form-check-input"
                    type="checkbox"
                    v-model="column.visible"
                />
                <label class="form-check-label">{{ column.label }}</label>
            </div>
        </template>
        <template #footer>
            <button class="btn btn-primary" @click="saveColumnsTable">
                Guardar
            </button>
        </template>
    </modal>
</template>

<script setup>
import { ref, onMounted, computed, onBeforeMount, watch } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import Modal from "../../../../../../shared/ModalSimple.vue";
import FormInstallationComponent from "./FormInstallationComponent.vue";
import { darkMode } from "../../../../../../hook/appConfig";
import { useDataTable } from "../../../../../../composables/useDataTable";
import {
    destroyInstallation,
    listInstallations,
} from "../../helper/cutInstallations";
import { getBranchs, getTechnicals } from "../../helper/helper";
import axios from "axios";

const props = defineProps({
    object: Object,
    box: Object,
    sucursalId: Number,
    hasPermission: Object,
    closing: Boolean,
});

const emits = defineEmits(["loaded"]);

const showModal = ref(false);
const loading = ref(false);
const tableIdentifier = ref("billing-technical-installation-and-services");
const { getColumns, saveColumns } = useDataTable();
const branchList = ref([]);
const technicals = ref([]);
const filters = ref(null);
const clients = ref([]);

const columns = ref([
    {
        name: "client_str",
        field: "client_str",
        label: "Cliente",
        align: "left",
        sortable: true,
        required: true,
    },
    {
        name: "branch_str",
        field: "branch_str",
        label: "Sucursal",
        align: "left",
        sortable: true,
    },
    {
        name: "activated",
        field: "activated",
        label: "Activó",
        align: "left",
        sortable: true,
        format: (val) => (val ? "Si" : "No"),
    },
    {
        name: "installation_cost",
        field: "installation_cost",
        label: "Instalación",
        align: "left",
        sortable: false,
    },
    {
        name: "service_amount",
        field: "service_amount",
        label: "Servicio",
        align: "left",
        sortable: false,
    },
    {
        name: "constance",
        field: "constance",
        label: "Constancia",
        align: "left",
        sortable: false,
    },
    {
        name: "warranty_cost",
        field: "warranty_cost",
        label: "Garantía",
        align: "left",
        sortable: false,
    },
    {
        name: "technical_str",
        field: "technical_str",
        label: "Instaló",
        align: "left",
        sortable: false,
    },
    {
        name: "comments",
        field: "comments",
        label: "Comentarios",
        align: "left",
        sortable: true,
    },
    {
        name: "service_amount",
        field: "service_amount",
        label: "Total",
        align: "left",
        sortable: false,
        required: true,
    },
]);

const rows = ref([]);

onBeforeMount(() => {
    initFilters();
});

onMounted(() => {
    if (hasActions.value) {
        columns.value.push({
            name: "actions",
            field: "actions",
            label: "Acciones",
            align: "center",
            sortable: false,
            required: true,
            style: "width: 100px",
        });
    }
    loadBranchs();
    getColumnsTable();
    onRequest();
    loadTechnicals();
    loadClients();
});

watch(
    () => props.box,
    () => {
        initFilters();
        onRequest();
    },
    {
        deep: true,
    }
);

const initFilters = () => {
    filters.value = {
        search: null,
        activated: null,
        branch: null,
        createdBy: null,
    };
};

const hasAdd = computed(() => {
    return (
        props.hasPermission?.data.canView("seller_cuts_add_installation") ??
        false
    );
});

const hasEdit = computed(() => {
    return (
        props.hasPermission?.data.canView("seller_cuts_edit_installation") ??
        false
    );
});

const hasDelete = computed(() => {
    return (
        props.hasPermission?.data.canView("seller_cuts_delete_installation") ??
        false
    );
});

const hasActions = computed(() => {
    return (hasAdd.value || hasEdit.value) && !props.box.closed;
});

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);

const filteredRows = computed(() => {
    let temp = rows.value;
    const { search, activated, branch } = filters.value;
    if (search) {
        let s = search.toLowerCase();
        temp = temp.filter(
            (r) =>
                r.client_str.toLowerCase().includes(s) ||
                r.technical_str.toLowerCase().includes(s) ||
                r.id.toString().includes(s)
        );
    }
    if (activated !== null) {
        temp = temp.filter((r) => r.activated === activated);
    }
    if (branch !== null) {
        temp = temp.filter((r) => r.branch_id === branch);
    }
    return temp;
});

const totalAmount = computed(() => {
    const total = filteredRows.value
        .filter((r) => r.activated)
        .reduce((t, p) => t + p.service_amount + p.installation_cost, 0);
    emits("loaded", total);
    return total;
});

const onRequest = async () => {
    loading.value = true;
    let data = await listInstallations(props.box.id);
    if (data !== null) {
        rows.value = data;
    } else {
        rows.value = [];
    }
    loading.value = false;
};

const getColumnsTable = async () => {
    try {
        const response = await getColumns(tableIdentifier.value);
        const storedColumns = response;
        if (storedColumns && storedColumns.length > 0) {
            columns.value.forEach((column) => {
                const storedColumn = storedColumns.find(
                    (col) => col.name === column.name
                );
                if (storedColumn) {
                    column.visible = storedColumn.visible;
                }
            });
        } else {
            columns.value.forEach((column) => {
                column.visible = true;
            });
        }
    } catch (error) {
        console.log(error);
    }
};

const saveColumnsTable = async () => {
    try {
        const columnsData = columns.value.map((col) => ({
            name: col.name,
            visible: col.visible,
        }));

        await saveColumns(tableIdentifier.value, columnsData);
        showModal.value = false;
    } catch (error) {
        console.log(error);
    }
};

const loadBranchs = async () => {
    let data = await getBranchs();
    branchList.value = data;
};

const onUpdateRow = (r) => {
    const row = rows.value.find((rr) => rr.id === r.id);
    if (row) {
        Object.assign(row, r);
    }
};

const destroy = async (object) => {
    Swal.fire({
        title: "Confirmación!",
        text: "Seguro que desea eliminar esta instalación?",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Si",
        cancelButtonText: "No",
    }).then(async (result) => {
        if (result.isConfirmed) {
            object.loading = true;
            const result = await destroyInstallation(object.id);
            if (result) {
                message("Instalación eliminada correctamente");
                rows.value = rows.value.filter((r) => r.id !== object.id);
            } else {
                object.loading = false;
                error500();
            }
        }
    });
};

const loadTechnicals = async () => {
    let data = await getTechnicals(props.box.id);
    if (data !== null) {
        let temp = data.forEach((d) => {
            d[
                "full_name"
            ] = `${d.name} ${d.father_last_name} ${d.mother_last_name}`;
        });
        technicals.value = data;
    } else {
        technicals.value = [];
    }
};

const loadClients = async () => {
    let result = await axios.get("/cliente/actives");
    clients.value = result.data ?? [];
};
</script>

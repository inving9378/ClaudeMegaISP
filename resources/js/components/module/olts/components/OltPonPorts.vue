<template>
    <q-table
        v-table-resizable="visibleColumns"
        :columns="visibleColumns"
        :rows="props.object.rows"
        :loading="props.object.loading"
        :dark="darkMode"
        :filter="filter"
        flat
        wrap-cells
        row-key="id"
        color="primary"
        loading-label="Obteniendo datos, por favor espere..."
        no-data-label="No existen datos disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Tarjetas por página"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        :rows-per-page-options="[5, 10, 20, 30, 50, 100]"
        :pagination="{
            rowsPerPage: 20,
        }"
    >
        <template v-slot:top>
            <div class="row no-padding">
                <div class="col no-padding">
                    <q-input
                        dense
                        debounce="300"
                        v-model="filter"
                        placeholder="Filtrar"
                        :dark="darkMode"
                        style="width: 300px"
                    >
                        <template v-slot:append>
                            <q-icon name="search" />
                        </template>
                    </q-input>
                </div>
                <div class="col col-auto">
                    <q-btn
                        color="primary"
                        class="q-mr-sm"
                        label="..."
                        @click="columnsDialog = true"
                    />
                </div>
                <div class="col col-auto no-padding">
                    <q-btn-dropdown
                        color="primary"
                        label="Actualizción"
                        no-caps
                        split
                        :loading="object.loading"
                        @click="() => emits('reload', false)"
                    >
                        <q-list dense>
                            <q-item
                                clickable
                                @click="() => emits('reload', false)"
                            >
                                <q-item-section>
                                    <q-item-label>Local</q-item-label>
                                </q-item-section>
                            </q-item>
                            <q-item
                                clickable
                                @click="() => emits('reload', true)"
                            >
                                <q-item-section>
                                    <q-item-label
                                        >Desde API SmartOLT</q-item-label
                                    >
                                </q-item-section>
                            </q-item>
                        </q-list>
                    </q-btn-dropdown>
                </div>
            </div>
        </template>
        <template v-slot:body-cell-operational_status="props">
            <td>
                <q-badge :color="props.row.operational_status_cls">
                    {{ props.row.operational_status }}
                </q-badge>
            </td>
        </template>
    </q-table>

    <q-dialog v-model="columnsDialog" persistent>
        <q-card>
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">
                            Mostrar columnas/Ocultar columnas
                        </div>
                    </q-item-section>
                    <q-item-section avatar>
                        <q-btn
                            icon="close"
                            flat
                            round
                            dense
                            @click="showDialog = false"
                        />
                    </q-item-section>
                </q-item>
            </q-card-section>
            <q-separator />
            <q-card-section style="max-height: 60vh" class="scroll">
                <div class="my-3">
                    <p>
                        Para mostrar los campos de la tabla, seleccione la
                        casilla de verificación correspondiente.
                    </p>
                </div>
                <div
                    class="form-check form-switch form-switch-md"
                    v-for="(column, index) in columns"
                    :key="index"
                >
                    <input
                        class="form-check-input"
                        type="checkbox"
                        v-model="column.visible"
                    />
                    <label class="form-check-label">{{ column.label }}</label>
                </div>
            </q-card-section>
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Guardar"
                    no-caps
                    @click="saveColumnsTable"
                    color="primary"
                    class="q-mr-sm"
                />
                <q-btn
                    label="Cerrar"
                    no-caps
                    @click="columnsDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { darkMode } from "../../../../hook/appConfig";
import { useDataTable } from "../../../../composables/useDataTable";

defineOptions({
    name: "OltPonPorts",
});

const props = defineProps({
    object: Object,
});

const emits = defineEmits(["reload", "update-columns"]);

const showDialog = ref(false);
const columnsDialog = ref(false);
const filter = ref("");

const { saveColumns } = useDataTable();

const columns = ref([
    {
        name: "board",
        field: "board",
        label: "Ranura",
        align: "left",
        sortable: true,
    },
    {
        name: "pon_port",
        field: "pon_port",
        label: "Puerto",
        align: "left",
        sortable: true,
    },
    {
        name: "pon_type",
        field: "pon_type",
        label: "Tipo",
        align: "left",
        sortable: true,
    },
    {
        name: "admin_status",
        field: "admin_status",
        label: "Estado admin",
        align: "left",
        sortable: true,
    },
    {
        name: "operational_status",
        field: "operational_status",
        label: "Estado operacional",
        align: "left",
        sortable: true,
    },
    {
        name: "onus_count",
        field: "onus_count",
        label: "ONU totales",
        align: "left",
        sortable: true,
    },
    {
        name: "online_onus_count",
        field: "online_onus_count",
        label: "ONU en línea",
        align: "left",
        sortable: true,
    },
    {
        name: "average_signal",
        field: "average_signal",
        label: "Señal promedio",
        align: "left",
        sortable: true,
    },
    {
        name: "min_range",
        field: "min_range",
        label: "Rango mínimo",
        align: "left",
        sortable: true,
    },
    {
        name: "max_range",
        field: "max_range",
        label: "Alcance máximo",
        align: "left",
        sortable: true,
    },
    {
        name: "tx_power",
        field: "tx_power",
        label: "Potencia TX",
        align: "left",
        sortable: true,
    },
    {
        name: "description",
        field: "description",
        label: "Descripción",
        align: "left",
        sortable: true,
    },
    {
        name: "last_synced_at_humans",
        field: "last_synced_at_humans",
        label: "Ultima sincronización",
        align: "left",
        sortable: false,
    },
]);

let timer;

onMounted(() => {
    getColumnsTable();
    timer = setInterval(() => {
        emits("reload", false);
    }, 60000);
});

onUnmounted(() => {
    clearInterval(timer);
});

const getColumnsTable = async () => {
    const storedColumns = props.object.columns;
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
        emits("update-columns", getColumnsMap());
    }
};

const getColumnsMap = () => {
    return columns.value.map((col) => ({
        name: col.name,
        visible: col.visible,
    }));
};

const saveColumnsTable = async () => {
    try {
        const columnsData = getColumnsMap();
        await saveColumns(`olt-${props.object.name}`, columnsData);
        emits("update-columns", columnsData);
        columnsDialog.value = false;
    } catch (error) {
        console.log(error);
    }
};

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);
</script>
<style scoped>
.q-field__append.row > button.q-icon {
    padding: 0px;
}
</style>

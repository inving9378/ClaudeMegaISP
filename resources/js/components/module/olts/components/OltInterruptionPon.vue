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
    name: "OltInterruptionPon",
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
        name: "port",
        field: "port",
        label: "Puerto",
        align: "left",
        sortable: true,
    },
    {
        name: "cause",
        field: "cause",
        label: "Causa de interrupción",
        align: "left",
        sortable: true,
    },
    {
        name: "los_count",
        field: "los_count",
        label: "Total LOS",
        align: "left",
        sortable: true,
    },
    {
        name: "total_onus",
        field: "total_onus",
        label: "ONU totales",
        align: "left",
        sortable: true,
    },
    {
        name: "power_count",
        field: "power_count",
        label: "ONU con fallo de alimentación total",
        align: "left",
        sortable: true,
    },
    {
        name: "pon_description",
        field: "pon_description",
        label: "Descripción",
        align: "left",
        sortable: true,
    },
    {
        name: "latest_status_change",
        field: "latest_status_change",
        label: "Ultimo cambio de estado",
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

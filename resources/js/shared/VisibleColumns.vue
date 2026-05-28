<template>
    <q-btn
        color="info"
        outline
        padding="8px"
        icon="mdi-dots-horizontal"
        @click="showDialog = true"
        v-if="columns.length > 0"
    />
    <q-dialog v-model="showDialog" persistent @before-show="onBeforeShow">
        <q-card>
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Mostrar/Ocultar columnas</div>
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
                    v-for="(c, index) in defaultColumns"
                    :key="index"
                >
                    <input
                        class="form-check-input"
                        type="checkbox"
                        v-model="c.visible"
                    />
                    <label
                        class="form-check-label"
                        @click="c.visible = !c.visible"
                        >{{ c.label }}</label
                    >
                </div>
            </q-card-section>
            <q-separator />
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
                    @click="showDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref } from "vue";
import { useDataTable } from "../composables/useDataTable";

const { saveColumns } = useDataTable();

const showDialog = ref(false);

const props = defineProps({
    columns: {
        type: Array,
        default: [],
    },
    tableId: {
        type: String,
        required: true,
    },
});

const emits = defineEmits(["update-columns"]);

const defaultColumns = ref([]);

const onBeforeShow = () => {
    defaultColumns.value = props.columns.map((col) => ({
        ...col,
    }));
};

const getColumnsMap = () => {
    return defaultColumns.value.map((col) => ({
        name: col.name,
        visible: col.visible,
    }));
};

const saveColumnsTable = async () => {
    try {
        const columnsData = getColumnsMap();
        await saveColumns(props.tableId, columnsData);
        emits("update-columns", defaultColumns.value);
        showDialog.value = false;
    } catch (error) {
        console.log(error);
    }
};
</script>

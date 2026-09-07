<template>
    <q-dialog
        :model-value="modelValue"
        @update:model-value="(v) => emits('update:modelValue', v)"
        persistent
        maximized
    >
        <q-card class="import-kml-wizard">
            <q-toolbar class="bg-primary text-white">
                <q-toolbar-title>Importar KML/KMZ con previsualización</q-toolbar-title>
                <q-btn flat round dense icon="close" @click="close" />
            </q-toolbar>

            <q-card-section class="q-pa-md" style="max-width: 1100px; margin: 0 auto; width: 100%">
                <!-- Paso 1: selección de archivo -->
                <div v-if="!previewData" class="text-center q-pa-xl">
                    <q-icon name="mdi-file-upload-outline" size="64px" color="primary" />
                    <p class="q-mt-md">
                        Selecciona un archivo <strong>.kml</strong> o <strong>.kmz</strong> para
                        previsualizar los elementos antes de importarlos al proyecto.
                    </p>
                    <q-file
                        v-model="file"
                        accept=".kml,.kmz"
                        outlined
                        label="Archivo KML/KMZ"
                        class="q-mx-auto"
                        style="max-width: 420px"
                        :disable="loadingPreview"
                    >
                        <template v-slot:prepend>
                            <q-icon name="mdi-paperclip" />
                        </template>
                    </q-file>
                    <div v-if="errorMsg" class="text-negative q-mt-sm">{{ errorMsg }}</div>
                    <q-btn
                        color="primary"
                        class="q-mt-md"
                        label="Previsualizar"
                        no-caps
                        :loading="loadingPreview"
                        :disable="!file"
                        @click="onPreview"
                    />
                </div>

                <!-- Paso 2: previsualización + confirmación -->
                <div v-else>
                    <div class="row q-col-gutter-sm q-mb-md">
                        <div class="col-auto">
                            <q-chip color="grey-3" text-color="black" icon="mdi-format-list-bulleted">
                                Total: {{ previewData.resumen.total }}
                            </q-chip>
                        </div>
                        <div class="col-auto">
                            <q-chip color="positive" text-color="white" icon="mdi-plus-circle-outline">
                                Nuevos: {{ previewData.resumen.nuevos }}
                            </q-chip>
                        </div>
                        <div class="col-auto">
                            <q-chip color="warning" text-color="black" icon="mdi-map-marker-radius">
                                Cercanos: {{ previewData.resumen.cercanos }}
                            </q-chip>
                        </div>
                        <div class="col-auto">
                            <q-chip color="grey-6" text-color="white" icon="mdi-content-duplicate">
                                Duplicados: {{ previewData.resumen.duplicados }}
                            </q-chip>
                        </div>
                        <div class="col-auto">
                            <q-chip color="negative" text-color="white" icon="mdi-alert-circle-outline">
                                No soportados: {{ previewData.resumen.no_soportados }}
                            </q-chip>
                        </div>
                    </div>

                    <q-table
                        :rows="previewData.items"
                        :columns="columns"
                        row-key="__key"
                        dense
                        flat
                        bordered
                        :pagination="{ rowsPerPage: 15 }"
                    >
                        <template v-slot:body-cell-nombre="propRow">
                            <q-td :props="propRow">
                                <div>{{ propRow.row.nombre || "(sin nombre)" }}</div>
                                <div
                                    v-if="propRow.row.folder_path && propRow.row.folder_path.length"
                                    class="text-caption text-grey-7"
                                >
                                    {{ propRow.row.folder_path.join(" / ") }}
                                </div>
                            </q-td>
                        </template>

                        <template v-slot:body-cell-tipo="propRow">
                            <q-td :props="propRow">
                                <q-select
                                    v-if="propRow.row.geometria?.type === 'marker' && propRow.row.soportado"
                                    dense
                                    outlined
                                    emit-value
                                    map-options
                                    :model-value="propRow.row.tipo?.dialog ?? null"
                                    :options="tipoOptions"
                                    style="min-width: 200px"
                                    @update:model-value="(val) => onTipoChange(propRow.row, val)"
                                />
                                <q-chip v-else dense :icon="propRow.row.tipo?.icon">
                                    {{ propRow.row.tipo?.text ?? "—" }}
                                </q-chip>
                            </q-td>
                        </template>

                        <template v-slot:body-cell-confianza="propRow">
                            <q-td :props="propRow">
                                <q-badge :color="confianzaColor(propRow.row.tipo?.confianza)">
                                    {{ confianzaLabel(propRow.row.tipo?.confianza) }}
                                </q-badge>
                            </q-td>
                        </template>

                        <template v-slot:body-cell-duplicado="propRow">
                            <q-td :props="propRow">
                                <div v-if="propRow.row.duplicado">
                                    <q-badge :color="propRow.row.duplicado.exacto ? 'negative' : 'warning'">
                                        {{ propRow.row.duplicado.exacto ? "Exacto" : "Cercano" }}
                                    </q-badge>
                                    <div class="text-caption text-grey-7">
                                        {{ propRow.row.duplicado.nombre_existente }} ·
                                        {{ propRow.row.duplicado.distancia_metros }}m
                                    </div>
                                    <q-checkbox
                                        v-if="propRow.row.duplicado.exacto"
                                        dense
                                        :model-value="!!propRow.row.forzar_duplicado"
                                        label="Importar de todos modos"
                                        @update:model-value="(val) => (propRow.row.forzar_duplicado = val)"
                                    />
                                </div>
                                <span v-else class="text-grey-6">—</span>
                            </q-td>
                        </template>

                        <template v-slot:body-cell-omitir="propRow">
                            <q-td :props="propRow">
                                <q-checkbox
                                    :model-value="!!propRow.row.omitir"
                                    :disable="!propRow.row.soportado"
                                    @update:model-value="(val) => (propRow.row.omitir = val)"
                                />
                                <div v-if="!propRow.row.soportado" class="text-caption text-negative">
                                    Geometría no soportada
                                </div>
                            </q-td>
                        </template>
                    </q-table>

                    <div class="row justify-end q-gutter-sm q-mt-md">
                        <q-btn
                            flat
                            no-caps
                            color="grey-8"
                            label="Elegir otro archivo"
                            :disable="loadingCommit"
                            @click="resetToUpload"
                        />
                        <q-btn
                            color="primary"
                            no-caps
                            label="Confirmar importación"
                            :loading="loadingCommit"
                            @click="onConfirm"
                        />
                    </div>
                </div>
            </q-card-section>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { previsualizarKml, confirmarKml } from "../helper/request";
import { menuOptions, titleLayers } from "../helper/mapUtils";
import { message } from "../../../../helpers/toastMsg";

defineOptions({
    name: "ImportKmlWizard",
});

const props = defineProps({
    modelValue: Boolean,
    projectId: {
        type: [Number, String, null],
        default: null,
    },
});

const emits = defineEmits(["update:modelValue", "imported"]);

const file = ref(null);
const previewData = ref(null);
const errorMsg = ref(null);
const loadingPreview = ref(false);
const loadingCommit = ref(false);

// MR-25 (#961) — mismo vocabulario que ImportadorRedService::TIPOS_DETECTABLES, con "kmz"
// (genérico) agregado a mano porque no vive en menuOptions (no es un tipo creable a mano).
const DIALOGS_MARCADOR = ["service_box", "junction_box", "cupboard", "pole", "source", "site"];

const tipoOptions = computed(() => {
    const opciones = DIALOGS_MARCADOR.map((dialog) => {
        const opt = menuOptions.find((m) => m.dialog === dialog);
        return {
            value: dialog,
            label: opt?.text ?? titleLayers[dialog] ?? dialog,
            icon: opt?.icon,
            route: opt?.route,
        };
    });
    opciones.push({
        value: "kmz",
        label: `${titleLayers.kmz} (genérico)`,
        icon: "mdi-map-marker",
        route: "kmz",
    });
    return opciones;
});

const columns = [
    { name: "nombre", label: "Nombre", field: "nombre", align: "left" },
    { name: "tipo", label: "Tipo detectado", field: () => "", align: "left" },
    { name: "confianza", label: "Confianza", field: () => "", align: "center" },
    { name: "duplicado", label: "Duplicado", field: () => "", align: "left" },
    { name: "omitir", label: "Omitir", field: () => "", align: "center" },
];

watch(
    () => props.modelValue,
    (abierto) => {
        if (!abierto) {
            resetAll();
        }
    }
);

const onPreview = async () => {
    if (!file.value) return;
    loadingPreview.value = true;
    errorMsg.value = null;
    const data = await previsualizarKml(file.value);
    loadingPreview.value = false;

    if (!data || data.error) {
        errorMsg.value = data?.error ?? "No se pudo previsualizar el archivo";
        return;
    }

    data.items.forEach((item, idx) => (item.__key = idx));
    previewData.value = data;
};

const onTipoChange = (row, dialogValue) => {
    const opt = tipoOptions.value.find((o) => o.value === dialogValue);
    if (!opt) return;
    row.tipo = {
        dialog: opt.value,
        text: opt.label,
        icon: opt.icon,
        route: opt.route ?? opt.value,
        confianza: "manual",
    };
};

const confianzaColor = (confianza) => {
    if (confianza === "alta") return "positive";
    if (confianza === "baja") return "warning";
    if (confianza === "manual") return "primary";
    return "grey-6";
};

const confianzaLabel = (confianza) => {
    if (confianza === "alta") return "Alta";
    if (confianza === "baja") return "Baja";
    if (confianza === "manual") return "Manual";
    if (confianza === "geometria") return "Por geometría";
    return "—";
};

const onConfirm = async () => {
    loadingCommit.value = true;
    const reporte = await confirmarKml(previewData.value.items, props.projectId ?? null);
    loadingCommit.value = false;

    if (!reporte) {
        message("Error al confirmar la importación", "error");
        return;
    }

    message(
        `Importación completada: ${reporte.creados} creado(s), ${reporte.omitidos.length} omitido(s)`
    );
    emits("imported", reporte);
    close();
};

const resetToUpload = () => {
    previewData.value = null;
    file.value = null;
    errorMsg.value = null;
};

const resetAll = () => {
    resetToUpload();
    loadingPreview.value = false;
    loadingCommit.value = false;
};

const close = () => {
    emits("update:modelValue", false);
};
</script>

<style scoped>
.import-kml-wizard {
    width: 100%;
}
</style>

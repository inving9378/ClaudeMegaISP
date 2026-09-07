<template>
    <q-dialog v-model="dialog" @hide="onHide">
        <q-card style="width: 720px; max-width: 95vw">
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section>
                        <div class="text-h6">
                            Carta de empalme {{ nombre ? `— ${nombre}` : "" }}
                        </div>
                    </q-item-section>
                    <q-item-section avatar>
                        <q-btn icon="close" flat round dense @click="dialog = false" />
                    </q-item-section>
                </q-item>
            </q-card-section>

            <q-separator />

            <q-card-section>
                <div v-if="loading" class="text-caption text-grey">Cargando…</div>

                <template v-else-if="grupos && grupos.length">
                    <div v-for="grupo in grupos" :key="grupo.bandeja ?? 'sin-bandeja'" class="q-mb-md">
                        <div class="text-subtitle2 q-mb-xs">
                            Bandeja {{ grupo.bandeja ?? "Sin bandeja asignada" }}
                        </div>
                        <table class="carta-empalme__table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Hilo A</th>
                                    <th>Tipo</th>
                                    <th>Extremo B</th>
                                    <th>Pérdida (dB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(fila, idx) in grupo.filas" :key="idx">
                                    <td>
                                        <span
                                            class="carta-empalme__swatch"
                                            :style="{ backgroundColor: colorHex(fila.hilo_a?.color) }"
                                            :title="fila.hilo_a?.color || 'Sin color'"
                                        />
                                    </td>
                                    <td>{{ hiloLabel(fila.hilo_a) }}</td>
                                    <td>{{ TIPO_LABELS[fila.tipo] || fila.tipo }}</td>
                                    <td>{{ extremoLabel(fila.extremo_b) }}</td>
                                    <td>{{ fila.perdida_db ?? "—" }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <div v-else class="text-caption text-grey">
                    Sin empalmes registrados en este elemento — no hay carta que generar.
                </div>
            </q-card-section>

            <q-separator />
            <q-card-actions align="right">
                <q-btn no-caps flat color="primary" label="Cerrar" @click="dialog = false" />
                <q-btn
                    no-caps
                    color="primary"
                    icon="download"
                    label="Exportar PDF"
                    :disable="!grupos || !grupos.length"
                    @click="exportarPdf"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
// MR-19 Fase 2 (item roadmap #9990566). Consume el endpoint agrupado de la Fase 1 backend
// (item #9990565, GET /mapa-red/api/empalmes/carta) y el export PDF (DomPDF), sin duplicar la
// lógica de fetch/label del panel de uniones (EmpalmesPanel.vue) más de lo necesario.
import { ref, watch } from "vue";
import { getCartaEmpalme, cartaEmpalmePdfUrl } from "../../helper/empalmes-request";

defineOptions({
    name: "CartaEmpalmeDialog",
});

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    elementoContenedorType: {
        type: String,
        required: true,
    },
    elementoContenedorId: {
        type: [Number, String],
        required: true,
    },
    nombre: {
        type: String,
        default: null,
    },
});

const emits = defineEmits(["update:modelValue"]);

const TIPO_LABELS = {
    fusion: "Fusión",
    mecanico: "Mecánico",
    conectorizado: "Conectorizado",
};

// Paleta EIA/TIA-598 (App\Modules\Addons\MapaRed\Support\FiberColorScheme::eiaTia598) — los
// nombres vienen en español desde el catálogo, CSS no los reconoce como keyword, así que se
// traducen aquí para el swatch.
const COLOR_HEX = {
    Azul: "#1f4fd8",
    Naranja: "#ff7f11",
    Verde: "#1f9d55",
    Café: "#6b4226",
    Gris: "#8c8c8c",
    Blanco: "#f5f5f5",
    Rojo: "#e0201b",
    Negro: "#1a1a1a",
    Amarillo: "#f2c811",
    Violeta: "#7b2fbe",
    Rosa: "#ff6fae",
    Aqua: "#00b8d9",
};

const colorHex = (nombreColor) => COLOR_HEX[nombreColor] || "#cccccc";

const hiloLabel = (hilo) =>
    hilo
        ? `Cable ${hilo.cable_id ?? "—"} · buffer ${hilo.buffer ?? "—"} (${hilo.buffer_color ?? "—"}) · hilo ${hilo.numero ?? "—"} (${hilo.color ?? "—"})`
        : "—";

const extremoLabel = (extremo) => {
    if (!extremo) return "—";
    if (extremo.cable_id !== undefined && extremo.cable_id !== null) {
        return `Cable ${extremo.cable_id} · buffer ${extremo.buffer ?? "—"} · hilo ${extremo.numero ?? "—"}`;
    }
    return `Puerto ${extremo.numero ?? extremo.id ?? "—"}`;
};

const dialog = ref(false);
const loading = ref(false);
const grupos = ref(null);

const cargar = async () => {
    loading.value = true;
    grupos.value = await getCartaEmpalme(props.elementoContenedorType, props.elementoContenedorId);
    loading.value = false;
};

watch(
    () => props.modelValue,
    (n) => {
        dialog.value = n;
        if (n) cargar();
    }
);

const onHide = () => {
    emits("update:modelValue", false);
};

const exportarPdf = () => {
    window.open(cartaEmpalmePdfUrl(props.elementoContenedorType, props.elementoContenedorId), "_blank");
};
</script>

<style scoped>
.carta-empalme__table {
    width: 100%;
    border-collapse: collapse;
}

.carta-empalme__table th,
.carta-empalme__table td {
    text-align: left;
    padding: 3px 6px;
    border-bottom: 1px solid rgba(128, 128, 128, 0.2);
    font-size: 12px;
}

.carta-empalme__swatch {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 3px;
    border: 1px solid rgba(0, 0, 0, 0.25);
}
</style>

<template>
    <section v-if="puedeVer && estados.length" class="ecc-card" :class="{ 'ecc-dark': darkMode }">
        <div class="ecc-head"><i class="mdi mdi-file-chart-outline"></i> Estados de cuenta</div>
        <table class="ecc-tabla">
            <thead>
                <tr><th>Fecha</th><th>Tipo</th><th>Estado del cliente</th><th class="r">Adeudo</th><th>PDF</th></tr>
            </thead>
            <tbody>
                <tr v-for="e in estados" :key="e.id">
                    <td>{{ e.fecha }}</td>
                    <td>{{ e.tipo === "final" ? "Final (baja)" : "Mensual" }}</td>
                    <td>{{ e.estado_cliente || "—" }}</td>
                    <td class="r">{{ dinero(e.adeudo) }}</td>
                    <td>
                        <a v-if="e.tiene_pdf" :href="`/cliente/billing/estados-cuenta/${clientId}/${e.id}/pdf`" target="_blank" rel="noopener">Ver PDF</a>
                        <span v-else class="ecc-error" :title="e.error || ''">No se generó</span>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="ecc-nota">Documento informativo sin valor fiscal. Generarlo no lo envía al cliente.</p>
    </section>
</template>

<script setup>
/**
 * FR-02 (#536): estados de cuenta generados del cliente (el final al pasar a Cancelado). Solo
 * lectura; no aparece si no hay ninguno. Permiso: facturacion.ver.
 */
import { ref, watch } from "vue";
import { darkMode } from "../../../../hook/appConfig";

const props = defineProps({
    clientId: { type: [String, Number], required: true },
    puedeVer: { type: Boolean, default: false },
});

const estados = ref([]);
let token = 0;

const dinero = (n) => "$" + Number(n || 0).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const cargar = async () => {
    const mio = ++token;
    estados.value = [];
    if (!props.puedeVer || !props.clientId) {
        return;
    }
    try {
        const { data } = await axios.get(`/cliente/billing/estados-cuenta/${props.clientId}`);
        if (mio === token) {
            estados.value = data.estados_cuenta || [];
        }
    } catch (e) {
        estados.value = [];
    }
};

watch(() => [props.clientId, props.puedeVer], cargar, { immediate: true });
</script>

<style scoped>
.ecc-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 0 0 14px; overflow-x: auto; }
.ecc-head { padding: 10px 16px; font-weight: 700; font-size: 0.92rem; background: #EEF2FF; color: #3730A3;
    border-bottom: 1px solid #f1f3f5; border-radius: 12px 12px 0 0; }
.ecc-tabla { width: 100%; border-collapse: collapse; font-size: 13px; }
.ecc-tabla th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; color: #6b7280; text-align: left; padding: 8px 16px; }
.ecc-tabla td { padding: 7px 16px; border-top: 1px solid #f1f3f5; }
.ecc-tabla .r { text-align: right; }
.ecc-error { color: #b42318; }
.ecc-nota { font-size: 12px; color: #6b7280; margin: 0; padding: 8px 16px 12px; }
.ecc-dark.ecc-card { background: #1d1d21; border-color: #2c2c34; }
.ecc-dark .ecc-head { background: #26262c; color: #e5e7eb; border-color: #2c2c34; }
.ecc-dark .ecc-tabla th, .ecc-dark .ecc-nota { color: #9ca3af; }
.ecc-dark .ecc-tabla td { border-color: #2c2c34; color: #e5e7eb; }
.ecc-dark .ecc-error { color: #fca5a5; }
</style>

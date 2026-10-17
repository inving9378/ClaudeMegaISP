<template>
    <section v-if="cargado" class="ctc-card" :class="{ 'ctc-dark': darkMode }">
        <div class="ctc-head">
            <span><i class="mdi mdi-file-sign"></i> Contrato</span>
            <span v-if="d.estado" class="ctc-badge" :class="`ctc-badge--${d.estado}`">{{ ESTADOS[d.estado] }}{{ d.estado === "renovado" ? ` · plazo ${d.plazo_numero}` : "" }}</span>
        </div>
        <div class="ctc-body">
            <dl class="ctc-grid">
                <div><dt>Plazo</dt><dd>{{ d.plazo }}</dd></div>
                <div><dt>Inicio</dt><dd>{{ d.inicio || "—" }}<small v-if="d.inicio_por_alta" class="ctc-nota"> (fecha de alta: no hay fecha de activación válida)</small></dd></div>
                <div><dt>Fin {{ d.estado === "renovado" ? "del plazo actual" : "" }}</dt><dd>{{ d.fin || "—" }}</dd></div>
                <div v-if="d.estado === 'cumplido' && !d.autorrenovacion">
                    <dt>Facturas sin pago tras cumplir</dt><dd>{{ d.facturas_sin_pago }} de {{ d.facturas_max_sin_pago }}</dd>
                </div>
            </dl>
            <div class="ctc-auto">
                <div class="form-check form-switch m-0">
                    <input :id="`ctc-auto-${clientId}`" v-model="d.autorrenovacion" class="form-check-input" type="checkbox"
                           :disabled="!d.tiene_paquete || !puedeEditar || guardando" @change="guardar" />
                    <label class="form-check-label" :for="`ctc-auto-${clientId}`">Autorrenovación del contrato</label>
                </div>
                <small class="ctc-nota">
                    {{ d.tiene_paquete
                        ? "Al cumplirse el plazo se abre otro igual. Es la «Renovación automática» de su paquete."
                        : "Este cliente no tiene paquete; la autorrenovación vive en el paquete." }}
                </small>
            </div>
            <p v-if="!d.capa_encendida" class="ctc-aviso">
                Las reglas de cobro por contrato aún están apagadas: hoy no cambian los cargos.
            </p>
        </div>
    </section>
</template>

<script setup>
/**
 * FR-07 (#541): contrato del cliente — plazo, inicio, fin, estado (vigente / cumplido / renovado) y
 * su autorrenovación. GET /cliente/contrato/{id}; cambiar la autorrenovación:
 * POST /cliente/contrato/{id}/autorrenovacion (queda en el historial).
 */
import { reactive, ref, watch } from "vue";
import { darkMode } from "../../../../hook/appConfig";

const props = defineProps({
    clientId: { type: [String, Number], required: true },
    puedeEditar: { type: Boolean, default: false },
});

const ESTADOS = { vigente: "Vigente", cumplido: "Cumplido", renovado: "Renovado" };
const d = reactive({});
const cargado = ref(false);
const guardando = ref(false);
let token = 0;

const cargar = async () => {
    const mio = ++token;
    cargado.value = false;
    if (!props.clientId) {
        return;
    }
    try {
        const { data } = await axios.get(`/cliente/contrato/${props.clientId}`);
        if (mio === token) {
            Object.keys(d).forEach((k) => delete d[k]);
            Object.assign(d, data);
            cargado.value = true;
        }
    } catch (e) {
        cargado.value = false;
    }
};

const guardar = async () => {
    guardando.value = true;
    try {
        const { data } = await axios.post(`/cliente/contrato/${props.clientId}/autorrenovacion`, { autorrenovacion: d.autorrenovacion });
        toastr.success(data.message, "Contrato");
        await cargar();
    } catch (e) {
        d.autorrenovacion = !d.autorrenovacion;
        toastr.error(e.response?.data?.error || "No se pudo cambiar la autorrenovación.", "Contrato");
    } finally {
        guardando.value = false;
    }
};

watch(() => props.clientId, cargar, { immediate: true });
</script>

<style scoped>
.ctc-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 0 0 14px; }
.ctc-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 16px; font-weight: 700;
    font-size: 0.92rem; background: #F0FDF4; color: #166534; border-bottom: 1px solid #f1f3f5; border-radius: 12px 12px 0 0; }
.ctc-badge { font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 10px; background: #F1F5F9; color: #475569; }
.ctc-badge--vigente { background: #DCFCE7; color: #166534; }
.ctc-badge--renovado { background: #DBEAFE; color: #1E40AF; }
.ctc-badge--cumplido { background: #FFEDD5; color: #9A3412; }
.ctc-body { padding: 12px 16px; }
.ctc-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px 18px; margin: 0 0 10px; }
@media (max-width: 991px) { .ctc-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 575px) { .ctc-grid { grid-template-columns: 1fr; } }
.ctc-grid dt { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.3px; }
.ctc-grid dd { margin: 0; font-size: 13px; }
.ctc-auto { display: flex; flex-direction: column; gap: 2px; }
.ctc-nota { font-size: 12px; color: #6b7280; }
.ctc-aviso { margin: 10px 0 0; font-size: 12px; color: #92400E; background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 6px 10px; }
.ctc-dark.ctc-card { background: #1d1d21; border-color: #2c2c34; }
.ctc-dark .ctc-head { background: #26262c; color: #e5e7eb; border-color: #2c2c34; }
.ctc-dark .ctc-grid dt, .ctc-dark .ctc-nota { color: #9ca3af; }
.ctc-dark .ctc-grid dd { color: #e5e7eb; }
.ctc-dark .ctc-aviso { background: #3a2412; border-color: #78350f; color: #fdba74; }
.ctc-dark .ctc-badge--vigente { background: #14321f; color: #86efac; }
.ctc-dark .ctc-badge--renovado { background: #172554; color: #93c5fd; }
.ctc-dark .ctc-badge--cumplido { background: #3a2412; color: #fdba74; }
</style>

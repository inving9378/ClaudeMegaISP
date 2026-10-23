<template>
    <section v-if="cargado" class="epg-card" :class="{ 'epg-dark': darkMode }">
        <div class="epg-head">Envío prefactura</div>
        <div class="epg-body">
            <label class="epg-label" for="epg-dias">Días antes del día de facturación (valor general)</label>
            <div class="epg-fila">
                <input id="epg-dias" v-model.number="dias" type="number" min="0" :max="MAX" class="form-control form-control-sm epg-input"
                       :class="{ 'is-invalid': error }" @input="error = ''" />
                <button type="button" class="btn btn-primary btn-sm" :disabled="guardando" @click="guardar">
                    {{ guardando ? "Guardando…" : "Guardar" }}
                </button>
            </div>
            <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
            <p class="epg-nota">
                Aplica a todos los clientes que no tienen su propio valor en su Configuración de facturación<span
                    v-if="propios"> ({{ propios }} {{ propios === 1 ? "cliente tiene" : "clientes tienen" }} uno propio)</span>.
                <b v-if="!encendido">La proforma anticipada sigue apagada: hoy no cambia cuándo se generan.</b>
                Se enciende en Configuración › Envíos automáticos.
            </p>
        </div>
    </section>
</template>

<script setup>
/**
 * FR-05 (#539): valor general de «Envío prefactura» (5 por defecto). Sustituye a los «Días de
 * antelación» de Facturas proforma para la proforma anticipada. GET/POST
 * /configuracion/finance-notification/envio-prefactura.
 */
import { onMounted, ref } from "vue";
import { darkMode } from "../../../../../../hook/appConfig";

const MAX = 60;
const dias = ref(5);
const propios = ref(0);
const encendido = ref(false);
const cargado = ref(false);
const guardando = ref(false);
const error = ref("");

onMounted(async () => {
    try {
        const { data } = await axios.get("/configuracion/finance-notification/envio-prefactura");
        dias.value = data.dias;
        propios.value = data.clientes_con_valor_propio || 0;
        encendido.value = !!data.interruptor_encendido;
        cargado.value = true;
    } catch (e) {
        cargado.value = false;
    }
});

const guardar = async () => {
    if (!Number.isInteger(dias.value) || dias.value < 0 || dias.value > MAX) {
        error.value = `Escribe un número de días entre 0 y ${MAX}.`;
        return;
    }
    guardando.value = true;
    try {
        await axios.post("/configuracion/finance-notification/envio-prefactura", { dias: dias.value });
        toastr.success("Envío prefactura guardado.", "Facturas proforma");
    } catch (e) {
        error.value = e.response?.data?.errors?.dias?.[0] || e.response?.data?.error || "No se pudo guardar.";
    } finally {
        guardando.value = false;
    }
};
</script>

<style scoped>
.epg-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 12px 0; }
.epg-head { padding: 10px 16px; font-weight: 700; font-size: 0.92rem; background: #EEF2FF; color: #3730A3;
    border-bottom: 1px solid #f1f3f5; border-radius: 12px 12px 0 0; }
.epg-body { padding: 12px 16px; }
.epg-label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
.epg-fila { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.epg-input { width: 110px; }
.epg-nota { font-size: 12px; color: #6b7280; margin: 8px 0 0; }
.epg-dark.epg-card { background: #1d1d21; border-color: #2c2c34; }
.epg-dark .epg-head { background: #26262c; color: #e5e7eb; border-color: #2c2c34; }
.epg-dark .epg-label { color: #cbd5e1; }
.epg-dark .epg-nota { color: #9ca3af; }
</style>

<template>
    <div class="tk-wrap" :class="{ 'tk-dark': darkMode }">
        <section class="tk-card">
            <div class="tk-head tk-fila">
                Ticket de caja {{ folio }}
                <a :href="`/cliente/editar/${clienteId}`" class="btn btn-outline-secondary btn-sm ms-auto tk-btn-head">Ir a la ficha del cliente</a>
            </div>
            <div class="tk-body">
                <p class="tk-nota">
                    El ticket es solo para imprimir en caja: ya no se envía solo. Si el cliente lo pide, se le puede reenviar por correo.
                </p>
                <div class="tk-fila tk-acciones">
                    <button type="button" class="btn btn-primary btn-sm" @click="imprimir">Imprimir</button>
                    <a :href="urlPdf" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Abrir el PDF</a>
                    <button v-if="puedeEnviar" type="button" class="btn btn-outline-success btn-sm" :disabled="!correo" @click="dialogo = true">
                        Enviar por correo…
                    </button>
                    <span v-if="puedeEnviar && !correo" class="tk-nota">El cliente no tiene correo en su ficha.</span>
                </div>
                <iframe ref="marco" :src="urlPdf" class="tk-marco" :title="`Ticket ${folio}`"></iframe>
            </div>
        </section>

        <div v-if="dialogo" class="tk-velo" @click.self="cerrar">
            <div class="tk-dialogo" role="dialog" aria-modal="true">
                <div class="tk-head">Enviar ticket por correo</div>
                <div class="tk-body">
                    <p>Se envía el ticket <b>{{ folio }}</b> a <b>{{ correo }}</b>.</p>
                    <p class="tk-nota">Es un envío a mano: no depende de la pausa de los envíos automáticos. Queda en el historial del cliente.</p>
                </div>
                <div v-if="error" class="tk-error">{{ error }}</div>
                <div class="tk-pie">
                    <button type="button" class="btn btn-light btn-sm" :disabled="enviando" @click="cerrar">Volver</button>
                    <button type="button" class="btn btn-success btn-sm" :disabled="enviando" @click="enviar">{{ enviando ? "Enviando…" : "Enviar" }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * #580: el ticket de caja (TKT-) de un pago, para imprimir y, si el cliente lo pide, reenviar por
 * correo a mano. Se abre desde el ícono del ticket en los pagos de la ficha.
 * GET /cliente/billing/payment/ticket/{id}/pdf · POST …/ticket/{id}/enviar.
 */
import { computed, ref } from "vue";
import { darkMode } from "../../../../../hook/appConfig";

const props = defineProps({
    pagoId: { type: Number, required: true },
    clienteId: { type: Number, required: true },
    folio: { type: String, required: true },
    correo: { type: String, default: "" },
    puedeEnviar: { type: Boolean, default: false },
});

const marco = ref(null);
const dialogo = ref(false);
const enviando = ref(false);
const error = ref("");
const urlPdf = computed(() => `/cliente/billing/payment/ticket/${props.pagoId}/pdf`);

const imprimir = () => {
    try {
        marco.value.contentWindow.focus();
        marco.value.contentWindow.print();
    } catch {
        window.open(urlPdf.value, "_blank", "noopener");
    }
};

const cerrar = () => {
    if (!enviando.value) {
        dialogo.value = false;
        error.value = "";
    }
};

const enviar = async () => {
    enviando.value = true;
    error.value = "";
    try {
        const { data } = await axios.post(`/cliente/billing/payment/ticket/${props.pagoId}/enviar`);
        toastr.success(data.message, "Ticket de caja");
        dialogo.value = false;
    } catch (e) {
        error.value = e.response?.data?.message || "No se pudo enviar el ticket.";
    } finally {
        enviando.value = false;
    }
};
</script>

<style scoped>
.tk-wrap { max-width: 760px; }
.tk-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 12px 0; }
.tk-head { padding: 10px 16px; font-weight: 700; font-size: 0.92rem; background: #EEF2FF; color: #3730A3;
    border-bottom: 1px solid #f1f3f5; border-radius: 12px 12px 0 0; }
.tk-body { padding: 12px 16px; }
.tk-fila { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.tk-btn-head { font-weight: 500; }
.tk-acciones { margin: 10px 0; }
.tk-nota { font-size: 12px; color: #6b7280; margin: 0; }
.tk-marco { width: 100%; height: 640px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f8fafc; }
.tk-error { color: #B91C1C; font-size: 12.5px; padding: 0 16px 8px; }
.tk-velo { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 1050; display: flex;
    align-items: center; justify-content: center; padding: 16px; }
.tk-dialogo { background: #fff; border-radius: 12px; width: 100%; max-width: 460px; }
.tk-pie { display: flex; justify-content: flex-end; gap: 8px; padding: 10px 16px; border-top: 1px solid #f1f3f5; }
.tk-dark .tk-card, .tk-dark .tk-dialogo { background: #1d1d21; border-color: #2c2c34; color: #e5e7eb; }
.tk-dark .tk-head { background: #26262c; color: #e5e7eb; border-color: #2c2c34; }
.tk-dark .tk-nota { color: #9ca3af; }
.tk-dark .tk-marco { border-color: #2c2c34; }
.tk-dark .tk-pie { border-color: #2c2c34; }
</style>

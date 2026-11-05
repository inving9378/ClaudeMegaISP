<template>
    <div class="modal d-block ro-fondo" tabindex="-1" @click.self="cerrar">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-calendar-alt me-2"></i>Reprogramar orden #{{ orden.id }}</h5>
                    <button type="button" class="btn-close" :disabled="guardando" @click="cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="small mb-3">
                        <strong>{{ orden.tipo }}</strong><span v-if="orden.cliente"> · {{ orden.cliente }}</span>
                        <div v-if="orden.fecha" :class="atrasada ? 'text-danger fw-semibold' : 'text-muted'">
                            {{ atrasada ? "Atrasada: era para el" : "Programada para el" }} {{ fechaLarga(orden.fecha) }}
                        </div>
                    </div>

                    <label class="form-label" for="ro_fecha">Nuevo día</label>
                    <FechaHoraOrden v-model="nueva" input-id="ro_fecha" :desde="hoy" />
                    <div class="form-text mb-3">De hoy en adelante. Si no eliges hora se queda la que tenía.</div>

                    <label class="form-label" for="ro_motivo">¿Por qué se mueve?</label>
                    <div class="ro-rapidos">
                        <button v-for="m in rapidos" :key="m" type="button" class="ro-chip" @click="usarMotivo(m)">{{ m }}</button>
                    </div>
                    <textarea id="ro_motivo" v-model="motivo" class="form-control" rows="3" maxlength="500"
                              placeholder="Ej. El cliente pidió que se revise hasta el día 15."></textarea>
                    <div class="form-text">Queda en el historial de la orden, con tu nombre.</div>

                    <div v-if="error" class="alert alert-danger py-2 small mt-3 mb-0">{{ error }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" :disabled="guardando" @click="cerrar">Cancelar</button>
                    <button type="button" class="btn btn-primary" :disabled="guardando || !listo" @click="guardar">
                        <span v-if="guardando" class="spinner-border spinner-border-sm me-1"></span>Mover orden
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import FechaHoraOrden from "./FechaHoraOrden.vue";

const MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
const DIAS = ["domingo", "lunes", "martes", "miércoles", "jueves", "viernes", "sábado"];
const pad = (n) => String(n).padStart(2, "0");

/**
 * Mover una orden de Talento a otro día con su motivo (item #1158). Lo atrasado no se queda atrás:
 * o se hace o se reprograma diciendo por qué. Lo usan «Mi día» (url del portal), Talento › Órdenes y
 * la agenda (url de administración).
 */
export default {
    name: "ReprogramarOrden",
    components: { FechaHoraOrden },
    props: {
        // { id, tipo, cliente, fecha: "YYYY-MM-DD", atrasada }
        orden: { type: Object, required: true },
        url: { type: String, required: true },
        // Día ya propuesto (p. ej. al arrastrar la orden en la agenda): "YYYY-MM-DD" o "YYYY-MM-DD HH:mm".
        fechaInicial: { type: String, default: null },
    },
    emits: ["cerrar", "reprogramada"],
    data() {
        const d = new Date();
        return {
            hoy: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
            nueva: this.fechaInicial,
            motivo: "",
            guardando: false,
            error: "",
            rapidos: ["Me la cancelaron", "El cliente no estaba", "El cliente pidió otro día", "Falta material o equipo", "No alcanzó el tiempo"],
        };
    },
    computed: {
        atrasada() {
            return this.orden.atrasada || (this.orden.fecha && this.orden.fecha < this.hoy);
        },
        listo() {
            return this.nueva && this.nueva.slice(0, 10) >= this.hoy && this.motivo.trim().length >= 5;
        },
    },
    methods: {
        cerrar() {
            if (!this.guardando) this.$emit("cerrar");
        },
        usarMotivo(m) {
            this.motivo = this.motivo.trim() ? `${this.motivo.trim()}. ${m}` : m;
        },
        fechaLarga(f) {
            const [y, m, d] = f.split("-").map(Number);
            const dt = new Date(y, m - 1, d);
            return `${DIAS[dt.getDay()]} ${d} de ${MESES[m - 1]}`;
        },
        async guardar() {
            this.guardando = true;
            this.error = "";
            try {
                const { data } = await axios.post(this.url, { fecha: this.nueva, motivo: this.motivo.trim() });
                this.$emit("reprogramada", data);
            } catch (e) {
                const d = e.response?.data;
                this.error = d?.error || d?.message || "No se pudo mover la orden.";
            } finally {
                this.guardando = false;
            }
        },
    },
};
</script>

<style scoped>
.ro-fondo {
    background: rgba(0, 0, 0, 0.5);
    z-index: 10020;
}
.ro-rapidos {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 6px;
}
.ro-chip {
    border: 1px solid var(--bs-border-color, #dee2e6);
    background: transparent;
    color: inherit;
    border-radius: 14px;
    padding: 2px 10px;
    font-size: 12px;
}
.ro-chip:hover {
    background: rgba(26, 115, 232, 0.08);
}
</style>

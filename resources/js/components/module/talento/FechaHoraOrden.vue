<template>
    <div class="fho">
        <div class="fho-fecha">
            <input :id="inputId" type="text" class="form-control" readonly
                   placeholder="Elige el día" :value="fechaTexto" />
            <i class="fa fa-calendar-alt fho-icono"></i>
            <!-- Siempre como menú (breakpoint 0) y por encima de los modales de Bootstrap del sistema,
                 que llevan z-index 9999: el menú de Quasar va en 6000 y quedaba detrás. -->
            <q-popup-proxy ref="calendario" :breakpoint="0" style="z-index: 10050"
                           transition-show="scale" transition-hide="scale">
                <q-date v-model="fecha" mask="YYYY-MM-DD" :locale="calendarioEs" today-btn :options="diaPermitido"
                        :dark="darkMode" class="fho-calendario" @update:model-value="$refs.calendario.hide()" />
            </q-popup-proxy>
        </div>
        <select class="form-select" v-model="hora" aria-label="Hora" :disabled="!fecha">
            <option value="">Hora</option>
            <option v-for="h in horas" :key="h.valor" :value="h.valor">{{ h.texto }}</option>
        </select>
        <select class="form-select" v-model="minuto" aria-label="Minutos" :disabled="hora === ''">
            <option v-for="m in minutos" :key="m" :value="m">:{{ m }}</option>
        </select>
    </div>
</template>

<script>
import { darkMode } from "../../../hook/appConfig.js";

const CALENDARIO_ES = {
    days: ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],
    daysShort: ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"],
    months: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
    monthsShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"],
    firstDayOfWeek: 1,
    format24h: false,
    pluralDay: "días",
};
// Hora en el formato con que se lee aquí (8 a.m., 1 p.m.); el valor es la hora de 0 a 23.
const HORAS = Array.from({ length: 24 }, (_, h) => ({
    valor: String(h),
    texto: `${h % 12 === 0 ? 12 : h % 12} ${h < 12 ? "a.m." : "p.m."}`,
}));
const MINUTOS = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, "0"));

/**
 * Día y hora de una orden de trabajo de Talento (2026-10-27): calendario en español y hora en dos
 * listas, sin segundos. Lo usan «Asignar trabajo» (fichas de cliente y prospecto) y «Nueva orden»
 * (Talento › Órdenes). El valor es null, "YYYY-MM-DD" o "YYYY-MM-DD HH:mm".
 */
export default {
    name: "FechaHoraOrden",
    props: {
        modelValue: { type: String, default: null },
        inputId: { type: String, default: "fho_fecha" },
        // "YYYY-MM-DD": primer día que se puede elegir (reprogramar no deja ir hacia atrás). Sin él, cualquiera.
        desde: { type: String, default: null },
    },
    emits: ["update:modelValue"],
    setup() {
        return { darkMode };
    },
    data() {
        return {
            fecha: "",
            hora: "",
            minuto: "00",
            calendarioEs: CALENDARIO_ES,
            horas: HORAS,
            minutos: MINUTOS,
        };
    },
    computed: {
        diaPermitido() {
            // q-date pasa "YYYY/MM/DD".
            return this.desde ? (d) => d.replace(/\//g, "-") >= this.desde : undefined;
        },
        valor() {
            if (!this.fecha) return null;
            if (this.hora === "") return this.fecha;
            return `${this.fecha} ${this.hora.padStart(2, "0")}:${this.minuto}`;
        },
        fechaTexto() {
            if (!this.fecha) return "";
            return new Date(this.fecha + "T12:00:00").toLocaleDateString("es-MX", {
                weekday: "long", day: "numeric", month: "long", year: "numeric",
            });
        },
    },
    watch: {
        // Quien abre el formulario de nuevo lo deja en null: aquí se vacía también.
        modelValue: {
            immediate: true,
            handler(v) {
                if ((v || null) === this.valor) return;
                const m = /^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(v || "");
                this.fecha = m ? m[1] : "";
                this.hora = m && m[2] ? String(parseInt(m[2], 10)) : "";
                this.minuto = m && m[3] ? m[3] : "00";
            },
        },
        // Al quitar el día en el calendario (clic sobre el día elegido) se va también la hora.
        fecha(v) {
            if (!v) {
                this.hora = "";
                this.minuto = "00";
            }
        },
        valor(v) {
            if (v !== (this.modelValue || null)) this.$emit("update:modelValue", v);
        },
    },
};
</script>

<style scoped>
.fho {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 112px 80px;
    gap: 8px;
}
.fho-fecha {
    position: relative;
    cursor: pointer;
}
.fho-fecha input {
    cursor: pointer;
    padding-right: 34px;
    background-color: var(--bs-body-bg, #fff);
}
.fho-icono {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0.6;
    pointer-events: none;
}
/* El calendario de Quasar usa .row por dentro y el Bootstrap global les pone ancho completo y relleno
   (ver PortalClienteApariencia.vue): se neutraliza solo dentro del calendario. */
.fho-calendario :deep(.row) {
    --bs-gutter-x: 0;
    --bs-gutter-y: 0;
}
.fho-calendario :deep(.row > *) {
    width: auto;
    max-width: none;
}
@media (max-width: 420px) {
    .fho {
        grid-template-columns: 1fr 1fr;
    }
    .fho-fecha {
        grid-column: 1 / -1;
    }
}
</style>

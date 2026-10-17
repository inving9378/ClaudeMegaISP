<template>
    <section v-if="cargado" class="hcc-card" :class="{ 'hcc-dark': darkMode }">
        <div class="hcc-head">
            <span><i class="mdi mdi-history"></i> Historial de estado y contrato</span>
            <button v-if="cambios.length > VISIBLES" type="button" class="btn btn-link btn-sm p-0" @click="todos = !todos">
                {{ todos ? "Ver menos" : `Ver los ${cambios.length}` }}
            </button>
        </div>
        <p v-if="!cambios.length" class="hcc-vacio">
            Todavía no hay cambios registrados. Desde esta versión se registra cada cambio de estado y de contrato.
        </p>
        <div v-else class="hcc-scroll">
            <table class="hcc-tabla">
                <thead>
                    <tr><th>Fecha</th><th>Qué cambió</th><th>Antes → Ahora</th><th>Origen</th><th>Quién</th></tr>
                </thead>
                <tbody>
                    <tr v-for="c in visibles" :key="c.id">
                        <td class="hcc-nowrap">{{ c.fecha }}</td>
                        <td>
                            {{ CAMPOS[c.campo] || c.campo }}
                            <div v-if="c.motivo" class="hcc-motivo">{{ c.motivo }}</div>
                        </td>
                        <td>{{ c.antes || "—" }} → <b>{{ c.despues || "—" }}</b></td>
                        <td><span class="hcc-origen" :class="`hcc-origen--${c.origen}`">{{ ORIGENES[c.origen] || c.origen }}</span></td>
                        <td>{{ c.quien || (c.origen === "usuario" ? "—" : "Sistema") }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<script setup>
/**
 * FR-04 (#538): historial de cambios de estado y de contrato del cliente (solo lectura).
 * GET /cliente/historial-cambios/{id}. El motivo (p. ej. «Pago #123») se ve al pasar el cursor.
 */
import { computed, ref, watch } from "vue";
import { darkMode } from "../../../../hook/appConfig";

const props = defineProps({
    clientId: { type: [String, Number], required: true },
});

const VISIBLES = 8;
const CAMPOS = {
    estado: "Estado",
    plazo: "Plazo de contrato",
    plazo_especial_meses: "Meses del plazo Especial",
    activation_date: "Fecha de activación",
    autorrenovacion: "Autorrenovación",
    renovacion: "Renovación del contrato",
};
const ORIGENES = { usuario: "Usuario", sistema: "Sistema", pago: "Pago" };

const cambios = ref([]);
const cargado = ref(false);
const todos = ref(false);
let token = 0;

const visibles = computed(() => (todos.value ? cambios.value : cambios.value.slice(0, VISIBLES)));

const cargar = async () => {
    const mio = ++token;
    cargado.value = false;
    cambios.value = [];
    todos.value = false;
    if (!props.clientId) {
        return;
    }
    try {
        const { data } = await axios.get(`/cliente/historial-cambios/${props.clientId}`);
        if (mio === token) {
            cambios.value = data.cambios || [];
            cargado.value = true;
        }
    } catch (e) {
        cargado.value = false;
    }
};

watch(() => props.clientId, cargar, { immediate: true });
</script>

<style scoped>
.hcc-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 0 0 14px; }
.hcc-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 16px;
    font-weight: 700; font-size: 0.92rem; background: #F1F5F9; color: #334155; border-bottom: 1px solid #f1f3f5;
    border-radius: 12px 12px 0 0; }
.hcc-vacio { margin: 0; padding: 12px 16px; font-size: 13px; color: #475569; }
.hcc-scroll { overflow-x: auto; }
.hcc-tabla { width: 100%; border-collapse: collapse; font-size: 13px; }
.hcc-tabla th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; color: #6b7280; text-align: left; padding: 8px 16px; }
.hcc-tabla td { padding: 7px 16px; border-top: 1px solid #f1f3f5; }
.hcc-nowrap { white-space: nowrap; }
.hcc-motivo { font-size: 11.5px; color: #6b7280; }
.hcc-origen { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; background: #F1F5F9; color: #475569; }
.hcc-origen--usuario { background: #EEF2FF; color: #3730A3; }
.hcc-origen--pago { background: #E9F5EF; color: #14724A; }
.hcc-dark.hcc-card { background: #1d1d21; border-color: #2c2c34; }
.hcc-dark .hcc-head { background: #26262c; color: #e5e7eb; border-color: #2c2c34; }
.hcc-dark .hcc-tabla th, .hcc-dark .hcc-vacio, .hcc-dark .hcc-motivo { color: #9ca3af; }
.hcc-dark .hcc-tabla td { border-color: #2c2c34; color: #e5e7eb; }
.hcc-dark .hcc-origen { background: #2c2c34; color: #cbd5e1; }
.hcc-dark .hcc-origen--usuario { background: #2a2f55; color: #c7d2fe; }
.hcc-dark .hcc-origen--pago { background: #14321f; color: #86efac; }
</style>

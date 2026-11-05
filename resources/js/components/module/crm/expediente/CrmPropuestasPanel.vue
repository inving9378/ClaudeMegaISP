<template>
    <div class="crm-prop">
        <div v-if="cargando" class="text-grey-7">Cargando propuestas…</div>
        <div v-else-if="!expedienteId" class="text-grey-7">Este prospecto aún no tiene expediente empresarial.</div>
        <template v-else>
            <div class="row items-center q-mb-sm">
                <div class="text-grey-7">{{ propuestas.length }} propuesta(s)</div>
                <q-space />
                <q-btn v-if="permisos.create" dense no-caps color="primary" icon="add" label="Nueva propuesta" :loading="trabajando" @click="crear" />
            </div>
            <div v-if="error" class="text-negative q-mb-sm">{{ error }}</div>
            <div v-if="!propuestas.length" class="text-grey-7">Sin propuestas todavía.</div>
            <q-expansion-item v-for="p in propuestas" :key="p.id" class="q-mb-sm crm-prop-item" dense-toggle>
                <template #header>
                    <q-item-section>
                        <q-item-label>{{ p.folio }} · v{{ p.version_actual }}</q-item-label>
                    </q-item-section>
                    <q-item-section side><q-badge :color="color(p.estado)" :label="etiqueta(p.estado)" /></q-item-section>
                </template>
                <div v-for="v in [...p.versiones].reverse()" :key="v.id" class="q-pa-sm crm-prop-ver">
                    <div class="row items-center">
                        <b>Versión {{ v.version }}</b>
                        <q-badge class="q-ml-sm" :color="color(v.estado)" :label="etiqueta(v.estado)" />
                        <span v-if="v.vigencia_hasta" class="q-ml-sm text-grey-7">vigente hasta {{ String(v.vigencia_hasta).substring(0, 10) }}</span>
                    </div>
                    <div v-if="v.motivo" class="text-grey-7">Motivo: {{ v.motivo }}</div>
                    <div class="q-mt-xs">
                        <q-btn v-for="a in acciones(v)" :key="a" dense no-caps outline size="sm" class="q-mr-xs" :label="etiqueta(a)" :disabled="trabajando" @click="transicionar(v, a)" />
                        <q-btn v-if="permisos.create && v.version === p.version_actual && !['sustituida'].includes(v.estado)" dense no-caps flat size="sm" label="Nueva versión" :disabled="trabajando" @click="nuevaVersion(p)" />
                    </div>
                    <div v-if="v.historial?.length" class="q-mt-xs text-caption text-grey-7">
                        <div v-for="h in v.historial" :key="h.id">
                            {{ String(h.at).substring(0, 16).replace("T", " ") }} · {{ h.de ? etiqueta(h.de) + " → " : "" }}{{ etiqueta(h.a) }}<span v-if="h.motivo"> — {{ h.motivo }}</span>
                        </div>
                    </div>
                </div>
            </q-expansion-item>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";

const props = defineProps({ crmId: { type: [String, Number], default: null } });

const cargando = ref(true);
const trabajando = ref(false);
const error = ref("");
const expedienteId = ref(null);
const permisos = ref({});
const transiciones = ref({});
const propuestas = ref([]);

const ETIQ = {
    borrador: "Borrador", en_revision: "En revisión", aprobada: "Aprobada", enviada: "Enviada", vista: "Vista",
    cambios_solicitados: "Cambios solicitados", aceptada: "Aceptada", rechazada: "Rechazada", vencida: "Vencida", sustituida: "Sustituida",
};
const COLOR = { aprobada: "positive", aceptada: "positive", enviada: "info", vista: "info", rechazada: "negative", vencida: "grey", sustituida: "grey", cambios_solicitados: "warning", en_revision: "orange" };
const etiqueta = (e) => ETIQ[e] || e;
const color = (e) => COLOR[e] || "primary";

// Destino -> permiso que lo gobierna (igual que el backend; el servidor valida de nuevo).
const acciones = (v) =>
    (transiciones.value[v.estado] || []).filter((a) => permisos.value[a === "aprobada" ? "aprobar" : a === "enviada" ? "enviar" : "create"]);

const BASE = "/crm-empresarial/api";
const msg = (e) => e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(" ") : e?.response?.data?.message || "No se pudo completar la acción.";

const cargar = async () => {
    if (!props.crmId) { cargando.value = false; return; }
    cargando.value = true;
    try {
        const { data } = await axios.get(`${BASE}/crm/${props.crmId}/propuestas`);
        expedienteId.value = data.expediente_id;
        permisos.value = data.permisos;
        transiciones.value = data.transiciones;
        propuestas.value = data.propuestas;
        error.value = "";
    } catch (e) {
        error.value = e?.response?.status === 403 ? "No tienes permiso para ver las propuestas." : msg(e);
    } finally {
        cargando.value = false;
    }
};

const ejecutar = async (fn) => {
    trabajando.value = true;
    error.value = "";
    try { await fn(); await cargar(); } catch (e) { error.value = msg(e); } finally { trabajando.value = false; }
};

const crear = () => ejecutar(() => axios.post(`${BASE}/expedientes/${expedienteId.value}/propuestas`, {}));

const nuevaVersion = (p) => {
    const motivo = window.prompt("Motivo de la nueva versión:");
    if (!motivo) return;
    ejecutar(() => axios.post(`${BASE}/propuestas/${p.id}/versiones`, { motivo }));
};

const transicionar = (v, a) => {
    let motivo = null;
    if (["rechazada", "cambios_solicitados", "borrador"].includes(a)) {
        motivo = window.prompt("Indica el motivo:");
        if (!motivo) return;
    }
    ejecutar(() => axios.post(`${BASE}/propuesta-versiones/${v.id}/transicion`, { a, motivo }));
};

onMounted(cargar);
watch(() => props.crmId, cargar);
</script>

<style scoped>
.crm-prop-ver { border-top: 1px solid rgba(128, 128, 128, 0.25); }
.crm-prop-item { border: 1px solid rgba(128, 128, 128, 0.25); border-radius: 4px; }
</style>

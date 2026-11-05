<template>
    <div class="fcac-wrap">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h4 class="mb-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Contactos de alerta</h4>
            <button class="btn btn-primary" :disabled="contactos.length >= limite" @click="abrir()"><i class="bi bi-plus-lg me-1"></i>Nuevo contacto</button>
        </div>
        <div class="alert alert-light border small">
            Elige quién recibe los avisos de tus vehículos, por qué medio y qué tipos de alerta. Máximo {{ limite }} contactos.
            <span v-if="wa.tope"> WhatsApp este mes: <strong>{{ wa.enviados }} de {{ wa.tope }}</strong>; al llegar al tope los avisos siguen por correo.</span>
        </div>
        <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
        <div v-if="loading" class="text-center text-muted py-4"><div class="spinner-border text-primary"></div></div>
        <div v-else-if="!contactos.length" class="text-muted py-3">Aún no tienes contactos. Mientras tanto los avisos llegan al portal y a tu correo principal.</div>
        <div v-else>
            <div class="border rounded p-2 mb-2 d-flex gap-2 align-items-center" v-for="c in contactos" :key="c.id">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold">{{ c.nombre }}
                        <span v-if="c.es_principal" class="badge bg-primary ms-1">Principal</span>
                        <span v-if="!c.active" class="badge bg-secondary ms-1">Inactivo</span>
                    </div>
                    <div class="small text-muted">{{ [c.email, c.telefono].filter(Boolean).join(' · ') }}</div>
                    <div class="small mt-1">
                        <span class="badge bg-light text-dark border me-1" v-for="ch in c.channels || []" :key="ch">{{ ch }}</span>
                        <span class="badge bg-light text-dark border me-1">{{ (c.tipos_alerta && c.tipos_alerta.length) ? c.tipos_alerta.join(', ') : 'Todos los tipos' }}</span>
                        <span v-if="c.silencio_desde" class="badge bg-light text-dark border"><i class="bi bi-moon me-1"></i>{{ c.silencio_desde }}–{{ c.silencio_hasta }}</span>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-primary" @click="abrir(c)"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" @click="borrar(c)"><i class="bi bi-trash"></i></button>
            </div>
        </div>

        <h5 class="mt-4">Bitácora de envíos</h5>
        <div v-if="!bitacora.length" class="text-muted small">Todavía no se ha enviado ningún aviso.</div>
        <div v-else class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Fecha</th><th>Medio</th><th>Destino</th><th>Estado</th><th>Detalle</th></tr></thead>
                <tbody>
                    <tr v-for="b in bitacora" :key="b.id">
                        <td>{{ fecha(b.fecha) }}</td><td>{{ b.canal }}</td><td>{{ b.destino }}</td>
                        <td><span class="badge" :class="estadoClase(b.estado)">{{ estadoTxt(b.estado) }}</span></td>
                        <td class="small text-muted">{{ b.motivo }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="show" class="modal fade show" style="display:block;background:rgba(0,0,0,.4)" @click.self="show=false">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ form.id ? 'Editar contacto' : 'Nuevo contacto' }}</h5><button type="button" class="btn-close" @click="show=false"></button></div>
                <div class="modal-body">
                    <div v-if="errForm" class="alert alert-danger py-2">{{ errForm }}</div>
                    <label class="form-label fw-semibold">Nombre</label>
                    <input class="form-control mb-2" v-model="form.nombre" maxlength="120">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6"><label class="form-label">Correo</label><input type="email" class="form-control" v-model="form.email"></div>
                        <div class="col-md-6"><label class="form-label">Teléfono (WhatsApp)</label><input class="form-control" v-model="form.telefono" maxlength="30"></div>
                    </div>
                    <label class="form-label fw-semibold">Avisar por</label>
                    <div class="mb-2">
                        <label class="me-3" v-for="ch in canales" :key="ch"><input type="checkbox" :value="ch" v-model="form.channels"> {{ ch }}</label>
                    </div>
                    <label class="form-label fw-semibold">Tipos de alerta <span class="text-muted small">(ninguno = todos)</span></label>
                    <div class="mb-2">
                        <label class="me-3" v-for="t in tipos" :key="t"><input type="checkbox" :value="t" v-model="form.tipos_alerta"> {{ t }}</label>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">No molestar desde</label><input type="time" class="form-control" v-model="form.silencio_desde"></div>
                        <div class="col-6"><label class="form-label">hasta</label><input type="time" class="form-control" v-model="form.silencio_hasta"></div>
                    </div>
                    <label class="me-3"><input type="checkbox" v-model="form.es_principal"> Contacto principal</label>
                    <label><input type="checkbox" v-model="form.active"> Activo</label>
                </div>
                <div class="modal-footer"><button class="btn btn-light" @click="show=false">Cancelar</button><button class="btn btn-primary" :disabled="saving" @click="guardar">Guardar</button></div>
            </div></div>
        </div>
    </div>
</template>

<script setup>
import axios from "axios";
import { ref, onMounted } from "vue";

const props = defineProps({ baseUrl: { type: String, required: true } });
const canales = ["portal", "email", "whatsapp"];
const contactos = ref([]); const bitacora = ref([]); const tipos = ref([]);
const limite = ref(5); const wa = ref({ enviados: 0, tope: 0 });
const loading = ref(true); const error = ref(""); const errForm = ref("");
const show = ref(false); const saving = ref(false); const form = ref({});
const url = () => `${props.baseUrl}/contactos`;
const cfg = { headers: { Accept: "application/json" } };

const cargar = async () => {
    try {
        const { data } = await axios.get(url(), cfg);
        contactos.value = data.contactos; bitacora.value = data.bitacora; tipos.value = data.tipos;
        limite.value = data.limite; wa.value = data.whatsapp; error.value = "";
    } catch (e) { error.value = e.response?.data?.message || "No se pudo cargar."; }
    loading.value = false;
};
const abrir = (c) => {
    errForm.value = "";
    form.value = c ? { ...c, channels: [...(c.channels || [])], tipos_alerta: [...(c.tipos_alerta || [])] }
        : { nombre: "", email: "", telefono: "", channels: ["portal", "email"], tipos_alerta: [], silencio_desde: "", silencio_hasta: "", es_principal: !contactos.value.length, active: true };
    show.value = true;
};
const guardar = async () => {
    saving.value = true; errForm.value = "";
    const f = form.value;
    const body = { ...f, silencio_desde: f.silencio_desde || null, silencio_hasta: f.silencio_hasta || null, email: f.email || null, telefono: f.telefono || null };
    try {
        if (f.id) await axios.put(`${url()}/${f.id}`, body, cfg); else await axios.post(url(), body, cfg);
        show.value = false; await cargar();
    } catch (e) {
        errForm.value = e.response?.data?.message || "No se pudo guardar.";
    }
    saving.value = false;
};
const borrar = async (c) => {
    if (!confirm(`¿Quitar a ${c.nombre}?`)) return;
    try { await axios.delete(`${url()}/${c.id}`, cfg); await cargar(); }
    catch (e) { error.value = e.response?.data?.message || "No se pudo quitar."; }
};
const fecha = (iso) => iso ? new Date(iso).toLocaleString("es-MX") : "";
const estadoTxt = (s) => ({ sent: "Enviado", failed: "Falló", skipped: "Omitido", queued: "En cola" }[s] || s);
const estadoClase = (s) => ({ sent: "bg-success", failed: "bg-danger", skipped: "bg-secondary", queued: "bg-info" }[s] || "bg-light text-dark");
onMounted(cargar);
</script>

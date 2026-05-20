<template>
    <div class="megafamilia-soporte mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0">Soporte MegaFamilia</h5>
            <select v-model="statusFilter" class="form-select form-select-sm" style="width:160px" @change="fetch">
                <option value="">Todos los estados</option>
                <option value="Nuevo">Nuevo</option>
                <option value="Trabajo en curso">Trabajo en curso</option>
                <option value="Esperando al agente">Esperando al agente</option>
                <option value="Resuelto">Resuelto</option>
            </select>
        </div>

        <div v-if="loading && rows.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
        <div v-else-if="rows.length === 0" class="text-muted">No hay tickets MegaFamilia.</div>

        <div v-else class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover m-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Asunto</th><th>Estado</th><th>Creado</th><th class="text-end">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in rows" :key="t.id">
                                <td class="text-muted small">{{ t.id }}</td>
                                <td>
                                    <div class="fw-bold">{{ t.title || t.asunto || '—' }}</div>
                                    <div class="text-muted small">{{ truncate(t.description || '', 80) }}</div>
                                </td>
                                <td><span class="badge" :class="statusBadge(t.estado)">{{ t.estado || 'Nuevo' }}</span></td>
                                <td class="small text-muted">{{ formatDate(t.created_at) }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" @click="openRespond(t)">
                                        <i class="fa fa-reply"></i> Responder
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Respond modal -->
        <div v-if="responding" class="modal-overlay" @click.self="closeRespond">
            <div class="modal-panel">
                <div class="modal-panel-header">
                    <h5 class="m-0">Responder ticket #{{ responding.id }}</h5>
                    <button class="btn-close" @click="closeRespond"></button>
                </div>
                <div class="modal-panel-body">
                    <div class="text-muted small mb-2">{{ responding.title }}</div>
                    <textarea v-model="responseText" rows="6" class="form-control" placeholder="Tu respuesta…"></textarea>
                </div>
                <div class="modal-panel-footer">
                    <button class="btn btn-outline-secondary" @click="closeRespond">Cancelar</button>
                    <button class="btn btn-primary" :disabled="sending || !responseText.trim()" @click="send">
                        <i class="fa fa-paper-plane"></i> {{ sending ? 'Enviando…' : 'Enviar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaSoporte',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { rows: [], statusFilter: '', loading: false, errorMsg: '', responding: null, responseText: '', sending: false }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const params = {};
                if (this.statusFilter) params.estado = this.statusFilter;
                const { data } = await axios.get(`${this.baseUrl}/soporte/data`, { params });
                this.rows = data.data || [];
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        openRespond(t) { this.responding = t; this.responseText = ''; },
        closeRespond() { this.responding = null; },
        async send() {
            this.sending = true;
            try {
                const { data } = await axios.post(`${this.baseUrl}/soporte/${this.responding.id}/respond`, { message: this.responseText }, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                if (data.note) alert(data.note);
                this.closeRespond();
            } catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.sending = false; }
        },
        statusBadge(s) {
            return ({ Nuevo: 'bg-primary', 'Trabajo en curso': 'bg-info', 'Esperando al agente': 'bg-warning text-dark', Resuelto: 'bg-success' })[s] || 'bg-secondary';
        },
        truncate(s, n) { if (!s) return ''; return s.length > n ? s.slice(0, n) + '…' : s; },
        formatDate(ts) { return ts ? new Date(ts).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : ''; },
    },
};
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.modal-panel { background: #fff; border-radius: 8px; width: min(600px, 92vw); display: flex; flex-direction: column; }
.modal-panel-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
.modal-panel-body { padding: 1rem 1.25rem; }
.modal-panel-footer { padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef; display: flex; justify-content: flex-end; gap: 0.5rem; }
</style>

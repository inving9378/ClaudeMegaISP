<template>
    <div class="megafamilia-soporte mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-life-ring me-2 text-primary"></i>
                Soporte MegaFamilia
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch(pagination.current_page)">
                    <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                </button>
                <button class="btn btn-sm btn-primary" @click="openCreate">
                    <i class="fa fa-plus me-1"></i> Nuevo ticket
                </button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="row g-2 mb-3">
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-primary"><div class="card-body p-3">
                    <div class="text-muted small">Abiertos</div>
                    <div class="kpi-num">{{ kpis.open }}</div>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-warning"><div class="card-body p-3">
                    <div class="text-muted small">En proceso</div>
                    <div class="kpi-num text-warning">{{ kpis.in_progress }}</div>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-success"><div class="card-body p-3">
                    <div class="text-muted small">Resueltos hoy</div>
                    <div class="kpi-num text-success">{{ kpis.resolved_today }}</div>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-info"><div class="card-body p-3">
                    <div class="text-muted small">⌀ resolución</div>
                    <div class="kpi-num">{{ kpis.avg_resolution_hours ? Math.round(kpis.avg_resolution_hours) + 'h' : '—' }}</div>
                </div></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3"><div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Buscar</label>
                    <input v-model="filters.search" class="form-control form-control-sm" placeholder="topic / reporter…" @keyup.enter="fetch(1)" />
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Estado</label>
                    <select v-model="filters.estado" class="form-select form-select-sm" @change="fetch(1)">
                        <option value="">Todos</option>
                        <option value="Nuevo">Abierto</option>
                        <option value="Trabajo en curso">En proceso</option>
                        <option value="Resuelto">Resuelto</option>
                        <option value="Cerrado">Cerrado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Prioridad</label>
                    <select v-model="filters.priority" class="form-select form-select-sm" @change="fetch(1)">
                        <option value="">Todas</option>
                        <option value="1">Baja</option>
                        <option value="2">Media</option>
                        <option value="3">Alta</option>
                        <option value="4">Urgente</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Asignado a</label>
                    <select v-model="filters.assigned_to" class="form-select form-select-sm" @change="fetch(1)">
                        <option value="">Todos</option>
                        <option v-for="t in technicians" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100" @click="fetch(1)">
                        <i class="fa fa-filter me-1"></i> Filtrar
                    </button>
                </div>
            </div>
        </div></div>

        <!-- Tabla -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Asunto</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Prioridad</th>
                            <th>Asignado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading && rows.length === 0"><td colspan="7" class="text-center text-muted py-3">Cargando…</td></tr>
                        <tr v-else-if="rows.length === 0"><td colspan="7" class="text-center text-muted py-3">Sin tickets.</td></tr>
                        <tr v-for="row in rows" :key="row.id" role="button" @click="openDrawer(row)">
                            <td class="text-muted">#{{ row.id }}</td>
                            <td><strong>{{ row.reporter || 'Anónimo' }}</strong></td>
                            <td class="text-truncate" style="max-width:300px;" :title="row.topic">{{ row.topic }}</td>
                            <td class="text-center"><span class="badge" :class="statusBadge(row.estado)">{{ statusLabel(row.estado) }}</span></td>
                            <td class="text-center"><span class="badge" :class="priorityBadge(row.priority)">{{ priorityLabel(row.priority) }}</span></td>
                            <td class="small">{{ technicianName(row.assigned_to) }}</td>
                            <td class="small">{{ formatDate(row.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="pagination.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">Mostrando {{ pagination.from }}-{{ pagination.to }} de {{ pagination.total }}</small>
                <ul class="pagination pagination-sm m-0">
                    <li class="page-item" :class="{ disabled: pagination.current_page === 1 }"><button class="page-link" @click="fetch(pagination.current_page - 1)">‹</button></li>
                    <li class="page-item" v-for="p in pageRange" :key="p" :class="{ active: p === pagination.current_page }"><button class="page-link" @click="fetch(p)">{{ p }}</button></li>
                    <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }"><button class="page-link" @click="fetch(pagination.current_page + 1)">›</button></li>
                </ul>
            </div>
        </div>

        <!-- Drawer detalle -->
        <div v-if="drawer.open" class="mf-drawer-overlay" @click.self="closeDrawer">
            <aside class="mf-drawer">
                <header class="mf-drawer-header">
                    <div>
                        <h5 class="m-0">Ticket #{{ drawer.data?.ticket?.id }}</h5>
                        <small class="text-muted">{{ drawer.data?.ticket?.topic }}</small>
                    </div>
                    <button class="btn-close" @click="closeDrawer"></button>
                </header>
                <div class="mf-drawer-body">
                    <div v-if="drawer.loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
                    <template v-else-if="drawer.data">
                        <!-- Cliente -->
                        <section class="mb-3">
                            <h6 class="text-uppercase small text-muted">Cliente</h6>
                            <div class="small">
                                <div><strong>{{ drawer.data.ticket.reporter || '—' }}</strong></div>
                                <div v-if="drawer.data.account">
                                    Plan: <strong>{{ drawer.data.account.plan?.name || '—' }}</strong> ·
                                    Estado: <span class="badge bg-success">{{ drawer.data.account.status }}</span>
                                </div>
                                <div v-if="drawer.data.account?.license">
                                    Licencia vence: {{ formatDate(drawer.data.account.license.expires_at) }}
                                </div>
                            </div>
                        </section>

                        <!-- Hilo -->
                        <section class="mb-3">
                            <h6 class="text-uppercase small text-muted">Conversación</h6>
                            <div class="thread">
                                <div v-for="msg in drawer.data.ticket.ticket_thread" :key="msg.id"
                                     class="thread-msg" :class="msg.client_id ? 'left' : 'right'">
                                    <div class="bubble">
                                        <div>{{ msg.message }}</div>
                                        <small class="text-muted">{{ formatDate(msg.created_at) }}</small>
                                    </div>
                                </div>
                                <div v-if="!drawer.data.ticket.ticket_thread?.length" class="text-muted small">Sin mensajes.</div>
                            </div>
                        </section>

                        <!-- Respuesta -->
                        <section>
                            <h6 class="text-uppercase small text-muted">Responder</h6>
                            <textarea v-model="drawer.reply" rows="3" class="form-control" placeholder="Escribe tu respuesta…"></textarea>
                            <button class="btn btn-sm btn-primary mt-2" :disabled="acting || !drawer.reply.trim()" @click="respond">
                                <i class="fa fa-paper-plane me-1"></i> Enviar respuesta
                            </button>
                        </section>
                    </template>
                </div>
                <footer v-if="drawer.data" class="mf-drawer-footer">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small mb-1">Estado</label>
                            <select v-model="drawer.data.ticket.estado" class="form-select form-select-sm" @change="changeStatus">
                                <option value="Nuevo">Abierto</option>
                                <option value="Trabajo en curso">En proceso</option>
                                <option value="Resuelto">Resuelto</option>
                                <option value="Cerrado">Cerrado</option>
                                <option value="Esperando al cliente">Esperando al cliente</option>
                                <option value="Esperando al agente">Esperando al agente</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Asignar a</label>
                            <select v-model.number="drawer.data.ticket.assigned_to" class="form-select form-select-sm" @change="assign">
                                <option :value="null">— Sin asignar —</option>
                                <option v-for="t in technicians" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Prioridad</label>
                            <select v-model.number="drawer.data.ticket.priority" class="form-select form-select-sm" @change="assign">
                                <option :value="1">Baja</option>
                                <option :value="2">Media</option>
                                <option :value="3">Alta</option>
                                <option :value="4">Urgente</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-sm btn-outline-danger" @click="closeTicket">
                                <i class="fa fa-times me-1"></i> Cerrar ticket
                            </button>
                        </div>
                    </div>
                </footer>
            </aside>
        </div>

        <!-- Modal nuevo ticket -->
        <div v-if="create.open" class="mf-modal-overlay" @click.self="create.open = false">
            <div class="mf-modal-panel" style="max-width:560px;">
                <div class="mf-modal-header">
                    <h5 class="m-0">Nuevo ticket MegaFamilia</h5>
                    <button class="btn-close" @click="create.open = false"></button>
                </div>
                <div class="mf-modal-body">
                    <div class="mb-3">
                        <label class="form-label">Asunto</label>
                        <input v-model="create.topic" class="form-control" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reporter ID (usuario)</label>
                        <input v-model.number="create.reporter_id" type="number" class="form-control" placeholder="opcional" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prioridad</label>
                        <select v-model.number="create.priority" class="form-select">
                            <option :value="1">Baja</option>
                            <option :value="2">Media</option>
                            <option :value="3">Alta</option>
                            <option :value="4">Urgente</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensaje inicial</label>
                        <textarea v-model="create.message" rows="3" class="form-control"></textarea>
                    </div>
                </div>
                <div class="mf-modal-footer">
                    <button class="btn btn-outline-secondary" @click="create.open = false">Cancelar</button>
                    <button class="btn btn-primary" :disabled="!create.topic || acting" @click="doCreate">
                        <i class="fa fa-save me-1"></i> Crear ticket
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
const STATUS_LABEL = {
    'Nuevo': 'Abierto', 'Trabajo en curso': 'En proceso',
    'Resuelto': 'Resuelto', 'Cerrado': 'Cerrado',
    'Esperando al cliente': 'Esp. cliente', 'Esperando al agente': 'Esp. agente',
};
const STATUS_BADGE = {
    'Nuevo': 'bg-primary', 'Trabajo en curso': 'bg-warning text-dark',
    'Resuelto': 'bg-success', 'Cerrado': 'bg-secondary',
};
const PRIORITY_LABEL = { 1: 'Baja', 2: 'Media', 3: 'Alta', 4: 'Urgente' };
const PRIORITY_BADGE = {
    1: 'bg-light text-dark', 2: 'bg-info text-dark',
    3: 'bg-warning text-dark', 4: 'bg-danger',
};

export default {
    name: 'MegaFamiliaSoporte',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() {
        return {
            loading: false, acting: false,
            rows: [], technicians: [],
            kpis: { open: 0, in_progress: 0, resolved_today: 0, avg_resolution_hours: 0 },
            filters: { search: '', estado: '', priority: '', assigned_to: '', date_from: '', date_to: '' },
            pagination: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            drawer: { open: false, loading: false, data: null, reply: '' },
            create: { open: false, topic: '', reporter_id: null, priority: 2, message: '' },
        };
    },
    computed: {
        pageRange() {
            const cur = this.pagination.current_page, last = this.pagination.last_page;
            const lo = Math.max(1, cur - 2), hi = Math.min(last, cur + 2);
            const arr = []; for (let i = lo; i <= hi; i++) arr.push(i); return arr;
        },
    },
    mounted() {
        this.fetchTechnicians();
        this.fetch(1);
    },
    methods: {
        async fetch(page = 1) {
            this.loading = true;
            try {
                const params = { page };
                for (const k of Object.keys(this.filters)) if (this.filters[k] !== '' && this.filters[k] !== null) params[k] = this.filters[k];
                const { data } = await axios.get(`${this.baseUrl}/soporte/data`, { params });
                this.kpis = data.kpis || this.kpis;
                this.rows = data.tickets?.data || [];
                this.pagination = {
                    current_page: data.tickets?.current_page || 1,
                    last_page: data.tickets?.last_page || 1,
                    from: data.tickets?.from || 0,
                    to: data.tickets?.to || 0,
                    total: data.tickets?.total || 0,
                };
            } catch (e) { console.error('[MF/Soporte]', e); }
            finally { this.loading = false; }
        },
        async fetchTechnicians() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/soporte/technicians`);
                this.technicians = data.technicians || [];
            } catch (e) { /* no-op */ }
        },
        async openDrawer(row) {
            this.drawer = { open: true, loading: true, data: null, reply: '' };
            try {
                const { data } = await axios.get(`${this.baseUrl}/soporte/${row.id}`);
                this.drawer.data = data;
            } catch (e) { alert(e.response?.data?.message || e.message); this.drawer.open = false; }
            finally { this.drawer.loading = false; }
        },
        closeDrawer() { this.drawer = { open: false, loading: false, data: null, reply: '' }; },
        async respond() {
            if (!this.drawer.reply.trim()) return;
            this.acting = true;
            try {
                await axios.post(`${this.baseUrl}/soporte/${this.drawer.data.ticket.id}/respond`,
                    { message: this.drawer.reply },
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                this.drawer.reply = '';
                await this.openDrawer({ id: this.drawer.data.ticket.id });
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        async changeStatus() {
            try {
                await axios.post(`${this.baseUrl}/soporte/${this.drawer.data.ticket.id}/status`,
                    { status: this.drawer.data.ticket.estado },
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                await this.fetch(this.pagination.current_page);
            } catch (e) { alert(e.response?.data?.message || e.message); }
        },
        async assign() {
            try {
                await axios.post(`${this.baseUrl}/soporte/${this.drawer.data.ticket.id}/assign`,
                    { assigned_to: this.drawer.data.ticket.assigned_to, priority: this.drawer.data.ticket.priority },
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
            } catch (e) { alert(e.response?.data?.message || e.message); }
        },
        async closeTicket() {
            if (!confirm('¿Cerrar este ticket?')) return;
            this.drawer.data.ticket.estado = 'Cerrado';
            await this.changeStatus();
            this.closeDrawer();
        },
        openCreate() { this.create = { open: true, topic: '', reporter_id: null, priority: 2, message: '' }; },
        async doCreate() {
            this.acting = true;
            try {
                await axios.post(`${this.baseUrl}/soporte`, this.create, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                this.create.open = false;
                await this.fetch(1);
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        technicianName(id) {
            if (!id) return '—';
            return this.technicians.find(t => t.id === id)?.name || `#${id}`;
        },
        statusLabel(s) { return STATUS_LABEL[s] || s; },
        statusBadge(s) { return STATUS_BADGE[s] || 'bg-light text-dark'; },
        priorityLabel(p) { return PRIORITY_LABEL[p] || '—'; },
        priorityBadge(p) { return PRIORITY_BADGE[p] || 'bg-light text-dark'; },
        formatDate(v) {
            if (!v) return '—';
            const d = new Date(v);
            return Number.isNaN(d.getTime()) ? v : d.toLocaleString('es-MX', { day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit' });
        },
    },
};
</script>

<style scoped>
.kpi-card { border: 1px solid #e9ecef; }
.kpi-card.kpi-primary { border-left: 4px solid #0d6efd; }
.kpi-card.kpi-warning { border-left: 4px solid #ffc107; }
.kpi-card.kpi-success { border-left: 4px solid #198754; }
.kpi-card.kpi-info    { border-left: 4px solid #0dcaf0; }
.kpi-num { font-size: 1.5rem; font-weight: 700; line-height: 1; margin-top: 4px; }

.megafamilia-soporte tbody tr { cursor: pointer; }

.thread { max-height: 360px; overflow-y: auto; }
.thread-msg { display: flex; margin-bottom: 8px; }
.thread-msg.left { justify-content: flex-start; }
.thread-msg.right { justify-content: flex-end; }
.bubble {
    max-width: 78%; padding: 8px 12px; border-radius: 10px;
    font-size: 13px; line-height: 1.4;
}
.thread-msg.left  .bubble { background: #e9ecef; color: #212529; }
.thread-msg.right .bubble { background: #0d6efd; color: #fff; }

.mf-drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9990; }
.mf-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: min(560px, 95vw); background: #fff; box-shadow: -4px 0 16px rgba(0,0,0,0.1); display: flex; flex-direction: column; }
.mf-drawer-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
.mf-drawer-body { flex: 1; overflow-y: auto; padding: 1rem 1.25rem; }
.mf-drawer-footer { padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef; }

.mf-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.mf-modal-panel { background: #fff; border-radius: 8px; width: min(640px, 92vw); max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
.mf-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
.mf-modal-body { padding: 1rem 1.25rem; overflow-y: auto; }
.mf-modal-footer { padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef; display: flex; justify-content: flex-end; gap: 0.5rem; }
</style>

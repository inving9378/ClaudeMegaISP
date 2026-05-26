<template>
    <div class="megafamilia-notificaciones mt-3">
        <h5 class="mb-3">
            <i class="fa fa-bullhorn me-2 text-primary"></i>
            Notificaciones push
        </h5>

        <!-- Nuevo envío -->
        <div class="card mb-3">
            <div class="card-header py-2 bg-light"><strong>Nueva notificación</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Título <small class="text-muted">{{ form.title.length }}/100</small></label>
                        <input v-model="form.title" maxlength="100" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select v-model="form.type" class="form-select">
                            <option value="informativa">Informativa</option>
                            <option value="urgente">Urgente</option>
                            <option value="promocion">Promoción</option>
                            <option value="mantenimiento">Mantenimiento</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Mensaje <small class="text-muted">{{ form.message.length }}/500</small></label>
                        <textarea v-model="form.message" maxlength="500" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Destinatarios</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check"><input class="form-check-input" type="radio" id="seg-all" v-model="form.segment" value="all" @change="updateEstimate" /><label class="form-check-label" for="seg-all">Todos los clientes activos</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" id="seg-plan" v-model="form.segment" value="plan" @change="updateEstimate" /><label class="form-check-label" for="seg-plan">Por plan</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" id="seg-prof" v-model="form.segment" value="profile_type" @change="updateEstimate" /><label class="form-check-label" for="seg-prof">Por tipo de perfil</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" id="seg-acc" v-model="form.segment" value="account" @change="updateEstimate" /><label class="form-check-label" for="seg-acc">Cliente específico</label></div>
                        </div>
                    </div>

                    <div class="col-md-6" v-if="form.segment === 'plan'">
                        <label class="form-label">Plan</label>
                        <select v-model="form.target" class="form-select" @change="updateEstimate">
                            <option value="">—</option>
                            <option v-for="p in targets.plans" :key="p.id" :value="p.slug">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-6" v-if="form.segment === 'profile_type'">
                        <label class="form-label">Tipo de perfil</label>
                        <select v-model="form.target" class="form-select" @change="updateEstimate">
                            <option value="">—</option>
                            <option v-for="t in targets.profile_types" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                    <div class="col-md-6" v-if="form.segment === 'account'">
                        <label class="form-label">Cuenta (autocomplete)</label>
                        <select v-model="form.target" class="form-select" @change="updateEstimate">
                            <option value="">—</option>
                            <option v-for="a in targets.accounts" :key="a.id" :value="a.id">
                                #{{ a.id }} — {{ a.user?.name || 'Sin nombre' }}
                            </option>
                        </select>
                    </div>

                    <!-- Preview -->
                    <div class="col-12">
                        <div class="push-preview">
                            <div class="push-icon"><i class="fa fa-bell"></i></div>
                            <div>
                                <div class="push-title">{{ form.title || 'Título de la notificación' }}</div>
                                <div class="push-body">{{ form.message || 'Mensaje del push…' }}</div>
                                <div class="push-meta">
                                    <span class="badge" :class="typeBadge(form.type)">{{ form.type }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <div class="small">
                    <i class="fa fa-users me-1"></i>
                    Estimado de destinatarios: <strong>{{ estimate }}</strong>
                    <span v-if="estimating" class="text-muted ms-2"><i class="fa fa-spinner fa-spin"></i></span>
                </div>
                <button class="btn btn-sm btn-primary" :disabled="!canSend || sending" @click="send">
                    <i class="fa fa-paper-plane me-1"></i> {{ sending ? 'Enviando…' : 'Enviar' }}
                </button>
            </div>
        </div>

        <!-- Historial -->
        <div class="card">
            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                <strong>Historial de envíos</strong>
                <div class="d-flex gap-2 align-items-center">
                    <select v-model="historyFilters.type" class="form-select form-select-sm" style="width:auto" @change="fetchHistory(1)">
                        <option value="">Todos los tipos</option>
                        <option value="informativa">Informativa</option>
                        <option value="urgente">Urgente</option>
                        <option value="promocion">Promoción</option>
                        <option value="mantenimiento">Mantenimiento</option>
                    </select>
                    <input v-model="historyFilters.date_from" type="date" class="form-control form-control-sm" @change="fetchHistory(1)" />
                    <input v-model="historyFilters.date_to" type="date" class="form-control form-control-sm" @change="fetchHistory(1)" />
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Título</th>
                            <th>Segmento</th>
                            <th class="text-center">Destinos</th>
                            <th class="text-center">Enviadas</th>
                            <th class="text-center">Fallidas</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!history.length" class="text-muted">
                            <td colspan="7" class="text-center py-3">Sin envíos registrados.</td>
                        </tr>
                        <tr v-for="h in history" :key="h.id" role="button" @click="openDetail(h)">
                            <td class="small">{{ formatDate(h.created_at) }}</td>
                            <td>{{ parseDetail(h.detail)?.title || '—' }}</td>
                            <td class="small">{{ parseDetail(h.detail)?.segment || '—' }} <span v-if="parseDetail(h.detail)?.target">/ {{ parseDetail(h.detail).target }}</span></td>
                            <td class="text-center">{{ parseDetail(h.detail)?.segment_size || 0 }}</td>
                            <td class="text-center text-success">{{ parseDetail(h.detail)?.result?.sent || 0 }}</td>
                            <td class="text-center text-danger">{{ parseDetail(h.detail)?.result?.failed || 0 }}</td>
                            <td><span class="badge" :class="typeBadge(parseDetail(h.detail)?.type)">{{ parseDetail(h.detail)?.type || 'informativa' }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal detalle -->
        <div v-if="detail" class="mf-modal-overlay" @click.self="detail = null">
            <div class="mf-modal-panel" style="max-width:560px;">
                <div class="mf-modal-header">
                    <h5 class="m-0">Detalle del envío #{{ detail.id }}</h5>
                    <button class="btn-close" @click="detail = null"></button>
                </div>
                <div class="mf-modal-body">
                    <dl class="row small mb-2">
                        <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8">{{ formatDate(detail.created_at) }}</dd>
                        <dt class="col-sm-4">IP</dt><dd class="col-sm-8"><code>{{ detail.ip || '—' }}</code></dd>
                    </dl>
                    <pre class="bg-light p-2 rounded small">{{ JSON.stringify(detail.data, null, 2) }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
const TYPE_BADGES = {
    informativa: 'bg-info-subtle text-info',
    urgente: 'bg-danger',
    promocion: 'bg-success',
    mantenimiento: 'bg-warning text-dark',
};

export default {
    name: 'MegaFamiliaNotificaciones',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            form: { title: '', message: '', segment: 'all', target: '', type: 'informativa' },
            targets: { plans: [], accounts: [], profile_types: [] },
            estimate: 0,
            estimating: false,
            sending: false,
            history: [],
            historyFilters: { type: '', date_from: '', date_to: '' },
            detail: null,
            JSON,
        };
    },
    computed: {
        canSend() {
            if (!this.form.title || !this.form.message) return false;
            if (this.form.segment !== 'all' && !this.form.target) return false;
            return true;
        },
    },
    mounted() {
        this.fetchTargets();
        this.fetchHistory();
        this.updateEstimate();
    },
    watch: {
        'form.segment'() { this.form.target = ''; },
    },
    methods: {
        async fetchTargets() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/notificaciones/targets`);
                this.targets = data;
            } catch (e) { /* no-op */ }
        },
        async updateEstimate() {
            if (this.form.segment !== 'all' && !this.form.target) {
                this.estimate = 0; return;
            }
            this.estimating = true;
            try {
                const { data } = await axios.post(`${this.baseUrl}/notificaciones/estimate`, {
                    segment: this.form.segment, target: this.form.target,
                }, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                this.estimate = data.count || 0;
            } catch (e) { this.estimate = 0; }
            finally { this.estimating = false; }
        },
        async send() {
            if (!this.canSend) return;
            if (!confirm(`¿Enviar a ${this.estimate} destinatarios?`)) return;
            this.sending = true;
            try {
                const { data } = await axios.post(`${this.baseUrl}/notificaciones/send`, this.form, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                alert(`Enviadas: ${data.sent || 0} · Fallidas: ${data.failed || 0}`);
                this.form.title = ''; this.form.message = '';
                await this.fetchHistory();
            } catch (e) { alert(e.response?.data?.error || e.response?.data?.message || e.message); }
            finally { this.sending = false; }
        },
        async fetchHistory(page = 1) {
            try {
                const params = { page };
                if (this.historyFilters.type)      params.type      = this.historyFilters.type;
                if (this.historyFilters.date_from) params.date_from = this.historyFilters.date_from;
                if (this.historyFilters.date_to)   params.date_to   = this.historyFilters.date_to;
                const { data } = await axios.get(`${this.baseUrl}/notificaciones/history`, { params });
                this.history = data.data || [];
            } catch (e) { /* no-op */ }
        },
        async openDetail(row) {
            try {
                const { data } = await axios.get(`${this.baseUrl}/notificaciones/${row.id}`);
                this.detail = data;
            } catch (e) { alert(e.response?.data?.message || e.message); }
        },
        parseDetail(d) { try { return d ? JSON.parse(d) : null; } catch { return null; } },
        typeBadge(t) { return TYPE_BADGES[t] || 'bg-light text-dark'; },
        formatDate(v) {
            if (!v) return '—';
            const d = new Date(v);
            return Number.isNaN(d.getTime()) ? v : d.toLocaleString('es-MX', { day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit' });
        },
    },
};
</script>

<style scoped>
.push-preview {
    display: flex; gap: 0.75rem; padding: 12px;
    border: 1px solid #e9ecef; border-radius: 8px; background: #f8f9fa;
}
.push-icon {
    width: 38px; height: 38px;
    background: #0d6efd; color: #fff; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.push-title { font-weight: 600; font-size: 14px; }
.push-body { font-size: 13px; color: #495057; line-height: 1.35; }
.push-meta { margin-top: 4px; }

.mf-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.mf-modal-panel { background: #fff; border-radius: 8px; width: min(640px, 92vw); max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
.mf-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
.mf-modal-body { padding: 1rem 1.25rem; overflow-y: auto; }
</style>

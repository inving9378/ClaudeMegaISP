<template>
    <div class="megafamilia-tareas mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">Tareas</h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" :class="{ active: statusFilter === '' }" @click="setStatus('')">Todas</button>
                <button class="btn btn-outline-primary" :class="{ active: statusFilter === 'pending' }" @click="setStatus('pending')">Pendientes</button>
                <button class="btn btn-outline-info" :class="{ active: statusFilter === 'completed' }" @click="setStatus('completed')">Completadas</button>
                <button class="btn btn-outline-success" :class="{ active: statusFilter === 'approved' }" @click="setStatus('approved')">Aprobadas</button>
            </div>
        </div>

        <div v-if="loading && rows.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
        <div v-else-if="rows.length === 0" class="text-muted">No hay tareas con ese filtro.</div>

        <div v-else class="row">
            <div v-for="t in rows" :key="t.id" class="col-md-6 col-lg-4 mb-3">
                <div class="card task-card h-100" :class="`status-${t.status}`">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="m-0">{{ t.title }}</h6>
                            <span class="badge" :class="statusBadge(t.status)">{{ statusLabel(t.status) }}</span>
                        </div>
                        <div class="text-muted small">{{ t.profile?.name || '—' }}</div>
                        <div v-if="t.description" class="mt-2 text-muted small">{{ t.description }}</div>

                        <div class="reward mt-2">
                            <i class="fa fa-gift me-1 text-warning"></i>
                            <strong>{{ rewardLabel(t.reward_type, t.reward_value) }}</strong>
                            <span v-if="t.points > 0" class="ms-2 badge bg-info">{{ t.points }} pts</span>
                        </div>

                        <div v-if="t.photo_proof" class="mt-2">
                            <img :src="t.photo_proof" class="img-fluid rounded" style="max-height:120px" />
                        </div>

                        <div v-if="t.completed_at" class="text-muted small mt-2">
                            Completada {{ formatDate(t.completed_at) }}
                        </div>

                        <div v-if="t.status === 'completed'" class="mt-auto pt-3 d-flex gap-2">
                            <button class="btn btn-success flex-grow-1" :disabled="acting === t.id" @click="approve(t)">
                                <i class="fa fa-check"></i> Aprobar y dar recompensa
                            </button>
                            <button class="btn btn-outline-danger" :disabled="acting === t.id" @click="reject(t)">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaTareas',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { rows: [], loading: false, errorMsg: '', acting: null, statusFilter: 'pending' }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const params = {};
                if (this.statusFilter) params.status = this.statusFilter;
                const { data } = await axios.get(`${this.baseUrl}/tareas/data`, { params });
                this.rows = data.data || [];
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        setStatus(s) { this.statusFilter = s; this.fetch(); },
        async approve(t) {
            this.acting = t.id;
            try { await axios.post(`${this.baseUrl}/tareas/${t.id}/approve`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } }); t.status = 'approved'; t.approved_at = new Date().toISOString(); }
            catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.acting = null; }
        },
        async reject(t) {
            this.acting = t.id;
            try { await axios.post(`${this.baseUrl}/tareas/${t.id}/reject`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } }); t.status = 'rejected'; }
            catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.acting = null; }
        },
        statusBadge(s) { return ({ pending: 'bg-primary', completed: 'bg-info', approved: 'bg-success', rejected: 'bg-danger' })[s] || 'bg-light text-dark'; },
        statusLabel(s) { return ({ pending: 'Pendiente', completed: 'Completada', approved: 'Aprobada', rejected: 'Rechazada' })[s] || s; },
        rewardLabel(type, value) {
            return ({
                time_extra: `+${value} min de pantalla`,
                app_unlock: `Desbloqueo de app`,
                points: `${value} puntos`,
                badge: `Insignia`,
            })[type] || `${type}: ${value}`;
        },
        formatDate(ts) { return new Date(ts).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }); },
    },
};
</script>

<style scoped>
.task-card.status-pending { border-left: 3px solid #5b8def; }
.task-card.status-completed { border-left: 3px solid #0dcaf0; background: #f0fbff; }
.task-card.status-approved { border-left: 3px solid #38c172; opacity: 0.8; }
.task-card.status-rejected { border-left: 3px solid #e74c3c; opacity: 0.6; }
.reward { background: #fff8ef; padding: 0.4rem 0.6rem; border-radius: 4px; }
</style>

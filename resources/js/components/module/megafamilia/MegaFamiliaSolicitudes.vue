<template>
    <div class="megafamilia-solicitudes mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0">Solicitudes</h5>
            <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-primary" :class="{ active: statusFilter === 'pending' }" @click="setStatus('pending')">Pendientes</button>
                <button class="btn btn-outline-success" :class="{ active: statusFilter === 'approved' }" @click="setStatus('approved')">Aprobadas</button>
                <button class="btn btn-outline-danger" :class="{ active: statusFilter === 'rejected' }" @click="setStatus('rejected')">Rechazadas</button>
            </div>
        </div>

        <div v-if="loading && rows.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
        <div v-else-if="rows.length === 0" class="text-muted">No hay solicitudes con ese filtro.</div>

        <div v-else class="row">
            <div v-for="r in rows" :key="r.id" class="col-md-6 col-lg-4 mb-3">
                <div class="card request-card h-100" :class="`req-${r.type}`">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start">
                            <i class="fa fa-2x" :class="iconFor(r.type)"></i>
                            <span class="badge" :class="statusBadge(r.status)">{{ statusLabel(r.status) }}</span>
                        </div>
                        <h6 class="mt-2 m-0">{{ labelFor(r.type) }}</h6>
                        <div v-if="r.detail" class="text-muted small mt-1">{{ r.detail }}</div>
                        <div v-if="r.message" class="message-bubble mt-2">"{{ r.message }}"</div>
                        <div class="text-muted small mt-2">{{ r.profile?.name || 'sin perfil' }} · {{ formatDate(r.created_at) }}</div>

                        <div v-if="r.status === 'pending'" class="mt-auto pt-3 d-flex gap-2">
                            <button class="btn btn-success flex-grow-1" :disabled="acting === r.id" @click="approve(r)">
                                <i class="fa fa-check"></i> Aprobar
                            </button>
                            <button class="btn btn-outline-danger flex-grow-1" :disabled="acting === r.id" @click="reject(r)">
                                <i class="fa fa-times"></i> Rechazar
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
    name: 'MegaFamiliaSolicitudes',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { rows: [], loading: false, errorMsg: '', acting: null, statusFilter: 'pending' }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/solicitudes/data`, { params: { status: this.statusFilter } });
                this.rows = data.data || [];
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        setStatus(s) { this.statusFilter = s; this.fetch(); },
        async approve(r) {
            this.acting = r.id;
            try { await axios.post(`${this.baseUrl}/solicitudes/${r.id}/approve`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } }); r.status = 'approved'; }
            catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.acting = null; }
        },
        async reject(r) {
            this.acting = r.id;
            try { await axios.post(`${this.baseUrl}/solicitudes/${r.id}/reject`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } }); r.status = 'rejected'; }
            catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.acting = null; }
        },
        iconFor(type) { return ({ time_extra: 'fa-clock text-info', app_unlock: 'fa-mobile-screen text-primary', web_unlock: 'fa-globe text-warning' })[type] || 'fa-question'; },
        labelFor(type) { return ({ time_extra: 'Tiempo extra', app_unlock: 'Desbloquear app', web_unlock: 'Desbloquear sitio' })[type] || type; },
        statusBadge(s) { return ({ pending: 'bg-primary', approved: 'bg-success', rejected: 'bg-danger' })[s] || 'bg-light text-dark'; },
        statusLabel(s) { return ({ pending: 'Pendiente', approved: 'Aprobada', rejected: 'Rechazada' })[s] || s; },
        formatDate(ts) { return new Date(ts).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }); },
    },
};
</script>

<style scoped>
.request-card { border-top: 3px solid #e9ecef; }
.req-time_extra { border-top-color: #0dcaf0; }
.req-app_unlock { border-top-color: #5b8def; }
.req-web_unlock { border-top-color: #ff8c00; }
.message-bubble { background: #f5f7fa; border-left: 3px solid #adb5bd; padding: 0.4rem 0.6rem; font-style: italic; color: #495057; }
</style>

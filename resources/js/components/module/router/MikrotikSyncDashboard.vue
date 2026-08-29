<template>
    <div class="mksync-wrap">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h4 class="mb-0"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Sync Mikrotik — pendientes / fallidos</h4>
            <div class="d-flex gap-2 align-items-center">
                <select class="form-select form-select-sm w-auto" v-model="statusFilter">
                    <option value="">Todos</option>
                    <option value="pending">Pendientes</option>
                    <option value="failed">Fallidos</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary" @click="load"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>

        <div v-if="loading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary mb-2"></div><div>Cargando…</div>
        </div>

        <div v-else-if="!items.length" class="mksync-empty">
            <i class="bi bi-check-circle"></i>
            <h6>Sin servicios pendientes o fallidos</h6>
            <p class="text-muted">Todos los servicios están sincronizados con Mikrotik.</p>
        </div>

        <div v-else class="table-responsive">
            <table class="table table-sm align-middle mksync-table">
                <thead>
                    <tr>
                        <th>Cliente</th><th>Tipo</th><th>Router</th>
                        <th>Estado</th><th>Error</th><th>Última actualización</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in items" :key="`${row.tipo}-${row.id}`">
                        <td>{{ row.client_name }}</td>
                        <td><span class="badge bg-secondary">{{ row.tipo_label }}</span></td>
                        <td>{{ row.router || '—' }}</td>
                        <td><span class="badge" :class="statusBadge(row.status)">{{ statusLabel(row.status) }}</span></td>
                        <td class="text-muted small mksync-error" :title="row.error || ''">{{ row.error || '—' }}</td>
                        <td class="text-muted small">{{ fmtDate(row.fecha) }}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" :disabled="isRetrying(row)" @click="retry(row)">
                                <i class="bi bi-arrow-repeat me-1"></i>{{ isRetrying(row) ? '…' : 'Reintentar sync' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <small class="text-muted">{{ items.length }} servicio(s)</small>
        </div>

        <transition name="mksync-toast-fade">
            <div v-if="toast.visible" class="mksync-toast" :class="`mksync-toast-${toast.type}`">
                <i :class="toast.icon" class="me-2"></i>{{ toast.message }}
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, reactive, watch, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'MikrotikSyncDashboard',
    props: { baseUrl: { type: String, default: '/red/mikrotik-sync' } },
    setup(props) {
        const items = ref([]);
        const loading = ref(true);
        const statusFilter = ref('');
        const retrying = ref(null);

        const toast = reactive({ visible: false, message: '', type: 'success', icon: '' });
        function notify(message, type = 'success') {
            toast.message = message; toast.type = type;
            toast.icon = type === 'success' ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-circle-fill';
            toast.visible = true;
            setTimeout(() => { toast.visible = false; }, 3500);
        }

        const statusLabel = (s) => ({ pending: 'Pendiente', failed: 'Fallido' }[s] || s);
        const statusBadge = (s) => ({ pending: 'bg-warning text-dark', failed: 'bg-danger' }[s] || 'bg-secondary');
        const isRetrying = (row) => retrying.value === `${row.tipo}-${row.id}`;

        function fmtDate(v) {
            if (!v) return '—';
            const d = new Date(v.replace(' ', 'T'));
            return isNaN(d) ? v : d.toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        }

        function load() {
            loading.value = true;
            const params = {};
            if (statusFilter.value) params.status = statusFilter.value;
            axios.get(`${props.baseUrl}/api/servicios`, { params })
                .then(({ data }) => { items.value = data?.items ?? []; })
                .catch(() => notify('No se pudo cargar el listado.', 'error'))
                .finally(() => { loading.value = false; });
        }

        function retry(row) {
            retrying.value = `${row.tipo}-${row.id}`;
            axios.post(`${props.baseUrl}/api/servicios/${row.tipo}/${row.id}/reintentar`)
                .then(() => { notify('Reintento despachado.'); load(); })
                .catch((e) => notify(e?.response?.data?.message || 'No se pudo reintentar.', 'error'))
                .finally(() => { retrying.value = null; });
        }

        watch(statusFilter, load);
        onMounted(load);

        return { items, loading, statusFilter, toast, statusLabel, statusBadge, isRetrying, fmtDate, load, retry };
    },
};
</script>

<style scoped>
.mksync-wrap { padding: 4px; }
.mksync-table th { font-size: .8rem; color: #6b7280; font-weight: 600; }
.mksync-error { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mksync-empty { text-align: center; padding: 60px 20px; background: #fff; border: 1px dashed #cbd5e1; border-radius: 14px; }
.mksync-empty i { font-size: 3rem; color: #cbd5e1; display: block; margin-bottom: 10px; }
.mksync-toast { position: fixed; bottom: 24px; right: 24px; z-index: 10001; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.15); display: flex; align-items: center; color: #fff; }
.mksync-toast-success { background: #16a34a; }
.mksync-toast-error { background: #dc2626; }
.mksync-toast-fade-enter-active, .mksync-toast-fade-leave-active { transition: all .25s; }
.mksync-toast-fade-enter-from, .mksync-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }
</style>

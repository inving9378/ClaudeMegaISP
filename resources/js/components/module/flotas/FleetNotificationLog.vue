<template>
    <div class="flt-nlog-wrap">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h4 class="mb-0"><i class="bi bi-bell me-2 text-primary"></i>Log de notificaciones</h4>
            <div class="d-flex gap-2 align-items-center">
                <select class="form-select form-select-sm w-auto" v-model="statusFilter">
                    <option value="">Todos los estados</option>
                    <option value="sent">Enviados</option>
                    <option value="failed">Fallidos</option>
                    <option value="skipped">Omitidos</option>
                    <option value="queued">En cola</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary" @click="load(1)"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>

        <div v-if="loading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary mb-2"></div><div>Cargando…</div>
        </div>

        <div v-else-if="!items.length" class="flt-nlog-empty">
            <i class="bi bi-bell-slash"></i>
            <h6>Sin notificaciones registradas</h6>
            <p class="text-muted">Aquí aparecerá la auditoría de cada envío de alerta de geocerca.</p>
        </div>

        <div v-else class="table-responsive">
            <table class="table table-sm align-middle flt-nlog-table">
                <thead>
                    <tr>
                        <th>Fecha/hora</th><th>Vehículo</th><th>Geocerca · evento</th>
                        <th>Destinatario</th><th>Canal</th><th>Estado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in items" :key="l.id">
                        <td class="text-muted small">{{ fmtDate(l.created_at) }}</td>
                        <td>
                            <a v-if="l.vehicle_id" :href="`${baseUrl}/${l.vehicle_id}?tab=gps`">{{ l.vehicle }}</a>
                            <span v-else>{{ l.vehicle }}</span>
                        </td>
                        <td>
                            {{ l.geofence }}
                            <span class="badge ms-1" :class="l.event_type === 'enter' ? 'bg-success' : 'bg-warning text-dark'">
                                {{ l.event_type === 'enter' ? 'Entrada' : 'Salida' }}
                            </span>
                        </td>
                        <td>
                            <div>{{ l.user }}</div>
                            <div class="text-muted small">{{ l.destination || '—' }}</div>
                        </td>
                        <td><i class="bi me-1" :class="l.channel === 'whatsapp' ? 'bi-whatsapp text-success' : 'bi-envelope text-primary'"></i>{{ l.channel }}</td>
                        <td>
                            <span class="badge" :class="statusBadge(l.status)">{{ statusLabel(l.status) }}</span>
                            <i v-if="l.error_message" class="bi bi-info-circle ms-1 text-muted" :title="l.error_message"></i>
                        </td>
                        <td class="text-end">
                            <button v-if="l.status === 'failed'" class="btn btn-sm btn-outline-primary" :disabled="resending === l.id" @click="resend(l)">
                                <i class="bi bi-arrow-repeat me-1"></i>{{ resending === l.id ? '…' : 'Reenviar' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="d-flex justify-content-between align-items-center mt-2" v-if="lastPage > 1">
                <small class="text-muted">{{ total }} registros · página {{ page }}/{{ lastPage }}</small>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" :disabled="page <= 1" @click="load(page - 1)">Anterior</button>
                    <button class="btn btn-outline-secondary" :disabled="page >= lastPage" @click="load(page + 1)">Siguiente</button>
                </div>
            </div>
        </div>

        <transition name="flt-toast-fade">
            <div v-if="toast.visible" class="flt-toast" :class="`flt-toast-${toast.type}`">
                <i :class="toast.icon" class="me-2"></i>{{ toast.message }}
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, reactive, watch, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'FleetNotificationLog',
    props: { baseUrl: { type: String, default: '/flotas' } },
    setup(props) {
        const items = ref([]);
        const loading = ref(true);
        const page = ref(1);
        const lastPage = ref(1);
        const total = ref(0);
        const statusFilter = ref('');
        const resending = ref(null);

        const toast = reactive({ visible: false, message: '', type: 'success', icon: '' });
        function notify(message, type = 'success') {
            toast.message = message; toast.type = type;
            toast.icon = type === 'success' ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-circle-fill';
            toast.visible = true;
            setTimeout(() => { toast.visible = false; }, 3500);
        }

        const statusLabel = (s) => ({ sent: 'Enviado', failed: 'Fallido', skipped: 'Omitido', queued: 'En cola' }[s] || s);
        const statusBadge = (s) => ({ sent: 'bg-success', failed: 'bg-danger', skipped: 'bg-secondary', queued: 'bg-info text-dark' }[s] || 'bg-secondary');

        function fmtDate(v) {
            if (!v) return '—';
            const d = new Date(v);
            return isNaN(d) ? v : d.toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        }

        function load(p = 1) {
            loading.value = true;
            const params = { page: p };
            if (statusFilter.value) params.status = statusFilter.value;
            axios.get(`${props.baseUrl}/api/notificaciones-log`, { params })
                .then(({ data }) => {
                    items.value = data?.items ?? [];
                    total.value = data?.total ?? 0;
                    page.value = data?.page ?? 1;
                    lastPage.value = data?.last_page ?? 1;
                })
                .catch(() => notify('No se pudo cargar el log.', 'error'))
                .finally(() => { loading.value = false; });
        }

        function resend(l) {
            resending.value = l.id;
            axios.post(`${props.baseUrl}/api/notificaciones-log/${l.id}/resend`)
                .then(() => { notify('Reenvío despachado.'); load(page.value); })
                .catch((e) => notify(e?.response?.data?.message || 'No se pudo reenviar.', 'error'))
                .finally(() => { resending.value = null; });
        }

        watch(statusFilter, () => load(1));
        onMounted(() => load(1));

        return { items, loading, page, lastPage, total, statusFilter, resending, toast,
                 statusLabel, statusBadge, fmtDate, load, resend };
    },
};
</script>

<style scoped>
.flt-nlog-wrap { padding: 4px; }
.flt-nlog-table th { font-size: .8rem; color: #6b7280; font-weight: 600; }
.flt-nlog-empty { text-align: center; padding: 60px 20px; background: #fff; border: 1px dashed #cbd5e1; border-radius: 14px; }
.flt-nlog-empty i { font-size: 3rem; color: #cbd5e1; display: block; margin-bottom: 10px; }
.flt-toast { position: fixed; bottom: 24px; right: 24px; z-index: 10001; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.15); display: flex; align-items: center; color: #fff; }
.flt-toast-success { background: #16a34a; }
.flt-toast-error { background: #dc2626; }
.flt-toast-fade-enter-active, .flt-toast-fade-leave-active { transition: all .25s; }
.flt-toast-fade-enter-from, .flt-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }
</style>

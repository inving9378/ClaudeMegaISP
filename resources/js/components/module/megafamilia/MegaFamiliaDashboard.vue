<template>
    <div class="mega-familia-dashboard">
        <!-- KPI cards -->
        <div class="row mt-3">
            <div class="col-md-3 col-sm-6 mb-3" v-for="kpi in kpiList" :key="kpi.key" :id="kpi.tourId">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <i class="fa fa-2x me-3" :class="[kpi.icon, kpi.color]"></i>
                        <div>
                            <div class="text-muted small">{{ kpi.label }}</div>
                            <div class="h3 m-0">{{ kpi.value }}</div>
                            <div v-if="kpi.sub" class="small text-muted">{{ kpi.sub }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Clientes por plan (bar) -->
            <div class="col-lg-7 mb-3" id="mf-chart-clientes">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Clientes por plan</h5>
                        <div v-if="loading.summary" class="text-muted">Cargando…</div>
                        <div v-else-if="clientsByPlan.length === 0" class="text-muted">Sin cuentas activas todavía.</div>
                        <div v-else class="bars">
                            <div v-for="row in clientsByPlan" :key="row.plan" class="bar-row">
                                <div class="bar-label">{{ row.plan }}</div>
                                <div class="bar-track">
                                    <div class="bar-fill" :style="{ width: barWidth(row.total) + '%' }">{{ row.total }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertas recientes -->
            <div class="col-lg-5 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Alertas recientes</h5>
                        <div v-if="loading.summary" class="text-muted">Cargando…</div>
                        <div v-else-if="recentAlerts.length === 0" class="text-muted">Sin alertas.</div>
                        <ul v-else class="list-unstyled m-0">
                            <li v-for="alert in recentAlerts" :key="alert.id" class="alert-row">
                                <i class="fa me-2" :class="alertIcon(alert.type)"></i>
                                <strong>{{ alertLabel(alert.type) }}</strong>
                                <span class="text-muted small ms-2">{{ alert.profile?.name || 'sin perfil' }}</span>
                                <span class="float-end text-muted small">{{ formatDate(alert.created_at) }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3" id="mf-ingresos-mes">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Ingresos del mes (estimado)</div>
                        <div class="h2 mb-0 text-success">${{ formatMoney(revenueThisMonth) }} MXN</div>
                        <div class="text-muted small">Suma de cuentas activas × precio mensual del plan.</div>
                    </div>
                </div>
            </div>
        </div>

        <button @click="launchTour" class="btn-tour-help" title="Tour guiado">
            <i class="fas fa-question-circle"></i>
        </button>
    </div>
</template>

<script>
import { startTour, shouldShowTour } from './MegaFamiliaTour.js';

export default {
    name: 'MegaFamiliaDashboard',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            kpis: {},
            clientsByPlan: [],
            recentAlerts: [],
            revenueThisMonth: 0,
            loading: { summary: true },
        };
    },
    computed: {
        kpiList() {
            return [
                { key: 'total_clients', tourId: 'mf-kpi-cuentas',      label: 'Cuentas', value: this.kpis.total_clients ?? 0, sub: `${this.kpis.active_clients ?? 0} activas`, icon: 'fa-users', color: 'text-primary' },
                { key: 'total_devices', tourId: 'mf-kpi-dispositivos', label: 'Dispositivos', value: this.kpis.total_devices ?? 0, sub: `${this.kpis.online_devices ?? 0} online`, icon: 'fa-mobile-screen', color: 'text-info' },
                { key: 'unread_alerts', tourId: 'mf-kpi-alertas',      label: 'Alertas sin leer', value: this.kpis.unread_alerts ?? 0, icon: 'fa-bell', color: 'text-warning' },
                { key: 'total_plans',   tourId: 'mf-kpi-planes',       label: 'Planes activos', value: this.kpis.total_plans ?? 0, icon: 'fa-layer-group', color: 'text-secondary' },
            ];
        },
    },
    mounted() {
        this.fetchSummary();
        if (shouldShowTour('dashboard')) {
            setTimeout(() => startTour('dashboard'), 1200);
        }
    },
    methods: {
        launchTour() { startTour('dashboard'); },
        async fetchSummary() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/dashboard/summary`);
                this.kpis = data.kpis || {};
                this.clientsByPlan = data.clients_by_plan || [];
                this.recentAlerts = data.recent_alerts || [];
                this.revenueThisMonth = Number(data.revenue_this_month) || 0;
            } catch (e) {
                // Silent fail; KPIs stay at 0.
            } finally {
                this.loading.summary = false;
            }
        },
        barWidth(value) {
            const max = Math.max(...this.clientsByPlan.map((r) => Number(r.total)), 1);
            return Math.max(5, (Number(value) / max) * 100);
        },
        alertIcon(type) {
            return {
                uninstall_attempt: 'fa-shield-alt text-danger',
                geofence_exit: 'fa-map-marker-alt text-warning',
                blocked_content: 'fa-ban text-secondary',
                low_battery: 'fa-battery-quarter text-warning',
                device_offline: 'fa-wifi text-muted',
            }[type] || 'fa-bell';
        },
        alertLabel(type) {
            return {
                uninstall_attempt: 'Intento de desinstalar',
                geofence_exit: 'Salida de geofence',
                blocked_content: 'Contenido bloqueado',
                low_battery: 'Batería baja',
                device_offline: 'Dispositivo offline',
            }[type] || type;
        },
        formatDate(ts) {
            if (!ts) return '—';
            return new Date(ts).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
        },
        formatMoney(n) {
            return Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    },
};
</script>

<style scoped>
.stat-card { border: 1px solid #eef0f4; }
.bars { display: flex; flex-direction: column; gap: 0.6rem; }
.bar-row { display: flex; align-items: center; gap: 0.75rem; }
.bar-label { min-width: 80px; font-weight: 600; }
.bar-track { flex: 1; background: #eef0f4; border-radius: 4px; height: 24px; overflow: hidden; }
.bar-fill {
    background: linear-gradient(90deg, #5b8def, #38c172);
    color: #fff;
    height: 100%;
    padding: 0 0.5rem;
    font-size: 12px;
    line-height: 24px;
    text-align: right;
    border-radius: 4px;
}
.alert-row {
    padding: 0.4rem 0;
    border-bottom: 1px dashed #eef0f4;
}
.alert-row:last-child { border-bottom: 0; }

.btn-tour-help {
    position: fixed; bottom: 30px; right: 30px; z-index: 999;
    width: 48px; height: 48px; border-radius: 50%;
    background: #0d6efd; color: white; border: none;
    font-size: 20px; box-shadow: 0 4px 12px rgba(13,110,253,0.4);
    cursor: pointer; transition: transform 0.2s;
}
.btn-tour-help:hover { transform: scale(1.1); }
</style>

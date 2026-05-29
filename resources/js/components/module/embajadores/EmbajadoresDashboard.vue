<template>
    <div class="embajadores-dashboard">
        <div class="row mt-3">
            <div class="col-md-3 col-sm-6 mb-3" v-for="kpi in kpiList" :key="kpi.key">
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
            <div class="col-lg-5 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Embajadores por plan</h5>
                        <div v-if="loading" class="text-muted">Cargando…</div>
                        <div v-else-if="!porPlan || Object.keys(porPlan).length === 0" class="text-muted small">Sin embajadores aún.</div>
                        <div v-else>
                            <div v-for="(total, plan) in porPlan" :key="plan" class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-600">{{ planLabel(plan) }}</span>
                                    <span class="badge" :class="planBadge(plan)">{{ total }}</span>
                                </div>
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar" :class="planBar(plan)"
                                         :style="{ width: barWidth(total) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Comisiones pendientes de aprobación</h5>
                        <div v-if="loading" class="text-muted">Cargando…</div>
                        <div v-else-if="recentComisiones.length === 0" class="text-muted small">Sin comisiones pendientes.</div>
                        <div v-else class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Beneficiario</th>
                                        <th>Nivel</th>
                                        <th class="text-end">Monto</th>
                                        <th>Periodo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in recentComisiones" :key="c.id">
                                        <td>{{ clientName(c.beneficiary) }}</td>
                                        <td><span class="badge bg-secondary">N{{ c.level }}</span></td>
                                        <td class="text-end text-success fw-600">${{ formatMoney(c.commission_amount) }}</td>
                                        <td class="text-muted small">{{ c.period_month }}/{{ c.period_year }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <a :href="baseUrl + '/comisiones'" class="btn btn-sm btn-outline-primary">Ver todas</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'EmbajadoresDashboard',
    props: {
        baseUrl:   { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: true,
            kpis: {},
            porPlan: {},
            recentComisiones: [],
        };
    },
    computed: {
        kpiList() {
            return [
                {
                    key: 'total_embajadores', label: 'Total embajadores',
                    value: this.kpis.total_embajadores ?? 0,
                    sub: `${this.kpis.embajadores_activos ?? 0} elegibles`,
                    icon: 'fa-handshake', color: 'text-primary',
                },
                {
                    key: 'comisiones_este_mes', label: 'Comisiones este mes',
                    value: '$' + this.formatMoney(this.kpis.comisiones_este_mes ?? 0),
                    icon: 'fa-coins', color: 'text-success',
                },
                {
                    key: 'comisiones_pendientes', label: 'Pendientes de aprobar',
                    value: this.kpis.comisiones_pendientes ?? 0,
                    icon: 'fa-clock', color: 'text-warning',
                },
                {
                    key: 'recompensas_disponibles', label: 'Mensualidades gratis',
                    value: this.kpis.recompensas_disponibles ?? 0,
                    sub: 'disponibles para aplicar',
                    icon: 'fa-gift', color: 'text-info',
                },
            ];
        },
    },
    mounted() {
        this.fetchSummary();
    },
    methods: {
        async fetchSummary() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/dashboard/summary`);
                this.kpis             = data.kpis || {};
                this.porPlan          = data.por_plan || {};
                this.recentComisiones = data.recent_comisiones || [];
            } catch (e) {
                console.error('EmbajadoresDashboard: error cargando resumen', e);
            } finally {
                this.loading = false;
            }
        },
        planLabel(plan) {
            return plan === 'single_reward' ? 'Mensualidad gratis' : 'Multinivel';
        },
        planBadge(plan) {
            return plan === 'single_reward' ? 'bg-info' : 'bg-primary';
        },
        planBar(plan) {
            return plan === 'single_reward' ? 'bg-info' : 'bg-primary';
        },
        barWidth(value) {
            const max = Math.max(...Object.values(this.porPlan).map(Number), 1);
            return Math.max(5, (Number(value) / max) * 100);
        },
        clientName(client) {
            const info = client?.client_main_information;
            if (!info) return `#${client?.id ?? '?'}`;
            return `${info.name || ''} ${info.father_last_name || ''}`.trim();
        },
        formatMoney(n) {
            return Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    },
};
</script>

<style scoped>
.stat-card { border: 1px solid #eef0f4; }
.fw-600 { font-weight: 600; }
</style>

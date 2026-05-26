<template>
    <div class="megafamilia-reportes mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-chart-bar me-2 text-primary"></i>
                Reportes MegaFamilia
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" @click="exportCsv">
                    <i class="fa fa-file-csv me-1"></i> Exportar CSV
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Perfil</label>
                        <select v-model="filters.profile_id" class="form-select form-select-sm">
                            <option value="">Todos los perfiles</option>
                            <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Desde</label>
                        <input v-model="filters.date_from" type="date" class="form-control form-control-sm" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Hasta</label>
                        <input v-model="filters.date_to" type="date" class="form-control form-control-sm" />
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary w-100" :disabled="loading" @click="apply">
                            <i class="fa fa-filter me-1"></i> Aplicar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="row g-2 mb-3">
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-info">
                    <div class="card-body p-3">
                        <div class="text-muted small">Tiempo total pantalla</div>
                        <div class="kpi-num">{{ minutesPretty(kpis.total_screen_minutes) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-success">
                    <div class="card-body p-3">
                        <div class="text-muted small">App top</div>
                        <div class="kpi-num kpi-text">{{ kpis.top_app || '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-warning">
                    <div class="card-body p-3">
                        <div class="text-muted small">Solicitudes</div>
                        <div class="kpi-num">{{ kpis.requests_count }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-primary">
                    <div class="card-body p-3">
                        <div class="text-muted small">Tareas completadas</div>
                        <div class="kpi-num">{{ kpis.tasks_completed }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-center py-3">
            <div class="spinner-border text-primary"></div>
        </div>

        <!-- Gráfica barras: tiempo pantalla por día -->
        <div class="card mb-3">
            <div class="card-header py-2 bg-light"><strong>Tiempo de pantalla por día</strong></div>
            <div class="card-body">
                <canvas ref="chartScreen" height="100"></canvas>
                <div v-if="data.screen_time_by_day && !data.screen_time_by_day.length" class="text-muted small text-center mt-2">
                    Sin datos en el rango seleccionado.
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Top apps -->
            <div class="col-lg-7 mb-3">
                <div class="card h-100">
                    <div class="card-header py-2 bg-light"><strong>Top 10 apps más usadas</strong></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>App</th>
                                    <th>Tiempo</th>
                                    <th style="width:40%">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!data.top_apps || data.top_apps.length === 0">
                                    <td colspan="4" class="text-muted text-center py-3">Sin datos.</td>
                                </tr>
                                <tr v-for="(app, idx) in data.top_apps" :key="app.app">
                                    <td class="text-muted">{{ idx + 1 }}</td>
                                    <td><strong>{{ app.app }}</strong></td>
                                    <td class="small">{{ minutesPretty(app.minutes) }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height:8px;">
                                                <div class="progress-bar bg-info" :style="`width:${app.percent}%`"></div>
                                            </div>
                                            <small class="text-muted">{{ app.percent }}%</small>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sitios bloqueados -->
            <div class="col-lg-5 mb-3">
                <div class="card h-100">
                    <div class="card-header py-2 bg-light"><strong>Sitios bloqueados intentados</strong></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Dominio</th>
                                    <th class="text-center">Intentos</th>
                                    <th>Última vez</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!data.blocked_sites || data.blocked_sites.length === 0">
                                    <td colspan="3" class="text-muted text-center py-3">Sin intentos.</td>
                                </tr>
                                <tr v-for="b in data.blocked_sites" :key="b.domain">
                                    <td><span class="text-truncate d-inline-block" style="max-width:180px" :title="b.domain">{{ b.domain }}</span></td>
                                    <td class="text-center"><span class="badge bg-danger">{{ b.attempts }}</span></td>
                                    <td class="small">{{ formatDate(b.last_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actividad por hora -->
        <div class="card mb-3">
            <div class="card-header py-2 bg-light"><strong>Actividad por hora del día</strong></div>
            <div class="card-body">
                <canvas ref="chartHour" height="80"></canvas>
            </div>
        </div>
    </div>
</template>

<script>
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

export default {
    name: 'MegaFamiliaReportes',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: false,
            profiles: [],
            filters: {
                profile_id: '',
                date_from: this.defaultFrom(),
                date_to: this.defaultTo(),
            },
            kpis: { total_screen_minutes: 0, top_app: '—', requests_count: 0, tasks_completed: 0 },
            data: { screen_time_by_day: [], top_apps: [], activity_by_hour: [], blocked_sites: [] },
            chartScreen: null,
            chartHour: null,
        };
    },
    mounted() {
        this.fetchProfiles();
        this.fetch();
    },
    beforeUnmount() {
        this.chartScreen?.destroy();
        this.chartHour?.destroy();
    },
    methods: {
        defaultFrom() {
            const d = new Date(); d.setDate(d.getDate() - 13);
            return d.toISOString().slice(0, 10);
        },
        defaultTo() { return new Date().toISOString().slice(0, 10); },
        apply() { this.fetch(); },
        async fetchProfiles() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/reportes/profiles`);
                this.profiles = data.profiles || [];
            } catch (e) { /* no-op */ }
        },
        async fetch() {
            this.loading = true;
            try {
                const params = {};
                if (this.filters.profile_id) params.profile_id = this.filters.profile_id;
                if (this.filters.date_from)  params.date_from  = this.filters.date_from;
                if (this.filters.date_to)    params.date_to    = this.filters.date_to;

                const { data } = await axios.get(`${this.baseUrl}/reportes/data`, { params });
                this.kpis = data.kpis || this.kpis;
                this.data = {
                    screen_time_by_day: data.screen_time_by_day || [],
                    top_apps:           data.top_apps || [],
                    activity_by_hour:   data.activity_by_hour || [],
                    blocked_sites:      data.blocked_sites || [],
                };
                this.$nextTick(() => this.renderCharts());
            } catch (e) {
                console.error('[MF/Reportes]', e);
            } finally {
                this.loading = false;
            }
        },
        renderCharts() {
            const screenEl = this.$refs.chartScreen;
            if (screenEl) {
                this.chartScreen?.destroy();
                this.chartScreen = new Chart(screenEl, {
                    type: 'bar',
                    data: {
                        labels: this.data.screen_time_by_day.map(r => r.day.slice(5)),
                        datasets: [{
                            label: 'Minutos',
                            data: this.data.screen_time_by_day.map(r => r.minutes),
                            backgroundColor: '#0dcaf0',
                            borderRadius: 4,
                        }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    },
                });
            }

            const hourEl = this.$refs.chartHour;
            if (hourEl) {
                this.chartHour?.destroy();
                this.chartHour = new Chart(hourEl, {
                    type: 'line',
                    data: {
                        labels: this.data.activity_by_hour.map(r => `${r.hour}:00`),
                        datasets: [{
                            label: 'Eventos',
                            data: this.data.activity_by_hour.map(r => r.total),
                            fill: true,
                            backgroundColor: 'rgba(13,110,253,0.10)',
                            borderColor: '#0d6efd',
                            tension: 0.35,
                            pointRadius: 3,
                        }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    },
                });
            }
        },
        exportCsv() {
            const params = new URLSearchParams();
            if (this.filters.profile_id) params.set('profile_id', this.filters.profile_id);
            if (this.filters.date_from)  params.set('date_from',  this.filters.date_from);
            if (this.filters.date_to)    params.set('date_to',    this.filters.date_to);
            window.location.href = `${this.baseUrl}/reportes/export?${params.toString()}`;
        },
        minutesPretty(min) {
            min = Number(min) || 0;
            if (min < 60) return `${min} min`;
            const h = Math.floor(min / 60);
            const m = min % 60;
            return `${h}h ${m}m`;
        },
        formatDate(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        },
    },
};
</script>

<style scoped>
.kpi-card { border: 1px solid #e9ecef; }
.kpi-card.kpi-info    { border-left: 4px solid #0dcaf0; }
.kpi-card.kpi-success { border-left: 4px solid #198754; }
.kpi-card.kpi-warning { border-left: 4px solid #ffc107; }
.kpi-card.kpi-primary { border-left: 4px solid #0d6efd; }
.kpi-num { font-size: 1.75rem; font-weight: 700; line-height: 1; margin-top: 4px; }
.kpi-num.kpi-text { font-size: 1.1rem; word-break: break-word; }

canvas { max-height: 280px; }
</style>

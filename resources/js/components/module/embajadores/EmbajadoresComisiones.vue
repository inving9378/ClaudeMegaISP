<template>
    <div class="embajadores-comisiones">
        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-center">
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" v-model="filters.status" @change="cargar">
                            <option value="">Todos los estados</option>
                            <option value="pending">Pendientes</option>
                            <option value="approved">Aprobadas</option>
                            <option value="applied">Aplicadas</option>
                            <option value="cancelled">Canceladas</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" v-model="filters.year" @change="cargar">
                            <option value="">Todos los años</option>
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" v-model="filters.month" @change="cargar">
                            <option value="">Todos los meses</option>
                            <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" v-model="filters.level" @change="cargar">
                            <option value="">Todos los niveles</option>
                            <option v-for="n in 5" :key="n" :value="n">Nivel {{ n }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex gap-3 justify-content-end flex-wrap">
                            <span v-for="(total, status) in totals" :key="status" class="small">
                                <span class="badge" :class="statusBadge(status)">{{ status }}</span>
                                <strong class="ms-1">${{ formatMoney(total) }}</strong>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-muted">Cargando comisiones…</div>
        <div v-else-if="comisiones.length === 0" class="alert alert-info">Sin comisiones con estos filtros.</div>
        <div v-else>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Beneficiario</th>
                            <th>Referido (quien pagó)</th>
                            <th class="text-center">Nivel</th>
                            <th class="text-end">Base</th>
                            <th class="text-center">%</th>
                            <th class="text-end">Comisión</th>
                            <th class="text-center">Periodo</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in comisiones" :key="c.id">
                            <td>
                                <div class="fw-600 small">{{ clientName(c.beneficiary) }}</div>
                            </td>
                            <td>
                                <div class="small text-muted">{{ clientName(c.referral?.referred_client) }}</div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary">N{{ c.level }}</span>
                            </td>
                            <td class="text-end text-muted small">${{ formatMoney(c.base_amount) }}</td>
                            <td class="text-center small">{{ c.commission_pct }}%</td>
                            <td class="text-end fw-600 text-success">${{ formatMoney(c.commission_amount) }}</td>
                            <td class="text-center small">{{ c.period_month }}/{{ c.period_year }}</td>
                            <td class="text-center">
                                <span class="badge" :class="statusBadge(c.status)">{{ statusLabel(c.status) }}</span>
                            </td>
                            <td class="text-center">
                                <template v-if="c.status === 'pending'">
                                    <button class="btn btn-sm btn-success me-1" @click="aprobar(c)" title="Aprobar">
                                        <i class="fa fa-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" @click="cancelar(c)" title="Cancelar">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </template>
                                <span v-else class="text-muted small">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-2" v-if="pagination.last_page > 1">
                <button class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page <= 1"
                        @click="cambiarPagina(pagination.current_page - 1)">‹ Anterior</button>
                <span class="btn btn-sm btn-light disabled">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
                <button class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page >= pagination.last_page"
                        @click="cambiarPagina(pagination.current_page + 1)">Siguiente ›</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'EmbajadoresComisiones',
    props: {
        baseUrl:   { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        const now = new Date();
        return {
            loading:    true,
            comisiones: [],
            pagination: {},
            totals:     {},
            filters:    { status: 'pending', year: now.getFullYear(), month: now.getMonth() + 1, level: '', page: 1 },
        };
    },
    computed: {
        years() {
            const y = new Date().getFullYear();
            return [y - 1, y, y + 1];
        },
    },
    mounted() {
        this.cargar();
    },
    methods: {
        async cargar() {
            this.loading = true;
            try {
                const params = { ...this.filters, per_page: 25 };
                const { data } = await axios.get(`${this.baseUrl}/comisiones/data`, { params });
                this.comisiones = data.data.data;
                this.pagination = {
                    current_page: data.data.current_page,
                    last_page: data.data.last_page,
                    total: data.data.total,
                };
                this.totals = data.totals || {};
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
        cambiarPagina(page) {
            this.filters.page = page;
            this.cargar();
        },
        async aprobar(comision) {
            if (!confirm('¿Aprobar esta comisión?')) return;
            try {
                await axios.post(`${this.baseUrl}/comisiones/${comision.id}/approve`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                comision.status = 'approved';
            } catch (e) {
                alert(e.response?.data?.message || 'Error aprobando la comisión.');
            }
        },
        async cancelar(comision) {
            if (!confirm('¿Cancelar esta comisión? Esta acción no se puede deshacer.')) return;
            try {
                await axios.post(`${this.baseUrl}/comisiones/${comision.id}/cancel`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                comision.status = 'cancelled';
            } catch (e) {
                alert(e.response?.data?.message || 'Error cancelando la comisión.');
            }
        },
        clientName(client) {
            const info = client?.client_main_information;
            if (!info) return `#${client?.id ?? '?'}`;
            return `${info.name || ''} ${info.father_last_name || ''}`.trim();
        },
        statusLabel(s) {
            return { pending: 'Pendiente', approved: 'Aprobada', applied: 'Aplicada', cancelled: 'Cancelada' }[s] || s;
        },
        statusBadge(s) {
            return { pending: 'bg-warning text-dark', approved: 'bg-success', applied: 'bg-secondary', cancelled: 'bg-danger' }[s] || 'bg-light text-dark';
        },
        monthName(m) {
            return new Date(2000, m - 1, 1).toLocaleString('es-MX', { month: 'long' });
        },
        formatMoney(n) {
            return Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    },
};
</script>

<style scoped>
.fw-600 { font-weight: 600; }
</style>

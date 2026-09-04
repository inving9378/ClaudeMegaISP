<template>
    <div class="flt-dash-wrap">

        <div class="flt-dash-header">
            <div>
                <h4 class="flt-dash-title">Análisis comparativo de gastos</h4>
                <p class="flt-dash-sub">Compara el gasto de la flota por vehículo en el período que elijas</p>
            </div>
            <div class="flt-dash-actions">
                <a :href="baseUrl" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Volver al dashboard
                </a>
            </div>
        </div>

        <div v-if="loading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary mb-2"></div>
            <div>Cargando gastos…</div>
        </div>

        <template v-else>
            <!-- ── FILTROS ─────────────────────────────────────────────── -->
            <div class="flt-card mb-4">
                <div class="flt-card-body flt-filters">
                    <div class="flt-filter-group">
                        <label class="small text-muted mb-1 d-block">Período</label>
                        <select class="form-select form-select-sm" v-model="period">
                            <option value="this_month">Mes actual</option>
                            <option value="last_month">Mes anterior</option>
                            <option value="last_3_months">Últimos 3 meses</option>
                            <option value="this_year">Este año</option>
                            <option value="custom">Personalizado…</option>
                        </select>
                    </div>
                    <template v-if="period === 'custom'">
                        <div class="flt-filter-group">
                            <label class="small text-muted mb-1 d-block">Desde</label>
                            <input type="date" class="form-control form-control-sm" v-model="customStart">
                        </div>
                        <div class="flt-filter-group">
                            <label class="small text-muted mb-1 d-block">Hasta</label>
                            <input type="date" class="form-control form-control-sm" v-model="customEnd">
                        </div>
                    </template>
                    <div class="flt-filter-group">
                        <label class="small text-muted mb-1 d-block">Vehículo</label>
                        <select class="form-select form-select-sm" v-model="vehicleFilter">
                            <option value="">Todos los vehículos</option>
                            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.display_name || (v.brand + ' ' + v.model) }}</option>
                        </select>
                    </div>
                    <div class="flt-filter-group flt-filter-range text-muted small">
                        {{ rangeLabel }}
                    </div>
                </div>
            </div>

            <!-- ── KPI CARDS ───────────────────────────────────────────── -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-4">
                    <div class="flt-metric">
                        <div class="flt-metric-label">Gasto total del período</div>
                        <div class="flt-metric-value">{{ fmtMoney(totalPeriod) }}</div>
                        <div class="flt-metric-foot">{{ vehicleRows.length }} vehículo(s) en el análisis</div>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="flt-metric">
                        <div class="flt-metric-label">Promedio por vehículo</div>
                        <div class="flt-metric-value">{{ fmtMoney(avgPerVehicle) }}</div>
                        <div class="flt-metric-foot">Gasto total ÷ vehículos en el análisis</div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="flt-metric">
                        <div class="flt-metric-label">Variación vs período anterior</div>
                        <div class="flt-metric-value" :class="variationClass">{{ variationText }}</div>
                        <div class="flt-metric-foot">{{ fmtMoney(prevTotalPeriod) }} en el período anterior equivalente</div>
                    </div>
                </div>
            </div>

            <!-- ── TABLA POR VEHÍCULO ──────────────────────────────────── -->
            <div class="flt-card">
                <div class="flt-card-head">
                    <span><i class="bi bi-table me-2"></i>Desglose por vehículo</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm flt-cmp-table mb-0" v-if="vehicleRows.length">
                        <thead>
                            <tr>
                                <th>Vehículo</th>
                                <th class="text-end">Mantenimiento</th>
                                <th class="text-end">Combustible</th>
                                <th class="text-end">Documentos</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">% de la flota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in vehicleRows" :key="row.id">
                                <td>
                                    <a :href="`${baseUrl}/${row.id}`">{{ row.display_name || (row.brand + ' ' + row.model) }}</a>
                                    <div class="small text-muted">{{ row.plates || 'Sin placas' }}</div>
                                </td>
                                <td class="text-end">{{ fmtMoney(row.maint) }}</td>
                                <td class="text-end">{{ fmtMoney(row.fuel) }}</td>
                                <td class="text-end">{{ fmtMoney(row.docs) }}</td>
                                <td class="text-end fw-bold">{{ fmtMoney(row.total) }}</td>
                                <td class="text-end">{{ row.pct.toFixed(1) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="text-muted small p-4">Sin gastos registrados en el período seleccionado.</div>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'FleetExpenseComparison',
    props: { baseUrl: { type: String, default: '/flotas' } },
    setup(props) {
        const loading = ref(true);
        const vehicles = ref([]);
        const maintenances = ref([]);
        const fuelLogs = ref([]);
        const documents = ref([]);

        const period = ref('this_month');
        const vehicleFilter = ref('');
        const today = new Date();
        const toIso = (d) => d.toISOString().slice(0, 10);
        const customStart = ref(toIso(new Date(today.getFullYear(), today.getMonth(), 1)));
        const customEnd = ref(toIso(today));

        const moneyFmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
        const fmtMoney = (v) => moneyFmt.format(Number(v || 0));
        const dateFmt = new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });

        // ── Rango de fechas por período ─────────────────────────────────────
        const range = computed(() => {
            const now = new Date();
            let start, end, prevStart, prevEnd;
            if (period.value === 'this_month') {
                start = new Date(now.getFullYear(), now.getMonth(), 1);
                end = now;
                prevStart = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                prevEnd = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59);
            } else if (period.value === 'last_month') {
                start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                end = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59);
                prevStart = new Date(now.getFullYear(), now.getMonth() - 2, 1);
                prevEnd = new Date(now.getFullYear(), now.getMonth() - 1, 0, 23, 59, 59);
            } else if (period.value === 'last_3_months') {
                start = new Date(now.getFullYear(), now.getMonth() - 2, 1);
                end = now;
                prevStart = new Date(now.getFullYear(), now.getMonth() - 5, 1);
                prevEnd = new Date(now.getFullYear(), now.getMonth() - 2, 0, 23, 59, 59);
            } else if (period.value === 'this_year') {
                start = new Date(now.getFullYear(), 0, 1);
                end = now;
                prevStart = new Date(now.getFullYear() - 1, 0, 1);
                prevEnd = new Date(now.getFullYear() - 1, now.getMonth(), now.getDate(), 23, 59, 59);
            } else {
                start = customStart.value ? new Date(customStart.value + 'T00:00:00') : new Date(now.getFullYear(), now.getMonth(), 1);
                end = customEnd.value ? new Date(customEnd.value + 'T23:59:59') : now;
                const spanMs = Math.max(0, end - start);
                prevEnd = new Date(start.getTime() - 1000);
                prevStart = new Date(prevEnd.getTime() - spanMs);
            }
            return { start, end, prevStart, prevEnd };
        });

        const rangeLabel = computed(() => `${dateFmt.format(range.value.start)} — ${dateFmt.format(range.value.end)}`);

        const inRange = (dateStr, start, end) => {
            if (!dateStr) return false;
            const d = new Date(dateStr);
            return d >= start && d <= end;
        };

        // ── Agregación por vehículo ──────────────────────────────────────────
        function aggregate(start, end) {
            const byVehicle = {};
            const bump = (vehicleId, key, amount) => {
                if (!byVehicle[vehicleId]) byVehicle[vehicleId] = { maint: 0, fuel: 0, docs: 0 };
                byVehicle[vehicleId][key] += Number(amount || 0);
            };
            maintenances.value.forEach((m) => { if (inRange(m.service_date, start, end)) bump(m.vehicle_id, 'maint', m.total_cost); });
            fuelLogs.value.forEach((f) => { if (inRange(f.refuel_date, start, end)) bump(f.vehicle_id, 'fuel', f.cost); });
            documents.value.forEach((d) => { if (inRange(d.issue_date, start, end)) bump(d.vehicle_id, 'docs', d.cost); });
            return byVehicle;
        }

        const scopedVehicles = computed(() => (
            vehicleFilter.value ? vehicles.value.filter((v) => v.id === Number(vehicleFilter.value)) : vehicles.value
        ));

        const vehicleRows = computed(() => {
            const agg = aggregate(range.value.start, range.value.end);
            const totalAll = Object.values(agg).reduce((s, v) => s + v.maint + v.fuel + v.docs, 0);
            return scopedVehicles.value
                .map((v) => {
                    const a = agg[v.id] || { maint: 0, fuel: 0, docs: 0 };
                    const total = a.maint + a.fuel + a.docs;
                    return { ...v, maint: a.maint, fuel: a.fuel, docs: a.docs, total, pct: totalAll > 0 ? (total / totalAll) * 100 : 0 };
                })
                .filter((row) => row.total > 0 || vehicleFilter.value)
                .sort((x, y) => y.total - x.total);
        });

        const totalPeriod = computed(() => vehicleRows.value.reduce((s, r) => s + r.total, 0));
        const avgPerVehicle = computed(() => (vehicleRows.value.length ? totalPeriod.value / vehicleRows.value.length : 0));

        const prevTotalPeriod = computed(() => {
            const agg = aggregate(range.value.prevStart, range.value.prevEnd);
            return scopedVehicles.value.reduce((s, v) => {
                const a = agg[v.id] || { maint: 0, fuel: 0, docs: 0 };
                return s + a.maint + a.fuel + a.docs;
            }, 0);
        });

        const variationPct = computed(() => {
            const prev = prevTotalPeriod.value;
            if (prev === 0) return totalPeriod.value === 0 ? 0 : 100;
            return ((totalPeriod.value - prev) / prev) * 100;
        });
        const variationText = computed(() => {
            if (prevTotalPeriod.value === 0 && totalPeriod.value === 0) return 'Sin gasto en el período anterior';
            const sign = variationPct.value > 0 ? '+' : '';
            return `${sign}${variationPct.value.toFixed(0)}%`;
        });
        const variationClass = computed(() => (variationPct.value > 0 ? 'text-danger' : (variationPct.value < 0 ? 'text-success' : 'text-muted')));

        // ── Carga ─────────────────────────────────────────────────────────
        onMounted(async () => {
            const safe = (p, fallback) => p.then((r) => r.data).catch(() => fallback);
            const b = props.baseUrl;
            const [vh, mt, fl, dc] = await Promise.all([
                safe(axios.get(`${b}/api/vehiculos`), { vehicles: [] }),
                safe(axios.get(`${b}/api/mantenimientos`), { maintenances: [] }),
                safe(axios.get(`${b}/api/combustible`), { fuel_log: [] }),
                safe(axios.get(`${b}/api/documentos`), { documents: [] }),
            ]);
            vehicles.value = vh?.vehicles ?? [];
            maintenances.value = mt?.maintenances ?? [];
            fuelLogs.value = fl?.fuel_log ?? [];
            documents.value = dc?.documents ?? [];
            loading.value = false;
        });

        return {
            baseUrl: props.baseUrl, loading, vehicles,
            period, vehicleFilter, customStart, customEnd, rangeLabel,
            fmtMoney, vehicleRows, totalPeriod, avgPerVehicle, prevTotalPeriod,
            variationText, variationClass,
        };
    },
};
</script>

<style scoped>
.flt-dash-wrap { max-width: 1280px; margin: 0 auto; padding: 16px 0 40px; }

.flt-dash-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
.flt-dash-title { font-size: 22px; font-weight: 700; margin: 0; }
.flt-dash-sub { color: #6b7280; font-size: 13px; margin: 2px 0 0; }
.flt-dash-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.flt-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.flt-card-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid #f3f4f6; font-weight: 700; font-size: 14px; }
.flt-card-body { padding: 14px 18px; }

.flt-filters { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
.flt-filter-group { min-width: 160px; }
.flt-filter-range { padding-bottom: 6px; }

.flt-metric { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; height: 100%; }
.flt-metric-label { font-size: 12px; color: #6b7280; }
.flt-metric-value { font-size: 26px; font-weight: 700; line-height: 1.2; margin: 4px 0; }
.flt-metric-foot { font-size: 11px; color: #6b7280; }

.flt-cmp-table th { font-size: 11px; text-transform: uppercase; color: #6b7280; border-top: none; }
.flt-cmp-table td { vertical-align: middle; }
</style>

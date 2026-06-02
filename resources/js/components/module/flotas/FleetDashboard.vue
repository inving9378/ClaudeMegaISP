<template>
    <div class="flt-dash-wrap">

        <!-- ── HEADER ──────────────────────────────────────────────────── -->
        <div class="flt-dash-header">
            <div>
                <h4 class="flt-dash-title">Flotas</h4>
                <p class="flt-dash-sub">Resumen general · {{ metrics.active || 0 }} vehículo(s) activo(s)</p>
            </div>
            <div class="flt-dash-actions">
                <a :href="`${baseUrl}/mapa`" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-map me-1"></i>Ver mapa
                </a>
                <button class="btn btn-outline-secondary btn-sm" @click="exportCsv">
                    <i class="bi bi-download me-1"></i>Exportar CSV
                </button>
                <a :href="`${baseUrl}/nuevo`" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Nuevo vehículo
                </a>
            </div>
        </div>

        <div v-if="loading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary mb-2"></div>
            <div>Cargando flota…</div>
        </div>

        <template v-else>
            <!-- ── 5 CARDS MÉTRICAS ────────────────────────────────────── -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg">
                    <div class="flt-metric">
                        <div class="flt-metric-label">Total vehículos</div>
                        <div class="flt-metric-value">{{ metrics.total || 0 }}</div>
                        <div class="flt-metric-foot">{{ gpsCount }} con GPS · {{ noGpsCount }} sin GPS</div>
                    </div>
                </div>
                <div class="col-6 col-lg">
                    <div class="flt-metric">
                        <div class="flt-metric-label">En movimiento</div>
                        <div class="flt-metric-value">0</div>
                        <div class="flt-metric-foot text-muted">Disponible en Fase 2</div>
                    </div>
                </div>
                <div class="col-6 col-lg">
                    <div class="flt-metric flt-metric-red">
                        <div class="flt-metric-label">Alertas críticas</div>
                        <div class="flt-metric-value text-danger">{{ criticalCount }}</div>
                        <div class="flt-metric-foot">Documentos vencidos</div>
                    </div>
                </div>
                <div class="col-6 col-lg">
                    <div class="flt-metric flt-metric-amber">
                        <div class="flt-metric-label">Por vencer 30d</div>
                        <div class="flt-metric-value text-warning">{{ metrics.expiringDocs || 0 }}</div>
                        <div class="flt-metric-foot">Documentos próximos</div>
                    </div>
                </div>
                <div class="col-12 col-lg">
                    <div class="flt-metric">
                        <div class="flt-metric-label">Gasto del mes</div>
                        <div class="flt-metric-value">{{ fmtMoney(spendThisMonth) }}</div>
                        <div class="flt-metric-foot" :class="variationClass">
                            <i :class="['bi', variationIcon, 'me-1']"></i>{{ variationText }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- ── COLUMNA PRINCIPAL ───────────────────────────────── -->
                <div class="col-lg-8">

                    <!-- Requieren atención -->
                    <div class="flt-card mb-4">
                        <div class="flt-card-head">
                            <span><i class="bi bi-bell-fill text-warning me-2"></i>Requieren atención</span>
                            <a v-if="alertas.length > 10" :href="'#'" class="small" @click.prevent="showAllAlerts = !showAllAlerts">
                                {{ showAllAlerts ? 'Ver menos' : 'Ver todas →' }}
                            </a>
                        </div>
                        <div class="flt-card-body">
                            <div v-if="!alertas.length" class="text-muted small py-2">
                                <i class="bi bi-check-circle text-success me-1"></i>Sin alertas. Todo en orden.
                            </div>
                            <div class="flt-alert-row" v-for="(a, i) in visibleAlerts" :key="i">
                                <span class="flt-alert-dot" :class="a.critical ? 'bg-danger' : 'bg-warning'"></span>
                                <i :class="['bi', a.icon, 'flt-alert-icon', a.critical ? 'text-danger' : 'text-warning']"></i>
                                <span class="flex-grow-1">{{ a.text }}</span>
                                <a :href="a.link" class="btn btn-sm btn-outline-primary">Atender</a>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de vehículos -->
                    <div class="flt-card">
                        <div class="flt-card-head flex-wrap gap-2">
                            <span><i class="bi bi-truck me-2"></i>Vehículos</span>
                            <div class="d-flex gap-2 ms-auto">
                                <select class="form-select form-select-sm w-auto" v-model="statusFilter">
                                    <option value="">Todos</option><option value="active">Activos</option>
                                    <option value="in_workshop">En taller</option><option value="inactive">Inactivos</option>
                                </select>
                                <select class="form-select form-select-sm w-auto" v-model="sortBy">
                                    <option value="alerts">Alertas primero</option><option value="km">Por km</option>
                                    <option value="plates">Por placas</option><option value="activity">Última actividad</option>
                                </select>
                            </div>
                        </div>
                        <div class="flt-card-body p-0">
                            <div v-if="!sortedVehicles.length" class="text-muted small p-3">No hay vehículos.</div>
                            <a class="flt-veh-row" v-for="v in sortedVehicles" :key="v.id" :href="`${baseUrl}/${v.id}`">
                                <div class="flt-veh-icon"><i :class="['bi', v.vehicle_type === 'motorcycle' ? 'bi-scooter' : 'bi-truck-front']"></i></div>
                                <div class="flt-veh-main">
                                    <div class="fw-semibold">{{ v.brand }} {{ v.model }} <span class="text-muted fw-normal">{{ v.year || '' }}</span></div>
                                    <div class="flt-veh-meta">
                                        <span><i class="bi bi-credit-card-2-front me-1"></i>{{ v.plates || 'Sin placas' }}</span>
                                        <span><i class="bi bi-speedometer2 me-1"></i>{{ fmtKm(v.current_km) }} km</span>
                                        <span><i class="bi bi-person me-1"></i>{{ operatorOf(v) }}</span>
                                        <span v-if="v.has_gps"><i class="bi bi-broadcast me-1"></i>Sin tracking</span>
                                    </div>
                                </div>
                                <div class="flt-veh-badges">
                                    <span v-if="alertCountOf(v) > 0" class="badge bg-danger">{{ alertCountOf(v) }} alerta(s)</span>
                                    <span class="badge" :class="v.has_gps ? 'bg-secondary' : 'bg-light text-muted border'">
                                        {{ v.has_gps ? 'Detenido' : 'Sin GPS' }}
                                    </span>
                                </div>
                                <div class="flt-veh-loc text-muted">
                                    <div class="small">Sin tracking</div>
                                    <div class="small">— km/h</div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ── COLUMNA LATERAL ─────────────────────────────────── -->
                <div class="col-lg-4">
                    <!-- Mapa rápido -->
                    <div class="flt-card mb-4">
                        <div class="flt-card-head"><span><i class="bi bi-geo-alt me-2"></i>Mapa rápido</span></div>
                        <div class="flt-map-placeholder">
                            <i class="bi bi-map"></i>
                            <p>Mapa con {{ gpsCount }} vehículo(s) GPS</p>
                            <small class="text-muted">Disponible en Fase 2</small>
                        </div>
                    </div>

                    <!-- Gastos del año -->
                    <div class="flt-card">
                        <div class="flt-card-head">
                            <span><i class="bi bi-graph-up me-2"></i>Gastos del año</span>
                            <span class="fw-bold">{{ fmtMoney(yearTotal) }}</span>
                        </div>
                        <div class="flt-card-body">
                            <div class="flt-bar-row" v-for="cat in spendBars" :key="cat.key">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span><i :class="['bi', cat.icon, 'me-1', cat.text]"></i>{{ cat.label }}</span>
                                    <span class="fw-semibold">{{ fmtMoney(cat.value) }}</span>
                                </div>
                                <div class="flt-bar-track">
                                    <div class="flt-bar-fill" :class="cat.bg" :style="{ width: barWidth(cat.value) }"></div>
                                </div>
                            </div>
                            <div v-if="yearTotal === 0" class="text-muted small">Sin gastos registrados este año.</div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Toast -->
        <transition name="flt-toast-fade">
            <div v-if="toast.visible" class="flt-toast" :class="`flt-toast-${toast.type}`">
                <i :class="toast.icon" class="me-2"></i>{{ toast.message }}
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'FleetDashboard',
    props: { baseUrl: { type: String, default: '/flotas' } },
    setup(props) {
        const loading = ref(true);
        const metrics = ref({});
        const alertas = ref([]);
        const vehicles = ref([]);
        const maintenances = ref([]);
        const fuelLogs = ref([]);
        const documents = ref([]);

        const statusFilter = ref('');
        const sortBy = ref('alerts');
        const showAllAlerts = ref(false);

        const toast = ref({ visible: false, message: '', type: 'success', icon: '', timer: null });
        function showToast(message, type = 'success', icon = 'bi bi-check-circle-fill') {
            if (toast.value.timer) clearTimeout(toast.value.timer);
            toast.value = { visible: true, message, type, icon, timer: setTimeout(() => { toast.value.visible = false; }, 4000) };
        }

        // ── Formatters ────────────────────────────────────────────────────
        const moneyFmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
        const fmtMoney = (v) => moneyFmt.format(Number(v || 0));
        const fmtKm = (v) => new Intl.NumberFormat('es-MX').format(Number(v || 0));

        const docTypeLabels = {
            circulation_card: 'Tarjeta de circulación', insurance_policy: 'Póliza de seguro',
            tenencia: 'Tenencia', verification: 'Verificación', operator_license: 'Licencia del operador',
            special_permit: 'Permiso especial', other: 'Documento',
        };

        // ── GPS breakdown ─────────────────────────────────────────────────
        const gpsCount = computed(() => vehicles.value.filter((v) => v.has_gps).length);
        const noGpsCount = computed(() => vehicles.value.filter((v) => !v.has_gps).length);

        // ── Alertas (mapeadas desde endpoint) ─────────────────────────────
        const mappedAlerts = computed(() => alertas.value.map((a) => {
            const days = Number(a.days ?? 0);
            if (a.type === 'document') {
                const critical = a.status === 'vencido' || days < 0;
                const label = docTypeLabels[a.doc_type] || 'Documento';
                const when = critical ? 'vencida' : `vence en ${days} día${days === 1 ? '' : 's'}`;
                return { critical, icon: 'bi-shield-exclamation', text: `${a.vehicle || 'Vehículo'} — ${label} ${when}`, link: `${props.baseUrl}/${a.vehicle_id}?tab=documentos`, days };
            }
            const critical = days < 0;
            return { critical, icon: 'bi-tools', text: `${a.vehicle || 'Vehículo'} — Servicio ${critical ? 'vencido' : `en ${days} días`}`, link: `${props.baseUrl}/${a.vehicle_id}?tab=mantenimientos`, days };
        }).sort((x, y) => x.days - y.days));
        const visibleAlerts = computed(() => (showAllAlerts.value ? mappedAlerts.value : mappedAlerts.value.slice(0, 10)));
        const criticalCount = computed(() => mappedAlerts.value.filter((a) => a.critical).length);

        // ── Vehículos: orden / filtro / alertas ───────────────────────────
        const operatorOf = (v) => {
            const a = v.current_assignment || v.currentAssignment;
            return a?.operator?.name || 'Sin operador';
        };
        const alertCountOf = (v) => Number(v.expired_documents_count || 0) + Number(v.expiring_documents_count || 0);
        const sortedVehicles = computed(() => {
            let list = vehicles.value.slice();
            if (statusFilter.value) list = list.filter((v) => v.status === statusFilter.value);
            const sorters = {
                alerts: (a, b) => alertCountOf(b) - alertCountOf(a),
                km: (a, b) => Number(b.current_km || 0) - Number(a.current_km || 0),
                plates: (a, b) => String(a.plates || '').localeCompare(String(b.plates || '')),
                activity: (a, b) => new Date(b.updated_at || 0) - new Date(a.updated_at || 0),
            };
            return list.sort(sorters[sortBy.value] || sorters.alerts);
        });

        // ── Gasto mes / variación ─────────────────────────────────────────
        const now = new Date();
        const inMonth = (dateStr, offset = 0) => {
            if (!dateStr) return false;
            const d = new Date(dateStr);
            const ref = new Date(now.getFullYear(), now.getMonth() + offset, 1);
            return d.getFullYear() === ref.getFullYear() && d.getMonth() === ref.getMonth();
        };
        const inYear = (dateStr) => dateStr && new Date(dateStr).getFullYear() === now.getFullYear();
        const monthSpend = (offset) =>
            maintenances.value.filter((m) => inMonth(m.service_date, offset)).reduce((s, m) => s + Number(m.total_cost || 0), 0)
            + fuelLogs.value.filter((f) => inMonth(f.refuel_date, offset)).reduce((s, f) => s + Number(f.cost || 0), 0)
            + documents.value.filter((d) => inMonth(d.issue_date, offset)).reduce((s, d) => s + Number(d.cost || 0), 0);
        const spendThisMonth = computed(() => monthSpend(0));
        const spendPrevMonth = computed(() => monthSpend(-1));
        const variationPct = computed(() => {
            const prev = spendPrevMonth.value;
            if (prev === 0) return spendThisMonth.value === 0 ? 0 : 100;
            return ((spendThisMonth.value - prev) / prev) * 100;
        });
        const variationText = computed(() => {
            if (spendPrevMonth.value === 0 && spendThisMonth.value === 0) return 'Sin gasto el mes anterior';
            const sign = variationPct.value > 0 ? '+' : '';
            return `${sign}${variationPct.value.toFixed(0)}% vs mes anterior`;
        });
        const variationClass = computed(() => (variationPct.value > 0 ? 'text-danger' : (variationPct.value < 0 ? 'text-success' : 'text-muted')));
        const variationIcon = computed(() => (variationPct.value > 0 ? 'bi-arrow-up-right' : (variationPct.value < 0 ? 'bi-arrow-down-right' : 'bi-dash')));

        // ── Gastos del año por categoría ──────────────────────────────────
        const yearMaint = computed(() => maintenances.value.filter((m) => inYear(m.service_date)).reduce((s, m) => s + Number(m.total_cost || 0), 0));
        const yearFuel = computed(() => fuelLogs.value.filter((f) => inYear(f.refuel_date)).reduce((s, f) => s + Number(f.cost || 0), 0));
        const yearDocs = computed(() => documents.value.filter((d) => inYear(d.issue_date)).reduce((s, d) => s + Number(d.cost || 0), 0));
        const spendBars = computed(() => [
            { key: 'maint', label: 'Mantenimientos', value: yearMaint.value, icon: 'bi-tools', text: 'text-primary', bg: 'bg-primary' },
            { key: 'fuel', label: 'Combustible', value: yearFuel.value, icon: 'bi-fuel-pump-fill', text: 'text-warning', bg: 'bg-warning' },
            { key: 'docs', label: 'Documentos', value: yearDocs.value, icon: 'bi-file-earmark-text', text: 'text-info', bg: 'bg-info' },
            { key: 'other', label: 'Otros', value: 0, icon: 'bi-three-dots', text: 'text-secondary', bg: 'bg-secondary' },
        ]);
        const yearTotal = computed(() => spendBars.value.reduce((s, c) => s + c.value, 0));
        const barWidth = (v) => (yearTotal.value > 0 ? `${Math.max(2, (v / yearTotal.value) * 100)}%` : '0%');

        // ── Acciones ──────────────────────────────────────────────────────
        function exportCsv() {
            const rows = [['Marca', 'Modelo', 'Año', 'Placas', 'Tipo', 'Estado', 'Km', 'GPS', 'Operador', 'Docs vencidos', 'Docs por vencer']];
            sortedVehicles.value.forEach((v) => rows.push([
                v.brand, v.model, v.year || '', v.plates || '', v.vehicle_type, v.status, v.current_km || 0,
                v.has_gps ? 'Sí' : 'No', operatorOf(v), v.expired_documents_count || 0, v.expiring_documents_count || 0,
            ]));
            const csv = rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `flotas_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            URL.revokeObjectURL(a.href);
            showToast('CSV exportado.', 'success', 'bi bi-download');
        }

        // ── Carga ─────────────────────────────────────────────────────────
        onMounted(async () => {
            const safe = (p, fallback) => p.then((r) => r.data).catch(() => fallback);
            const b = props.baseUrl;
            const [m, al, vh, mt, fl, dc] = await Promise.all([
                safe(axios.get(`${b}/api/vehiculos/data/dashboard`), {}),
                safe(axios.get(`${b}/api/vehiculos/data/alertas`), { alertas: [] }),
                safe(axios.get(`${b}/api/vehiculos`), { vehicles: [] }),
                safe(axios.get(`${b}/api/mantenimientos`), { maintenances: [] }),
                safe(axios.get(`${b}/api/combustible`), { fuel_log: [] }),
                safe(axios.get(`${b}/api/documentos`), { documents: [] }),
            ]);
            metrics.value = m ?? {};
            alertas.value = al?.alertas ?? [];
            vehicles.value = vh?.vehicles ?? [];
            maintenances.value = mt?.maintenances ?? [];
            fuelLogs.value = fl?.fuel_log ?? [];
            documents.value = dc?.documents ?? [];
            loading.value = false;
        });

        return {
            baseUrl: props.baseUrl, loading, metrics, alertas, vehicles, toast,
            statusFilter, sortBy, showAllAlerts,
            fmtMoney, fmtKm,
            gpsCount, noGpsCount, mappedAlerts, visibleAlerts, criticalCount,
            operatorOf, alertCountOf, sortedVehicles,
            spendThisMonth, variationText, variationClass, variationIcon,
            spendBars, yearTotal, barWidth,
            exportCsv,
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

/* Métricas */
.flt-metric { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; height: 100%; }
.flt-metric-red { border-left: 3px solid #ef4444; }
.flt-metric-amber { border-left: 3px solid #f59e0b; }
.flt-metric-label { font-size: 12px; color: #6b7280; }
.flt-metric-value { font-size: 26px; font-weight: 700; line-height: 1.2; margin: 4px 0; }
.flt-metric-foot { font-size: 11px; color: #6b7280; }

/* Cards */
.flt-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.flt-card-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid #f3f4f6; font-weight: 700; font-size: 14px; }
.flt-card-body { padding: 14px 18px; }

/* Alertas */
.flt-alert-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
.flt-alert-row:last-child { border-bottom: none; }
.flt-alert-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.flt-alert-icon { font-size: 16px; }

/* Vehículos */
.flt-veh-row { display: flex; align-items: center; gap: 14px; padding: 14px 18px; border-bottom: 1px solid #f3f4f6; text-decoration: none; color: inherit; transition: background .12s; }
.flt-veh-row:hover { background: #f9fafb; }
.flt-veh-icon { width: 40px; height: 40px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.flt-veh-main { flex-grow: 1; min-width: 0; }
.flt-veh-meta { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; color: #6b7280; margin-top: 2px; }
.flt-veh-badges { display: flex; flex-direction: column; gap: 4px; align-items: flex-end; flex-shrink: 0; }
.flt-veh-loc { text-align: right; flex-shrink: 0; min-width: 70px; }

/* Mapa */
.flt-map-placeholder { text-align: center; padding: 36px 16px; color: #9ca3af; }
.flt-map-placeholder i { font-size: 42px; display: block; margin-bottom: 8px; }
.flt-map-placeholder p { margin: 0; font-weight: 600; color: #6b7280; }

/* Barras de gasto */
.flt-bar-row { margin-bottom: 14px; }
.flt-bar-row:last-child { margin-bottom: 0; }
.flt-bar-track { height: 8px; background: #f3f4f6; border-radius: 5px; overflow: hidden; }
.flt-bar-fill { height: 100%; border-radius: 5px; transition: width .4s; }

/* Toast */
.flt-toast { position: fixed; bottom: 28px; right: 28px; z-index: 9999; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.15); display: flex; align-items: center; color: #fff; }
.flt-toast-success { background: #16a34a; }
.flt-toast-error { background: #dc2626; }
.flt-toast-fade-enter-active, .flt-toast-fade-leave-active { transition: all .25s; }
.flt-toast-fade-enter-from, .flt-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }

@media (max-width: 991px) {
    .flt-veh-loc { display: none; }
}
</style>

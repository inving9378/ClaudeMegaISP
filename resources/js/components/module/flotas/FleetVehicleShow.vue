<template>
    <div class="flt-show-wrap">

        <div v-if="loading" class="flt-center text-muted py-5">
            <div class="spinner-border text-primary mb-2"></div>
            <div>Cargando vehículo…</div>
        </div>

        <div v-else-if="loadError" class="alert alert-danger d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            No se pudo cargar el vehículo. <a :href="baseUrl" class="ms-1">Volver a Flotas</a>
        </div>

        <template v-else-if="vehicle">

            <!-- HEADER -->
            <div class="flt-header">
                <div class="flt-header-icon" :class="`flt-status-${vehicle.status}`">
                    <i :class="['bi', vehicle.vehicle_type === 'motorcycle' ? 'bi-scooter' : 'bi-truck-front']"></i>
                </div>
                <div class="flt-header-main">
                    <h4 class="flt-header-title">
                        {{ vehicle.brand }} {{ vehicle.model }}
                        <span class="text-muted fw-normal" v-if="vehicle.year">{{ vehicle.year }}</span>
                    </h4>
                    <div class="flt-badges">
                        <span class="badge" :class="`flt-badge-${vehicle.status}`">{{ statusLabel(vehicle.status) }}</span>
                        <span class="badge" :class="vehicle.has_gps ? 'flt-badge-gps' : 'flt-badge-nogps'">
                            <i class="bi bi-broadcast me-1"></i>{{ vehicle.has_gps ? 'Con GPS' : 'Sin GPS' }}
                        </span>
                    </div>
                    <div class="flt-header-quick">
                        <span><i class="bi bi-credit-card-2-front me-1"></i>{{ vehicle.plates || 'Sin placas' }}</span>
                        <span><i class="bi bi-person me-1"></i>{{ currentOperatorName || 'Sin operador' }}</span>
                        <span><i class="bi bi-speedometer2 me-1"></i>{{ fmtKm(vehicle.current_km) }} km</span>
                    </div>
                </div>
                <div class="flt-header-actions">
                    <button class="btn btn-sm btn-primary me-2" @click="goTab('informacion')">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </button>
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-secondary" @click.stop="moreOpen = !moreOpen">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" :class="{ show: moreOpen }" @click="moreOpen = false">
                            <li><a class="dropdown-item" href="#" @click.prevent="goTab('fotos')"><i class="bi bi-images me-2"></i>Fotos</a></li>
                            <li><a class="dropdown-item" href="#" @click.prevent="goTab('historial')"><i class="bi bi-clock-history me-2"></i>Historial</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" @click.prevent="openDeleteModal"><i class="bi bi-trash me-2"></i>Eliminar vehículo</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- TARJETAS DE SALUD -->
            <div class="row g-3 flt-health">
                <div class="col-6 col-lg-3">
                    <div class="flt-health-card">
                        <div class="flt-health-icon bg-soft-blue"><i class="bi bi-wrench-adjustable"></i></div>
                        <div><div class="flt-health-label">Próximo servicio</div><div class="flt-health-value">{{ nextServiceText }}</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="flt-health-card">
                        <div class="flt-health-icon" :class="docsAlertCount > 0 ? 'bg-soft-amber' : 'bg-soft-green'"><i class="bi bi-file-earmark-text"></i></div>
                        <div><div class="flt-health-label">Documentos por vencer</div><div class="flt-health-value" :class="docsAlertCount > 0 ? 'text-danger' : ''">{{ docsAlertCount }}</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="flt-health-card">
                        <div class="flt-health-icon bg-soft-blue"><i class="bi bi-tools"></i></div>
                        <div><div class="flt-health-label">Mantenimientos del año</div><div class="flt-health-value">{{ maintYearCount }}</div></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="flt-health-card">
                        <div class="flt-health-icon bg-soft-green"><i class="bi bi-cash-stack"></i></div>
                        <div><div class="flt-health-label">Gasto del año</div><div class="flt-health-value">{{ fmtMoney(spendYear) }}</div></div>
                    </div>
                </div>
            </div>

            <!-- PESTAÑAS -->
            <ul class="nav nav-tabs flt-tabs mt-4">
                <li class="nav-item" v-for="t in tabs" :key="t.key">
                    <a class="nav-link" :class="{ active: activeTab === t.key }" href="#" @click.prevent="goTab(t.key)">
                        <i :class="['bi', t.icon, 'me-1']"></i>{{ t.label }}
                        <span v-if="t.badge" class="badge flt-tab-badge ms-1">{{ t.badge }}</span>
                    </a>
                </li>
            </ul>

            <div class="flt-tab-body">
                <fleet-tab-info
                    v-show="activeTab === 'informacion'"
                    :vehicle="vehicle" :vehicle-id="vehicleId" :base-url="baseUrl"
                    @reload="reloadVehicle" @toast="showToast" />

                <fleet-tab-asignacion
                    v-show="activeTab === 'asignacion'"
                    :vehicle="vehicle" :vehicle-id="vehicleId" :base-url="baseUrl"
                    :assignments="assignments" :operators="operators"
                    @reload="reloadVehicle" @reload-assignments="reloadAssignments" @toast="showToast" />

                <fleet-tab-mantenimientos
                    v-show="activeTab === 'mantenimientos'"
                    :vehicle="vehicle" :vehicle-id="vehicleId" :base-url="baseUrl"
                    :providers="providers"
                    @reload="reloadVehicle" @provider-created="onProviderCreated" @toast="showToast" />

                <fleet-tab-documentos
                    v-show="activeTab === 'documentos'"
                    :vehicle="vehicle" :vehicle-id="vehicleId" :base-url="baseUrl"
                    @reload="reloadVehicle" @toast="showToast" />

                <fleet-tab-combustible
                    v-show="activeTab === 'combustible'"
                    :vehicle="vehicle" :vehicle-id="vehicleId" :base-url="baseUrl"
                    @reload="reloadVehicle" @toast="showToast" />

                <fleet-tab-gps
                    v-show="activeTab === 'gps'"
                    :vehicle="vehicle" :vehicle-id="vehicleId" :base-url="baseUrl"
                    :active="activeTab === 'gps'"
                    @reload="reloadVehicle" @toast="showToast" />

                <fleet-tab-historial
                    v-show="activeTab === 'historial'"
                    :vehicle="vehicle" :assignments="assignments"
                    @go-tab="goTab" />

                <fleet-tab-fotos
                    v-show="activeTab === 'fotos'"
                    :vehicle="vehicle" :vehicle-id="vehicleId" :base-url="baseUrl"
                    @reload="reloadVehicle" @toast="showToast" />
            </div>

            <!-- BARRA FIJA -->
            <div class="flt-fixed-bar">
                <button class="btn btn-outline-danger" @click="openDeleteModal"><i class="bi bi-trash me-1"></i>Eliminar</button>
                <a :href="baseUrl" class="btn btn-outline-secondary">Volver a Flotas</a>
            </div>
        </template>

        <!-- Modal eliminar -->
        <div v-if="showDeleteModal" class="modal fade show flt-delete-modal" tabindex="-1" style="display:block"
             @click.self="!deleting && (showDeleteModal = false)">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>¿Eliminar este vehículo?</h5>
                        <button type="button" class="btn-close" :disabled="deleting" @click="showDeleteModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3" v-if="vehicle">
                            Estás a punto de eliminar <strong>{{ vehicle.brand }} {{ vehicle.model }}</strong>
                            con placas <strong>{{ vehicle.plates || 'sin placas' }}</strong>.
                        </p>
                        <p class="mb-2">Esta acción también marcará como eliminados:</p>
                        <ul class="flt-delete-list">
                            <li><i class="bi bi-tools me-2 text-muted"></i>{{ deleteCounts.maintenances }} mantenimiento(s)</li>
                            <li><i class="bi bi-file-earmark-text me-2 text-muted"></i>{{ deleteCounts.documents }} documento(s)</li>
                            <li><i class="bi bi-person me-2 text-muted"></i>{{ deleteCounts.assignments }} asignación(es)</li>
                            <li><i class="bi bi-fuel-pump me-2 text-muted"></i>{{ deleteCounts.fuel }} carga(s) de combustible</li>
                            <li><i class="bi bi-images me-2 text-muted"></i>{{ deleteCounts.photos }} foto(s)</li>
                        </ul>
                        <div class="alert alert-light border small mb-0 mt-3">
                            <i class="bi bi-info-circle me-1"></i>Los datos quedan en BD (soft delete).
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" :disabled="deleting" @click="showDeleteModal = false">Cancelar</button>
                        <button class="btn btn-danger" :disabled="deleting" @click="executeDelete">
                            <i class="bi bi-trash me-1"></i>{{ deleting ? 'Eliminando…' : 'Sí, eliminar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showDeleteModal" class="modal-backdrop fade show"></div>

        <!-- Toast -->
        <transition name="flt-toast-fade">
            <div v-if="toast.visible" class="flt-toast" :class="`flt-toast-${toast.type}`">
                <i :class="toast.icon" class="me-2"></i>{{ toast.message }}
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { useFleetFormatters } from './useFleetFormatters.js';

export default {
    name: 'FleetVehicleShow',
    props: {
        baseUrl: { type: String, default: '/flotas' },
    },
    setup(props) {
        const { fmtMoney, fmtKm, statusLabel, docStatus } = useFleetFormatters();

        const path      = window.location.pathname.split('/').filter(Boolean);
        const vehicleId = path[path.length - 1];
        const queryTab  = new URLSearchParams(window.location.search).get('tab');
        const tabKeys   = ['informacion', 'asignacion', 'mantenimientos', 'documentos', 'combustible', 'gps', 'historial', 'fotos'];
        const activeTab = ref(tabKeys.includes(queryTab) ? queryTab : 'informacion');
        const moreOpen  = ref(false);

        function goTab(k) {
            activeTab.value = k;
            const url = new URL(window.location);
            url.searchParams.set('tab', k);
            window.history.replaceState({}, '', url);
        }

        const loading   = ref(true);
        const loadError = ref(false);
        const vehicle   = ref(null);
        const assignments = ref([]);
        const operators   = ref([]);
        const providers   = ref([]);

        const toast = ref({ visible: false, message: '', type: 'success', icon: '', timer: null });
        function showToast({ message, type = 'success' }) {
            if (toast.value.timer) clearTimeout(toast.value.timer);
            const icon = type === 'success' ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-circle-fill';
            toast.value = { visible: true, message, type, icon, timer: setTimeout(() => { toast.value.visible = false; }, 4000) };
        }

        const thisYear = new Date().getFullYear();
        const maintenances = computed(() => vehicle.value?.maintenances ?? []);
        const maintYearCount = computed(() => maintenances.value.filter((m) => m.service_date && new Date(m.service_date).getFullYear() === thisYear).length);
        const spendYear = computed(() => maintenances.value.filter((m) => m.service_date && new Date(m.service_date).getFullYear() === thisYear).reduce((s, m) => s + Number(m.total_cost || 0), 0));

        const nextServiceRecord = computed(() => {
            const w = maintenances.value.filter((m) => m.next_service_date || m.next_service_km);
            return w.length ? w.slice().sort((a, b) => new Date(b.service_date) - new Date(a.service_date))[0] : null;
        });
        const nextServiceText = computed(() => {
            const r = nextServiceRecord.value;
            if (!r) return '—';
            if (r.next_service_km) { const rem = r.next_service_km - Number(vehicle.value?.current_km || 0); return rem > 0 ? `${fmtKm(rem)} km` : 'Vencido'; }
            if (r.next_service_date) return new Date(r.next_service_date).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
            return '—';
        });
        const docsAlertCount = computed(() => (vehicle.value?.documents ?? []).filter((d) => { const s = docStatus(d); return s === 'vencido' || s === 'por_vencer'; }).length);
        const currentOperatorName = computed(() => (vehicle.value?.current_assignment || vehicle.value?.currentAssignment)?.operator?.name || null);

        const tabs = computed(() => [
            { key: 'informacion',    label: 'Información',    icon: 'bi-info-circle' },
            { key: 'asignacion',     label: 'Asignación',     icon: 'bi-person-badge' },
            { key: 'mantenimientos', label: 'Mantenimientos', icon: 'bi-tools',             badge: maintenances.value.length || null },
            { key: 'documentos',     label: 'Documentos',     icon: 'bi-file-earmark-text', badge: docsAlertCount.value || null },
            { key: 'combustible',    label: 'Combustible',    icon: 'bi-fuel-pump' },
            { key: 'gps',            label: 'Tracking GPS',   icon: 'bi-broadcast' },
            { key: 'historial',      label: 'Historial',      icon: 'bi-clock-history' },
            { key: 'fotos',          label: 'Fotos',          icon: 'bi-images' },
        ]);

        const showDeleteModal = ref(false);
        const deleting = ref(false);
        const deleteCounts = computed(() => ({
            maintenances: (vehicle.value?.maintenances ?? []).length,
            documents:    (vehicle.value?.documents ?? []).length,
            assignments:  assignments.value.length,
            fuel:         (vehicle.value?.fuel_log ?? []).length,
            photos:       (vehicle.value?.photos ?? []).length,
        }));
        function openDeleteModal() { moreOpen.value = false; showDeleteModal.value = true; }
        async function executeDelete() {
            deleting.value = true;
            try {
                await axios.delete(`${props.baseUrl}/api/vehiculos/${vehicleId}`);
                showToast({ message: 'Vehículo eliminado correctamente.' });
                setTimeout(() => { window.location.href = props.baseUrl; }, 800);
            } catch (e) {
                showToast({ message: e?.response?.data?.message || 'No se pudo eliminar.', type: 'error' });
                deleting.value = false;
            }
        }

        async function reloadVehicle() {
            const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${vehicleId}`);
            vehicle.value = data.vehicle;
        }
        async function reloadAssignments() {
            const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${vehicleId}/asignaciones`).catch(() => ({ data: { assignments: [] } }));
            assignments.value = data?.assignments ?? [];
        }
        function onProviderCreated(provider) { providers.value.push(provider); }

        onMounted(async () => {
            try {
                await reloadVehicle();
                await Promise.all([
                    reloadAssignments(),
                    axios.get(`${props.baseUrl}/api/vehiculos/data/operadores`).catch(() => ({ data: { users: [] } })).then(({ data }) => { operators.value = data?.users ?? []; }),
                    axios.get(`${props.baseUrl}/api/proveedores`).catch(() => ({ data: { providers: [] } })).then(({ data }) => { providers.value = data?.providers ?? []; }),
                ]);
            } catch { loadError.value = true; }
            finally { loading.value = false; }
        });

        return {
            vehicleId, baseUrl: props.baseUrl,
            loading, loadError, vehicle, assignments, operators, providers,
            activeTab, tabs, goTab, moreOpen,
            toast, showToast,
            statusLabel, fmtMoney, fmtKm,
            maintYearCount, spendYear, nextServiceText, docsAlertCount, currentOperatorName,
            showDeleteModal, deleting, deleteCounts, openDeleteModal, executeDelete,
            reloadVehicle, reloadAssignments, onProviderCreated,
        };
    },
};
</script>

<style>
.flt-show-wrap { max-width: 1200px; margin: 0 auto; padding: 16px 0 90px; }
.flt-center { text-align: center; }
.flt-section-title { font-size: 14px; font-weight: 700; color: #374151; }
.flt-header { display: flex; align-items: center; gap: 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 20px; }
.flt-header-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 26px; color: #fff; background: #6b7280; flex-shrink: 0; }
.flt-header-icon.flt-status-active { background: #2563eb; }
.flt-header-icon.flt-status-in_workshop { background: #d97706; }
.flt-header-main { flex-grow: 1; min-width: 0; }
.flt-header-title { font-size: 19px; font-weight: 700; margin: 0 0 4px; }
.flt-badges { display: flex; gap: 6px; margin-bottom: 6px; flex-wrap: wrap; }
.flt-header-quick { display: flex; gap: 16px; flex-wrap: wrap; font-size: 13px; color: #6b7280; }
.flt-header-actions { flex-shrink: 0; }
.badge.flt-badge-active, .badge.flt-badge-gps { background: #16a34a; color: #fff; font-weight: 600; }
.badge.flt-badge-in_workshop { background: #d97706; color: #fff; font-weight: 600; }
.badge.flt-badge-inactive, .badge.flt-badge-nogps { background: #9ca3af; color: #fff; font-weight: 600; }
.flt-health { margin-top: 16px; }
.flt-health-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; height: 100%; }
.flt-health-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.bg-soft-blue { background: #dbeafe; color: #2563eb; }
.bg-soft-green { background: #dcfce7; color: #16a34a; }
.bg-soft-amber { background: #fef3c7; color: #d97706; }
.flt-health-label { font-size: 12px; color: #6b7280; }
.flt-health-value { font-size: 18px; font-weight: 700; }
.flt-tabs .nav-link { color: #6b7280; font-weight: 600; font-size: 13px; cursor: pointer; }
.flt-tabs .nav-link.active { color: #2563eb; }
.flt-tab-badge { background: #ef4444; color: #fff; font-size: 10px; }
.flt-tab-body { background: #fff; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 12px 12px; padding: 22px; }
.flt-info-label { font-size: 12px; color: #6b7280; }
.flt-info-value { font-size: 14px; font-weight: 600; color: #1f2937; }
.flt-gps-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; }
.flt-inline-form { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; }
.flt-banner { border-radius: 10px; padding: 12px 16px; font-size: 13px; font-weight: 600; display: flex; align-items: center; }
.flt-banner-blue { background: #dbeafe; color: #1e40af; }
.flt-banner-red { background: #fee2e2; color: #b91c1c; }
.flt-mini-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; height: 100%; }
.flt-mini-label { font-size: 11px; color: #6b7280; }
.flt-mini-value { font-size: 17px; font-weight: 700; }
.flt-mini-red { background: #fef2f2; border-color: #fecaca; }
.flt-mini-amber { background: #fffbeb; border-color: #fde68a; }
.flt-mini-green { background: #f0fdf4; border-color: #bbf7d0; }
.flt-list-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
.flt-list-icon { width: 34px; height: 34px; border-radius: 8px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.flt-doc-item { display: flex; align-items: flex-start; gap: 12px; padding: 14px; border-radius: 10px; margin-bottom: 10px; border: 1px solid #e5e7eb; background: #fff; }
.flt-doc-vencido { background: #fef2f2; border-color: #ef4444; }
.flt-doc-por_vencer { background: #fffbeb; border-color: #f59e0b; }
.flt-timeline { position: relative; padding-left: 20px; }
.flt-timeline::before { content: ''; position: absolute; left: 5px; top: 4px; bottom: 4px; width: 2px; background: #e5e7eb; }
.flt-tl-item { position: relative; padding: 0 0 18px 16px; }
.flt-tl-dot { position: absolute; left: -19px; top: 4px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; }
.flt-current-assign { display: flex; align-items: center; gap: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 16px; }
.flt-avatar { width: 42px; height: 42px; border-radius: 50%; background: #dbeafe; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.flt-drop { border: 2px dashed #cbd5e1; border-radius: 10px; padding: 18px; text-align: center; color: #6b7280; font-size: 13px; cursor: pointer; transition: all .15s; }
.flt-drop:hover, .flt-drop-over { border-color: #2563eb; background: #eff6ff; color: #2563eb; }
.flt-file-list { list-style: none; padding: 0; margin: 0; font-size: 13px; }
.flt-file-list li { display: flex; align-items: center; padding: 3px 0; }
.flt-file-rm { margin-left: auto; cursor: pointer; color: #ef4444; }
.flt-empty { text-align: center; padding: 40px 20px; }
.flt-empty i { font-size: 48px; color: #cbd5e1; display: block; margin-bottom: 12px; }
.flt-empty h6 { font-weight: 700; }
.flt-photo-slot { border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
.flt-photo-slot-head { background: #f9fafb; padding: 6px 10px; font-size: 12px; font-weight: 600; color: #6b7280; }
.flt-photo-thumb { position: relative; height: 120px; cursor: pointer; background: #f3f4f6; display: flex; align-items: center; justify-content: center; }
.flt-photo-thumb img { width: 100%; height: 100%; object-fit: cover; }
.flt-photo-thumb.flt-photo-broken::after { content: '\F8C9'; font-family: 'bootstrap-icons'; font-size: 28px; color: #cbd5e1; }
.flt-photo-del { position: absolute; top: 6px; right: 6px; border: none; background: rgba(0,0,0,.55); color: #fff; border-radius: 6px; width: 28px; height: 28px; }
.flt-photo-empty { height: 120px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9ca3af; cursor: pointer; background: #f9fafb; }
.flt-photo-empty i { font-size: 24px; }
.flt-zoom { position: fixed; inset: 0; background: rgba(0,0,0,.8); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 30px; }
.flt-zoom img { max-width: 90%; max-height: 90%; border-radius: 8px; }
.flt-gps-mapbox { position: relative; height: 420px; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.flt-gps-map { width: 100%; height: 100%; }
.flt-gps-overlay { position: absolute; top: 10px; right: 10px; z-index: 500; display: flex; align-items: center; gap: 8px; }
.flt-gps-errbar { position: absolute; top: 10px; left: 50%; transform: translateX(-50%); z-index: 500; background: #fef2f2; color: #b91c1c; padding: 5px 12px; border-radius: 8px; font-size: .82rem; }
.flt-gps-ranges { display: flex; flex-wrap: wrap; align-items: center; }
.flt-gps-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; margin-bottom: 12px; }
.flt-gps-card h6 { font-weight: 700; font-size: .9rem; margin-bottom: 10px; }
.flt-gps-row { display: flex; justify-content: space-between; align-items: center; padding: 4px 0; font-size: .88rem; }
.flt-gps-row span { color: #6b7280; }
.flt-geo-events .flt-geo-ev-list { display: flex; flex-direction: column; gap: 4px; max-height: 280px; overflow-y: auto; }
.flt-geo-ev { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; background: #fff; border: 1px solid #eef0f3; border-radius: 8px; padding: 8px 10px; font-size: .88rem; cursor: pointer; }
.flt-geo-ev:hover { background: #f8fafc; }
.flt-geo-ev.disabled { cursor: default; opacity: .7; }
.flt-geo-ev-time { flex-shrink: 0; white-space: nowrap; }
.spin { display: inline-block; animation: flt-spin 1s linear infinite; }
@keyframes flt-spin { to { transform: rotate(360deg); } }
.flt-delete-modal { z-index: 9999; }
.flt-delete-modal .modal-content { border: none; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
.modal-backdrop.show { z-index: 9998; opacity: .5; }
.flt-delete-list { list-style: none; padding: 0; margin: 0; }
.flt-delete-list li { padding: 4px 0; font-size: .9rem; }
.flt-fixed-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; border-top: 1px solid #e5e7eb; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; z-index: 1000; box-shadow: 0 -2px 10px rgba(0,0,0,.05); }
.flt-toast { position: fixed; bottom: 80px; right: 28px; z-index: 10001; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.15); display: flex; align-items: center; color: #fff; }
.flt-toast-success { background: #16a34a; }
.flt-toast-error { background: #dc2626; }
.flt-toast-fade-enter-active, .flt-toast-fade-leave-active { transition: all .25s; }
.flt-toast-fade-enter-from, .flt-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }
.dropdown-menu.show { display: block; }
</style>

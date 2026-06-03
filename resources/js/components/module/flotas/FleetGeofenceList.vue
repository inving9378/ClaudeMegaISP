<template>
    <div class="flt-geo-wrap">

        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h4 class="mb-0"><i class="bi bi-bounding-box-circles me-2 text-primary"></i>Geocercas</h4>
            <a :href="`${baseUrl}/geocercas/nueva`" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nueva geocerca
            </a>
        </div>

        <!-- Métricas -->
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="flt-geo-metric"><div class="flt-geo-metric-icon bg-soft-blue"><i class="bi bi-bounding-box"></i></div>
                    <div><div class="flt-geo-metric-label">Total</div><div class="flt-geo-metric-value">{{ stats.total }}</div></div></div>
            </div>
            <div class="col-md-4">
                <div class="flt-geo-metric"><div class="flt-geo-metric-icon bg-soft-green"><i class="bi bi-check-circle"></i></div>
                    <div><div class="flt-geo-metric-label">Activas</div><div class="flt-geo-metric-value">{{ stats.active }}</div></div></div>
            </div>
            <div class="col-md-4">
                <div class="flt-geo-metric"><div class="flt-geo-metric-icon bg-soft-amber"><i class="bi bi-truck"></i></div>
                    <div><div class="flt-geo-metric-label">Vehículos asignados</div><div class="flt-geo-metric-value">{{ stats.assigned_vehicles }}</div></div></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <div class="flex-grow-1" style="min-width:200px">
                <input class="form-control form-control-sm" placeholder="Buscar por nombre…" v-model="search" />
            </div>
            <select class="form-select form-select-sm w-auto" v-model="typeFilter">
                <option value="">Todos los tipos</option>
                <option value="enter">Entrada</option>
                <option value="exit">Salida</option>
                <option value="both">Ambos</option>
            </select>
            <select class="form-select form-select-sm w-auto" v-model="activeFilter">
                <option value="all">Todas</option>
                <option value="true">Activas</option>
                <option value="false">Inactivas</option>
            </select>
            <select class="form-select form-select-sm w-auto" v-model="vehicleFilter">
                <option value="">Cualquier vehículo</option>
                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.display_name }}</option>
            </select>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary mb-2"></div>
            <div>Cargando geocercas…</div>
        </div>

        <!-- Empty state -->
        <div v-else-if="!geofences.length" class="flt-geo-empty">
            <i class="bi bi-bounding-box-circles"></i>
            <h6>No hay geocercas creadas</h6>
            <p class="text-muted">Crea zonas en el mapa para tu flota (oficinas, zonas de cobertura, etc.).</p>
            <a :href="`${baseUrl}/geocercas/nueva`" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Crear primera geocerca
            </a>
        </div>

        <!-- Lista -->
        <div v-else class="flt-geo-list">
            <div class="flt-geo-item" v-for="g in geofences" :key="g.id">
                <div class="flt-geo-swatch" :style="{ background: g.color }">
                    <i class="bi bi-bounding-box"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate">{{ g.name }}</div>
                    <div class="text-muted small text-truncate" v-if="g.description">{{ g.description }}</div>
                    <div class="flt-geo-badges mt-1">
                        <span class="badge" :class="typeBadge(g.type)">{{ typeLabel(g.type) }}</span>
                        <span class="badge" :class="g.active ? 'bg-success' : 'bg-secondary'">{{ g.active ? 'Activa' : 'Inactiva' }}</span>
                        <span class="badge bg-light text-dark border"><i class="bi bi-truck me-1"></i>{{ g.vehicles_count }} vehículo(s)</span>
                    </div>
                </div>
                <div class="d-flex gap-1 align-items-center">
                    <a :href="`${baseUrl}/geocercas/${g.id}/editar`" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i>Ver/Editar
                    </a>
                    <button class="btn btn-sm btn-outline-danger" @click="askDelete(g)"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </div>

        <!-- Modal eliminar -->
        <div v-if="toDelete" class="modal fade show flt-geo-modal" tabindex="-1" style="display:block" @click.self="!deleting && (toDelete = null)">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>¿Eliminar geocerca?</h5>
                        <button type="button" class="btn-close" :disabled="deleting" @click="toDelete = null"></button>
                    </div>
                    <div class="modal-body">
                        Estás a punto de eliminar <strong>{{ toDelete.name }}</strong>.
                        Se quitará la asignación a sus {{ toDelete.vehicles_count }} vehículo(s).
                        Los datos quedan en base de datos (soft delete).
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" :disabled="deleting" @click="toDelete = null">Cancelar</button>
                        <button class="btn btn-danger" :disabled="deleting" @click="confirmDelete">
                            <i class="bi bi-trash me-1"></i>{{ deleting ? 'Eliminando…' : 'Sí, eliminar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="toDelete" class="modal-backdrop fade show"></div>

        <!-- Toast -->
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
    name: 'FleetGeofenceList',
    props: { baseUrl: { type: String, default: '/flotas' } },
    setup(props) {
        const geofences = ref([]);
        const vehicles = ref([]);
        const stats = reactive({ total: 0, active: 0, assigned_vehicles: 0 });
        const loading = ref(true);

        const search = ref('');
        const typeFilter = ref('');
        const activeFilter = ref('all');
        const vehicleFilter = ref('');

        const toDelete = ref(null);
        const deleting = ref(false);

        const toast = reactive({ visible: false, message: '', type: 'success', icon: '' });
        function notify(message, type = 'success') {
            toast.message = message; toast.type = type;
            toast.icon = type === 'success' ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-circle-fill';
            toast.visible = true;
            setTimeout(() => { toast.visible = false; }, 3500);
        }

        const typeLabel = (t) => ({ enter: 'Entrada', exit: 'Salida', both: 'Ambos' }[t] || t);
        const typeBadge = (t) => ({ enter: 'bg-info', exit: 'bg-warning text-dark', both: 'bg-primary' }[t] || 'bg-secondary');

        let debounce = null;
        function load() {
            loading.value = true;
            const params = {};
            if (search.value) params.search = search.value;
            if (typeFilter.value) params.type = typeFilter.value;
            if (activeFilter.value !== 'all') params.active = activeFilter.value;
            if (vehicleFilter.value) params.vehicle_id = vehicleFilter.value;

            axios.get(`${props.baseUrl}/api/geocercas`, { params })
                .then(({ data }) => {
                    geofences.value = data?.geofences ?? [];
                    const s = data?.stats ?? {};
                    stats.total = s.total ?? 0;
                    stats.active = s.active ?? 0;
                    stats.assigned_vehicles = s.assigned_vehicles ?? 0;
                })
                .catch(() => notify('No se pudieron cargar las geocercas.', 'error'))
                .finally(() => { loading.value = false; });
        }

        function loadVehicles() {
            axios.get(`${props.baseUrl}/api/vehiculos`)
                .then(({ data }) => { vehicles.value = data?.vehicles ?? []; })
                .catch(() => { vehicles.value = []; });
        }

        watch([typeFilter, activeFilter, vehicleFilter], load);
        watch(search, () => { if (debounce) clearTimeout(debounce); debounce = setTimeout(load, 300); });

        function askDelete(g) { toDelete.value = g; }
        function confirmDelete() {
            if (!toDelete.value) return;
            deleting.value = true;
            axios.delete(`${props.baseUrl}/api/geocercas/${toDelete.value.id}`)
                .then(() => { notify('Geocerca eliminada.'); toDelete.value = null; load(); })
                .catch((e) => notify(e?.response?.data?.message || 'No se pudo eliminar.', 'error'))
                .finally(() => { deleting.value = false; });
        }

        onMounted(() => { load(); loadVehicles(); });

        return {
            geofences, vehicles, stats, loading, search, typeFilter, activeFilter, vehicleFilter,
            toDelete, deleting, toast, typeLabel, typeBadge, askDelete, confirmDelete,
        };
    },
};
</script>

<style scoped>
.flt-geo-wrap { padding: 4px; }
.flt-geo-metric { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px 16px; }
.flt-geo-metric-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
.flt-geo-metric-label { font-size: .8rem; color: #6b7280; }
.flt-geo-metric-value { font-size: 1.5rem; font-weight: 700; }
.bg-soft-blue { background: #e0f2fe; color: #0284c7; }
.bg-soft-green { background: #dcfce7; color: #16a34a; }
.bg-soft-amber { background: #fef3c7; color: #d97706; }
.flt-geo-list { display: flex; flex-direction: column; gap: 8px; }
.flt-geo-item { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 16px; }
.flt-geo-item:hover { border-color: #cbd5e1; }
.flt-geo-swatch { width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.2rem; text-shadow: 0 1px 2px rgba(0,0,0,.3); }
.flt-geo-badges { display: flex; flex-wrap: wrap; gap: 6px; }
.min-w-0 { min-width: 0; }
.flt-geo-empty { text-align: center; padding: 60px 20px; background: #fff; border: 1px dashed #cbd5e1; border-radius: 14px; }
.flt-geo-empty i { font-size: 3rem; color: #cbd5e1; display: block; margin-bottom: 10px; }
.flt-geo-modal { z-index: 9999; }
.flt-geo-modal .modal-content { border: none; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
.modal-backdrop.show { z-index: 9998; opacity: .5; }
.flt-toast { position: fixed; bottom: 24px; right: 24px; z-index: 10001; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.15); display: flex; align-items: center; color: #fff; }
.flt-toast-success { background: #16a34a; }
.flt-toast-error { background: #dc2626; }
.flt-toast-fade-enter-active, .flt-toast-fade-leave-active { transition: all .25s; }
.flt-toast-fade-enter-from, .flt-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }
</style>

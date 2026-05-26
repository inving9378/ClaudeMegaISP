<template>
    <div class="megafamilia-planes mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-th-large me-2 text-primary"></i>
                Planes MegaFamilia
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch">
                    <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                </button>
                <button id="btn-nuevo-plan" class="btn btn-sm btn-primary" @click="openCreate">
                    <i class="fa fa-plus me-1"></i> Nuevo Plan
                </button>
            </div>
        </div>

        <div v-if="loading && plans.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="alert alert-danger small">{{ errorMsg }}</div>

        <div class="row mf-planes-grid">
            <div v-for="plan in plans" :key="plan.id" class="col-lg-4 col-md-6 mb-3">
                <div class="card plan-card h-100" :class="planClass(plan.slug)">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h4 class="card-title m-0">{{ plan.name }}</h4>
                            <span
                                class="badge mf-plan-toggle"
                                :class="plan.active ? 'bg-success' : 'bg-danger'"
                                role="button"
                                :title="plan.active ? 'Click para desactivar' : 'Click para activar'"
                                @click="toggleActive(plan)">
                                {{ plan.active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>

                        <p v-if="plan.description" class="text-muted small mb-2">{{ plan.description }}</p>

                        <div class="price">
                            ${{ Number(plan.price_monthly).toFixed(0) }}<span class="period"> /{{ plan.period === 'yearly' ? 'año' : 'mes' }}</span>
                        </div>

                        <ul class="list-unstyled limits mt-3">
                            <li><i class="fa fa-child me-2 text-info"></i> {{ formatLimit(plan.max_children, 'hijos') }}</li>
                            <li><i class="fa fa-mobile-alt me-2 text-info"></i> {{ formatLimit(plan.max_devices, 'dispositivos') }}</li>
                            <li><i class="fa fa-user-shield me-2 text-info"></i> {{ formatLimit(plan.max_parents, 'padres') }}</li>
                        </ul>

                        <div class="features-chips mt-2">
                            <span
                                v-for="(enabled, key) in plan.features || {}"
                                :key="key"
                                class="feature-chip"
                                :class="{ on: enabled }">
                                <i class="fa fa-check me-1" v-if="enabled"></i>
                                <i class="fa fa-times me-1" v-else></i>
                                {{ featureLabel(key) }}
                            </span>
                        </div>

                        <div class="licenses-count mt-3 text-muted small">
                            <i class="fa fa-key me-1"></i>
                            {{ plan.licenses_count || 0 }} licencias vendidas
                        </div>

                        <div class="d-flex gap-2 mt-auto pt-3">
                            <button class="btn btn-outline-primary flex-grow-1" @click="openEdit(plan)">
                                <i class="fa fa-edit"></i> Editar
                            </button>
                            <button
                                class="btn btn-outline-danger"
                                :disabled="(plan.licenses_count || 0) > 0"
                                :title="(plan.licenses_count || 0) > 0 ? 'No se puede eliminar: tiene licencias asociadas' : 'Eliminar'"
                                @click="confirmDelete(plan)">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="!loading && plans.length === 0" class="col-12">
                <div class="text-center text-muted py-5">
                    <i class="fa fa-folder-open fa-3x mb-3 d-block"></i>
                    No hay planes registrados. Crea uno con el botón "Nuevo Plan".
                </div>
            </div>
        </div>

        <button @click="launchTour" class="btn-tour-help" title="Tour guiado">
            <i class="fas fa-question-circle"></i>
        </button>

        <!-- Modal crear/editar -->
        <div v-if="editing" class="mf-modal-overlay" @click.self="closeForm">
            <div class="mf-modal-panel">
                <div class="mf-modal-header">
                    <h5 class="m-0">{{ editing.id ? 'Editar plan' : 'Nuevo plan' }}</h5>
                    <button class="btn-close" @click="closeForm"></button>
                </div>
                <div class="mf-modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input v-model="editing.name" type="text" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Periodo</label>
                            <select v-model="editing.period" class="form-select">
                                <option value="monthly">Mensual</option>
                                <option value="yearly">Anual</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea v-model="editing.description" rows="2" class="form-control"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Precio mensual (MXN) <span class="text-danger">*</span></label>
                            <input v-model.number="editing.price_monthly" type="number" min="0" step="0.01" class="form-control" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Precio anual (MXN)</label>
                            <input v-model.number="editing.price_yearly" type="number" min="0" step="0.01" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max hijos (0 = ilimitado)</label>
                            <input v-model.number="editing.max_children" type="number" min="0" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max dispositivos</label>
                            <input v-model.number="editing.max_devices" type="number" min="0" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max padres</label>
                            <input v-model.number="editing.max_parents" type="number" min="0" class="form-control" />
                        </div>
                        <div class="col-12">
                            <label class="form-label">Estado</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="plan-active" v-model="editing.active" />
                                <label class="form-check-label" for="plan-active">
                                    {{ editing.active ? 'Activo' : 'Inactivo' }}
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-2">Features incluidas</label>
                            <div class="row g-2">
                                <div class="col-md-6 col-lg-4" v-for="key in FEATURE_KEYS" :key="key">
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            :id="`feat-${key}`"
                                            :checked="!!(editing.features && editing.features[key])"
                                            @change="toggleFeature(key, $event.target.checked)" />
                                        <label class="form-check-label" :for="`feat-${key}`">
                                            {{ featureLabel(key) }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mf-modal-footer">
                    <button class="btn btn-outline-secondary" @click="closeForm">Cancelar</button>
                    <button class="btn btn-primary" :disabled="saving" @click="save">
                        <i class="fa fa-save me-1"></i>
                        {{ saving ? 'Guardando…' : (editing.id ? 'Guardar cambios' : 'Crear plan') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { startTour, shouldShowTour } from './MegaFamiliaTour.js';

const FEATURE_KEYS = [
    'ubicacion', 'geofences', 'reportes_avanzados', 'tareas', 'web_filter',
    'control_pantalla', 'limites_diarios', 'bloqueo_apps', 'gps',
    'mikrotik', 'ia', 'soporte_prioritario',
];

const FEATURE_LABELS = {
    ubicacion: 'Ubicación',
    geofences: 'Geofences',
    reportes_avanzados: 'Reportes avanzados',
    tareas: 'Tareas y recompensas',
    web_filter: 'Filtro web',
    control_pantalla: 'Control de pantalla',
    limites_diarios: 'Límites diarios',
    bloqueo_apps: 'Bloqueo de apps',
    bloqueo_web: 'Bloqueo web',
    tareas_recompensas: 'Tareas y recompensas',
    gps: 'GPS',
    mikrotik: 'Integración MikroTik',
    ia: 'IA',
    soporte_prioritario: 'Soporte prioritario',
};

function blankPlan() {
    const features = {};
    for (const k of FEATURE_KEYS) features[k] = false;
    return {
        id: null,
        name: '',
        description: '',
        price_monthly: 0,
        price_yearly: 0,
        period: 'monthly',
        max_children: 0,
        max_devices: 0,
        max_parents: 1,
        active: true,
        features,
    };
}

export default {
    name: 'MegaFamiliaPlanes',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            plans: [],
            loading: false,
            errorMsg: '',
            editing: null,
            saving: false,
            FEATURE_KEYS,
        };
    },
    mounted() {
        this.fetch();
        if (shouldShowTour('planes')) setTimeout(() => startTour('planes'), 1200);
    },
    methods: {
        launchTour() { startTour('planes'); },
        async fetch() {
            this.loading = true;
            this.errorMsg = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/planes/data`);
                this.plans = data.plans || [];
            } catch (e) {
                this.errorMsg = e.response?.data?.message || e.message;
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editing = blankPlan();
        },
        openEdit(plan) {
            const copy = JSON.parse(JSON.stringify(plan));
            copy.features = copy.features || {};
            for (const k of FEATURE_KEYS) {
                if (!(k in copy.features)) copy.features[k] = false;
            }
            this.editing = copy;
        },
        closeForm() { this.editing = null; },
        toggleFeature(key, checked) {
            if (!this.editing.features) this.editing.features = {};
            this.editing.features[key] = !!checked;
        },
        async save() {
            if (!this.editing.name || this.editing.price_monthly == null) {
                alert('Nombre y precio mensual son obligatorios.');
                return;
            }
            this.saving = true;
            try {
                const payload = {
                    name: this.editing.name,
                    description: this.editing.description,
                    price_monthly: this.editing.price_monthly,
                    price_yearly: this.editing.price_yearly,
                    period: this.editing.period,
                    max_children: this.editing.max_children,
                    max_devices: this.editing.max_devices,
                    max_parents: this.editing.max_parents,
                    active: !!this.editing.active,
                    features: this.editing.features,
                };
                const headers = { 'X-CSRF-TOKEN': this.csrfToken };
                if (this.editing.id) {
                    await axios.put(`${this.baseUrl}/planes/${this.editing.id}`, payload, { headers });
                } else {
                    await axios.post(`${this.baseUrl}/planes`, payload, { headers });
                }
                this.closeForm();
                await this.fetch();
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            } finally {
                this.saving = false;
            }
        },
        async toggleActive(plan) {
            try {
                const { data } = await axios.post(
                    `${this.baseUrl}/planes/${plan.id}/toggle`, {},
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } },
                );
                plan.active = !!data.active;
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            }
        },
        async confirmDelete(plan) {
            if (!confirm(`¿Eliminar el plan "${plan.name}"? Esta acción no se puede deshacer.`)) return;
            try {
                await axios.delete(`${this.baseUrl}/planes/${plan.id}`, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                await this.fetch();
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            }
        },
        formatLimit(n, label) {
            if (n === 0 || n === null || n === undefined) return `${label} ilimitados`;
            return `${n} ${label}`;
        },
        planClass(slug) {
            return {
                basico: 'plan-basico',
                plus: 'plan-plus',
                premium: 'plan-premium',
            }[slug] || '';
        },
        featureLabel(key) { return FEATURE_LABELS[key] || key; },
    },
};
</script>

<style scoped>
.plan-card { border-width: 2px; transition: transform 0.15s ease, box-shadow 0.15s ease; }
.plan-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
.plan-basico { border-color: #adb5bd; }
.plan-plus { border-color: #0dcaf0; }
.plan-premium { border-color: #ff8c00; background: linear-gradient(180deg, #fff8ef 0%, #ffffff 60%); }

.price { font-size: 2.25rem; font-weight: 700; color: #2d3142; }
.price .period { font-size: 1rem; color: #6c757d; font-weight: 400; }

.limits li { padding: 0.25rem 0; font-weight: 500; font-size: 14px; }

.features-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.feature-chip {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 12px;
    background: #f1f3f5;
    color: #868e96;
    font-size: 11px;
    border: 1px solid #e9ecef;
}
.feature-chip.on {
    background: #d1f7e7;
    color: #0a7f4d;
    border-color: #a3edcb;
}

.licenses-count { font-size: 12px; }

.mf-modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.mf-modal-panel {
    background: #fff; border-radius: 8px; width: min(720px, 92vw);
    max-height: 90vh; overflow: hidden; display: flex; flex-direction: column;
}
.mf-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef;
}
.mf-modal-body { padding: 1rem 1.25rem; overflow-y: auto; }
.mf-modal-footer {
    padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef;
    display: flex; justify-content: flex-end; gap: 0.5rem;
}

.btn-tour-help {
    position: fixed; bottom: 30px; right: 30px; z-index: 999;
    width: 48px; height: 48px; border-radius: 50%;
    background: #0d6efd; color: white; border: none;
    font-size: 20px; box-shadow: 0 4px 12px rgba(13,110,253,0.4);
    cursor: pointer; transition: transform 0.2s;
}
.btn-tour-help:hover { transform: scale(1.1); }
</style>

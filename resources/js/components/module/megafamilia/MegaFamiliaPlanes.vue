<template>
    <div class="megafamilia-planes mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0">Planes MegaFamilia</h5>
            <button class="btn btn-sm btn-outline-secondary" @click="fetch" :disabled="loading">
                <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
            </button>
        </div>

        <div v-if="loading && plans.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>

        <div class="row">
            <div v-for="plan in plans" :key="plan.id" class="col-lg-4 mb-3">
                <div class="card plan-card h-100" :class="planClass(plan.slug)">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start">
                            <h4 class="card-title">{{ plan.name }}</h4>
                            <span v-if="!plan.active" class="badge bg-danger">Inactivo</span>
                        </div>
                        <div class="price">${{ Number(plan.price_monthly).toFixed(0) }}<span class="period"> /mes</span></div>

                        <ul class="list-unstyled limits mt-3">
                            <li><i class="fa fa-child me-2 text-info"></i> {{ plan.max_children === 0 ? 'Hijos ilimitados' : `${plan.max_children} hijos` }}</li>
                            <li><i class="fa fa-mobile me-2 text-info"></i> {{ plan.max_devices === 0 ? 'Dispositivos ilimitados' : `${plan.max_devices} dispositivos` }}</li>
                            <li><i class="fa fa-user-shield me-2 text-info"></i> {{ plan.max_parents === 0 ? 'Padres ilimitados' : `${plan.max_parents} ${plan.max_parents === 1 ? 'padre' : 'padres'}` }}</li>
                        </ul>

                        <ul class="list-unstyled features mt-2">
                            <li v-for="(enabled, key) in plan.features || {}" :key="key">
                                <i class="fa me-2" :class="enabled ? 'fa-check text-success' : 'fa-times text-muted'"></i>
                                {{ featureLabel(key) }}
                            </li>
                        </ul>

                        <button class="btn btn-outline-primary mt-auto" @click="openEdit(plan)">
                            <i class="fa fa-edit"></i> Editar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit modal -->
        <div v-if="editing" class="modal-overlay" @click.self="closeEdit">
            <div class="modal-panel">
                <div class="modal-panel-header">
                    <h5 class="m-0">Editar {{ editing.name }}</h5>
                    <button class="btn-close" @click="closeEdit"></button>
                </div>
                <div class="modal-panel-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Precio mensual (MXN)</label>
                            <input v-model.number="editing.price_monthly" type="number" min="0" class="form-control" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select v-model="editing.active" class="form-select">
                                <option :value="true">Activo</option>
                                <option :value="false">Inactivo</option>
                            </select>
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
                        <div class="col-12 mt-2">
                            <label class="form-label">Features</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check" v-for="(enabled, key) in editing.features || {}" :key="key">
                                    <input class="form-check-input" type="checkbox" :id="`feat-${key}`" :checked="!!enabled" @change="editing.features[key] = $event.target.checked" />
                                    <label class="form-check-label" :for="`feat-${key}`">{{ featureLabel(key) }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-panel-footer">
                    <button class="btn btn-outline-secondary" @click="closeEdit">Cancelar</button>
                    <button class="btn btn-primary" :disabled="saving" @click="save">
                        <i class="fa fa-save"></i> {{ saving ? 'Guardando…' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
const FEATURE_LABELS = {
    control_pantalla: 'Control de pantalla',
    limites_diarios: 'Límites diarios',
    bloqueo_apps: 'Bloqueo de apps',
    bloqueo_web: 'Bloqueo web',
    geofence: 'Geofence',
    tareas_recompensas: 'Tareas y recompensas',
    gps: 'GPS',
    mikrotik: 'Integración MikroTik',
    ia: 'IA',
    soporte_prioritario: 'Soporte prioritario',
};

export default {
    name: 'MegaFamiliaPlanes',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() {
        return { plans: [], loading: false, errorMsg: '', editing: null, saving: false };
    },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/planes/data`);
                this.plans = data.plans || [];
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        openEdit(plan) { this.editing = JSON.parse(JSON.stringify(plan)); },
        closeEdit() { this.editing = null; },
        async save() {
            this.saving = true;
            try {
                const payload = {
                    name: this.editing.name,
                    price_monthly: this.editing.price_monthly,
                    max_children: this.editing.max_children,
                    max_devices: this.editing.max_devices,
                    max_parents: this.editing.max_parents,
                    active: this.editing.active,
                    features: this.editing.features,
                };
                await axios.post(`${this.baseUrl}/planes/${this.editing.id}`, payload, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                this.closeEdit();
                await this.fetch();
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.saving = false; }
        },
        planClass(slug) { return { basico: 'plan-basico', plus: 'plan-plus', premium: 'plan-premium' }[slug] || ''; },
        featureLabel(key) { return FEATURE_LABELS[key] || key; },
    },
};
</script>

<style scoped>
.plan-card { border-width: 2px; }
.plan-basico { border-color: #adb5bd; }
.plan-plus { border-color: #0dcaf0; }
.plan-premium { border-color: #ff8c00; background: linear-gradient(180deg, #fff8ef 0%, #ffffff 60%); }
.price { font-size: 2.25rem; font-weight: 700; color: #2d3142; }
.price .period { font-size: 1rem; color: #6c757d; font-weight: 400; }
.limits li { padding: 0.25rem 0; font-weight: 500; }
.features li { padding: 0.15rem 0; font-size: 13px; color: #495057; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.modal-panel { background: #fff; border-radius: 8px; width: min(640px, 92vw); max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
.modal-panel-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
.modal-panel-body { padding: 1rem 1.25rem; overflow-y: auto; }
.modal-panel-footer { padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef; display: flex; justify-content: flex-end; gap: 0.5rem; }
</style>

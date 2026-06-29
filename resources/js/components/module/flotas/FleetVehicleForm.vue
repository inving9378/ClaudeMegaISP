<template>
    <div class="flt-form-wrap">

        <div class="flt-form-header">
            <h4 class="flt-form-title">
                <i class="bi bi-truck me-2"></i>
                {{ editMode ? 'Editar vehículo' : 'Nuevo vehículo' }}
            </h4>
            <a :href="`${baseUrl}`" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>

        <form @submit.prevent="handleSave('save')" novalidate>

            <!-- ── Sección 1: Datos básicos ─────────────────────────────── -->
            <div class="flt-section">
                <div class="flt-section-header" @click="toggleSection('basic')">
                    <span><i class="bi bi-card-list me-2 text-primary"></i>Datos básicos</span>
                    <i :class="['bi', openSections.basic ? 'bi-chevron-up' : 'bi-chevron-down']"></i>
                </div>
                <div v-show="openSections.basic" class="flt-section-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Placas <span class="text-danger">*</span></label>
                            <input class="form-control" :class="{'is-invalid': errors.plates}" v-model="form.plates" placeholder="ABC-123" maxlength="20" />
                            <div v-if="errors.plates" class="invalid-feedback">{{ errors.plates }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Marca <span class="text-danger">*</span></label>
                            <input class="form-control" :class="{'is-invalid': errors.brand}" v-model="form.brand" placeholder="Nissan" />
                            <div v-if="errors.brand" class="invalid-feedback">{{ errors.brand }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Modelo <span class="text-danger">*</span></label>
                            <input class="form-control" :class="{'is-invalid': errors.model}" v-model="form.model" placeholder="Frontier" />
                            <div v-if="errors.model" class="invalid-feedback">{{ errors.model }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Año</label>
                            <input class="form-control" type="number" v-model="form.year" min="1990" max="2100" placeholder="2022" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Color</label>
                            <input class="form-control" v-model="form.color" placeholder="Blanco" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Tipo de vehículo</label>
                            <select class="form-select" v-model="form.vehicle_type">
                                <option value="car">Automóvil</option>
                                <option value="pickup">Pickup / Camioneta</option>
                                <option value="truck">Camión</option>
                                <option value="motorcycle">Motocicleta</option>
                                <option value="other">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Combustible</label>
                            <select class="form-select" v-model="form.fuel_type">
                                <option value="gasoline">Gasolina</option>
                                <option value="diesel">Diésel</option>
                                <option value="electric">Eléctrico</option>
                                <option value="hybrid">Híbrido</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Cap. tanque (L)</label>
                            <input class="form-control" type="number" v-model="form.tank_capacity_liters" min="0" step="0.5" placeholder="70" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Kilometraje actual</label>
                            <input class="form-control" type="number" v-model="form.current_km" min="0" placeholder="0" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">VIN / No. serie</label>
                            <input class="form-control" v-model="form.vin" placeholder="3VWFE21C04M..." maxlength="50" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">No. motor</label>
                            <input class="form-control" v-model="form.motor_number" maxlength="50" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Estado</label>
                            <select class="form-select" v-model="form.status">
                                <option value="active">Activo</option>
                                <option value="in_workshop">En taller</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ubicación habitual</label>
                            <input class="form-control" v-model="form.habitual_location" placeholder="Bodega Norte, Sucursal Centro..." />
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notas</label>
                            <textarea class="form-control" v-model="form.notes" rows="2" placeholder="Información adicional..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Sección 2: Asignación inicial ──────────────────────────── -->
            <div class="flt-section">
                <div class="flt-section-header" @click="toggleSection('assignment')">
                    <span><i class="bi bi-person-badge me-2 text-success"></i>Asignación inicial <span class="text-muted fw-normal">(opcional)</span></span>
                    <i :class="['bi', openSections.assignment ? 'bi-chevron-up' : 'bi-chevron-down']"></i>
                </div>
                <div v-show="openSections.assignment" class="flt-section-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Operador asignado</label>
                            <select class="form-select" v-model="assignment.user_id">
                                <option :value="null">Sin asignar</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Departamento / Área</label>
                            <input class="form-control" v-model="assignment.department" placeholder="Instalaciones, Gerencia..." />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de asignación</label>
                            <input class="form-control" type="date" v-model="assignment.since" />
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notas de asignación</label>
                            <input class="form-control" v-model="assignment.notes" placeholder="Notas opcionales..." />
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Sección 3: GPS ───────────────────────────────────────── -->
            <div class="flt-section">
                <div class="flt-section-header" @click="toggleSection('gps')">
                    <span><i class="bi bi-broadcast me-2 text-info"></i>Tracking GPS <span class="text-muted fw-normal">(Fase 2)</span></span>
                    <i :class="['bi', openSections.gps ? 'bi-chevron-up' : 'bi-chevron-down']"></i>
                </div>
                <div v-show="openSections.gps" class="flt-section-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="hasGps" v-model="form.has_gps" />
                        <label class="form-check-label fw-semibold" for="hasGps">
                            Este vehículo tiene GPS instalado
                        </label>
                    </div>
                    <div v-if="form.has_gps" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Marca GPS</label>
                            <input class="form-control" v-model="form.gps_brand" placeholder="Ruptela, Concox..." />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Modelo GPS</label>
                            <input class="form-control" v-model="form.gps_model" placeholder="FM-Pro3" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">IMEI</label>
                            <input class="form-control" v-model="form.gps_imei" placeholder="350000000000000" maxlength="20" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">SIM / No. chip</label>
                            <input class="form-control" v-model="form.gps_sim" maxlength="20" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Operadora</label>
                            <input class="form-control" v-model="form.gps_carrier" placeholder="Telcel, AT&T..." />
                        </div>
                    </div>
                    <p v-else class="text-muted small mt-1">
                        <i class="bi bi-info-circle me-1"></i>
                        Los campos GPS se completarán en Fase 2 cuando se configure el tracking.
                    </p>
                </div>
            </div>

            <!-- ── Botones ──────────────────────────────────────────────── -->
            <div class="flt-form-footer">
                <a :href="baseUrl" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-x me-1"></i>Cancelar
                </a>
                <button type="button" class="btn btn-outline-primary me-2" @click="handleSave('new')" :disabled="saving">
                    <i class="bi bi-plus-circle me-1"></i>
                    {{ saving && saveMode === 'new' ? 'Guardando...' : 'Guardar y agregar otro' }}
                </button>
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    <i class="bi bi-check2 me-1"></i>
                    {{ saving && saveMode === 'save' ? 'Guardando...' : 'Guardar' }}
                </button>
            </div>

        </form>

        <!-- Toast -->
        <transition name="flt-toast-fade">
            <div v-if="toast.visible" class="flt-toast" :class="`flt-toast-${toast.type}`">
                <i :class="toast.icon" class="me-2"></i>{{ toast.message }}
            </div>
        </transition>

    </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'FleetVehicleForm',
    props: {
        baseUrl: { type: String, default: '/flotas' },
        vehicleId: { type: [Number, String], default: null },
    },
    setup(props) {
        const editMode = ref(!!props.vehicleId);
        const saving   = ref(false);
        const saveMode = ref('save');
        const users    = ref([]);
        const errors   = ref({});
        const toast    = ref({ visible: false, message: '', type: 'success', icon: '', timer: null });

        const openSections = reactive({ basic: true, assignment: true, gps: false });

        const defaultForm = () => ({
            plates: '', brand: '', model: '', year: new Date().getFullYear(),
            color: '', vehicle_type: 'car', fuel_type: 'gasoline',
            tank_capacity_liters: '', current_km: 0, vin: '', motor_number: '',
            status: 'active', habitual_location: '', notes: '',
            has_gps: false, gps_brand: '', gps_model: '', gps_imei: '', gps_sim: '', gps_carrier: '',
        });

        const defaultAssignment = () => ({
            user_id: null, department: '', since: new Date().toISOString().split('T')[0], notes: '',
        });

        const form       = reactive(defaultForm());
        const assignment = reactive(defaultAssignment());

        function toggleSection(s) { openSections[s] = !openSections[s]; }

        function validate() {
            errors.value = {};
            if (!form.plates?.trim()) errors.value.plates = 'Las placas son requeridas';
            if (!form.brand?.trim())  errors.value.brand  = 'La marca es requerida';
            if (!form.model?.trim())  errors.value.model  = 'El modelo es requerido';
            return Object.keys(errors.value).length === 0;
        }

        async function handleSave(mode) {
            if (!validate()) {
                showToast('Revisa los campos requeridos.', 'error', 'bi bi-exclamation-circle');
                openSections.basic = true;
                return;
            }
            saving.value  = true;
            saveMode.value = mode;
            try {
                const url  = editMode.value
                    ? `${props.baseUrl}/api/vehiculos/${props.vehicleId}`
                    : `${props.baseUrl}/api/vehiculos`;
                const method = editMode.value ? 'patch' : 'post';

                const { data } = await axios[method](url, { ...form });
                const vehicleId = data.vehicle?.id;

                // Asignar operador si se especificó (no bloquea el guardado del vehículo)
                let operadorAsignado = true;
                if (assignment.user_id && vehicleId) {
                    const asignEndpoint = `${props.baseUrl}/api/vehiculos/${vehicleId}/asignaciones`;
                    try {
                        await axios.post(asignEndpoint, { ...assignment, vehicle_id: vehicleId });
                    } catch (e) {
                        operadorAsignado = false;
                        console.error('Flotas: fallo al asignar el operador', e.response?.status, asignEndpoint);
                    }
                }

                // El vehículo SÍ se guardó. Si la asignación del operador falló, avisamos la verdad
                // (falla parcial) sin convertir el éxito del guardado en un error.
                if (operadorAsignado) {
                    showToast('Vehículo guardado correctamente.', 'success', 'bi bi-check-circle-fill');
                } else {
                    showToast('Vehículo guardado, pero no se pudo asignar el operador.', 'error', 'bi bi-exclamation-triangle-fill');
                }

                if (mode === 'new') {
                    Object.assign(form, defaultForm());
                    Object.assign(assignment, defaultAssignment());
                    errors.value = {};
                } else {
                    setTimeout(() => { window.location.href = `${props.baseUrl}/${vehicleId}`; }, 800);
                }
            } catch (e) {
                const msg = e.response?.data?.message || e.response?.data?.error || 'Error al guardar el vehículo.';
                showToast(msg, 'error', 'bi bi-exclamation-circle-fill');
                if (e.response?.data?.errors) {
                    errors.value = Object.fromEntries(
                        Object.entries(e.response.data.errors).map(([k, v]) => [k, v[0]])
                    );
                }
            } finally {
                saving.value = false;
            }
        }

        async function loadUsers() {
            try {
                const { data } = await axios
                    .get(`${props.baseUrl}/api/vehiculos/data/operadores`)
                    .catch(() => ({ data: null }));
                users.value = data?.users ?? [];
            } catch { users.value = []; }
        }

        async function loadVehicle() {
            if (!props.vehicleId) return;
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${props.vehicleId}`);
                Object.assign(form, data.vehicle ?? {});
                if (data.vehicle?.current_assignment) {
                    Object.assign(assignment, data.vehicle.current_assignment);
                }
            } catch { showToast('Error al cargar el vehículo.', 'error', 'bi bi-exclamation-circle'); }
        }

        function showToast(message, type = 'success', icon = 'bi bi-check2') {
            if (toast.value.timer) clearTimeout(toast.value.timer);
            toast.value = { visible: true, message, type, icon,
                timer: setTimeout(() => { toast.value.visible = false; }, 4000) };
        }

        onMounted(() => { loadUsers(); loadVehicle(); });

        return {
            editMode, saving, saveMode, form, assignment, users, errors, openSections, toast,
            toggleSection, handleSave,
        };
    },
};
</script>

<style scoped>
.flt-form-wrap { max-width: 1000px; margin: 0 auto; padding: 20px 0; }

.flt-form-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
}
.flt-form-title { font-size: 20px; font-weight: 700; margin: 0; }

.flt-section {
    border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 16px; overflow: hidden;
}
.flt-section-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 18px; background: #f9fafb; cursor: pointer; font-weight: 600; font-size: 14px;
    transition: background .15s;
}
.flt-section-header:hover { background: #f3f4f6; }
.flt-section-body { padding: 20px; }

.flt-form-footer {
    display: flex; justify-content: flex-end; align-items: center;
    padding: 20px 0 8px; gap: 4px;
}

.flt-toast {
    position: fixed; bottom: 28px; right: 28px; z-index: 9999;
    padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;
    box-shadow: 0 4px 16px rgba(0,0,0,.15); display: flex; align-items: center;
}
.flt-toast-success { background: #16a34a; color: #fff; }
.flt-toast-error   { background: #dc2626; color: #fff; }
.flt-toast-fade-enter-active, .flt-toast-fade-leave-active { transition: all .25s; }
.flt-toast-fade-enter-from, .flt-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }

@media (max-width: 640px) {
    .flt-form-footer { flex-direction: column; align-items: stretch; }
    .flt-form-footer .btn { width: 100%; margin: 2px 0; }
}
</style>

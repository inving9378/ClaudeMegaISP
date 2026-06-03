<template>
    <section>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="flt-section-title mb-0">Operador actual</h6>
            <button class="btn btn-sm btn-outline-primary" @click="showForm = !showForm">
                <i class="bi bi-arrow-left-right me-1"></i>Cambiar asignación
            </button>
        </div>

        <div class="flt-current-assign mb-3" v-if="currentAssignment">
            <div class="flt-avatar"><i class="bi bi-person-fill"></i></div>
            <div>
                <div class="fw-semibold">{{ currentAssignment.operator?.name || 'Sin nombre' }}</div>
                <div class="text-muted small">
                    {{ currentAssignment.department || 'Sin departamento' }} ·
                    desde {{ fmtDate(currentAssignment.since) }}
                </div>
            </div>
        </div>
        <div v-else class="text-muted mb-3"><i class="bi bi-person-dash me-1"></i>Sin operador asignado.</div>

        <div v-if="showForm" class="flt-inline-form mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Operador <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="form.user_id">
                        <option :value="null">Selecciona…</option>
                        <option v-for="u in operators" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Departamento</label>
                    <input class="form-control" v-model="form.department" />
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Desde <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" v-model="form.since" />
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Hasta</label>
                    <input type="date" class="form-control" v-model="form.until" />
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Notas</label>
                    <input class="form-control" v-model="form.notes" />
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn btn-sm btn-outline-secondary me-2" @click="showForm = false">Cancelar</button>
                <button class="btn btn-sm btn-primary" :disabled="saving" @click="save">
                    <i class="bi bi-check2 me-1"></i>{{ saving ? 'Guardando…' : 'Asignar' }}
                </button>
            </div>
        </div>

        <h6 class="flt-section-title">Historial de asignaciones</h6>
        <div v-if="!assignments.length" class="text-muted small">Sin historial.</div>
        <div class="flt-timeline">
            <div class="flt-tl-item" v-for="a in assignments" :key="a.id">
                <div class="flt-tl-dot" :class="a.is_active ? 'bg-success' : 'bg-secondary'"></div>
                <div class="flt-tl-content">
                    <div class="fw-semibold">
                        {{ a.operator_name || ('Usuario #' + a.user_id) }}
                        <span v-if="a.is_active" class="badge flt-badge-active ms-1">Actual</span>
                    </div>
                    <div class="text-muted small">
                        {{ a.department || 'Sin departamento' }} ·
                        {{ fmtDate(a.since) }} → {{ a.until ? fmtDate(a.until) : 'presente' }}
                    </div>
                    <div v-if="a.notes" class="small mt-1">{{ a.notes }}</div>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
import { ref, reactive, computed } from 'vue';
import axios from 'axios';
import { useFleetFormatters } from './useFleetFormatters.js';

export default {
    name: 'FleetTabAsignacion',
    props: {
        vehicle:     { type: Object, required: true },
        vehicleId:   { type: [String, Number], required: true },
        baseUrl:     { type: String, default: '/flotas' },
        assignments: { type: Array, default: () => [] },
        operators:   { type: Array, default: () => [] },
    },
    emits: ['reload', 'reload-assignments', 'toast'],
    setup(props, { emit }) {
        const { fmtDate } = useFleetFormatters();

        const showForm = ref(false);
        const saving   = ref(false);
        const form     = reactive({
            user_id: null, department: '', since: new Date().toISOString().split('T')[0], until: '', notes: '',
        });

        const currentAssignment = computed(() =>
            props.vehicle?.current_assignment || props.vehicle?.currentAssignment || null
        );

        async function save() {
            if (!form.user_id) { emit('toast', { message: 'Selecciona un operador.', type: 'error' }); return; }
            saving.value = true;
            try {
                const payload = { ...form };
                if (!payload.until) delete payload.until;
                await axios.post(`${props.baseUrl}/api/vehiculos/${props.vehicleId}/asignaciones`, payload);
                showForm.value = false;
                emit('reload-assignments');
                emit('reload');
                emit('toast', { message: 'Asignación registrada.', type: 'success' });
            } catch (e) {
                emit('toast', { message: e?.response?.data?.message || 'No se pudo registrar la asignación.', type: 'error' });
            } finally { saving.value = false; }
        }

        return { showForm, saving, form, currentAssignment, fmtDate, save };
    },
};
</script>

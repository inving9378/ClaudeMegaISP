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
                        <option :value="null">{{ modo === 'portal' ? 'Selecciona de Mis operadores…' : 'Selecciona…' }}</option>
                        <option v-for="u in operators" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div class="col-md-4" v-if="modo === 'portal'">
                    <label class="form-label fw-semibold">…o escribe el nombre</label>
                    <input class="form-control" v-model="form.operator_name" maxlength="150" />
                </div>
                <div class="col-md-4" v-else>
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

        <div v-if="modo === 'portal'" class="mb-4">
            <h6 class="flt-section-title">Mis operadores</h6>
            <div class="row g-2 mb-2">
                <div class="col-md-5"><input class="form-control form-control-sm" v-model="opForm.nombre" placeholder="Nombre" maxlength="150" /></div>
                <div class="col-md-3"><input class="form-control form-control-sm" v-model="opForm.telefono" placeholder="Teléfono" maxlength="30" /></div>
                <div class="col-md-4">
                    <button class="btn btn-sm btn-primary me-1" :disabled="opSaving" @click="saveOperator">
                        {{ opForm.id ? 'Guardar' : 'Agregar operador' }}
                    </button>
                    <button v-if="opForm.id" class="btn btn-sm btn-outline-secondary" @click="resetOp">Cancelar</button>
                </div>
            </div>
            <div v-if="!myOperators.length" class="text-muted small">Aún no has dado de alta operadores.</div>
            <ul class="list-group list-group-flush">
                <li v-for="o in myOperators" :key="o.id" class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span>{{ o.nombre }}<span v-if="o.telefono" class="text-muted small"> · {{ o.telefono }}</span></span>
                    <span>
                        <button class="btn btn-sm btn-link" @click="editOp(o)">Editar</button>
                        <button class="btn btn-sm btn-link text-danger" @click="removeOp(o)">Eliminar</button>
                    </span>
                </li>
            </ul>
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
import { ref, reactive, computed, onMounted } from 'vue';
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
        modo:        { type: String, default: 'panel' },
    },
    emits: ['reload', 'reload-assignments', 'toast'],
    setup(props, { emit }) {
        const { fmtDate } = useFleetFormatters();

        const showForm = ref(false);
        const saving   = ref(false);
        const form     = reactive({
            user_id: null, operator_name: '', department: '', since: new Date().toISOString().split('T')[0], until: '', notes: '',
        });

        const currentAssignment = computed(() =>
            props.vehicle?.current_assignment || props.vehicle?.currentAssignment || null
        );

        async function save() {
            const portal = props.modo === 'portal';
            if (portal ? (!form.user_id && !form.operator_name.trim() || form.user_id && form.operator_name.trim()) : !form.user_id) {
                emit('toast', { message: portal ? 'Elige un operador de tu lista o escribe un nombre (solo uno).' : 'Selecciona un operador.', type: 'error' }); return;
            }
            saving.value = true;
            try {
                const payload = { ...form };
                if (portal) {
                    payload.client_operator_id = payload.user_id || null;
                    delete payload.user_id; delete payload.department;
                    if (!payload.operator_name.trim()) delete payload.operator_name;
                }
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

        // Mis operadores (solo portal): CRUD sobre {baseUrl}/api/operadores
        const myOperators = ref([]);
        const opSaving = ref(false);
        const opForm = reactive({ id: null, nombre: '', telefono: '' });
        const resetOp = () => { opForm.id = null; opForm.nombre = ''; opForm.telefono = ''; };
        async function loadOps() {
            if (props.modo !== 'portal') return;
            try { myOperators.value = (await axios.get(`${props.baseUrl}/api/operadores`)).data?.operadores ?? []; } catch (e) { myOperators.value = []; }
        }
        async function saveOperator() {
            if (!opForm.nombre.trim()) { emit('toast', { message: 'Escribe el nombre del operador.', type: 'error' }); return; }
            opSaving.value = true;
            try {
                const body = { nombre: opForm.nombre.trim(), telefono: opForm.telefono || null };
                if (opForm.id) await axios.put(`${props.baseUrl}/api/operadores/${opForm.id}`, body);
                else await axios.post(`${props.baseUrl}/api/operadores`, body);
                resetOp(); await loadOps(); emit('reload-assignments');
                emit('toast', { message: 'Operador guardado.', type: 'success' });
            } catch (e) {
                emit('toast', { message: e?.response?.data?.message || 'No se pudo guardar el operador.', type: 'error' });
            } finally { opSaving.value = false; }
        }
        function editOp(o) { opForm.id = o.id; opForm.nombre = o.nombre; opForm.telefono = o.telefono || ''; }
        async function removeOp(o) {
            if (!window.confirm(`¿Eliminar a ${o.nombre}?`)) return;
            try { await axios.delete(`${props.baseUrl}/api/operadores/${o.id}`); await loadOps(); emit('reload-assignments'); }
            catch (e) { emit('toast', { message: 'No se pudo eliminar.', type: 'error' }); }
        }
        onMounted(loadOps);

        return { showForm, saving, form, currentAssignment, fmtDate, save, myOperators, opSaving, opForm, resetOp, saveOperator, editOp, removeOp };
    },
};
</script>

<template>
    <section>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="flt-section-title mb-0">Datos generales</h6>
            <div v-if="!editMode">
                <button class="btn btn-sm btn-outline-primary" @click="startEdit">
                    <i class="bi bi-pencil me-1"></i>Editar
                </button>
            </div>
            <div v-else>
                <button class="btn btn-sm btn-outline-secondary me-2" @click="cancelEdit">Cancelar</button>
                <button class="btn btn-sm btn-primary" :disabled="saving" @click="save">
                    <i class="bi bi-check2 me-1"></i>{{ saving ? 'Guardando…' : 'Guardar' }}
                </button>
            </div>
        </div>

        <!-- Vista -->
        <div v-if="!editMode" class="row g-3 flt-info-grid">
            <div class="col-md-4" v-for="f in infoFields" :key="f.key">
                <div class="flt-info-label">{{ f.label }}</div>
                <div class="flt-info-value">{{ f.format ? f.format(vehicle[f.key]) : (vehicle[f.key] || '—') }}</div>
            </div>
        </div>

        <!-- Edición inline -->
        <div v-else class="row g-3">
            <div class="col-md-3"><label class="form-label fw-semibold">Placas</label><input class="form-control" v-model="form.plates" /></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Marca</label><input class="form-control" v-model="form.brand" /></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Modelo</label><input class="form-control" v-model="form.model" /></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Año</label><input type="number" class="form-control" v-model="form.year" /></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Color</label><input class="form-control" v-model="form.color" /></div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Tipo</label>
                <select class="form-select" v-model="form.vehicle_type">
                    <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Combustible</label>
                <select class="form-select" v-model="form.fuel_type">
                    <option v-for="(label, key) in fuelLabels" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Estado</label>
                <select class="form-select" v-model="form.status">
                    <option value="active">Activo</option>
                    <option value="in_workshop">En taller</option>
                    <option value="inactive">Inactivo</option>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label fw-semibold">Cap. tanque (L)</label><input type="number" step="0.5" class="form-control" v-model="form.tank_capacity_liters" /></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Kilometraje</label><input type="number" class="form-control" v-model="form.current_km" /></div>
            <div class="col-md-3"><label class="form-label fw-semibold">VIN</label><input class="form-control" v-model="form.vin" /></div>
            <div class="col-md-3"><label class="form-label fw-semibold">No. motor</label><input class="form-control" v-model="form.motor_number" /></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Ubicación habitual</label><input class="form-control" v-model="form.habitual_location" /></div>
            <div class="col-12"><label class="form-label fw-semibold">Notas</label><textarea class="form-control" rows="2" v-model="form.notes"></textarea></div>
        </div>

        <!-- Sección GPS -->
        <div v-if="vehicle.has_gps" class="flt-gps-box mt-4">
            <h6 class="flt-section-title"><i class="bi bi-broadcast text-info me-1"></i>Dispositivo GPS</h6>
            <div class="row g-3 flt-info-grid">
                <div class="col-md-3"><div class="flt-info-label">Marca</div><div class="flt-info-value">{{ vehicle.gps_brand || '—' }}</div></div>
                <div class="col-md-3"><div class="flt-info-label">Modelo</div><div class="flt-info-value">{{ vehicle.gps_model || '—' }}</div></div>
                <div class="col-md-3"><div class="flt-info-label">IMEI</div><div class="flt-info-value">{{ vehicle.gps_imei || '—' }}</div></div>
                <div class="col-md-3"><div class="flt-info-label">SIM</div><div class="flt-info-value">{{ vehicle.gps_sim || '—' }}</div></div>
                <div class="col-md-3"><div class="flt-info-label">Operadora</div><div class="flt-info-value">{{ vehicle.gps_carrier || '—' }}</div></div>
            </div>
        </div>
    </section>
</template>

<script>
import { ref, reactive } from 'vue';
import axios from 'axios';
import { useFleetFormatters } from './useFleetFormatters.js';

export default {
    name: 'FleetTabInfo',
    props: {
        vehicle:   { type: Object, required: true },
        vehicleId: { type: [String, Number], required: true },
        baseUrl:   { type: String, default: '/flotas' },
    },
    emits: ['reload', 'toast'],
    setup(props, { emit }) {
        const { fmtKm, statusLabel, fuelLabels, typeLabels } = useFleetFormatters();

        const editMode = ref(false);
        const saving   = ref(false);
        const form     = reactive({});

        const infoFields = [
            { key: 'plates',               label: 'Placas' },
            { key: 'brand',                label: 'Marca' },
            { key: 'model',                label: 'Modelo' },
            { key: 'year',                 label: 'Año' },
            { key: 'color',                label: 'Color' },
            { key: 'vehicle_type',         label: 'Tipo',         format: (v) => typeLabels[v] || v },
            { key: 'fuel_type',            label: 'Combustible',  format: (v) => fuelLabels[v] || v },
            { key: 'tank_capacity_liters', label: 'Cap. tanque',  format: (v) => (v ? `${v} L` : '—') },
            { key: 'current_km',           label: 'Kilometraje',  format: (v) => `${fmtKm(v)} km` },
            { key: 'status',               label: 'Estado',       format: (v) => statusLabel(v) },
            { key: 'vin',                  label: 'VIN' },
            { key: 'motor_number',         label: 'No. motor' },
            { key: 'habitual_location',    label: 'Ubicación habitual' },
            { key: 'notes',                label: 'Notas' },
        ];

        function startEdit() { Object.assign(form, props.vehicle); editMode.value = true; }
        function cancelEdit() { editMode.value = false; }

        async function save() {
            saving.value = true;
            try {
                await axios.patch(`${props.baseUrl}/api/vehiculos/${props.vehicleId}`, form);
                editMode.value = false;
                emit('reload');
                emit('toast', { message: 'Vehículo actualizado.', type: 'success' });
            } catch (e) {
                const msg = e?.response?.data?.message || 'No se pudo actualizar el vehículo.';
                emit('toast', { message: msg, type: 'error' });
            } finally {
                saving.value = false;
            }
        }

        return { editMode, saving, form, infoFields, fuelLabels, typeLabels, startEdit, cancelEdit, save };
    },
};
</script>

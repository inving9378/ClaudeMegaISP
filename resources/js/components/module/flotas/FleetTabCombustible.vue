<template>
    <section>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="flt-section-title mb-0">Cargas de combustible</h6>
            <button class="btn btn-sm btn-primary" @click="showForm = !showForm">
                <i class="bi bi-plus-lg me-1"></i>Nueva carga
            </button>
        </div>

        <div v-if="showForm" class="flt-inline-form mb-4">
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label><input type="date" class="form-control" v-model="form.refuel_date" /></div>
                <div class="col-md-2"><label class="form-label fw-semibold">Litros <span class="text-danger">*</span></label><input type="number" step="0.01" class="form-control" v-model.number="form.liters" /></div>
                <div class="col-md-2"><label class="form-label fw-semibold">Costo <span class="text-danger">*</span></label><input type="number" step="0.01" class="form-control" v-model.number="form.cost" /></div>
                <div class="col-md-2"><label class="form-label fw-semibold">Km</label><input type="number" class="form-control" v-model.number="form.km_at_refuel" /></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Octanaje</label><input class="form-control" v-model="form.octane" placeholder="Magna / Premium" /></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Gasolinera</label><input class="form-control" v-model="form.station_name" /></div>
            </div>
            <div class="text-end mt-3">
                <button class="btn btn-sm btn-outline-secondary me-2" @click="showForm = false">Cancelar</button>
                <button class="btn btn-sm btn-primary" :disabled="saving" @click="save">
                    <i class="bi bi-check2 me-1"></i>{{ saving ? 'Guardando…' : 'Guardar' }}
                </button>
            </div>
        </div>

        <div v-if="!computed_log.length" class="text-muted small py-3">Sin cargas registradas.</div>
        <div class="flt-list">
            <div class="flt-list-item" v-for="f in computed_log" :key="f.id">
                <div class="flt-list-icon"><i class="bi bi-fuel-pump-fill text-warning"></i></div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold">{{ Number(f.liters).toFixed(1) }} L
                            <span class="text-muted fw-normal">· {{ fmtMoney(f.cost) }}</span>
                        </span>
                        <span class="text-muted small" v-if="f.km_l">{{ f.km_l }} km/L</span>
                    </div>
                    <div class="text-muted small">
                        {{ fmtDate(f.refuel_date) }}
                        <span v-if="f.km_at_refuel"> · {{ fmtKm(f.km_at_refuel) }} km</span>
                        <span v-if="f.octane"> · {{ f.octane }}</span>
                        <span v-if="f.station_name"> · {{ f.station_name }}</span>
                        <span> · {{ fmtMoney(f.cost_per_liter) }}/L</span>
                    </div>
                </div>
                <button class="btn btn-sm btn-link text-danger" @click="remove(f)"><i class="bi bi-trash"></i></button>
            </div>
        </div>
    </section>
</template>

<script>
import { ref, reactive, computed } from 'vue';
import axios from 'axios';
import { useFleetFormatters } from './useFleetFormatters.js';

export default {
    name: 'FleetTabCombustible',
    props: {
        vehicle:   { type: Object, required: true },
        vehicleId: { type: [String, Number], required: true },
        baseUrl:   { type: String, default: '/flotas' },
    },
    emits: ['reload', 'toast'],
    setup(props, { emit }) {
        const { fmtMoney, fmtKm, fmtDate } = useFleetFormatters();

        const computed_log = computed(() => {
            const logs = (props.vehicle?.fuelLog ?? []).slice().sort((a, b) => new Date(a.refuel_date) - new Date(b.refuel_date));
            let prevKm = null;
            return logs.map((f) => {
                let km_l = null;
                if (prevKm != null && f.km_at_refuel && f.liters > 0 && f.km_at_refuel > prevKm) {
                    km_l = ((f.km_at_refuel - prevKm) / Number(f.liters)).toFixed(1);
                }
                if (f.km_at_refuel) prevKm = f.km_at_refuel;
                return { ...f, km_l, cost_per_liter: Number(f.liters) > 0 ? (Number(f.cost) / Number(f.liters)) : 0 };
            }).reverse();
        });

        const showForm = ref(false); const saving = ref(false);
        const defaultForm = () => ({
            refuel_date: new Date().toISOString().split('T')[0],
            liters: null, cost: null,
            km_at_refuel: props.vehicle?.current_km ?? null,
            octane: '', station_name: '',
        });
        const form = reactive(defaultForm());

        async function save() {
            if (!form.liters || !form.cost) { emit('toast', { message: 'Litros y costo son requeridos.', type: 'error' }); return; }
            saving.value = true;
            try {
                await axios.post(`${props.baseUrl}/api/combustible`, { ...form, vehicle_id: Number(props.vehicleId) });
                showForm.value = false; Object.assign(form, defaultForm());
                emit('reload'); emit('toast', { message: 'Carga registrada.', type: 'success' });
            } catch (e) { emit('toast', { message: 'No se pudo registrar la carga.', type: 'error' }); }
            finally { saving.value = false; }
        }

        async function remove(f) {
            if (!window.confirm('¿Eliminar esta carga?')) return;
            try { await axios.delete(`${props.baseUrl}/api/combustible/${f.id}`); emit('reload'); emit('toast', { message: 'Carga eliminada.', type: 'success' }); }
            catch (e) { emit('toast', { message: 'No se pudo eliminar.', type: 'error' }); }
        }

        return { computed_log, showForm, saving, form, save, remove, fmtMoney, fmtKm, fmtDate };
    },
};
</script>

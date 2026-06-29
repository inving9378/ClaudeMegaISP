<template>
    <section>
        <div v-if="nextServiceBanner" class="flt-banner flt-banner-blue mb-3">
            <i class="bi bi-wrench-adjustable-circle me-2"></i>{{ nextServiceBanner }}
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3"><div class="flt-mini-card"><div class="flt-mini-label">Total</div><div class="flt-mini-value">{{ maintenances.length }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="flt-mini-card"><div class="flt-mini-label">Este año</div><div class="flt-mini-value">{{ maintYearCount }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="flt-mini-card"><div class="flt-mini-label">Gasto del año</div><div class="flt-mini-value">{{ fmtMoney(maintSpendYear) }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="flt-mini-card"><div class="flt-mini-label">Promedio</div><div class="flt-mini-value">{{ fmtMoney(maintAvg) }}</div></div></div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <div class="flex-grow-1" style="min-width:180px">
                <input class="form-control form-control-sm" placeholder="Buscar…" v-model="search" />
            </div>
            <select class="form-select form-select-sm w-auto" v-model="typeFilter">
                <option value="">Todos los tipos</option>
                <option v-for="(l, k) in maintLabels" :key="k" :value="k">{{ l }}</option>
            </select>
            <select class="form-select form-select-sm w-auto" v-model="periodFilter">
                <option value="">Todo el tiempo</option>
                <option value="year">Este año</option>
                <option value="month">Este mes</option>
            </select>
            <button class="btn btn-sm btn-primary ms-auto" @click="showForm = !showForm">
                <i class="bi bi-plus-lg me-1"></i>Nuevo mantenimiento
            </button>
        </div>

        <!-- Formulario inline -->
        <div v-if="showForm" class="flt-inline-form mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                    <div class="d-flex flex-wrap gap-1">
                        <template v-for="(l, k) in maintLabels" :key="k">
                            <input type="radio" class="btn-check" :id="`mtype-${k}`" :value="k" v-model="form.type" autocomplete="off">
                            <label class="btn btn-sm btn-outline-secondary" :for="`mtype-${k}`">{{ l }}</label>
                        </template>
                    </div>
                </div>
                <div class="col-md-3"><label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label><input type="date" class="form-control" v-model="form.service_date" /></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Km al servicio</label><input type="number" class="form-control" v-model="form.service_km" /></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Mecánico</label><input class="form-control" v-model="form.mechanic_name" /></div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Trabajos realizados</label>
                    <div class="border rounded p-2" style="max-height:200px;overflow-y:auto;">
                        <div class="row g-1">
                            <div class="col-6" v-for="w in worksCatalog" :key="w">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" :id="`work-${w}`" :value="w" v-model="form.works">
                                    <label class="form-check-label small" :for="`work-${w}`">{{ w }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="form.works.length" class="mt-2 d-flex flex-wrap gap-1">
                        <span v-for="w in form.works" :key="w" class="badge rounded-pill bg-light text-dark border small d-inline-flex align-items-center gap-1">
                            {{ w }}<i class="bi bi-x" style="cursor:pointer" @click="form.works = form.works.filter(x => x !== w)"></i>
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Proveedor / Taller</label>
                    <div class="input-group">
                        <select class="form-select" v-model="form.provider_id">
                            <option :value="null">Sin proveedor</option>
                            <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <button class="btn btn-outline-secondary" type="button" @click="showProviderForm = !showProviderForm">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div v-if="showProviderForm" class="d-flex gap-2 mt-2">
                        <input class="form-control form-control-sm" placeholder="Nombre del nuevo proveedor" v-model="newProviderName" />
                        <button class="btn btn-sm btn-success" :disabled="creatingProvider" @click="createProvider">Crear</button>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea class="form-control" rows="2" v-model="form.description"></textarea>
                    </div>
                </div>
                <div class="col-md-3"><label class="form-label fw-semibold">Mano de obra</label><input type="number" step="0.01" class="form-control" v-model.number="form.labor_cost" /></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Refacciones</label><input type="number" step="0.01" class="form-control" v-model.number="form.parts_cost" /></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Otros</label><input type="number" step="0.01" class="form-control" v-model.number="form.other_cost" /></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Total</label><input class="form-control bg-light" :value="fmtMoney(totalLive)" readonly /></div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Próximo servicio</label>
                    <div class="d-flex flex-wrap gap-1">
                        <input type="radio" class="btn-check" id="next-none" value="none" v-model="form.next_mode" autocomplete="off">
                        <label class="btn btn-sm btn-outline-secondary" for="next-none">Sin definir</label>
                        <input type="radio" class="btn-check" id="next-km" value="km" v-model="form.next_mode" autocomplete="off">
                        <label class="btn btn-sm btn-outline-secondary" for="next-km">Por km</label>
                        <input type="radio" class="btn-check" id="next-date" value="date" v-model="form.next_mode" autocomplete="off">
                        <label class="btn btn-sm btn-outline-secondary" for="next-date">Por fecha</label>
                        <input type="radio" class="btn-check" id="next-both" value="both" v-model="form.next_mode" autocomplete="off">
                        <label class="btn btn-sm btn-outline-secondary" for="next-both">Ambos</label>
                    </div>
                </div>
                <div class="col-md-3" v-if="['km','both'].includes(form.next_mode)">
                    <label class="form-label fw-semibold">Próximo km</label>
                    <input type="number" class="form-control" v-model="form.next_service_km" />
                </div>
                <div class="col-md-3" v-if="['date','both'].includes(form.next_mode)">
                    <label class="form-label fw-semibold">Próxima fecha</label>
                    <input type="date" class="form-control" v-model="form.next_service_date" />
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Evidencia</label>
                    <div class="flt-drop" :class="{ 'flt-drop-over': drag }"
                         @dragover.prevent="drag = true" @dragleave.prevent="drag = false"
                         @drop.prevent="onDrop" @click="$refs.fileInput.click()">
                        <i class="bi bi-cloud-arrow-up fs-3 d-block mb-1"></i>
                        Arrastra archivos aquí o haz clic para seleccionar
                        <input ref="fileInput" type="file" multiple class="d-none" @change="onPick" />
                    </div>
                    <ul class="flt-file-list mt-2" v-if="files.length">
                        <li v-for="(f, i) in files" :key="i">
                            <i class="bi bi-paperclip me-1"></i>{{ f.name }}
                            <i class="bi bi-x-lg flt-file-rm" @click="files.splice(i, 1)"></i>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn btn-sm btn-outline-secondary me-2" @click="saveMaint(true)" :disabled="saving">
                    <i class="bi bi-file-earmark me-1"></i>Borrador
                </button>
                <button class="btn btn-sm btn-outline-primary me-2" @click="saveMaint(false, true)" :disabled="saving">Guardar y crear otro</button>
                <button class="btn btn-sm btn-primary" @click="saveMaint(false)" :disabled="saving">
                    <i class="bi bi-check2 me-1"></i>{{ saving ? 'Guardando…' : 'Guardar' }}
                </button>
            </div>
        </div>

        <div v-if="!filtered.length" class="text-muted small py-3">Sin mantenimientos registrados.</div>
        <div class="flt-list">
            <div class="flt-list-item" v-for="m in filtered" :key="m.id">
                <div class="flt-list-icon"><i :class="['bi', maintIcon(m.type).icon, maintIcon(m.type).color]"></i></div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold">{{ maintTypeLabel(m.type) }}
                            <span v-if="m.is_draft" class="badge bg-secondary ms-1">Borrador</span>
                        </span>
                        <span class="fw-semibold">{{ fmtMoney(m.total_cost) }}</span>
                    </div>
                    <div class="text-muted small">
                        {{ fmtDate(m.service_date) }}
                        <span v-if="m.service_km"> · {{ fmtKm(m.service_km) }} km</span>
                        <span v-if="m.provider"> · {{ m.provider.name }}</span>
                    </div>
                    <div v-if="m.works && m.works.length" class="small text-muted mt-1"><i class="bi bi-list-check me-1"></i>{{ m.works.join(', ') }}</div>
                    <div v-if="m.description" class="small mt-1">{{ m.description }}</div>
                    <div v-if="m.files && m.files.length" class="small text-muted mt-1"><i class="bi bi-paperclip me-1"></i>{{ m.files.length }} archivo(s)</div>
                </div>
                <button class="btn btn-sm btn-link text-danger" @click="deleteMaint(m)"><i class="bi bi-trash"></i></button>
            </div>
        </div>
    </section>
</template>

<script>
import { ref, reactive, computed } from 'vue';
import axios from 'axios';
import { useFleetFormatters } from './useFleetFormatters.js';

export default {
    name: 'FleetTabMantenimientos',
    props: {
        vehicle:   { type: Object, required: true },
        vehicleId: { type: [String, Number], required: true },
        baseUrl:   { type: String, default: '/flotas' },
        providers: { type: Array, default: () => [] },
    },
    emits: ['reload', 'provider-created', 'toast'],
    setup(props, { emit }) {
        const { fmtMoney, fmtKm, fmtDate, maintLabels, maintTypeLabel, maintIcon, worksCatalog } = useFleetFormatters();

        const maintenances = computed(() => props.vehicle?.maintenances ?? []);
        const thisYear = new Date().getFullYear();
        const isThisYear  = (d) => d && new Date(d).getFullYear() === thisYear;
        const isThisMonth = (d) => { if (!d) return false; const t = new Date(d), n = new Date(); return t.getFullYear() === n.getFullYear() && t.getMonth() === n.getMonth(); };

        const maintYearCount = computed(() => maintenances.value.filter((m) => isThisYear(m.service_date)).length);
        const maintSpendYear = computed(() => maintenances.value.filter((m) => isThisYear(m.service_date)).reduce((s, m) => s + Number(m.total_cost || 0), 0));
        const maintAvg       = computed(() => maintenances.value.length ? maintenances.value.reduce((s, m) => s + Number(m.total_cost || 0), 0) / maintenances.value.length : 0);

        const nextServiceRecord = computed(() => {
            const withNext = maintenances.value.filter((m) => m.next_service_date || m.next_service_km);
            if (!withNext.length) return null;
            return withNext.slice().sort((a, b) => new Date(b.service_date) - new Date(a.service_date))[0];
        });
        const nextServiceBanner = computed(() => {
            const r = nextServiceRecord.value;
            if (!r) return null;
            const parts = [];
            if (r.next_service_date) parts.push(`fecha estimada ${fmtDate(r.next_service_date)}`);
            if (r.next_service_km) parts.push(`a los ${fmtKm(r.next_service_km)} km`);
            return `Próximo servicio: ${parts.join(' · ')}`;
        });

        const search = ref(''); const typeFilter = ref(''); const periodFilter = ref('');
        const filtered = computed(() => maintenances.value.filter((m) => {
            if (typeFilter.value && m.type !== typeFilter.value) return false;
            if (periodFilter.value === 'year' && !isThisYear(m.service_date)) return false;
            if (periodFilter.value === 'month' && !isThisMonth(m.service_date)) return false;
            if (search.value) {
                const q = search.value.toLowerCase();
                const hay = `${maintTypeLabel(m.type)} ${m.description || ''} ${m.mechanic_name || ''} ${(m.works || []).join(' ')} ${m.provider?.name || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        }));

        const showForm = ref(false); const saving = ref(false); const drag = ref(false); const files = ref([]);
        const defaultForm = () => ({
            type: 'preventive', service_date: new Date().toISOString().split('T')[0],
            service_km: props.vehicle?.current_km ?? null, mechanic_name: '', works: [],
            provider_id: null, description: '', labor_cost: 0, parts_cost: 0, other_cost: 0,
            next_mode: 'none', next_service_km: null, next_service_date: null,
        });
        const form = reactive(defaultForm());
        const totalLive = computed(() => Number(form.labor_cost || 0) + Number(form.parts_cost || 0) + Number(form.other_cost || 0));

        const onDrop = (e) => { drag.value = false; files.value.push(...Array.from(e.dataTransfer.files)); };
        const onPick = (e) => { files.value.push(...Array.from(e.target.files)); e.target.value = ''; };

        const showProviderForm = ref(false); const newProviderName = ref(''); const creatingProvider = ref(false);
        async function createProvider() {
            if (!newProviderName.value.trim()) return;
            creatingProvider.value = true;
            try {
                const { data } = await axios.post(`${props.baseUrl}/api/proveedores`, { name: newProviderName.value.trim(), type: 'workshop' });
                emit('provider-created', data.provider);
                form.provider_id = data.provider.id;
                newProviderName.value = ''; showProviderForm.value = false;
                emit('toast', { message: 'Proveedor creado.', type: 'success' });
            } catch (e) { emit('toast', { message: 'No se pudo crear el proveedor.', type: 'error' }); }
            finally { creatingProvider.value = false; }
        }

        async function saveMaint(isDraft, keepOpen = false) {
            saving.value = true;
            try {
                const payload = {
                    vehicle_id: Number(props.vehicleId), type: form.type, service_date: form.service_date,
                    service_km: form.service_km || null, works: form.works, description: form.description || null,
                    provider_id: form.provider_id, mechanic_name: form.mechanic_name || null,
                    labor_cost: form.labor_cost || 0, parts_cost: form.parts_cost || 0, other_cost: form.other_cost || 0,
                    is_draft: !!isDraft,
                    next_service_km: ['km', 'both'].includes(form.next_mode) ? form.next_service_km : null,
                    next_service_date: ['date', 'both'].includes(form.next_mode) ? form.next_service_date : null,
                };
                const { data } = await axios.post(`${props.baseUrl}/api/mantenimientos`, payload);
                let archivosFallidos = 0;
                const filesEndpoint = `${props.baseUrl}/api/mantenimientos/${data.maintenance.id}/files`;
                for (const f of files.value) {
                    const fd = new FormData(); fd.append('file', f);
                    try {
                        await axios.post(filesEndpoint, fd);
                    } catch (e) {
                        archivosFallidos++;
                        console.error('Flotas: fallo al subir archivo de mantenimiento', e.response?.status, filesEndpoint);
                    }
                }
                // El mantenimiento SÍ se registró. Si algún archivo no subió, lo decimos (falla parcial),
                // sin convertir el éxito del registro en un error.
                const baseMsg = isDraft ? 'Borrador guardado.' : 'Mantenimiento registrado.';
                if (archivosFallidos > 0) {
                    emit('toast', { message: `${baseMsg} Pero ${archivosFallidos} archivo(s) no se pudieron subir.`, type: 'error' });
                } else {
                    emit('toast', { message: baseMsg, type: 'success' });
                }
                emit('reload');
                files.value = []; Object.assign(form, defaultForm());
                if (!keepOpen) showForm.value = false;
            } catch (e) { emit('toast', { message: 'No se pudo guardar el mantenimiento.', type: 'error' }); }
            finally { saving.value = false; }
        }

        async function deleteMaint(m) {
            if (!window.confirm('¿Eliminar este mantenimiento?')) return;
            try { await axios.delete(`${props.baseUrl}/api/mantenimientos/${m.id}`); emit('reload'); emit('toast', { message: 'Mantenimiento eliminado.', type: 'success' }); }
            catch (e) { emit('toast', { message: 'No se pudo eliminar.', type: 'error' }); }
        }

        return {
            maintenances, maintYearCount, maintSpendYear, maintAvg, nextServiceBanner,
            search, typeFilter, periodFilter, filtered,
            showForm, saving, drag, files, form, totalLive, onDrop, onPick, saveMaint, deleteMaint,
            showProviderForm, newProviderName, creatingProvider, createProvider,
            maintLabels, maintTypeLabel, maintIcon, worksCatalog, fmtMoney, fmtKm, fmtDate,
        };
    },
};
</script>

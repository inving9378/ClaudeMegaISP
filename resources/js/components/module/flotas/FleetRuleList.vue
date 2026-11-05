<template>
    <div class="flt-rule-wrap">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h4 class="mb-0"><i class="bi bi-funnel me-2 text-primary"></i>Reglas de alertas</h4>
            <button class="btn btn-primary" @click="openForm()"><i class="bi bi-plus-lg me-1"></i>Nueva regla</button>
        </div>

        <div class="alert alert-light border small">
            <i class="bi bi-info-circle me-1"></i>
            Las reglas son una <strong>capa opcional</strong>. Sin reglas activas recibes todas las alertas según las
            preferencias de cada vehículo. Con reglas, solo se notifica lo que <strong>al menos una</strong> regla permita.
        </div>

        <div v-if="loading" class="text-center text-muted py-5"><div class="spinner-border text-primary mb-2"></div><div>Cargando…</div></div>

        <div v-else-if="!rules.length" class="flt-rule-empty">
            <i class="bi bi-funnel"></i>
            <h6>No tienes reglas configuradas</h6>
            <p class="text-muted">Recibirás todas las notificaciones según tus preferencias de cada vehículo. Crea una regla para filtrar por horario, días, vehículo o geocerca.</p>
            <button class="btn btn-primary" @click="openForm()"><i class="bi bi-plus-lg me-1"></i>Crear regla</button>
        </div>

        <div v-else class="flt-rule-list">
            <div class="flt-rule-item" v-for="r in rules" :key="r.id">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold">{{ r.name }}
                        <span class="badge ms-1" :class="r.active ? 'bg-success' : 'bg-secondary'">{{ r.active ? 'Activa' : 'Inactiva' }}</span>
                    </div>
                    <div class="text-muted small text-truncate" v-if="r.description">{{ r.description }}</div>
                    <div class="flt-rule-badges mt-1">
                        <span v-if="isPortal" class="badge bg-primary-subtle text-dark border">{{ tipoLabel(r.tipo) }}<template v-if="tipoResumen(r)"> · {{ tipoResumen(r) }}</template></span>
                        <span class="badge bg-light text-dark border"><i class="bi bi-truck me-1"></i>{{ r.vehicle_ids.length ? r.vehicle_ids.length + ' vehículo(s)' : 'Todos' }}</span>
                        <span v-if="!isPortal || r.tipo === 'geocerca'" class="badge bg-light text-dark border"><i class="bi bi-bounding-box me-1"></i>{{ r.geofence_ids.length ? r.geofence_ids.length + ' geocerca(s)' : 'Todas' }}</span>
                        <span v-if="!isPortal || r.tipo === 'geocerca'" class="badge bg-light text-dark border">{{ r.event_types.map(eventLabel).join('/') }}</span>
                        <span class="badge bg-light text-dark border"><i class="bi bi-calendar3 me-1"></i>{{ daysLabel(r.days_of_week) }}</span>
                        <span class="badge bg-light text-dark border"><i class="bi bi-clock me-1"></i>{{ r.time_from && r.time_to ? r.time_from + '–' + r.time_to : 'Cualquier hora' }}</span>
                        <span class="badge bg-info-subtle text-dark border" v-for="c in r.channels" :key="c"><i class="bi me-1" :class="c==='whatsapp'?'bi-whatsapp':'bi-envelope'"></i>{{ c }}</span>
                    </div>
                </div>
                <div class="d-flex gap-1 align-items-center">
                    <div class="form-check form-switch m-0 me-1" :title="r.active ? 'Desactivar' : 'Activar'">
                        <input class="form-check-input" type="checkbox" :checked="r.active" @change="toggle(r)">
                    </div>
                    <button class="btn btn-sm btn-outline-primary" @click="openForm(r)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" @click="askDelete(r)"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </div>

        <!-- Modal crear/editar -->
        <div v-if="showForm" class="modal fade show flt-rule-modal" tabindex="-1" style="display:block" @click.self="showForm=false">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ form.id ? 'Editar regla' : 'Nueva regla' }}</h5>
                        <button type="button" class="btn-close" @click="showForm=false"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input class="form-control" v-model="form.name" maxlength="150" placeholder="Ej. Entradas nocturnas Frontier" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Descripción</label>
                            <textarea class="form-control" rows="2" v-model="form.description"></textarea>
                        </div>
                        <div v-if="isPortal" class="mb-3">
                            <label class="form-label fw-semibold">Tipo de regla <span class="text-danger">*</span></label>
                            <select class="form-select" v-model="form.tipo">
                                <option v-for="t in tipos" :key="t" :value="t">{{ tipoLabel(t) }}</option>
                            </select>
                            <div class="row g-2 mt-1">
                                <div class="col-md-6" v-if="form.tipo === 'velocidad'">
                                    <label class="form-label small">Velocidad máxima (km/h)</label>
                                    <input type="number" class="form-control form-control-sm" min="10" max="250" v-model.number="form.max_kmh">
                                </div>
                                <div class="col-md-6" v-if="form.tipo === 'sin_conexion'">
                                    <label class="form-label small">Minutos sin conexión</label>
                                    <input type="number" class="form-control form-control-sm" min="5" max="10080" v-model.number="form.minutos">
                                </div>
                                <div class="col-md-6" v-if="form.tipo === 'documento'">
                                    <label class="form-label small">Avisar días antes del vencimiento</label>
                                    <input type="number" class="form-control form-control-sm" min="1" max="365" v-model.number="form.dias_antes">
                                </div>
                                <template v-if="form.tipo === 'mantenimiento'">
                                    <div class="col-md-6">
                                        <label class="form-label small">Avisar km antes</label>
                                        <input type="number" class="form-control form-control-sm" min="50" max="20000" v-model.number="form.km_antes">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">Avisar días antes</label>
                                        <input type="number" class="form-control form-control-sm" min="1" max="365" v-model.number="form.mant_dias">
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Vehículos</label>
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="rAllVeh" v-model="form.all_vehicles"><label class="form-check-label" for="rAllVeh">Todos mis vehículos</label></div>
                                <select v-if="!form.all_vehicles" class="form-select form-select-sm mt-1" multiple size="4" v-model="form.vehicle_ids">
                                    <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.display_name }}</option>
                                </select>
                            </div>
                            <div class="col-md-6" v-if="!isPortal || form.tipo === 'geocerca'">
                                <label class="form-label fw-semibold">Geocercas</label>
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="rAllGeo" v-model="form.all_geofences"><label class="form-check-label" for="rAllGeo">Todas las geocercas</label></div>
                                <select v-if="!form.all_geofences" class="form-select form-select-sm mt-1" multiple size="4" v-model="form.geofence_ids">
                                    <option v-for="g in geofences" :key="g.id" :value="g.id">{{ g.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-4" v-if="!isPortal || form.tipo === 'geocerca'">
                                <label class="form-label fw-semibold">Tipo de evento</label>
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="rEnter" value="enter" v-model="form.event_types"><label class="form-check-label" for="rEnter">Entrada</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="rExit" value="exit" v-model="form.event_types"><label class="form-check-label" for="rExit">Salida</label></div>
                            </div>
                            <div :class="(!isPortal || form.tipo === 'geocerca') ? 'col-md-8' : 'col-12'">
                                <label class="form-label fw-semibold">Horario</label>
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="rAnyTime" v-model="form.any_time"><label class="form-check-label" for="rAnyTime">Cualquier hora</label></div>
                                <div v-if="!form.any_time" class="d-flex align-items-center gap-2 mt-1">
                                    <input type="time" class="form-control form-control-sm" v-model="form.time_from" style="max-width:130px">
                                    <span>→</span>
                                    <input type="time" class="form-control form-control-sm" v-model="form.time_to" style="max-width:130px">
                                    <small class="text-muted">(de mayor a menor = cruza medianoche)</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label fw-semibold">Días</label>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <button v-for="d in dayDefs" :key="d.iso" type="button" class="btn btn-sm"
                                        :class="form.days_of_week.includes(d.iso) ? 'btn-primary' : 'btn-outline-secondary'"
                                        @click="toggleDay(d.iso)">{{ d.short }}</button>
                                <span class="ms-2 small">
                                    <a href="#" @click.prevent="setDays([])">Todos</a> ·
                                    <a href="#" @click.prevent="setDays([1,2,3,4,5])">L-V</a> ·
                                    <a href="#" @click.prevent="setDays([6,7])">Fin de semana</a>
                                </span>
                            </div>
                            <small class="text-muted">Vacío = todos los días.</small>
                        </div>
                        <div class="mt-3">
                            <label class="form-label fw-semibold">Canales</label>
                            <div class="d-flex gap-3">
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="rChEmail" value="email" v-model="form.channels"><label class="form-check-label" for="rChEmail">Email</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="rChWa" value="whatsapp" v-model="form.channels"><label class="form-check-label" for="rChWa">WhatsApp</label></div>
                            </div>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" id="rActive" v-model="form.active"><label class="form-check-label" for="rActive">Regla activa</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" @click="showForm=false">Cancelar</button>
                        <button class="btn btn-primary" :disabled="saving" @click="save"><i class="bi bi-check2 me-1"></i>{{ saving ? 'Guardando…' : 'Guardar' }}</button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showForm" class="modal-backdrop fade show flt-rule-backdrop"></div>

        <!-- Modal eliminar -->
        <div v-if="toDelete" class="modal fade show flt-rule-modal" tabindex="-1" style="display:block" @click.self="toDelete=null">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title text-danger">¿Eliminar regla?</h5><button type="button" class="btn-close" @click="toDelete=null"></button></div>
                    <div class="modal-body">Eliminar <strong>{{ toDelete.name }}</strong>. Dejarás de filtrar con esta regla.</div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" @click="toDelete=null">Cancelar</button>
                        <button class="btn btn-danger" :disabled="deleting" @click="confirmDelete">{{ deleting ? 'Eliminando…' : 'Sí, eliminar' }}</button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="toDelete" class="modal-backdrop fade show flt-rule-backdrop"></div>

        <transition name="flt-toast-fade">
            <div v-if="toast.visible" class="flt-toast" :class="`flt-toast-${toast.type}`"><i :class="toast.icon" class="me-2"></i>{{ toast.message }}</div>
        </transition>
    </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

const DAY_DEFS = [
    { iso: 1, short: 'L' }, { iso: 2, short: 'M' }, { iso: 3, short: 'M' },
    { iso: 4, short: 'J' }, { iso: 5, short: 'V' }, { iso: 6, short: 'S' }, { iso: 7, short: 'D' },
];

export default {
    name: 'FleetRuleList',
    props: {
        baseUrl: { type: String, default: '/flotas' },
        // 'panel' (default) | 'portal': mismo criterio que FleetDashboard (#658). No se deduce de baseUrl,
        // que llega absoluto (url('/portal/flotas') = http://host/portal/flotas).
        modo: { type: String, default: 'panel' },
    },
    setup(props) {
        const rules = ref([]);
        const vehicles = ref([]);
        const geofences = ref([]);
        const loading = ref(true);
        const showForm = ref(false);
        const saving = ref(false);
        const toDelete = ref(null);
        const deleting = ref(false);

        // Modo portal (baseUrl=/portal/flotas): reglas del cliente con tipos de #644. El panel queda igual.
        const isPortal = props.modo === 'portal';
        const tipos = ref(['geocerca', 'velocidad', 'horario', 'sin_conexion', 'documento', 'mantenimiento']);
        const maxReglas = ref(null);
        const TIPO_LABELS = { geocerca: 'Geocerca', velocidad: 'Exceso de velocidad', horario: 'Uso fuera de horario',
            sin_conexion: 'Sin conexión', documento: 'Documento por vencer', mantenimiento: 'Mantenimiento próximo' };
        const tipoLabel = (t) => TIPO_LABELS[t] || t;
        function tipoResumen(r) {
            const p = r.params || {};
            if (r.tipo === 'velocidad') return `${p.max_kmh} km/h`;
            if (r.tipo === 'sin_conexion') return `${p.minutos} min`;
            if (r.tipo === 'documento') return `${p.dias_antes} días antes`;
            if (r.tipo === 'mantenimiento') return [p.km_antes ? `${p.km_antes} km` : null, p.dias_antes ? `${p.dias_antes} días` : null].filter(Boolean).join(' / ');
            return '';
        }

        const blankForm = () => ({
            id: null, name: '', description: '',
            tipo: 'geocerca', max_kmh: 100, minutos: 30, dias_antes: 30, km_antes: 500, mant_dias: null,
            all_vehicles: true, vehicle_ids: [],
            all_geofences: true, geofence_ids: [],
            event_types: ['enter', 'exit'],
            any_time: true, time_from: '22:00', time_to: '06:00',
            days_of_week: [], channels: ['email'], active: true,
        });
        const form = reactive(blankForm());

        const toast = reactive({ visible: false, message: '', type: 'success', icon: '' });
        function notify(message, type = 'success') {
            toast.message = message; toast.type = type;
            toast.icon = type === 'success' ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-circle-fill';
            toast.visible = true; setTimeout(() => { toast.visible = false; }, 3500);
        }

        const eventLabel = (e) => (e === 'enter' ? 'Entrada' : 'Salida');
        function daysLabel(days) {
            if (!days || !days.length) return 'Todos los días';
            if (days.length === 5 && [1,2,3,4,5].every(d => days.includes(d))) return 'L-V';
            if (days.length === 2 && days.includes(6) && days.includes(7)) return 'Fin de semana';
            return DAY_DEFS.filter(d => days.includes(d.iso)).map(d => d.short).join('');
        }

        function load() {
            loading.value = true;
            axios.get(`${props.baseUrl}/api/reglas`)
                .then(({ data }) => {
                    rules.value = (isPortal ? data?.reglas : data?.rules) ?? [];
                    if (isPortal) {
                        if (data?.tipos?.length) tipos.value = data.tipos;
                        maxReglas.value = data?.limite ?? null;
                    }
                })
                .catch(() => notify('No se pudieron cargar las reglas.', 'error'))
                .finally(() => { loading.value = false; });
        }
        function loadOptions() {
            // Selects auxiliares: un dropdown vacío no engaña al usuario, así que solo dejamos
            // rastro en consola (sin aviso intrusivo).
            axios.get(`${props.baseUrl}/api/vehiculos`).then(({ data }) => { vehicles.value = data?.vehicles ?? []; })
                .catch((e) => console.error('Flotas: fallo al cargar vehículos', e.response?.status, `${props.baseUrl}/api/vehiculos`));
            axios.get(`${props.baseUrl}/api/geocercas`).then(({ data }) => { geofences.value = data?.geofences ?? []; })
                .catch((e) => console.error('Flotas: fallo al cargar geocercas', e.response?.status, `${props.baseUrl}/api/geocercas`));
        }

        function openForm(rule = null) {
            Object.assign(form, blankForm());
            if (rule) {
                form.id = rule.id; form.name = rule.name; form.description = rule.description || '';
                if (isPortal) {
                    const p = rule.params || {};
                    form.tipo = rule.tipo || 'geocerca';
                    form.max_kmh = p.max_kmh ?? 100; form.minutos = p.minutos ?? 30; form.dias_antes = p.dias_antes ?? 30;
                    if (form.tipo === 'mantenimiento') { form.km_antes = p.km_antes ?? null; form.mant_dias = p.dias_antes ?? null; }
                }
                form.vehicle_ids = [...rule.vehicle_ids]; form.all_vehicles = rule.vehicle_ids.length === 0;
                form.geofence_ids = [...rule.geofence_ids]; form.all_geofences = rule.geofence_ids.length === 0;
                form.event_types = [...rule.event_types];
                form.any_time = !(rule.time_from && rule.time_to);
                form.time_from = rule.time_from || '22:00'; form.time_to = rule.time_to || '06:00';
                form.days_of_week = [...rule.days_of_week]; form.channels = [...rule.channels]; form.active = rule.active;
            }
            showForm.value = true;
        }

        const dayDefs = DAY_DEFS;
        function toggleDay(iso) {
            const i = form.days_of_week.indexOf(iso);
            if (i >= 0) form.days_of_week.splice(i, 1); else form.days_of_week.push(iso);
        }
        function setDays(arr) { form.days_of_week = [...arr]; }

        function save() {
            if (!form.name.trim()) { notify('El nombre es obligatorio.', 'error'); return; }
            const geo = !isPortal || form.tipo === 'geocerca';
            if (isPortal && form.tipo === 'horario' && form.any_time) { notify('Define el horario de la regla.', 'error'); return; }
            if (isPortal && form.tipo === 'mantenimiento' && !form.km_antes && !form.mant_dias) { notify('Indica km o días de aviso.', 'error'); return; }
            if (geo && !form.event_types.length) { notify('Selecciona al menos un tipo de evento.', 'error'); return; }
            if (!form.channels.length) { notify('Selecciona al menos un canal.', 'error'); return; }
            if (!form.any_time && (!form.time_from || !form.time_to)) { notify('Define desde y hasta, o marca "Cualquier hora".', 'error'); return; }

            const payload = {
                name: form.name.trim(), description: form.description || null,
                vehicle_ids: form.all_vehicles ? [] : form.vehicle_ids,
                geofence_ids: form.all_geofences ? [] : form.geofence_ids,
                event_types: form.event_types,
                time_from: form.any_time ? null : form.time_from,
                time_to: form.any_time ? null : form.time_to,
                days_of_week: form.days_of_week,
                channels: form.channels, active: form.active,
            };
            if (isPortal) {
                payload.tipo = form.tipo;
                if (form.tipo === 'velocidad') payload.max_kmh = form.max_kmh;
                if (form.tipo === 'sin_conexion') payload.minutos = form.minutos;
                if (form.tipo === 'documento') payload.dias_antes = form.dias_antes;
                if (form.tipo === 'mantenimiento') { payload.km_antes = form.km_antes || null; payload.mant_dias = form.mant_dias || null; }
                if (form.tipo !== 'geocerca') { payload.event_types = []; payload.geofence_ids = []; }
            }
            saving.value = true;
            const req = form.id
                ? axios.put(`${props.baseUrl}/api/reglas/${form.id}`, payload)
                : axios.post(`${props.baseUrl}/api/reglas`, payload);
            req.then(() => { notify(form.id ? 'Regla actualizada.' : 'Regla creada.'); showForm.value = false; load(); })
                .catch((e) => notify(Object.values(e?.response?.data?.errors || {})[0]?.[0] || e?.response?.data?.message || 'No se pudo guardar la regla.', 'error'))
                .finally(() => { saving.value = false; });
        }

        function toggle(r) {
            axios.post(`${props.baseUrl}/api/reglas/${r.id}/toggle`)
                .then(({ data }) => { r.active = data.active; })
                .catch(() => { notify('No se pudo cambiar el estado.', 'error'); load(); });
        }
        function askDelete(r) { toDelete.value = r; }
        function confirmDelete() {
            deleting.value = true;
            axios.delete(`${props.baseUrl}/api/reglas/${toDelete.value.id}`)
                .then(() => { notify('Regla eliminada.'); toDelete.value = null; load(); })
                .catch(() => notify('No se pudo eliminar.', 'error'))
                .finally(() => { deleting.value = false; });
        }

        onMounted(() => { load(); loadOptions(); });

        return { rules, vehicles, geofences, loading, showForm, saving, toDelete, deleting, form, toast,
                 dayDefs, isPortal, tipos, maxReglas, tipoLabel, tipoResumen, eventLabel, daysLabel, openForm, toggleDay, setDays, save, toggle, askDelete, confirmDelete };
    },
};
</script>

<style scoped>
.flt-rule-wrap { padding: 4px; }
.flt-rule-list { display: flex; flex-direction: column; gap: 8px; }
.flt-rule-item { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 16px; }
.flt-rule-item:hover { border-color: #cbd5e1; }
.flt-rule-badges { display: flex; flex-wrap: wrap; gap: 5px; }
.min-w-0 { min-width: 0; }
.flt-rule-empty { text-align: center; padding: 60px 20px; background: #fff; border: 1px dashed #cbd5e1; border-radius: 14px; }
.flt-rule-empty i { font-size: 3rem; color: #cbd5e1; display: block; margin-bottom: 10px; }
.flt-rule-modal { z-index: 9999; }
.flt-rule-modal .modal-content { border: none; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
/* Clase propia (NO la global .modal-backdrop): este <style> NO es scoped, y usar
   .modal-backdrop aquí leakeaba z-index:9998 a TODA la app, tapando ModalSimple/Swal
   de otras pantallas (candado de Permisos, Aplicar pago) con un gris permanente. */
.flt-rule-backdrop.show { z-index: 9998; opacity: .5; }
.flt-toast { position: fixed; bottom: 24px; right: 24px; z-index: 10001; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.15); display: flex; align-items: center; color: #fff; }
.flt-toast-success { background: #16a34a; }
.flt-toast-error { background: #dc2626; }
.flt-toast-fade-enter-active, .flt-toast-fade-leave-active { transition: all .25s; }
.flt-toast-fade-enter-from, .flt-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }
</style>

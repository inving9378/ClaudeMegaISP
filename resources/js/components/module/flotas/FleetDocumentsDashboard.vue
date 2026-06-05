<template>
    <div class="container-fluid py-3">

        <!-- Header -->
        <div class="d-flex align-items-center gap-3 mb-4">
            <a :href="`${baseUrl}`" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
            <h4 class="mb-0 fw-bold"><i class="bi bi-folder-check me-2 text-primary"></i>Documentos de la flota</h4>
            <button class="btn btn-sm btn-outline-secondary ms-auto" @click="load" :disabled="loading" title="Recargar">
                <i class="bi bi-arrow-clockwise" :class="{ 'spin': loading }"></i>
            </button>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-2">
                <div class="flt-mini-card flt-mini-red cursor-pointer" @click="setStatusFilter('vencido')">
                    <div class="flt-mini-label">Vencidos</div>
                    <div class="flt-mini-value">{{ kpis.vencidos ?? 0 }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="flt-mini-card flt-mini-amber cursor-pointer" @click="setStatusFilter('por_vencer')">
                    <div class="flt-mini-label">Por vencer 30d</div>
                    <div class="flt-mini-value">{{ kpis.por_vencer ?? 0 }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="flt-mini-card flt-mini-green cursor-pointer" @click="setStatusFilter('vigente')">
                    <div class="flt-mini-label">Vigentes</div>
                    <div class="flt-mini-value">{{ kpis.vigentes ?? 0 }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="flt-mini-card">
                    <div class="flt-mini-label">Total</div>
                    <div class="flt-mini-value">{{ kpis.total ?? 0 }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="flt-mini-card">
                    <div class="flt-mini-label">Costo del año</div>
                    <div class="flt-mini-value" style="font-size:1rem">{{ fmtMoney(kpis.costo_year ?? 0) }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="flt-mini-card">
                    <div class="flt-mini-label">Alertas hoy</div>
                    <div class="flt-mini-value">{{ kpis.alertas_hoy ?? 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <select class="form-select form-select-sm w-auto" v-model="filters.type" @change="load">
                        <option value="">Todos los tipos</option>
                        <option v-for="(label, key) in docTypeLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <select class="form-select form-select-sm w-auto" v-model="filters.status" @change="load">
                        <option value="">Todos los estados</option>
                        <option value="vencido">Vencidos</option>
                        <option value="por_vencer">Por vencer</option>
                        <option value="vigente">Vigentes</option>
                    </select>
                    <select class="form-select form-select-sm w-auto" v-model="filters.vehicle" @change="load" style="max-width:220px">
                        <option value="">Todos los vehículos</option>
                        <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.label }}</option>
                    </select>
                    <input class="form-control form-control-sm w-auto" style="min-width:160px"
                           type="text" placeholder="Buscar folio…"
                           v-model="filters.search" @keyup.enter="load" />
                    <button class="btn btn-sm btn-outline-secondary" @click="clearFilters">
                        <i class="bi bi-x-circle me-1"></i>Limpiar
                    </button>
                    <span v-if="loading" class="ms-2 text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando…</span>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div v-if="!loading && !documents.length" class="text-center text-muted py-5">
                    <i class="bi bi-folder2-open fs-1 d-block mb-2 opacity-25"></i>
                    <span v-if="hasFilters">No hay documentos que cumplan los filtros.</span>
                    <span v-else>No hay documentos registrados. Ve a un vehículo para crear el primero.</span>
                </div>

                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="sortable" @click="sortBy('vehicle')">
                                    Vehículo <i :class="sortIcon('vehicle')"></i>
                                </th>
                                <th class="sortable" @click="sortBy('document_type')">
                                    Tipo <i :class="sortIcon('document_type')"></i>
                                </th>
                                <th>Folio</th>
                                <th class="sortable" @click="sortBy('expiration_date')">
                                    Vencimiento <i :class="sortIcon('expiration_date')"></i>
                                </th>
                                <th class="sortable text-end" @click="sortBy('days_until_expiration')">
                                    Días <i :class="sortIcon('days_until_expiration')"></i>
                                </th>
                                <th class="sortable text-end" @click="sortBy('cost')">
                                    Costo <i :class="sortIcon('cost')"></i>
                                </th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in sorted" :key="d.id">
                                <td>
                                    <a :href="`${baseUrl}/${d.vehicle_id}?tab=documentos`" class="text-decoration-none fw-semibold">
                                        {{ vehicleLabel(d) }}
                                    </a>
                                </td>
                                <td>{{ docTypeLabels[d.document_type] || d.document_type }}</td>
                                <td class="text-muted">{{ d.folio_number || '—' }}</td>
                                <td>
                                    <span v-if="d.expiration_date">
                                        {{ fmtDate(d.expiration_date) }}
                                        <span class="badge ms-1" :class="badgeClass(d.status)">{{ badgeText(d) }}</span>
                                    </span>
                                    <span v-else class="text-muted">—</span>
                                </td>
                                <td class="text-end fw-semibold" :class="daysClass(d)">
                                    <span v-if="d.days_until_expiration !== null">{{ d.days_until_expiration }}</span>
                                    <span v-else class="text-muted">—</span>
                                </td>
                                <td class="text-end">{{ d.cost ? fmtMoney(d.cost) : '—' }}</td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a :href="`${baseUrl}/${d.vehicle_id}?tab=documentos`"
                                           class="btn btn-xs btn-outline-secondary" title="Ir al vehículo">
                                            <i class="bi bi-car-front"></i>
                                        </a>
                                        <a v-if="d.has_file"
                                           :href="`${baseUrl}/api/documentos/${d.id}/descargar`"
                                           target="_blank"
                                           class="btn btn-xs btn-outline-secondary" title="Descargar archivo">
                                            <i class="bi bi-file-earmark-arrow-down"></i>
                                        </a>
                                        <button class="btn btn-xs btn-outline-primary" title="Renovar" @click="openRenew(d)">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-if="documents.length" class="card-footer text-muted small">
                {{ sorted.length }} documento(s)
            </div>
        </div>

        <!-- Toast -->
        <div v-if="toast.show" class="position-fixed bottom-0 end-0 p-3" style="z-index:10001">
            <div class="toast show align-items-center text-white border-0"
                 :class="toast.type === 'success' ? 'bg-success' : 'bg-danger'">
                <div class="d-flex">
                    <div class="toast-body">{{ toast.message }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="toast.show=false"></button>
                </div>
            </div>
        </div>

        <!-- Modal Renovación -->
        <div v-if="renewModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Renovar documento</h5>
                        <button type="button" class="btn-close" @click="closeRenew" :disabled="renewSaving"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="renewModal.loading" class="text-center py-4">
                            <span class="spinner-border text-primary"></span>
                        </div>
                        <template v-else>
                            <!-- Banner del doc anterior -->
                            <div class="alert alert-info d-flex align-items-start gap-2 small mb-3">
                                <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
                                <div>
                                    Renovando <strong>{{ docTypeLabels[renewModal.prefilled.document_type] }}</strong>
                                    <template v-if="renewModal.prev.folio_number">
                                        · Folio anterior: <strong>{{ renewModal.prev.folio_number }}</strong>
                                    </template>
                                    <template v-if="renewModal.prev.expiration_date">
                                        · Venció: <strong>{{ renewModal.prev.expiration_date }}</strong>
                                    </template>
                                    <br><span class="text-muted">Llena los datos del <em>nuevo</em> documento. El tipo y la configuración de alertas se copiaron del anterior.</span>
                                </div>
                            </div>

                            <div class="row g-3">
                                <!-- Tipo (locked) -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Tipo</label>
                                    <div class="form-control bg-light text-muted">
                                        {{ docTypeLabels[renewModal.prefilled.document_type] }}
                                    </div>
                                </div>
                                <!-- Folio nuevo (required) -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Folio nuevo <span class="text-danger">*</span></label>
                                    <input class="form-control" :class="{'is-invalid': renewErrors.folio_number}"
                                           v-model="renewForm.folio_number" placeholder="Ej. 202401-A" />
                                    <div class="invalid-feedback">Requerido.</div>
                                </div>
                                <!-- Emisor -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Emisor</label>
                                    <input class="form-control" v-model="renewForm.issued_by" />
                                </div>
                                <!-- Fechas -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Fecha emisión</label>
                                    <input type="date" class="form-control" v-model="renewForm.issue_date" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Fecha vencimiento <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" :class="{'is-invalid': renewErrors.expiration_date}"
                                           v-model="renewForm.expiration_date" />
                                    <div class="invalid-feedback">Requerida.</div>
                                </div>
                                <!-- Costo -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Costo</label>
                                    <input type="number" step="0.01" class="form-control" v-model.number="renewForm.cost" />
                                </div>
                                <!-- Archivo -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Archivo <span class="text-muted fw-normal">(PDF, JPG o PNG — máx. 10 MB)</span></label>
                                    <div v-if="!renewFile">
                                        <input ref="renewFileInput" type="file" class="form-control"
                                               accept=".pdf,.jpg,.jpeg,.png" @change="handleRenewFile" />
                                    </div>
                                    <div v-else class="d-flex align-items-center gap-2 p-2 bg-light border rounded">
                                        <i class="bi bi-paperclip text-primary"></i>
                                        <span class="small flex-grow-1 text-truncate">{{ renewFile.name }} <span class="text-muted">({{ fmtFileSize(renewFile.size) }})</span></span>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" @click="clearRenewFile">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                    <div v-if="renewFileError" class="text-danger small mt-1">{{ renewFileError }}</div>
                                </div>
                                <!-- Alertas -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Alertas de vencimiento</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check"><input class="form-check-input" type="checkbox" id="ra30" v-model="renewForm.alert_30_days"><label class="form-check-label" for="ra30">30 días</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" id="ra7" v-model="renewForm.alert_7_days"><label class="form-check-label" for="ra7">7 días</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" id="ra1" v-model="renewForm.alert_1_day"><label class="form-check-label" for="ra1">1 día</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" id="ra0" v-model="renewForm.alert_same_day"><label class="form-check-label" for="ra0">El mismo día</label></div>
                                    </div>
                                </div>
                                <!-- Canales -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Canales</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check" v-for="ch in ['email','whatsapp','push','sms']" :key="ch">
                                            <input class="form-check-input" type="checkbox" :id="'rch'+ch" :value="ch" v-model="renewForm.alert_channels">
                                            <label class="form-check-label text-capitalize" :for="'rch'+ch">{{ ch }}</label>
                                        </div>
                                    </div>
                                </div>
                                <!-- Notas -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Notas</label>
                                    <textarea class="form-control" rows="2" v-model="renewForm.notes"></textarea>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" @click="closeRenew" :disabled="renewSaving">Cancelar</button>
                        <button class="btn btn-primary" @click="saveRenew" :disabled="renewSaving || renewModal.loading">
                            <span v-if="renewSaving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
                            <span v-else><i class="bi bi-check2 me-1"></i>Guardar renovación</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { useFleetFormatters } from './useFleetFormatters.js';

const MAX_FILE_BYTES = 10 * 1024 * 1024;
const ALLOWED_EXT   = /\.(pdf|jpg|jpeg|png)$/i;
const ALLOWED_MIME  = ['application/pdf', 'image/jpeg', 'image/png'];

export default {
    name: 'FleetDocumentsDashboard',
    props: {
        baseUrl: { type: String, default: '/flotas' },
    },
    setup(props) {
        const { fmtMoney, fmtDate, docTypeLabels, docIcon } = useFleetFormatters();

        // ── Estado principal ───────────────────────────────────────────────────
        const loading   = ref(false);
        const documents = ref([]);
        const kpis      = ref({});
        const vehicles  = ref([]);

        const filters = reactive({ type: '', status: '', vehicle: '', search: '' });
        const hasFilters = computed(() => filters.type || filters.status || filters.vehicle || filters.search);

        // ── Sort ───────────────────────────────────────────────────────────────
        const sortCol = ref('expiration_date');
        const sortDir = ref('asc');

        function sortBy(col) {
            if (sortCol.value === col) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
            else { sortCol.value = col; sortDir.value = 'asc'; }
        }

        function sortIcon(col) {
            if (sortCol.value !== col) return 'bi bi-chevron-expand text-muted opacity-50';
            return sortDir.value === 'asc' ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        }

        const sorted = computed(() => {
            const col = sortCol.value;
            const dir = sortDir.value === 'asc' ? 1 : -1;
            return [...documents.value].sort((a, b) => {
                let va = a[col] ?? '';
                let vb = b[col] ?? '';
                if (col === 'vehicle') { va = vehicleLabel(a); vb = vehicleLabel(b); }
                if (va === null || va === '') return 1;
                if (vb === null || vb === '') return -1;
                return va < vb ? -dir : va > vb ? dir : 0;
            });
        });

        // ── Helpers display ────────────────────────────────────────────────────
        function vehicleLabel(d) {
            const v = d.vehicle;
            if (!v) return `Vehículo #${d.vehicle_id}`;
            return [v.plate, v.brand, v.model].filter(Boolean).join(' · ');
        }

        const badgeClass = (s) => ({ vencido: 'bg-danger', por_vencer: 'bg-warning text-dark', vigente: 'bg-success' }[s] || 'bg-secondary');
        const badgeText  = (d) => {
            if (d.status === 'vencido') return 'VENCIDO';
            if (d.status === 'por_vencer') return 'POR VENCER';
            return 'Vigente';
        };
        const daysClass  = (d) => {
            if (d.days_until_expiration === null) return '';
            if (d.days_until_expiration < 0)  return 'text-danger';
            if (d.days_until_expiration <= 7)  return 'text-danger';
            if (d.days_until_expiration <= 30) return 'text-warning';
            return 'text-success';
        };

        function fmtFileSize(bytes) {
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        // ── Carga de datos ─────────────────────────────────────────────────────
        async function load() {
            loading.value = true;
            try {
                const params = {};
                if (filters.type)    params.type    = filters.type;
                if (filters.status)  params.status  = filters.status;
                if (filters.vehicle) params.vehicle = filters.vehicle;
                if (filters.search)  params.search  = filters.search;

                const { data } = await axios.get(`${props.baseUrl}/api/documentos/dashboard/all`, { params });
                documents.value = data.documents;
                kpis.value      = data.kpis;

                // Construir lista de vehículos únicos para el filtro
                const seen = new Set();
                vehicles.value = data.documents
                    .filter(d => { if (seen.has(d.vehicle_id)) return false; seen.add(d.vehicle_id); return true; })
                    .map(d => ({ id: d.vehicle_id, label: vehicleLabel(d) }))
                    .sort((a, b) => a.label.localeCompare(b.label));

            } catch (e) {
                showToast('Error al cargar documentos.', 'error');
            } finally {
                loading.value = false;
            }
        }

        function clearFilters() {
            Object.assign(filters, { type: '', status: '', vehicle: '', search: '' });
            load();
        }

        function setStatusFilter(s) {
            filters.status = filters.status === s ? '' : s;
            load();
        }

        // ── Toast ──────────────────────────────────────────────────────────────
        const toast = reactive({ show: false, message: '', type: 'success' });
        let toastTimer = null;
        function showToast(message, type = 'success') {
            clearTimeout(toastTimer);
            Object.assign(toast, { show: true, message, type });
            toastTimer = setTimeout(() => { toast.show = false; }, 4000);
        }

        // ── Renovación inteligente ─────────────────────────────────────────────
        const renewModal   = reactive({ show: false, loading: false, prefilled: {}, prev: {} });
        const renewSaving  = ref(false);
        const renewFileInput = ref(null);
        const renewFile    = ref(null);
        const renewFileError = ref('');
        const renewErrors  = reactive({ folio_number: false, expiration_date: false });
        const renewForm    = reactive({
            folio_number: '', issued_by: '', issue_date: '', expiration_date: '',
            cost: null, notes: '',
            alert_30_days: true, alert_7_days: true, alert_1_day: true, alert_same_day: false,
            alert_channels: ['email'],
        });
        let renewVehicleId = null;

        async function openRenew(doc) {
            renewModal.show    = true;
            renewModal.loading = true;
            renewModal.prefilled = {};
            renewModal.prev    = {};
            renewVehicleId     = doc.vehicle_id;
            clearRenewFile();
            Object.assign(renewErrors, { folio_number: false, expiration_date: false });

            try {
                const { data } = await axios.get(`${props.baseUrl}/api/documentos/${doc.id}/renovar/prefill`);
                renewModal.prefilled = data.prefilled;
                renewModal.prev      = data.previous_document;

                // Pre-llenar el form con datos copiados
                Object.assign(renewForm, {
                    folio_number:    '',
                    issued_by:       data.prefilled.issued_by     || '',
                    issue_date:      new Date().toISOString().slice(0, 10),
                    expiration_date: '',
                    cost:            null,
                    notes:           data.prefilled.notes         || '',
                    alert_30_days:   data.prefilled.alert_30_days,
                    alert_7_days:    data.prefilled.alert_7_days,
                    alert_1_day:     data.prefilled.alert_1_day,
                    alert_same_day:  data.prefilled.alert_same_day,
                    alert_channels:  data.prefilled.alert_channels ?? ['email'],
                });
            } catch (e) {
                showToast('Error al cargar datos de renovación.', 'error');
                renewModal.show = false;
            } finally {
                renewModal.loading = false;
            }
        }

        function closeRenew() {
            renewModal.show = false;
            clearRenewFile();
        }

        function handleRenewFile(e) {
            const file = e.target.files?.[0];
            renewFileError.value = '';
            renewFile.value = null;
            if (!file) return;
            if (!ALLOWED_EXT.test(file.name) || !ALLOWED_MIME.includes(file.type)) {
                renewFileError.value = 'Solo se permiten PDF, JPG o PNG.';
                return;
            }
            if (file.size > MAX_FILE_BYTES) {
                renewFileError.value = `El archivo pesa ${fmtFileSize(file.size)}, el máximo es 10 MB.`;
                return;
            }
            renewFile.value = file;
        }

        function clearRenewFile() {
            renewFile.value = null;
            renewFileError.value = '';
            if (renewFileInput.value) renewFileInput.value.value = '';
        }

        async function saveRenew() {
            // Validar campos requeridos
            renewErrors.folio_number    = !renewForm.folio_number?.trim();
            renewErrors.expiration_date = !renewForm.expiration_date;
            if (renewErrors.folio_number || renewErrors.expiration_date) return;
            if (renewFileError.value) return;

            renewSaving.value = true;
            try {
                const fd = new FormData();
                fd.append('vehicle_id',      renewVehicleId);
                fd.append('document_type',   renewModal.prefilled.document_type);
                fd.append('folio_number',    renewForm.folio_number.trim());
                fd.append('issued_by',       renewForm.issued_by    || '');
                fd.append('issue_date',      renewForm.issue_date   || '');
                fd.append('expiration_date', renewForm.expiration_date);
                fd.append('cost',            renewForm.cost         ?? '');
                fd.append('notes',           renewForm.notes        || '');
                fd.append('alert_30_days',   renewForm.alert_30_days  ? '1' : '0');
                fd.append('alert_7_days',    renewForm.alert_7_days   ? '1' : '0');
                fd.append('alert_1_day',     renewForm.alert_1_day    ? '1' : '0');
                fd.append('alert_same_day',  renewForm.alert_same_day ? '1' : '0');
                fd.append('alert_channels',  JSON.stringify(renewForm.alert_channels));
                if (renewFile.value) fd.append('file', renewFile.value);

                await axios.post(`${props.baseUrl}/api/documentos`, fd);
                closeRenew();
                await load();
                showToast('Renovación registrada correctamente.', 'success');
            } catch (e) {
                const msg = e.response?.data?.message || 'No se pudo guardar la renovación.';
                showToast(msg, 'error');
            } finally {
                renewSaving.value = false;
            }
        }

        onMounted(load);

        return {
            loading, documents, kpis, vehicles, filters, hasFilters,
            sortCol, sortDir, sorted, sortBy, sortIcon,
            vehicleLabel, badgeClass, badgeText, daysClass, fmtFileSize,
            setStatusFilter, clearFilters, load,
            toast, showToast,
            renewModal, renewSaving, renewFileInput, renewFile, renewFileError, renewErrors, renewForm,
            openRenew, closeRenew, handleRenewFile, clearRenewFile, saveRenew,
            docTypeLabels, docIcon, fmtMoney, fmtDate,
        };
    },
};
</script>

<style scoped>
.sortable { cursor: pointer; user-select: none; white-space: nowrap; }
.sortable:hover { background: rgba(0,0,0,.04); }
.btn-xs { padding: .15rem .35rem; font-size: .75rem; }
.cursor-pointer { cursor: pointer; }
.spin { animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

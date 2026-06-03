<template>
    <section>
        <div v-if="criticalCount" class="flt-banner flt-banner-red mb-3">
            <i class="bi bi-exclamation-octagon-fill me-2"></i>
            Hay {{ criticalCount }} documento(s) vencido(s) o por vencer que requieren atención.
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3"><div class="flt-mini-card flt-mini-red"><div class="flt-mini-label">Vencidos</div><div class="flt-mini-value">{{ counts.vencido }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="flt-mini-card flt-mini-amber"><div class="flt-mini-label">Por vencer 30d</div><div class="flt-mini-value">{{ counts.por_vencer }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="flt-mini-card flt-mini-green"><div class="flt-mini-label">Vigentes</div><div class="flt-mini-value">{{ counts.vigente }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="flt-mini-card"><div class="flt-mini-label">Costo del año</div><div class="flt-mini-value">{{ fmtMoney(costYear) }}</div></div></div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <select class="form-select form-select-sm w-auto" v-model="typeFilter">
                <option value="">Todos los tipos</option>
                <option v-for="(label, key) in docTypeLabels" :key="key" :value="key">{{ label }}</option>
            </select>
            <select class="form-select form-select-sm w-auto" v-model="statusFilter">
                <option value="">Todos los estados</option>
                <option value="vencido">Vencidos</option>
                <option value="por_vencer">Por vencer</option>
                <option value="vigente">Vigentes</option>
            </select>
            <button class="btn btn-sm btn-primary ms-auto" @click="showForm = !showForm">
                <i class="bi bi-plus-lg me-1"></i>Nuevo documento
            </button>
        </div>

        <div v-if="showForm" class="flt-inline-form mb-4">
            <div class="alert alert-light border d-flex align-items-center small mb-3">
                <i class="bi bi-robot me-2 text-primary"></i>
                <span><strong>Detección automática con IA (Fase 7)</strong> — pronto podrás subir el documento y se llenarán los campos solos.</span>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="form.document_type">
                        <option v-for="(label, key) in docTypeLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label fw-semibold">Folio</label><input class="form-control" v-model="form.folio_number" /></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Emisor</label><input class="form-control" v-model="form.issued_by" /></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Fecha emisión</label><input type="date" class="form-control" v-model="form.issue_date" /></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Fecha vencimiento</label><input type="date" class="form-control" v-model="form.expiration_date" /></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Costo</label><input type="number" step="0.01" class="form-control" v-model.number="form.cost" /></div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Alertas de vencimiento</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="a30" v-model="form.alert_30_days"><label class="form-check-label" for="a30">30 días</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="a7" v-model="form.alert_7_days"><label class="form-check-label" for="a7">7 días</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="a1" v-model="form.alert_1_day"><label class="form-check-label" for="a1">1 día</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="a0" v-model="form.alert_same_day"><label class="form-check-label" for="a0">El mismo día</label></div>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Canales</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check" v-for="ch in ['email','whatsapp','push','sms']" :key="ch">
                            <input class="form-check-input" type="checkbox" :id="'ch'+ch" :value="ch" v-model="form.alert_channels">
                            <label class="form-check-label text-capitalize" :for="'ch'+ch">{{ ch }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn btn-sm btn-outline-secondary me-2" @click="showForm = false">Cancelar</button>
                <button class="btn btn-sm btn-primary" :disabled="saving" @click="save">
                    <i class="bi bi-check2 me-1"></i>{{ saving ? 'Guardando…' : 'Guardar' }}
                </button>
            </div>
        </div>

        <div v-if="!filtered.length" class="text-muted small py-3">Sin documentos registrados.</div>
        <div class="flt-doc-list">
            <div class="flt-doc-item" :class="`flt-doc-${d._status}`" v-for="d in filtered" :key="d.id">
                <div class="flt-list-icon"><i :class="['bi', docIcon(d.document_type)]"></i></div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="fw-semibold">{{ docTypeLabels[d.document_type] || d.document_type }}</span>
                        <span class="badge" :class="badgeClass(d._status)">{{ badgeText(d) }}</span>
                    </div>
                    <div class="text-muted small">
                        <span v-if="d.issued_by">{{ d.issued_by }}</span>
                        <span v-if="d.folio_number"> · Folio {{ d.folio_number }}</span>
                        <span v-if="d.expiration_date"> · Vence {{ fmtDate(d.expiration_date) }}</span>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-primary" @click="renew(d)">
                    {{ d._status === 'vigente' ? 'Agendar' : 'Renovar' }}
                </button>
            </div>
        </div>
    </section>
</template>

<script>
import { ref, reactive, computed } from 'vue';
import axios from 'axios';
import { useFleetFormatters } from './useFleetFormatters.js';

export default {
    name: 'FleetTabDocumentos',
    props: {
        vehicle:   { type: Object, required: true },
        vehicleId: { type: [String, Number], required: true },
        baseUrl:   { type: String, default: '/flotas' },
    },
    emits: ['reload', 'toast'],
    setup(props, { emit }) {
        const { fmtMoney, fmtDate, docTypeLabels, docIcon, docStatus, docDays } = useFleetFormatters();

        const thisYear = new Date().getFullYear();
        const decorated = computed(() =>
            (props.vehicle?.documents ?? []).map((d) => ({ ...d, _status: docStatus(d), _days: docDays(d) }))
        );
        const counts = computed(() => {
            const c = { vencido: 0, por_vencer: 0, vigente: 0 };
            decorated.value.forEach((d) => { c[d._status] = (c[d._status] || 0) + 1; });
            return c;
        });
        const criticalCount = computed(() => counts.value.vencido + counts.value.por_vencer);
        const costYear = computed(() => decorated.value.filter((d) => d.issue_date && new Date(d.issue_date).getFullYear() === thisYear).reduce((s, d) => s + Number(d.cost || 0), 0));

        const typeFilter = ref(''); const statusFilter = ref('');
        const filtered = computed(() => decorated.value
            .filter((d) => !typeFilter.value || d.document_type === typeFilter.value)
            .filter((d) => !statusFilter.value || d._status === statusFilter.value)
            .slice().sort((a, b) => ({ vencido: 0, por_vencer: 1, vigente: 2 }[a._status] - { vencido: 0, por_vencer: 1, vigente: 2 }[b._status])));

        const badgeClass = (s) => ({ vencido: 'bg-danger', por_vencer: 'bg-warning text-dark', vigente: 'bg-success' }[s] || 'bg-secondary');
        const badgeText  = (d) => {
            if (d._status === 'vencido') return 'VENCIDA';
            if (d._status === 'por_vencer') return `VENCE EN ${d._days} DÍA${d._days === 1 ? '' : 'S'}`;
            return 'Vigente';
        };

        const showForm = ref(false); const saving = ref(false);
        const defaultForm = () => ({
            document_type: 'circulation_card', folio_number: '', issued_by: '',
            issue_date: '', expiration_date: '', cost: null,
            alert_30_days: true, alert_7_days: true, alert_1_day: true, alert_same_day: false, alert_channels: ['email'],
        });
        const form = reactive(defaultForm());

        async function save() {
            saving.value = true;
            try {
                const payload = { ...form, vehicle_id: Number(props.vehicleId) };
                Object.keys(payload).forEach((k) => { if (payload[k] === '') payload[k] = null; });
                await axios.post(`${props.baseUrl}/api/documentos`, payload);
                showForm.value = false; Object.assign(form, defaultForm());
                emit('reload'); emit('toast', { message: 'Documento registrado.', type: 'success' });
            } catch (e) { emit('toast', { message: 'No se pudo guardar el documento.', type: 'error' }); }
            finally { saving.value = false; }
        }

        function renew(d) {
            Object.assign(form, defaultForm(), { document_type: d.document_type, issued_by: d.issued_by || '' });
            showForm.value = true;
            emit('toast', { message: 'Captura los datos del documento renovado.', type: 'success' });
        }

        return {
            decorated, counts, criticalCount, costYear, typeFilter, statusFilter, filtered,
            badgeClass, badgeText, showForm, saving, form, save, renew,
            docTypeLabels, docIcon, fmtMoney, fmtDate,
        };
    },
};
</script>

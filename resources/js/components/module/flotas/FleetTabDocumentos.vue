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
            <button class="btn btn-sm btn-primary ms-auto" @click="openNewForm">
                <i class="bi bi-plus-lg me-1"></i>Nuevo documento
            </button>
        </div>

        <div v-if="showForm" class="flt-inline-form mb-4">
            <!-- #580 — Lectura por IA: adjunta el documento y se prellenan los campos. -->
            <div v-if="ocrRunning" class="alert alert-light border d-flex align-items-center small mb-3">
                <span class="spinner-border spinner-border-sm text-primary me-2"></span>
                <span>Leyendo el documento con IA… puedes seguir capturando a mano mientras tanto.</span>
            </div>

            <div v-else-if="ocrResult" class="mb-3">
                <div v-if="!ocrResult.ok" class="alert alert-warning border d-flex align-items-start small mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                    <div>
                        <strong>No se pudo leer el documento con IA.</strong>
                        <div>{{ ocrResult.error }}</div>
                        <div class="mt-1">Captura los datos a mano — el documento se guardará igual, marcado para revisión manual.</div>
                    </div>
                </div>

                <div v-else class="border rounded p-3 bg-light">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-robot me-2 text-primary"></i>
                        <strong class="small">Datos leídos del documento</strong>
                        <span class="badge ms-2" :class="ocrResult.needs_review ? 'bg-warning text-dark' : 'bg-success'">
                            {{ ocrResult.needs_review ? 'Requiere revisión' : 'Lectura confiable' }}
                        </span>
                        <button type="button" class="btn btn-sm btn-link ms-auto text-decoration-none py-0" @click="resetOcr">
                            Ocultar
                        </button>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6" v-for="f in readFields()" :key="f.key">
                            <div class="d-flex align-items-center small">
                                <span class="text-muted" style="min-width:150px">{{ f.label }}</span>
                                <span class="fw-semibold flex-grow-1">{{ f.value ?? '—' }}</span>
                                <span class="badge" :class="confBadge(f.confidence)">{{ confLabel(f.confidence) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="small text-muted mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        <span v-if="ocrResult.needs_review">
                            La <strong>fecha de vencimiento</strong> no se leyó con seguridad: verifícala antes de guardar
                            (es la que dispara las alertas). El documento se guardará marcado para revisión manual.
                        </span>
                        <span v-else>Revisa y corrige lo que haga falta. Nada se guarda hasta que presiones <strong>Guardar</strong>.</span>
                    </div>
                </div>
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

                <!-- Archivo adjunto -->
                <div class="col-12">
                    <label class="form-label fw-semibold">Archivo del documento <span class="text-muted fw-normal">(PDF, JPG o PNG — máx. 10 MB)</span></label>
                    <div v-if="!selectedFile">
                        <input ref="fileInput" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange" />
                    </div>
                    <div v-else class="d-flex align-items-center gap-2 p-2 bg-light border rounded">
                        <i class="bi bi-paperclip text-primary"></i>
                        <span class="small flex-grow-1 text-truncate">{{ selectedFile.name }} <span class="text-muted">({{ fmtFileSize(selectedFile.size) }})</span></span>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" @click="clearFile" title="Quitar archivo">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div v-if="fileError" class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ fileError }}</div>
                </div>

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
                <button class="btn btn-sm btn-outline-secondary me-2" @click="cancelForm">Cancelar</button>
                <button class="btn btn-sm btn-primary" :disabled="saving || !!fileError" @click="save">
                    <span v-if="saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
                    <span v-else><i class="bi bi-check2 me-1"></i>Guardar</span>
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
                    <!-- #580 — la IA no pudo leerlo con seguridad: alguien debe verificarlo. -->
                    <div v-if="d.ocr_needs_review" class="small text-warning-emphasis mt-1">
                        <i class="bi bi-robot me-1"></i>Revisar manualmente — la IA no leyó los datos con seguridad.
                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-decoration-none align-baseline"
                                :disabled="reviewing === d.id" @click="markReviewed(d)">
                            {{ reviewing === d.id ? 'Guardando…' : 'Ya lo revisé' }}
                        </button>
                    </div>
                </div>
                <div class="d-flex gap-1 align-items-center">
                    <a v-if="d.has_file"
                       :href="`${baseUrl}/api/documentos/${d.id}/descargar`"
                       target="_blank"
                       class="btn btn-sm btn-outline-secondary"
                       title="Ver / Descargar archivo">
                        <i class="bi bi-file-earmark-arrow-down"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-primary" @click="renew(d)">
                        {{ d._status === 'vigente' ? 'Agendar' : 'Renovar' }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
import { ref, reactive, computed } from 'vue';
import axios from 'axios';
import { useFleetFormatters } from './useFleetFormatters.js';
import { useFleetDocumentOcr } from './useFleetDocumentOcr.js';

const MAX_FILE_BYTES = 10 * 1024 * 1024; // 10 MB
const ALLOWED_MIME   = ['application/pdf', 'image/jpeg', 'image/png'];
const ALLOWED_EXT    = /\.(pdf|jpg|jpeg|png)$/i;

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

        // #580 — lectura por IA del documento adjunto (vía el módulo IA, servicio único).
        const {
            ocrRunning, ocrResult, ocrRunId,
            runOcr, resetOcr, applyToForm, readFields, confBadge, confLabel,
        } = useFleetDocumentOcr(props.baseUrl);

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
        const costYear = computed(() => decorated.value
            .filter((d) => d.issue_date && new Date(d.issue_date).getFullYear() === thisYear)
            .reduce((s, d) => s + Number(d.cost || 0), 0));

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

        // ── Archivo ────────────────────────────────────────────────────────────
        const fileInput    = ref(null);
        const selectedFile = ref(null);
        const fileError    = ref('');

        function handleFileChange(e) {
            const file = e.target.files?.[0];
            fileError.value = '';
            selectedFile.value = null;
            resetOcr();
            if (!file) return;
            if (!ALLOWED_EXT.test(file.name) || !ALLOWED_MIME.includes(file.type)) {
                fileError.value = 'Solo se permiten archivos PDF, JPG o PNG.';
                return;
            }
            if (file.size > MAX_FILE_BYTES) {
                fileError.value = `El archivo pesa ${fmtFileSize(file.size)}, el máximo es 10 MB.`;
                return;
            }
            selectedFile.value = file;

            // #580 — adjuntar el archivo dispara la lectura por IA. Best-effort: si falla, el
            // formulario sigue disponible tal cual y el guardado no se bloquea.
            runOcr(file, props.vehicleId).then((res) => {
                if (!res?.ok) return;
                // El tipo trae un default de pantalla ('circulation_card'), no una elección del
                // usuario -> sí se puede sobrescribir. El resto solo llena huecos.
                const aplicados = applyToForm(form, { overwriteKeys: ['document_type'] });
                if (aplicados.length) {
                    emit('toast', {
                        message: `IA: se prellenaron ${aplicados.length} campo(s). Revísalos antes de guardar.`,
                        type: 'success',
                    });
                }
            });
        }

        function clearFile() {
            selectedFile.value = null;
            fileError.value    = '';
            resetOcr();
            if (fileInput.value) fileInput.value.value = '';
        }

        function fmtFileSize(bytes) {
            if (bytes < 1024)       return bytes + ' B';
            if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        // ── Formulario ─────────────────────────────────────────────────────────
        const showForm = ref(false); const saving = ref(false);
        const defaultForm = () => ({
            document_type: 'circulation_card', folio_number: '', issued_by: '',
            issue_date: '', expiration_date: '', cost: null,
            alert_30_days: true, alert_7_days: true, alert_1_day: true, alert_same_day: false,
            alert_channels: ['email'],
        });
        const form = reactive(defaultForm());

        function openNewForm() { showForm.value = !showForm.value; }

        function cancelForm() {
            showForm.value = false;
            Object.assign(form, defaultForm());
            clearFile();
        }

        async function save() {
            if (fileError.value) return;
            saving.value = true;
            try {
                const fd = new FormData();
                fd.append('vehicle_id',    Number(props.vehicleId));
                fd.append('document_type', form.document_type);
                fd.append('folio_number',  form.folio_number  || '');
                fd.append('issued_by',     form.issued_by     || '');
                fd.append('issue_date',    form.issue_date    || '');
                fd.append('expiration_date', form.expiration_date || '');
                fd.append('cost',          form.cost ?? '');
                fd.append('alert_30_days', form.alert_30_days  ? '1' : '0');
                fd.append('alert_7_days',  form.alert_7_days   ? '1' : '0');
                fd.append('alert_1_day',   form.alert_1_day    ? '1' : '0');
                fd.append('alert_same_day',form.alert_same_day ? '1' : '0');
                fd.append('alert_channels', JSON.stringify(form.alert_channels));
                if (selectedFile.value) fd.append('file', selectedFile.value);
                // #580 — el backend lee el veredicto de la IA de su propia bitácora usando este id.
                if (ocrRunId.value) fd.append('ocr_run_id', ocrRunId.value);

                await axios.post(`${props.baseUrl}/api/documentos`, fd);
                showForm.value = false;
                Object.assign(form, defaultForm());
                clearFile();
                emit('reload');
                emit('toast', { message: 'Documento registrado.', type: 'success' });
            } catch (e) {
                const msg = e.response?.data?.message || 'No se pudo guardar el documento.';
                emit('toast', { message: msg, type: 'error' });
            } finally {
                saving.value = false;
            }
        }

        // #580 — apaga la marca "revisar manualmente" de un documento ya verificado por una persona.
        const reviewing = ref(null);
        async function markReviewed(d) {
            reviewing.value = d.id;
            try {
                await axios.post(`${props.baseUrl}/api/documentos/${d.id}/ocr/revisado`);
                emit('reload');
                emit('toast', { message: 'Documento marcado como revisado.', type: 'success' });
            } catch (e) {
                emit('toast', { message: 'No se pudo marcar como revisado.', type: 'error' });
            } finally {
                reviewing.value = null;
            }
        }

        async function renew(d) {
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/documentos/${d.id}/renovar/prefill`);
                const p = data.prefilled;
                Object.assign(form, defaultForm(), {
                    document_type:  p.document_type,
                    issued_by:      p.issued_by      || '',
                    notes:          p.notes          || '',
                    alert_30_days:  p.alert_30_days,
                    alert_7_days:   p.alert_7_days,
                    alert_1_day:    p.alert_1_day,
                    alert_same_day: p.alert_same_day,
                    alert_channels: p.alert_channels ?? ['email'],
                });
            } catch {
                // Si falla el prefill, abre igual con defaults básicos
                Object.assign(form, defaultForm(), { document_type: d.document_type, issued_by: d.issued_by || '' });
            }
            clearFile();
            showForm.value = true;
            emit('toast', { message: 'Captura los datos del documento renovado. Tipo y alertas copiados del anterior.', type: 'success' });
        }

        return {
            decorated, counts, criticalCount, costYear, typeFilter, statusFilter, filtered,
            badgeClass, badgeText,
            showForm, saving, form, openNewForm, cancelForm, save, renew,
            fileInput, selectedFile, fileError, handleFileChange, clearFile, fmtFileSize,
            docTypeLabels, docIcon, fmtMoney, fmtDate,
            ocrRunning, ocrResult, ocrRunId, resetOcr, readFields, confBadge, confLabel,
            reviewing, markReviewed,
        };
    },
};
</script>

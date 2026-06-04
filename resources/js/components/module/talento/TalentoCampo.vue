<template>
  <div class="talento-campo">

    <!-- LIST VIEW -->
    <template v-if="!selectedOrderId">
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h5 class="mb-0"><i class="fa fa-hard-hat me-2 text-primary"></i>Órdenes de Campo</h5>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-3">
          <input v-model="filters.search" @input="debounce" type="text"
                 class="form-control form-control-sm" placeholder="Buscar técnico o cliente…">
        </div>
        <div class="col-md-2">
          <select v-model="filters.status" @change="load" class="form-select form-select-sm">
            <option value="">Todos los estados</option>
            <option value="in_progress">En curso</option>
            <option value="completed">Completada</option>
            <option value="validated">Validada</option>
            <option value="pending_activation">Pend. Activación</option>
            <option value="active">Activa</option>
            <option value="survey_pending">Pend. Encuesta</option>
          </select>
        </div>
      </div>

      <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>OT#</th>
              <th>Técnico</th>
              <th>Tipo</th>
              <th>Agendada</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in orders" :key="o.id">
              <td class="text-muted small">{{ o.id }}</td>
              <td class="small fw-semibold">{{ o.colaborador?.user?.name }}</td>
              <td class="small">{{ o.type?.name }}</td>
              <td class="small">{{ fmtdt(o.scheduled_at) }}</td>
              <td><span class="badge" :class="statusColor(o.status)">{{ statusLabel(o.status) }}</span></td>
              <td>
                <button @click="openOrder(o.id)" class="btn btn-xs btn-primary">
                  <i class="fa fa-bolt me-1"></i>Ver flujo
                </button>
              </td>
            </tr>
            <tr v-if="!orders.length">
              <td colspan="6" class="text-center text-muted py-4">Sin órdenes de campo.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- FIELD FLOW DETAIL VIEW -->
    <template v-else>
      <div class="d-flex align-items-center gap-3 mb-3">
        <button @click="selectedOrderId=null; flow=null" class="btn btn-sm btn-outline-secondary">
          <i class="fa fa-arrow-left me-1"></i>Volver
        </button>
        <h5 class="mb-0">
          OT #{{ flow?.order?.id }} — {{ flow?.order?.type?.name }}
          <span class="ms-2 badge" :class="statusColor(flow?.order?.status)">{{ statusLabel(flow?.order?.status) }}</span>
        </h5>
      </div>

      <div v-if="loadingFlow" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <template v-else-if="flow">

        <!-- Colaborador + info rápida -->
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <div class="card border-0 shadow-sm p-3">
              <div class="d-flex gap-3 align-items-center">
                <i class="fa fa-id-badge fa-2x text-primary"></i>
                <div>
                  <div class="fw-bold">{{ flow.order?.colaborador?.user?.name }}</div>
                  <div class="small text-muted">
                    Agendada: {{ fmtdt(flow.order?.scheduled_at) }}
                    <span v-if="flow.order?.modem_sn" class="ms-3">SN: <code>{{ flow.order.modem_sn }}</code></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Progress stepper -->
          <div class="col-md-6">
            <div class="d-flex align-items-center justify-content-between gap-1 pt-2">
              <div v-for="(step, i) in steps" :key="i" class="text-center flex-fill">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                     :class="stepClass(step.status)"
                     style="width:28px;height:28px;font-size:12px;">
                  <i :class="step.icon"></i>
                </div>
                <div class="small text-muted mt-1" style="font-size:10px;">{{ step.label }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">

          <!-- COL IZQUIERDA: pasos del flujo -->
          <div class="col-md-8">

            <!-- PASO 1: Evidencia -->
            <div class="card border-0 shadow-sm mb-3">
              <div class="card-header bg-light d-flex align-items-center justify-content-between">
                <span><i class="fa fa-camera me-2"></i>Evidencia fotográfica</span>
                <span v-if="mediaComplete" class="badge bg-success"><i class="fa fa-check me-1"></i>Completa</span>
                <span v-else class="badge bg-warning text-dark">Pendiente</span>
              </div>
              <div class="card-body">
                <div class="row g-2">
                  <div v-for="mtype in mediaTypes" :key="mtype.key" class="col-6 col-md-4">
                    <div class="border rounded p-2 text-center small"
                         :class="mediaHas(mtype.key) ? 'border-success bg-success-subtle' : 'border-dashed bg-light'">
                      <i :class="['fa fa-2x mb-1 d-block', mtype.icon, mtype.sensitive ? 'text-warning' : 'text-primary']"></i>
                      <div>{{ mtype.label }}</div>
                      <div v-if="mediaHas(mtype.key)" class="text-success mt-1">
                        <i class="fa fa-check-circle"></i>
                        <span v-if="mediaGet(mtype.key)?.location_flagged" class="badge bg-warning text-dark ms-1">⚠️GPS</span>
                      </div>
                      <div v-else class="text-muted mt-1">Falta</div>
                    </div>
                  </div>
                </div>
                <!-- Upload form (admin) -->
                <div class="mt-3 border-top pt-3">
                  <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                      <select v-model="upload.type" class="form-select form-select-sm">
                        <option v-for="m in mediaTypes" :key="m.key" :value="m.key">{{ m.label }}</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <input type="file" ref="fileInput" @change="onFileChange" accept="image/*"
                             class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                      <button @click="uploadMedia" class="btn btn-sm btn-outline-primary" :disabled="upload.uploading || !upload.file">
                        <span v-if="upload.uploading"><span class="spinner-border spinner-border-sm me-1"></span></span>
                        <span v-else><i class="fa fa-upload me-1"></i>Subir</span>
                      </button>
                    </div>
                  </div>
                  <div v-if="upload.error" class="small text-danger mt-1">{{ upload.error }}</div>
                </div>
              </div>
            </div>

            <!-- PASO 2: Validación IA -->
            <div class="card border-0 shadow-sm mb-3">
              <div class="card-header bg-light d-flex align-items-center justify-content-between">
                <span><i class="fa fa-robot me-2"></i>Validación IA</span>
                <button @click="runIa" class="btn btn-xs btn-outline-secondary" :disabled="ia.running">
                  <span v-if="ia.running"><span class="spinner-border spinner-border-sm me-1"></span></span>
                  <span v-else><i class="fa fa-sync-alt me-1"></i>Ejecutar</span>
                </button>
              </div>
              <div class="card-body">
                <div v-if="!flow.ia_validation" class="text-muted small">Sin validación ejecutada aún.</div>
                <div v-else>
                  <div v-if="!flow.ia_validation.flags?.length" class="text-success small">
                    <i class="fa fa-check-circle me-1"></i>Sin banderas.
                  </div>
                  <div v-for="(flag, fi) in flow.ia_validation.flags" :key="fi"
                       class="d-flex align-items-start gap-2 mb-2 small">
                    <span class="badge mt-1" :class="flag.severity==='error'?'bg-danger':'bg-warning text-dark'">
                      {{ flag.severity === 'error' ? 'Error' : 'Aviso' }}
                    </span>
                    <span>{{ flag.issue }}</span>
                  </div>
                  <div v-if="!flow.ia_validation.overridden && flow.ia_validation.flags?.length" class="mt-2">
                    <div class="input-group input-group-sm">
                      <input v-model="ia.overrideReason" type="text" class="form-control" placeholder="Motivo del override…">
                      <button @click="overrideIa" class="btn btn-outline-warning" :disabled="!ia.overrideReason">
                        Confirmar override
                      </button>
                    </div>
                  </div>
                  <div v-else-if="flow.ia_validation.overridden" class="mt-1 text-muted small">
                    <i class="fa fa-check me-1 text-success"></i>Override: "{{ flow.ia_validation.override_reason }}"
                  </div>
                </div>
              </div>
            </div>

            <!-- PASO 3: Firmas -->
            <div class="card border-0 shadow-sm mb-3">
              <div class="card-header bg-light"><i class="fa fa-signature me-2"></i>Firmas</div>
              <div class="card-body d-flex gap-4">
                <div v-for="st in ['technician','client']" :key="st" class="flex-fill">
                  <div class="border rounded p-3 text-center" :class="hasSig(st) ? 'border-success bg-success-subtle' : 'bg-light'">
                    <i class="fa fa-pen-fancy fa-2x mb-2 d-block" :class="hasSig(st) ? 'text-success' : 'text-muted'"></i>
                    <div class="small fw-semibold">{{ st === 'technician' ? 'Técnico' : 'Cliente' }}</div>
                    <div v-if="hasSig(st)" class="small text-success mt-1">
                      <i class="fa fa-check-circle me-1"></i>{{ fmtdt(getSig(st)?.signed_at) }}
                    </div>
                    <div v-else class="small text-muted mt-1">Pendiente</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- PASO 4: Aceptar -->
            <div class="card border-0 shadow-sm mb-3">
              <div class="card-header bg-light"><i class="fa fa-check-double me-2"></i>Aceptar instalación</div>
              <div class="card-body">
                <div v-if="!canAccept" class="text-muted small">
                  <i class="fa fa-lock me-1"></i>Requiere ambas firmas
                  <span v-if="!iaCleared"> y validación IA resuelta</span>.
                </div>
                <div v-else>
                  <p class="small text-muted mb-2">
                    Al aceptar: se registra la ubicación GPS oficial, se transfiere la custodia del módem al cliente y la orden avanza a <strong>Pendiente de Activación</strong>.
                  </p>
                  <button @click="acceptOrder" class="btn btn-success" :disabled="accepting">
                    <span v-if="accepting"><span class="spinner-border spinner-border-sm me-1"></span></span>
                    <span v-else><i class="fa fa-check-double me-1"></i>Aceptar instalación</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- PASO 5: Activación (solo admin/activaciones) -->
            <div v-if="flow.order?.status === 'pending_activation' || flow.activation" class="card border-0 shadow-sm mb-3">
              <div class="card-header bg-light d-flex align-items-center justify-content-between">
                <span><i class="fa fa-bolt me-2"></i>Activación</span>
                <span v-if="flow.order?.activation_confirmed_at" class="badge bg-success">Activada</span>
                <span v-else class="badge bg-warning text-dark">Pendiente</span>
              </div>
              <div class="card-body">
                <div v-if="flow.order?.activation_confirmed_at" class="small text-success">
                  <i class="fa fa-check-circle me-1"></i>Activada por {{ flow.activation?.activated_by }}
                  el {{ fmtdt(flow.order.activation_confirmed_at) }}
                  <span v-if="flow.activation?.olt_dispatched" class="ms-2 badge bg-info text-dark">OLT ✓</span>
                </div>
                <div v-else>
                  <div class="d-flex align-items-center gap-3">
                    <div class="form-check mb-0">
                      <input v-model="activation.dispatchOlt" type="checkbox" class="form-check-input" id="dispOlt">
                      <label class="form-check-label small" for="dispOlt">Disparar OLT (OLTsService)</label>
                    </div>
                    <button @click="confirmActivation" class="btn btn-warning btn-sm" :disabled="activation.confirming">
                      <span v-if="activation.confirming"><span class="spinner-border spinner-border-sm me-1"></span></span>
                      <span v-else><i class="fa fa-bolt me-1"></i>Confirmar activación</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- PASO 6: Onboarding + Encuesta -->
            <div v-if="flow.order?.status === 'active' || flow.order?.status === 'survey_pending'" class="card border-0 shadow-sm mb-3">
              <div class="card-header bg-light"><i class="fa fa-user-plus me-2"></i>Onboarding + Encuesta</div>
              <div class="card-body">
                <div v-if="!onboarding.done && flow.order?.status === 'active'">
                  <p class="small text-muted mb-2">Genera la contraseña temporal del cliente y crea la encuesta de instalación.</p>
                  <button @click="doOnboard" class="btn btn-primary btn-sm" :disabled="onboarding.loading">
                    <span v-if="onboarding.loading"><span class="spinner-border spinner-border-sm me-1"></span></span>
                    <span v-else><i class="fa fa-user-plus me-1"></i>Generar onboarding</span>
                  </button>
                </div>
                <div v-if="onboarding.result" class="alert alert-success small py-2 mt-2">
                  <strong>Contraseña temporal:</strong> <code>{{ onboarding.result.temp_password }}</code>
                  (muéstrasela al cliente, se borrará al cerrar este panel)
                </div>
                <!-- Encuesta -->
                <div v-if="flow.order?.status === 'survey_pending' || flow.survey" class="mt-3 border-top pt-3">
                  <h6 class="small text-uppercase text-muted">Encuesta de satisfacción</h6>
                  <div v-if="flow.survey?.submitted_at" class="small text-success">
                    <i class="fa fa-check-circle me-1"></i>Respondida el {{ fmtdt(flow.survey.submitted_at) }}
                    · {{ flow.survey.rating_overall }}/5 general · {{ flow.survey.rating_technician }}/5 técnico
                  </div>
                  <div v-else>
                    <div class="row g-2 align-items-end">
                      <div class="col-md-2">
                        <label class="form-label form-label-sm">General</label>
                        <select v-model.number="survey.rating_overall" class="form-select form-select-sm">
                          <option value="">—</option>
                          <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                        </select>
                      </div>
                      <div class="col-md-2">
                        <label class="form-label form-label-sm">Técnico</label>
                        <select v-model.number="survey.rating_technician" class="form-select form-select-sm">
                          <option value="">—</option>
                          <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label form-label-sm">Comentarios</label>
                        <input v-model="survey.comments" type="text" class="form-control form-control-sm">
                      </div>
                      <div class="col-md-2 d-flex flex-column gap-1">
                        <div class="form-check form-check-sm">
                          <input v-model="survey.google_review_offered" type="checkbox" class="form-check-input">
                          <label class="form-check-label small">Ofreció G-Review</label>
                        </div>
                        <div class="form-check form-check-sm">
                          <input v-model="survey.google_review_opened" type="checkbox" class="form-check-input">
                          <label class="form-check-label small">Abrió enlace</label>
                        </div>
                      </div>
                      <div class="col-md-2">
                        <button @click="submitSurvey" class="btn btn-primary btn-sm w-100" :disabled="survey.submitting">
                          <span v-if="survey.submitting"><span class="spinner-border spinner-border-sm"></span></span>
                          <span v-else>Guardar</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div><!-- /col-izquierda -->

          <!-- COL DERECHA: fotos capturadas -->
          <div class="col-md-4">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-light small text-uppercase text-muted">Evidencia capturada</div>
              <div class="card-body p-2">
                <div v-if="!flow.media?.length" class="text-muted small text-center py-3">Sin fotos aún.</div>
                <div v-for="m in flow.media" :key="m.id" class="mb-2 border rounded overflow-hidden">
                  <div v-if="m.url" class="position-relative">
                    <img :src="m.url" class="img-fluid w-100" style="max-height:120px;object-fit:cover;" @error="m.url=null">
                    <span v-if="m.watermark_applied" class="position-absolute bottom-0 start-0 badge bg-dark bg-opacity-75 m-1" style="font-size:9px;">Marca de agua ✓</span>
                  </div>
                  <div v-else class="bg-light d-flex align-items-center justify-content-center" style="height:60px;">
                    <span class="small text-muted">{{ m.restricted ? '🔒 Restringido' : '—' }}</span>
                  </div>
                  <div class="px-2 py-1 d-flex justify-content-between align-items-center">
                    <span class="badge bg-light text-dark" style="font-size:10px;">{{ mediaLabel(m.type) }}</span>
                    <span v-if="m.location_flagged" class="badge bg-warning text-dark" style="font-size:10px;">⚠️GPS</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /row -->
      </template>
    </template>

  </div>
</template>

<script>
export default {
  name: 'TalentoCampo',
  data() {
    return {
      orders: [],
      loading: true,
      filters: { search: '', status: '' },
      debounceTimer: null,
      selectedOrderId: null,
      flow: null,
      loadingFlow: false,

      upload: { type: 'presentation', file: null, uploading: false, error: '' },
      ia: { running: false, overrideReason: '' },
      accepting: false,
      activation: { dispatchOlt: false, confirming: false },
      onboarding: { loading: false, done: false, result: null },
      survey: { rating_overall: '', rating_technician: '', comments: '', google_review_offered: false, google_review_opened: false, submitting: false },

      mediaTypes: [
        { key: 'presentation',  label: 'Presentación',  icon: 'fa-image',       sensitive: false },
        { key: 'completion',    label: 'Completado',    icon: 'fa-check-square', sensitive: false },
        { key: 'modem_sn',      label: 'SN Módem',      icon: 'fa-barcode',      sensitive: false },
        { key: 'ine_front',     label: 'INE Frente',    icon: 'fa-id-card',      sensitive: true  },
        { key: 'ine_back',      label: 'INE Reverso',   icon: 'fa-id-card',      sensitive: true  },
        { key: 'proof_address', label: 'Comprobante',   icon: 'fa-file-alt',     sensitive: true  },
      ],

      steps: [
        { label: 'Inicio',    icon: 'fa fa-play',        status: 'done' },
        { label: 'Evidencia', icon: 'fa fa-camera',      status: 'pending' },
        { label: 'IA',        icon: 'fa fa-robot',       status: 'pending' },
        { label: 'Firmas',    icon: 'fa fa-signature',   status: 'pending' },
        { label: 'Aceptar',   icon: 'fa fa-check-double',status: 'pending' },
        { label: 'Activación',icon: 'fa fa-bolt',        status: 'pending' },
        { label: 'Onboard',   icon: 'fa fa-user-plus',   status: 'pending' },
        { label: 'Encuesta',  icon: 'fa fa-star',        status: 'pending' },
      ],
    };
  },
  computed: {
    mediaComplete() {
      const required = ['presentation', 'completion'];
      return required.every(t => this.mediaHas(t));
    },
    iaCleared() {
      if (!this.flow?.ia_validation) return true;
      if (!this.flow.ia_validation.flags?.length) return true;
      return this.flow.ia_validation.overridden === true;
    },
    canAccept() {
      return this.hasSig('technician') && this.hasSig('client') && this.iaCleared;
    },
  },
  mounted() { this.load(); },
  methods: {
    debounce() {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => this.load(), 350);
    },
    async load() {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/ordenes', { params: { ...this.filters, per_page: 50 } });
        this.orders = data?.data ?? [];
      } finally { this.loading = false; }
    },
    async openOrder(id) {
      this.selectedOrderId = id;
      this.loadingFlow = true;
      try {
        const { data } = await axios.get(`/talento/api/campo/${id}/estado`);
        this.flow = data;
        this.updateSteps();
      } finally { this.loadingFlow = false; }
    },
    updateSteps() {
      if (!this.flow) return;
      const s = this.flow.order?.status;
      const steps = this.steps;
      const map = { 0: 'done', 1: this.mediaComplete?'done':'active', 2: this.flow.ia_validation?'done':'pending',
                    3: (this.hasSig('technician')&&this.hasSig('client'))?'done':'pending',
                    4: ['pending_activation','active','survey_pending','validated'].includes(s)?'done':'pending',
                    5: ['active','survey_pending','validated'].includes(s)?'done':'pending',
                    6: ['survey_pending','validated'].includes(s)?'done':'pending',
                    7: s==='validated'?'done':'pending' };
      Object.keys(map).forEach(i => { steps[i].status = map[i]; });
    },
    stepClass(status) {
      if (status === 'done')   return 'bg-success text-white';
      if (status === 'active') return 'bg-primary text-white';
      return 'bg-light text-muted border';
    },
    mediaHas(type) { return (this.flow?.media ?? []).some(m => m.type === type); },
    mediaGet(type) { return (this.flow?.media ?? []).find(m => m.type === type); },
    mediaLabel(t) { return this.mediaTypes.find(m => m.key === t)?.label ?? t; },
    hasSig(st) { return (this.flow?.signatures ?? []).some(s => s.signer_type === st); },
    getSig(st) { return (this.flow?.signatures ?? []).find(s => s.signer_type === st); },
    onFileChange(e) { this.upload.file = e.target.files[0] || null; },
    async uploadMedia() {
      if (!this.upload.file) return;
      this.upload.uploading = true; this.upload.error = '';
      try {
        const fd = new FormData();
        fd.append('file', this.upload.file);
        fd.append('type', this.upload.type);
        await axios.post(`/talento/api/campo/${this.selectedOrderId}/media`, fd, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
        this.upload.file = null;
        if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        await this.openOrder(this.selectedOrderId);
      } catch (e) {
        this.upload.error = e.response?.data?.message ?? 'Error al subir.';
      } finally { this.upload.uploading = false; }
    },
    async runIa() {
      this.ia.running = true;
      try {
        await axios.post(`/talento/api/campo/${this.selectedOrderId}/ia-validacion`);
        await this.openOrder(this.selectedOrderId);
      } finally { this.ia.running = false; }
    },
    async overrideIa() {
      if (!this.ia.overrideReason) return;
      await axios.post(`/talento/api/campo/ia-validacion/${this.flow.ia_validation.id}/override`, { reason: this.ia.overrideReason });
      this.ia.overrideReason = '';
      await this.openOrder(this.selectedOrderId);
    },
    async acceptOrder() {
      this.accepting = true;
      try {
        await axios.post(`/talento/api/campo/${this.selectedOrderId}/aceptar`);
        await this.openOrder(this.selectedOrderId);
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error al aceptar.');
      } finally { this.accepting = false; }
    },
    async confirmActivation() {
      this.activation.confirming = true;
      try {
        await axios.post(`/talento/api/campo/${this.selectedOrderId}/activar`, { dispatch_olt: this.activation.dispatchOlt });
        await this.openOrder(this.selectedOrderId);
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error al activar.');
      } finally { this.activation.confirming = false; }
    },
    async doOnboard() {
      this.onboarding.loading = true;
      try {
        const { data } = await axios.post(`/talento/api/campo/${this.selectedOrderId}/onboarding`);
        this.onboarding.result = data;
        this.onboarding.done = true;
        await this.openOrder(this.selectedOrderId);
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error en onboarding.');
      } finally { this.onboarding.loading = false; }
    },
    async submitSurvey() {
      this.survey.submitting = true;
      try {
        await axios.post(`/talento/api/campo/${this.selectedOrderId}/encuesta`, {
          rating_overall:       this.survey.rating_overall || null,
          rating_technician:    this.survey.rating_technician || null,
          comments:             this.survey.comments || null,
          google_review_offered: this.survey.google_review_offered,
          google_review_opened:  this.survey.google_review_opened,
        });
        await this.openOrder(this.selectedOrderId);
      } finally { this.survey.submitting = false; }
    },
    statusColor(s) {
      const m = { pending:'bg-secondary', in_progress:'bg-primary', completed:'bg-info text-dark',
                  validated:'bg-success', cancelled:'bg-danger',
                  pending_activation:'bg-warning text-dark', active:'bg-success',
                  survey_pending:'bg-info text-dark' };
      return m[s] ?? 'bg-light text-muted';
    },
    statusLabel(s) {
      const m = { pending:'Pendiente', in_progress:'En curso', completed:'Completada',
                  validated:'Validada', cancelled:'Cancelada',
                  pending_activation:'Pend. Activación', active:'Activa',
                  survey_pending:'Pend. Encuesta' };
      return m[s] ?? s;
    },
    fmtdt(d) {
      if (!d) return '—';
      return new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
  },
};
</script>

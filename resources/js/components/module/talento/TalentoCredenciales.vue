<template>
  <div class="talento-credenciales">

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'alerts' }" href="#" @click.prevent="tab='alerts'">
          <i class="fa fa-bell me-1"></i>Alertas de vencimiento
          <span v-if="alertCount" class="badge bg-danger ms-1">{{ alertCount }}</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'by_col' }" href="#" @click.prevent="tab='by_col'">
          <i class="fa fa-id-card me-1"></i>Por colaborador
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'funds' }" href="#" @click.prevent="tab='funds'">
          <i class="fa fa-piggy-bank me-1"></i>Fondos pendientes
          <span v-if="pendingFundsCount" class="badge bg-warning text-dark ms-1">{{ pendingFundsCount }}</span>
        </a>
      </li>
    </ul>

    <!-- ── TAB ALERTAS ── -->
    <div v-if="tab === 'alerts'">
      <div class="d-flex gap-2 mb-3 flex-wrap">
        <select v-model="alertFilter" @change="loadAlerts" class="form-select form-select-sm" style="width:180px">
          <option value="">Todos los estados</option>
          <option value="expiring">Por vencer</option>
          <option value="expired">Vencidas</option>
          <option value="missing">Sin registro</option>
        </select>
      </div>

      <div v-if="loadingAlerts" class="text-center py-5"><div class="spinner-border text-warning"></div></div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr><th>Técnico</th><th>Tipo</th><th>Núm. doc.</th><th>Vence</th><th>Días restantes</th><th>Estado</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="c in alertCredentials" :key="c.id">
              <td class="fw-semibold small">{{ c.collaborator_name ?? '—' }}</td>
              <td class="small">{{ typeLabel(c.type) }}</td>
              <td class="small text-muted">{{ c.document_number ?? '—' }}</td>
              <td class="small">{{ c.expires_at ? fmtDate(c.expires_at) : '—' }}</td>
              <td class="small">
                <span v-if="c.days_until_expiry != null"
                      :class="c.days_until_expiry < 0 ? 'text-danger fw-bold' : c.days_until_expiry < 56 ? 'text-warning fw-semibold' : 'text-success'">
                  {{ c.days_until_expiry < 0 ? Math.abs(c.days_until_expiry) + 'd vencida' : c.days_until_expiry + ' días' }}
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span class="badge" :class="credStatusColor(c.status)">{{ credStatusLabel(c.status) }}</span>
              </td>
              <td>
                <button @click="openEditCred(c)" class="btn btn-xs btn-outline-primary">
                  <i class="fa fa-pen"></i>
                </button>
              </td>
            </tr>
            <tr v-if="!alertCredentials?.length">
              <td colspan="7" class="text-center text-muted py-4">Sin alertas activas. ✅</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── TAB POR COLABORADOR ── -->
    <div v-if="tab === 'by_col'">
      <div class="row g-3 mb-3">
        <div class="col-md-5">
          <label class="form-label small text-muted">Colaborador</label>
          <select v-model="selectedColId" @change="loadColData" class="form-select">
            <option :value="null">— Seleccionar colaborador —</option>
            <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="loadingCol" class="text-center py-4"><div class="spinner-border text-primary"></div></div>

      <template v-if="selectedColId && !loadingCol">
        <!-- Credenciales del colaborador -->
        <h6 class="text-uppercase text-muted small mb-2">Credenciales</h6>
        <div v-if="colCredentials?.length" class="row g-3 mb-3">
          <div v-for="cred in colCredentials" :key="cred.id" class="col-md-5">
            <div class="card shadow-sm">
              <div class="card-body py-2">
                <div class="d-flex align-items-center justify-content-between mb-1">
                  <span class="fw-semibold small"><i class="fa fa-id-card me-1 text-primary"></i>{{ typeLabel(cred.type) }}</span>
                  <span class="badge" :class="credStatusColor(cred.status)">{{ credStatusLabel(cred.status) }}</span>
                </div>
                <div class="small text-muted">
                  Núm: <span class="text-dark">{{ cred.document_number ?? '—' }}</span>
                </div>
                <div class="small text-muted">
                  Vence: <span class="text-dark fw-semibold">{{ cred.expires_at ? fmtDate(cred.expires_at) : '—' }}</span>
                  <span v-if="cred.days_until_expiry != null" class="ms-2"
                        :class="cred.days_until_expiry < 0 ? 'text-danger' : cred.days_until_expiry < 56 ? 'text-warning' : 'text-success'">
                    ({{ cred.days_until_expiry < 0 ? Math.abs(cred.days_until_expiry) + 'd vencida' : cred.days_until_expiry + ' días' }})
                  </span>
                </div>
                <div v-if="cred.notes" class="small text-muted mt-1">{{ cred.notes }}</div>
              </div>
              <div class="card-footer p-1 d-flex gap-1">
                <a :href="`/talento/credential-doc/${cred.id}`" target="_blank"
                   class="btn btn-xs btn-outline-secondary flex-fill" title="Ver documento (cifrado)">
                  <i class="fa fa-lock me-1"></i>Documento
                </a>
                <button @click="openEditCred(cred)" class="btn btn-xs btn-outline-primary flex-fill">
                  <i class="fa fa-pen me-1"></i>Editar
                </button>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="alert alert-light border small mb-3">
          <i class="fa fa-exclamation-circle text-warning me-1"></i>
          Sin credenciales registradas para este colaborador.
        </div>
        <button @click="openNewCred" class="btn btn-sm btn-outline-primary mb-4">
          <i class="fa fa-plus me-1"></i>Registrar credencial
        </button>

        <!-- Fondos del colaborador -->
        <h6 class="text-uppercase text-muted small mb-2">Fondos de ahorro</h6>
        <div v-if="colFunds?.length" class="row g-3 mb-3">
          <div v-for="fund in colFunds" :key="fund.id" class="col-md-6">
            <div class="card shadow-sm" :class="!fund.authorized ? 'border-warning' : ''">
              <div class="card-body py-2">
                <div class="d-flex align-items-center justify-content-between mb-1">
                  <span class="fw-semibold small">
                    <i class="fa fa-piggy-bank me-1 text-info"></i>{{ purposeLabel(fund.purpose) }}
                  </span>
                  <span class="badge" :class="fundStatusColor(fund.status)">{{ fundStatusLabel(fund.status) }}</span>
                </div>
                <!-- Progreso -->
                <div class="d-flex align-items-center gap-2 my-2">
                  <div class="progress flex-fill" style="height:10px;">
                    <div class="progress-bar" :class="fund.status === 'ready' ? 'bg-success' : 'bg-info'"
                         :style="`width:${fund.progress_pct}%`"></div>
                  </div>
                  <span class="small fw-bold">{{ fund.progress_pct }}%</span>
                </div>
                <div class="small text-muted">
                  Acumulado: <strong class="text-dark">${{ fmt2(fund.accumulated) }}</strong>
                  de <strong>${{ fmt2(fund.target_amount) }}</strong>
                  · ${{ fmt2(fund.weekly_deduction) }}/sem
                </div>
                <div v-if="fund.weeks_remaining != null && fund.status === 'accumulating'" class="small text-muted mt-1">
                  ~{{ fund.weeks_remaining }} semanas restantes
                </div>
                <!-- Aviso autorización -->
                <div v-if="!fund.authorized" class="alert alert-warning py-1 small mt-2 mb-0">
                  <i class="fa fa-exclamation-triangle me-1"></i>
                  <strong>Sin autorizar.</strong> No se aparta nada hasta que el colaborador firme la autorización (LFT).
                </div>
                <div v-else class="small text-success mt-1">
                  <i class="fa fa-check-circle me-1"></i>
                  Autorizado — dinero del colaborador, se aparta en cada corte.
                </div>
              </div>
              <div class="card-footer p-1 d-flex gap-1">
                <button v-if="!fund.authorized" @click="openAuthorizeFund(fund)"
                        class="btn btn-xs btn-warning flex-fill">
                  <i class="fa fa-pen-fancy me-1"></i>Autorizar
                </button>
                <button v-if="fund.status === 'ready'" @click="markSpent(fund)"
                        class="btn btn-xs btn-outline-success flex-fill">
                  <i class="fa fa-check me-1"></i>Marcar usado
                </button>
                <span v-if="fund.authorized && fund.status === 'accumulating'"
                      class="btn btn-xs btn-outline-secondary flex-fill disabled">
                  Activo ✓
                </span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-muted small mb-3 fst-italic">Sin fondos de ahorro activos.</div>
        <button @click="openNewFund" class="btn btn-sm btn-outline-info">
          <i class="fa fa-plus me-1"></i>Crear fondo manualmente
        </button>
      </template>
    </div>

    <!-- ── TAB FONDOS PENDIENTES ── -->
    <div v-if="tab === 'funds'">
      <div v-if="loadingFunds" class="text-center py-5"><div class="spinner-border text-warning"></div></div>
      <div v-else>
        <div v-if="pendingFunds?.length" class="table-responsive">
          <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
              <tr><th>Técnico</th><th>Propósito</th><th>Objetivo</th><th>Acumulado</th><th>$/sem</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="f in pendingFunds" :key="f.id" class="table-warning-row">
                <td class="fw-semibold small">{{ f.collaborator_name ?? '—' }}</td>
                <td class="small">{{ purposeLabel(f.purpose) }}</td>
                <td class="small">${{ fmt2(f.target_amount) }}</td>
                <td class="small">
                  <div class="d-flex align-items-center gap-1">
                    <div class="progress flex-fill" style="height:6px;min-width:60px;">
                      <div class="progress-bar bg-info" :style="`width:${f.progress_pct}%`"></div>
                    </div>
                    <span>${{ fmt2(f.accumulated) }}</span>
                  </div>
                </td>
                <td class="small">${{ fmt2(f.weekly_deduction) }}</td>
                <td>
                  <span class="badge bg-warning text-dark">⚠ Sin autorizar</span>
                </td>
                <td>
                  <button @click="openAuthorizeFund(f)" class="btn btn-xs btn-warning">
                    <i class="fa fa-pen-fancy me-1"></i>Autorizar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center text-muted py-5">
          <i class="fa fa-check-circle fa-2x text-success mb-2"></i>
          <div>Sin fondos pendientes de autorización.</div>
        </div>
      </div>
    </div>

    <!-- ── MODAL CREDENCIAL (nueva / editar) ── -->
    <div v-if="credModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="fa fa-id-card me-2 text-primary"></i>
              {{ credModal.id ? 'Actualizar credencial' : 'Registrar credencial' }}
            </h5>
            <button @click="credModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-5" v-if="!credModal.id">
                <label class="form-label">Colaborador <span class="text-danger">*</span></label>
                <select v-model="credModal.colaborador_id" class="form-select">
                  <option :value="null">— Seleccionar —</option>
                  <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Tipo</label>
                <div class="d-flex gap-3 pt-2">
                  <div class="form-check">
                    <input v-model="credModal.type" type="radio" value="driver_license"
                           class="form-check-input" id="t-lic" :disabled="!!credModal.id">
                    <label for="t-lic" class="form-check-label small">Licencia de conducir</label>
                  </div>
                  <div class="form-check">
                    <input v-model="credModal.type" type="radio" value="other"
                           class="form-check-input" id="t-other" :disabled="!!credModal.id">
                    <label for="t-other" class="form-check-label small">Otro</label>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Núm. documento</label>
                <input v-model="credModal.document_number" type="text" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha emisión</label>
                <input v-model="credModal.issued_at" type="date" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha vencimiento <span class="text-danger">*</span></label>
                <input v-model="credModal.expires_at" type="date" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label">Alertar con (semanas)</label>
                <div class="input-group input-group-sm">
                  <input v-model.number="credModal.alert_weeks_before" type="number" min="1" max="52" class="form-control">
                  <span class="input-group-text">sem. antes</span>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Documento (foto/PDF)</label>
                <input type="file" class="form-control form-control-sm"
                       accept="image/*,.pdf" @change="onDocFileSelected" ref="docFileInput">
                <div class="small text-muted mt-1">
                  <i class="fa fa-lock me-1 text-primary"></i>
                  Se almacena cifrado (Crypt::encryptString). No se expone en URL pública.
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Notas</label>
                <input v-model="credModal.notes" type="text" class="form-control form-control-sm">
              </div>
              <div v-if="credModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ credModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="credModal.show=false" class="btn btn-secondary" :disabled="credModal.saving">Cancelar</button>
            <button @click="saveCred" class="btn btn-primary" :disabled="credModal.saving">
              <span v-if="credModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
              <span v-else>Guardar credencial</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL FONDO (nuevo) ── -->
    <div v-if="fundModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa fa-piggy-bank me-2 text-info"></i>Crear fondo de ahorro</h5>
            <button @click="fundModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info py-2 small mb-3">
              <i class="fa fa-info-circle me-1"></i>
              Este fondo es <strong>dinero del colaborador</strong> apartado a su favor (ahorro forzoso).
              No es un descuento punitivo. Se devuelve en el finiquito si no se usa.
              <strong>No se descuenta nada hasta autorización expresa (LFT).</strong>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Propósito</label>
                <select v-model="fundModal.purpose" class="form-select form-select-sm">
                  <option value="license">Renovación de licencia</option>
                  <option value="other">Otro</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Monto objetivo ($) <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text">$</span>
                  <input v-model.number="fundModal.target_amount" type="number" min="1" step="50" class="form-control">
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Deducción semanal ($) <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text">$</span>
                  <input v-model.number="fundModal.weekly_deduction" type="number" min="1" step="50" class="form-control">
                </div>
                <div v-if="fundModal.target_amount && fundModal.weekly_deduction" class="small text-muted mt-1">
                  ~{{ Math.ceil(fundModal.target_amount / fundModal.weekly_deduction) }} semanas para completar
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Notas</label>
                <input v-model="fundModal.notes" type="text" class="form-control form-control-sm">
              </div>
              <div v-if="fundModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ fundModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="fundModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="saveFund" class="btn btn-info" :disabled="fundModal.saving">
              <span v-if="fundModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Creando…</span>
              <span v-else>Crear fondo</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL AUTORIZAR FONDO ── -->
    <div v-if="authModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa fa-pen-fancy me-2 text-warning"></i>Autorizar fondo de ahorro</h5>
            <button @click="authModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="card border-0 bg-light mb-3">
              <div class="card-body py-2 small">
                <div><span class="text-muted">Técnico:</span> <strong>{{ authModal.fund?.collaborator_name ?? authModal.fund?.colaborador?.user?.name }}</strong></div>
                <div><span class="text-muted">Propósito:</span> {{ purposeLabel(authModal.fund?.purpose) }}</div>
                <div><span class="text-muted">Deducción:</span> <strong>${{ fmt2(authModal.fund?.weekly_deduction) }}/semana</strong> hasta ${{ fmt2(authModal.fund?.target_amount) }}</div>
              </div>
            </div>
            <div class="alert alert-warning py-2 small">
              <i class="fa fa-exclamation-triangle me-1"></i>
              Al autorizar confirmas que:
              <ul class="mb-0 mt-1 ps-3">
                <li>El colaborador firmó la autorización por escrito (Art. 110 LFT)</li>
                <li>El monto es <strong>ahorro del colaborador</strong>, NO una penalización</li>
                <li>Se aparta ${{ fmt2(authModal.fund?.weekly_deduction) }} del neto semanal a partir del próximo corte</li>
                <li>Si no se usa, se devuelve en el finiquito</li>
              </ul>
            </div>
            <div v-if="authModal.error" class="alert alert-danger py-2 small mb-0">{{ authModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button @click="authModal.show=false" class="btn btn-secondary" :disabled="authModal.saving">Cancelar</button>
            <button @click="confirmAuthorize" class="btn btn-warning" :disabled="authModal.saving">
              <span v-if="authModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Autorizando…</span>
              <span v-else><i class="fa fa-check me-1"></i>Confirmar — hay firma LFT</span>
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoCredenciales',
  data() {
    return {
      tab: 'alerts',

      // Alertas
      alertCredentials: [], loadingAlerts: true, alertFilter: '',

      // Por colaborador
      selectedColId: null, loadingCol: false,
      colCredentials: [], colFunds: [],
      colaboradores: [],

      // Fondos pendientes
      pendingFunds: [], loadingFunds: true,

      // Modals
      credModal: {
        show: false, id: null,
        colaborador_id: null, type: 'driver_license',
        document_number: '', issued_at: '', expires_at: '',
        alert_weeks_before: 8, notes: '', docFile: null,
        saving: false, error: '',
      },
      fundModal: {
        show: false,
        purpose: 'license', target_amount: 800, weekly_deduction: 100, notes: '',
        saving: false, error: '',
      },
      authModal: { show: false, fund: null, saving: false, error: '' },
    };
  },
  computed: {
    alertCount() {
      return (this.alertCredentials ?? []).filter(c => c.status === 'expired').length || null;
    },
    pendingFundsCount() {
      return this.pendingFunds?.length || null;
    },
  },
  mounted() {
    this.loadAlerts();
    this.loadColaboradores();
    this.loadPendingFunds();
  },
  methods: {
    async loadColaboradores() {
      const { data } = await axios.get('/talento/api/colaboradores', { params: { per_page: 300, status: 'active' } });
      this.colaboradores = data?.data ?? [];
    },
    async loadAlerts() {
      this.loadingAlerts = true;
      try {
        const params = this.alertFilter ? { status: this.alertFilter } : {};
        const { data } = await axios.get('/talento/api/credentials/expiring', { params });
        this.alertCredentials = data ?? [];
      } finally { this.loadingAlerts = false; }
    },
    async loadColData() {
      if (!this.selectedColId) return;
      this.loadingCol = true;
      this.colCredentials = [];
      this.colFunds = [];
      try {
        const [creds, funds] = await Promise.all([
          axios.get(`/talento/api/colaboradores/${this.selectedColId}/credentials`),
          axios.get(`/talento/api/colaboradores/${this.selectedColId}/funds`),
        ]);
        this.colCredentials = creds.data ?? [];
        this.colFunds = funds.data ?? [];
      } finally { this.loadingCol = false; }
    },
    async loadPendingFunds() {
      this.loadingFunds = true;
      try {
        const { data } = await axios.get('/talento/api/credentials/funds-alert');
        this.pendingFunds = data ?? [];
      } finally { this.loadingFunds = false; }
    },

    // ── Credencial ──────────────────────────────────────────────────────────
    openNewCred() {
      this.credModal = {
        show: true, id: null,
        colaborador_id: this.selectedColId, type: 'driver_license',
        document_number: '', issued_at: '', expires_at: '',
        alert_weeks_before: 8, notes: '', docFile: null,
        saving: false, error: '',
      };
    },
    openEditCred(cred) {
      this.credModal = {
        show: true, id: cred.id,
        colaborador_id: cred.colaborador_id, type: cred.type,
        document_number: cred.document_number ?? '',
        issued_at: cred.issued_at ?? '',
        expires_at: cred.expires_at ?? '',
        alert_weeks_before: cred.alert_weeks_before ?? 8,
        notes: cred.notes ?? '',
        docFile: null, saving: false, error: '',
      };
    },
    onDocFileSelected(e) { this.credModal.docFile = e.target.files[0] ?? null; },
    async saveCred() {
      this.credModal.error = '';
      if (!this.credModal.id && !this.credModal.colaborador_id) { this.credModal.error = 'Selecciona un colaborador.'; return; }
      if (!this.credModal.expires_at) { this.credModal.error = 'La fecha de vencimiento es requerida.'; return; }
      this.credModal.saving = true;
      try {
        const fd = new FormData();
        if (!this.credModal.id) {
          fd.append('colaborador_id', this.credModal.colaborador_id);
          fd.append('type', this.credModal.type);
        }
        if (this.credModal.document_number) fd.append('document_number', this.credModal.document_number);
        if (this.credModal.issued_at)       fd.append('issued_at', this.credModal.issued_at);
        fd.append('expires_at', this.credModal.expires_at);
        fd.append('alert_weeks_before', this.credModal.alert_weeks_before);
        if (this.credModal.notes) fd.append('notes', this.credModal.notes);
        if (this.credModal.docFile) fd.append('document_file', this.credModal.docFile);

        if (this.credModal.id) {
          await axios.put(`/talento/api/credentials/${this.credModal.id}`, fd,
            { headers: { 'Content-Type': 'multipart/form-data' } });
        } else {
          await axios.post('/talento/api/credentials', fd,
            { headers: { 'Content-Type': 'multipart/form-data' } });
        }
        this.credModal.show = false;
        this.loadAlerts();
        if (this.selectedColId) this.loadColData();
      } catch (e) {
        this.credModal.error = e.response?.data?.message ?? 'Error al guardar.';
      } finally { this.credModal.saving = false; }
    },

    // ── Fondo ───────────────────────────────────────────────────────────────
    openNewFund() {
      this.fundModal = {
        show: true, purpose: 'license', target_amount: 800,
        weekly_deduction: 100, notes: '', saving: false, error: '',
      };
    },
    async saveFund() {
      this.fundModal.error = '';
      if (!this.selectedColId) { this.fundModal.error = 'Selecciona un colaborador primero.'; return; }
      this.fundModal.saving = true;
      try {
        await axios.post('/talento/api/funds', {
          colaborador_id:   this.selectedColId,
          purpose:          this.fundModal.purpose,
          target_amount:    this.fundModal.target_amount,
          weekly_deduction: this.fundModal.weekly_deduction,
          notes:            this.fundModal.notes || null,
        });
        this.fundModal.show = false;
        this.loadColData();
        this.loadPendingFunds();
      } catch (e) {
        this.fundModal.error = e.response?.data?.message ?? 'Error al crear fondo.';
      } finally { this.fundModal.saving = false; }
    },
    openAuthorizeFund(fund) {
      this.authModal = { show: true, fund, saving: false, error: '' };
    },
    async confirmAuthorize() {
      this.authModal.saving = true;
      try {
        await axios.post(`/talento/api/funds/${this.authModal.fund.id}/authorize`);
        this.authModal.show = false;
        this.loadPendingFunds();
        if (this.selectedColId) this.loadColData();
      } catch (e) {
        this.authModal.error = e.response?.data?.error ?? 'Error al autorizar.';
      } finally { this.authModal.saving = false; }
    },
    async markSpent(fund) {
      if (!confirm(`¿Marcar el fondo de ${purposeLabel(fund.purpose)} como usado? El dinero acumulado se aplicará a la renovación.`)) return;
      try {
        await axios.post(`/talento/api/funds/${fund.id}/spent`);
        if (this.selectedColId) this.loadColData();
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error.');
      }
    },

    // ── Helpers ─────────────────────────────────────────────────────────────
    credStatusColor(s) {
      return { valid:'bg-success', expiring:'bg-warning text-dark', expired:'bg-danger', missing:'bg-secondary' }[s] ?? 'bg-light';
    },
    credStatusLabel(s) {
      return { valid:'✅ Vigente', expiring:'🟡 Por vencer', expired:'🔴 Vencida', missing:'⚫ Sin registro' }[s] ?? s;
    },
    fundStatusColor(s) {
      return { accumulating:'bg-info text-dark', ready:'bg-success', spent:'bg-secondary' }[s] ?? 'bg-light';
    },
    fundStatusLabel(s) {
      return { accumulating:'Acumulando', ready:'Listo para usar', spent:'Aplicado' }[s] ?? s;
    },
    typeLabel:    t => ({ driver_license:'Licencia de conducir', other:'Otro' }[t] ?? t),
    purposeLabel: p => ({ license:'Renovación de licencia', other:'Otro' }[p] ?? p),
    fmtDate(d) {
      if (!d) return '—';
      return new Date(d + 'T12:00:00').toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' });
    },
    fmt2: n => Number(n ?? 0).toLocaleString('es-MX', { minimumFractionDigits:2, maximumFractionDigits:2 }),
  },
};
</script>

<template>
  <div class="talento-cajas">

    <!-- Settings bono -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex align-items-center flex-wrap gap-3">
          <strong class="me-1"><i class="fa fa-cog text-primary me-2"></i>Bono de salud de red:</strong>
          <div class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 small">Monto</label>
            <div class="input-group input-group-sm" style="width:120px">
              <span class="input-group-text">$</span>
              <input v-model.number="settings.amount" type="number" min="0" step="5" class="form-control">
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 small">Pérdida máx.</label>
            <div class="input-group input-group-sm" style="width:100px">
              <input v-model.number="settings.maxLoss" type="number" min="0" max="10" step="0.1" class="form-control">
              <span class="input-group-text">dB</span>
            </div>
          </div>
          <button @click="saveSettings" class="btn btn-sm btn-outline-primary" :disabled="settings.saving">
            <span v-if="settings.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
            Guardar settings
          </button>
          <div class="ms-auto small text-muted">
            Ejemplos con pérdida máx {{ settings.maxLoss }}dB:
            <span class="text-success ms-1">baseline−21, cliente−21.8 → pérdida 0.8dB ≤ {{ settings.maxLoss }} → ✅ bono</span> ·
            <span class="text-danger ms-1">cliente−22.5 → pérdida 1.5dB > {{ settings.maxLoss }} → ❌ sin bono</span>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Tabla de baselines -->
      <div class="col-md-7">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0"><i class="fa fa-signal text-primary me-2"></i>Baselines por caja</h6>
          <button @click="openCreate" class="btn btn-sm btn-primary">
            <i class="fa fa-plus me-1"></i>Registrar
          </button>
        </div>
        <div class="mb-2">
          <input v-model="search" @input="debounce" type="text" class="form-control form-control-sm" placeholder="Buscar caja…" style="max-width:220px">
        </div>
        <div v-if="loading" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
        <div v-else class="table-responsive">
          <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
              <tr><th>Ref. caja</th><th>Baseline</th><th>Registrada</th><th>Notas</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="b in baselines" :key="b.id">
                <td class="font-monospace fw-semibold">{{ b.caja_ref }}</td>
                <td class="fw-bold" :class="parseFloat(b.baseline_power_dbm) < -25 ? 'text-danger' : 'text-success'">
                  {{ fmt1(b.baseline_power_dbm) }} dBm
                </td>
                <td class="small">{{ fmtdt(b.registered_at) }}</td>
                <td class="small text-muted">{{ b.notes ?? '—' }}</td>
                <td>
                  <button @click="openCreate(b.caja_ref)" class="btn btn-xs btn-outline-secondary">+ Nuevo</button>
                </td>
              </tr>
              <tr v-if="!baselines.length">
                <td colspan="5" class="text-center text-muted py-4">Sin baselines registradas.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Log de bonos -->
      <div class="col-md-5">
        <h6 class="mb-2"><i class="fa fa-award text-warning me-2"></i>Log de bonos recientes</h6>
        <div v-if="loadingLog" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
        <div v-else class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr><th>OT#</th><th>Caja</th><th>Pérdida</th><th>Bono</th></tr>
            </thead>
            <tbody>
              <tr v-for="l in bonusLog" :key="l.id" :class="l.bonus_awarded ? '' : 'text-muted'">
                <td class="small">{{ l.work_order_id }}</td>
                <td class="font-monospace small">{{ l.caja_ref ?? '—' }}</td>
                <td class="small">
                  <span v-if="l.loss_db !== null">
                    {{ fmt2(l.loss_db) }} dB
                    <span class="text-muted small">({{ fmt2(l.baseline_power_dbm) }} / {{ fmt2(l.client_power_dbm) }})</span>
                  </span>
                  <span v-else class="fst-italic">{{ l.skip_reason }}</span>
                </td>
                <td>
                  <span v-if="l.bonus_awarded" class="badge bg-success">${{ fmt2(l.bonus_amount) }}</span>
                  <span v-else class="badge bg-secondary">—</span>
                </td>
              </tr>
              <tr v-if="!bonusLog.length">
                <td colspan="4" class="text-muted small text-center py-2">Sin evaluaciones aún.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal registrar baseline -->
    <div v-if="modal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Registrar baseline de potencia</h5>
            <button @click="modal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Ref. caja (ODB) <span class="text-danger">*</span></label>
                <input v-model="modal.caja_ref" type="text" class="form-control" placeholder="Ej: 10 o ODB-10">
              </div>
              <div class="col-md-6">
                <label class="form-label">Potencia baseline (dBm) <span class="text-danger">*</span></label>
                <input v-model.number="modal.baseline_power_dbm" type="number" step="0.01" max="0" class="form-control"
                       placeholder="-21.50">
                <div class="form-text">Valor negativo, ej. -21.50</div>
              </div>
              <div class="col-12">
                <label class="form-label">Notas</label>
                <input v-model="modal.notes" type="text" class="form-control">
              </div>
              <div v-if="modal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ modal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="modal.show=false" class="btn btn-secondary" :disabled="modal.saving">Cancelar</button>
            <button @click="saveBaseline" class="btn btn-primary" :disabled="modal.saving">
              <span v-if="modal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
              <span v-else>Guardar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoCajas',
  data() {
    return {
      baselines: [],
      bonusLog: [],
      loading: true,
      loadingLog: true,
      search: '',
      debounceTimer: null,
      settings: { amount: 30, maxLoss: 1.0, saving: false },
      modal: { show: false, caja_ref: '', baseline_power_dbm: '', notes: '', saving: false, error: '' },
    };
  },
  mounted() {
    this.loadSettings();
    this.loadBaselines();
    this.loadBonusLog();
  },
  methods: {
    debounce() {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => this.loadBaselines(), 350);
    },
    async loadSettings() {
      try {
        const { data } = await axios.get('/talento/api/cajas/settings');
        this.settings.amount  = data?.health_bonus_amount ?? 30;
        this.settings.maxLoss = data?.health_bonus_max_loss_db ?? 1.0;
      } catch {}
    },
    async saveSettings() {
      this.settings.saving = true;
      try {
        await axios.put('/talento/api/cajas/settings', {
          health_bonus_amount: this.settings.amount,
          health_bonus_max_loss_db: this.settings.maxLoss,
        });
      } finally { this.settings.saving = false; }
    },
    async loadBaselines() {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/cajas/latest', { params: { search: this.search } });
        this.baselines = data ?? [];
      } finally { this.loading = false; }
    },
    async loadBonusLog() {
      this.loadingLog = true;
      try {
        const { data } = await axios.get('/talento/api/cajas/bonus-log', { params: { per_page: 30 } });
        this.bonusLog = data?.data ?? [];
      } finally { this.loadingLog = false; }
    },
    openCreate(cajaRef = '') {
      this.modal = { show: true, caja_ref: cajaRef, baseline_power_dbm: '', notes: '', saving: false, error: '' };
    },
    async saveBaseline() {
      this.modal.error = '';
      if (!this.modal.caja_ref) { this.modal.error = 'La referencia de caja es requerida.'; return; }
      if (this.modal.baseline_power_dbm === '' || this.modal.baseline_power_dbm >= 0) {
        this.modal.error = 'Ingresa un valor negativo de potencia (dBm).'; return;
      }
      this.modal.saving = true;
      try {
        await axios.post('/talento/api/cajas', {
          caja_ref: this.modal.caja_ref,
          baseline_power_dbm: this.modal.baseline_power_dbm,
          notes: this.modal.notes || null,
        });
        this.modal.show = false;
        this.loadBaselines();
      } catch (e) {
        this.modal.error = e.response?.data?.message ?? 'Error al guardar.';
      } finally { this.modal.saving = false; }
    },
    fmt1(n) { return Number(n ?? 0).toFixed(1); },
    fmt2(n) { return Number(n ?? 0).toFixed(2); },
    fmtdt(d) {
      if (!d) return '—';
      return new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
  },
};
</script>

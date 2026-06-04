<template>
  <div class="talento-compensacion">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0"><i class="fa fa-coins me-2 text-primary"></i>Compensación</h5>
      <button @click="openCreateRule" class="btn btn-primary btn-sm">
        <i class="fa fa-plus me-1"></i> Nueva regla
      </button>
    </div>

    <div class="row g-4">

      <!-- REGLAS DEFINIDAS -->
      <div class="col-12">
        <h6 class="text-uppercase text-muted small mb-2">Reglas definidas</h6>
        <div v-if="loadingRules" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>
        <div v-else class="table-responsive">
          <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Período</th>
                <th class="text-end">Sueldo base</th>
                <th class="text-center">Cuota/sem</th>
                <th class="text-end">$/unidad</th>
                <th class="text-center">Activa</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in rules" :key="r.id">
                <td class="fw-semibold">{{ r.name }}</td>
                <td><span class="badge bg-light text-dark">{{ targetLabel(r.target_type) }}</span></td>
                <td><span class="badge bg-light text-dark">{{ periodLabel(r.period) }}</span></td>
                <td class="text-end">${{ fmt(r.base_salary) }}</td>
                <td class="text-center">{{ r.weekly_quota_units }}</td>
                <td class="text-end text-success fw-semibold">${{ fmt(r.value_per_unit) }}</td>
                <td class="text-center">
                  <span class="badge" :class="r.active ? 'bg-success' : 'bg-secondary'">{{ r.active ? 'Sí' : 'No' }}</span>
                </td>
                <td>
                  <button @click="openEditRule(r)" class="btn btn-xs btn-outline-primary">Editar</button>
                </td>
              </tr>
              <tr v-if="!rules.length">
                <td colspan="8" class="text-center text-muted py-3">No hay reglas definidas. Crea la primera.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ASIGNAR REGLA -->
      <div class="col-12">
        <h6 class="text-uppercase text-muted small mb-2">Asignar regla a colaborador</h6>
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label form-label-sm">Colaborador</label>
                <input v-model="assign.search" @input="debounceAssignSearch" type="text"
                       class="form-control form-control-sm" placeholder="Buscar…">
                <ul v-if="assign.suggestions.length" class="list-group mt-1 position-absolute shadow" style="z-index:9999;max-height:160px;overflow-y:auto">
                  <li v-for="c in assign.suggestions" :key="c.id" @click="selectAssignCol(c)"
                      class="list-group-item list-group-item-action small cursor-pointer">
                    {{ c.user?.name }}
                  </li>
                </ul>
                <div v-if="assign.colaborador_id" class="mt-1 small text-success">
                  <i class="fa fa-check-circle me-1"></i>{{ assign.colaborador_name }}
                </div>
              </div>
              <div class="col-md-3">
                <label class="form-label form-label-sm">Regla</label>
                <select v-model="assign.rule_id" @change="onAssignRuleChange" class="form-select form-select-sm">
                  <option :value="null">— Seleccionar —</option>
                  <option v-for="r in activeRules" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label form-label-sm">Fecha efectiva</label>
                <input v-model="assign.assigned_at" type="date" class="form-control form-control-sm">
              </div>
              <div class="col-md-3 d-flex align-items-end gap-2">
                <button @click="saveAssign" class="btn btn-primary btn-sm" :disabled="assign.saving">
                  <span v-if="assign.saving"><span class="spinner-border spinner-border-sm"></span></span>
                  <span v-else>Asignar</span>
                </button>
              </div>
            </div>
            <div v-if="assign.rulePreview" class="mt-2 small text-muted">
              <i class="fa fa-info-circle me-1"></i>
              {{ assign.rulePreview.name }} — Base ${{ fmt(assign.rulePreview.base_salary) }},
              cuota {{ assign.rulePreview.weekly_quota_units }} ud/sem,
              ${{ fmt(assign.rulePreview.value_per_unit) }}/unidad
            </div>
            <div v-if="assign.error" class="mt-2 small text-danger">{{ assign.error }}</div>
            <div v-if="assign.success" class="mt-2 small text-success">{{ assign.success }}</div>
          </div>
        </div>
      </div>

      <!-- HISTORIAL DEL COLABORADOR SELECCIONADO -->
      <div v-if="assign.colaborador_id" class="col-12">
        <h6 class="text-uppercase text-muted small mb-2">Historial de asignaciones — {{ assign.colaborador_name }}</h6>
        <div v-if="assign.loadingHistory" class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary"></div></div>
        <div v-else class="table-responsive">
          <table class="table table-sm">
            <thead class="table-light">
              <tr>
                <th>Fecha efectiva</th>
                <th>Regla</th>
                <th class="text-end">Sueldo base</th>
                <th class="text-center">Cuota</th>
                <th class="text-end">$/ud</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="h in assign.history" :key="h.id">
                <td class="small">{{ formatDate(h.assigned_at) }}</td>
                <td>{{ h.data?.name }}</td>
                <td class="text-end">${{ fmt(h.data?.base_salary) }}</td>
                <td class="text-center">{{ h.data?.weekly_quota_units }}</td>
                <td class="text-end">${{ fmt(h.data?.weekly_quota_units > 0 ? h.data?.base_salary / h.data?.weekly_quota_units : 0) }}</td>
              </tr>
              <tr v-if="!assign.history.length">
                <td colspan="5" class="text-muted small text-center py-2">Sin historial.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal crear/editar regla -->
    <div v-if="ruleModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ ruleModal.id ? 'Editar regla' : 'Nueva regla de compensación' }}</h5>
            <button @click="ruleModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                <input v-model="ruleModal.name" type="text" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">Aplica a</label>
                <select v-model="ruleModal.target_type" class="form-select">
                  <option value="technician">Técnico</option>
                  <option value="seller">Vendedor</option>
                  <option value="counter">Mostrador</option>
                  <option value="all">Todos</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Período</label>
                <select v-model="ruleModal.period" class="form-select">
                  <option value="weekly">Semanal</option>
                  <option value="biweekly">Quincenal</option>
                  <option value="monthly">Mensual</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Sueldo base <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input v-model="ruleModal.base_salary" type="number" step="0.01" min="0" class="form-control">
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Cuota semanal (unidades)</label>
                <input v-model="ruleModal.weekly_quota_units" type="number" min="0" class="form-control">
              </div>
              <div class="col-md-4 d-flex flex-column justify-content-end">
                <div class="form-text">
                  $/unidad = ${{ fmt(ruleModal.weekly_quota_units > 0 ? ruleModal.base_salary / ruleModal.weekly_quota_units : 0) }}
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-check mt-4">
                  <input v-model="ruleModal.active" type="checkbox" class="form-check-input" id="ruleActive">
                  <label class="form-check-label" for="ruleActive">Activa</label>
                </div>
              </div>
              <div v-if="ruleModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ ruleModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="ruleModal.show=false" class="btn btn-secondary" :disabled="ruleModal.saving">Cancelar</button>
            <button @click="saveRule" class="btn btn-primary" :disabled="ruleModal.saving">
              <span v-if="ruleModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
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
  name: 'TalentoCompensacion',
  data() {
    return {
      rules: [],
      loadingRules: true,
      ruleModal: { show: false, id: null, name: '', target_type: 'technician', base_salary: 0,
                   period: 'weekly', weekly_quota_units: 0, active: true, error: '', saving: false },
      assign: {
        search: '', suggestions: [], colaborador_id: null, colaborador_name: '',
        rule_id: null, rulePreview: null, assigned_at: new Date().toISOString().substring(0,10),
        saving: false, error: '', success: '', history: [], loadingHistory: false,
      },
      assignSearchTimeout: null,
    };
  },
  computed: {
    activeRules() { return this.rules.filter(r => r.active); },
  },
  mounted() { this.loadRules(); },
  methods: {
    async loadRules() {
      this.loadingRules = true;
      try {
        const { data } = await axios.get('/talento/api/reglas');
        this.rules = data ?? [];
      } finally { this.loadingRules = false; }
    },
    openCreateRule() {
      this.ruleModal = { show: true, id: null, name: '', target_type: 'technician', base_salary: 0,
                         period: 'weekly', weekly_quota_units: 0, active: true, error: '', saving: false };
    },
    openEditRule(r) {
      this.ruleModal = { show: true, id: r.id, name: r.name, target_type: r.target_type,
                         base_salary: r.base_salary, period: r.period,
                         weekly_quota_units: r.weekly_quota_units, active: r.active, error: '', saving: false };
    },
    async saveRule() {
      this.ruleModal.error = '';
      if (!this.ruleModal.name) { this.ruleModal.error = 'El nombre es requerido.'; return; }
      this.ruleModal.saving = true;
      try {
        const payload = {
          name: this.ruleModal.name, target_type: this.ruleModal.target_type,
          base_salary: parseFloat(this.ruleModal.base_salary),
          period: this.ruleModal.period, weekly_quota_units: parseInt(this.ruleModal.weekly_quota_units),
          active: this.ruleModal.active,
        };
        if (this.ruleModal.id) {
          await axios.put(`/talento/api/reglas/${this.ruleModal.id}`, payload);
        } else {
          await axios.post('/talento/api/reglas', payload);
        }
        this.ruleModal.show = false;
        this.loadRules();
      } catch (e) {
        this.ruleModal.error = e.response?.data?.message ?? 'Error al guardar.';
      } finally { this.ruleModal.saving = false; }
    },
    debounceAssignSearch() {
      clearTimeout(this.assignSearchTimeout);
      this.assignSearchTimeout = setTimeout(() => this.searchAssignCols(), 300);
    },
    async searchAssignCols() {
      if (!this.assign.search.trim()) { this.assign.suggestions = []; return; }
      const { data } = await axios.get('/talento/api/colaboradores', { params: { search: this.assign.search, per_page: 10 } });
      this.assign.suggestions = data?.data ?? [];
    },
    selectAssignCol(c) {
      this.assign.colaborador_id = c.id;
      this.assign.colaborador_name = c.user?.name;
      this.assign.search = c.user?.name;
      this.assign.suggestions = [];
      this.loadHistory();
    },
    async loadHistory() {
      if (!this.assign.colaborador_id) return;
      this.assign.loadingHistory = true;
      try {
        const { data } = await axios.get(`/talento/api/colaboradores/${this.assign.colaborador_id}/regla/historial`);
        this.assign.history = data ?? [];
      } finally { this.assign.loadingHistory = false; }
    },
    onAssignRuleChange() {
      this.assign.rulePreview = this.rules.find(r => r.id === this.assign.rule_id) || null;
    },
    async saveAssign() {
      this.assign.error = '';
      this.assign.success = '';
      if (!this.assign.colaborador_id) { this.assign.error = 'Selecciona un colaborador.'; return; }
      if (!this.assign.rule_id) { this.assign.error = 'Selecciona una regla.'; return; }
      this.assign.saving = true;
      try {
        await axios.post(`/talento/api/colaboradores/${this.assign.colaborador_id}/regla`, {
          rule_id: this.assign.rule_id, assigned_at: this.assign.assigned_at,
        });
        this.assign.success = 'Regla asignada correctamente.';
        this.loadHistory();
      } catch (e) {
        this.assign.error = e.response?.data?.message ?? 'Error al asignar.';
      } finally { this.assign.saving = false; }
    },
    targetLabel(t) { return { technician: 'Técnico', seller: 'Vendedor', counter: 'Mostrador', all: 'Todos' }[t] ?? t; },
    periodLabel(p) { return { weekly: 'Semanal', biweekly: 'Quincenal', monthly: 'Mensual' }[p] ?? p; },
    fmt(n) { return Number(n ?? 0).toFixed(2); },
    formatDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    },
  },
};
</script>

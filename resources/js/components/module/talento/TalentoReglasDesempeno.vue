<template>
  <div class="talento-reglas-desempeno tc-wrap" :class="{ 'tc-dark': darkMode }">

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'rules' }" href="#" @click.prevent="tab='rules'">
          <i class="fa fa-balance-scale me-1"></i>Reglas
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'pending' }" href="#" @click.prevent="tab='pending'">
          <i class="fa fa-hourglass-half me-1"></i>Pendientes de confirmar
          <span v-if="pendingCount" class="tc-status is-warn ms-1">{{ pendingCount }}</span>
        </a>
      </li>
    </ul>

    <!-- ── TAB REGLAS ── -->
    <div v-if="tab === 'rules'">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0">Reglas por combinación de roles</h6>
        <button class="tc-btn tc-btn-primary" @click="openNewRule">
          <i class="fa fa-plus me-1"></i>Nueva regla
        </button>
      </div>

      <div v-if="loadingRules" class="text-center py-4"><i class="fa fa-spinner fa-spin"></i></div>
      <div v-else-if="rules.length === 0" class="text-center text-muted py-4">Sin reglas definidas todavía.</div>
      <div v-else class="table-responsive">
        <table class="table tc-table align-middle">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Combinación de roles</th>
              <th>Piso semanal</th>
              <th>Techo (bono)</th>
              <th>Penalización</th>
              <th>Activa</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rules" :key="r.id">
              <td>{{ r.name }}</td>
              <td>
                <span v-for="role in r.roles" :key="role" class="badge tc-badge me-1">{{ role }}</span>
              </td>
              <td>
                <span v-if="r.min_prospects !== null">{{ r.min_prospects }} prospectos</span>
                <span v-if="r.min_prospects !== null && r.min_sales !== null"> / </span>
                <span v-if="r.min_sales !== null">{{ r.min_sales }} ventas</span>
                <span v-if="r.min_prospects === null && r.min_sales === null" class="text-muted">—</span>
              </td>
              <td>
                <span v-if="r.bonus_prospects_threshold !== null">{{ r.bonus_prospects_threshold }} prospectos</span>
                <span v-if="r.bonus_prospects_threshold !== null && r.bonus_sales_threshold !== null"> / </span>
                <span v-if="r.bonus_sales_threshold !== null">{{ r.bonus_sales_threshold }} ventas</span>
                <span v-if="r.bonus_amount" class="text-muted ms-1">(${{ r.bonus_amount }})</span>
                <span v-if="!r.bonus_prospects_threshold && !r.bonus_sales_threshold" class="text-muted">—</span>
              </td>
              <td>{{ r.penalty_type?.name ?? '—' }}</td>
              <td>
                <span class="tc-status" :class="r.active ? 'is-ok' : 'is-off'">{{ r.active ? 'Activa' : 'Inactiva' }}</span>
              </td>
              <td class="text-end">
                <button class="tc-btn tc-btn-seg" @click="openEditRule(r)"><i class="fa fa-pen"></i></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── TAB PENDIENTES ── -->
    <div v-if="tab === 'pending'">
      <div v-if="loadingPending" class="text-center py-4"><i class="fa fa-spinner fa-spin"></i></div>
      <div v-else-if="pendingEvaluations.length === 0" class="text-center text-muted py-4">
        Sin penalizaciones pendientes de confirmar.
      </div>
      <div v-else class="table-responsive">
        <table class="table tc-table align-middle">
          <thead>
            <tr>
              <th>Colaborador</th>
              <th>Semana</th>
              <th>Prospectos</th>
              <th>Ventas</th>
              <th>Penalización sugerida</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ev in pendingEvaluations" :key="ev.id">
              <td>{{ ev.colaborador?.user?.name ?? '—' }}</td>
              <td>{{ formatDate(ev.week_start) }} – {{ formatDate(ev.week_end) }}</td>
              <td>{{ ev.prospects_count }}</td>
              <td>{{ ev.sales_count }}</td>
              <td>{{ ev.penalty_type?.name ?? '—' }} (${{ ev.penalty_amount }})</td>
              <td class="text-end">
                <button class="tc-btn tc-btn-bad" :disabled="confirmingId === ev.id" @click="confirmPenalty(ev)">
                  <i class="fa fa-check me-1"></i>Confirmar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── MODAL REGLA (crear / editar) ── -->
    <div v-if="ruleModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ ruleModal.id ? 'Editar regla' : 'Nueva regla de desempeño' }}</h5>
            <button @click="ruleModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                <input v-model="ruleModal.name" type="text" class="form-control" placeholder="Ej. Mostrador — semana estándar">
              </div>

              <div class="col-md-6">
                <label class="form-label">Rol base <span class="text-danger">*</span></label>
                <select v-model="ruleModal.baseRole" class="form-select tc-select">
                  <option value="" disabled>Elige un rol</option>
                  <option v-for="r in availableRoles" :key="r" :value="r">{{ r }}</option>
                </select>
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <div class="form-check">
                  <input v-model="ruleModal.tieneMasRoles" type="checkbox" class="form-check-input" id="rd-mas-roles">
                  <label for="rd-mas-roles" class="form-check-label">Tiene más roles</label>
                </div>
              </div>

              <div v-if="ruleModal.tieneMasRoles" class="col-12">
                <label class="form-label">Otros roles de la combinación</label>
                <q-select
                  v-model="ruleModal.otrosRoles"
                  dense options-dense outlined
                  :options="otrosRolesDisponibles"
                  multiple use-chips emit-value map-options clearable
                  hint="La regla aplicará solo a quien tenga EXACTAMENTE el rol base + estos"
                  @clear="ruleModal.otrosRoles = []"
                >
                  <template v-slot:option="scope">
                    <q-item v-bind="scope.itemProps">
                      <q-item dense>
                        <q-item-section>{{ scope.opt.label }}</q-item-section>
                        <q-item-section avatar>
                          <q-checkbox v-model="ruleModal.otrosRoles" :val="scope.opt.value" />
                        </q-item-section>
                      </q-item>
                    </q-item>
                  </template>
                </q-select>
              </div>

              <div class="col-12"><hr></div>

              <div class="col-md-6">
                <label class="form-label">Mínimo de prospectos / semana</label>
                <input v-model.number="ruleModal.min_prospects" type="number" min="0" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Mínimo de ventas / semana</label>
                <input v-model.number="ruleModal.min_sales" type="number" min="0" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label">Penalización si no se cumple el mínimo</label>
                <select v-model="ruleModal.penalty_type_id" class="form-select tc-select">
                  <option :value="null">Sin penalización</option>
                  <option v-for="t in penaltyTypes" :key="t.id" :value="t.id">{{ t.name }} (${{ t.amount }})</option>
                </select>
              </div>

              <div class="col-12"><hr></div>

              <div class="col-md-4">
                <label class="form-label">Techo de prospectos (bono)</label>
                <input v-model.number="ruleModal.bonus_prospects_threshold" type="number" min="0" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">Techo de ventas (bono)</label>
                <input v-model.number="ruleModal.bonus_sales_threshold" type="number" min="0" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">Monto del bono ($)</label>
                <input v-model.number="ruleModal.bonus_amount" type="number" min="0" step="50" class="form-control">
              </div>

              <div class="col-12 d-flex align-items-center gap-2">
                <input v-model="ruleModal.active" type="checkbox" class="form-check-input" id="rd-active">
                <label for="rd-active" class="form-check-label">Activa</label>
              </div>

              <div v-if="ruleModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ ruleModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="ruleModal.show=false" class="tc-btn tc-btn-seg">Cancelar</button>
            <button @click="saveRule" class="tc-btn tc-btn-primary" :disabled="ruleModal.saving">
              <i class="fa fa-spinner fa-spin me-1" v-if="ruleModal.saving"></i>Guardar
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios';
import { darkMode } from '../../../hook/appConfig.js';

// Mismo criterio de "rol de sistema" que ya usa el backend
// (TalentoColaboradorSyncService / SyncColaboradoresCommand) — se excluyen
// del selector porque nunca aplica una regla de desempeño de campo a ellos.
const SYSTEM_ROLES = ['super-administrator', 'ADMINISTRADOR_COMPLETO', 'DESARROLLADOR'];

export default {
  name: 'TalentoReglasDesempeno',
  setup() {
    return { darkMode };
  },
  data() {
    return {
      tab: 'rules',

      rules: [], loadingRules: true,
      allRoles: [], penaltyTypes: [],

      pendingEvaluations: [], loadingPending: true, confirmingId: null,

      ruleModal: this.emptyRuleModal(),
    };
  },
  computed: {
    availableRoles() {
      return this.allRoles.filter(r => !SYSTEM_ROLES.includes(r));
    },
    // Exactamente lo pedido: el multiselect de "otros roles" nunca muestra
    // el rol base ya elegido arriba.
    otrosRolesDisponibles() {
      return this.availableRoles
        .filter(r => r !== this.ruleModal.baseRole)
        .map(r => ({ label: r, value: r }));
    },
    pendingCount() {
      return this.pendingEvaluations.length;
    },
  },
  async mounted() {
    await Promise.all([this.loadRoles(), this.loadPenaltyTypes()]);
    this.loadRules();
    this.loadPending();
  },
  methods: {
    emptyRuleModal() {
      return {
        show: false, id: null, name: '',
        baseRole: '', tieneMasRoles: false, otrosRoles: [],
        min_prospects: null, min_sales: null, penalty_type_id: null,
        bonus_prospects_threshold: null, bonus_sales_threshold: null, bonus_amount: null,
        active: true, saving: false, error: '',
      };
    },
    formatDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX');
    },

    async loadRoles() {
      try {
        const { data } = await axios.get('/administracion/user/getRoles');
        this.allRoles = (data || []).map(r => r.name);
      } catch { this.allRoles = []; }
    },
    async loadPenaltyTypes() {
      try {
        const { data } = await axios.get('/talento/api/penalty-types', { params: { active_only: 1 } });
        this.penaltyTypes = data || [];
      } catch { this.penaltyTypes = []; }
    },

    async loadRules() {
      this.loadingRules = true;
      try {
        const { data } = await axios.get('/talento/api/reglas-desempeno');
        this.rules = data || [];
      } catch { this.rules = []; }
      finally { this.loadingRules = false; }
    },
    async loadPending() {
      this.loadingPending = true;
      try {
        const { data } = await axios.get('/talento/api/reglas-desempeno/evaluaciones', { params: { outcome: 'penalty_pending' } });
        this.pendingEvaluations = data?.data || [];
      } catch { this.pendingEvaluations = []; }
      finally { this.loadingPending = false; }
    },

    openNewRule() {
      this.ruleModal = this.emptyRuleModal();
      this.ruleModal.show = true;
    },
    openEditRule(r) {
      const roles = [...(r.roles || [])];
      const baseRole = roles[0] ?? '';
      const otros = roles.slice(1);
      this.ruleModal = {
        show: true, id: r.id, name: r.name,
        baseRole, tieneMasRoles: otros.length > 0, otrosRoles: otros,
        min_prospects: r.min_prospects, min_sales: r.min_sales,
        penalty_type_id: r.penalty_type_id,
        bonus_prospects_threshold: r.bonus_prospects_threshold,
        bonus_sales_threshold: r.bonus_sales_threshold,
        bonus_amount: r.bonus_amount,
        active: !!r.active, saving: false, error: '',
      };
    },
    async saveRule() {
      this.ruleModal.error = '';
      if (!this.ruleModal.name) { this.ruleModal.error = 'El nombre es requerido.'; return; }
      if (!this.ruleModal.baseRole) { this.ruleModal.error = 'Elige el rol base.'; return; }

      const roles = this.ruleModal.tieneMasRoles
        ? [this.ruleModal.baseRole, ...this.ruleModal.otrosRoles]
        : [this.ruleModal.baseRole];

      this.ruleModal.saving = true;
      try {
        const payload = {
          name: this.ruleModal.name,
          roles,
          min_prospects: this.ruleModal.min_prospects,
          min_sales: this.ruleModal.min_sales,
          penalty_type_id: this.ruleModal.penalty_type_id,
          bonus_prospects_threshold: this.ruleModal.bonus_prospects_threshold,
          bonus_sales_threshold: this.ruleModal.bonus_sales_threshold,
          bonus_amount: this.ruleModal.bonus_amount,
          active: this.ruleModal.active,
        };
        if (this.ruleModal.id) {
          await axios.put(`/talento/api/reglas-desempeno/${this.ruleModal.id}`, payload);
        } else {
          await axios.post('/talento/api/reglas-desempeno', payload);
        }
        this.ruleModal.show = false;
        this.loadRules();
      } catch (e) {
        this.ruleModal.error = e.response?.data?.message ?? 'Error al guardar.';
      } finally { this.ruleModal.saving = false; }
    },

    async confirmPenalty(ev) {
      if (!confirm(`¿Confirmar la penalización de ${ev.colaborador?.user?.name ?? 'este colaborador'}? Esto descuenta dinero real de inmediato.`)) return;
      this.confirmingId = ev.id;
      try {
        await axios.post(`/talento/api/reglas-desempeno/evaluaciones/${ev.id}/confirmar-penalizacion`);
        this.loadPending();
      } catch (e) {
        alert(e.response?.data?.message ?? 'Error al confirmar.');
      } finally { this.confirmingId = null; }
    },
  },
};
</script>

<style scoped>
.tc-badge {
  background: var(--tc-bg2, #eef1f4);
  color: inherit;
  font-weight: 500;
}
</style>

<template>
  <div class="talento-finiquito">

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'loans' }" href="#" @click.prevent="tab='loans'">
          <i class="fa fa-hand-holding-usd me-1"></i>Préstamos
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'settlement' }" href="#" @click.prevent="tab='settlement'">
          <i class="fa fa-file-invoice-dollar me-1"></i>Finiquito
        </a>
      </li>
    </ul>

    <!-- ── TAB PRÉSTAMOS ── -->
    <div v-if="tab === 'loans'">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="d-flex gap-2 flex-wrap">
          <select v-model="lFilters.colaborador_id" @change="loadLoans" class="form-select form-select-sm" style="width:200px">
            <option value="">Todos los técnicos</option>
            <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
          </select>
          <select v-model="lFilters.status" @change="loadLoans" class="form-select form-select-sm" style="width:140px">
            <option value="">Todos</option>
            <option value="active">Activos</option>
            <option value="paid">Pagados</option>
          </select>
        </div>
        <button @click="openNewLoan" class="btn btn-sm btn-primary">
          <i class="fa fa-plus me-1"></i>Registrar préstamo
        </button>
      </div>

      <div v-if="loadingLoans" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr><th>Técnico</th><th>Monto original</th><th>Saldo pendiente</th><th>$/sem</th><th>Motivo</th><th>Autorizado</th><th>Estado</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="loan in loans" :key="loan.id">
              <td class="fw-semibold small">{{ loan.colaborador?.user?.name ?? '—' }}</td>
              <td class="small">${{ fmt2(loan.amount) }}</td>
              <td class="small fw-bold" :class="parseFloat(loan.balance) > 0 ? 'text-danger' : 'text-success'">
                ${{ fmt2(loan.balance) }}
              </td>
              <td class="small text-muted">{{ loan.repayment_weekly ? '$' + fmt2(loan.repayment_weekly) : '—' }}</td>
              <td class="small text-muted text-truncate" style="max-width:160px" :title="loan.reason">{{ loan.reason ?? '—' }}</td>
              <td class="text-center">
                <i v-if="loan.authorized" class="fa fa-check-circle text-success"></i>
                <span v-else class="badge bg-warning text-dark small">Pendiente</span>
              </td>
              <td>
                <span class="badge" :class="loan.status === 'active' ? 'bg-primary' : 'bg-secondary'">
                  {{ loan.status === 'active' ? 'Activo' : 'Pagado' }}
                </span>
              </td>
              <td class="d-flex gap-1">
                <button v-if="!loan.authorized && loan.status === 'active'"
                        @click="authorizeLoan(loan)" class="btn btn-xs btn-warning" title="Autorizar">
                  <i class="fa fa-pen-fancy"></i>
                </button>
                <span v-if="loan.status === 'active' && loan.repayment_weekly" class="badge bg-light text-muted border small">
                  ~{{ weeksRemaining(loan) }}sem
                </span>
              </td>
            </tr>
            <tr v-if="!loans?.length">
              <td colspan="8" class="text-center text-muted py-4">Sin préstamos registrados.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── TAB FINIQUITO ── -->
    <div v-if="tab === 'settlement'">
      <!-- Selector de colaborador + botón calcular -->
      <div class="row g-3 mb-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label small text-muted">Colaborador</label>
          <select v-model="sColId" class="form-select">
            <option :value="null">— Seleccionar colaborador —</option>
            <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small text-muted">Fecha de separación</label>
          <input v-model="sDate" type="date" class="form-control">
        </div>
        <div class="col-md-3">
          <button @click="loadDraft" class="btn btn-primary w-100" :disabled="!sColId || loadingS">
            <span v-if="loadingS"><span class="spinner-border spinner-border-sm me-1"></span>Calculando…</span>
            <span v-else><i class="fa fa-calculator me-1"></i>Calcular borrador</span>
          </button>
        </div>
      </div>

      <div v-if="settlement">
        <!-- Badge estado -->
        <div class="d-flex align-items-center gap-2 mb-3">
          <span class="badge fs-6" :class="settlement.status === 'closed' ? 'bg-secondary' : 'bg-info text-dark'">
            {{ settlement.status === 'closed' ? '🔒 Cerrado' : '📋 Borrador' }}
          </span>
          <span class="small text-muted">Fecha: {{ fmtDate(settlement.settlement_date) }}</span>
          <span v-if="settlement.closed_at" class="small text-muted">
            · Cerrado: {{ fmtDate(settlement.closed_at) }}
          </span>
        </div>

        <!-- Resumen de cuenta corriente -->
        <div class="card mb-4 border-0 shadow-sm">
          <div class="card-header bg-light py-2">
            <strong class="small text-uppercase">Resumen de cuenta corriente</strong>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <tbody>
                <tr class="table-success">
                  <td colspan="2" class="fw-semibold small ps-3">CRÉDITOS a favor del colaborador</td>
                </tr>
                <tr>
                  <td class="ps-4 small text-muted">Salario, bonos, sobreproducción (ledger)</td>
                  <td class="text-end small text-success fw-semibold pe-3">+${{ fmt2(detail.ledger_credits) }}</td>
                </tr>
                <tr v-if="detail.funds_to_return > 0">
                  <td class="ps-4 small text-muted">Fondos de ahorro a devolver</td>
                  <td class="text-end small text-success pe-3">+${{ fmt2(detail.funds_to_return) }}</td>
                </tr>
                <tr class="fw-bold">
                  <td class="ps-3 small">TOTAL CRÉDITOS</td>
                  <td class="text-end text-success pe-3">+${{ fmt2(settlement.gross_credits) }}</td>
                </tr>

                <tr class="table-danger">
                  <td colspan="2" class="fw-semibold small ps-3 pt-2">DÉBITOS en su contra</td>
                </tr>
                <tr>
                  <td class="ps-4 small text-muted">Penalizaciones y otros cargos (ledger)</td>
                  <td class="text-end small text-danger pe-3">-${{ fmt2(detail.ledger_debits) }}</td>
                </tr>
                <tr v-if="detail.active_loan_balance > 0">
                  <td class="ps-4 small text-muted">Saldo de préstamos pendientes</td>
                  <td class="text-end small text-danger pe-3">-${{ fmt2(detail.active_loan_balance) }}</td>
                </tr>
                <tr v-if="detail.material_debits > 0">
                  <td class="ps-4 small text-muted">Material dañado / faltante</td>
                  <td class="text-end small text-danger pe-3">-${{ fmt2(detail.material_debits) }}</td>
                </tr>
                <tr class="fw-bold">
                  <td class="ps-3 small">TOTAL DÉBITOS</td>
                  <td class="text-end text-danger pe-3">-${{ fmt2(settlement.gross_debits) }}</td>
                </tr>

                <tr class="border-top-2">
                  <td colspan="2"><hr class="my-1 mx-3"></td>
                </tr>
                <tr class="fw-bold">
                  <td class="ps-3">NETO A LIQUIDAR</td>
                  <td class="text-end pe-3 fs-5" :class="settlement.net_settlement >= 0 ? 'text-success' : 'text-danger'">
                    {{ settlement.net_settlement >= 0 ? '+' : '' }}${{ fmt2(settlement.net_settlement) }}
                  </td>
                </tr>
                <tr v-if="settlement.net_settlement < 0">
                  <td colspan="2" class="ps-3 pb-2">
                    <small class="text-danger"><i class="fa fa-exclamation-triangle me-1"></i>
                      El colaborador debe ${{ fmt2(Math.abs(settlement.net_settlement)) }} a la empresa.
                    </small>
                  </td>
                </tr>
                <tr v-else-if="settlement.net_settlement > 0">
                  <td colspan="2" class="ps-3 pb-2">
                    <small class="text-success"><i class="fa fa-check-circle me-1"></i>
                      La empresa debe ${{ fmt2(settlement.net_settlement) }} al colaborador.
                    </small>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Material en custodia -->
        <div class="card mb-4 border-0 shadow-sm">
          <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
            <strong class="small text-uppercase">Material en custodia</strong>
            <span class="badge bg-info text-dark">{{ settlement.items?.length ?? 0 }} ítems</span>
          </div>
          <div class="card-body p-0">
            <div v-if="!settlement.items?.length" class="text-center text-muted py-3 small">
              Sin material en custodia.
            </div>
            <div v-else class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr><th>Ítem</th><th>Cant.</th><th>Costo unit.</th><th>Disposición</th><th>Cargo</th><th>Notas</th><th v-if="settlement.status==='draft'"></th></tr>
                </thead>
                <tbody>
                  <tr v-for="item in settlement.items" :key="item.id">
                    <td class="small fw-semibold">{{ item.item_name }}</td>
                    <td class="small">{{ item.current_stock }}</td>
                    <td class="small text-muted">${{ fmt2(item.unit_cost) }}</td>
                    <td>
                      <div v-if="settlement.status === 'draft'" class="d-flex gap-2 flex-wrap">
                        <div v-for="d in ['returned','damaged','missing']" :key="d" class="form-check form-check-inline mb-0">
                          <input :id="`d-${item.id}-${d}`" type="radio"
                                 class="form-check-input"
                                 :checked="item.disposition === d"
                                 @change="setDisposition(item, d)">
                          <label :for="`d-${item.id}-${d}`" class="form-check-label small"
                                 :class="d==='returned'?'text-success':d==='damaged'?'text-warning':'text-danger'">
                            {{ d==='returned' ? 'Devuelto' : d==='damaged' ? 'Dañado' : 'Faltante' }}
                          </label>
                        </div>
                      </div>
                      <span v-else class="badge"
                            :class="item.disposition==='returned'?'bg-success':item.disposition==='damaged'?'bg-warning text-dark':'bg-danger'">
                        {{ item.disposition==='returned' ? 'Devuelto' : item.disposition==='damaged' ? 'Dañado' : 'Faltante' }}
                      </span>
                    </td>
                    <td class="small fw-semibold" :class="parseFloat(item.debit_amount) > 0 ? 'text-danger' : 'text-muted'">
                      {{ parseFloat(item.debit_amount) > 0 ? '-$' + fmt2(item.debit_amount) : '$0' }}
                    </td>
                    <td class="small">
                      <input v-if="settlement.status === 'draft'"
                             v-model="itemNotes[item.id]" type="text"
                             class="form-control form-control-sm" style="min-width:120px"
                             placeholder="Notas opcionales">
                      <span v-else class="text-muted">{{ item.notes ?? '—' }}</span>
                    </td>
                    <td v-if="settlement.status === 'draft'">
                      <button @click="saveItem(item)" class="btn btn-xs btn-outline-primary" :disabled="savingItems[item.id]">
                        <span v-if="savingItems[item.id]"><span class="spinner-border spinner-border-sm"></span></span>
                        <span v-else><i class="fa fa-save"></i></span>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Botón cerrar (solo draft) -->
        <div v-if="settlement.status === 'draft'" class="d-flex justify-content-end">
          <button @click="openClose" class="btn btn-danger">
            <i class="fa fa-lock me-1"></i>Cerrar finiquito — IRREVERSIBLE
          </button>
        </div>
      </div>
    </div>

    <!-- ── MODAL REGISTRAR PRÉSTAMO ── -->
    <div v-if="loanModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa fa-hand-holding-usd me-2 text-primary"></i>Registrar préstamo / adelanto</h5>
            <button @click="loanModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning py-2 small mb-3">
              <i class="fa fa-exclamation-triangle me-1"></i>
              <strong>LFT Art. 110:</strong> No se descuenta nada de nómina sin autorización por escrito del colaborador. El préstamo queda pendiente hasta que firme.
            </div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Colaborador <span class="text-danger">*</span></label>
                <select v-model="loanModal.colaborador_id" class="form-select">
                  <option :value="null">— Seleccionar —</option>
                  <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Monto ($) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input v-model.number="loanModal.amount" type="number" min="1" step="100" class="form-control">
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Descuento semanal ($)</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input v-model.number="loanModal.repayment_weekly" type="number" min="0" step="50" class="form-control" placeholder="0 = sin descuento aún">
                </div>
                <div v-if="loanModal.amount && loanModal.repayment_weekly" class="small text-muted mt-1">
                  ~{{ Math.ceil(loanModal.amount / loanModal.repayment_weekly) }} semanas para saldar
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Motivo</label>
                <textarea v-model="loanModal.reason" class="form-control form-control-sm" rows="2" placeholder="Adelanto de nómina, emergencia médica…"></textarea>
              </div>
              <div v-if="loanModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ loanModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="loanModal.show=false" class="btn btn-secondary" :disabled="loanModal.saving">Cancelar</button>
            <button @click="saveLoan" class="btn btn-primary" :disabled="loanModal.saving">
              <span v-if="loanModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Registrando…</span>
              <span v-else>Registrar préstamo</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL CERRAR FINIQUITO (doble confirmación) ── -->
    <div v-if="closeModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title"><i class="fa fa-lock me-2"></i>Cerrar finiquito — IRREVERSIBLE</h5>
            <button @click="closeModal.show=false" type="button" class="btn-close btn-close-white"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-danger py-2 small mb-3">
              <strong>Esta acción NO se puede deshacer.</strong> Al confirmar:
              <ul class="mb-0 mt-1 ps-3">
                <li>Se escriben los asientos finales en el ledger (fund_return, ajustes)</li>
                <li>Los préstamos activos quedan saldados contra el finiquito</li>
                <li>El material devuelto se regresa al almacén en el sistema</li>
                <li>El finiquito queda sellado y firmado</li>
              </ul>
            </div>
            <div class="mb-3">
              <span class="small text-muted">Neto a liquidar:</span>
              <span class="fs-4 fw-bold ms-2" :class="settlement?.net_settlement >= 0 ? 'text-success' : 'text-danger'">
                {{ settlement?.net_settlement >= 0 ? '+' : '' }}${{ fmt2(settlement?.net_settlement) }}
              </span>
            </div>
            <div class="form-check">
              <input v-model="closeModal.confirmed" type="checkbox" class="form-check-input" id="confirm-close">
              <label for="confirm-close" class="form-check-label small">
                Confirmo que el colaborador recibió / firmó el finiquito y entiendo que esta acción es irreversible.
              </label>
            </div>
            <div v-if="closeModal.error" class="alert alert-danger py-2 small mt-2 mb-0">{{ closeModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button @click="closeModal.show=false" class="btn btn-secondary" :disabled="closeModal.saving">Cancelar</button>
            <button @click="confirmClose" class="btn btn-danger" :disabled="!closeModal.confirmed || closeModal.saving">
              <span v-if="closeModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Cerrando…</span>
              <span v-else><i class="fa fa-lock me-1"></i>Confirmar cierre definitivo</span>
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoFiniquito',
  data() {
    const today = new Date().toISOString().substring(0, 10);
    return {
      tab: 'loans',

      // Préstamos
      loans: [], loadingLoans: true,
      lFilters: { colaborador_id: '', status: 'active' },

      // Finiquito
      sColId: null, sDate: today, loadingS: false,
      settlement: null,
      itemNotes: {},     // { [item.id]: notes }
      savingItems: {},   // { [item.id]: bool }

      // Compartido
      colaboradores: [],

      // Modals
      loanModal: {
        show: false, colaborador_id: null, amount: 0,
        repayment_weekly: null, reason: '',
        saving: false, error: '',
      },
      closeModal: { show: false, confirmed: false, saving: false, error: '' },
    };
  },
  computed: {
    detail() {
      return this.settlement?.detail ?? {};
    },
  },
  mounted() {
    this.loadColaboradores();
    this.loadLoans();
  },
  methods: {
    async loadColaboradores() {
      const { data } = await axios.get('/talento/api/colaboradores', { params: { per_page: 300, status: 'active' } });
      this.colaboradores = data?.data ?? [];
    },
    async loadLoans(page = 1) {
      this.loadingLoans = true;
      try {
        const params = { page };
        if (this.lFilters.colaborador_id) params.colaborador_id = this.lFilters.colaborador_id;
        if (this.lFilters.status)          params.status          = this.lFilters.status;
        const { data } = await axios.get('/talento/api/loans', { params });
        this.loans = data?.data ?? [];
      } finally { this.loadingLoans = false; }
    },

    // ── Préstamos ──────────────────────────────────────────────────────────
    openNewLoan() {
      this.loanModal = {
        show: true, colaborador_id: null, amount: 0,
        repayment_weekly: null, reason: '', saving: false, error: '',
      };
    },
    async saveLoan() {
      this.loanModal.error = '';
      if (!this.loanModal.colaborador_id) { this.loanModal.error = 'Selecciona un colaborador.'; return; }
      if (!this.loanModal.amount || this.loanModal.amount <= 0) { this.loanModal.error = 'El monto debe ser mayor a 0.'; return; }
      this.loanModal.saving = true;
      try {
        await axios.post('/talento/api/loans', {
          colaborador_id:   this.loanModal.colaborador_id,
          amount:           this.loanModal.amount,
          repayment_weekly: this.loanModal.repayment_weekly || null,
          reason:           this.loanModal.reason || null,
        });
        this.loanModal.show = false;
        this.loadLoans();
      } catch (e) {
        this.loanModal.error = e.response?.data?.message ?? 'Error al registrar.';
      } finally { this.loanModal.saving = false; }
    },
    async authorizeLoan(loan) {
      if (!confirm(`¿Autorizar descuento de $${this.fmt2(loan.repayment_weekly ?? 0)}/sem para préstamo de ${loan.colaborador?.user?.name}?\nConfirma que hay autorización por escrito (LFT Art. 110).`)) return;
      try {
        await axios.post(`/talento/api/loans/${loan.id}/authorize`);
        this.loadLoans();
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error al autorizar.');
      }
    },
    weeksRemaining(loan) {
      if (!loan.repayment_weekly || parseFloat(loan.repayment_weekly) <= 0) return '—';
      return Math.ceil(parseFloat(loan.balance) / parseFloat(loan.repayment_weekly));
    },

    // ── Finiquito ──────────────────────────────────────────────────────────
    async loadDraft() {
      if (!this.sColId) return;
      this.loadingS = true;
      this.settlement = null;
      this.itemNotes = {};
      this.savingItems = {};
      try {
        const { data } = await axios.post(
          `/talento/api/colaboradores/${this.sColId}/settlement/draft`,
          { settlement_date: this.sDate }
        );
        this.settlement = data;
        // Pre-cargar notas existentes
        (data.items ?? []).forEach(i => { this.itemNotes[i.id] = i.notes ?? ''; });
      } catch (e) {
        alert(e.response?.data?.error ?? e.response?.data?.message ?? 'Error al calcular borrador.');
      } finally { this.loadingS = false; }
    },
    setDisposition(item, disposition) {
      // Actualizar localmente para respuesta inmediata
      item.disposition = disposition;
      // Calcular cargo estimado local
      const cost = parseFloat(item.unit_cost ?? 0);
      const qty  = parseFloat(item.current_stock ?? 0);
      item.debit_amount = disposition === 'returned' ? 0 :
                          disposition === 'damaged'  ? cost * qty * 0.5 :
                                                       cost * qty;
    },
    async saveItem(item) {
      this.$set ? this.$set(this.savingItems, item.id, true) : (this.savingItems[item.id] = true);
      try {
        const { data } = await axios.put(`/talento/api/settlement-items/${item.id}`, {
          disposition:  item.disposition,
          debit_amount: item.debit_amount,
          notes:        this.itemNotes[item.id] || null,
        });
        // Refrescar totales del finiquito
        this.settlement.gross_credits  = data.gross_credits;
        this.settlement.gross_debits   = data.gross_debits;
        this.settlement.net_settlement = data.net_settlement;
        this.settlement.detail         = data.detail;
        // Actualizar items del settlement
        this.settlement.items = data.items;
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error al guardar disposición.');
      } finally {
        this.savingItems[item.id] = false;
      }
    },
    openClose() {
      this.closeModal = { show: true, confirmed: false, saving: false, error: '' };
    },
    async confirmClose() {
      if (!this.closeModal.confirmed) return;
      this.closeModal.saving = true;
      try {
        const { data } = await axios.post(`/talento/api/settlements/${this.settlement.id}/close`);
        this.settlement = data;
        this.closeModal.show = false;
      } catch (e) {
        this.closeModal.error = e.response?.data?.error ?? e.response?.data?.message ?? 'Error al cerrar.';
      } finally { this.closeModal.saving = false; }
    },

    // ── Helpers ─────────────────────────────────────────────────────────────
    fmtDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    },
    fmt2: n => Number(n ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
  },
};
</script>

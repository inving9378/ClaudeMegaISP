<template>
  <div class="talento-comisiones tc-wrap" :class="{ 'tc-dark': darkMode }">

    <div class="tc-card">
      <div class="tc-cardhead">
        <h1 class="tc-h1">Comisiones</h1>
      </div>
      <div class="p-3">
        <div class="alert alert-info small mb-3">
          Vista <strong>replicada</strong> de comisiones de Vendedores — usa el mismo motor de cálculo
          (<code>CalculateBalanceSellerService</code>) y las mismas tablas, sin motor de dinero paralelo.
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-5">
            <label class="form-label small text-muted">Colaborador</label>
            <select v-model="selectedColId" @change="onColaboradorChange" class="form-select tc-select">
              <option :value="null">— Seleccionar colaborador —</option>
              <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
            </select>
          </div>
        </div>

        <div v-if="!selectedColId" class="text-center text-muted py-5">
          Selecciona un colaborador para ver sus comisiones.
        </div>

        <div v-else-if="loadingSeller" class="text-center py-5">
          <div class="spinner-border text-primary"></div>
        </div>

        <div v-else-if="notASeller" class="alert alert-warning small">
          <i class="fa fa-triangle-exclamation me-1"></i>{{ notASellerMessage }}
        </div>

        <template v-else>
          <!-- Resumen -->
          <div v-if="loadingStatement && !statement" class="alert alert-info small mb-4 d-flex align-items-center gap-2">
            <div class="spinner-border spinner-border-sm"></div>
            Calculando resumen de comisiones (puede tardar hasta un minuto — recorre todo el histórico)…
          </div>
          <div v-if="statement" class="row g-2 mb-4">
            <div class="col-6" style="flex:0 0 20%; max-width:20%">
              <div class="tc-kpi-box">
                <div class="tc-n">{{ fmt2(statement.income) }}</div>
                <div class="tc-l">Ingresos</div>
              </div>
            </div>
            <div class="col-6" style="flex:0 0 20%; max-width:20%">
              <div class="tc-kpi-box">
                <div class="tc-n">{{ fmt2(statement.expenses) }}</div>
                <div class="tc-l">Egresos</div>
              </div>
            </div>
            <div class="col-6" style="flex:0 0 20%; max-width:20%">
              <div class="tc-kpi-box tc-kpi-bad">
                <div class="tc-n">{{ fmt2(statement.debt) }}</div>
                <div class="tc-l">Deuda</div>
              </div>
            </div>
            <div class="col-6" style="flex:0 0 20%; max-width:20%">
              <div class="tc-kpi-box">
                <div class="tc-n">{{ fmt2(statement.discount) }}</div>
                <div class="tc-l">Descuentos</div>
              </div>
            </div>
            <div class="col-6" style="flex:0 0 20%; max-width:20%">
              <div class="tc-kpi-box tc-kpi-net">
                <div class="tc-n">{{ fmt2(statement.current_balance) }}</div>
                <div class="tc-l">Saldo actual</div>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
              <a class="nav-link" :class="{ active: tab === 'reglas' }" href="#" @click.prevent="tab='reglas'">
                <i class="fa fa-list-check me-1"></i>Reglas asignadas
                <span v-if="rules.length" class="tc-status is-slate ms-1">{{ rules.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: tab === 'pendientes' }" href="#" @click.prevent="tab='pendientes'">
                <i class="fa fa-hourglass-half me-1"></i>Pendientes de pago
                <span v-if="pending.length" class="tc-status is-warn ms-1">{{ pending.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: tab === 'pagos' }" href="#" @click.prevent="tab='pagos'">
                <i class="fa fa-money-check-dollar me-1"></i>Historial de pagos
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: tab === 'deudas' }" href="#" @click.prevent="tab='deudas'">
                <i class="fa fa-file-invoice-dollar me-1"></i>Deudas
                <span v-if="debtSales.length" class="tc-status is-bad ms-1">{{ debtSales.length }}</span>
              </a>
            </li>
          </ul>

          <div v-if="loadingTab" class="text-center py-4"><div class="spinner-border text-primary"></div></div>

          <template v-else>
            <!-- REGLAS -->
            <div v-if="tab === 'reglas'" class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                  <tr><th>Regla</th><th>%  comisión</th><th>Salario fijo</th><th>Periodo</th></tr>
                </thead>
                <tbody>
                  <tr v-for="r in rules" :key="r.id">
                    <td>{{ r.name }}</td>
                    <td>{{ r.commission_percentage ?? '—' }}</td>
                    <td>
                      <span class="tc-status" :class="r.is_fixed_salary ? 'is-ok' : 'is-slate'">
                        {{ r.is_fixed_salary ? fmt2(r.fixed_salary) : 'No aplica' }}
                      </span>
                    </td>
                    <td class="small text-muted">{{ r.period ?? '—' }}</td>
                  </tr>
                  <tr v-if="!rules.length"><td colspan="4" class="text-center text-muted py-3">Sin reglas asignadas.</td></tr>
                </tbody>
              </table>
            </div>

            <!-- PENDIENTES -->
            <div v-if="tab === 'pendientes'">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                  <thead class="table-light">
                    <tr><th>Periodo</th><th>Tipo</th><th>Monto</th><th></th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="p in pending" :key="p.id">
                      <td class="small">{{ p.period_str }}</td>
                      <td class="small">{{ p.type }}</td>
                      <td>{{ fmt2(p.amount) }}</td>
                      <td>
                        <button @click="openPayModal(p)" class="tc-btn tc-btn-ok btn-xs">
                          <i class="fa fa-check me-1"></i>Registrar pago
                        </button>
                      </td>
                    </tr>
                    <tr v-if="!pending.length"><td colspan="4" class="text-center text-muted py-3">Sin comisiones pendientes de pago.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- PAGOS -->
            <div v-if="tab === 'pagos'" class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                  <tr><th>#</th><th>Folio</th><th>Monto</th><th>Método</th><th>Fecha</th><th></th></tr>
                </thead>
                <tbody>
                  <tr v-for="pay in payments" :key="pay.id">
                    <td>{{ pay.id }}</td>
                    <td class="small">{{ pay.invoice_number ?? '—' }}</td>
                    <td>{{ fmt2(pay.amount) }}</td>
                    <td class="small">{{ pay.payment_method_str }}</td>
                    <td class="small text-muted">{{ pay.payment_date }}</td>
                    <td>
                      <a :href="`/talento/api/comisiones/pagos/${pay.id}/pdf`" target="_blank" class="tc-btn tc-btn-seg btn-xs">
                        <i class="fa fa-file-pdf"></i>
                      </a>
                    </td>
                  </tr>
                  <tr v-if="!payments.length"><td colspan="6" class="text-center text-muted py-3">Sin pagos registrados.</td></tr>
                </tbody>
              </table>
            </div>

            <!-- DEUDAS -->
            <div v-if="tab === 'deudas'">
              <h6 class="text-uppercase text-muted small mb-2">Ventas con deuda pendiente</h6>
              <div class="table-responsive mb-4">
                <table class="table table-sm table-hover align-middle">
                  <thead class="table-light">
                    <tr><th>Cliente</th><th>Fecha</th><th>Saldo</th><th></th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="s in debtSales" :key="s.id">
                      <td class="small">{{ s.client }}</td>
                      <td class="small text-muted">{{ s.date }}</td>
                      <td>{{ fmt2(s.current_debt) }}</td>
                      <td>
                        <button @click="openDebtModal(s)" class="tc-btn tc-btn-warn-solid btn-xs">
                          <i class="fa fa-hand-holding-dollar me-1"></i>Cobrar
                        </button>
                      </td>
                    </tr>
                    <tr v-if="!debtSales.length"><td colspan="4" class="text-center text-muted py-3">Sin deuda pendiente.</td></tr>
                  </tbody>
                </table>
              </div>

              <h6 class="text-uppercase text-muted small mb-2">Cobros registrados</h6>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                  <thead class="table-light">
                    <tr><th>#</th><th>Folio</th><th>Monto</th><th>Fecha</th><th></th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in discounts" :key="d.id">
                      <td>{{ d.id }}</td>
                      <td class="small">{{ d.invoice_number ?? '—' }}</td>
                      <td>{{ fmt2(d.discount) }}</td>
                      <td class="small text-muted">{{ d.date }}</td>
                      <td>
                        <a :href="`/talento/api/comisiones/descuentos/${d.id}/pdf`" target="_blank" class="tc-btn tc-btn-seg btn-xs">
                          <i class="fa fa-file-pdf"></i>
                        </a>
                      </td>
                    </tr>
                    <tr v-if="!discounts.length"><td colspan="5" class="text-center text-muted py-3">Sin cobros registrados.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>
        </template>
      </div>
    </div>

    <!-- MODAL: registrar pago -->
    <div v-if="payModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Registrar pago — {{ payModal.item?.type }}</h5>
            <button @click="payModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted">Periodo: {{ payModal.item?.period_str }} — monto calculado: <strong>{{ fmt2(payModal.item?.amount) }}</strong></p>
            <div class="mb-2">
              <label class="form-label small">Método de pago</label>
              <select v-model.number="payModal.form.payment_method_id" class="form-select tc-select">
                <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.type }}</option>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label small">Folio / factura</label>
              <input v-model="payModal.form.invoice_number" type="text" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label small">Fecha de pago</label>
              <input v-model="payModal.form.payment_date" type="date" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label small">Comentarios</label>
              <textarea v-model="payModal.form.comments" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="payModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="savePayment" :disabled="payModal.saving" class="btn btn-primary">
              <span v-if="payModal.saving" class="spinner-border spinner-border-sm me-1"></span>Registrar pago
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: cobrar deuda -->
    <div v-if="debtModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Cobrar deuda — {{ debtModal.sale?.client }}</h5>
            <button @click="debtModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted">Saldo de la venta: <strong>{{ fmt2(debtModal.sale?.current_debt) }}</strong></p>
            <div class="mb-2">
              <label class="form-label small">Monto a cobrar</label>
              <input v-model.number="debtModal.form.amount" type="number" step="0.01" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label small">Folio / factura</label>
              <input v-model="debtModal.form.invoice_number" type="text" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label small">Comentarios</label>
              <textarea v-model="debtModal.form.comments" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="debtModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="saveDebt" :disabled="debtModal.saving" class="btn btn-primary">
              <span v-if="debtModal.saving" class="spinner-border spinner-border-sm me-1"></span>Cobrar
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import axios from "axios";
import { darkMode } from "../../../hook/appConfig.js";
import Swal from "sweetalert2";

export default {
  name: "TalentoComisiones",
  setup() {
    return { darkMode };
  },
  data() {
    return {
      colaboradores: [],
      selectedColId: null,
      loadingSeller: false,
      notASeller: false,
      notASellerMessage: "",

      tab: "reglas",
      loadingTab: false,
      statement: null,
      loadingStatement: false,
      rules: [],
      pending: [],
      payments: [],
      debtSales: [],
      discounts: [],

      paymentMethods: [],

      payModal: { show: false, item: null, saving: false, form: this.blankPayForm() },
      debtModal: { show: false, sale: null, saving: false, form: { amount: 0, invoice_number: "", comments: "" } },
    };
  },
  watch: {
    tab(val) {
      if (this.selectedColId && !this.notASeller) this.loadTabData(val);
    },
  },
  async mounted() {
    await Promise.all([this.loadColaboradores(), this.loadPaymentMethods()]);
  },
  methods: {
    blankPayForm() {
      return {
        payment_method_id: 1,
        invoice_number: "",
        payment_date: new Date().toISOString().substring(0, 10),
        comments: "",
      };
    },

    async loadColaboradores() {
      const { data } = await axios.get("/talento/api/colaboradores", { params: { per_page: 300, status: "active" } });
      this.colaboradores = data?.data ?? [];
    },
    async loadPaymentMethods() {
      try {
        const { data } = await axios.get("/talento/api/comisiones/metodos-pago");
        this.paymentMethods = data ?? [];
      } catch (e) { this.paymentMethods = []; }
    },

    async onColaboradorChange() {
      this.statement = null;
      this.loadingStatement = false;
      this.rules = [];
      this.pending = [];
      this.payments = [];
      this.debtSales = [];
      this.discounts = [];
      this.notASeller = false;
      if (!this.selectedColId) return;

      // Chequeo de identidad rápido (reglas, <1s) — el estado de cuenta usa el
      // MISMO motor pesado que ya es lento en Vendedores (CalculateBalanceSellerService
      // recorre todo el histórico semana a semana); no bloquea la pantalla, se
      // carga aparte en segundo plano para no tener al usuario esperando ~40s
      // en blanco antes de poder ver reglas/pendientes/pagos.
      this.loadingSeller = true;
      try {
        const { data } = await axios.get(`/talento/api/colaboradores/${this.selectedColId}/comisiones/reglas`);
        this.rules = data ?? [];
        this.tab = "reglas";
        this.loadStatement();
      } catch (e) {
        if (e.response?.status === 422) {
          this.notASeller = true;
          this.notASellerMessage = e.response.data?.message ?? "Este colaborador no tiene cuenta de vendedor asociada.";
        }
      } finally {
        this.loadingSeller = false;
      }
    },

    async loadStatement() {
      this.loadingStatement = true;
      try {
        const { data } = await axios.get(`/talento/api/colaboradores/${this.selectedColId}/comisiones/estado-cuenta`);
        this.statement = data;
      } catch (e) {
        // silencioso: si falla, la pantalla sigue usable (reglas/pendientes/pagos/deudas
        // no dependen del resumen) — el bloque de resumen simplemente no aparece.
      } finally {
        this.loadingStatement = false;
      }
    },

    async loadTabData(tab) {
      this.loadingTab = true;
      try {
        if (tab === "reglas") {
          const { data } = await axios.get(`/talento/api/colaboradores/${this.selectedColId}/comisiones/reglas`);
          this.rules = data ?? [];
        } else if (tab === "pendientes") {
          const { data } = await axios.get(`/talento/api/colaboradores/${this.selectedColId}/comisiones/pendientes`);
          this.pending = data ?? [];
        } else if (tab === "pagos") {
          const { data } = await axios.get(`/talento/api/colaboradores/${this.selectedColId}/comisiones/pagos`);
          this.payments = data?.data ?? [];
        } else if (tab === "deudas") {
          const [debtRes, discRes] = await Promise.all([
            axios.get(`/talento/api/colaboradores/${this.selectedColId}/comisiones/deuda-pendiente`),
            axios.get(`/talento/api/colaboradores/${this.selectedColId}/comisiones/descuentos`),
          ]);
          this.debtSales = debtRes.data ?? [];
          this.discounts = discRes.data?.data ?? [];
        }
      } finally {
        this.loadingTab = false;
      }
    },

    // ── Registrar pago ────────────────────────────────────────────────────
    openPayModal(item) {
      this.payModal.item = item;
      this.payModal.form = this.blankPayForm();
      this.payModal.show = true;
    },
    async savePayment() {
      this.payModal.saving = true;
      try {
        await axios.post(`/talento/api/colaboradores/${this.selectedColId}/comisiones/pagos`, {
          ...this.payModal.form,
          amount: 0,
          general_bonus: [this.payModal.item.code],
          period_date: this.payModal.item.period_date,
        });
        this.payModal.show = false;
        await Promise.all([this.onColaboradorChange()]);
        Swal.fire("Listo", "Pago registrado correctamente.", "success");
      } catch (e) {
        Swal.fire("Error", e.response?.data?.message ?? "No se pudo registrar el pago.", "error");
      } finally {
        this.payModal.saving = false;
      }
    },

    // ── Cobrar deuda ────────────────────────────────────────────────────
    openDebtModal(sale) {
      this.debtModal.sale = sale;
      this.debtModal.form = { amount: sale.current_debt, invoice_number: "", comments: "" };
      this.debtModal.show = true;
    },
    async saveDebt() {
      this.debtModal.saving = true;
      try {
        const s = this.debtModal.sale;
        await axios.post(`/talento/api/colaboradores/${this.selectedColId}/comisiones/cobrar-deuda`, {
          date: new Date().toISOString().substring(0, 10),
          discount: this.debtModal.form.amount,
          invoice_number: this.debtModal.form.invoice_number,
          comments: this.debtModal.form.comments,
          sales: [{ id: s.id, rule_id: s.rule_id ?? null, to_pay: this.debtModal.form.amount, ...s }],
        });
        this.debtModal.show = false;
        await this.loadTabData("deudas");
        await this.onColaboradorChange();
        Swal.fire("Listo", "Cobro registrado correctamente.", "success");
      } catch (e) {
        Swal.fire("Error", e.response?.data?.message ?? "No se pudo registrar el cobro.", "error");
      } finally {
        this.debtModal.saving = false;
      }
    },

    // ── Helpers ─────────────────────────────────────────────────────────
    fmt2: (n) => Number(n ?? 0).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
  },
};
</script>

<style scoped>
/* Gap del tema Torre (paso 8): tarjetas de KPI propias (mismo patrón que
   TalentoCajaVendedor.vue), con una variante roja para "Deuda". */
.tc-kpi-box {
  background: var(--tc-bg2);
  border-radius: 10px;
  padding: 12px;
  text-align: center;
}
.tc-kpi-net {
  background: rgba(21, 128, 61, .12);
}
.tc-kpi-bad {
  background: rgba(220, 38, 38, .12);
}
.tc-kpi-box .tc-n {
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--tc-ink);
}
.tc-kpi-box .tc-l {
  font-size: .72rem;
  text-transform: uppercase;
  color: var(--tc-muted);
  letter-spacing: .03em;
}
</style>

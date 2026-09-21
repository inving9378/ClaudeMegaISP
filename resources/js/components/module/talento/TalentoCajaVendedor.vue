<template>
  <div class="talento-caja-vendedor tc-wrap" :class="{ 'tc-dark': darkMode }">

    <div class="tc-card">
      <div class="tc-cardhead">
        <h1 class="tc-h1">Caja diaria de efectivo</h1>
      </div>
      <div class="p-3">
        <div class="alert alert-info small mb-3">
          Vista <strong>replicada</strong> de la caja diaria que ya usa Vendedores — lee y escribe las
          mismas tablas (<code>cut_boxs</code> y relacionadas), sin motor de dinero paralelo.
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
          Selecciona un colaborador para ver sus cajas.
        </div>

        <template v-else>
          <div v-if="loadingBoxes" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          <div v-else class="table-responsive">
            <table class="table table-hover table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Fecha</th>
                  <th>Abre</th>
                  <th>Cierra</th>
                  <th>Estado</th>
                  <th>Total neto</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="b in boxes" :key="b.id" :class="{ 'tc-row-selected': box?.id === b.id }">
                  <td>{{ b.id }}</td>
                  <td>{{ b.start_date }}</td>
                  <td class="small text-muted">{{ b.start_time }}</td>
                  <td class="small text-muted">{{ b.end_time ?? '—' }}</td>
                  <td><span class="tc-status" :class="b.closed ? 'is-ok' : 'is-warn'">{{ b.closed ? 'Cerrada' : 'Abierta' }}</span></td>
                  <td>{{ b.closed ? fmt2(b.total_net) : '—' }}</td>
                  <td>
                    <button @click="openBox(b.id)" class="tc-btn tc-btn-info btn-xs" title="Ver detalle">
                      <i class="fa fa-eye"></i>
                    </button>
                  </td>
                </tr>
                <tr v-if="!boxes.length">
                  <td colspan="7" class="text-center text-muted py-4">Este colaborador no tiene cajas registradas.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </div>

    <!-- ── DETALLE DE CAJA ── -->
    <div v-if="box" class="tc-card mt-3">
      <div class="tc-cardhead d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1 class="tc-h1 mb-0">
          Caja #{{ box.id }}
          <span class="tc-status ms-2" :class="box.closed ? 'is-ok' : 'is-warn'">{{ box.closed ? 'Cerrada' : 'Abierta' }}</span>
        </h1>
        <div class="d-flex gap-2">
          <button v-if="box.closed" @click="downloadPdf" class="tc-btn tc-btn-seg btn-sm">
            <i class="fa fa-file-pdf me-1"></i>Ver PDF
          </button>
          <button v-if="!box.closed" @click="confirmClose" :disabled="closingBox" class="tc-btn tc-btn-bad-solid btn-sm">
            <span v-if="closingBox" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="fa fa-lock me-1"></i>Cerrar caja
          </button>
        </div>
      </div>

      <div class="p-3">
        <p class="small text-muted mb-3">Vendedor: <strong>{{ box.user_str }}</strong> · Abrió {{ box.start_date }} {{ box.start_time }}</p>

        <!-- Totales (solo cuando ya cerró — son el resultado real de close(), no se recalculan en el navegador) -->
        <div v-if="box.closed" class="row g-2 mb-4">
          <div class="col-6 col-md-2-4" style="flex:0 0 20%; max-width:20%">
            <div class="tc-kpi-box">
              <div class="tc-n">{{ fmt2(box.total_received) }}</div>
              <div class="tc-l">Recibido</div>
            </div>
          </div>
          <div class="col-6" style="flex:0 0 20%; max-width:20%">
            <div class="tc-kpi-box">
              <div class="tc-n">{{ fmt2(box.total_extras) }}</div>
              <div class="tc-l">Extras</div>
            </div>
          </div>
          <div class="col-6" style="flex:0 0 20%; max-width:20%">
            <div class="tc-kpi-box">
              <div class="tc-n">{{ fmt2(box.total_technicals) }}</div>
              <div class="tc-l">Instalaciones</div>
            </div>
          </div>
          <div class="col-6" style="flex:0 0 20%; max-width:20%">
            <div class="tc-kpi-box">
              <div class="tc-n">{{ fmt2(box.total_proveedores) }}</div>
              <div class="tc-l">Proveedores</div>
            </div>
          </div>
          <div class="col-6" style="flex:0 0 20%; max-width:20%">
            <div class="tc-kpi-box tc-kpi-net">
              <div class="tc-n">{{ fmt2(box.total_net) }}</div>
              <div class="tc-l">Neto</div>
            </div>
          </div>
        </div>
        <div v-else class="alert alert-warning small mb-4">
          Los totales se calculan y guardan al <strong>cerrar la caja</strong> (igual que en Vendedores) — mientras
          está abierta, aquí solo se ven los movimientos capturados.
        </div>

        <!-- Sub-tabs -->
        <ul class="nav nav-tabs mb-3">
          <li class="nav-item">
            <a class="nav-link" :class="{ active: activeTab === 'recibidos' }" href="#" @click.prevent="activeTab='recibidos'">
              <i class="fa fa-hand-holding-usd me-1"></i>Pagos recibidos
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" :class="{ active: activeTab === 'extras' }" href="#" @click.prevent="activeTab='extras'">
              <i class="fa fa-plus-circle me-1"></i>Ingresos extra
              <span v-if="extras.length" class="tc-status is-slate ms-1">{{ extras.length }}</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" :class="{ active: activeTab === 'proveedores' }" href="#" @click.prevent="activeTab='proveedores'">
              <i class="fa fa-truck me-1"></i>Gastos a proveedores
              <span v-if="suppliers.length" class="tc-status is-slate ms-1">{{ suppliers.length }}</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" :class="{ active: activeTab === 'instalaciones' }" href="#" @click.prevent="activeTab='instalaciones'">
              <i class="fa fa-network-wired me-1"></i>Instalaciones
              <span v-if="installations.length" class="tc-status is-slate ms-1">{{ installations.length }}</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" :class="{ active: activeTab === 'observaciones' }" href="#" @click.prevent="activeTab='observaciones'">
              <i class="fa fa-comment-dots me-1"></i>Observaciones
              <span v-if="observations.length" class="tc-status is-slate ms-1">{{ observations.length }}</span>
            </a>
          </li>
        </ul>

        <div v-if="loadingTab" class="text-center py-4"><div class="spinner-border text-primary"></div></div>

        <template v-else>
          <!-- PAGOS RECIBIDOS (solo lectura) -->
          <div v-if="activeTab === 'recibidos'" class="table-responsive">
            <table class="table table-sm table-hover align-middle">
              <thead class="table-light">
                <tr><th>#</th><th>Cliente</th><th>Monto</th><th>Método</th><th>Fecha</th><th>Comentario</th></tr>
              </thead>
              <tbody>
                <tr v-for="p in receivedPayments" :key="p.id">
                  <td>{{ p.id }}</td>
                  <td>Cliente #{{ p.paymentable_id }}</td>
                  <td>{{ fmt2(p.amount) }}</td>
                  <td class="small">{{ methodLabel(p.payment_method_id) }}</td>
                  <td class="small text-muted">{{ fmtDate(p.date) }}</td>
                  <td class="small text-muted">{{ p.comment ?? '—' }}</td>
                </tr>
                <tr v-if="!receivedPayments.length"><td colspan="6" class="text-center text-muted py-3">Sin pagos recibidos ese día.</td></tr>
              </tbody>
            </table>
          </div>

          <!-- EXTRAS -->
          <div v-if="activeTab === 'extras'">
            <div class="text-end mb-2" v-if="!box.closed">
              <button @click="openExtraModal(null)" class="tc-btn tc-btn-ok btn-sm"><i class="fa fa-plus me-1"></i>Agregar</button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                  <tr><th>#</th><th>Monto</th><th>Método</th><th>Folio</th><th>Fecha</th><th>Comentarios</th><th>Registró</th><th v-if="!box.closed"></th></tr>
                </thead>
                <tbody>
                  <tr v-for="r in extras" :key="r.id">
                    <td>{{ r.id }}</td>
                    <td>{{ fmt2(r.amount) }}</td>
                    <td class="small">{{ r.payment_method_str }}</td>
                    <td class="small">{{ r.invoice_number ?? '—' }}</td>
                    <td class="small text-muted">{{ fmtDate(r.payment_date) }}</td>
                    <td class="small text-muted">{{ r.comments ?? '—' }}</td>
                    <td class="small text-muted">{{ r.created_by_str }}</td>
                    <td v-if="!box.closed">
                      <button @click="openExtraModal(r)" class="tc-btn tc-btn-info btn-xs me-1"><i class="fa fa-pen"></i></button>
                      <button @click="deleteExtra(r)" class="tc-btn tc-btn-bad btn-xs"><i class="fa fa-trash"></i></button>
                    </td>
                  </tr>
                  <tr v-if="!extras.length"><td colspan="8" class="text-center text-muted py-3">Sin ingresos extra registrados.</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- PROVEEDORES -->
          <div v-if="activeTab === 'proveedores'">
            <div class="text-end mb-2" v-if="!box.closed">
              <button @click="openSupplierModal(null)" class="tc-btn tc-btn-ok btn-sm"><i class="fa fa-plus me-1"></i>Agregar</button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                  <tr><th>#</th><th>Monto</th><th>Método</th><th>Folio</th><th>Fecha</th><th>Comentarios</th><th>Registró</th><th v-if="!box.closed"></th></tr>
                </thead>
                <tbody>
                  <tr v-for="r in suppliers" :key="r.id">
                    <td>{{ r.id }}</td>
                    <td>{{ fmt2(r.amount) }}</td>
                    <td class="small">{{ r.payment_method_str }}</td>
                    <td class="small">{{ r.invoice_number ?? '—' }}</td>
                    <td class="small text-muted">{{ fmtDate(r.payment_date) }}</td>
                    <td class="small text-muted">{{ r.comments ?? '—' }}</td>
                    <td class="small text-muted">{{ r.created_by_str }}</td>
                    <td v-if="!box.closed">
                      <button @click="openSupplierModal(r)" class="tc-btn tc-btn-info btn-xs me-1"><i class="fa fa-pen"></i></button>
                      <button @click="deleteSupplier(r)" class="tc-btn tc-btn-bad btn-xs"><i class="fa fa-trash"></i></button>
                    </td>
                  </tr>
                  <tr v-if="!suppliers.length"><td colspan="8" class="text-center text-muted py-3">Sin gastos a proveedores registrados.</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- INSTALACIONES -->
          <div v-if="activeTab === 'instalaciones'">
            <p class="small text-muted">Se generan solas de las altas activadas ese día por este colaborador — aquí solo se asigna técnico y se ajustan montos.</p>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                  <tr><th>#</th><th>Cliente</th><th>Servicio</th><th>Instalación</th><th>Activada</th><th>Técnico</th><th v-if="!box.closed"></th></tr>
                </thead>
                <tbody>
                  <tr v-for="r in installations" :key="r.id">
                    <td>{{ r.id }}</td>
                    <td class="small">{{ r.client_str }}</td>
                    <td>{{ fmt2(r.service_amount) }}</td>
                    <td>{{ fmt2(r.installation_cost) }}</td>
                    <td><span class="tc-status" :class="r.activated ? 'is-ok' : 'is-slate'">{{ r.activated ? 'Sí' : 'No' }}</span></td>
                    <td class="small">{{ r.technical_str ?? '—' }}</td>
                    <td v-if="!box.closed">
                      <button @click="openInstallationModal(r)" class="tc-btn tc-btn-info btn-xs"><i class="fa fa-pen"></i></button>
                    </td>
                  </tr>
                  <tr v-if="!installations.length"><td colspan="7" class="text-center text-muted py-3">Sin instalaciones ese día.</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- OBSERVACIONES -->
          <div v-if="activeTab === 'observaciones'">
            <div v-if="!box.closed" class="d-flex gap-2 mb-3">
              <input v-model="newObservation" type="text" class="form-control" placeholder="Escribe una observación...">
              <button @click="addObservation" :disabled="!newObservation.trim()" class="tc-btn tc-btn-ok btn-sm">Agregar</button>
            </div>
            <ul class="list-group">
              <li v-for="o in observations" :key="o.id" class="list-group-item d-flex justify-content-between align-items-start">
                <div>
                  <div>{{ o.comment }}</div>
                  <div class="small text-muted">{{ o.created_by_str }}</div>
                </div>
                <button v-if="!box.closed" @click="deleteObservation(o)" class="tc-btn tc-btn-bad btn-xs"><i class="fa fa-trash"></i></button>
              </li>
              <li v-if="!observations.length" class="list-group-item text-center text-muted">Sin observaciones.</li>
            </ul>
          </div>
        </template>
      </div>
    </div>

    <!-- ── MODAL: extra / proveedor (mismo shape) ── -->
    <div v-if="extraModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ extraModal.editing ? 'Editar' : 'Agregar' }} ingreso extra</h5>
            <button @click="extraModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-2">
              <label class="form-label small">Monto</label>
              <input v-model.number="extraModal.form.amount" type="number" step="0.01" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label small">Método de pago</label>
              <select v-model.number="extraModal.form.payment_method_id" class="form-select tc-select">
                <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.type }}</option>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label small">Folio / factura</label>
              <input v-model="extraModal.form.invoice_number" type="text" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label small">Fecha</label>
              <input v-model="extraModal.form.payment_date" type="date" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label small">Comentarios</label>
              <textarea v-model="extraModal.form.comments" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="extraModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="saveExtra" :disabled="extraModal.saving" class="btn btn-primary">
              <span v-if="extraModal.saving" class="spinner-border spinner-border-sm me-1"></span>Guardar
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="supplierModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ supplierModal.editing ? 'Editar' : 'Agregar' }} gasto a proveedor</h5>
            <button @click="supplierModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-2">
              <label class="form-label small">Monto</label>
              <input v-model.number="supplierModal.form.amount" type="number" step="0.01" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label small">Método de pago</label>
              <select v-model.number="supplierModal.form.payment_method_id" class="form-select tc-select">
                <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.type }}</option>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label small">Folio / factura</label>
              <input v-model="supplierModal.form.invoice_number" type="text" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label small">Fecha</label>
              <input v-model="supplierModal.form.payment_date" type="date" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label small">Comentarios</label>
              <textarea v-model="supplierModal.form.comments" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="supplierModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="saveSupplier" :disabled="supplierModal.saving" class="btn btn-primary">
              <span v-if="supplierModal.saving" class="spinner-border spinner-border-sm me-1"></span>Guardar
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="installationModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Instalación — {{ installationModal.form.client_str }}</h5>
            <button @click="installationModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-2">
              <label class="form-label small">Técnico responsable</label>
              <select v-model.number="installationModal.form.technical_id" class="form-select tc-select">
                <option :value="null">— Sin asignar —</option>
                <option v-for="t in technicals" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label small">Monto servicio</label>
                <input v-model.number="installationModal.form.service_amount" type="number" step="0.01" class="form-control">
              </div>
              <div class="col-6">
                <label class="form-label small">Costo instalación</label>
                <input v-model.number="installationModal.form.installation_cost" type="number" step="0.01" class="form-control">
              </div>
            </div>
            <div class="form-check mb-2">
              <input v-model="installationModal.form.activated" class="form-check-input" type="checkbox" id="instActivated">
              <label class="form-check-label small" for="instActivated">Activada</label>
            </div>
            <div class="mb-2">
              <label class="form-label small">Comentarios</label>
              <textarea v-model="installationModal.form.comments" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="installationModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="saveInstallation" :disabled="installationModal.saving" class="btn btn-primary">
              <span v-if="installationModal.saving" class="spinner-border spinner-border-sm me-1"></span>Guardar
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
  name: "TalentoCajaVendedor",
  setup() {
    return { darkMode };
  },
  data() {
    return {
      colaboradores: [],
      selectedColId: null,
      boxes: [],
      loadingBoxes: false,

      box: null,
      loadingTab: false,
      activeTab: "recibidos",
      receivedPayments: [],
      extras: [],
      suppliers: [],
      installations: [],
      observations: [],
      newObservation: "",

      technicals: [],
      paymentMethods: [],
      closingBox: false,

      extraModal: { show: false, editing: null, saving: false, form: this.blankExtraForm() },
      supplierModal: { show: false, editing: null, saving: false, form: this.blankExtraForm() },
      installationModal: { show: false, editing: null, saving: false, form: {} },
    };
  },
  async mounted() {
    await Promise.all([this.loadColaboradores(), this.loadPaymentMethods(), this.loadTechnicals()]);
  },
  methods: {
    blankExtraForm() {
      return {
        amount: null,
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
        const { data } = await axios.get("/configuracion/metodos-de-pago/get-all-methods");
        this.paymentMethods = data ?? [];
      } catch (e) { this.paymentMethods = []; }
    },
    async loadTechnicals() {
      try {
        const { data } = await axios.get("/talento/api/caja/tecnicos");
        this.technicals = data ?? [];
      } catch (e) { this.technicals = []; }
    },
    methodLabel(id) {
      return this.paymentMethods.find((m) => m.id === id)?.type ?? "—";
    },

    async onColaboradorChange() {
      this.box = null;
      this.boxes = [];
      if (!this.selectedColId) return;
      this.loadingBoxes = true;
      try {
        const { data } = await axios.get(`/talento/api/colaboradores/${this.selectedColId}/caja`, { params: { per_page: 50 } });
        this.boxes = data?.data ?? [];
      } finally {
        this.loadingBoxes = false;
      }
    },

    async openBox(boxId) {
      this.loadingTab = true;
      this.activeTab = "recibidos";
      try {
        const { data } = await axios.get(`/talento/api/caja/box/${boxId}`);
        this.box = data;
        await this.loadBoxData(boxId);
      } finally {
        this.loadingTab = false;
      }
    },

    async loadBoxData(boxId) {
      const [received, extras, suppliers, installations, observations] = await Promise.all([
        axios.get(`/talento/api/caja/box/${boxId}/pagos-recibidos`),
        axios.get(`/talento/api/caja/extras/${boxId}`),
        axios.get(`/talento/api/caja/proveedores/${boxId}`),
        axios.get(`/talento/api/caja/instalaciones/${boxId}`),
        axios.get(`/talento/api/caja/observaciones/${boxId}`),
      ]);
      this.receivedPayments = received.data ?? [];
      this.extras = extras.data ?? [];
      this.suppliers = suppliers.data ?? [];
      this.installations = installations.data ?? [];
      this.observations = observations.data ?? [];
    },

    confirmClose() {
      Swal.fire({
        title: "¿Cerrar esta caja?",
        text: "Ya no se podrán modificar extras, proveedores, instalaciones ni observaciones. Verifica que todo esté capturado.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Cerrar caja",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#dc2626",
      }).then((res) => {
        if (res.isConfirmed) this.doClose();
      });
    },
    async doClose() {
      this.closingBox = true;
      try {
        const { data } = await axios.post(`/talento/api/caja/box/${this.box.id}/cerrar`);
        this.box = data;
        await this.onColaboradorChange();
        this.box = data;
        Swal.fire("Listo", "Caja cerrada correctamente.", "success");
      } catch (e) {
        Swal.fire("Error", "No se pudo cerrar la caja.", "error");
      } finally {
        this.closingBox = false;
      }
    },
    downloadPdf() {
      window.open(`/talento/api/caja/box/${this.box.id}/pdf`, "_blank");
    },

    // ── Extras ──────────────────────────────────────────────────────────
    openExtraModal(row) {
      this.extraModal.editing = row;
      this.extraModal.form = row
        ? { amount: row.amount, payment_method_id: row.payment_method_id, invoice_number: row.invoice_number, payment_date: row.payment_date?.substring(0, 10) ?? "", comments: row.comments }
        : this.blankExtraForm();
      this.extraModal.show = true;
    },
    async saveExtra() {
      this.extraModal.saving = true;
      try {
        const payload = { ...this.extraModal.form, box_id: this.box.id };
        if (this.extraModal.editing) {
          await axios.put(`/talento/api/caja/extras/${this.extraModal.editing.id}`, payload);
        } else {
          await axios.post("/talento/api/caja/extras", payload);
        }
        this.extraModal.show = false;
        await this.loadBoxData(this.box.id);
      } finally {
        this.extraModal.saving = false;
      }
    },
    async deleteExtra(row) {
      const res = await Swal.fire({ title: "¿Eliminar este ingreso extra?", icon: "warning", showCancelButton: true, confirmButtonText: "Eliminar", confirmButtonColor: "#dc2626" });
      if (!res.isConfirmed) return;
      await axios.delete(`/talento/api/caja/extras/${row.id}`);
      await this.loadBoxData(this.box.id);
    },

    // ── Proveedores ─────────────────────────────────────────────────────
    openSupplierModal(row) {
      this.supplierModal.editing = row;
      this.supplierModal.form = row
        ? { amount: row.amount, payment_method_id: row.payment_method_id, invoice_number: row.invoice_number, payment_date: row.payment_date?.substring(0, 10) ?? "", comments: row.comments }
        : this.blankExtraForm();
      this.supplierModal.show = true;
    },
    async saveSupplier() {
      this.supplierModal.saving = true;
      try {
        const payload = { ...this.supplierModal.form, box_id: this.box.id };
        if (this.supplierModal.editing) {
          await axios.put(`/talento/api/caja/proveedores/${this.supplierModal.editing.id}`, payload);
        } else {
          await axios.post("/talento/api/caja/proveedores", payload);
        }
        this.supplierModal.show = false;
        await this.loadBoxData(this.box.id);
      } finally {
        this.supplierModal.saving = false;
      }
    },
    async deleteSupplier(row) {
      const res = await Swal.fire({ title: "¿Eliminar este gasto?", icon: "warning", showCancelButton: true, confirmButtonText: "Eliminar", confirmButtonColor: "#dc2626" });
      if (!res.isConfirmed) return;
      await axios.delete(`/talento/api/caja/proveedores/${row.id}`);
      await this.loadBoxData(this.box.id);
    },

    // ── Instalaciones ───────────────────────────────────────────────────
    openInstallationModal(row) {
      this.installationModal.editing = row;
      this.installationModal.form = {
        client_str: row.client_str,
        technical_id: row.technical_id,
        service_amount: row.service_amount,
        installation_cost: row.installation_cost,
        activated: !!row.activated,
        comments: row.comments,
      };
      this.installationModal.show = true;
    },
    async saveInstallation() {
      this.installationModal.saving = true;
      try {
        const { client_str, ...payload } = this.installationModal.form;
        await axios.put(`/talento/api/caja/instalaciones/${this.installationModal.editing.id}`, payload);
        this.installationModal.show = false;
        await this.loadBoxData(this.box.id);
      } finally {
        this.installationModal.saving = false;
      }
    },

    // ── Observaciones ───────────────────────────────────────────────────
    async addObservation() {
      if (!this.newObservation.trim()) return;
      await axios.post("/talento/api/caja/observaciones", { comment: this.newObservation, box_id: this.box.id });
      this.newObservation = "";
      await this.loadBoxData(this.box.id);
    },
    async deleteObservation(row) {
      const res = await Swal.fire({ title: "¿Eliminar esta observación?", icon: "warning", showCancelButton: true, confirmButtonText: "Eliminar", confirmButtonColor: "#dc2626" });
      if (!res.isConfirmed) return;
      await axios.delete(`/talento/api/caja/observaciones/${row.id}`);
      await this.loadBoxData(this.box.id);
    },

    // ── Helpers ─────────────────────────────────────────────────────────
    fmtDate(d) {
      if (!d) return "—";
      return new Date(d).toLocaleDateString("es-MX", { day: "2-digit", month: "short", year: "numeric" });
    },
    fmt2: (n) => Number(n ?? 0).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
  },
};
</script>

<style scoped>
/* Gap del tema Torre (paso 8): tarjetas de KPI propias de esta pantalla (no
   había un patrón .card-* previo aquí) — mismos tokens que el resto de Talento. */
.tc-kpi-box {
  background: var(--tc-bg2);
  border-radius: 10px;
  padding: 12px;
  text-align: center;
}
.tc-kpi-net {
  background: rgba(21, 128, 61, .12);
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
.tc-row-selected > * {
  background: rgba(13, 148, 136, .08);
}
</style>

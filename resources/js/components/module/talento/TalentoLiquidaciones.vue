<template>
  <div class="talento-liquidaciones">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0"><i class="fa fa-file-invoice-dollar me-2 text-primary"></i>Liquidaciones Semanales</h5>
      <button @click="openCalc" class="btn btn-primary btn-sm">
        <i class="fa fa-calculator me-1"></i> Calcular
      </button>
    </div>

    <!-- Filtros -->
    <div class="row g-2 mb-3">
      <div class="col-md-3">
        <input v-model="filters.search" @input="debounceLoad" type="text"
               class="form-control form-control-sm" placeholder="Buscar colaborador…">
      </div>
      <div class="col-md-2">
        <select v-model="filters.status" @change="load" class="form-select form-select-sm">
          <option value="">Todos</option>
          <option value="draft">Borrador</option>
          <option value="closed">Cerrada</option>
        </select>
      </div>
      <div class="col-md-2">
        <input v-model="filters.from" @change="load" type="date" class="form-control form-control-sm" title="Desde">
      </div>
      <div class="col-md-2">
        <input v-model="filters.to" @change="load" type="date" class="form-control form-control-sm" title="Hasta">
      </div>
    </div>

    <!-- Tabla -->
    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
    <div v-else class="table-responsive">
      <table class="table table-hover table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>Colaborador</th>
            <th>Período</th>
            <th class="text-center">Unidades</th>
            <th class="text-end">Base</th>
            <th class="text-end">Sobreprod.</th>
            <th class="text-end">Gross</th>
            <th>Estado</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="liq in liquidaciones" :key="liq.id">
            <td class="fw-semibold small">{{ liq.colaborador?.user?.name }}</td>
            <td class="small">{{ formatDate(liq.period_start) }} – {{ formatDate(liq.period_end) }}</td>
            <td class="text-center">{{ liq.total_units }}</td>
            <td class="text-end">${{ fmt(liq.base_paid) }}</td>
            <td class="text-end text-success">${{ fmt(liq.overproduction_paid) }}</td>
            <td class="text-end fw-bold">${{ fmt(liq.gross_pay) }}</td>
            <td><span class="badge" :class="liq.status === 'closed' ? 'bg-success' : 'bg-warning text-dark'">{{ liq.status === 'closed' ? 'Cerrada' : 'Borrador' }}</span></td>
            <td class="text-end">
              <button @click="viewLiq(liq)" class="btn btn-xs btn-outline-secondary me-1">Ver</button>
              <button v-if="liq.status === 'draft'" @click="openCerrar(liq)" class="btn btn-xs btn-outline-success">Cerrar</button>
            </td>
          </tr>
          <tr v-if="!liquidaciones.length">
            <td colspan="8" class="text-center text-muted py-4">No hay liquidaciones. Usa "Calcular" para generar la primera.</td>
          </tr>
        </tbody>
      </table>
      <div v-if="pagination.last_page > 1" class="d-flex justify-content-end">
        <nav><ul class="pagination pagination-sm mb-0">
          <li class="page-item" :class="{disabled: pagination.current_page<=1}">
            <button class="page-link" @click="goPage(pagination.current_page-1)">‹</button>
          </li>
          <li v-for="p in pagination.last_page" :key="p" class="page-item" :class="{active: p===pagination.current_page}">
            <button class="page-link" @click="goPage(p)">{{ p }}</button>
          </li>
          <li class="page-item" :class="{disabled: pagination.current_page>=pagination.last_page}">
            <button class="page-link" @click="goPage(pagination.current_page+1)">›</button>
          </li>
        </ul></nav>
      </div>
    </div>

    <!-- Modal calcular -->
    <div v-if="calcModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Calcular liquidación</h5>
            <button @click="calcModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Colaborador <span class="text-danger">*</span></label>
                <input v-model="calcModal.colSearch" @input="debounceCalcSearch" type="text"
                       class="form-control" placeholder="Buscar colaborador…">
                <ul v-if="calcModal.suggestions.length" class="list-group mt-1 position-absolute shadow" style="z-index:10001;max-height:160px;overflow-y:auto">
                  <li v-for="c in calcModal.suggestions" :key="c.id" @click="selectCalcCol(c)"
                      class="list-group-item list-group-item-action small cursor-pointer">
                    {{ c.user?.name }}
                  </li>
                </ul>
                <div v-if="calcModal.colaborador_id" class="mt-1 small text-success">
                  <i class="fa fa-check-circle me-1"></i>{{ calcModal.colaborador_name }}
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Período inicio <span class="text-danger">*</span></label>
                <input v-model="calcModal.period_start" type="date" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Período fin <span class="text-danger">*</span></label>
                <input v-model="calcModal.period_end" type="date" class="form-control">
              </div>
              <div class="col-12">
                <button @click="fillLastWeek" class="btn btn-sm btn-outline-secondary">
                  <i class="fa fa-clock me-1"></i>Última semana completa
                </button>
              </div>
              <div v-if="calcModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ calcModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="calcModal.show=false" class="btn btn-secondary" :disabled="calcModal.saving">Cancelar</button>
            <button @click="calcular" class="btn btn-primary" :disabled="calcModal.saving">
              <span v-if="calcModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Calculando…</span>
              <span v-else><i class="fa fa-calculator me-1"></i>Calcular</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal detalle -->
    <div v-if="detail.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.55);z-index:9999">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ detail.liq?.colaborador?.user?.name }} — {{ formatDate(detail.liq?.period_start) }} al {{ formatDate(detail.liq?.period_end) }}</h5>
            <button @click="detail.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body" v-if="detail.liq">
            <div class="row g-3 mb-3">
              <div class="col-md-3">
                <div class="card bg-light border-0 text-center p-3">
                  <div class="fs-4 fw-bold">{{ detail.liq.total_units }}</div>
                  <div class="text-muted small">Unidades validadas</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card bg-light border-0 text-center p-3">
                  <div class="fs-5 fw-bold">${{ fmt(detail.liq.base_paid) }}</div>
                  <div class="text-muted small">Sueldo base</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card bg-success-subtle border-0 text-center p-3">
                  <div class="fs-5 fw-bold text-success">${{ fmt(detail.liq.overproduction_paid) }}</div>
                  <div class="text-muted small">Sobreproducción</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card bg-primary-subtle border-0 text-center p-3">
                  <div class="fs-4 fw-bold text-primary">${{ fmt(detail.liq.gross_pay) }}</div>
                  <div class="text-muted small">GROSS</div>
                </div>
              </div>
            </div>

            <div class="row g-3">
              <!-- Órdenes del período -->
              <div class="col-md-7">
                <h6 class="mb-2">Órdenes validadas del período ({{ detail.liq.orders?.length ?? 0 }} órdenes)</h6>
                <div v-if="!detail.liq.orders?.length" class="text-muted small fst-italic">Sin órdenes validadas.</div>
                <div v-else class="table-responsive" style="max-height:280px;overflow-y:auto">
                  <table class="table table-sm">
                    <thead class="table-light sticky-top">
                      <tr><th>Tipo</th><th class="text-center">Pts</th><th>Validada</th></tr>
                    </thead>
                    <tbody>
                      <tr v-for="o in detail.liq.orders" :key="o.id">
                        <td>{{ o.type?.name }}</td>
                        <td class="text-center fw-bold">{{ o.points }}</td>
                        <td class="small">{{ formatDatetime(o.validated_at) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <!-- Ledger -->
              <div class="col-md-5">
                <h6 class="mb-2">Ledger del período</h6>
                <div v-if="!detail.liq.ledger?.length" class="text-muted small fst-italic">Sin asientos.</div>
                <ul v-else class="list-group list-group-flush" style="max-height:280px;overflow-y:auto">
                  <li v-for="e in detail.liq.ledger" :key="e.id" class="list-group-item px-0 py-1 d-flex justify-content-between small">
                    <span>
                      <span class="badge me-1" :class="e.type==='credit'?'bg-success':'bg-danger'">{{ e.type === 'credit' ? '+' : '−' }}</span>
                      {{ conceptLabel(e.concept) }}
                      <span v-if="e.notes" class="text-muted ms-1">{{ e.notes }}</span>
                    </span>
                    <span class="fw-semibold" :class="e.type==='credit'?'text-success':'text-danger'">
                      ${{ fmt(e.amount) }}
                    </span>
                  </li>
                </ul>
              </div>
            </div>

            <div v-if="detail.liq.other_credits > 0 || detail.liq.other_debits > 0" class="mt-2 small text-muted">
              Otros créditos: ${{ fmt(detail.liq.other_credits) }} · Otros débitos: ${{ fmt(detail.liq.other_debits) }}
            </div>
          </div>
          <div class="modal-footer">
            <button @click="detail.show=false" class="btn btn-secondary">Cerrar</button>
            <button v-if="detail.liq?.status === 'draft'" @click="openCerrar(detail.liq)" class="btn btn-success">
              <i class="fa fa-lock me-1"></i>Cerrar liquidación
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal confirmar cierre -->
    <div v-if="cerrarModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.55);z-index:10001">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-warning-subtle">
            <h5 class="modal-title"><i class="fa fa-lock me-2"></i>Cerrar liquidación</h5>
            <button @click="cerrarModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <p>Esta acción es <strong>irreversible</strong>. ¿Cerrar la liquidación de <strong>{{ cerrarModal.liq?.colaborador?.user?.name }}</strong> por <strong>${{ fmt(cerrarModal.liq?.gross_pay) }}</strong>?</p>
          </div>
          <div class="modal-footer">
            <button @click="cerrarModal.show=false" class="btn btn-secondary" :disabled="cerrarModal.saving">Cancelar</button>
            <button @click="confirmCerrar" class="btn btn-warning" :disabled="cerrarModal.saving">
              <span v-if="cerrarModal.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
              Sí, cerrar
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoLiquidaciones',
  data() {
    return {
      liquidaciones: [],
      loading: true,
      pagination: { current_page: 1, last_page: 1 },
      filters: { search: '', status: '', from: '', to: '' },
      searchTimeout: null,
      calcModal: {
        show: false, colSearch: '', suggestions: [], colaborador_id: null, colaborador_name: '',
        period_start: '', period_end: '', saving: false, error: '',
      },
      calcSearchTimeout: null,
      detail: { show: false, liq: null },
      cerrarModal: { show: false, liq: null, saving: false },
    };
  },
  mounted() { this.load(); },
  methods: {
    debounceLoad() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.load(), 350);
    },
    async load(page = 1) {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/liquidaciones', { params: { ...this.filters, page } });
        this.liquidaciones = data?.data ?? [];
        this.pagination = { current_page: data?.current_page ?? 1, last_page: data?.last_page ?? 1 };
      } finally { this.loading = false; }
    },
    goPage(p) { if (p >= 1 && p <= this.pagination.last_page) this.load(p); },
    openCalc() {
      this.calcModal = { show: true, colSearch: '', suggestions: [], colaborador_id: null, colaborador_name: '',
                         period_start: '', period_end: '', saving: false, error: '' };
    },
    fillLastWeek() {
      const today = new Date();
      const dayOfWeek = today.getDay(); // 0=sun,6=sat
      const diffToLastSat = dayOfWeek === 6 ? 7 : (dayOfWeek + 1);
      const lastSat = new Date(today); lastSat.setDate(today.getDate() - diffToLastSat);
      const lastFri = new Date(lastSat); lastFri.setDate(lastSat.getDate() + 6);
      this.calcModal.period_start = lastSat.toISOString().substring(0,10);
      this.calcModal.period_end   = lastFri.toISOString().substring(0,10);
    },
    debounceCalcSearch() {
      clearTimeout(this.calcSearchTimeout);
      this.calcSearchTimeout = setTimeout(() => this.searchCalcCols(), 300);
    },
    async searchCalcCols() {
      if (!this.calcModal.colSearch.trim()) { this.calcModal.suggestions = []; return; }
      const { data } = await axios.get('/talento/api/colaboradores', { params: { search: this.calcModal.colSearch, per_page: 10 } });
      this.calcModal.suggestions = data?.data ?? [];
    },
    selectCalcCol(c) {
      this.calcModal.colaborador_id   = c.id;
      this.calcModal.colaborador_name = c.user?.name;
      this.calcModal.colSearch        = c.user?.name;
      this.calcModal.suggestions      = [];
    },
    async calcular() {
      this.calcModal.error = '';
      if (!this.calcModal.colaborador_id) { this.calcModal.error = 'Selecciona un colaborador.'; return; }
      if (!this.calcModal.period_start || !this.calcModal.period_end) { this.calcModal.error = 'Define el período.'; return; }
      this.calcModal.saving = true;
      try {
        const { data } = await axios.post('/talento/api/liquidaciones/calcular', {
          colaborador_id: this.calcModal.colaborador_id,
          period_start:   this.calcModal.period_start,
          period_end:     this.calcModal.period_end,
        });
        this.calcModal.show = false;
        this.load();
        // Open detail immediately
        this.viewLiqById(data.id);
      } catch (e) {
        this.calcModal.error = e.response?.data?.error ?? 'Error al calcular.';
      } finally { this.calcModal.saving = false; }
    },
    async viewLiq(liq) { this.viewLiqById(liq.id); },
    async viewLiqById(id) {
      const { data } = await axios.get(`/talento/api/liquidaciones/${id}`);
      this.detail = { show: true, liq: data };
    },
    openCerrar(liq) { this.cerrarModal = { show: true, liq, saving: false }; },
    async confirmCerrar() {
      this.cerrarModal.saving = true;
      try {
        await axios.post(`/talento/api/liquidaciones/${this.cerrarModal.liq.id}/cerrar`);
        this.cerrarModal.show = false;
        this.detail.show = false;
        this.load();
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error al cerrar.');
      } finally { this.cerrarModal.saving = false; }
    },
    conceptLabel(c) {
      return { salary_base: 'Sueldo base', overproduction: 'Sobreproducción', bonus: 'Bono',
               penalty: 'Penalización', advance: 'Adelanto', fund: 'Fondo', adjustment: 'Ajuste',
               embajador: 'Embajador' }[c] ?? c;
    },
    fmt(n) { return Number(n ?? 0).toFixed(2); },
    formatDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    },
    formatDatetime(d) {
      if (!d) return '—';
      return new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
  },
};
</script>

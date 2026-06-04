<template>
  <div class="talento-ordenes">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0"><i class="fa fa-clipboard-list me-2 text-primary"></i>Órdenes de Trabajo</h5>
      <button @click="openCreate" class="btn btn-primary btn-sm">
        <i class="fa fa-plus me-1"></i> Nueva orden
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
          <option value="">Todos los estados</option>
          <option value="pending">Pendiente</option>
          <option value="in_progress">En curso</option>
          <option value="completed">Completada</option>
          <option value="validated">Validada</option>
          <option value="cancelled">Cancelada</option>
        </select>
      </div>
      <div class="col-md-2">
        <select v-model="filters.type_id" @change="load" class="form-select form-select-sm">
          <option value="">Todos los tipos</option>
          <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
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
            <th>#</th>
            <th>Colaborador</th>
            <th>Tipo</th>
            <th class="text-center">Pts</th>
            <th>Agendada</th>
            <th>Estado</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in orders" :key="o.id">
            <td class="text-muted small">{{ o.id }}</td>
            <td>
              <div class="fw-semibold small">{{ o.colaborador?.user?.name }}</div>
            </td>
            <td>
              <span class="badge bg-light text-dark">{{ o.type?.name }}</span>
              <i v-if="o.is_billable" class="fa fa-dollar-sign text-success ms-1" title="Pagable"></i>
            </td>
            <td class="text-center fw-bold">{{ o.points }}</td>
            <td class="small">{{ formatDatetime(o.scheduled_at) }}</td>
            <td><span class="badge" :class="statusBadge(o.status)">{{ statusLabel(o.status) }}</span></td>
            <td class="text-end">
              <button @click="viewOrder(o)" class="btn btn-xs btn-outline-secondary me-1">Ver</button>
              <button v-if="o.status === 'completed'" @click="openValidate(o)" class="btn btn-xs btn-outline-success">Validar</button>
              <button v-if="canAdvance(o.status)" @click="advanceStatus(o)" class="btn btn-xs btn-outline-primary">
                {{ nextStatusLabel(o.status) }}
              </button>
            </td>
          </tr>
          <tr v-if="!orders.length">
            <td colspan="7" class="text-center text-muted py-4">No hay órdenes que mostrar.</td>
          </tr>
        </tbody>
      </table>
      <div v-if="pagination.last_page > 1" class="d-flex justify-content-end">
        <nav><ul class="pagination pagination-sm mb-0">
          <li class="page-item" :class="{disabled: pagination.current_page <= 1}">
            <button class="page-link" @click="goPage(pagination.current_page-1)">‹</button>
          </li>
          <li v-for="p in pagination.last_page" :key="p" class="page-item" :class="{active: p === pagination.current_page}">
            <button class="page-link" @click="goPage(p)">{{ p }}</button>
          </li>
          <li class="page-item" :class="{disabled: pagination.current_page >= pagination.last_page}">
            <button class="page-link" @click="goPage(pagination.current_page+1)">›</button>
          </li>
        </ul></nav>
      </div>
    </div>

    <!-- Modal crear orden -->
    <div v-if="createModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Nueva Orden de Trabajo</h5>
            <button @click="createModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Colaborador <span class="text-danger">*</span></label>
                <input v-model="colSearch" @input="debounceColSearch" type="text" class="form-control"
                       placeholder="Buscar colaborador…">
                <ul v-if="colSuggestions.length" class="list-group mt-1 position-absolute shadow" style="z-index:10001;max-height:180px;overflow-y:auto">
                  <li v-for="c in colSuggestions" :key="c.id" @click="selectCol(c)"
                      class="list-group-item list-group-item-action small cursor-pointer">
                    {{ c.user?.name }} <span class="text-muted">· {{ c.type }}</span>
                  </li>
                </ul>
                <div v-if="createModal.colaborador_id" class="mt-1 small text-success">
                  <i class="fa fa-check-circle me-1"></i>{{ createModal.colaborador_name }}
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Tipo de orden <span class="text-danger">*</span></label>
                <select v-model="createModal.type_id" @change="onTypeChange" class="form-select">
                  <option :value="null">— Seleccionar —</option>
                  <option v-for="t in activeTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <div v-if="selectedType" class="mt-1 small text-muted">
                  {{ selectedType.points }} pts
                  · {{ selectedType.is_billable ? '💲 Pagable' : 'No pagable' }}
                  {{ selectedType.requires_validation ? '· Requiere validación' : '' }}
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Agendar para</label>
                <input v-model="createModal.scheduled_at" type="datetime-local" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea v-model="createModal.notes" class="form-control" rows="2"></textarea>
              </div>
              <div v-if="createModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ createModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="createModal.show=false" class="btn btn-secondary" :disabled="createModal.saving">Cancelar</button>
            <button @click="saveOrder" class="btn btn-primary" :disabled="createModal.saving">
              <span v-if="createModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
              <span v-else>Crear orden</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal ver detalle -->
    <div v-if="detail.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Orden #{{ detail.order?.id }} — {{ detail.order?.colaborador?.user?.name }}</h5>
            <button @click="detail.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body" v-if="detail.order">
            <div class="row g-3">
              <div class="col-md-4"><strong>Tipo:</strong> {{ detail.order.type?.name }}</div>
              <div class="col-md-2"><strong>Pts:</strong> {{ detail.order.points }}</div>
              <div class="col-md-3"><strong>Estado:</strong> <span class="badge" :class="statusBadge(detail.order.status)">{{ statusLabel(detail.order.status) }}</span></div>
              <div class="col-md-3"><strong>Agendada:</strong> {{ formatDatetime(detail.order.scheduled_at) }}</div>
              <div class="col-md-4" v-if="detail.order.validated_at">
                <strong>Validada:</strong> {{ formatDatetime(detail.order.validated_at) }}<br>
                <small class="text-muted">por {{ detail.order.validated_by?.name }}</small>
              </div>
              <div class="col-12" v-if="detail.order.notes"><strong>Notas:</strong> {{ detail.order.notes }}</div>
            </div>
            <div v-if="detail.order.activities?.length" class="mt-3">
              <h6>Actividades</h6>
              <ul class="list-group list-group-flush">
                <li v-for="a in detail.order.activities" :key="a.id" class="list-group-item px-0 py-1 small">
                  {{ a.description }}
                  <span v-if="a.duration_minutes" class="text-muted ms-1">({{ a.duration_minutes }} min)</span>
                </li>
              </ul>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="detail.show=false" class="btn btn-secondary">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal validar -->
    <div v-if="validateModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Validar orden</h5>
            <button @click="validateModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <p>¿Confirmar validación de la orden <strong>#{{ validateModal.order?.id }}</strong> — <em>{{ validateModal.order?.type?.name }}</em> ({{ validateModal.order?.points }} pts)?</p>
            <p class="text-muted small mb-0">Una vez validada se contabiliza para la liquidación semanal.</p>
          </div>
          <div class="modal-footer">
            <button @click="validateModal.show=false" class="btn btn-secondary" :disabled="validateModal.saving">Cancelar</button>
            <button @click="confirmValidate" class="btn btn-success" :disabled="validateModal.saving">
              <span v-if="validateModal.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
              Sí, validar
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoOrdenes',
  data() {
    return {
      orders: [],
      types: [],
      loading: true,
      pagination: { current_page: 1, last_page: 1 },
      filters: { search: '', status: '', type_id: '', from: '', to: '' },
      searchTimeout: null,
      createModal: { show: false, colaborador_id: null, colaborador_name: '', type_id: null, scheduled_at: '', notes: '', saving: false, error: '' },
      detail: { show: false, order: null },
      validateModal: { show: false, order: null, saving: false },
      colSearch: '',
      colSuggestions: [],
      colSearchTimeout: null,
    };
  },
  computed: {
    activeTypes() { return this.types.filter(t => t.active); },
    selectedType() { return this.types.find(t => t.id === this.createModal.type_id) || null; },
  },
  mounted() {
    this.loadTypes();
    this.load();
  },
  methods: {
    debounceLoad() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.load(), 350);
    },
    async load(page = 1) {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/ordenes', { params: { ...this.filters, page } });
        this.orders = data?.data ?? [];
        this.pagination = { current_page: data?.current_page ?? 1, last_page: data?.last_page ?? 1 };
      } finally { this.loading = false; }
    },
    async loadTypes() {
      const { data } = await axios.get('/talento/api/order-types');
      this.types = data ?? [];
    },
    goPage(p) { if (p >= 1 && p <= this.pagination.last_page) this.load(p); },
    openCreate() {
      this.createModal = { show: true, colaborador_id: null, colaborador_name: '', type_id: null, scheduled_at: '', notes: '', saving: false, error: '' };
      this.colSearch = '';
      this.colSuggestions = [];
    },
    debounceColSearch() {
      clearTimeout(this.colSearchTimeout);
      this.colSearchTimeout = setTimeout(() => this.searchCols(), 300);
    },
    async searchCols() {
      if (!this.colSearch.trim()) { this.colSuggestions = []; return; }
      const { data } = await axios.get('/talento/api/colaboradores', { params: { search: this.colSearch, per_page: 10 } });
      this.colSuggestions = data?.data ?? [];
    },
    selectCol(c) {
      this.createModal.colaborador_id = c.id;
      this.createModal.colaborador_name = c.user?.name;
      this.colSearch = c.user?.name;
      this.colSuggestions = [];
    },
    onTypeChange() { /* selectedType computed updates automatically */ },
    async saveOrder() {
      this.createModal.error = '';
      if (!this.createModal.colaborador_id) { this.createModal.error = 'Selecciona un colaborador.'; return; }
      if (!this.createModal.type_id) { this.createModal.error = 'Selecciona un tipo de orden.'; return; }
      this.createModal.saving = true;
      try {
        await axios.post('/talento/api/ordenes', {
          colaborador_id: this.createModal.colaborador_id,
          type_id:        this.createModal.type_id,
          scheduled_at:   this.createModal.scheduled_at || null,
          notes:          this.createModal.notes || null,
        });
        this.createModal.show = false;
        this.load();
      } catch (e) {
        this.createModal.error = e.response?.data?.message ?? 'Error al crear la orden.';
      } finally { this.createModal.saving = false; }
    },
    async viewOrder(o) {
      const { data } = await axios.get(`/talento/api/ordenes/${o.id}`);
      this.detail = { show: true, order: data };
    },
    openValidate(o) { this.validateModal = { show: true, order: o, saving: false }; },
    async confirmValidate() {
      this.validateModal.saving = true;
      try {
        await axios.post(`/talento/api/ordenes/${this.validateModal.order.id}/validate`);
        this.validateModal.show = false;
        this.load();
      } catch (e) {
        alert(e.response?.data?.error ?? 'Error al validar.');
      } finally { this.validateModal.saving = false; }
    },
    canAdvance(status) { return ['pending','in_progress'].includes(status); },
    nextStatusLabel(status) { return { pending: 'Iniciar', in_progress: 'Completar' }[status] ?? ''; },
    async advanceStatus(o) {
      const next = { pending: 'in_progress', in_progress: 'completed' }[o.status];
      if (!next) return;
      await axios.put(`/talento/api/ordenes/${o.id}/status`, { status: next });
      this.load();
    },
    statusBadge(s) {
      return { pending: 'bg-secondary', in_progress: 'bg-primary', completed: 'bg-info text-dark',
               validated: 'bg-success', cancelled: 'bg-danger' }[s] ?? 'bg-light';
    },
    statusLabel(s) {
      return { pending: 'Pendiente', in_progress: 'En curso', completed: 'Completada',
               validated: 'Validada', cancelled: 'Cancelada' }[s] ?? s;
    },
    formatDatetime(d) {
      if (!d) return '—';
      return new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
  },
};
</script>

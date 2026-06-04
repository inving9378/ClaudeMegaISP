<template>
  <div class="talento-colaboradores">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0"><i class="fa fa-id-badge me-2 text-primary"></i>Colaboradores</h5>
      <button v-if="canManage" @click="openModal(null)" class="btn btn-primary btn-sm">
        <i class="fa fa-plus me-1"></i> Nuevo colaborador
      </button>
    </div>

    <!-- Filtros -->
    <div class="row g-2 mb-3">
      <div class="col-md-4">
        <input v-model="filters.search" @input="debounceLoad" type="text"
               class="form-control form-control-sm" placeholder="Buscar nombre o email…">
      </div>
      <div class="col-md-2">
        <select v-model="filters.status" @change="load" class="form-select form-select-sm">
          <option value="">Todos los status</option>
          <option value="active">Activo</option>
          <option value="inactive">Inactivo</option>
          <option value="suspended">Suspendido</option>
        </select>
      </div>
      <div class="col-md-2">
        <select v-model="filters.type" @change="load" class="form-select form-select-sm">
          <option value="">Todos los tipos</option>
          <option value="interno">Interno</option>
          <option value="externo">Externo</option>
        </select>
      </div>
    </div>

    <!-- Tabla -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary"></div>
    </div>
    <div v-else class="table-responsive">
      <table class="table table-hover table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>Nombre</th>
            <th>Tipo</th>
            <th>Departamento</th>
            <th>Supervisor</th>
            <th>Ingreso</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="col in items" :key="col.id">
            <td>
              <div class="fw-semibold">{{ col.user?.name }}</div>
              <div class="small text-muted">{{ col.user?.email }}</div>
            </td>
            <td>
              <span class="badge" :class="col.type === 'interno' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary'">
                {{ col.type }}
              </span>
            </td>
            <td>{{ col.department ?? '—' }}</td>
            <td>{{ col.supervisor?.user?.name ?? '—' }}</td>
            <td class="small">{{ formatDate(col.hire_date) }}</td>
            <td><span class="badge" :class="statusBadge(col.status)">{{ statusLabel(col.status) }}</span></td>
            <td>
              <button v-if="canManage" @click="openModal(col)" class="btn btn-xs btn-outline-primary me-1">
                <i class="fa fa-pen"></i>
              </button>
              <a :href="`/talento/custodia`" class="btn btn-xs btn-outline-secondary me-1" title="Custodia">
                <i class="fa fa-boxes"></i>
              </a>
            </td>
          </tr>
          <tr v-if="!items.length">
            <td colspan="7" class="text-center text-muted py-4">No se encontraron colaboradores.</td>
          </tr>
        </tbody>
      </table>

      <!-- Paginación -->
      <div v-if="pagination.last_page > 1" class="d-flex justify-content-end">
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item" :class="{ disabled: pagination.current_page <= 1 }">
              <button class="page-link" @click="goPage(pagination.current_page - 1)">‹</button>
            </li>
            <li v-for="p in pagination.last_page" :key="p" class="page-item" :class="{ active: p === pagination.current_page }">
              <button class="page-link" @click="goPage(p)">{{ p }}</button>
            </li>
            <li class="page-item" :class="{ disabled: pagination.current_page >= pagination.last_page }">
              <button class="page-link" @click="goPage(pagination.current_page + 1)">›</button>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- Modal crear/editar -->
    <div v-if="modal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ modal.id ? 'Editar colaborador' : 'Nuevo colaborador' }}</h5>
            <button @click="closeModal" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">

              <!-- Usuario (solo en creación) -->
              <div v-if="!modal.id" class="col-12">
                <label class="form-label">Usuario del sistema <span class="text-danger">*</span></label>
                <input v-model="userSearch" @input="debounceUserSearch" type="text"
                       class="form-control" placeholder="Buscar por nombre o email…">
                <ul v-if="userSuggestions.length" class="list-group mt-1 position-absolute shadow" style="z-index:10000;max-height:200px;overflow-y:auto">
                  <li v-for="u in userSuggestions" :key="u.id"
                      @click="selectUser(u)"
                      class="list-group-item list-group-item-action cursor-pointer small">
                    <strong>{{ u.name }}</strong> <span class="text-muted">{{ u.email }}</span>
                  </li>
                </ul>
                <div v-if="modal.user_id" class="mt-1 small text-success">
                  <i class="fa fa-check-circle me-1"></i> {{ modal.user_name }}
                </div>
                <div v-if="errors.user_id" class="text-danger small mt-1">{{ errors.user_id }}</div>
              </div>
              <div v-else class="col-12">
                <label class="form-label">Usuario</label>
                <input type="text" class="form-control" :value="modal.user_name" disabled>
              </div>

              <!-- Tipo -->
              <div class="col-md-4">
                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                <div class="d-flex gap-3 mt-1">
                  <div class="form-check">
                    <input v-model="modal.type" type="radio" value="interno" class="form-check-input" id="tipo-int">
                    <label class="form-check-label" for="tipo-int">Interno</label>
                  </div>
                  <div class="form-check">
                    <input v-model="modal.type" type="radio" value="externo" class="form-check-input" id="tipo-ext">
                    <label class="form-check-label" for="tipo-ext">Externo</label>
                  </div>
                </div>
              </div>

              <!-- Status -->
              <div class="col-md-4">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select v-model="modal.status" class="form-select">
                  <option value="active">Activo</option>
                  <option value="inactive">Inactivo</option>
                  <option value="suspended">Suspendido</option>
                </select>
              </div>

              <!-- Departamento -->
              <div class="col-md-4">
                <label class="form-label">Departamento</label>
                <input v-model="modal.department" type="text" class="form-control" placeholder="Ej. Técnico, Ventas…">
              </div>

              <!-- Supervisor -->
              <div class="col-md-6">
                <label class="form-label">Supervisor</label>
                <select v-model="modal.supervisor_id" class="form-select">
                  <option :value="null">— Sin supervisor —</option>
                  <option v-for="c in supervisores" :key="c.id" :value="c.id">
                    {{ c.user?.name }}
                  </option>
                </select>
              </div>

              <!-- Fecha ingreso -->
              <div class="col-md-3">
                <label class="form-label">Fecha de ingreso</label>
                <input v-model="modal.hire_date" type="date" class="form-control">
              </div>

              <!-- Salario -->
              <div class="col-md-3">
                <label class="form-label">Salario base</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input v-model="modal.base_salary" type="number" step="0.01" min="0" class="form-control" placeholder="0.00">
                </div>
              </div>

              <!-- Notas -->
              <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea v-model="modal.notes" class="form-control" rows="2"></textarea>
              </div>

              <div v-if="errors._global" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ errors._global }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="closeModal" class="btn btn-secondary" :disabled="saving">Cancelar</button>
            <button @click="save" class="btn btn-primary" :disabled="saving">
              <span v-if="saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
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
  name: 'TalentoColaboradores',
  data() {
    return {
      items: [],
      supervisores: [],
      loading: true,
      saving: false,
      pagination: { current_page: 1, last_page: 1 },
      filters: { search: '', status: '', type: '' },
      modal: { show: false, id: null, user_id: null, user_name: '', type: 'interno', status: 'active',
               department: '', supervisor_id: null, hire_date: '', base_salary: '', notes: '' },
      errors: {},
      userSearch: '',
      userSuggestions: [],
      userSearchTimeout: null,
      searchTimeout: null,
      canManage: false,
    };
  },
  mounted() {
    this.checkPermission();
    this.load();
    this.loadSupervisores();
  },
  methods: {
    async checkPermission() {
      try {
        await axios.get('/talento/api/colaboradores/users-disponibles', { params: { search: '' } });
        this.canManage = true;
      } catch {
        this.canManage = false;
      }
    },
    debounceLoad() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.load(), 350);
    },
    async load(page = 1) {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/colaboradores', {
          params: { ...this.filters, page }
        });
        this.items = data?.data ?? [];
        this.pagination = { current_page: data?.current_page ?? 1, last_page: data?.last_page ?? 1 };
      } finally {
        this.loading = false;
      }
    },
    async loadSupervisores() {
      try {
        const { data } = await axios.get('/talento/api/colaboradores', { params: { per_page: 200 } });
        this.supervisores = data?.data ?? [];
      } catch {}
    },
    goPage(p) {
      if (p < 1 || p > this.pagination.last_page) return;
      this.load(p);
    },
    openModal(col) {
      this.errors = {};
      this.userSearch = '';
      this.userSuggestions = [];
      if (col) {
        this.modal = {
          show: true, id: col.id,
          user_id: col.user_id, user_name: col.user?.name ?? '',
          type: col.type, status: col.status,
          department: col.department ?? '', supervisor_id: col.supervisor_id,
          hire_date: col.hire_date ? col.hire_date.substring(0,10) : '',
          base_salary: col.base_salary ?? '', notes: col.notes ?? '',
        };
      } else {
        this.modal = { show: true, id: null, user_id: null, user_name: '', type: 'interno', status: 'active',
                       department: '', supervisor_id: null, hire_date: '', base_salary: '', notes: '' };
      }
    },
    closeModal() { this.modal.show = false; },
    debounceUserSearch() {
      clearTimeout(this.userSearchTimeout);
      this.userSearchTimeout = setTimeout(() => this.searchUsers(), 300);
    },
    async searchUsers() {
      if (!this.userSearch.trim()) { this.userSuggestions = []; return; }
      try {
        const { data } = await axios.get('/talento/api/colaboradores/users-disponibles', { params: { search: this.userSearch } });
        this.userSuggestions = data ?? [];
      } catch {}
    },
    selectUser(u) {
      this.modal.user_id = u.id;
      this.modal.user_name = u.name;
      this.userSearch = u.name;
      this.userSuggestions = [];
    },
    async save() {
      this.errors = {};
      if (!this.modal.id && !this.modal.user_id) {
        this.errors.user_id = 'Selecciona un usuario.';
        return;
      }
      this.saving = true;
      try {
        const payload = {
          user_id: this.modal.user_id,
          type: this.modal.type,
          status: this.modal.status,
          department: this.modal.department || null,
          supervisor_id: this.modal.supervisor_id || null,
          hire_date: this.modal.hire_date || null,
          base_salary: this.modal.base_salary || null,
          notes: this.modal.notes || null,
        };
        if (this.modal.id) {
          await axios.put(`/talento/api/colaboradores/${this.modal.id}`, payload);
        } else {
          await axios.post('/talento/api/colaboradores', payload);
        }
        this.closeModal();
        this.load();
        this.loadSupervisores();
      } catch (e) {
        if (e.response?.status === 422) {
          const errs = e.response.data?.errors ?? {};
          Object.keys(errs).forEach(k => { this.errors[k] = errs[k][0]; });
        } else {
          this.errors._global = e.response?.data?.message ?? 'Error al guardar.';
        }
      } finally {
        this.saving = false;
      }
    },
    statusBadge(s) {
      return { active: 'bg-success', inactive: 'bg-secondary', suspended: 'bg-warning text-dark' }[s] ?? 'bg-light';
    },
    statusLabel(s) {
      return { active: 'Activo', inactive: 'Inactivo', suspended: 'Suspendido' }[s] ?? s;
    },
    formatDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    },
  },
};
</script>

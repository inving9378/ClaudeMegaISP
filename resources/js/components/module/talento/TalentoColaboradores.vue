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
              <!-- Roles Spatie (solo lectura) — la gestión vive en Administradores -->
              <div v-if="col.user?.role_names?.length" class="mt-1">
                <span v-for="r in col.user.role_names" :key="r"
                      class="badge bg-light text-secondary border me-1" style="font-size:10px;">
                  {{ r }}
                </span>
              </div>
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
            <td class="text-end">
              <button v-if="canManage" @click="openModal(col)" class="btn btn-xs btn-outline-primary me-1">
                <i class="fa fa-pen"></i>
              </button>
              <a :href="`/talento/custodia`" class="btn btn-xs btn-outline-secondary me-1" title="Custodia">
                <i class="fa fa-boxes"></i>
              </a>
              <button @click="openDocumentos(col)" class="btn btn-xs btn-outline-secondary me-1" title="Documentos">
                <i class="fa fa-file-alt"></i>
              </button>
              <!-- Cross-link: gestión de acceso en Administradores -->
              <a :href="`/administracion/user/${col.user_id}/editar`"
                 class="btn btn-xs btn-outline-info" title="Gestión de acceso (Administradores)"
                 target="_blank">
                <i class="fa fa-key"></i>
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
                <div class="d-flex align-items-center gap-2">
                  <input type="text" class="form-control" :value="modal.user_name" disabled>
                  <!-- Cross-link: gestión de acceso en Administradores -->
                  <a :href="`/administracion/user/${modal.user_id}/editar`"
                     class="btn btn-sm btn-outline-info flex-shrink-0" target="_blank"
                     title="Editar acceso, roles y permisos en Administradores">
                    <i class="fa fa-key me-1"></i>Acceso
                  </a>
                </div>
                <!-- Roles Spatie actuales (solo lectura) -->
                <div v-if="modal.role_names?.length" class="mt-2">
                  <small class="text-muted me-2">Roles:</small>
                  <span v-for="r in modal.role_names" :key="r"
                        class="badge bg-light text-secondary border me-1">{{ r }}</span>
                  <small class="text-muted fst-italic">
                    (Gestión de roles en <a href="/administracion/user" target="_blank">Administradores</a>)
                  </small>
                </div>
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
                <div v-if="!modal.id && modal.department" class="form-text text-success">
                  <i class="fa fa-magic me-1"></i>Autodetectado del rol — puedes editarlo.
                </div>
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

              <!-- Expediente RH (item #199) — datos personales sensibles, gateados en la lectura por talento.expediente.view -->
              <div class="col-12 mt-2">
                <hr class="my-1">
                <h6 class="text-muted small text-uppercase mb-0">Expediente RH</h6>
              </div>

              <div class="col-md-4">
                <label class="form-label">Fecha de nacimiento</label>
                <input v-model="modal.birth_date" type="date" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">CURP</label>
                <input v-model="modal.curp" type="text" maxlength="18" class="form-control text-uppercase" placeholder="CURP">
              </div>
              <div class="col-md-4">
                <label class="form-label">NSS</label>
                <input v-model="modal.nss" type="text" maxlength="11" class="form-control" placeholder="Número de seguro social">
              </div>

              <div class="col-md-6">
                <label class="form-label">Contacto de emergencia</label>
                <input v-model="modal.emergency_contact_name" type="text" class="form-control" placeholder="Nombre">
              </div>
              <div class="col-md-6">
                <label class="form-label">Teléfono de emergencia</label>
                <input v-model="modal.emergency_contact_phone" type="text" class="form-control" placeholder="Teléfono">
              </div>

              <div class="col-md-4">
                <label class="form-label">Puesto</label>
                <select v-model="modal.puesto_id" class="form-select" @change="onPuestoChange">
                  <option :value="null">— Sin especificar —</option>
                  <option v-for="p in puestos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                </select>
                <div v-if="modal.job_title && !puestoCoincide" class="form-text text-muted">
                  Valor libre guardado: "{{ modal.job_title }}"
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Tipo de relación laboral</label>
                <select v-model="modal.relation_type" class="form-select">
                  <option :value="null">— Sin especificar —</option>
                  <option value="indeterminada">Indeterminada</option>
                  <option value="determinada">Determinada</option>
                  <option value="obra">Por obra</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha fin de relación</label>
                <input v-model="modal.relation_end_date" type="date" class="form-control">
              </div>

              <div class="col-md-4">
                <label class="form-label">Periodicidad de pago</label>
                <select v-model="modal.pay_frequency" class="form-select">
                  <option :value="null">— Sin especificar —</option>
                  <option value="semanal">Semanal</option>
                  <option value="quincenal">Quincenal</option>
                  <option value="mensual">Mensual</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Lugar principal de trabajo</label>
                <input v-model="modal.work_location" type="text" class="form-control" placeholder="Ej. Base Centro">
              </div>
              <div class="col-md-2">
                <label class="form-label">Entrada</label>
                <input v-model="modal.shift_start" type="time" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label">Salida</label>
                <input v-model="modal.shift_end" type="time" class="form-control">
              </div>

              <div class="col-12">
                <label class="form-label">Días laborables</label>
                <div class="d-flex gap-2 flex-wrap">
                  <div v-for="d in diasSemana" :key="d.value" class="form-check">
                    <input type="checkbox" class="form-check-input" :id="`dia-${d.value}`"
                           :checked="workDaysList.includes(d.value)" @change="toggleWorkDay(d.value)">
                    <label class="form-check-label small" :for="`dia-${d.value}`">{{ d.label }}</label>
                  </div>
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

    <!-- Modal Documentos del expediente (solo lectura, Hijo D2 fase C) -->
    <div v-if="documentosModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Documentos — {{ documentosModal.colaboradorName }}</h5>
            <button @click="closeDocumentos" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div v-if="documentosModal.loading" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-primary"></div>
            </div>
            <div v-else-if="!documentosModal.items.length" class="text-muted small text-center py-3">
              Este colaborador no tiene documentos generados (sin puesto asignado o sin plantillas para su puesto).
            </div>
            <ul v-else class="list-group">
              <li v-for="doc in documentosModal.items" :key="doc.id"
                  class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold">{{ doc.template?.name ?? '—' }}</div>
                  <span class="badge" :class="doc.status === 'completo' ? 'bg-success' : 'bg-warning text-dark'"
                        :title="doc.status === 'completo' ? 'Documento completo' : 'Faltan datos por capturar en el sistema'">
                    {{ doc.status === 'completo' ? 'Completo' : 'Pendiente' }}
                  </span>
                </div>
                <a :href="`/talento/colaboradores/${documentosModal.colaboradorId}/documentos/${doc.id}`"
                   target="_blank" class="btn btn-sm btn-outline-primary">
                  <i class="fa fa-eye me-1"></i>Abrir
                </a>
              </li>
            </ul>
          </div>
          <div class="modal-footer">
            <button @click="closeDocumentos" class="btn btn-secondary">Cerrar</button>
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
      modal: this.emptyModal(),
      errors: {},
      diasSemana: [
        { value: '1', label: 'Lun' }, { value: '2', label: 'Mar' }, { value: '3', label: 'Mié' },
        { value: '4', label: 'Jue' }, { value: '5', label: 'Vie' }, { value: '6', label: 'Sáb' },
        { value: '7', label: 'Dom' },
      ],
      userSearch: '',
      userSuggestions: [],
      userSearchTimeout: null,
      searchTimeout: null,
      canManage: false,
      roleDepartments: {},
      documentosModal: { show: false, loading: false, colaboradorId: null, colaboradorName: '', items: [] },
      puestos: [],
    };
  },
  computed: {
    workDaysList() {
      return (this.modal.work_days || '').split(',').filter(Boolean);
    },
    // item #9990208 — si job_title libre coincide (case-insensitive/trim) con algún puesto
    // del catálogo, no se muestra como "valor libre" aparte (ya está representado por el select).
    puestoCoincide() {
      const jt = (this.modal.job_title || '').trim().toLowerCase();
      if (!jt) return true;
      return this.puestos.some(p => (p.nombre || '').trim().toLowerCase() === jt);
    },
  },
  mounted() {
    this.checkPermission();
    this.load();
    this.loadSupervisores();
    this.loadPuestos();
    this.loadRoleDepartments();
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
    async loadPuestos() {
      try {
        const { data } = await axios.get('/talento/api/puestos', { params: { activo: 1 } });
        this.puestos = data ?? [];
      } catch {}
    },
    // Al elegir un puesto del catálogo, sincroniza job_title como espejo legible (#870 y otros
    // lectores siguen leyendo job_title — ver migración 2026_09_03_150100).
    onPuestoChange() {
      const p = this.puestos.find(p => p.id === this.modal.puesto_id);
      if (p) this.modal.job_title = p.nombre;
    },
    goPage(p) {
      if (p < 1 || p > this.pagination.last_page) return;
      this.load(p);
    },
    emptyModal() {
      return {
        show: false, id: null, user_id: null, user_name: '', type: 'interno', status: 'active',
        department: '', supervisor_id: null, hire_date: '', base_salary: '', notes: '',
        role_names: [],
        // Expediente RH (item #199)
        birth_date: '', curp: '', nss: '', emergency_contact_name: '', emergency_contact_phone: '',
        job_title: '', puesto_id: null, relation_type: null, relation_end_date: '', pay_frequency: null,
        work_location: '', shift_start: '', shift_end: '', work_days: '',
      };
    },
    openModal(col) {
      this.errors = {};
      this.userSearch = '';
      this.userSuggestions = [];
      if (col) {
        this.modal = {
          ...this.emptyModal(),
          show: true, id: col.id,
          user_id: col.user_id, user_name: col.user?.name ?? '',
          type: col.type, status: col.status,
          department: col.department ?? '', supervisor_id: col.supervisor_id,
          hire_date: col.hire_date ? col.hire_date.substring(0,10) : '',
          base_salary: col.base_salary ?? '', notes: col.notes ?? '',
          // Roles Spatie (solo lectura — se gestionan en Administradores)
          role_names: col.user?.role_names ?? [],
          // Expediente RH — ausente en la respuesta si el usuario no tiene talento.expediente.view
          birth_date: col.birth_date ? col.birth_date.substring(0,10) : '',
          curp: col.curp ?? '', nss: col.nss ?? '',
          emergency_contact_name: col.emergency_contact_name ?? '',
          emergency_contact_phone: col.emergency_contact_phone ?? '',
          job_title: col.job_title ?? '', puesto_id: col.puesto_id ?? null, relation_type: col.relation_type ?? null,
          relation_end_date: col.relation_end_date ? col.relation_end_date.substring(0,10) : '',
          pay_frequency: col.pay_frequency ?? null, work_location: col.work_location ?? '',
          shift_start: col.shift_start ?? '', shift_end: col.shift_end ?? '',
          work_days: col.work_days ?? '',
        };
      } else {
        this.modal = { ...this.emptyModal(), show: true };
      }
    },
    closeModal() { this.modal.show = false; },
    toggleWorkDay(value) {
      const list = this.workDaysList;
      const next = list.includes(value) ? list.filter(d => d !== value) : [...list, value].sort();
      this.modal.work_days = next.join(',');
    },
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
    async loadRoleDepartments() {
      try {
        const { data } = await axios.get('/talento/api/colaboradores/role-departments');
        this.roleDepartments = data ?? {};
      } catch {}
    },
    selectUser(u) {
      this.modal.user_id = u.id;
      this.modal.user_name = u.name;
      this.userSearch = u.name;
      this.userSuggestions = [];
      // Pre-rellenar department según rol (prioridad: TECNICO_PLANTA > TECNICO_INSTALADOR > TECNICO > otros)
      const priority = ['TECNICO_PLANTA', 'TECNICO_INSTALADOR', 'TECNICO'];
      const roleNames = u.role_names ?? [];
      let inferred = null;
      for (const r of priority) {
        if (roleNames.includes(r) && this.roleDepartments[r]) { inferred = this.roleDepartments[r]; break; }
      }
      if (!inferred) {
        for (const r of roleNames) {
          if (this.roleDepartments[r]) { inferred = this.roleDepartments[r]; break; }
        }
      }
      if (inferred) this.modal.department = inferred;
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
          // Expediente RH (item #199)
          birth_date: this.modal.birth_date || null,
          curp: this.modal.curp || null,
          nss: this.modal.nss || null,
          emergency_contact_name: this.modal.emergency_contact_name || null,
          emergency_contact_phone: this.modal.emergency_contact_phone || null,
          job_title: this.modal.job_title || null,
          puesto_id: this.modal.puesto_id || null,
          relation_type: this.modal.relation_type || null,
          relation_end_date: this.modal.relation_end_date || null,
          pay_frequency: this.modal.pay_frequency || null,
          work_location: this.modal.work_location || null,
          shift_start: this.modal.shift_start || null,
          shift_end: this.modal.shift_end || null,
          work_days: this.modal.work_days || null,
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
    async openDocumentos(col) {
      this.documentosModal = {
        show: true, loading: true,
        colaboradorId: col.id, colaboradorName: col.user?.name ?? '',
        items: [],
      };
      try {
        const { data } = await axios.get(`/talento/api/colaboradores/${col.id}/documentos`);
        this.documentosModal.items = data ?? [];
      } catch {
        this.documentosModal.items = [];
      } finally {
        this.documentosModal.loading = false;
      }
    },
    closeDocumentos() { this.documentosModal.show = false; },
  },
};
</script>

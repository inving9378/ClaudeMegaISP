<template>
  <div class="talento-niveles">

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'colaboradores' }" href="#"
           @click.prevent="tab='colaboradores'">
          <i class="fa fa-users me-1"></i>Colaboradores
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'definition' }" href="#"
           @click.prevent="tab='definition'">
          <i class="fa fa-layer-group me-1"></i>Definición de niveles
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'gating' }" href="#"
           @click.prevent="tab='gating'; loadGating()">
          <i class="fa fa-lock me-1"></i>Gating
        </a>
      </li>
    </ul>

    <!-- ── TAB COLABORADORES ── -->
    <div v-if="tab === 'colaboradores'">
      <div class="row g-3 mb-3">
        <div class="col-md-5">
          <label class="form-label small text-muted">Colaborador</label>
          <select v-model="selectedColId" @change="loadEligibility" class="form-select">
            <option :value="null">— Seleccionar colaborador —</option>
            <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="loadingElig" class="text-center py-5"><div class="spinner-border text-primary"></div></div>

      <div v-else-if="eligibility" class="row g-3">
        <!-- Panel principal -->
        <div class="col-md-8">
          <div class="card shadow-sm">
            <div class="card-body">
              <!-- Nivel actual -->
              <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                <div>
                  <div class="small text-muted">Nivel actual</div>
                  <div v-if="eligibility.current_level" class="d-flex align-items-center gap-2 mt-1">
                    <span class="badge fs-6" :class="rankColor(eligibility.current_level.rank)">
                      {{ eligibility.current_level.name }}
                    </span>
                    <span class="small text-muted">rank {{ eligibility.current_level.rank }}</span>
                    <span class="fw-bold text-primary">${{ fmtMXN(eligibility.current_level.base_salary) }}</span>
                  </div>
                  <div v-else class="text-muted small fst-italic">Sin nivel asignado</div>
                </div>
                <div v-if="eligibility.eligible_level" class="ms-md-3">
                  <div class="small text-muted">Nivel elegible ahora</div>
                  <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="badge fs-6" :class="rankColor(eligibility.eligible_level.rank)">
                      {{ eligibility.eligible_level.name }}
                    </span>
                    <span class="fw-bold text-success">${{ fmtMXN(eligibility.eligible_level.base_salary) }}</span>
                  </div>
                </div>
              </div>

              <!-- Progreso certificaciones -->
              <h6 class="text-uppercase text-muted small mb-2">Progreso de certificaciones</h6>
              <div class="mb-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <div class="progress flex-fill" style="height:10px;">
                    <div class="progress-bar bg-success"
                         :style="`width:${eligibility.progress?.completion_pct ?? 0}%`"></div>
                  </div>
                  <span class="small fw-bold">{{ eligibility.progress?.certified_count ?? 0 }} / {{ eligibility.progress?.total_courses ?? 0 }}</span>
                </div>
                <div class="row g-1 mt-1">
                  <div v-for="c in eligibility.progress?.courses ?? []" :key="c.course_id"
                       class="col-md-6 small d-flex align-items-center gap-1">
                    <i class="fa" :class="c.certified ? 'fa-check-circle text-success' : 'fa-circle text-light border rounded-circle'"></i>
                    <span :class="c.certified ? '' : 'text-muted'">{{ c.title }}</span>
                    <span v-if="!c.certified && c.exam_passed && !c.practical_ok" class="badge bg-warning text-dark ms-1" style="font-size:9px">sin práctica</span>
                    <span v-if="!c.certified && !c.exam_passed" class="badge bg-light text-muted border ms-1" style="font-size:9px">sin examen</span>
                  </div>
                </div>
              </div>

              <!-- Escala de niveles con indicadores -->
              <h6 class="text-uppercase text-muted small mb-2">Escalafón</h6>
              <div class="d-flex gap-2 flex-wrap">
                <div v-for="lv in eligibility.levels ?? []" :key="lv.id"
                     class="p-2 rounded border text-center" style="min-width:110px;"
                     :class="lv.is_current ? 'border-primary bg-primary bg-opacity-10' : lv.qualifies ? 'border-success bg-success bg-opacity-10' : 'border-light'">
                  <div class="fw-semibold small">{{ lv.name }}</div>
                  <div class="small text-muted">rank {{ lv.rank }}</div>
                  <div class="small fw-bold" :class="lv.is_current ? 'text-primary' : lv.qualifies ? 'text-success' : 'text-muted'">
                    ${{ fmtMXN(lv.base_salary) }}
                  </div>
                  <div class="mt-1">
                    <span v-if="lv.is_current" class="badge bg-primary" style="font-size:9px">Actual</span>
                    <span v-else-if="lv.qualifies" class="badge bg-success" style="font-size:9px">✓ Califica</span>
                    <span v-else class="badge bg-light text-muted border" style="font-size:9px">{{ reqLabel(lv.required_certifications) }}</span>
                  </div>
                </div>
              </div>

              <!-- Botón promover -->
              <div v-if="eligibility.can_promote" class="mt-3 d-flex align-items-center gap-2">
                <button @click="openPromote" class="btn btn-success">
                  <i class="fa fa-arrow-up me-1"></i>
                  Promover a {{ eligibility.eligible_level?.name }}
                  (${{ fmtMXN(eligibility.eligible_level?.base_salary) }})
                </button>
                <span class="small text-muted">
                  Aplica el nuevo sueldo base al historial de compensación.
                </span>
              </div>
              <div v-else-if="eligibility.eligible_level && !eligibility.can_promote" class="mt-3">
                <div class="alert alert-info py-2 small mb-0">
                  <i class="fa fa-info-circle me-1"></i>
                  El colaborador ya está en el nivel elegible más alto ({{ eligibility.eligible_level.name }}).
                </div>
              </div>
              <div v-else class="mt-3">
                <div class="alert alert-light border small mb-0">
                  <i class="fa fa-lock me-1 text-muted"></i>
                  No califica para ningún nivel aún. Debe completar las certificaciones requeridas.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Historial de niveles -->
        <div class="col-md-4">
          <div class="card shadow-sm h-100">
            <div class="card-header py-2 bg-light">
              <strong class="small text-uppercase">Historial de niveles</strong>
            </div>
            <div class="card-body p-0">
              <div v-if="loadingHistory" class="text-center py-3"><div class="spinner-border spinner-border-sm text-muted"></div></div>
              <div v-else-if="!levelHistory?.length" class="text-muted small fst-italic p-3">Sin historial.</div>
              <ul v-else class="list-group list-group-flush">
                <li v-for="h in levelHistory" :key="h.id" class="list-group-item py-2 small">
                  <div class="d-flex justify-content-between">
                    <span class="badge" :class="rankColor(h.level?.rank ?? 1)">{{ h.level?.name ?? '?' }}</span>
                    <span class="text-muted">{{ fmtDate(h.assigned_at) }}</span>
                  </div>
                  <div class="text-muted mt-1" style="font-size:11px;">
                    {{ h.reason === 'promotion' ? 'Promoción automática' : 'Asignación manual' }}
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── TAB DEFINICIÓN DE NIVELES ── -->
    <div v-if="tab === 'definition'">
      <div class="d-flex justify-content-end mb-3">
        <button @click="openNewLevel" class="btn btn-sm btn-primary">
          <i class="fa fa-plus me-1"></i>Nuevo nivel
        </button>
      </div>

      <div v-if="loadingLevels" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr><th>Rank</th><th>Nombre</th><th>Sueldo base</th><th>Certificaciones requeridas</th><th>Activo</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="lv in levels" :key="lv.id">
              <td><span class="badge" :class="rankColor(lv.rank)">{{ lv.rank }}</span></td>
              <td class="fw-semibold">{{ lv.name }}</td>
              <td class="fw-bold text-primary">${{ fmtMXN(lv.base_salary) }}</td>
              <td class="small text-muted">{{ reqLabel(lv.required_certifications) }}</td>
              <td>
                <span class="badge" :class="lv.active ? 'bg-success' : 'bg-secondary'">
                  {{ lv.active ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td>
                <button @click="openEditLevel(lv)" class="btn btn-xs btn-outline-primary">
                  <i class="fa fa-pen"></i>
                </button>
              </td>
            </tr>
            <tr v-if="!levels?.length">
              <td colspan="6" class="text-center text-muted py-4">Sin niveles definidos.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── TAB GATING ── -->
    <div v-if="tab === 'gating'">
      <div v-if="loadingGatingData" class="text-center py-5"><div class="spinner-border text-warning"></div></div>
      <template v-else>
        <!-- Work order types -->
        <h6 class="text-uppercase text-muted small mb-2">
          <i class="fa fa-clipboard-list me-1"></i>Tipos de orden de trabajo
        </h6>
        <div class="table-responsive mb-4">
          <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
              <tr><th>Tipo</th><th>Categoría</th><th>Nivel requerido</th></tr>
            </thead>
            <tbody>
              <tr v-for="wot in workOrderTypes" :key="wot.id">
                <td class="fw-semibold small">{{ wot.name }}</td>
                <td class="small text-muted">{{ wot.category ?? '—' }}</td>
                <td>
                  <select :value="wot.required_level_id" @change="setWotLevel(wot, $event.target.value)"
                          class="form-select form-select-sm" style="width:160px">
                    <option :value="null">Sin restricción</option>
                    <option v-for="lv in levels" :key="lv.id" :value="lv.id">
                      {{ lv.name }} (rank {{ lv.rank }})
                    </option>
                  </select>
                </td>
              </tr>
              <tr v-if="!workOrderTypes?.length">
                <td colspan="3" class="text-center text-muted py-3">Sin tipos de orden.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Activity types -->
        <h6 class="text-uppercase text-muted small mb-2">
          <i class="fa fa-project-diagram me-1"></i>Tipos de actividad (proyectos planta)
        </h6>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
              <tr><th>Actividad</th><th>Unidad</th><th>Pts/ud</th><th>Nivel requerido</th></tr>
            </thead>
            <tbody>
              <tr v-for="at in activityTypes" :key="at.id">
                <td class="fw-semibold small">{{ at.name }}</td>
                <td class="small text-muted">{{ at.unit }}</td>
                <td class="small">{{ at.points_per_unit }}</td>
                <td>
                  <select :value="at.required_level_id" @change="setActLevel(at, $event.target.value)"
                          class="form-select form-select-sm" style="width:160px">
                    <option :value="null">Sin restricción</option>
                    <option v-for="lv in levels" :key="lv.id" :value="lv.id">
                      {{ lv.name }} (rank {{ lv.rank }})
                    </option>
                  </select>
                </td>
              </tr>
              <tr v-if="!activityTypes?.length">
                <td colspan="4" class="text-center text-muted py-3">Sin tipos de actividad.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <!-- ── MODAL NIVEL (crear / editar) ── -->
    <div v-if="levelModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ levelModal.id ? 'Editar nivel' : 'Nuevo nivel' }}</h5>
            <button @click="levelModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                <input v-model="levelModal.name" type="text" class="form-control" placeholder="ej. Senior">
              </div>
              <div class="col-md-4">
                <label class="form-label">Rank <span class="text-danger">*</span>
                  <small class="text-muted">(1=más bajo)</small>
                </label>
                <input v-model.number="levelModal.rank" type="number" min="1" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label">Sueldo base ($) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input v-model.number="levelModal.base_salary" type="number" min="0" step="100" class="form-control">
                </div>
                <div class="small text-muted mt-1">
                  <i class="fa fa-info-circle me-1"></i>Al promover, este sueldo se aplica al historial de compensación del colaborador.
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Mínimo de certificaciones</label>
                <input v-model.number="levelModal.req_count" type="number" min="0" class="form-control form-control-sm" placeholder="0 = sin mínimo">
              </div>
              <div class="col-md-6 d-flex align-items-end gap-2">
                <input v-model="levelModal.active" type="checkbox" class="form-check-input" id="lv-active">
                <label for="lv-active" class="form-check-label">Activo</label>
              </div>
              <!-- Cursos específicos requeridos -->
              <div class="col-12">
                <label class="form-label">Cursos específicos requeridos (opcional)</label>
                <div class="d-flex flex-wrap gap-2">
                  <div v-for="c in courses" :key="c.id" class="form-check">
                    <input :id="`rc-${c.id}`" type="checkbox" class="form-check-input"
                           :checked="levelModal.req_courses.includes(c.id)"
                           @change="toggleReqCourse(c.id)">
                    <label :for="`rc-${c.id}`" class="form-check-label small">{{ c.title }}</label>
                  </div>
                </div>
              </div>
              <div v-if="levelModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ levelModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="levelModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="saveLevel" class="btn btn-primary" :disabled="levelModal.saving">
              <span v-if="levelModal.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
              <span v-else>Guardar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL CONFIRMAR PROMOCIÓN ── -->
    <div v-if="promoteModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title"><i class="fa fa-arrow-up me-2"></i>Confirmar promoción</h5>
            <button @click="promoteModal.show=false" type="button" class="btn-close btn-close-white"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-success py-2 small mb-3">
              <strong>{{ colName }}</strong> será promovido a
              <strong>{{ eligibility?.eligible_level?.name }}</strong>.
            </div>
            <div class="card border-0 bg-light mb-3">
              <div class="card-body py-2 small">
                <div>
                  Nuevo sueldo base:
                  <strong class="text-success fs-5 ms-1">${{ fmtMXN(eligibility?.eligible_level?.base_salary) }}</strong>
                </div>
                <div class="text-muted mt-1">
                  Se creará una nueva entrada en el historial de compensación
                  con este sueldo base. Las demás condiciones (cuota semanal, etc.)
                  se heredan del historial previo.
                </div>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label small">Tipo de asignación</label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input v-model="promoteModal.reason" type="radio" value="promotion"
                         class="form-check-input" id="pr-promo">
                  <label for="pr-promo" class="form-check-label small">Promoción (automática)</label>
                </div>
                <div class="form-check">
                  <input v-model="promoteModal.reason" type="radio" value="manual"
                         class="form-check-input" id="pr-manual">
                  <label for="pr-manual" class="form-check-label small">Manual (admin)</label>
                </div>
              </div>
            </div>
            <div v-if="promoteModal.error" class="alert alert-danger py-2 small mb-0">{{ promoteModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button @click="promoteModal.show=false" class="btn btn-secondary" :disabled="promoteModal.saving">Cancelar</button>
            <button @click="confirmPromote" class="btn btn-success" :disabled="promoteModal.saving">
              <span v-if="promoteModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Promoviendo…</span>
              <span v-else><i class="fa fa-arrow-up me-1"></i>Confirmar promoción</span>
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoNiveles',
  data() {
    return {
      tab: 'colaboradores',

      // Colaboradores
      colaboradores: [],
      selectedColId: null,
      eligibility: null, loadingElig: false,
      levelHistory: [], loadingHistory: false,

      // Niveles
      levels: [], loadingLevels: true,
      courses: [],

      // Gating
      workOrderTypes: [], activityTypes: [], loadingGatingData: false,

      // Modals
      levelModal: {
        show: false, id: null, name: '', rank: 1, base_salary: 0, active: true,
        req_count: 1, req_courses: [], saving: false, error: '',
      },
      promoteModal: { show: false, reason: 'promotion', saving: false, error: '' },
    };
  },
  computed: {
    colName() {
      const col = this.colaboradores.find(c => c.id === this.selectedColId);
      return col?.user?.name ?? '—';
    },
  },
  mounted() {
    this.loadColaboradores();
    this.loadLevels();
    this.loadCourses();
  },
  methods: {
    async loadColaboradores() {
      const { data } = await axios.get('/talento/api/colaboradores', { params: { per_page: 300, status: 'active' } });
      this.colaboradores = data?.data ?? [];
    },
    async loadLevels() {
      this.loadingLevels = true;
      try {
        const { data } = await axios.get('/talento/api/levels');
        this.levels = data ?? [];
      } finally { this.loadingLevels = false; }
    },
    async loadCourses() {
      const { data } = await axios.get('/talento/api/courses', { params: { active_only: 1 } });
      this.courses = data ?? [];
    },
    async loadEligibility() {
      if (!this.selectedColId) return;
      this.loadingElig = true;
      this.eligibility = null;
      this.levelHistory = [];
      try {
        const [eligRes, histRes] = await Promise.all([
          axios.get(`/talento/api/colaboradores/${this.selectedColId}/level-eligibility`),
          axios.get(`/talento/api/colaboradores/${this.selectedColId}/level-history`),
        ]);
        this.eligibility = eligRes.data;
        this.levelHistory = histRes.data ?? [];
      } finally { this.loadingElig = false; }
    },

    // ── Gating ─────────────────────────────────────────────────────────────
    async loadGating() {
      if (this.workOrderTypes?.length) return; // ya cargado
      this.loadingGatingData = true;
      try {
        const [wotRes, atRes] = await Promise.all([
          axios.get('/talento/api/work-order-types'),
          axios.get('/talento/api/activity-types-config'),
        ]);
        this.workOrderTypes = wotRes.data ?? [];
        this.activityTypes  = atRes.data ?? [];
      } finally { this.loadingGatingData = false; }
    },
    async setWotLevel(wot, rawValue) {
      const levelId = rawValue === 'null' || rawValue === '' ? null : parseInt(rawValue);
      await axios.put(`/talento/api/work-order-types/${wot.id}/level`, { required_level_id: levelId });
      wot.required_level_id = levelId;
    },
    async setActLevel(at, rawValue) {
      const levelId = rawValue === 'null' || rawValue === '' ? null : parseInt(rawValue);
      await axios.put(`/talento/api/activity-types/${at.id}/level`, { required_level_id: levelId });
      at.required_level_id = levelId;
    },

    // ── Level CRUD ──────────────────────────────────────────────────────────
    openNewLevel() {
      this.levelModal = { show:true, id:null, name:'', rank:1, base_salary:0, active:true,
                          req_count:1, req_courses:[], saving:false, error:'' };
    },
    openEditLevel(lv) {
      const rc = lv.required_certifications ?? {};
      this.levelModal = {
        show: true, id: lv.id, name: lv.name, rank: lv.rank,
        base_salary: parseFloat(lv.base_salary), active: lv.active,
        req_count: rc.count ?? 1, req_courses: rc.courses ?? [],
        saving: false, error: '',
      };
    },
    toggleReqCourse(courseId) {
      const idx = this.levelModal.req_courses.indexOf(courseId);
      if (idx >= 0) this.levelModal.req_courses.splice(idx, 1);
      else          this.levelModal.req_courses.push(courseId);
    },
    async saveLevel() {
      this.levelModal.error = '';
      if (!this.levelModal.name)       { this.levelModal.error = 'El nombre es requerido.'; return; }
      if (!this.levelModal.rank)       { this.levelModal.error = 'El rank es requerido.'; return; }
      if (!this.levelModal.base_salary){ this.levelModal.error = 'El sueldo base es requerido.'; return; }
      this.levelModal.saving = true;

      const reqCerts = { count: this.levelModal.req_count };
      if (this.levelModal.req_courses.length) reqCerts.courses = this.levelModal.req_courses;

      try {
        const payload = {
          name: this.levelModal.name, rank: this.levelModal.rank,
          base_salary: this.levelModal.base_salary, active: this.levelModal.active,
          required_certifications: reqCerts,
        };
        if (this.levelModal.id) await axios.put(`/talento/api/levels/${this.levelModal.id}`, payload);
        else                    await axios.post('/talento/api/levels', payload);
        this.levelModal.show = false;
        this.loadLevels();
      } catch (e) {
        this.levelModal.error = e.response?.data?.message ?? 'Error al guardar.';
      } finally { this.levelModal.saving = false; }
    },

    // ── Promoción ───────────────────────────────────────────────────────────
    openPromote() {
      this.promoteModal = { show: true, reason: 'promotion', saving: false, error: '' };
    },
    async confirmPromote() {
      this.promoteModal.saving = true;
      this.promoteModal.error  = '';
      try {
        await axios.post(`/talento/api/colaboradores/${this.selectedColId}/promote`, {
          level_id: this.eligibility.eligible_level.id,
          reason:   this.promoteModal.reason,
        });
        this.promoteModal.show = false;
        await this.loadEligibility();
      } catch (e) {
        this.promoteModal.error = e.response?.data?.error ?? e.response?.data?.message ?? 'Error al promover.';
      } finally { this.promoteModal.saving = false; }
    },

    // ── Helpers ─────────────────────────────────────────────────────────────
    rankColor(rank) {
      return { 1:'bg-secondary', 2:'bg-primary', 3:'bg-info text-dark', 4:'bg-warning text-dark' }[rank] ?? 'bg-dark';
    },
    reqLabel(req) {
      if (!req) return '—';
      const parts = [];
      if (req.count)          parts.push(`${req.count} cert.`);
      if (req.courses?.length) parts.push(`${req.courses.length} curso(s) específico(s)`);
      return parts.join(' + ') || '—';
    },
    fmtMXN: n => Number(n ?? 0).toLocaleString('es-MX', { minimumFractionDigits:2, maximumFractionDigits:2 }),
    fmtDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' });
    },
  },
};
</script>

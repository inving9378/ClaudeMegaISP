<template>
  <div class="talento-calidad">

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'inspecciones' }" href="#" @click.prevent="tab='inspecciones'">
          <i class="fa fa-clipboard-check me-1"></i>Inspecciones
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" :class="{ active: tab === 'standards' }" href="#" @click.prevent="tab='standards'">
          <i class="fa fa-book me-1"></i>Catálogo de estándares
        </a>
      </li>
    </ul>

    <!-- ── TAB INSPECCIONES ── -->
    <div v-if="tab === 'inspecciones'">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="d-flex gap-2 flex-wrap">
          <input v-model="filters.caja_ref" @input="debounceLoad" type="text" class="form-control form-control-sm"
                 placeholder="Buscar caja…" style="width:160px">
          <select v-model="filters.project_id" @change="loadInspecciones" class="form-select form-select-sm" style="width:180px">
            <option value="">Todos los proyectos</option>
            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <select v-model="filters.result" @change="loadInspecciones" class="form-select form-select-sm" style="width:150px">
            <option value="">Todos los resultados</option>
            <option value="pass">Pass ✅</option>
            <option value="needs_rework">Necesita trabajo ⚠</option>
            <option value="fail">Falla ❌</option>
          </select>
        </div>
        <button @click="openNew" class="btn btn-sm btn-primary">
          <i class="fa fa-plus me-1"></i>Nueva inspección
        </button>
      </div>

      <div v-if="loadingInsp" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Caja</th><th>Proyecto</th><th>Técnico</th><th>Fecha</th>
              <th>Fusión</th><th>Potencia</th><th>Estético</th>
              <th>Resultado</th><th>Validado</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="insp in inspecciones" :key="insp.id">
              <td class="fw-semibold small">{{ insp.caja_ref }}</td>
              <td class="small text-muted">{{ insp.project?.name ?? '—' }}</td>
              <td class="small">{{ insp.colaborador?.user?.name ?? '—' }}</td>
              <td class="small">{{ fmtDate(insp.created_at) }}</td>
              <td class="small">
                <span v-if="insp.fusion_loss_measured != null"
                      :class="insp.fusion_loss_measured > 0.5 ? 'text-danger fw-bold' : insp.fusion_loss_measured > 0.3 ? 'text-warning' : 'text-success'">
                  {{ fmt2(insp.fusion_loss_measured) }} dB
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="small">
                <span v-if="insp.power_measured != null"
                      :class="insp.power_measured < -28 || insp.power_measured > -10 ? 'text-danger fw-bold' : 'text-success'">
                  {{ fmt1(insp.power_measured) }} dBm
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="small">{{ insp.aesthetic_score != null ? insp.aesthetic_score + '/10' : '—' }}</td>
              <td>
                <span v-if="insp.overall_result" class="badge" :class="resultColor(insp.overall_result)">
                  {{ resultLabel(insp.overall_result) }}
                </span>
                <span v-else class="text-muted small">—</span>
              </td>
              <td class="text-center">
                <i v-if="insp.supervisor_validated" class="fa fa-check-circle text-success"></i>
                <i v-else class="fa fa-clock text-muted"></i>
              </td>
              <td>
                <button @click="openView(insp.id)" class="btn btn-xs btn-outline-primary">
                  <i class="fa fa-eye"></i>
                </button>
              </td>
            </tr>
            <tr v-if="!inspecciones?.length">
              <td colspan="10" class="text-center text-muted py-4">Sin inspecciones.</td>
            </tr>
          </tbody>
        </table>
        <div v-if="inspPagination.last_page > 1" class="d-flex justify-content-center gap-2 mt-2">
          <button v-for="p in inspPagination.last_page" :key="p"
                  @click="loadInspecciones(p)"
                  class="btn btn-xs" :class="p === inspPagination.current_page ? 'btn-primary' : 'btn-outline-secondary'">
            {{ p }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── TAB CATÁLOGO ── -->
    <div v-if="tab === 'standards'">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <select v-model="stdFilter" @change="loadStandards" class="form-select form-select-sm" style="width:200px">
          <option value="">Todos los tipos</option>
          <option v-for="(label, val) in typeLabels" :key="val" :value="val">{{ label }}</option>
        </select>
        <button @click="openNewStd" class="btn btn-sm btn-primary">
          <i class="fa fa-plus me-1"></i>Nuevo estándar
        </button>
      </div>

      <div v-if="loadingStd" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else>
        <div v-for="(group, type) in groupedStandards" :key="type" class="mb-4">
          <h6 class="text-uppercase text-muted small border-bottom pb-1 mb-2">
            <i class="fa fa-tag me-1"></i>{{ typeLabels[type] ?? type }}
          </h6>
          <div class="row g-3">
            <div v-for="std in group" :key="std.id" class="col-md-4 col-lg-3">
              <div class="card h-100 shadow-sm">
                <div v-if="std.reference_image_path" class="card-img-top"
                     style="height:120px;overflow:hidden;background:#f8f9fa;">
                  <img :src="`/storage/${std.reference_image_path}`" class="w-100 h-100"
                       style="object-fit:cover;" :alt="std.name" @error="$event.target.style.display='none'">
                </div>
                <div v-else class="card-img-top d-flex align-items-center justify-content-center bg-light"
                     style="height:80px;">
                  <i class="fa fa-image text-muted fa-2x"></i>
                </div>
                <div class="card-body p-2">
                  <div class="fw-semibold small">{{ std.name }}</div>
                  <div v-if="std.ideal_value" class="text-muted mt-1" style="font-size:11px;">
                    <i class="fa fa-bullseye me-1 text-success"></i>{{ std.ideal_value }}
                  </div>
                  <span v-if="!std.active" class="badge bg-secondary mt-1" style="font-size:10px;">Inactivo</span>
                </div>
                <div class="card-footer p-1 d-flex gap-1">
                  <button @click="openEditStd(std)" class="btn btn-xs btn-outline-primary flex-fill">
                    <i class="fa fa-pen"></i>
                  </button>
                  <button @click="openStdImage(std)" class="btn btn-xs btn-outline-secondary flex-fill" title="Subir imagen de referencia">
                    <i class="fa fa-image"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="!standards?.length" class="text-center text-muted py-4">Sin estándares. Crea el primer estándar.</div>
      </div>
    </div>

    <!-- ── MODAL INSPECCIÓN (nueva / ver) ── -->
    <div v-if="inspModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ inspModal.id ? 'Inspección #' + inspModal.id : 'Nueva inspección' }}
            </h5>
            <button @click="inspModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <!-- Caja + Proyecto -->
              <div class="col-md-4">
                <label class="form-label">Caja ref <span class="text-danger">*</span></label>
                <input v-model="inspModal.caja_ref" :disabled="!!inspModal.id" type="text"
                       class="form-control form-control-sm" placeholder="ODB-04-A">
              </div>
              <div class="col-md-5">
                <label class="form-label">Proyecto (opcional)</label>
                <select v-model="inspModal.project_id" :disabled="!!inspModal.id" class="form-select form-select-sm">
                  <option :value="null">— Sin proyecto —</option>
                  <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </div>

              <!-- Foto -->
              <div class="col-12">
                <label class="form-label">Foto de la caja</label>
                <div v-if="inspModal.id && inspModal.photo_path" class="mb-2">
                  <img :src="`/talento/inspection-photo/${inspModal.id}`"
                       class="img-thumbnail" style="max-height:200px;"
                       @error="$event.target.style.display='none'">
                </div>
                <div v-if="!inspModal.id">
                  <input type="file" class="form-control form-control-sm" accept="image/*"
                         @change="onPhotoSelected" ref="photoInput">
                  <div class="small text-muted mt-1">
                    <i class="fa fa-shield-alt me-1 text-primary"></i>
                    La foto se almacena cifrada en servidor (sin acceso público). Aplica antifraude: GPS + in-app.
                  </div>
                </div>
              </div>

              <!-- GPS -->
              <div v-if="!inspModal.id" class="col-md-4">
                <label class="form-label">Latitud</label>
                <input v-model.number="inspModal.captured_lat" type="number" step="any" class="form-control form-control-sm">
              </div>
              <div v-if="!inspModal.id" class="col-md-4">
                <label class="form-label">Longitud</label>
                <input v-model.number="inspModal.captured_lng" type="number" step="any" class="form-control form-control-sm">
              </div>
              <div v-if="!inspModal.id" class="col-md-4 d-flex align-items-end">
                <button @click="useGps" class="btn btn-sm btn-outline-secondary w-100">
                  <i class="fa fa-map-marker-alt me-1"></i>Mi ubicación
                </button>
              </div>

              <!-- Mediciones -->
              <div class="col-12"><hr class="my-1"><h6 class="small text-uppercase text-muted">Mediciones</h6></div>
              <div class="col-md-4">
                <label class="form-label">Pérdida fusión (dB)</label>
                <div class="input-group input-group-sm">
                  <input v-model.number="inspModal.fusion_loss" :disabled="!!inspModal.id" type="number"
                         min="0" max="99" step="0.01" class="form-control"
                         :class="inspModal.fusion_loss > 0.5 ? 'border-danger' : inspModal.fusion_loss > 0.3 ? 'border-warning' : ''">
                  <span class="input-group-text">dB</span>
                </div>
                <div v-if="inspModal.fusion_loss > 0.5" class="small text-danger mt-1">
                  <i class="fa fa-exclamation-triangle me-1"></i>Supera máximo aceptable (0.5 dB)
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Potencia (dBm)</label>
                <div class="input-group input-group-sm">
                  <input v-model.number="inspModal.power" :disabled="!!inspModal.id" type="number"
                         min="-99" max="0" step="0.1" class="form-control"
                         :class="(inspModal.power < -28 || inspModal.power > -10) ? 'border-danger' : 'border-success'">
                  <span class="input-group-text">dBm</span>
                </div>
                <div v-if="inspModal.power != null" class="small mt-1"
                     :class="inspModal.power < -28 || inspModal.power > -10 ? 'text-danger' : 'text-success'">
                  Rango ideal: -10 a -28 dBm
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Score estético (1-10)</label>
                <div class="d-flex align-items-center gap-2">
                  <input v-model.number="inspModal.aesthetic_score" :disabled="!!inspModal.id" type="range"
                         min="1" max="10" step="1" class="form-range flex-fill">
                  <span class="badge" :class="inspModal.aesthetic_score >= 7 ? 'bg-success' : inspModal.aesthetic_score >= 4 ? 'bg-warning text-dark' : 'bg-danger'"
                        style="min-width:32px">{{ inspModal.aesthetic_score ?? '—' }}</span>
                </div>
              </div>

              <!-- Panel IA -->
              <div class="col-12"><hr class="my-1"><h6 class="small text-uppercase text-muted">Análisis IA <span class="badge bg-light text-muted ms-1">Asesora</span></h6></div>
              <div v-if="inspModal.ia_flags?.length" class="col-12">
                <div class="alert alert-light border py-2 mb-2">
                  <div class="fw-semibold small mb-1">{{ inspModal.ia_summary }}</div>
                  <div v-for="(flag, i) in inspModal.ia_flags" :key="i" class="d-flex align-items-start gap-2 small">
                    <i class="fa mt-1"
                       :class="flag.severity === 'ok' ? 'fa-check-circle text-success'
                             : flag.severity === 'error' ? 'fa-times-circle text-danger'
                             : 'fa-exclamation-triangle text-warning'"></i>
                    <span><strong>{{ flag.aspect }}:</strong> {{ flag.issue }}</span>
                  </div>
                </div>
              </div>
              <div v-else-if="inspModal.id" class="col-12">
                <div class="small text-muted fst-italic">Sin análisis IA registrado.</div>
              </div>
              <div v-if="inspModal.id" class="col-12">
                <button @click="runIa" class="btn btn-sm btn-outline-primary" :disabled="inspModal.runningIa">
                  <span v-if="inspModal.runningIa"><span class="spinner-border spinner-border-sm me-1"></span>Analizando…</span>
                  <span v-else><i class="fa fa-robot me-1"></i>Analizar foto con IA</span>
                </button>
              </div>

              <!-- Resultado sugerido -->
              <div v-if="inspModal.overall_result" class="col-12">
                <div class="d-flex align-items-center gap-2 mt-1">
                  <span class="small text-muted">Resultado:</span>
                  <span class="badge fs-6" :class="resultColor(inspModal.overall_result)">
                    {{ resultLabel(inspModal.overall_result) }}
                  </span>
                  <span v-if="inspModal.supervisor_validated" class="badge bg-info text-dark">
                    <i class="fa fa-user-check me-1"></i>Validado por supervisor
                  </span>
                </div>
              </div>

              <!-- Notas -->
              <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea v-model="inspModal.notes" :disabled="!!inspModal.id" class="form-control form-control-sm" rows="2"></textarea>
              </div>

              <!-- Info baseline -->
              <div v-if="!inspModal.id && inspModal.power != null" class="col-12">
                <div class="alert alert-info py-2 small mb-0">
                  <i class="fa fa-database me-1"></i>
                  Al guardar se registrará la potencia <strong>{{ fmt1(inspModal.power) }} dBm</strong> como baseline en
                  <code>talento_caja_baselines</code> para el cálculo del bono de salud de red.
                </div>
              </div>

              <!-- Validación supervisor -->
              <template v-if="inspModal.id && !inspModal.supervisor_validated">
                <div class="col-12"><hr class="my-1"><h6 class="small text-uppercase text-muted">Validación de supervisor</h6></div>
                <div class="col-md-5">
                  <label class="form-label">Override resultado</label>
                  <select v-model="inspModal.override_result" class="form-select form-select-sm">
                    <option value="pass">Pass ✅</option>
                    <option value="needs_rework">Necesita trabajo ⚠</option>
                    <option value="fail">Falla ❌</option>
                  </select>
                </div>
                <div class="col-md-5">
                  <label class="form-label">Notas supervisión</label>
                  <input v-model="inspModal.override_notes" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                  <button @click="supervisorValidate" class="btn btn-sm btn-success w-100" :disabled="inspModal.validating">
                    <span v-if="inspModal.validating"><span class="spinner-border spinner-border-sm"></span></span>
                    <span v-else><i class="fa fa-check me-1"></i>Validar</span>
                  </button>
                </div>
              </template>

              <div v-if="inspModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ inspModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="inspModal.show=false" class="btn btn-secondary">Cerrar</button>
            <button v-if="!inspModal.id" @click="saveInspection" class="btn btn-primary" :disabled="inspModal.saving">
              <span v-if="inspModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
              <span v-else>Guardar inspección</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL ESTÁNDAR (crear / editar) ── -->
    <div v-if="stdModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ stdModal.id ? 'Editar estándar' : 'Nuevo estándar' }}</h5>
            <button @click="stdModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                <input v-model="stdModal.name" type="text" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                <select v-model="stdModal.type" class="form-select">
                  <option v-for="(label, val) in typeLabels" :key="val" :value="val">{{ label }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Valor ideal</label>
                <input v-model="stdModal.ideal_value" type="text" class="form-control" placeholder="ej. ≤ 0.3 dB">
              </div>
              <div class="col-12 d-flex align-items-center gap-2">
                <input v-model="stdModal.active" type="checkbox" class="form-check-input" id="std-active">
                <label for="std-active" class="form-check-label">Activo</label>
              </div>
              <div v-if="stdModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ stdModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="stdModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="saveStandard" class="btn btn-primary" :disabled="stdModal.saving">
              <span v-if="stdModal.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
              <span v-else>Guardar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── MODAL IMAGEN DE ESTÁNDAR ── -->
    <div v-if="imgModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Imagen de referencia — {{ imgModal.stdName }}</h5>
            <button @click="imgModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div v-if="imgModal.currentPath" class="mb-3 text-center">
              <img :src="`/storage/${imgModal.currentPath}`" class="img-fluid img-thumbnail"
                   style="max-height:180px;" @error="$event.target.style.display='none'">
              <div class="small text-muted mt-1">Imagen actual</div>
            </div>
            <input type="file" class="form-control" accept="image/*" @change="onStdImageSelected" ref="stdImgInput">
            <div v-if="imgModal.error" class="alert alert-danger py-2 small mt-2 mb-0">{{ imgModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button @click="imgModal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="uploadStdImage" class="btn btn-primary" :disabled="imgModal.saving || !imgModal.file">
              <span v-if="imgModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Subiendo…</span>
              <span v-else>Subir imagen</span>
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoCalidad',
  data() {
    return {
      tab: 'inspecciones',

      // Inspecciones
      inspecciones: [],
      loadingInsp: true,
      inspPagination: { current_page: 1, last_page: 1 },
      filters: { caja_ref: '', project_id: '', result: '' },
      debounceTimer: null,
      projects: [],

      inspModal: {
        show: false, id: null,
        caja_ref: '', project_id: null,
        photoFile: null, photo_path: null,
        captured_lat: null, captured_lng: null,
        fusion_loss: null, power: null, aesthetic_score: 5,
        ia_flags: [], ia_summary: '', overall_result: null,
        supervisor_validated: false, notes: '',
        override_result: 'pass', override_notes: '',
        saving: false, runningIa: false, validating: false, error: '',
      },

      // Standards
      standards: [],
      loadingStd: true,
      stdFilter: '',
      typeLabels: {
        fusion_loss:  'Pérdida de fusión',
        power:        'Potencia óptica',
        raqueta:      'Raqueta / bandeja',
        organization: 'Organización',
        other:        'Otro',
      },

      stdModal: {
        show: false, id: null,
        name: '', type: 'other', ideal_value: '', active: true,
        saving: false, error: '',
      },
      imgModal: {
        show: false, stdId: null, stdName: '', currentPath: null,
        file: null, saving: false, error: '',
      },
    };
  },
  computed: {
    groupedStandards() {
      const groups = {};
      for (const std of (this.standards ?? [])) {
        if (!groups[std.type]) groups[std.type] = [];
        groups[std.type].push(std);
      }
      return groups;
    },
  },
  mounted() {
    this.loadInspecciones();
    this.loadStandards();
    this.loadProjects();
  },
  methods: {
    debounceLoad() {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => this.loadInspecciones(), 350);
    },
    async loadInspecciones(page = 1) {
      this.loadingInsp = true;
      try {
        const params = { page, ...this.filters };
        if (!params.caja_ref) delete params.caja_ref;
        if (!params.project_id) delete params.project_id;
        if (!params.result) delete params.result;
        const { data } = await axios.get('/talento/api/inspecciones', { params });
        this.inspecciones = data?.data ?? [];
        this.inspPagination = {
          current_page: data?.current_page ?? 1,
          last_page: data?.last_page ?? 1,
        };
      } finally { this.loadingInsp = false; }
    },
    async loadStandards() {
      this.loadingStd = true;
      try {
        const params = this.stdFilter ? { type: this.stdFilter } : {};
        const { data } = await axios.get('/talento/api/standards', { params });
        this.standards = data ?? [];
      } finally { this.loadingStd = false; }
    },
    async loadProjects() {
      const { data } = await axios.get('/talento/api/proyectos', { params: { per_page: 200 } });
      this.projects = data?.data ?? [];
    },

    // ── Inspección ──────────────────────────────────────────────────────────
    openNew() {
      this.inspModal = {
        show: true, id: null,
        caja_ref: '', project_id: null,
        photoFile: null, photo_path: null,
        captured_lat: null, captured_lng: null,
        fusion_loss: null, power: null, aesthetic_score: 5,
        ia_flags: [], ia_summary: '', overall_result: null,
        supervisor_validated: false, notes: '',
        override_result: 'pass', override_notes: '',
        saving: false, runningIa: false, validating: false, error: '',
      };
    },
    async openView(id) {
      const { data } = await axios.get(`/talento/api/inspecciones/${id}`);
      this.inspModal = {
        show: true, id: data.id,
        caja_ref: data.caja_ref, project_id: data.project_id,
        photoFile: null, photo_path: data.photo_path,
        captured_lat: data.captured_lat, captured_lng: data.captured_lng,
        fusion_loss: data.fusion_loss_measured, power: data.power_measured,
        aesthetic_score: data.aesthetic_score,
        ia_flags: data.ia_flags ?? [], ia_summary: '',
        overall_result: data.overall_result,
        supervisor_validated: data.supervisor_validated, notes: data.notes,
        override_result: data.overall_result ?? 'pass', override_notes: '',
        saving: false, runningIa: false, validating: false, error: '',
      };
    },
    onPhotoSelected(e) {
      this.inspModal.photoFile = e.target.files[0] ?? null;
    },
    useGps() {
      if (!navigator.geolocation) return alert('Geolocalización no disponible.');
      navigator.geolocation.getCurrentPosition(pos => {
        this.inspModal.captured_lat = parseFloat(pos.coords.latitude.toFixed(7));
        this.inspModal.captured_lng = parseFloat(pos.coords.longitude.toFixed(7));
      }, () => alert('No se pudo obtener ubicación.'));
    },
    async saveInspection() {
      this.inspModal.error = '';
      if (!this.inspModal.caja_ref) { this.inspModal.error = 'La caja ref es requerida.'; return; }
      this.inspModal.saving = true;
      try {
        const fd = new FormData();
        fd.append('caja_ref', this.inspModal.caja_ref);
        if (this.inspModal.project_id) fd.append('project_id', this.inspModal.project_id);
        if (this.inspModal.captured_lat != null) fd.append('captured_lat', this.inspModal.captured_lat);
        if (this.inspModal.captured_lng != null) fd.append('captured_lng', this.inspModal.captured_lng);
        fd.append('captured_in_app', '0');
        if (this.inspModal.fusion_loss != null) fd.append('fusion_loss_measured', this.inspModal.fusion_loss);
        if (this.inspModal.power != null) fd.append('power_measured', this.inspModal.power);
        if (this.inspModal.aesthetic_score != null) fd.append('aesthetic_score', this.inspModal.aesthetic_score);
        if (this.inspModal.notes) fd.append('notes', this.inspModal.notes);
        if (this.inspModal.photoFile) fd.append('photo', this.inspModal.photoFile);

        const { data } = await axios.post('/talento/api/inspecciones', fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        this.inspModal.show = false;
        this.loadInspecciones();
        // Auto-open for IA analysis
        await this.openView(data.id);
      } catch (e) {
        this.inspModal.error = e.response?.data?.message ?? 'Error al guardar.';
      } finally { this.inspModal.saving = false; }
    },
    async runIa() {
      this.inspModal.runningIa = true;
      try {
        const { data } = await axios.post(`/talento/api/inspecciones/${this.inspModal.id}/ia`);
        this.inspModal.ia_flags   = data.ia_flags ?? [];
        this.inspModal.ia_summary = data.summary ?? '';
        if (data.overall_result) this.inspModal.overall_result = data.overall_result;
        if (data.aesthetic_score) this.inspModal.aesthetic_score = data.aesthetic_score;
        this.loadInspecciones();
      } catch (e) {
        this.inspModal.error = e.response?.data?.error ?? 'Error en análisis IA.';
      } finally { this.inspModal.runningIa = false; }
    },
    async supervisorValidate() {
      this.inspModal.validating = true;
      try {
        await axios.post(`/talento/api/inspecciones/${this.inspModal.id}/validate`, {
          overall_result: this.inspModal.override_result,
          supervisor_validated: true,
          notes: this.inspModal.override_notes || null,
        });
        this.inspModal.supervisor_validated = true;
        this.inspModal.overall_result = this.inspModal.override_result;
        this.loadInspecciones();
      } catch (e) {
        this.inspModal.error = e.response?.data?.message ?? 'Error al validar.';
      } finally { this.inspModal.validating = false; }
    },

    // ── Standards ───────────────────────────────────────────────────────────
    openNewStd() {
      this.stdModal = { show: true, id: null, name: '', type: 'other', ideal_value: '', active: true, saving: false, error: '' };
    },
    openEditStd(std) {
      this.stdModal = { show: true, id: std.id, name: std.name, type: std.type,
                        ideal_value: std.ideal_value ?? '', active: std.active, saving: false, error: '' };
    },
    async saveStandard() {
      this.stdModal.error = '';
      if (!this.stdModal.name) { this.stdModal.error = 'El nombre es requerido.'; return; }
      this.stdModal.saving = true;
      try {
        const payload = { name: this.stdModal.name, type: this.stdModal.type,
                          ideal_value: this.stdModal.ideal_value || null, active: this.stdModal.active };
        if (this.stdModal.id) {
          await axios.put(`/talento/api/standards/${this.stdModal.id}`, payload);
        } else {
          await axios.post('/talento/api/standards', payload);
        }
        this.stdModal.show = false;
        this.loadStandards();
      } catch (e) {
        this.stdModal.error = e.response?.data?.message ?? 'Error al guardar.';
      } finally { this.stdModal.saving = false; }
    },
    openStdImage(std) {
      this.imgModal = { show: true, stdId: std.id, stdName: std.name,
                        currentPath: std.reference_image_path, file: null, saving: false, error: '' };
    },
    onStdImageSelected(e) {
      this.imgModal.file = e.target.files[0] ?? null;
    },
    async uploadStdImage() {
      if (!this.imgModal.file) return;
      this.imgModal.saving = true;
      try {
        const fd = new FormData();
        fd.append('image', this.imgModal.file);
        const { data } = await axios.post(`/talento/api/standards/${this.imgModal.stdId}/image`, fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        this.imgModal.currentPath = data.path;
        this.imgModal.show = false;
        this.loadStandards();
      } catch (e) {
        this.imgModal.error = e.response?.data?.message ?? 'Error al subir imagen.';
      } finally { this.imgModal.saving = false; }
    },

    // ── Helpers ─────────────────────────────────────────────────────────────
    resultColor(r) {
      return { pass: 'bg-success', needs_rework: 'bg-warning text-dark', fail: 'bg-danger' }[r] ?? 'bg-secondary';
    },
    resultLabel(r) {
      return { pass: '✅ Pass', needs_rework: '⚠ Necesita trabajo', fail: '❌ Falla' }[r] ?? r;
    },
    fmtDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    },
    fmt1: n => Number(n ?? 0).toFixed(1),
    fmt2: n => Number(n ?? 0).toFixed(2),
  },
};
</script>

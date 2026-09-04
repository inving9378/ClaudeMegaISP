<template>
  <div>
    <!-- Encabezado -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="mb-0">Paquete de documentos por puesto</h5>
        <small class="text-muted">
          Elige un puesto, marca qué plantillas del expediente le corresponden
          (ej. técnico recibe el paquete completo, un puesto de oficina solo el suyo)
          y guarda: todos los cambios se mandan en un solo request.
        </small>
      </div>
      <span v-if="saving" class="badge bg-warning text-dark ms-3">
        <i class="fas fa-spinner fa-spin me-1"></i>Guardando…
      </span>
      <span v-else-if="dirty" class="badge bg-info text-dark ms-3">
        <i class="fas fa-circle me-1"></i>Cambios sin guardar
      </span>
      <span v-else-if="lastSaved" class="badge bg-success ms-3">
        <i class="fas fa-check me-1"></i>Guardado
      </span>
    </div>

    <!-- Skeleton / error inicial -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <div class="mt-2 text-muted">Cargando catálogo…</div>
    </div>

    <div v-else-if="errorMsg" class="alert alert-danger">
      <i class="fas fa-exclamation-triangle me-2"></i>{{ errorMsg }}
      <button class="btn btn-sm btn-outline-danger ms-3" @click="load">Reintentar</button>
    </div>

    <div v-else>
      <!-- Selector de puesto -->
      <div class="row mb-3">
        <div class="col-md-5">
          <label class="form-label fw-semibold">Puesto</label>
          <select class="form-select" v-model="puestoSeleccionado">
            <option :value="null" disabled>Selecciona un puesto…</option>
            <option v-for="p in puestos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
          </select>
          <small v-if="!puestos.length" class="text-muted">
            No hay puestos capturados aún (catálogo Talento → Puestos).
          </small>
        </div>
      </div>

      <!-- Checklist de plantillas -->
      <div v-if="!puestoSeleccionado" class="alert alert-secondary">
        Selecciona un puesto para ver y editar su paquete de documentos.
      </div>

      <div v-else-if="loadingAsignaciones" class="text-center py-4">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
      </div>

      <div v-else>
        <div class="mb-2">
          <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-secondary" @click="marcarTodos">
              Marcar todos
            </button>
            <button type="button" class="btn btn-outline-secondary" @click="marcarNinguno">
              Marcar ninguno
            </button>
            <button type="button" class="btn btn-outline-secondary" @click="invertirSeleccion">
              Invertir selección
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle" style="max-width:640px">
            <thead class="table-dark">
              <tr>
                <th style="width:60px" class="text-center">Aplica</th>
                <th>Documento</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tpl in templates" :key="tpl.id">
                <td class="text-center">
                  <div class="form-check d-flex justify-content-center mb-0">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      style="width:1.2rem;height:1.2rem;cursor:pointer"
                      :checked="isAssigned(tpl.id)"
                      @change="toggleLocal(tpl.id, $event.target.checked)"
                    />
                  </div>
                </td>
                <td>{{ tpl.name }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <button
          type="button"
          class="btn btn-primary"
          :disabled="!dirty || saving"
          @click="guardar"
        >
          <i class="fas fa-spinner fa-spin me-1" v-if="saving"></i>
          <i class="fas fa-save me-1" v-else></i>
          Guardar
        </button>
      </div>
    </div>

    <!-- Toast de error inline -->
    <div
      v-if="saveError"
      class="alert alert-danger alert-dismissible mt-2"
      role="alert"
    >
      <i class="fas fa-exclamation-circle me-2"></i>{{ saveError }}
      <button type="button" class="btn-close" @click="saveError = null"></button>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'TalentoPaqueteDocumentos',

  data() {
    return {
      loading:   true,
      errorMsg:  null,
      saving:    false,
      lastSaved: false,
      saveError: null,

      puestos:   [],   // [{id, nombre}]
      templates: [],   // [{id, name, category}]

      puestoSeleccionado:   null,
      loadingAsignaciones:  false,
      savedSet:             new Set(), // último estado confirmado por el servidor
      workingSet:           new Set(), // estado local editable (lo que ve/marca el usuario)
      suprimirSiguienteCambioPuesto: false, // evita el confirm() al revertir el <select> por watcher
    };
  },

  computed: {
    dirty() {
      if (this.savedSet.size !== this.workingSet.size) return true;
      for (const id of this.workingSet) {
        if (!this.savedSet.has(id)) return true;
      }
      return false;
    },
  },

  watch: {
    puestoSeleccionado(nuevo, anterior) {
      if (this.suprimirSiguienteCambioPuesto) {
        this.suprimirSiguienteCambioPuesto = false;
        return;
      }
      if (anterior && this.dirty) {
        const confirma = window.confirm(
          'Tienes cambios sin guardar en el puesto anterior. ¿Descartarlos y cambiar de puesto?'
        );
        if (!confirma) {
          this.suprimirSiguienteCambioPuesto = true;
          this.puestoSeleccionado = anterior;
          return;
        }
      }
      this.loadAsignaciones();
    },
  },

  mounted() {
    this.load();
    window.addEventListener('beforeunload', this.onBeforeUnload);
  },

  beforeUnmount() {
    window.removeEventListener('beforeunload', this.onBeforeUnload);
  },

  methods: {
    onBeforeUnload(e) {
      if (!this.dirty) return;
      e.preventDefault();
      e.returnValue = '';
      return '';
    },

    async load() {
      this.loading  = true;
      this.errorMsg = null;
      try {
        const [puestosRes, templatesRes] = await Promise.all([
          axios.get('/talento/api/expediente/paquetes/puestos'),
          axios.get('/talento/api/expediente/paquetes/templates'),
        ]);
        this.puestos   = puestosRes.data ?? [];
        this.templates = templatesRes.data ?? [];
      } catch (e) {
        this.errorMsg = e?.response?.data?.message ?? 'No se pudo cargar el catálogo.';
      } finally {
        this.loading = false;
      }
    },

    async loadAsignaciones() {
      if (!this.puestoSeleccionado) return;
      this.loadingAsignaciones = true;
      this.saveError = null;
      try {
        const res = await axios.get('/talento/api/expediente/paquetes/asignaciones', {
          params: { puesto_id: this.puestoSeleccionado },
        });
        this.savedSet   = new Set(res.data ?? []);
        this.workingSet = new Set(this.savedSet);
        this.lastSaved  = false;
      } catch (e) {
        this.saveError = e?.response?.data?.message ?? 'No se pudo cargar el paquete de este puesto.';
      } finally {
        this.loadingAsignaciones = false;
      }
    },

    isAssigned(templateId) {
      return this.workingSet.has(templateId);
    },

    toggleLocal(templateId, checked) {
      if (checked) {
        this.workingSet.add(templateId);
      } else {
        this.workingSet.delete(templateId);
      }
      this.workingSet = new Set(this.workingSet);
      this.lastSaved  = false;
    },

    marcarTodos() {
      this.workingSet = new Set(this.templates.map((t) => t.id));
      this.lastSaved  = false;
    },

    marcarNinguno() {
      this.workingSet = new Set();
      this.lastSaved  = false;
    },

    invertirSeleccion() {
      const nuevo = new Set();
      for (const tpl of this.templates) {
        if (!this.workingSet.has(tpl.id)) nuevo.add(tpl.id);
      }
      this.workingSet = nuevo;
      this.lastSaved  = false;
    },

    async guardar() {
      if (!this.puestoSeleccionado || this.saving) return;
      this.saving    = true;
      this.saveError = null;
      this.lastSaved = false;

      try {
        const res = await axios.post('/talento/api/expediente/paquetes/sincronizar', {
          puesto_id:    this.puestoSeleccionado,
          template_ids: Array.from(this.workingSet),
        });
        this.savedSet = new Set(res.data?.template_ids ?? Array.from(this.workingSet));
        this.workingSet = new Set(this.savedSet);
        this.lastSaved = true;
        setTimeout(() => { this.lastSaved = false; }, 2500);
      } catch (e) {
        // NO se revierte workingSet: la selección marcada se conserva para reintentar.
        this.saveError = e?.response?.data?.message ?? 'Error al guardar. Tu selección se conservó, puedes reintentar.';
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

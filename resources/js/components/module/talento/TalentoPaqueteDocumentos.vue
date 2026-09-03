<template>
  <div>
    <!-- Encabezado -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="mb-0">Paquete de documentos por puesto</h5>
        <small class="text-muted">
          Elige un puesto y marca qué plantillas del expediente le corresponden
          (ej. técnico recibe el paquete completo, un puesto de oficina solo el suyo).
        </small>
      </div>
      <span v-if="saving" class="badge bg-warning text-dark ms-3">
        <i class="fas fa-spinner fa-spin me-1"></i>Guardando…
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
          <select class="form-select" v-model="puestoSeleccionado" @change="loadAsignaciones">
            <option :value="null" disabled>Selecciona un puesto…</option>
            <option v-for="p in puestos" :key="p" :value="p">{{ p }}</option>
          </select>
          <small v-if="!puestos.length" class="text-muted">
            No hay puestos capturados aún (campo "Puesto" de Colaboradores).
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

      <div v-else class="table-responsive">
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
                    :disabled="pendingId === tpl.id"
                    @change="toggle(tpl.id, $event.target.checked)"
                  />
                </div>
              </td>
              <td>{{ tpl.name }}</td>
            </tr>
          </tbody>
        </table>
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
      pendingId: null,

      puestos:   [],   // ["Técnico", "Vendedor", ...]
      templates: [],   // [{id, name, category}]

      puestoSeleccionado:   null,
      loadingAsignaciones:  false,
      assignedSet:          new Set(), // template_id asignados al puesto actual
    };
  },

  mounted() {
    this.load();
  },

  methods: {
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
          params: { puesto: this.puestoSeleccionado },
        });
        this.assignedSet = new Set(res.data ?? []);
      } catch (e) {
        this.saveError = e?.response?.data?.message ?? 'No se pudo cargar el paquete de este puesto.';
      } finally {
        this.loadingAsignaciones = false;
      }
    },

    isAssigned(templateId) {
      return this.assignedSet.has(templateId);
    },

    async toggle(templateId, checked) {
      this.pendingId = templateId;
      this.saving    = true;
      this.lastSaved = false;
      this.saveError = null;

      // Optimistic update
      if (checked) {
        this.assignedSet.add(templateId);
      } else {
        this.assignedSet.delete(templateId);
      }
      this.assignedSet = new Set(this.assignedSet);

      try {
        await axios.post('/talento/api/expediente/paquetes/toggle', {
          puesto:      this.puestoSeleccionado,
          template_id: templateId,
          asignado:    checked,
        });
        this.lastSaved = true;
        setTimeout(() => { this.lastSaved = false; }, 2500);
      } catch (e) {
        // Revertir
        if (checked) {
          this.assignedSet.delete(templateId);
        } else {
          this.assignedSet.add(templateId);
        }
        this.assignedSet = new Set(this.assignedSet);

        this.saveError = e?.response?.data?.message ?? 'Error al guardar.';
      } finally {
        this.saving    = false;
        this.pendingId = null;
      }
    },
  },
};
</script>

<template>
  <div class="pkg-docs">
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
      <!-- Selector de puesto (superficie glass) -->
      <div class="pkg-docs__glass mb-3">
        <div class="row">
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
      </div>

      <!-- Checklist de plantillas -->
      <div v-if="!puestoSeleccionado" class="alert alert-secondary">
        Selecciona un puesto para ver y editar su paquete de documentos.
      </div>

      <div v-else-if="loadingAsignaciones" class="text-center py-4">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
      </div>

      <div v-else>
        <!-- Barra de acciones (superficie glass) -->
        <div class="pkg-docs__glass pkg-docs__toolbar mb-3">
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

        <!-- Píldoras neumórficas por documento (reemplaza la tabla oscura) -->
        <div class="pkg-docs__doc-grid" role="group" aria-label="Documentos del paquete">
          <label
            v-for="tpl in templates"
            :key="tpl.id"
            class="pkg-docs__doc-pill"
            :class="{ 'is-checked': isAssigned(tpl.id) }"
          >
            <input
              class="pkg-docs__doc-checkbox"
              type="checkbox"
              :checked="isAssigned(tpl.id)"
              @change="toggleLocal(tpl.id, $event.target.checked)"
            />
            <span class="pkg-docs__doc-indicator" aria-hidden="true">
              <i class="fas fa-check"></i>
            </span>
            <span class="pkg-docs__doc-name">{{ tpl.name }}</span>
          </label>
        </div>

        <button
          type="button"
          class="btn btn-primary pkg-docs__save-btn mt-3"
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

<style scoped>
/* Raíz propia del componente: cualquier estilo de esta hoja vive bajo .pkg-docs
   o sus descendientes. `scoped` además ata cada regla al data-attribute de este
   componente, así que nada se fuga a otras pantallas (ver incidente Flotas). */
.pkg-docs {
  position: relative;
  padding: 1.25rem;
  border-radius: 22px;
  background: linear-gradient(135deg, #eef2f8 0%, #e6ebf4 50%, #eef2f8 100%);
}

/* Superficie "glass": necesita un fondo detrás para que el blur se note,
   por eso vive dentro del degradado de .pkg-docs y no del <body>. */
.pkg-docs__glass {
  padding: 1rem 1.25rem;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.55);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border: 1px solid rgba(255, 255, 255, 0.65);
  box-shadow: 0 8px 24px rgba(148, 163, 184, 0.25);
}

.pkg-docs__toolbar {
  display: flex;
  align-items: center;
}

.pkg-docs__doc-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: 0.75rem;
  max-width: 760px;
}

/* Neumorfismo: sombra doble (clara arriba-izq / oscura abajo-der) en reposo,
   e "inset" al marcar, para dar sensación física de tecla presionada. */
.pkg-docs__doc-pill {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.7rem 1rem;
  border-radius: 14px;
  background: #eef1f6;
  border: 1px solid transparent;
  cursor: pointer;
  box-shadow:
    6px 6px 12px rgba(163, 177, 198, 0.5),
    -6px -6px 12px rgba(255, 255, 255, 0.85);
  transition: box-shadow 150ms ease, transform 150ms ease,
    background-color 150ms ease, border-color 150ms ease;
}

.pkg-docs__doc-pill:hover {
  transform: translateY(-1px);
  box-shadow:
    8px 8px 16px rgba(163, 177, 198, 0.45),
    -8px -8px 16px rgba(255, 255, 255, 0.9);
}

.pkg-docs__doc-pill.is-checked {
  background: #e8f0ff;
  border-color: rgba(61, 107, 255, 0.4);
  box-shadow:
    inset 4px 4px 8px rgba(148, 163, 184, 0.45),
    inset -4px -4px 8px rgba(255, 255, 255, 0.85);
}

/* El neumorfismo tiende a comerse el :focus nativo → se restituye con anillo propio. */
.pkg-docs__doc-pill:focus-within {
  outline: 2px solid #3d6bff;
  outline-offset: 2px;
}

.pkg-docs__doc-checkbox {
  width: 1rem;
  height: 1rem;
  margin: 0;
  cursor: pointer;
  accent-color: #3d6bff;
  flex-shrink: 0;
}

/* Indicador redundante: círculo hueco vs. círculo relleno con check.
   El estado marcado/desmarcado no depende solo del color (forma + icono). */
.pkg-docs__doc-indicator {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.35rem;
  height: 1.35rem;
  border-radius: 50%;
  border: 1.5px solid rgba(100, 116, 139, 0.45);
  color: transparent;
  font-size: 0.65rem;
  flex-shrink: 0;
  transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
}

.pkg-docs__doc-pill.is-checked .pkg-docs__doc-indicator {
  background: #3d6bff;
  border-color: #3d6bff;
  color: #fff;
}

.pkg-docs__doc-name {
  color: #1f2937;
  font-size: 0.92rem;
  line-height: 1.3;
}

.pkg-docs__save-btn {
  border-radius: 12px;
  transition: transform 150ms ease, box-shadow 150ms ease;
}

.pkg-docs__save-btn:not(:disabled):hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 14px rgba(61, 107, 255, 0.35);
}
</style>

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
      <div class="pkg-docs__panel mb-3">
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
        <div class="pkg-docs__panel pkg-docs__toolbar mb-3">
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
   componente, así que nada se fuga a otras pantallas (ver incidente Flotas).

   TEMA CLARO/OSCURO — todo el color sale de los tokens canónicos de
   `resources/css/dark-light-tokens.css` (importados en `resources/sass/app.scss`),
   que conmutan solos con `data-layout-mode="light|dark"` en el <body>. NADA de
   color hardcodeado aquí: esa era justamente la causa de que la pantalla fuera
   ilegible en oscuro. */
.pkg-docs {
  position: relative;
  padding: 16px 18px;
  border-radius: 14px;
  background: var(--bg-secondary);
  color: var(--text-primary);
}

/* Antes era una superficie "glass" (blanco translúcido + blur). Se retiró: el
   blanco al 55% sobre el fondo oscuro daba un panel lechoso con texto ilegible,
   y el blur no aportaba nada funcional. Ahora es una superficie OPACA que toma
   su color del tema. */
.pkg-docs__panel {
  padding: 16px 18px;
  border-radius: 14px;
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.pkg-docs__toolbar {
  display: flex;
  align-items: center;
}

/* Sin `max-width`: la rejilla ocupa TODO el ancho disponible y deja que
   `auto-fill` decida cuántas columnas caben. Antes un max-width de 760px la
   dejaba encajonada en la mitad izquierda por ancha que fuera la pantalla. */
.pkg-docs__doc-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 0.75rem;
  width: 100%;
}

/* Antes: neumorfismo (sombra clara arriba-izq + oscura abajo-der). Ese efecto
   sólo funciona sobre un gris claro concreto — sobre fondo oscuro se veía como
   un halo sucio. Se sustituye por superficie + borde + sombra del tema, que
   funciona igual en claro y en oscuro. */
.pkg-docs__doc-pill {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.7rem 1rem;
  border-radius: 12px;
  background: var(--bg-primary);
  border: 1px solid var(--border-default);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
  cursor: pointer;
  transition: box-shadow 150ms ease, transform 150ms ease,
    background-color 150ms ease, border-color 150ms ease;
}

.pkg-docs__doc-pill:hover {
  transform: translateY(-1px);
  background: var(--bg-hover);
  border-color: var(--accent);
}

/* Marcado: el color NO es la única señal (el indicador de la derecha pasa de
   círculo hueco a círculo relleno con check), así que sigue siendo legible en
   alto contraste y para daltonismo. */
.pkg-docs__doc-pill.is-checked {
  background: var(--bg-hover);
  border-color: var(--accent);
}

/* El borde de foco nativo se pierde con fondo propio → se restituye. */
.pkg-docs__doc-pill:focus-within {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}

.pkg-docs__doc-checkbox {
  width: 1rem;
  height: 1rem;
  margin: 0;
  cursor: pointer;
  accent-color: var(--accent);
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
  border: 1.5px solid var(--border-default);
  color: transparent;
  font-size: 0.65rem;
  flex-shrink: 0;
  transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
}

.pkg-docs__doc-pill.is-checked .pkg-docs__doc-indicator {
  background: var(--accent);
  border-color: var(--accent);
  color: var(--bg-primary);
}

/* Mismo tratamiento que los encabezados de sección de la Torre (.tc-h2):
   versalitas pequeñas en color secundario. */
.pkg-docs :deep(h5) {
  font-size: 12.5px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  font-weight: 700;
  color: var(--text-secondary);
}

.pkg-docs__doc-name {
  color: var(--text-primary);
  font-size: 0.92rem;
  line-height: 1.3;
}

/* Bootstrap fija `.text-muted` a un gris pensado para fondo claro; en oscuro
   queda casi invisible. Se reapunta al token secundario SOLO dentro de este
   componente (scoped: no toca el resto del sistema). */
.pkg-docs :deep(.text-muted) {
  color: var(--text-secondary) !important;
}

/* Mismo caso con el <select> de Bootstrap: fondo blanco fijo sobre tema oscuro. */
.pkg-docs :deep(.form-select) {
  background-color: var(--bg-primary);
  color: var(--text-primary);
  border-color: var(--border-default);
}

.pkg-docs__save-btn {
  border-radius: 12px;
  transition: transform 150ms ease, box-shadow 150ms ease;
}

.pkg-docs__save-btn:not(:disabled):hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-card);
}
</style>

<template>
  <div>
    <!-- Encabezado -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="mb-0">Matriz de evidencias obligatorias</h5>
        <small class="text-muted">
          Cada celda marcada exige esa evidencia para cerrar el tipo de OT correspondiente.
          Las reglas condicionales (ej: "solo si hubo cambio de equipo") no se muestran aquí.
        </small>
      </div>
      <span v-if="saving" class="badge bg-warning text-dark ms-3">
        <i class="fas fa-spinner fa-spin me-1"></i>Guardando…
      </span>
      <span v-else-if="lastSaved" class="badge bg-success ms-3">
        <i class="fas fa-check me-1"></i>Guardado
      </span>
    </div>

    <!-- Skeleton / error -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <div class="mt-2 text-muted">Cargando catálogo…</div>
    </div>

    <div v-else-if="errorMsg" class="alert alert-danger">
      <i class="fas fa-exclamation-triangle me-2"></i>{{ errorMsg }}
      <button class="btn btn-sm btn-outline-danger ms-3" @click="load">Reintentar</button>
    </div>

    <!-- Tabla principal -->
    <div v-else class="table-responsive">
      <table class="table table-bordered table-sm align-middle" style="min-width:760px">
        <thead class="table-dark">
          <tr>
            <th style="min-width:220px">Tipo de evidencia</th>
            <th
              v-for="ot in otTypes"
              :key="ot.id"
              class="text-center"
              style="min-width:110px"
            >
              <div class="fw-semibold">{{ ot.name }}</div>
              <div class="text-warning small">{{ ot.points }} pts</div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ev in evTypes" :key="ev.id">
            <!-- Nombre + badges -->
            <td>
              <div class="fw-semibold text-dark">{{ ev.name }}</div>
              <div class="d-flex flex-wrap gap-1 mt-1">
                <span v-if="ev.es_lectura_dbm"        class="badge bg-info text-dark">dBm</span>
                <span v-if="ev.es_firma"              class="badge bg-secondary">Firma</span>
                <span v-if="ev.permite_varias"        class="badge bg-primary">Varias</span>
                <span v-if="ev.requiere_justificacion" class="badge bg-warning text-dark">Justif.</span>
              </div>
            </td>
            <!-- Checkboxes -->
            <td
              v-for="ot in otTypes"
              :key="ot.id"
              class="text-center"
            >
              <div class="form-check d-flex justify-content-center mb-0">
                <input
                  class="form-check-input"
                  type="checkbox"
                  style="width:1.2rem;height:1.2rem;cursor:pointer"
                  :checked="isRequired(ot.id, ev.id)"
                  :disabled="pendingKey === cellKey(ot.id, ev.id)"
                  @change="toggle(ot.id, ev.id, $event.target.checked)"
                />
              </div>
            </td>
          </tr>
        </tbody>
      </table>
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
  name: 'TalentoEvidenciaConfig',

  data() {
    return {
      loading:    true,
      errorMsg:   null,
      saving:     false,
      lastSaved:  false,
      saveError:  null,
      pendingKey: null,

      otTypes:      [],   // [{id, name, points}]
      evTypes:      [],   // [{id, name, permite_varias, ...}]
      // Set of "otId-evId" strings que están marcados como obligatorios
      requiredSet:  new Set(),
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
        const res = await axios.get('/talento/api/config/evidencias');
        this.otTypes = res.data?.ot_types ?? [];
        this.evTypes = res.data?.ev_types ?? [];

        const reqs = res.data?.requirements ?? [];
        this.requiredSet = new Set(
          reqs.map(r => this.cellKey(r.ot_type_id, r.evidence_type_id))
        );
      } catch (e) {
        this.errorMsg = e?.response?.data?.message ?? 'No se pudo cargar el catálogo.';
      } finally {
        this.loading = false;
      }
    },

    cellKey(otId, evId) {
      return `${otId}-${evId}`;
    },

    isRequired(otId, evId) {
      return this.requiredSet.has(this.cellKey(otId, evId));
    },

    async toggle(otId, evId, checked) {
      const key = this.cellKey(otId, evId);
      this.pendingKey = key;
      this.saving     = true;
      this.lastSaved  = false;
      this.saveError  = null;

      // Optimistic update
      if (checked) {
        this.requiredSet.add(key);
      } else {
        this.requiredSet.delete(key);
      }
      // trigger reactivity (Set no es reactivo de forma nativa en Vue 2; forzamos)
      this.requiredSet = new Set(this.requiredSet);

      try {
        await axios.post('/talento/api/config/evidencias/toggle', {
          work_order_type_id: otId,
          evidence_type_id:   evId,
          obligatoria:        checked,
        });
        this.lastSaved = true;
        setTimeout(() => { this.lastSaved = false; }, 2500);
      } catch (e) {
        // Revertir
        if (checked) {
          this.requiredSet.delete(key);
        } else {
          this.requiredSet.add(key);
        }
        this.requiredSet = new Set(this.requiredSet);

        const msg = e?.response?.data?.message ?? 'Error al guardar.';
        this.saveError = msg;
      } finally {
        this.saving     = false;
        this.pendingKey = null;
      }
    },
  },
};
</script>

<template>
  <div class="talento-puestos">
    <div class="d-flex justify-content-end mb-3">
      <button @click="openNew" class="btn btn-sm btn-primary">
        <i class="fa fa-plus me-1"></i>Nuevo puesto
      </button>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary"></div>
    </div>

    <div v-else-if="errorMsg" class="alert alert-danger">
      <i class="fa fa-exclamation-triangle me-2"></i>{{ errorMsg }}
      <button class="btn btn-sm btn-outline-danger ms-3" @click="load">Reintentar</button>
    </div>

    <div v-else class="table-responsive">
      <table class="table table-hover table-sm align-middle">
        <thead class="table-light">
          <tr><th>Nombre</th><th>Colaboradores</th><th>Activo</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="p in puestos" :key="p.id">
            <td class="fw-semibold">{{ p.nombre }}</td>
            <td class="small text-muted">{{ p.colaboradores_count }}</td>
            <td>
              <span class="badge" :class="p.activo ? 'bg-success' : 'bg-secondary'">
                {{ p.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td>
              <button @click="openEdit(p)" class="btn btn-xs btn-outline-primary">
                <i class="fa fa-pen"></i>
              </button>
            </td>
          </tr>
          <tr v-if="!puestos.length">
            <td colspan="4" class="text-center text-muted py-4">Sin puestos capturados.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── MODAL PUESTO (crear / editar) ── -->
    <div v-if="modal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ modal.id ? 'Editar puesto' : 'Nuevo puesto' }}</h5>
            <button @click="modal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                <input v-model="modal.nombre" type="text" class="form-control" placeholder="ej. Técnico instalador">
              </div>
              <div class="col-12 d-flex align-items-center gap-2">
                <input v-model="modal.activo" type="checkbox" class="form-check-input" id="puesto-activo">
                <label for="puesto-activo" class="form-check-label">Activo</label>
              </div>
              <div v-if="modal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ modal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="modal.show=false" class="btn btn-secondary">Cancelar</button>
            <button @click="save" class="btn btn-primary" :disabled="modal.saving">
              <span v-if="modal.saving"><span class="spinner-border spinner-border-sm me-1"></span></span>
              <span v-else>Guardar</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'TalentoPuestos',
  data() {
    return {
      puestos: [],
      loading: true,
      errorMsg: null,
      modal: { show: false, id: null, nombre: '', activo: true, saving: false, error: '' },
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.errorMsg = null;
      try {
        const { data } = await axios.get('/talento/api/puestos');
        this.puestos = data ?? [];
      } catch (e) {
        this.errorMsg = e?.response?.data?.message ?? 'No se pudo cargar el catálogo de puestos.';
      } finally {
        this.loading = false;
      }
    },
    openNew() {
      this.modal = { show: true, id: null, nombre: '', activo: true, saving: false, error: '' };
    },
    openEdit(p) {
      this.modal = { show: true, id: p.id, nombre: p.nombre, activo: p.activo, saving: false, error: '' };
    },
    async save() {
      this.modal.error = '';
      if (!this.modal.nombre) { this.modal.error = 'El nombre es requerido.'; return; }
      this.modal.saving = true;
      try {
        const payload = { nombre: this.modal.nombre, activo: this.modal.activo };
        if (this.modal.id) await axios.put(`/talento/api/puestos/${this.modal.id}`, payload);
        else                await axios.post('/talento/api/puestos', payload);
        this.modal.show = false;
        this.load();
      } catch (e) {
        this.modal.error = e.response?.data?.message ?? 'Error al guardar.';
      } finally {
        this.modal.saving = false;
      }
    },
  },
};
</script>

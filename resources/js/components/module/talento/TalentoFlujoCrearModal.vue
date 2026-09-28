<template>
  <div v-if="show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Nuevo flujo de campo</h5>
          <button @click="$emit('close')" type="button" class="btn-close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Colaborador <span class="text-danger">*</span></label>
              <div class="form-control-plaintext small">
                <i class="fa fa-check-circle text-success me-1"></i>{{ colaboradorNombre }}
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Tipo de flujo <span class="text-danger">*</span></label>
              <select v-model="form.type_id" class="form-select tc-select">
                <option :value="null">— Seleccionar —</option>
                <option v-for="t in tiposCampo" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Agendada</label>
              <input v-model="form.scheduled_at" type="datetime-local" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Notas</label>
              <textarea v-model="form.notes" class="form-control" rows="2"></textarea>
            </div>
            <div v-if="form.error" class="col-12">
              <div class="alert alert-danger py-2 small mb-0">{{ form.error }}</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="$emit('close')" class="btn btn-secondary" :disabled="form.saving">Cancelar</button>
          <button @click="crear" class="btn btn-primary" :disabled="form.saving">
            <span v-if="form.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
            <span v-else>Crear flujo</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
/**
 * Modal chico y propio para "Nuevo flujo" (pestaña Flujo de campo) — NO
 * reusa el modal de TalentoOrdenes.vue a propósito: ese cubre casos que
 * un flujo de campo no necesita (cliente/prospecto CRM). Comparte el MISMO
 * endpoint de creación (POST /talento/api/ordenes) y el MISMO candado del
 * backend (talento.work_orders.manage O supervisor directo — ver
 * TalentoWorkOrderController::store()), solo pre-filtra el tipo a los de
 * categoría "campo" (David, 28-sep-2026).
 */
export default {
  name: 'TalentoFlujoCrearModal',
  props: {
    show: { type: Boolean, default: false },
    colaboradorId: { type: [Number, String], required: true },
    colaboradorNombre: { type: String, default: '' },
  },
  emits: ['close', 'created'],
  data() {
    return {
      tipos: [],
      form: { type_id: null, scheduled_at: '', notes: '', saving: false, error: '' },
    };
  },
  computed: {
    tiposCampo() {
      return this.tipos.filter(t => t.active && t.category === 'campo');
    },
  },
  watch: {
    show(v) {
      if (v) {
        this.form = { type_id: null, scheduled_at: '', notes: '', saving: false, error: '' };
        this.loadTipos();
      }
    },
  },
  methods: {
    async loadTipos() {
      try {
        const { data } = await axios.get('/talento/api/order-types');
        this.tipos = data ?? [];
      } catch (e) { this.tipos = []; }
    },
    async crear() {
      this.form.error = '';
      if (!this.form.type_id) { this.form.error = 'Selecciona un tipo de flujo.'; return; }
      this.form.saving = true;
      try {
        const { data } = await axios.post('/talento/api/ordenes', {
          colaborador_id: this.colaboradorId,
          type_id: this.form.type_id,
          scheduled_at: this.form.scheduled_at || null,
          notes: this.form.notes || null,
        });
        this.$emit('created', data);
        this.$emit('close');
      } catch (e) {
        this.form.error = e.response?.data?.error ?? e.response?.data?.message ?? 'Error al crear el flujo.';
      } finally { this.form.saving = false; }
    },
  },
};
</script>

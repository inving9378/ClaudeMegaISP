<template>
  <div class="tc-card">
    <div class="tc-cardhead p-3">
      <h5 class="tc-h1 mb-0"><i class="fa fa-folder-open me-2 text-primary"></i>Paquetes de documentos</h5>
    </div>
    <div class="p-3">
      <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else-if="!documentos.length" class="text-center text-muted py-4">
        Sin documentos generados para este colaborador.
      </div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Documento</th>
              <th>Estado</th>
              <th>Generado</th>
              <th>Firma</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in documentos" :key="d.id">
              <td>{{ d.template?.name ?? '—' }}</td>
              <td><span class="tc-status" :class="d.status_efectivo === 'pendiente' ? 'is-warn' : 'is-ok'">{{ d.status_efectivo }}</span></td>
              <td>{{ formatFecha(d.generated_at) }}</td>
              <td>
                <span v-if="d.pendiente_firma" class="text-warning"><i class="fa fa-signature me-1"></i>Pendiente</span>
                <span v-else-if="d.firmado" class="text-success"><i class="fa fa-check-circle me-1"></i>Firmado</span>
                <span v-else class="text-muted">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
/**
 * Solo lectura — "lo mío" (expediente de UN colaborador), a diferencia de
 * TalentoPaqueteDocumentos.vue (catálogo/gestión de plantillas, cosa
 * distinta). Consume el endpoint self/supervisor/manage-scoped
 * (TalentoColaboradorController::miFichaDocumentos, que delega en la MISMA
 * lógica de forColaborador() sin duplicarla) — David, 28-sep-2026.
 */
export default {
  name: 'TalentoFichaDocumentos',
  props: {
    colaboradorId: { type: [Number, String], required: true },
  },
  data() {
    return { documentos: [], loading: true };
  },
  mounted() { this.load(); },
  methods: {
    async load() {
      this.loading = true;
      try {
        const { data } = await axios.get(`/talento/mi-ficha/${this.colaboradorId}/documentos`);
        this.documentos = data ?? [];
      } catch (e) { this.documentos = []; }
      finally { this.loading = false; }
    },
    formatFecha(f) {
      if (!f) return '—';
      return new Date(f).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    },
  },
};
</script>

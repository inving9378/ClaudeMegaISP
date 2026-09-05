<template>
  <div class="talento-expediente-documentos">
    <div v-if="loading" class="text-center py-4">
      <div class="spinner-border spinner-border-sm text-primary"></div>
    </div>
    <div v-else-if="!items.length" class="text-muted small text-center py-3">
      Este colaborador no tiene documentos generados (sin puesto asignado o sin plantillas para su puesto).
    </div>
    <ul v-else class="list-group">
      <li v-for="doc in items" :key="doc.id"
          class="list-group-item d-flex justify-content-between align-items-center">
        <div>
          <div class="fw-semibold">{{ doc.template?.name ?? '—' }}</div>
          <span class="badge" :class="doc.status === 'completo' ? 'bg-success' : 'bg-warning text-dark'"
                :title="doc.status === 'completo' ? 'Documento completo' : 'Faltan datos por capturar en el sistema'">
            {{ doc.status === 'completo' ? 'Completo' : 'Pendiente' }}
          </span>
        </div>
        <div>
          <a :href="documentoUrl(doc)" target="_blank" class="btn btn-sm btn-outline-primary me-1">
            <i class="fa fa-eye me-1"></i>Ver
          </a>
          <button @click="imprimir(doc)" type="button" class="btn btn-sm btn-outline-secondary">
            <i class="fa fa-print me-1"></i>Imprimir
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>

<script>
/**
 * Item roadmap #9990358 (Expediente RH — Hijo E1 de #203). Componente reusable de
 * "Documentos" del expediente, extraído del modal inline que vivía en
 * TalentoColaboradores.vue. Solo Ver/Imprimir (las 2 acciones con motor ya listo);
 * Regenerar/Subir firmado quedan para el Hijo E2. El montaje/gate por permiso lo decide
 * el componente padre que lo use (Hijo E3/E4) — este componente NO valida permisos.
 */
export default {
  name: 'TalentoExpedienteDocumentos',
  props: {
    colaboradorId: { type: Number, required: true },
  },
  data() {
    return {
      loading: true,
      items: [],
    };
  },
  watch: {
    colaboradorId: {
      immediate: true,
      handler() {
        this.load();
      },
    },
  },
  methods: {
    async load() {
      this.loading = true;
      try {
        const { data } = await axios.get(`/talento/api/colaboradores/${this.colaboradorId}/documentos`);
        this.items = data ?? [];
      } catch {
        this.items = [];
      } finally {
        this.loading = false;
      }
    },
    documentoUrl(doc) {
      return `/talento/colaboradores/${this.colaboradorId}/documentos/${doc.id}`;
    },
    imprimir(doc) {
      const ventana = window.open(this.documentoUrl(doc), '_blank');
      if (ventana) {
        ventana.onload = () => ventana.print();
      }
    },
  },
};
</script>

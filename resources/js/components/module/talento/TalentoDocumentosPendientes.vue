<template>
  <div class="talento-documentos-pendientes">

    <div class="alert alert-light small mb-3 py-2">
      <i class="fa fa-info-circle me-1 text-primary"></i>
      Vista <strong>solo lectura</strong> de documentos con firma pendiente, de todos los colaboradores. La única
      acción disponible es enviar un recordatorio por WhatsApp.
    </div>

    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>

    <div v-else-if="loadError" class="alert alert-warning">
      <i class="fa fa-exclamation-triangle me-1"></i> {{ loadError }}
      <button @click="load" class="btn btn-sm btn-outline-secondary ms-2">Reintentar</button>
    </div>

    <div v-else>
      <!-- KPIs -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-circle p-3 bg-primary-subtle" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
                <i class="fa fa-file-signature text-primary" style="font-size:18px;"></i>
              </div>
              <div>
                <div class="h4 mb-0 fw-bold">{{ kpis.total }}</div>
                <div class="small text-muted">Total pendientes</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-circle p-3 bg-warning-subtle" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
                <i class="fa fa-clock text-warning" style="font-size:18px;"></i>
              </div>
              <div>
                <div class="h4 mb-0 fw-bold">{{ kpis.mas7 }}</div>
                <div class="small text-muted">Con más de 7 días</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-circle p-3 bg-danger-subtle" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
                <i class="fa fa-exclamation-circle text-danger" style="font-size:18px;"></i>
              </div>
              <div>
                <div class="h4 mb-0 fw-bold">{{ kpis.mas30 }}</div>
                <div class="small text-muted">Con más de 30 días</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabla agrupada por colaborador -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold small d-flex justify-content-between align-items-center">
          <span>Documentos pendientes de firma</span>
          <button @click="load" class="btn btn-sm btn-outline-secondary"><i class="fa fa-sync-alt me-1"></i>Actualizar</button>
        </div>
        <div class="card-body p-0">
          <div v-if="!grupos.length" class="alert alert-light text-center m-3 mb-0">
            No hay documentos pendientes de firma.
          </div>
          <div v-else class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Colaborador</th>
                  <th>Documento</th>
                  <th class="text-end">Días pendiente</th>
                  <th>Última acción</th>
                  <th class="text-end">Acción</th>
                </tr>
              </thead>
              <template v-for="grupo in grupos" :key="grupo.colaborador_id">
                <tbody>
                  <tr class="table-light">
                    <td colspan="5" class="small fw-semibold">
                      <i class="fa fa-user me-1 text-muted"></i>{{ grupo.colaborador_nombre }}
                      <span class="badge bg-secondary ms-2">{{ grupo.docs.length }}</span>
                    </td>
                  </tr>
                  <tr v-for="doc in grupo.docs" :key="doc.documento_id">
                    <td></td>
                    <td class="small">{{ doc.documento_nombre }}</td>
                    <td class="text-end">
                      <span class="badge" :class="diasBadgeClass(doc.dias_pendiente)">{{ fmtDias(doc.dias_pendiente) }}</span>
                    </td>
                    <td class="small text-muted">{{ fmtFecha(doc.ultima_accion) }}</td>
                    <td class="text-end">
                      <button @click="openRecordar(doc, grupo)" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-paper-plane me-1"></i>Recordar
                      </button>
                    </td>
                  </tr>
                </tbody>
              </template>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal confirmación de recordatorio -->
    <div v-if="modal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Enviar recordatorio</h5>
            <button @click="closeModal" type="button" class="btn-close" :disabled="modal.sending"></button>
          </div>
          <div class="modal-body">
            <p>
              ¿Enviar recordatorio a <strong>{{ modal.colaborador_nombre }}</strong>
              por el documento <strong>{{ modal.documento_nombre }}</strong>?
            </p>
            <div v-if="modal.error" class="alert alert-danger py-2 small mb-0">{{ modal.error }}</div>
            <div v-if="modal.sent" class="alert alert-success py-2 small mb-0">Recordatorio enviado.</div>
          </div>
          <div class="modal-footer">
            <button @click="closeModal" class="btn btn-secondary" :disabled="modal.sending">
              {{ modal.sent ? 'Cerrar' : 'Cancelar' }}
            </button>
            <button v-if="!modal.sent" @click="confirmRecordar" class="btn btn-primary" :disabled="modal.sending">
              <span v-if="modal.sending"><span class="spinner-border spinner-border-sm me-1"></span>Enviando…</span>
              <span v-else>Enviar recordatorio</span>
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
const MAX_PAGINAS = 40;

export default {
  name: 'TalentoDocumentosPendientes',
  data() {
    return {
      loading: true,
      loadError: '',
      rows: [],
      modal: {
        show: false,
        doc: null,
        colaborador_nombre: '',
        documento_nombre: '',
        sending: false,
        sent: false,
        error: '',
      },
    };
  },
  computed: {
    kpis() {
      return {
        total: this.rows.length,
        mas7: this.rows.filter(r => Number(r.dias_pendiente) > 7).length,
        mas30: this.rows.filter(r => Number(r.dias_pendiente) > 30).length,
      };
    },
    grupos() {
      const porColaborador = new Map();
      for (const doc of this.rows) {
        const key = doc.colaborador_id;
        if (!porColaborador.has(key)) {
          porColaborador.set(key, { colaborador_id: key, colaborador_nombre: doc.colaborador_nombre, docs: [] });
        }
        porColaborador.get(key).docs.push(doc);
      }
      return [...porColaborador.values()].sort((a, b) => a.colaborador_nombre.localeCompare(b.colaborador_nombre));
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.loadError = '';
      const rows = [];
      try {
        let page = 1;
        let lastPage = 1;
        do {
          const r = await axios.get('/talento/api/documentos/pendientes', { params: { page } });
          rows.push(...(r.data?.data ?? []));
          lastPage = r.data?.last_page ?? 1;
          page += 1;
        } while (page <= lastPage && page <= MAX_PAGINAS);
        this.rows = rows;
      } catch (e) {
        this.loadError = e?.response?.status === 404
          ? 'El listado admin de pendientes todavía no está disponible (Fase 1 sin desplegar).'
          : 'No se pudo cargar el listado de documentos pendientes.';
      } finally {
        this.loading = false;
      }
    },
    openRecordar(doc, grupo) {
      this.modal = {
        show: true,
        doc,
        colaborador_nombre: grupo.colaborador_nombre,
        documento_nombre: doc.documento_nombre,
        sending: false,
        sent: false,
        error: '',
      };
    },
    closeModal() {
      const debeRecargar = this.modal.sent;
      this.modal.show = false;
      if (debeRecargar) this.load();
    },
    async confirmRecordar() {
      this.modal.sending = true;
      this.modal.error = '';
      try {
        const r = await axios.post(`/talento/api/documentos/${this.modal.doc.documento_id}/recordar`);
        if (r.data?.enviado) {
          this.modal.sent = true;
        } else {
          this.modal.error = r.data?.error || 'No se pudo enviar el recordatorio.';
        }
      } catch (e) {
        this.modal.error = e?.response?.data?.message || 'No se pudo enviar el recordatorio.';
      } finally {
        this.modal.sending = false;
      }
    },
    fmtDias(v) {
      return v == null ? '—' : `${v} día${Number(v) === 1 ? '' : 's'}`;
    },
    diasBadgeClass(v) {
      if (v == null) return 'bg-secondary';
      if (Number(v) > 30) return 'bg-danger';
      if (Number(v) > 7) return 'bg-warning text-dark';
      return 'bg-secondary';
    },
    fmtFecha(v) {
      if (!v) return '—';
      const d = new Date(v);
      return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString('es-MX');
    },
  },
};
</script>

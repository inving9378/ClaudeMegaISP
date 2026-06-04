<template>
  <div class="talento-dispositivos">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0"><i class="fa fa-mobile-alt me-2 text-primary"></i>Dispositivos Vinculados</h5>
    </div>
    <p class="text-muted small mb-3">
      Cada colaborador puede tener un único dispositivo activo. Al vincular uno nuevo, el anterior se revoca automáticamente.
      Las re-vinculaciones pueden requerir aprobación manual.
    </p>

    <!-- Buscador colaborador -->
    <div class="row mb-3">
      <div class="col-md-5">
        <input v-model="searchColaborador" @input="buscarColaborador" type="text"
               class="form-control" placeholder="Buscar colaborador...">
      </div>
    </div>

    <div v-if="loadingColaboradores" class="text-center py-3">
      <div class="spinner-border spinner-border-sm text-primary"></div>
    </div>

    <div v-else class="table-responsive">
      <table class="table table-hover table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>Colaborador</th>
            <th>Dispositivo</th>
            <th>Plataforma</th>
            <th>Último acceso</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="col in colaboradores" :key="col.id">
            <tr v-if="(col.devices ?? []).length === 0">
              <td>{{ col.user?.name }}</td>
              <td colspan="4" class="text-muted small fst-italic">Sin dispositivo vinculado</td>
              <td></td>
            </tr>
            <tr v-for="dev in (col.devices ?? [])" :key="dev.id">
              <td>{{ col.user?.name }}</td>
              <td>
                <span class="font-monospace small">{{ dev.device_key?.substring(0, 16) }}…</span>
                <div v-if="dev.label" class="text-muted small">{{ dev.label }}</div>
              </td>
              <td>{{ dev.platform ?? '—' }}</td>
              <td class="small">{{ formatDatetime(dev.last_seen_at) }}</td>
              <td>
                <span v-if="dev.revoked_at" class="badge bg-danger">Revocado</span>
                <span v-else-if="dev.approval_required && !dev.approved" class="badge bg-warning text-dark">Pendiente aprobación</span>
                <span v-else class="badge bg-success">Activo</span>
              </td>
              <td>
                <button v-if="dev.approval_required && !dev.approved && !dev.revoked_at"
                        @click="approveDevice(col.id, dev.id)"
                        class="btn btn-xs btn-outline-success me-1">Aprobar</button>
                <button v-if="!dev.revoked_at"
                        @click="revokeDevice(col.id, dev.id)"
                        class="btn btn-xs btn-outline-danger">Revocar</button>
              </td>
            </tr>
          </template>
          <tr v-if="!colaboradores.length">
            <td colspan="6" class="text-center text-muted py-4">No hay colaboradores que mostrar.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TalentoDispositivos',
  data() {
    return {
      colaboradores: [],
      searchColaborador: '',
      loadingColaboradores: true,
      searchTimeout: null,
    };
  },
  mounted() {
    this.loadColaboradores();
  },
  methods: {
    buscarColaborador() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.loadColaboradores(), 350);
    },
    async loadColaboradores() {
      this.loadingColaboradores = true;
      try {
        const { data } = await axios.get('/talento/api/colaboradores', {
          params: { search: this.searchColaborador, per_page: 50 }
        });
        const cols = data?.data ?? [];
        // Load devices for each
        await Promise.all(cols.map(async (col) => {
          try {
            const r = await axios.get(`/talento/api/colaboradores/${col.id}/dispositivos`);
            col.devices = r.data ?? [];
          } catch {
            col.devices = [];
          }
        }));
        this.colaboradores = cols;
      } finally {
        this.loadingColaboradores = false;
      }
    },
    async approveDevice(colId, devId) {
      await axios.post(`/talento/api/colaboradores/${colId}/dispositivos/${devId}/approve`);
      this.loadColaboradores();
    },
    async revokeDevice(colId, devId) {
      if (!confirm('¿Revocar este dispositivo?')) return;
      await axios.delete(`/talento/api/colaboradores/${colId}/dispositivos/${devId}`);
      this.loadColaboradores();
    },
    formatDatetime(d) {
      if (!d) return '—';
      return new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
  },
};
</script>

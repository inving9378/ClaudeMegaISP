<template>
  <div class="talento-dispositivos">

    <!-- ── Descarga APK ──────────────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
        <i class="fa fa-download text-success"></i>
        <strong>Descarga de la App — Talento Equipo</strong>
        <span v-if="release" class="badge bg-success ms-1">v{{ release.version_name }}</span>
      </div>
      <div class="card-body">

        <div v-if="loadingRelease" class="text-center py-3">
          <div class="spinner-border spinner-border-sm text-success"></div>
        </div>

        <div v-else-if="!release" class="text-muted small fst-italic">
          No hay ninguna versión activa registrada en <code>talento_app_releases</code>.
        </div>

        <div v-else class="row align-items-center g-3">
          <!-- QR -->
          <div class="col-auto">
            <canvas ref="qrCanvas" style="border-radius:8px;border:1px solid #e0e0e0;"></canvas>
          </div>
          <!-- Info -->
          <div class="col">
            <p class="mb-1">
              <strong>Versión:</strong> {{ release.version_name }}
              (build {{ release.version_code }})
            </p>
            <p v-if="release.changelog" class="mb-2 text-muted small">{{ release.changelog }}</p>
            <a :href="release.apk_url" target="_blank" rel="noopener"
               class="btn btn-success btn-sm me-2">
              <i class="fa fa-download me-1"></i>Descargar APK
            </a>
            <button class="btn btn-outline-secondary btn-sm" @click="copyApkUrl">
              <i class="fa fa-copy me-1"></i>Copiar enlace
            </button>
            <p class="text-muted small mt-2 mb-0">
              Escanea el QR con el teléfono del colaborador para descargar directamente.
            </p>
          </div>
        </div>

      </div>
    </div>

    <!-- ── Dispositivos vinculados ───────────────────────────────────────── -->
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
import QRCode from 'qrcode';

export default {
  name: 'TalentoDispositivos',
  data() {
    return {
      colaboradores: [],
      searchColaborador: '',
      loadingColaboradores: true,
      searchTimeout: null,
      release: null,
      loadingRelease: true,
    };
  },
  mounted() {
    this.loadColaboradores();
    this.loadRelease();
  },
  methods: {
    async loadRelease() {
      this.loadingRelease = true;
      try {
        const { data } = await axios.get('/talento/api/app/latest', { params: { version_code: 0 } });
        if (data?.update_available && data?.apk_url) {
          this.release = data;
          this.$nextTick(() => this.renderQr(data.apk_url));
        }
      } catch {
        // No hay release activo — no mostrar error
      } finally {
        this.loadingRelease = false;
      }
    },
    async renderQr(url) {
      const canvas = this.$refs.qrCanvas;
      if (!canvas) return;
      await QRCode.toCanvas(canvas, url, {
        width: 180,
        margin: 2,
        color: { dark: '#1a1a1a', light: '#ffffff' },
      });
    },
    copyApkUrl() {
      if (!this.release?.apk_url) return;
      navigator.clipboard?.writeText(this.release.apk_url)
        .then(() => toastr.success('Enlace copiado al portapapeles'))
        .catch(() => toastr.warning('No se pudo copiar automáticamente'));
    },
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

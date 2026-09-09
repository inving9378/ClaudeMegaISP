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
          class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <div class="fw-semibold">{{ doc.template?.name ?? '—' }}</div>
          <span class="badge" :class="statusBadgeClass(doc)">{{ statusBadgeText(doc) }}</span>
          <div v-if="doc.firmado" class="small text-muted mt-1">
            Firmado el {{ formatFecha(doc.signed_at) }}
            <img v-if="doc.signature_url" :src="doc.signature_url" alt="Firma" class="firma-preview-inline d-block mt-1">
          </div>
        </div>
        <div class="d-flex flex-wrap gap-1">
          <a :href="documentoUrl(doc)" target="_blank" class="btn btn-sm btn-primary">
            <i class="fa fa-eye me-1"></i>Ver
          </a>
          <button @click="imprimir(doc)" type="button" class="btn btn-sm btn-secondary">
            <i class="fa fa-print me-1"></i>Imprimir
          </button>
          <button v-if="doc.pendiente_firma" @click="abrirFirma(doc)" type="button" class="btn btn-sm btn-danger">
            <i class="fa fa-signature me-1"></i>Firmar
          </button>
          <button v-else-if="doc.requires_signature" @click="abrirFirma(doc)" type="button" class="btn btn-sm btn-secondary">
            <i class="fa fa-signature me-1"></i>Volver a firmar
          </button>
          <button v-if="(doc.huecos_count ?? 0) > 0"
                  @click="abrirCompletar(doc)" type="button" class="btn btn-sm btn-primary">
            <i class="fa fa-clipboard-check me-1"></i>Completar documento
          </button>
        </div>
      </li>
    </ul>

    <!-- Modal de captura de firma -->
    <div v-if="firmaModal.show" class="modal d-block firma-modal-backdrop" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Firmar — {{ firmaModal.doc?.template?.name ?? '' }}</h5>
            <button type="button" class="btn-close" :disabled="firmaModal.saving" @click="cerrarFirma"></button>
          </div>
          <div class="modal-body">
            <ul class="nav nav-tabs mb-3">
              <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: firmaModal.mode === 'draw' }"
                        @click="firmaModal.mode = 'draw'">
                  <i class="fa fa-pen me-1"></i>Dibujar
                </button>
              </li>
              <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: firmaModal.mode === 'upload' }"
                        @click="firmaModal.mode = 'upload'">
                  <i class="fa fa-upload me-1"></i>Subir imagen
                </button>
              </li>
            </ul>

            <div v-show="firmaModal.mode === 'draw'">
              <div class="firma-pad-wrap">
                <canvas ref="firmaCanvas" class="firma-canvas"></canvas>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-secondary" @click="deshacerPad">
                    <i class="fa fa-undo me-1"></i>Deshacer
                  </button>
                  <button type="button" class="btn btn-secondary" @click="limpiarPad">
                    <i class="fa fa-eraser me-1"></i>Limpiar
                  </button>
                </div>
                <div v-if="firmaModal.previewDataUrl" class="small text-muted text-end">
                  Vista previa:
                  <img :src="firmaModal.previewDataUrl" alt="Vista previa de firma" class="firma-preview-thumb d-block">
                </div>
              </div>
            </div>

            <div v-show="firmaModal.mode === 'upload'">
              <input type="file" accept="image/png,image/jpeg,image/webp" class="form-control"
                     @change="seleccionarArchivo">
              <div v-if="firmaModal.uploadPreviewUrl" class="mt-2 small text-muted">
                Vista previa:
                <img :src="firmaModal.uploadPreviewUrl" alt="Vista previa de firma" class="firma-preview-thumb d-block mt-1">
              </div>
            </div>

            <div v-if="firmaModal.error" class="alert alert-danger mt-3 mb-0 py-2 small">{{ firmaModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" :disabled="firmaModal.saving" @click="cerrarFirma">
              Cancelar
            </button>
            <button type="button" class="btn btn-primary" :disabled="firmaModal.saving" @click="guardarFirma">
              <span v-if="firmaModal.saving" class="spinner-border spinner-border-sm me-1"></span>
              Guardar firma
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de completar huecos gap-driven (empleado.*/empresa.*/doc.*, item #9990663) -->
    <div v-if="completarModal.show" class="modal d-block firma-modal-backdrop" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Completar documento — {{ completarModal.doc?.template?.name ?? '' }}</h5>
            <button type="button" class="btn-close" :disabled="completarModal.saving" @click="cerrarCompletar"></button>
          </div>
          <div class="modal-body">
            <div v-if="huecosPorGrupo.empleado.length" class="mb-4">
              <div class="small text-muted fw-semibold mb-2">Datos del empleado</div>
              <div class="alert alert-warning py-2 small mb-2">Afecta a todos los documentos de este colaborador.</div>
              <div v-for="hueco in huecosPorGrupo.empleado" :key="hueco.ruta" class="mb-3">
                <label class="form-label">{{ hueco.label }}</label>
                <template v-if="hueco.tipo === 'horario'">
                  <div class="d-flex gap-2">
                    <input type="time" class="form-control" placeholder="Entrada" v-model="completarModal.valores[hueco.ruta].inicio">
                    <input type="time" class="form-control" placeholder="Salida" v-model="completarModal.valores[hueco.ruta].fin">
                  </div>
                </template>
                <input v-else :type="inputType(hueco)" class="form-control" v-model="completarModal.valores[hueco.ruta]">
              </div>
            </div>

            <div v-if="huecosPorGrupo.empresa.length" class="mb-4">
              <div class="small text-muted fw-semibold mb-2">Datos de la empresa</div>
              <div class="alert alert-warning py-2 small mb-2">Afecta a todos los documentos de todos los colaboradores.</div>
              <div v-for="hueco in huecosPorGrupo.empresa" :key="hueco.ruta" class="mb-3">
                <label class="form-label">{{ hueco.label }}</label>
                <input :type="inputType(hueco)" class="form-control" v-model="completarModal.valores[hueco.ruta]">
              </div>
            </div>

            <div v-if="huecosPorGrupo.doc.length" class="mb-2">
              <div class="small text-muted fw-semibold mb-2">Solo este documento</div>
              <div v-for="hueco in huecosPorGrupo.doc" :key="hueco.ruta" class="mb-3">
                <label class="form-label">{{ hueco.label }}</label>
                <input :type="inputType(hueco)" class="form-control" v-model="completarModal.valores[hueco.ruta]">
              </div>
            </div>

            <div v-if="completarModal.error" class="alert alert-danger mt-3 mb-0 py-2 small">{{ completarModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" :disabled="completarModal.saving" @click="cerrarCompletar">
              Cancelar
            </button>
            <button type="button" class="btn btn-primary" :disabled="completarModal.saving" @click="guardarCompletar">
              <span v-if="completarModal.saving" class="spinner-border spinner-border-sm me-1"></span>
              Guardar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
/**
 * Item roadmap #9990358 (Expediente RH — Hijo E1 de #203). Componente reusable de
 * "Documentos" del expediente, extraído del modal inline que vivía en
 * TalentoColaboradores.vue. Ver/Imprimir/Firmar (motor de firma, backend fase 1 del
 * item #9990618, UI fase 2 del item #9990621). El montaje/gate por permiso lo decide
 * el componente padre que lo use (Hijo E3/E4) — este componente NO valida permisos.
 */
import SignaturePad from 'signature_pad';

// Umbral mínimo de puntos dibujados para aceptar la firma como real (opción q3 aprobada:
// no basta con "no vacío", se exige un mínimo de trazo para filtrar un toque accidental).
const MIN_PUNTOS_FIRMA = 8;

export default {
  name: 'TalentoExpedienteDocumentos',
  props: {
    colaboradorId: { type: Number, required: true },
  },
  data() {
    return {
      loading: true,
      items: [],
      firmaModal: {
        show: false,
        doc: null,
        mode: 'draw',
        saving: false,
        error: null,
        previewDataUrl: null,
        uploadFile: null,
        uploadPreviewUrl: null,
      },
      completarModal: {
        show: false,
        doc: null,
        valores: {},
        saving: false,
        error: null,
      },
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
  computed: {
    huecosPorGrupo() {
      const grupos = { empleado: [], empresa: [], doc: [] };
      const huecos = this.completarModal.doc?.huecos ?? [];
      huecos.filter((hueco) => hueco.editable).forEach((hueco) => {
        (grupos[hueco.destino] ?? grupos.doc).push(hueco);
      });
      return grupos;
    },
  },
  beforeUnmount() {
    this.destruirPad();
  },
  methods: {
    inputType(hueco) {
      if (hueco.tipo === 'fecha') return 'date';
      if (hueco.tipo === 'numero') return 'number';
      return 'text';
    },
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
    statusBadgeClass(doc) {
      if (doc.pendiente_firma) return 'bg-danger';
      return doc.status_efectivo === 'completo' ? 'bg-success' : 'bg-warning text-dark';
    },
    statusBadgeText(doc) {
      if (doc.pendiente_firma) return 'Pendiente de firma';
      return doc.status_efectivo === 'completo' ? 'Completo' : 'Pendiente';
    },
    formatFecha(iso) {
      if (!iso) return '—';
      const d = new Date(iso);
      if (Number.isNaN(d.getTime())) return iso;
      return d.toLocaleString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    },
    abrirFirma(doc) {
      this.firmaModal.show = true;
      this.firmaModal.doc = doc;
      this.firmaModal.mode = 'draw';
      this.firmaModal.saving = false;
      this.firmaModal.error = null;
      this.firmaModal.previewDataUrl = null;
      this.firmaModal.uploadFile = null;
      this.firmaModal.uploadPreviewUrl = null;
      this.$nextTick(() => this.initPad());
    },
    cerrarFirma() {
      if (this.firmaModal.saving) return;
      this.firmaModal.show = false;
      this.destruirPad();
      if (this.firmaModal.uploadPreviewUrl) {
        URL.revokeObjectURL(this.firmaModal.uploadPreviewUrl);
      }
      this.firmaModal.doc = null;
    },
    initPad() {
      const canvas = this.$refs.firmaCanvas;
      if (!canvas) return;
      this.pad = new SignaturePad(canvas, { backgroundColor: 'rgba(0,0,0,0)', penColor: 'black' });
      this.pad.addEventListener('endStroke', this.actualizarPreview);
      this._onResize = () => this.resizeCanvas();
      window.addEventListener('resize', this._onResize);
      this.resizeCanvas();
    },
    resizeCanvas() {
      const canvas = this.$refs.firmaCanvas;
      if (!canvas || !this.pad) return;
      // Preserva el trazo ya dibujado al re-escalar (rotación de pantalla / resize de ventana).
      const datos = this.pad.toData();
      const ratio = Math.max(window.devicePixelRatio || 1, 1);
      canvas.width = canvas.offsetWidth * ratio;
      canvas.height = canvas.offsetHeight * ratio;
      canvas.getContext('2d').scale(ratio, ratio);
      this.pad.clear();
      if (datos && datos.length) this.pad.fromData(datos);
    },
    destruirPad() {
      if (this._onResize) {
        window.removeEventListener('resize', this._onResize);
        this._onResize = null;
      }
      if (this.pad) {
        this.pad.removeEventListener('endStroke', this.actualizarPreview);
        this.pad.off();
        this.pad = null;
      }
    },
    limpiarPad() {
      if (this.pad) this.pad.clear();
      this.firmaModal.previewDataUrl = null;
      this.firmaModal.error = null;
    },
    deshacerPad() {
      if (!this.pad) return;
      const datos = this.pad.toData();
      if (datos && datos.length) {
        datos.pop();
        this.pad.fromData(datos);
      }
      this.actualizarPreview();
    },
    actualizarPreview() {
      if (this.pad && !this.pad.isEmpty()) {
        this.firmaModal.previewDataUrl = this.pad.toDataURL('image/png');
      } else {
        this.firmaModal.previewDataUrl = null;
      }
    },
    seleccionarArchivo(e) {
      const file = e.target.files && e.target.files[0];
      this.firmaModal.error = null;
      if (this.firmaModal.uploadPreviewUrl) {
        URL.revokeObjectURL(this.firmaModal.uploadPreviewUrl);
      }
      if (!file) {
        this.firmaModal.uploadFile = null;
        this.firmaModal.uploadPreviewUrl = null;
        return;
      }
      this.firmaModal.uploadFile = file;
      this.firmaModal.uploadPreviewUrl = URL.createObjectURL(file);
    },
    contarPuntos(datos) {
      if (!datos) return 0;
      return datos.reduce((total, trazo) => total + ((trazo.points || trazo).length || 0), 0);
    },
    async guardarFirma() {
      this.firmaModal.error = null;
      const doc = this.firmaModal.doc;
      if (!doc) return;

      let payload;

      if (this.firmaModal.mode === 'draw') {
        if (!this.pad || this.pad.isEmpty()) {
          this.firmaModal.error = 'Dibuja tu firma antes de guardar.';
          return;
        }
        if (this.contarPuntos(this.pad.toData()) < MIN_PUNTOS_FIRMA) {
          this.firmaModal.error = 'La firma parece incompleta, inténtalo de nuevo.';
          return;
        }
        payload = { signature: this.pad.toDataURL('image/png') };
      } else {
        if (!this.firmaModal.uploadFile) {
          this.firmaModal.error = 'Selecciona una imagen de firma.';
          return;
        }
        payload = new FormData();
        payload.append('signature_file', this.firmaModal.uploadFile);
      }

      this.firmaModal.saving = true;
      try {
        await axios.post(
          `/talento/api/colaboradores/${this.colaboradorId}/documentos/${doc.id}/firma`,
          payload
        );
        this.cerrarFirma();
        await this.load();
      } catch (e) {
        this.firmaModal.error = e?.response?.data?.message || 'No se pudo guardar la firma, intenta de nuevo.';
      } finally {
        this.firmaModal.saving = false;
      }
    },
    abrirCompletar(doc) {
      this.completarModal.doc = doc;
      this.completarModal.valores = Object.fromEntries(
        (doc.huecos || [])
          .filter((hueco) => hueco.editable)
          .map((hueco) => [hueco.ruta, hueco.tipo === 'horario' ? { inicio: '', fin: '' } : ''])
      );
      this.completarModal.error = null;
      this.completarModal.saving = false;
      this.completarModal.show = true;
    },
    cerrarCompletar() {
      if (this.completarModal.saving) return;
      this.completarModal.show = false;
      this.completarModal.doc = null;
      this.completarModal.valores = {};
    },
    async guardarCompletar() {
      this.completarModal.error = null;
      const doc = this.completarModal.doc;
      if (!doc) return;

      this.completarModal.saving = true;
      try {
        await axios.post(
          `/talento/api/colaboradores/${this.colaboradorId}/documentos/${doc.id}/completar`,
          { campos: this.completarModal.valores }
        );
        this.cerrarCompletar();
        // Recarga la lista completa (no solo este doc): cuando el campo tocado es
        // empleado.*/empresa.* el backend regenera TODOS los documentos del colaborador,
        // así que sus huecos también deben recalcularse aquí sin esperar un F5.
        await this.load();
      } catch (e) {
        this.completarModal.error = e?.response?.data?.message || 'No se pudo guardar, intenta de nuevo.';
      } finally {
        this.completarModal.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.firma-modal-backdrop {
  background: rgba(0, 0, 0, .5);
  z-index: 10000;
}
.firma-pad-wrap {
  width: 100%;
  max-width: 600px;
  margin: 0 auto;
}
.firma-canvas {
  width: 100%;
  height: 200px;
  display: block;
  border: 1px dashed #adb5bd;
  border-radius: 4px;
  background: #fff;
  touch-action: none;
  cursor: crosshair;
}
.firma-preview-thumb {
  max-width: 200px;
  max-height: 80px;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  background: repeating-conic-gradient(#f1f3f5 0% 25%, #fff 0% 50%) 50% / 12px 12px;
}
.firma-preview-inline {
  max-width: 150px;
  max-height: 60px;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  background: repeating-conic-gradient(#f1f3f5 0% 25%, #fff 0% 50%) 50% / 12px 12px;
}
</style>

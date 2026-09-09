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
          <div v-if="doc.firmado && !tieneSlots(doc)" class="small text-muted mt-1">
            Firmado el {{ formatFecha(doc.signed_at) }}
            <img v-if="doc.signature_url" :src="doc.signature_url" alt="Firma" class="firma-preview-inline d-block mt-1">
          </div>
        </div>
        <div class="d-flex flex-wrap gap-1">
          <a :href="documentoUrl(doc)" target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="fa fa-eye me-1"></i>Ver
          </a>
          <button @click="imprimir(doc)" type="button" class="btn btn-sm btn-outline-secondary">
            <i class="fa fa-print me-1"></i>Imprimir
          </button>
          <button v-if="doc.pendiente_firma" @click="abrirFirma(doc)" type="button" class="btn btn-sm btn-danger">
            <i class="fa fa-signature me-1"></i>Firmar
          </button>
          <button v-else-if="doc.requires_signature" @click="abrirFirma(doc)" type="button" class="btn btn-sm btn-outline-secondary">
            <i class="fa fa-signature me-1"></i>{{ tieneSlots(doc) ? 'Firmas completas' : 'Volver a firmar' }}
          </button>
          <button v-if="doc.fillable_fields && doc.fillable_fields.length"
                  @click="abrirCompletar(doc)" type="button" class="btn btn-sm btn-outline-primary">
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
            <div v-for="(recuadro, idx) in firmaModal.recuadros" :key="recuadro.key ?? '__single__'"
                 class="firma-slot-card" :class="{ 'border rounded p-2': firmaModal.recuadros.length > 1 }">
              <div v-if="firmaModal.recuadros.length > 1" class="fw-semibold small mb-2">
                {{ recuadro.label }}
              </div>

              <div v-if="recuadro.firmadoInicial && !recuadro.editing" class="small text-muted">
                <i class="fa fa-check-circle text-success me-1"></i>Ya firmado
                <img v-if="recuadro.signatureUrlInicial" :src="recuadro.signatureUrlInicial" alt="Firma actual"
                     class="firma-preview-inline d-block mt-1">
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" @click="activarEdicion(idx)">
                  <i class="fa fa-signature me-1"></i>Volver a firmar
                </button>
              </div>

              <template v-else>
                <ul class="nav nav-tabs mb-3">
                  <li class="nav-item">
                    <button type="button" class="nav-link" :class="{ active: recuadro.mode === 'draw' }"
                            @click="recuadro.mode = 'draw'">
                      <i class="fa fa-pen me-1"></i>Dibujar
                    </button>
                  </li>
                  <li class="nav-item">
                    <button type="button" class="nav-link" :class="{ active: recuadro.mode === 'upload' }"
                            @click="recuadro.mode = 'upload'">
                      <i class="fa fa-upload me-1"></i>Subir imagen
                    </button>
                  </li>
                </ul>

                <div v-show="recuadro.mode === 'draw'">
                  <div class="firma-pad-wrap">
                    <canvas :ref="(el) => setCanvasRef(idx, el)" class="firma-canvas"></canvas>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                    <div class="btn-group btn-group-sm">
                      <button type="button" class="btn btn-outline-secondary" @click="deshacerPad(idx)">
                        <i class="fa fa-undo me-1"></i>Deshacer
                      </button>
                      <button type="button" class="btn btn-outline-secondary" @click="limpiarPad(idx)">
                        <i class="fa fa-eraser me-1"></i>Limpiar
                      </button>
                    </div>
                    <div v-if="recuadro.previewDataUrl" class="small text-muted text-end">
                      Vista previa:
                      <img :src="recuadro.previewDataUrl" alt="Vista previa de firma" class="firma-preview-thumb d-block">
                    </div>
                  </div>
                </div>

                <div v-show="recuadro.mode === 'upload'">
                  <input type="file" accept="image/png,image/jpeg,image/webp" class="form-control"
                         @change="(e) => seleccionarArchivo(idx, e)">
                  <div v-if="recuadro.uploadPreviewUrl" class="mt-2 small text-muted">
                    Vista previa:
                    <img :src="recuadro.uploadPreviewUrl" alt="Vista previa de firma" class="firma-preview-thumb d-block mt-1">
                  </div>
                </div>
              </template>
            </div>

            <div v-if="firmaModal.error" class="alert alert-danger mt-3 mb-0 py-2 small">{{ firmaModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" :disabled="firmaModal.saving" @click="cerrarFirma">
              Cancelar
            </button>
            <button type="button" class="btn btn-primary" :disabled="firmaModal.saving" @click="guardarFirma">
              <span v-if="firmaModal.saving" class="spinner-border spinner-border-sm me-1"></span>
              Guardar firma{{ firmaModal.recuadros.length > 1 ? 's' : '' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de completar campos del documento (doc.*) -->
    <div v-if="completarModal.show" class="modal d-block firma-modal-backdrop" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Completar documento — {{ completarModal.doc?.template?.name ?? '' }}</h5>
            <button type="button" class="btn-close" :disabled="completarModal.saving" @click="cerrarCompletar"></button>
          </div>
          <div class="modal-body">
            <div class="small text-muted mb-2">Datos del documento</div>
            <div v-for="campo in completarModal.doc?.fillable_fields ?? []" :key="campo.key" class="mb-3">
              <label class="form-label">{{ campo.label }}</label>
              <input type="text" class="form-control" v-model="completarModal.valores[campo.key]">
            </div>
            <div v-if="completarModal.error" class="alert alert-danger mt-3 mb-0 py-2 small">{{ completarModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" :disabled="completarModal.saving" @click="cerrarCompletar">
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
        saving: false,
        error: null,
        // Item #9990649: 1 recuadro por slot declarado en la plantilla (empresa/trabajador/…),
        // o un único recuadro "legado" si el doc no declara slots (mismo flujo de siempre).
        recuadros: [],
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
  beforeUnmount() {
    this.destruirPads();
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
    tieneSlots(doc) {
      return !!(doc.signature_slots && doc.signature_slots.length);
    },
    abrirFirma(doc) {
      this.firmaModal.show = true;
      this.firmaModal.doc = doc;
      this.firmaModal.saving = false;
      this.firmaModal.error = null;
      this._canvasEls = {};
      this.pads = {};

      const esLegacy = !this.tieneSlots(doc);
      // Doc sin slots declarados (legado, 1 sola firma): recuadro sintético que abre SIEMPRE
      // en modo edición — mismo comportamiento de "un solo recuadro" que tenía el modal antes
      // de este item (item #9990649: "no romper el flujo de firma actual").
      const slots = esLegacy
        ? [{ key: null, label: null, requerido: true, firmado: doc.firmado, signature_url: doc.signature_url }]
        : doc.signature_slots;

      this.firmaModal.recuadros = slots.map((s) => ({
        key: s.key,
        label: s.label,
        requerido: s.requerido,
        firmadoInicial: s.firmado,
        signatureUrlInicial: s.signature_url,
        editing: esLegacy ? true : !s.firmado,
        mode: 'draw',
        previewDataUrl: null,
        uploadFile: null,
        uploadPreviewUrl: null,
      }));

      this._onResize = () => {
        Object.keys(this.pads).forEach((idx) => this.resizeCanvas(idx));
      };
      window.addEventListener('resize', this._onResize);

      this.$nextTick(() => this.initPads());
    },
    cerrarFirma() {
      if (this.firmaModal.saving) return;
      this.firmaModal.show = false;
      this.destruirPads();
      (this.firmaModal.recuadros || []).forEach((r) => {
        if (r.uploadPreviewUrl) URL.revokeObjectURL(r.uploadPreviewUrl);
      });
      this.firmaModal.doc = null;
      this.firmaModal.recuadros = [];
    },
    setCanvasRef(idx, el) {
      if (el) this._canvasEls[idx] = el;
      else delete this._canvasEls[idx];
    },
    initPads() {
      this.firmaModal.recuadros.forEach((r, idx) => {
        if (r.editing) this.initPad(idx);
      });
    },
    initPad(idx) {
      const canvas = this._canvasEls[idx];
      if (!canvas) return;
      const pad = new SignaturePad(canvas, { backgroundColor: 'rgba(0,0,0,0)', penColor: 'black' });
      pad.addEventListener('endStroke', () => this.actualizarPreview(idx));
      this.pads[idx] = pad;
      this.resizeCanvas(idx);
    },
    resizeCanvas(idx) {
      const canvas = this._canvasEls[idx];
      const pad = this.pads[idx];
      if (!canvas || !pad) return;
      // Preserva el trazo ya dibujado al re-escalar (rotación de pantalla / resize de ventana).
      const datos = pad.toData();
      const ratio = Math.max(window.devicePixelRatio || 1, 1);
      canvas.width = canvas.offsetWidth * ratio;
      canvas.height = canvas.offsetHeight * ratio;
      canvas.getContext('2d').scale(ratio, ratio);
      pad.clear();
      if (datos && datos.length) pad.fromData(datos);
    },
    destruirPads() {
      if (this._onResize) {
        window.removeEventListener('resize', this._onResize);
        this._onResize = null;
      }
      Object.values(this.pads || {}).forEach((pad) => pad.off());
      this.pads = {};
      this._canvasEls = {};
    },
    activarEdicion(idx) {
      const r = this.firmaModal.recuadros[idx];
      r.editing = true;
      r.mode = 'draw';
      this.$nextTick(() => this.initPad(idx));
    },
    limpiarPad(idx) {
      const pad = this.pads[idx];
      if (pad) pad.clear();
      this.firmaModal.recuadros[idx].previewDataUrl = null;
      this.firmaModal.error = null;
    },
    deshacerPad(idx) {
      const pad = this.pads[idx];
      if (!pad) return;
      const datos = pad.toData();
      if (datos && datos.length) {
        datos.pop();
        pad.fromData(datos);
      }
      this.actualizarPreview(idx);
    },
    actualizarPreview(idx) {
      const pad = this.pads[idx];
      const r = this.firmaModal.recuadros[idx];
      if (pad && !pad.isEmpty()) {
        r.previewDataUrl = pad.toDataURL('image/png');
      } else {
        r.previewDataUrl = null;
      }
    },
    seleccionarArchivo(idx, e) {
      const file = e.target.files && e.target.files[0];
      const r = this.firmaModal.recuadros[idx];
      this.firmaModal.error = null;
      if (r.uploadPreviewUrl) URL.revokeObjectURL(r.uploadPreviewUrl);
      if (!file) {
        r.uploadFile = null;
        r.uploadPreviewUrl = null;
        return;
      }
      r.uploadFile = file;
      r.uploadPreviewUrl = URL.createObjectURL(file);
    },
    contarPuntos(datos) {
      if (!datos) return 0;
      return datos.reduce((total, trazo) => total + ((trazo.points || trazo).length || 0), 0);
    },
    tieneFirmaNueva(idx) {
      const r = this.firmaModal.recuadros[idx];
      if (!r.editing) return false;
      if (r.mode === 'draw') {
        const pad = this.pads[idx];
        return !!pad && !pad.isEmpty();
      }
      return !!r.uploadFile;
    },
    async guardarFirma() {
      this.firmaModal.error = null;
      const doc = this.firmaModal.doc;
      if (!doc) return;

      const pendientes = [];
      for (let idx = 0; idx < this.firmaModal.recuadros.length; idx += 1) {
        if (this.tieneFirmaNueva(idx)) pendientes.push(idx);
      }

      if (!pendientes.length) {
        this.firmaModal.error = 'No hay ninguna firma nueva para guardar.';
        return;
      }

      this.firmaModal.saving = true;
      let huboError = false;

      for (const idx of pendientes) {
        const r = this.firmaModal.recuadros[idx];
        try {
          await this.enviarFirmaSlot(doc, r, idx);
          r.firmadoInicial = true;
          r.editing = false;
          r.previewDataUrl = null;
          r.uploadFile = null;
          if (r.uploadPreviewUrl) {
            URL.revokeObjectURL(r.uploadPreviewUrl);
            r.uploadPreviewUrl = null;
          }
          const pad = this.pads[idx];
          if (pad) pad.off();
          delete this.pads[idx];
        } catch (e) {
          huboError = true;
          const etiqueta = r.label ? ` (${r.label})` : '';
          this.firmaModal.error = (e?.response?.data?.message || 'No se pudo guardar la firma, intenta de nuevo.') + etiqueta;
        }
      }

      this.firmaModal.saving = false;

      if (!huboError) {
        this.cerrarFirma();
        await this.load();
      }
    },
    async enviarFirmaSlot(doc, r, idx) {
      const url = `/talento/api/colaboradores/${this.colaboradorId}/documentos/${doc.id}/firma`;
      let payload;

      if (r.mode === 'draw') {
        const pad = this.pads[idx];
        if (this.contarPuntos(pad.toData()) < MIN_PUNTOS_FIRMA) {
          const error = new Error('firma_incompleta');
          error.response = { data: { message: 'La firma parece incompleta, inténtalo de nuevo.' } };
          throw error;
        }
        payload = { signature: pad.toDataURL('image/png') };
        if (r.key) payload.slot_key = r.key;
      } else {
        payload = new FormData();
        payload.append('signature_file', r.uploadFile);
        if (r.key) payload.append('slot_key', r.key);
      }

      return axios.post(url, payload);
    },
    abrirCompletar(doc) {
      this.completarModal.doc = doc;
      this.completarModal.valores = Object.fromEntries(
        (doc.fillable_fields || []).map((campo) => [campo.key, doc.datos_extra?.[campo.key] ?? ''])
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
          { valores: this.completarModal.valores }
        );
        this.cerrarCompletar();
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
.firma-slot-card + .firma-slot-card {
  margin-top: .75rem;
}
</style>

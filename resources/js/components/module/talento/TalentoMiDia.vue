<template>
  <!-- "Mi día" dentro de la ficha (2026-10-23): lo que antes estaba en el Portal — órdenes de hoy y
       su ejecución (evidencia con cámara y firmas). Arriba, la entrada y la salida del día (la misma
       tarjeta que en Asistencia).
       Usa los mismos endpoints /talento/portal/* (self-scoped por Actor), sin lógica nueva. -->
  <div class="talento-mi-dia">
    <div v-if="aviso.texto" class="alert py-2 small d-flex align-items-center justify-content-between"
         :class="'alert-' + aviso.tipo">
      <span>{{ aviso.texto }}</span>
      <button type="button" class="btn-close btn-sm" @click="aviso.texto = ''"></button>
    </div>

    <div v-if="reaperturas.length && !otSel" class="alert alert-warning small">
      <i class="fa fa-exclamation-triangle me-1"></i>
      Tienes {{ reaperturas.length }} documento{{ reaperturas.length > 1 ? 's' : '' }}
      reabierto{{ reaperturas.length > 1 ? 's' : '' }} pendiente{{ reaperturas.length > 1 ? 's' : '' }} de volver a firmar:
      {{ reaperturas.map(r => r.template_nombre).join(', ') }}. Contacta a RH.
    </div>

    <!-- Detalle de una orden -->
    <template v-if="otSel">
      <button class="tc-btn tc-btn-seg btn-sm mb-3" @click="cerrarDetalle">
        <i class="fa fa-arrow-left me-1"></i> Volver a mis órdenes de hoy
      </button>

      <div v-if="cargandoDetalle" class="text-center py-5"><div class="spinner-border text-primary"></div></div>

      <template v-else-if="detalle">
        <div v-if="otSelAtrasada" class="alert alert-danger small d-flex align-items-center justify-content-between gap-2 flex-wrap">
          <span>
            <i class="fa fa-exclamation-circle me-1"></i>
            <strong>Orden atrasada:</strong> era para el {{ fechaCorta(otSelAtrasada.fecha) }}. Termínala o reprográmala diciendo por qué.
          </span>
          <button type="button" class="tc-btn tc-btn-bad btn-xs" @click="abrirReprogramar(otSelAtrasada)">
            <i class="fa fa-calendar-alt me-1"></i>Reprogramar
          </button>
        </div>
        <div class="tc-card">
          <div class="p-3">
            <div class="tc-muted-txt text-uppercase">{{ detalle.ot.tipo }}</div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="tc-h1">Orden #{{ detalle.ot.folio }}</span>
              <span class="tc-status" :class="estado(detalle.ot.status).clase">{{ estado(detalle.ot.status).label }}</span>
            </div>
            <div class="row g-2 mt-2 small">
              <div v-if="detalle.ot.cliente" class="col-md-6"><i class="fa fa-user me-1 text-muted"></i>{{ detalle.ot.cliente }}<span v-if="detalle.ot.es_prospecto" class="tc-muted-txt"> · prospecto</span></div>
              <div v-if="detalle.ot.telefono" class="col-md-6"><i class="fa fa-phone me-1 text-muted"></i>{{ detalle.ot.telefono }}</div>
              <div v-if="detalle.ot.direccion" class="col-12"><i class="fa fa-map-marker-alt me-1 text-muted"></i>{{ detalle.ot.direccion }}</div>
              <div v-if="detalle.ot.notas" class="col-12"><i class="fa fa-sticky-note me-1 text-muted"></i>{{ detalle.ot.notas }}</div>
            </div>
          </div>
        </div>

        <!-- Acción principal según el estado -->
        <div class="tc-card">
          <div class="p-3">
            <div v-if="detalle.handoff" class="small">
              <i class="fa fa-hourglass-half me-1 text-primary"></i>Orden aceptada — <strong>en espera de activación</strong>.
            </div>
            <button v-else-if="detalle.ot.status === 'pending'" class="tc-btn tc-btn-ok" :disabled="accionOt" @click="iniciarOt">
              <span v-if="accionOt" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fa fa-play me-1"></i>Iniciar orden
            </button>
            <template v-else-if="detalle.ot.status === 'in_progress'">
              <button class="tc-btn tc-btn-ok" :disabled="accionOt" @click="completarOt">
                <span v-if="accionOt" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fa fa-check-circle me-1"></i>Completar orden
              </button>
              <div class="tc-muted-txt mt-2">Antes de cerrar se revisa la evidencia obligatoria y la lectura dBm.</div>
            </template>
            <template v-else-if="detalle.ot.status === 'completed'">
              <button class="tc-btn tc-btn-ok" :disabled="accionOt || !ambasFirmas" @click="aceptarOt">
                <span v-if="accionOt" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fa fa-check-double me-1"></i>Aceptar orden
              </button>
              <div class="tc-muted-txt mt-2">
                <span v-if="!ambasFirmas">Necesita la firma del técnico y la del cliente.</span>
                <span v-else>Al aceptarla pasa a espera de activación.</span>
              </div>
            </template>
            <div v-else class="small text-muted">
              <i class="fa fa-info-circle me-1"></i>Orden en estado «{{ estado(detalle.ot.status).label }}».
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="tc-card h-100">
              <div class="tc-cardhead p-3 fw-semibold"><i class="fa fa-tasks me-2"></i>Evidencia requerida</div>
              <ul class="list-group list-group-flush">
                <li v-for="c in detalle.checklist" :key="c.evidence_type_id"
                    class="list-group-item bg-transparent d-flex align-items-center justify-content-between small">
                  <span>
                    <i class="fa me-2" :class="c.subido ? 'fa-check-circle text-success' : 'fa-circle text-muted'"></i>{{ c.nombre }}
                    <span v-if="c.es_lectura_dbm" class="tc-muted-txt ms-1">(lectura dBm)</span>
                    <span v-if="c.es_firma" class="tc-muted-txt ms-1">(firma)</span>
                  </span>
                  <span v-if="c.subido" class="tc-status is-ok">Listo</span>
                </li>
                <li v-if="!detalle.checklist.length" class="list-group-item bg-transparent small text-muted">Esta orden no pide evidencia.</li>
              </ul>
              <div v-if="detalle.ot.status === 'in_progress'" class="p-3 border-top">
                <button class="tc-btn tc-btn-primary btn-sm" @click="abrirCaptura">
                  <i class="fa fa-camera me-1"></i>Capturar evidencia
                </button>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="tc-card h-100">
              <div class="tc-cardhead p-3 fw-semibold d-flex justify-content-between">
                <span><i class="fa fa-images me-2"></i>Evidencia capturada</span>
                <span v-if="detalle.evidencias.length" class="tc-status is-info">{{ detalle.evidencias.length }}</span>
              </div>
              <ul class="list-group list-group-flush">
                <li v-for="ev in detalle.evidencias" :key="ev.id" class="list-group-item bg-transparent small">
                  <i class="fa fa-image me-2 text-primary"></i>{{ ev.tipo_nombre || ('Tipo #' + ev.type_id) }}
                  <span class="tc-muted-txt ms-1">{{ hora(ev.created_at) }}</span>
                  <span v-if="ev.potencia_dbm !== null && ev.potencia_dbm !== undefined" class="tc-muted-txt">
                    · {{ Math.round(ev.potencia_dbm * 10) / 10 }} dBm
                  </span>
                </li>
                <li v-if="!detalle.evidencias.length" class="list-group-item bg-transparent small text-muted">Aún no hay evidencia capturada.</li>
              </ul>
            </div>
          </div>
        </div>

        <div v-if="['in_progress', 'completed'].includes(detalle.ot.status)" class="tc-card">
          <div class="tc-cardhead p-3 fw-semibold"><i class="fa fa-signature me-2"></i>Firmas</div>
          <ul class="list-group list-group-flush">
            <li v-for="s in firmantes" :key="s.tipo" class="list-group-item bg-transparent d-flex align-items-center justify-content-between small">
              <span><i class="fa me-2" :class="detalle.firmas[s.tipo] ? 'fa-check-circle text-success' : 'fa-circle text-muted'"></i>{{ s.label }}</span>
              <button class="tc-btn tc-btn-info btn-xs" @click="abrirFirma(s.tipo)">
                {{ detalle.firmas[s.tipo] ? 'Volver a firmar' : 'Firmar' }}
              </button>
            </li>
          </ul>
        </div>
      </template>

      <div v-else class="alert alert-danger small">No se pudo cargar el detalle de la orden.</div>
    </template>

    <!-- Asistencia y órdenes de hoy -->
    <template v-else>
      <!-- La entrada y la salida, a la mano al abrir la ficha (2026-10-28, video de Irving: «registra tu
           entrada… y tener esto a la mano»). Es la misma tarjeta de la pestaña Asistencia. -->
      <talento-marcar-asistencia />

      <!-- Item #1158: lo que no se hizo en su día no se queda atrás. Sigue aquí, en rojo, hasta que se
           termine o se reprograme con un motivo. -->
      <div v-if="atrasadas.length" class="alert alert-danger small d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <span>
          <i class="fa fa-exclamation-circle me-1"></i>
          <strong>{{ atrasadas.length === 1 ? "Tienes 1 orden atrasada." : `Tienes ${atrasadas.length} órdenes atrasadas.` }}</strong>
          Termínala hoy o reprográmala diciendo por qué; mientras tanto sigue aquí.
        </span>
      </div>

      <div class="tc-card">
        <div class="tc-cardhead p-3 fw-semibold d-flex justify-content-between">
          <span><i class="fa fa-clipboard-list me-2"></i>Órdenes de hoy<template v-if="atrasadas.length"> y atrasadas</template></span>
          <span v-if="!cargandoOts && ots.length" class="tc-status is-info">{{ ots.length }}</span>
        </div>
        <div v-if="cargandoOts" class="p-3 small text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Cargando órdenes…</div>
        <div v-else-if="!ots.length" class="p-4 text-center text-muted small">
          <i class="fa fa-calendar-check fa-2x d-block mb-2"></i>No tienes órdenes de trabajo para hoy.
        </div>
        <div v-else class="list-group list-group-flush">
          <a v-for="ot in ots" :key="ot.origen + '-' + ot.id" href="#" @click.prevent="abrirDetalle(ot)"
             class="list-group-item list-group-item-action bg-transparent d-flex align-items-center justify-content-between"
             :class="{ 'mi-dia-atrasada': ot.atrasada }">
            <span class="small">
              <span v-if="ot.atrasada" class="mi-dia-etq-atrasada">
                <i class="fa fa-exclamation-circle me-1"></i>Atrasada desde el {{ fechaCorta(ot.fecha) }}
                <template v-if="ot.dias_atraso"> · {{ ot.dias_atraso === 1 ? "1 día" : `${ot.dias_atraso} días` }}</template>
              </span>
              <strong>#{{ ot.folio }} · {{ ot.tipo }}</strong>
              <span v-if="ot.cliente" class="d-block text-muted">{{ ot.cliente }}<span v-if="ot.es_prospecto"> · prospecto</span></span>
              <span v-if="ot.direccion" class="d-block tc-muted-txt"><i class="fa fa-map-marker-alt me-1"></i>{{ ot.direccion }}</span>
            </span>
            <span class="d-flex align-items-center gap-2">
              <button v-if="ot.atrasada && ot.origen === 'task'" type="button" class="tc-btn tc-btn-bad btn-xs"
                      @click.prevent.stop="abrirReprogramar(ot)">
                <i class="fa fa-calendar-alt me-1"></i>Reprogramar
              </button>
              <span class="tc-status" :class="estado(ot.status).clase">{{ estado(ot.status).label }}</span>
              <i class="fa fa-chevron-right text-muted"></i>
            </span>
          </a>
        </div>
      </div>
    </template>

    <!-- Item #1158: al abrir «Mi día» con órdenes atrasadas salta este aviso; no se quita hasta que
         cada una se termine o se reprograme («Ahora no» solo lo cierra por esta vez). -->
    <div v-if="avisoAtrasadas && atrasadas.length && !otSel && !reprogramar" class="modal d-block" tabindex="-1"
         style="background:rgba(0,0,0,.55);z-index:10001">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title text-danger"><i class="fa fa-exclamation-circle me-2"></i>Tienes órdenes atrasadas</h5>
            <button type="button" class="btn-close" @click="avisoAtrasadas = false"></button>
          </div>
          <div class="modal-body">
            <p class="small mb-2">Estas órdenes ya pasaron de su día y siguen sin terminarse. ¿Qué pasó con ellas?</p>
            <div class="list-group">
              <div v-for="ot in atrasadas" :key="'aviso-' + ot.id"
                   class="list-group-item bg-transparent d-flex align-items-center justify-content-between gap-2 mi-dia-atrasada">
                <span class="small">
                  <strong>#{{ ot.folio }} · {{ ot.tipo }}</strong>
                  <span v-if="ot.cliente" class="d-block text-muted">{{ ot.cliente }}</span>
                  <span class="d-block text-danger">Era para el {{ fechaCorta(ot.fecha) }}</span>
                </span>
                <span class="d-flex flex-column gap-1">
                  <button type="button" class="tc-btn tc-btn-bad btn-xs" @click="abrirReprogramar(ot)">Reprogramar</button>
                  <button type="button" class="tc-btn tc-btn-seg btn-xs" @click="avisoAtrasadas = false; abrirDetalle(ot)">Abrir</button>
                </span>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="tc-btn tc-btn-seg btn-sm" @click="avisoAtrasadas = false">Ahora no</button>
          </div>
        </div>
      </div>
    </div>

    <ReprogramarOrden v-if="reprogramar" :orden="reprogramar" :url="`/talento/portal/ot/task/${reprogramar.id}/reprogramar`"
                      @cerrar="reprogramar = null" @reprogramada="trasReprogramar" />

    <!-- Captura de evidencia: cámara en vivo, sin galería (anti-fraude; el servidor sella la marca) -->
    <div v-if="captura.abierta" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.55);z-index:10001">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa fa-camera me-2"></i>Capturar evidencia</h5>
            <button type="button" class="btn-close" :disabled="captura.enviando" @click="cerrarCaptura"></button>
          </div>
          <div class="modal-body">
            <div v-if="captura.error" class="alert alert-warning small py-2">{{ captura.error }}</div>
            <label class="form-label small text-muted">Tipo de evidencia</label>
            <select v-model="captura.tipo" class="form-select form-select-sm tc-select mb-3">
              <option v-for="t in tiposCapturables" :key="t.evidence_type_id" :value="t.evidence_type_id">
                {{ t.nombre }}{{ t.subido ? ' ✓' : '' }}
              </option>
            </select>
            <div v-show="!captura.tomada && captura.streamActivo" class="text-center">
              <video ref="video" playsinline muted class="mi-dia-media"></video>
              <button class="tc-btn tc-btn-ok mt-2" @click="capturarFrame"><i class="fa fa-camera me-1"></i>Tomar foto</button>
              <div class="tc-muted-txt mt-1">Solo cámara en vivo (no galería).</div>
            </div>
            <canvas ref="canvas" v-show="captura.tomada" class="mi-dia-media"></canvas>
            <template v-if="captura.tomada">
              <div v-if="tipoSel && tipoSel.es_lectura_dbm" class="mt-2">
                <label class="form-label small text-muted">Lectura dBm</label>
                <input v-model.number="captura.dbm" type="number" step="0.1" class="form-control form-control-sm" placeholder="Entre -60 y 10">
              </div>
              <div v-if="tipoSel && tipoSel.requiere_justificacion" class="mt-2">
                <label class="form-label small text-muted">Justificación</label>
                <textarea v-model="captura.justificacion" rows="2" class="form-control form-control-sm"></textarea>
              </div>
            </template>
          </div>
          <div v-if="captura.tomada" class="modal-footer">
            <button class="tc-btn tc-btn-seg btn-sm" :disabled="captura.enviando" @click="reintentarFoto"><i class="fa fa-redo me-1"></i>Repetir</button>
            <button class="tc-btn tc-btn-ok btn-sm" :disabled="captura.enviando" @click="enviarEvidencia">
              <span v-if="captura.enviando" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fa fa-cloud-upload-alt me-1"></i>Enviar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Firma del técnico o del cliente -->
    <div v-if="firma.abierta" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.55);z-index:10001">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa fa-signature me-2"></i>Firma del {{ firma.tipo === 'technician' ? 'técnico' : 'cliente' }}</h5>
            <button type="button" class="btn-close" :disabled="firma.guardando" @click="firma.abierta = false"></button>
          </div>
          <div class="modal-body">
            <FirmaCanvas ref="firmaCanvas" :disabled="firma.guardando" />
            <div class="tc-muted-txt mt-1">Firma con el dedo o el mouse dentro del recuadro.</div>
            <div v-if="firma.error" class="small text-danger mt-1">{{ firma.error }}</div>
          </div>
          <div class="modal-footer">
            <button class="tc-btn tc-btn-seg btn-sm" :disabled="firma.guardando" @click="$refs.firmaCanvas?.clear()">Limpiar</button>
            <button class="tc-btn tc-btn-ok btn-sm" :disabled="firma.guardando" @click="guardarFirma">
              <span v-if="firma.guardando" class="spinner-border spinner-border-sm me-1"></span>Guardar firma
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ReprogramarOrden from "./ReprogramarOrden.vue";
import FirmaCanvas from "../../firma/FirmaCanvas.vue";
import TalentoMarcarAsistencia from "./TalentoMarcarAsistencia.vue";

const ESTADOS = {
  pending:            { label: "Pendiente",   clase: "is-info" },
  in_progress:        { label: "En progreso", clase: "is-warn" },
  completed:          { label: "Completada",  clase: "is-ok" },
  incidencia:         { label: "Incidencia",  clase: "is-bad" },
  pending_activation: { label: "Por activar", clase: "is-info" },
  active:             { label: "Activa",      clase: "is-ok" },
  survey_pending:     { label: "Encuesta",    clase: "is-info" },
  validated:          { label: "Validada",    clase: "is-ok" },
};

function ubicacion() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) { reject({ code: "unsupported" }); return; }
    navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
  });
}

function uuid() {
  if (window.crypto?.randomUUID) return crypto.randomUUID();
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === "x" ? r : (r & 0x3) | 0x8).toString(16);
  });
}

export default {
  name: "TalentoMiDia",
  components: { FirmaCanvas, TalentoMarcarAsistencia, ReprogramarOrden },
  data() {
    return {
      aviso: { tipo: "info", texto: "" },
      ots: [],
      avisoAtrasadas: true,
      reprogramar: null,
      cargandoOts: true,
      reaperturas: [],
      otSel: null,
      detalle: null,
      cargandoDetalle: false,
      accionOt: false,
      firmantes: [
        { tipo: "technician", label: "Firma del técnico" },
        { tipo: "client", label: "Firma del cliente" },
      ],
      captura: { abierta: false, tipo: null, tomada: false, streamActivo: false, error: "", dbm: null, justificacion: "", enviando: false },
      firma: { abierta: false, tipo: "client", guardando: false, error: "" },
      stream: null,
    };
  },
  computed: {
    atrasadas() { return this.ots.filter((o) => o.atrasada); },
    // La orden abierta, si está atrasada (también cuando se abrió desde la campanita, sin la marca).
    otSelAtrasada() {
      if (!this.otSel) return null;
      if (this.otSel.atrasada) return this.otSel;
      return this.atrasadas.find((o) => o.origen === this.otSel.origen && o.id === this.otSel.id) ?? null;
    },
    ambasFirmas() { return !!(this.detalle?.firmas?.technician && this.detalle?.firmas?.client); },
    // La firma va por su propio recuadro; aquí solo los tipos que llevan foto.
    tiposCapturables() { return (this.detalle?.checklist ?? []).filter((c) => !c.es_firma); },
    tipoSel() { return this.tiposCapturables.find((t) => t.evidence_type_id === this.captura.tipo) ?? null; },
  },
  mounted() {
    this.cargarOts();
    this.cargarReaperturas();
    // La campanita de «Nueva tarea asignada» llega con ?ot=<id> (2026-10-27): abre esa orden, sea o
    // no de hoy.
    const ot = Number(new URLSearchParams(window.location.search).get("ot"));
    if (ot) this.abrirDetalle({ origen: "task", id: ot });
  },
  beforeUnmount() {
    this.detenerCamara();
  },
  methods: {
    avisar(tipo, texto) {
      this.aviso = { tipo, texto };
    },
    error(e, porDefecto) {
      const d = e?.response?.data;
      return d?.message || d?.error || porDefecto;
    },
    estado(s) { return ESTADOS[s] ?? { label: s || "—", clase: "is-info" }; },
    fechaCorta(f) {
      if (!f) return "";
      const [y, m, d] = String(f).slice(0, 10).split("-").map(Number);
      return new Date(y, m - 1, d).toLocaleDateString("es-MX", { weekday: "long", day: "numeric", month: "long" });
    },
    abrirReprogramar(ot) {
      this.reprogramar = { id: ot.id, tipo: ot.tipo, cliente: ot.cliente, fecha: ot.fecha, atrasada: !!ot.atrasada };
    },
    trasReprogramar(r) {
      this.reprogramar = null;
      this.avisar("success", `Orden movida al ${this.fechaCorta(r?.fecha)}. El motivo quedó en su historial.`);
      if (this.otSel) this.cerrarDetalle();
      this.cargarOts();
    },
    hora(dt) {
      if (!dt) return "";
      const d = new Date(String(dt).replace(" ", "T"));
      return isNaN(d.getTime()) ? "" : d.toLocaleTimeString("es-MX", { hour: "2-digit", minute: "2-digit" });
    },
    async cargarOts() {
      this.cargandoOts = true;
      try {
        const { data } = await axios.get("/talento/portal/ots/hoy");
        this.ots = Array.isArray(data?.data) ? data.data : [];
      } catch (e) {
        this.ots = [];
      } finally {
        this.cargandoOts = false;
      }
    },
    async cargarReaperturas() {
      try {
        const { data } = await axios.get("/talento/portal/reaperturas");
        this.reaperturas = Array.isArray(data?.items) ? data.items : [];
      } catch (e) {
        this.reaperturas = [];
      }
    },
    abrirDetalle(ot) {
      this.otSel = ot;
      this.cargarDetalle();
    },
    cerrarDetalle() {
      this.otSel = null;
      this.detalle = null;
      // Que recargar la página no vuelva a abrir la orden que trajo la campanita.
      const url = new URL(window.location.href);
      if (url.searchParams.has("ot")) {
        url.searchParams.delete("ot");
        window.history.replaceState(window.history.state, "", url);
      }
    },
    async cargarDetalle() {
      if (!this.otSel) return;
      this.cargandoDetalle = true;
      try {
        const { data } = await axios.get(`/talento/portal/ot/${this.otSel.origen}/${this.otSel.id}`);
        this.detalle = data;
      } catch (e) {
        this.detalle = null;
        this.avisar("danger", this.error(e, "No se pudo cargar la orden."));
      } finally {
        this.cargandoDetalle = false;
      }
    },
    otUrl(sufijo) {
      return `/talento/portal/ot/${this.detalle.ot.origen}/${this.detalle.ot.id}${sufijo}`;
    },
    async accion(sufijo, exito, porDefecto) {
      if (!this.detalle || this.accionOt) return;
      this.accionOt = true;
      try {
        const { data } = await axios.post(this.otUrl(sufijo), {});
        this.avisar("success", data?.message || exito);
        await this.cargarDetalle();
        this.cargarOts();
      } catch (e) {
        let msg = this.error(e, porDefecto);
        const faltan = e?.response?.data?.faltantes;
        if (Array.isArray(faltan) && faltan.length) msg += " Faltan: " + faltan.map((f) => f.name).join(", ") + ".";
        this.avisar("warning", msg);
      } finally {
        this.accionOt = false;
      }
    },
    iniciarOt() { this.accion("/iniciar", "Orden iniciada.", "No se pudo iniciar la orden."); },
    completarOt() { this.accion("/completar", "Orden completada.", "No se pudo completar la orden."); },
    aceptarOt() { this.accion("/aceptar", "Orden aceptada.", "No se pudo aceptar la orden."); },

    async abrirCaptura() {
      const pendiente = this.tiposCapturables.find((t) => !t.subido);
      this.captura = { abierta: true, tipo: (pendiente ?? this.tiposCapturables[0])?.evidence_type_id ?? null,
        tomada: false, streamActivo: false, error: "", dbm: null, justificacion: "", enviando: false };
      // La cámara del navegador solo funciona en una página segura (https).
      const segura = window.isSecureContext || ["localhost", "127.0.0.1"].includes(location.hostname);
      if (!segura || !navigator.mediaDevices?.getUserMedia) {
        this.captura.error = "La cámara solo funciona entrando al sistema por https.";
        return;
      }
      try {
        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
        this.captura.streamActivo = true;
        await this.$nextTick();
        const v = this.$refs.video;
        if (v) { v.srcObject = this.stream; v.play().catch(() => {}); }
      } catch (e) {
        this.captura.error = e?.name === "NotAllowedError"
          ? "El navegador no tiene permiso de usar la cámara. Actívalo para capturar evidencia."
          : "No se pudo abrir la cámara.";
      }
    },
    detenerCamara() {
      if (this.stream) { this.stream.getTracks().forEach((t) => t.stop()); this.stream = null; }
      this.captura.streamActivo = false;
    },
    capturarFrame() {
      const v = this.$refs.video;
      const c = this.$refs.canvas;
      if (!v || !c) return;
      c.width = v.videoWidth || 720;
      c.height = v.videoHeight || 960;
      const ctx = c.getContext("2d");
      ctx.drawImage(v, 0, 0, c.width, c.height);
      // Marca de vista previa; la marca real la sella el servidor.
      ctx.fillStyle = "rgba(0,0,0,0.5)";
      ctx.fillRect(0, c.height - 46, c.width, 46);
      ctx.fillStyle = "#fff";
      ctx.font = "16px sans-serif";
      ctx.fillText("Talento Meganet · " + new Date().toLocaleString("es-MX"), 10, c.height - 18);
      this.captura.tomada = true;
      this.detenerCamara();
    },
    reintentarFoto() {
      this.detenerCamara();
      this.abrirCaptura();
    },
    cerrarCaptura() {
      this.detenerCamara();
      this.captura.abierta = false;
    },
    async enviarEvidencia() {
      const t = this.tipoSel;
      if (this.captura.enviando || !this.captura.tomada || !t) return;
      if (t.requiere_justificacion && !this.captura.justificacion.trim()) {
        this.captura.error = "Este tipo de evidencia necesita justificación.";
        return;
      }
      if (t.es_lectura_dbm && (this.captura.dbm === null || this.captura.dbm === "")) {
        this.captura.error = "Captura la lectura dBm.";
        return;
      }
      this.captura.enviando = true;
      let pos;
      try {
        pos = await ubicacion();
      } catch (e) {
        this.captura.enviando = false;
        this.captura.error = "Necesitamos tu ubicación para subir la evidencia.";
        return;
      }
      const blob = await new Promise((r) => this.$refs.canvas.toBlob(r, "image/jpeg", 0.9));
      const fd = new FormData();
      fd.append("foto", blob, "evidencia.jpg");
      fd.append("evidence_type_id", String(t.evidence_type_id));
      fd.append("lat", pos.coords.latitude);
      fd.append("lng", pos.coords.longitude);
      if (pos.coords.accuracy != null) fd.append("accuracy", pos.coords.accuracy);
      if (t.es_lectura_dbm) fd.append("potencia_dbm", this.captura.dbm);
      if (this.captura.justificacion.trim()) fd.append("justificacion", this.captura.justificacion.trim());
      fd.append("client_uuid", uuid());
      try {
        await axios.post(this.otUrl("/evidencia"), fd);
        this.cerrarCaptura();
        this.avisar("success", "Evidencia registrada.");
        await this.cargarDetalle();
      } catch (e) {
        this.captura.error = this.error(e, "No se pudo subir la evidencia.");
      } finally {
        this.captura.enviando = false;
      }
    },

    async abrirFirma(tipo) {
      this.firma = { abierta: true, tipo, guardando: false, error: "" };
      await this.$nextTick();
      this.$refs.firmaCanvas?.resize();
    },
    async guardarFirma() {
      const pad = this.$refs.firmaCanvas;
      if (!pad || pad.isEmpty()) {
        this.firma.error = "Dibuja la firma antes de guardar.";
        return;
      }
      this.firma.guardando = true;
      const body = { signer_type: this.firma.tipo, signature_data: pad.toPNG() };
      try {
        const pos = await ubicacion();
        body.lat = pos.coords.latitude;
        body.lng = pos.coords.longitude;
      } catch (e) { /* la firma se guarda aunque no haya ubicación */ }
      try {
        await axios.post(this.otUrl("/firma"), body);
        this.firma.abierta = false;
        this.avisar("success", "Firma guardada.");
        await this.cargarDetalle();
      } catch (e) {
        this.firma.error = this.error(e, "No se pudo guardar la firma.");
      } finally {
        this.firma.guardando = false;
      }
    },
  },
};
</script>

<style scoped>
.mi-dia-atrasada {
  border-left: 4px solid #d50000 !important;
  background: rgba(213, 0, 0, 0.05) !important;
}
.mi-dia-etq-atrasada {
  display: block;
  color: #d50000;
  font-weight: 600;
  font-size: 12px;
  margin-bottom: 2px;
}
.mi-dia-media {
  width: 100%;
  max-height: 60vh;
  border-radius: 8px;
  background: #000;
}
</style>

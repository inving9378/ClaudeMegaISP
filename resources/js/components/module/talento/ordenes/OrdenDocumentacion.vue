<template>
  <div v-if="visible" class="mt-3 orden-documentacion">
    <h6 class="fw-semibold mb-2">Documentación</h6>
    <div v-if="cargando" class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando la documentación…</div>
    <div v-else-if="error" class="text-danger small">{{ error }}</div>
    <template v-else-if="doc">
      <!-- Evidencias obligatorias -->
      <div v-if="obligatorias.length" class="od-bloque">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="small fw-semibold">Evidencias obligatorias</span>
          <span class="tc-status" :class="faltantes.length ? 'is-bad' : 'is-ok'">{{ cumplidas }} de {{ obligatorias.length }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <span v-for="r in obligatorias" :key="r.id" class="od-chip" :class="r.cumplida ? 'od-ok' : 'od-falta'"
                :title="r.cumplida ? r.fotos + (r.fotos === 1 ? ' foto' : ' fotos') : 'Falta'">
            <i class="fa me-1" :class="r.cumplida ? 'fa-check' : 'fa-times'"></i>{{ r.nombre }}<template v-if="r.fotos > 1"> ({{ r.fotos }})</template>
          </span>
        </div>
        <div v-if="condicionales.length" class="small text-muted mt-2">
          Solo en ciertos casos:
          <span v-for="(r, k) in condicionales" :key="r.id">{{ k ? ', ' : '' }}{{ r.nombre }} ({{ textoCondicion(r.condicion) }}{{ r.cumplida ? ', sí está' : '' }})</span>
        </div>
      </div>
      <div v-else class="od-bloque small text-muted">Este tipo de orden no exige evidencias.</div>

      <!-- Fotos -->
      <div class="od-bloque">
        <div class="small fw-semibold mb-2">Fotos de evidencia <span v-if="fotos.length" class="text-muted fw-normal">· {{ fotos.length }}</span></div>
        <div v-if="!doc.puede_ver_fotos" class="small text-muted">No tienes permiso para ver las fotos.</div>
        <div v-else-if="!fotos.length" class="small text-muted">Esta orden no tiene fotos de evidencia.</div>
        <!-- #1286: las fotos de cuando se hizo la orden y, aparte, las de cada corrección que pidió el supervisor. -->
        <template v-else>
          <div v-for="g in gruposFotos" :key="g.clave" class="od-grupo">
            <div v-if="g.correccion" class="small mb-2">
              <span class="fw-semibold">Corrección {{ g.numero }}</span>
              <span class="tc-status ms-1" :class="g.estado[1]">{{ g.estado[0] }}</span>
              <div>{{ g.correccion.motivo }}</div>
              <div class="text-muted">
                Pedida {{ hora(g.correccion.pedida_at) }}<template v-if="g.correccion.pedida_por"> por {{ g.correccion.pedida_por }}</template>
                <template v-if="g.correccion.enviada_at"> · enviada {{ hora(g.correccion.enviada_at) }}</template>
              </div>
              <div v-if="g.correccion.nota_tecnico"><strong>Nota del técnico:</strong> {{ g.correccion.nota_tecnico }}</div>
            </div>
            <div v-else-if="gruposFotos.length > 1" class="small fw-semibold mb-2">De cuando se hizo la orden</div>
            <div v-if="!g.fotos.length" class="small text-muted">Sin fotos todavía.</div>
            <div v-else class="oe-grid">
              <button v-for="f in g.fotos" :key="f.id" type="button" class="oe-mini"
                      :aria-label="'Ver foto: ' + etiqueta(f)" :disabled="!f.url" @click="abrir(fotos.indexOf(f))">
                <img v-if="f.url && !rotas[f.id]" :src="f.url" :alt="etiqueta(f)" loading="lazy" @error="rotas[f.id] = true">
                <span v-else class="oe-sin-foto"><i v-if="f.restringida" class="fa fa-lock me-1"></i>{{ f.restringida ? 'Restringida' : 'Sin imagen' }}</span>
                <span class="oe-pie">
                  {{ etiqueta(f) }}
                  <span class="d-block text-muted">{{ hora(f.capturada) }}<template v-if="f.potencia_dbm !== null && f.potencia_dbm !== undefined"> · {{ f.potencia_dbm }} dBm</template></span>
                  <span v-if="f.justificacion" class="d-block text-muted fst-italic">{{ f.justificacion }}</span>
                  <span v-if="f.fuera_de_lugar" class="tc-status is-warn mt-1">Fuera del lugar{{ f.distancia_m ? ' (' + Math.round(f.distancia_m) + ' m)' : '' }}</span>
                </span>
              </button>
            </div>
          </div>
        </template>
      </div>

      <!-- Lecturas de dBm -->
      <div v-if="doc.lecturas_dbm && doc.lecturas_dbm.length" class="od-bloque">
        <div class="small fw-semibold mb-1">Lecturas de potencia</div>
        <ul class="small mb-0 ps-3">
          <li v-for="l in doc.lecturas_dbm" :key="l.foto_id">
            <strong>{{ l.valor }} dBm</strong>
            <span v-if="l.categoria" class="tc-status ms-1" :class="claseDbm(l.categoria)">{{ textoCategoria(l.categoria) }}</span>
            <span class="text-muted ms-1">{{ hora(l.capturada) }}<template v-if="l.correccion"> · de una corrección</template></span>
          </li>
        </ul>
      </div>

      <!-- Nota del técnico -->
      <div v-if="doc.nota_tecnico" class="od-bloque">
        <div class="small fw-semibold mb-1">Nota del técnico</div>
        <div class="small" style="white-space:pre-line">{{ doc.nota_tecnico }}</div>
      </div>

      <!-- Incidencias -->
      <div v-if="doc.incidencias && doc.incidencias.length" class="od-bloque">
        <div class="small fw-semibold mb-1">Incidencias</div>
        <ul class="small mb-0 ps-3">
          <li v-for="(inc, k) in doc.incidencias" :key="k">
            <strong>{{ inc.motivo }}</strong><template v-if="inc.nota"> — {{ inc.nota }}</template>
            <span class="text-muted d-block">{{ hora(inc.fecha) }}<template v-if="inc.quien"> · {{ inc.quien }}</template></span>
          </li>
        </ul>
      </div>

      <!-- Cronología -->
      <div v-if="doc.cronologia && doc.cronologia.length" class="od-bloque">
        <div class="small fw-semibold mb-1">Cronología</div>
        <ul class="small mb-0 ps-3">
          <li v-for="(c, k) in doc.cronologia" :key="k">{{ c.paso }}: {{ hora(c.fecha) }}<template v-if="c.quien"> · {{ c.quien }}</template></li>
        </ul>
      </div>
    </template>

    <!-- Foto ampliada -->
    <div v-if="actual !== null" class="oe-visor" role="dialog" aria-modal="true" :aria-label="'Foto: ' + etiqueta(fotos[actual])"
         tabindex="-1" ref="visor" @click.self="cerrar" @keydown.esc="cerrar" @keydown.left="mover(-1)" @keydown.right="mover(1)">
      <div class="oe-visor-caja">
        <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
          <div class="small text-white">
            <strong>{{ etiqueta(fotos[actual]) }}</strong> · {{ hora(fotos[actual].capturada) }}
            <span class="text-white-50">({{ actual + 1 }} de {{ fotos.length }})</span>
          </div>
          <div class="text-nowrap">
            <a :href="fotos[actual].url" target="_blank" rel="noopener" data-spa-skip class="btn btn-sm btn-light me-1">Abrir en otra pestaña</a>
            <button type="button" class="btn btn-sm btn-light" aria-label="Cerrar" @click="cerrar"><i class="fa fa-times"></i></button>
          </div>
        </div>
        <div class="oe-visor-img">
          <button v-if="fotos.length > 1" type="button" class="btn btn-light oe-flecha oe-izq" aria-label="Foto anterior" @click="mover(-1)"><i class="fa fa-chevron-left"></i></button>
          <img :src="fotos[actual].url" :alt="etiqueta(fotos[actual])">
          <button v-if="fotos.length > 1" type="button" class="btn btn-light oe-flecha oe-der" aria-label="Foto siguiente" @click="mover(1)"><i class="fa fa-chevron-right"></i></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

// Tipos internos de talento_work_order_media.type (los mismos de la pantalla «Campo»), por si la foto no
// trae tipo de evidencia del catálogo.
const NOMBRES_INTERNOS = {
  presentation: 'Presentación', completion: 'Completado', modem_sn: 'SN módem',
  ine_front: 'INE frente', ine_back: 'INE reverso', proof_address: 'Comprobante de domicilio',
};
// Categorías de talento_dbm_thresholds.
const CATEGORIAS_DBM = {
  sobrepotencia: ['Sobrepotencia', 'is-bad'], aviso: ['Aviso', 'is-warn'], excelente: ['Excelente', 'is-ok'],
  cumplio: ['Cumplió', 'is-ok'], baja_senal: ['Baja señal', 'is-bad'],
};
const CONDICIONES = { cambio_equipo: 'si hubo cambio de equipo' };
// #1286: cómo va cada corrección (texto y color de la etiqueta).
const ESTADOS_CORRECCION = {
  pedida: ['Espera al técnico', 'is-warn'], enviada: ['Por revisar', 'is-info'], aceptada: ['Aceptada', 'is-ok'],
  cancelada: ['Cancelada', 'is-slate'], reemplazada: ['Se pidió otra', 'is-slate'],
};

export default {
  name: 'OrdenDocumentacion',
  props: {
    // El mismo id con el que se abrió el detalle (GET /talento/api/ordenes/{id}).
    ordenId: { type: [Number, String], required: true },
  },
  data() {
    return { doc: null, cargando: false, error: '', visible: true, actual: null, rotas: {} };
  },
  computed: {
    fotos() { return Array.isArray(this.doc?.fotos) ? this.doc.fotos : []; },
    obligatorias() { return (this.doc?.requeridas || []).filter(r => !r.condicion); },
    condicionales() { return (this.doc?.requeridas || []).filter(r => r.condicion); },
    cumplidas() { return this.obligatorias.filter(r => r.cumplida).length; },
    faltantes() { return this.obligatorias.filter(r => !r.cumplida); },
    // #1286: primero las fotos de cuando se hizo la orden; después, cada corrección con las suyas.
    gruposFotos() {
      const correcciones = Array.isArray(this.doc?.correcciones) ? this.doc.correcciones : [];
      const grupos = [{ clave: 'orden', fotos: this.fotos.filter(f => !f.correccion_id) }];
      correcciones.forEach((c, k) => grupos.push({
        clave: 'c' + c.id, numero: k + 1, correccion: c,
        estado: ESTADOS_CORRECCION[c.estado === 'cerrada' ? c.cierre : c.estado] || [c.estado, 'is-slate'],
        fotos: this.fotos.filter(f => f.correccion_id === c.id),
      }));
      return grupos.filter(g => g.correccion || g.fotos.length);
    },
  },
  watch: {
    ordenId: { immediate: true, handler() { this.cargar(); } },
  },
  methods: {
    async cargar() {
      this.doc = null;
      this.error = '';
      this.actual = null;
      this.rotas = {};
      this.visible = true;
      this.cargando = true;
      const pedida = this.ordenId;
      try {
        const { data } = await axios.get(`/talento/api/ordenes/${encodeURIComponent(pedida)}/documentacion`);
        if (pedida !== this.ordenId) return; // ya se abrió otra orden
        this.doc = data || null;
      } catch (e) {
        if (pedida !== this.ordenId) return;
        if (e?.response?.status === 403) this.visible = false;
        else this.error = 'No se pudo cargar la documentación de la orden.';
      } finally {
        if (pedida === this.ordenId) this.cargando = false;
      }
    },
    etiqueta(f) { return f?.tipo || NOMBRES_INTERNOS[f?.tipo_interno] || 'Evidencia'; },
    hora(iso) { return iso ? new Date(iso).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : ''; },
    textoCategoria(c) { return (CATEGORIAS_DBM[c] || [c])[0]; },
    claseDbm(c) { return (CATEGORIAS_DBM[c] || [null, 'is-slate'])[1]; },
    textoCondicion(c) { return CONDICIONES[c] || c; },
    abrir(i) {
      if (!this.fotos[i]?.url) return;
      this.actual = i;
      this.$nextTick(() => this.$refs.visor?.focus());
    },
    cerrar() { this.actual = null; },
    mover(d) {
      if (this.actual === null) return;
      const n = this.fotos.length;
      for (let paso = 1; paso <= n; paso++) {
        const j = (this.actual + d * paso + n * n) % n;
        if (this.fotos[j]?.url) { this.actual = j; return; }
      }
    },
  },
};
</script>

<style scoped>
.od-bloque { border-top: 1px solid rgba(0, 0, 0, .08); padding-top: .6rem; margin-top: .6rem; }
.od-chip { font-size: .78rem; border-radius: 999px; padding: .15rem .6rem; border: 1px solid transparent; }
.od-grupo + .od-grupo { margin-top: .9rem; padding-top: .6rem; border-top: 1px dashed rgba(0, 0, 0, .12); }
.od-ok { background: rgba(25, 135, 84, .1); border-color: rgba(25, 135, 84, .35); color: #146c43; }
.od-falta { background: rgba(220, 53, 69, .08); border-color: rgba(220, 53, 69, .4); color: #b02a37; }
.oe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: .75rem; }
.oe-mini { display: flex; flex-direction: column; text-align: left; padding: 0; border: 1px solid rgba(0, 0, 0, .12); border-radius: .5rem; overflow: hidden; background: transparent; color: inherit; cursor: zoom-in; }
.oe-mini:disabled { cursor: default; }
.oe-mini:focus-visible { outline: 2px solid #0d6efd; outline-offset: 2px; }
.oe-mini img, .oe-sin-foto { width: 100%; height: 110px; object-fit: cover; display: flex; align-items: center; justify-content: center; background: rgba(0, 0, 0, .06); font-size: .8rem; }
.oe-pie { font-size: .78rem; padding: .35rem .5rem; line-height: 1.25; }
.oe-visor { position: fixed; inset: 0; background: rgba(0, 0, 0, .85); z-index: 10050; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.oe-visor-caja { width: 100%; max-width: 1100px; }
.oe-visor-img { position: relative; display: flex; justify-content: center; }
.oe-visor-img img { max-width: 100%; max-height: calc(100vh - 8rem); object-fit: contain; border-radius: .25rem; }
.oe-flecha { position: absolute; top: 50%; transform: translateY(-50%); opacity: .85; }
.oe-izq { left: .5rem; }
.oe-der { right: .5rem; }
</style>

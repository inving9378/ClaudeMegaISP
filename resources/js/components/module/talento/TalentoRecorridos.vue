<template>
  <div class="talento-recorridos tc-wrap" :class="{ 'tc-dark': darkMode }">

    <!-- LISTA -->
    <template v-if="!det">
      <div class="tc-card">
        <div class="tc-cardhead d-flex align-items-center justify-content-between flex-wrap gap-2 p-3">
          <h5 class="mb-0"><i class="fa fa-route me-2 text-primary"></i>Recorridos de planta externa</h5>
          <div class="d-flex gap-2 flex-wrap">
            <button v-if="puedeCategorias" @click="abrirCategorias" class="tc-btn tc-btn-seg"><i class="fa fa-tags me-1"></i>Categorías</button>
            <button v-if="puedeImportar" @click="abrirImportar" class="tc-btn tc-btn-ok"><i class="fa fa-upload me-1"></i>Importar recorrido</button>
          </div>
        </div>

        <div class="p-3">
          <p class="small text-muted mb-3">
            Sube la ruta que se grabó con Geo Tracker (GPX, KML o KMZ, o el GPX de una unidad de Flotas) y sus fotos.
            Cada foto se vuelve un hallazgo en el mapa; clasifícalos y envía el recorrido.
          </p>
          <div class="row g-2 mb-3">
            <div class="col-6 col-md-2">
              <select v-model="filtros.estado" @change="cargar(1)" class="form-select form-select-sm tc-select">
                <option value="">Todos los estados</option>
                <option v-for="(t, k) in ESTADOS" :key="k" :value="k">{{ t }}</option>
              </select>
            </div>
            <div class="col-6 col-md-2"><input v-model="filtros.desde" @change="cargar(1)" type="date" class="form-control form-control-sm" title="Desde"></div>
            <div class="col-6 col-md-2"><input v-model="filtros.hasta" @change="cargar(1)" type="date" class="form-control form-control-sm" title="Hasta"></div>
          </div>

          <div v-if="cargando" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
          <div v-else class="table-responsive">
            <table class="table table-hover table-sm align-middle">
              <thead class="table-light">
                <tr><th>Fecha</th><th>Colaborador</th><th>Recorrido</th><th>Modo</th><th class="text-end">Distancia</th><th class="text-center">Hallazgos</th><th>Estado</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="r in lista" :key="r.id">
                  <td class="small text-nowrap">{{ fecha(r.iniciado_at || r.created_at) }}</td>
                  <td class="small fw-semibold">{{ nombre(r.colaborador) }}</td>
                  <td class="small">{{ r.titulo || '—' }} <span class="text-muted">· {{ r.fuente.toUpperCase() }}</span></td>
                  <td class="small">{{ MODOS[r.modo] }}</td>
                  <td class="small text-end text-nowrap">{{ km(r.distancia_m) }}</td>
                  <td class="text-center small">
                    {{ r.hallazgos_count }}
                    <span v-if="r.hallazgos_sin_ubicar_count" class="tc-status is-warn ms-1" title="Sin ubicar">{{ r.hallazgos_sin_ubicar_count }} sin ubicar</span>
                  </td>
                  <td><span class="tc-status" :class="colorEstado(r.estado)">{{ ESTADOS[r.estado] }}</span></td>
                  <td><button @click="abrir(r.id)" class="tc-btn tc-btn-info btn-xs">Ver</button></td>
                </tr>
                <tr v-if="!lista.length"><td colspan="8" class="text-center text-muted py-4">Sin recorridos.</td></tr>
              </tbody>
            </table>
          </div>
          <div v-if="paginas > 1" class="d-flex justify-content-end gap-2 align-items-center small">
            <button class="tc-btn tc-btn-seg btn-xs" :disabled="pagina <= 1" @click="cargar(pagina - 1)">Anterior</button>
            <span>{{ pagina }} de {{ paginas }}</span>
            <button class="tc-btn tc-btn-seg btn-xs" :disabled="pagina >= paginas" @click="cargar(pagina + 1)">Siguiente</button>
          </div>
        </div>
      </div>
    </template>

    <!-- DETALLE -->
    <template v-else>
      <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <button @click="cerrar" class="tc-btn tc-btn-seg"><i class="fa fa-arrow-left me-1"></i>Volver</button>
        <h5 class="mb-0 me-2">{{ rec.titulo || 'Recorrido #' + rec.id }}</h5>
        <span class="tc-status" :class="colorEstado(rec.estado)">{{ ESTADOS[rec.estado] }}</span>
        <div class="ms-auto d-flex gap-2 flex-wrap">
          <a :href="`/talento/api/recorridos/${rec.id}/original`" class="tc-btn tc-btn-seg" data-spa-skip><i class="fa fa-download me-1"></i>Archivo original</a>
          <button v-if="det.puede_editar" @click="eliminarRecorrido" class="tc-btn tc-btn-bad"><i class="fa fa-trash me-1"></i>Eliminar</button>
          <button v-if="det.puede_editar" @click="enviar" class="tc-btn tc-btn-ok" :disabled="ocupado"><i class="fa fa-paper-plane me-1"></i>Enviar recorrido</button>
        </div>
      </div>

      <div class="small text-muted mb-2">
        {{ nombre(rec.colaborador) }} · {{ MODOS[rec.modo] }} · {{ km(rec.distancia_m) }}
        <template v-if="rec.iniciado_at"> · {{ fecha(rec.iniciado_at) }} a {{ hora(rec.terminado_at) }}</template>
        · {{ rec.puntos }} de {{ rec.puntos_total }} puntos · {{ rec.iniciado_por === 'supervisor' ? 'Lo subió el supervisor' : 'Lo subió el colaborador' }}
        <template v-if="rec.tarea_id"> · Orden #{{ rec.tarea_id }}</template>
      </div>
      <div v-if="aviso" class="alert py-2 small" :class="aviso.tipo === 'error' ? 'alert-danger' : 'alert-success'">{{ aviso.texto }}</div>

      <div class="row g-3">
        <div class="col-lg-7">
          <div class="tc-card p-0 overflow-hidden">
            <div ref="mapEl" class="rc-mapa" :class="{ 'rc-ubicando': ubicandoId }"></div>
          </div>
          <div v-if="ubicandoId" class="small mt-1 text-primary">
            <i class="fa fa-crosshairs me-1"></i>Haz clic en el mapa donde está el hallazgo #{{ numero(ubicandoId) }}.
            <a href="#" @click.prevent="ubicandoId = null">Cancelar</a>
          </div>

          <div v-if="det.puede_editar && muestra.length > 1" class="tc-card p-3 mt-3">
            <h6 class="mb-2"><i class="fa fa-cut me-1"></i>Recortar inicio y fin</h6>
            <div class="small text-muted mb-2">Quita lo que se grabó antes de empezar o después de terminar. El archivo original no se toca.</div>
            <label class="small d-flex justify-content-between"><span>Inicio</span><span>{{ etiquetaPunto(recorte.desde) }}</span></label>
            <input type="range" class="form-range" min="0" :max="muestra.length - 1" v-model.number="recorte.desde" @input="previaRecorte">
            <label class="small d-flex justify-content-between"><span>Fin</span><span>{{ etiquetaPunto(recorte.hasta) }}</span></label>
            <input type="range" class="form-range" min="0" :max="muestra.length - 1" v-model.number="recorte.hasta" @input="previaRecorte">
            <div class="d-flex gap-2 mt-1">
              <button class="tc-btn tc-btn-primary btn-xs" :disabled="ocupado || recorte.hasta <= recorte.desde" @click="guardarRecorte">Guardar recorte</button>
              <button class="tc-btn tc-btn-seg btn-xs" :disabled="ocupado" @click="quitarRecorte">Ruta completa</button>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div v-if="det.puede_editar" class="tc-card p-3 mb-3">
            <label class="small fw-semibold mb-1"><i class="fa fa-camera me-1"></i>Agregar fotos</label>
            <input ref="fotosExtra" type="file" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" multiple @change="subirFotosExtra" :disabled="!!subida">
            <div v-if="subida" class="small mt-1">Subiendo {{ subida.hechas }} de {{ subida.total }}…</div>
          </div>

          <h6 class="mb-2">Hallazgos ({{ det.hallazgos.length }})</h6>
          <div v-if="!det.hallazgos.length" class="small text-muted">Sin hallazgos. Cada foto que subas crea uno.</div>
          <div v-for="(h, i) in det.hallazgos" :key="h.id" :id="`rc-hal-${h.id}`"
               class="tc-card p-2 mb-2 rc-hallazgo" :class="{ 'rc-activo': activoId === h.id, 'rc-incompleto': errores[h.id] }">
            <div class="d-flex align-items-start gap-2">
              <span class="rc-num" :class="`rc-sev-${h.severidad || 'nada'}`">{{ i + 1 }}</span>
              <div class="d-flex gap-1 flex-wrap">
                <a v-for="f in h.fotos" :key="f.id" :href="f.url" target="_blank" data-spa-skip>
                  <img :src="f.url" class="rc-mini" :alt="f.nombre" loading="lazy">
                </a>
              </div>
              <div class="ms-auto small text-end text-muted">
                <div v-if="h.capturado_at">{{ h.capturado_at.substring(11, 16) }}</div>
                <div v-if="h.lat !== null">{{ FUENTES[h.fuente_ubicacion] || 'Ubicado' }}</div>
                <div v-else class="text-warning fw-semibold">Sin ubicar</div>
              </div>
            </div>

            <template v-if="det.puede_editar">
              <div class="row g-1 mt-1">
                <div class="col-7">
                  <select class="form-select form-select-sm tc-select" :value="h.categoria_id ?? ''" @change="guardarHallazgo(h, { categoria_id: $event.target.value || null })">
                    <option value="">Categoría…</option>
                    <option v-for="c in categoriasDe(h)" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                  </select>
                </div>
                <div class="col-5">
                  <select class="form-select form-select-sm tc-select" :value="h.severidad ?? ''" @change="guardarHallazgo(h, { severidad: $event.target.value || null })">
                    <option value="">Severidad…</option>
                    <option v-for="(t, k) in SEVERIDADES" :key="k" :value="k">{{ t }}</option>
                  </select>
                </div>
              </div>
              <textarea class="form-control form-control-sm mt-1" rows="2" maxlength="2000" placeholder="Descripción (obligatoria para enviar)"
                        :value="h.descripcion" @change="guardarHallazgo(h, { descripcion: $event.target.value })"></textarea>
              <div class="d-flex gap-1 mt-1 flex-wrap align-items-center">
                <button class="tc-btn tc-btn-seg btn-xs" @click="ubicar(h)"><i class="fa fa-map-marker-alt me-1"></i>{{ h.lat === null ? 'Ubicar en el mapa' : 'Mover' }}</button>
                <select v-if="det.hallazgos.length > 1" class="form-select form-select-sm tc-select rc-unir" @change="unir(h, $event)">
                  <option value="">Unir con…</option>
                  <option v-for="o in otros(h)" :key="o.id" :value="o.id">Hallazgo {{ o.n }}</option>
                </select>
                <button class="tc-btn tc-btn-bad btn-xs ms-auto" @click="eliminarHallazgo(h)"><i class="fa fa-trash"></i></button>
              </div>
              <div v-if="errores[h.id]" class="small text-danger mt-1">Falta: {{ errores[h.id].join(', ') }}.</div>
            </template>
            <div v-else class="small mt-1">
              <div><strong>{{ h.categoria || 'Sin categoría' }}</strong> · {{ SEVERIDADES[h.severidad] || 'Sin severidad' }}</div>
              <div class="rc-texto">{{ h.descripcion }}</div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Modal importar -->
    <div v-if="imp.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Importar recorrido</h5>
            <button @click="imp.show = false" type="button" class="btn-close" :disabled="imp.guardando"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6 position-relative">
                <label class="form-label">Colaborador que lo recorrió <span class="text-danger">*</span></label>
                <input v-model="imp.buscar" @input="buscarColaborador" type="text" class="form-control" placeholder="Buscar…">
                <ul v-if="imp.sugerencias.length" class="list-group mt-1 position-absolute shadow" style="z-index:10001;max-height:160px;overflow-y:auto">
                  <li v-for="c in imp.sugerencias" :key="c.id" @click="elegirColaborador(c)" class="list-group-item list-group-item-action small cursor-pointer">{{ nombre(c) }}</li>
                </ul>
                <div v-if="imp.colaborador_id" class="mt-1 small text-success"><i class="fa fa-check-circle me-1"></i>{{ imp.colaborador_nombre }}</div>
              </div>
              <div class="col-md-3">
                <label class="form-label">Modo <span class="text-danger">*</span></label>
                <select v-model="imp.modo" class="form-select tc-select">
                  <option v-for="(t, k) in MODOS" :key="k" :value="k">{{ t }}</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Orden (opcional)</label>
                <input v-model="imp.tarea_id" type="number" min="1" class="form-control" placeholder="Núm.">
              </div>
              <div class="col-12">
                <label class="form-label">Nombre o zona</label>
                <input v-model="imp.titulo" type="text" maxlength="160" class="form-control" placeholder="Ej. Revisión de troncal, colonia…">
              </div>
              <div class="col-md-6">
                <label class="form-label">Archivo de ruta <span class="text-danger">*</span></label>
                <input ref="archivoRuta" type="file" class="form-control" accept=".gpx,.kml,.kmz,application/gpx+xml,application/vnd.google-earth.kml+xml,application/vnd.google-earth.kmz">
                <div class="form-text">GPX, KML o KMZ exportado de Geo Tracker (o el GPX de una unidad de Flotas).</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Fotos</label>
                <input ref="fotosImp" type="file" class="form-control" accept="image/jpeg,image/png,image/webp" multiple>
                <div class="form-text">Se ubican con el GPS de la foto o con su hora sobre la ruta.</div>
              </div>
              <div v-if="imp.error" class="col-12"><div class="alert alert-danger py-2 small mb-0">{{ imp.error }}</div></div>
              <div v-if="subida" class="col-12 small">Subiendo fotos: {{ subida.hechas }} de {{ subida.total }}…</div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="imp.show = false" class="tc-btn tc-btn-seg" :disabled="imp.guardando">Cancelar</button>
            <button @click="importar" class="tc-btn tc-btn-ok" :disabled="imp.guardando">
              <span v-if="imp.guardando"><span class="spinner-border spinner-border-sm me-1"></span>Importando…</span>
              <span v-else>Importar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal categorías -->
    <div v-if="cat.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Categorías de hallazgo</h5>
            <button @click="cat.show = false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted">Las categorías no se borran: se desactivan. La severidad sugerida se propone al elegir la categoría.</p>
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr><th style="width:70px">Orden</th><th>Nombre</th><th style="width:140px">Severidad sugerida</th><th style="width:70px">Activa</th><th style="width:90px"></th></tr></thead>
              <tbody>
                <tr v-for="c in cat.lista" :key="c.id ?? 'nueva'">
                  <td><input v-model.number="c.orden" type="number" min="0" class="form-control form-control-sm"></td>
                  <td><input v-model="c.nombre" type="text" maxlength="120" class="form-control form-control-sm"></td>
                  <td>
                    <select v-model="c.severidad_sugerida" class="form-select form-select-sm tc-select">
                      <option :value="null">—</option>
                      <option v-for="(t, k) in SEVERIDADES" :key="k" :value="k">{{ t }}</option>
                    </select>
                  </td>
                  <td class="text-center"><input v-model="c.activo" type="checkbox" class="form-check-input"></td>
                  <td><button class="tc-btn tc-btn-primary btn-xs" @click="guardarCategoria(c)">Guardar</button></td>
                </tr>
              </tbody>
            </table>
            <button class="tc-btn tc-btn-seg btn-xs" @click="cat.lista.push({ id: null, nombre: '', severidad_sugerida: null, activo: true, orden: null })"><i class="fa fa-plus me-1"></i>Agregar categoría</button>
            <div v-if="cat.mensaje" class="small mt-2" :class="cat.error ? 'text-danger' : 'text-success'">{{ cat.mensaje }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { darkMode } from "../../../hook/appConfig.js";
import Permission from "../../../helpers/Permission.js";
import { allViewHasPermission } from "../../../helpers/Request.js";

const MODOS = { a_pie: 'A pie', vehiculo: 'En vehículo' };
const ESTADOS = { borrador: 'Borrador', enviado: 'Enviado', evaluado: 'Evaluado' };
const SEVERIDADES = { baja: 'Baja', media: 'Media', alta: 'Alta', critica: 'Crítica' };
const FUENTES = { exif: 'GPS de la foto', por_hora: 'Por la hora de la foto', manual: 'Puesto a mano', gps: 'GPS de la app' };

/**
 * Item #1311 — Recorridos de planta externa, fase (a): importar la ruta y las fotos de Geo Tracker,
 * clasificar los hallazgos, recortar y enviar. El servidor hace los cálculos
 * (TalentoRecorridoController); aquí solo se dibuja y se edita.
 */
export default {
  name: 'TalentoRecorridos',
  setup() {
    return { darkMode, MODOS, ESTADOS, SEVERIDADES, FUENTES };
  },
  data() {
    return {
      permisos: {},
      lista: [], cargando: true, pagina: 1, paginas: 1,
      filtros: { estado: '', desde: '', hasta: '' },
      det: null, activoId: null, ubicandoId: null, ocupado: false, aviso: null, errores: {},
      recorte: { desde: 0, hasta: 0 }, subida: null,
      imp: { show: false, colaborador_id: null, colaborador_nombre: '', buscar: '', sugerencias: [], modo: 'a_pie', titulo: '', tarea_id: '', error: '', guardando: false },
      cat: { show: false, lista: [], mensaje: '', error: false },
    };
  },
  created() {
    // Leaflet fuera de la reactividad de Vue.
    this.mapa = null;
    this.capa = null;
    this.capaPrevia = null;
    this.timerBuscar = null;
  },
  computed: {
    puedeImportar() { return new Permission(this.permisos).canDo('talento.recorridos.manage'); },
    puedeCategorias() { return new Permission(this.permisos).canDo('talento.recorridos.categorias.manage'); },
    rec() { return this.det?.recorrido ?? {}; },
    muestra() { return this.det?.recorrido?.muestra ?? []; },
  },
  async mounted() {
    this.permisos = await allViewHasPermission();
    this.cargar(1);
  },
  beforeUnmount() { this.quitarMapa(); },
  methods: {
    error(e, porDefecto = 'No se pudo guardar.') {
      const d = e?.response?.data ?? {};
      const primero = d.errors ? Object.values(d.errors).flat().find(v => typeof v === 'string') : null;
      return d.error || primero || d.message || porDefecto;
    },
    nombre(c) {
      const u = c?.user;
      return u ? [u.name, u.father_last_name].filter(Boolean).join(' ') : '—';
    },
    fecha(v) {
      if (!v) return '—';
      return new Date(String(v).includes('T') ? v : String(v).replace(' ', 'T'))
        .toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    },
    hora(v) {
      if (!v) return '—';
      return new Date(String(v).includes('T') ? v : String(v).replace(' ', 'T')).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    },
    km(m) { return m >= 1000 ? (m / 1000).toFixed(2) + ' km' : (m ?? 0) + ' m'; },
    colorEstado(e) { return { borrador: 'is-slate', enviado: 'is-info', evaluado: 'is-ok' }[e] || 'is-slate'; },
    numero(id) { return this.det.hallazgos.findIndex(h => h.id === id) + 1; },
    categoriasDe(h) { return this.det.categorias.filter(c => c.activo || c.id === h.categoria_id); },
    otros(h) { return this.det.hallazgos.map((o, j) => ({ id: o.id, n: j + 1 })).filter(o => o.id !== h.id); },

    async cargar(pagina) {
      this.cargando = true;
      try {
        const { data } = await axios.get('/talento/api/recorridos', { params: { ...this.filtros, page: pagina } });
        this.lista = data?.data ?? [];
        this.pagina = data?.current_page ?? 1;
        this.paginas = data?.last_page ?? 1;
      } finally { this.cargando = false; }
    },

    // ── Detalle y mapa ──────────────────────────────────────────────────
    async abrir(id) {
      const { data } = await axios.get(`/talento/api/recorridos/${id}`);
      this.aviso = null;
      this.errores = {};
      this.ponerDetalle(data);
    },
    async ponerDetalle(data, encuadrar = true) {
      this.det = data;
      const total = this.muestra.length;
      const r = data.recorrido;
      const idx = (n) => Math.max(0, this.muestra.findIndex(m => m[0] >= n));
      this.recorte = {
        desde: r.recorte_desde === null ? 0 : idx(r.recorte_desde),
        hasta: r.recorte_hasta === null ? total - 1 : idx(r.recorte_hasta),
      };
      await this.$nextTick();
      this.dibujar(encuadrar);
    },
    cerrar() {
      this.quitarMapa();
      this.det = null;
      this.ubicandoId = null;
      this.cargar(this.pagina);
    },
    quitarMapa() {
      if (this.mapa) { this.mapa.remove(); this.mapa = null; this.capa = null; this.capaPrevia = null; }
    },
    dibujar(encuadrar) {
      if (!this.$refs.mapEl) return;
      if (!this.mapa) {
        this.mapa = L.map(this.$refs.mapEl).setView([19.4326, -99.1332], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(this.mapa);
        this.capa = L.layerGroup().addTo(this.mapa);
        this.capaPrevia = L.layerGroup().addTo(this.mapa);
        this.mapa.on('click', (ev) => this.clicMapa(ev));
      }
      this.capa.clearLayers();
      this.capaPrevia.clearLayers();
      const r = this.det.recorrido;

      // Lo recortado, en gris punteado (de la muestra); la ruta que cuenta, en azul (simplificada).
      if (this.muestra.length > 1 && (r.recorte_desde !== null || r.recorte_hasta !== null)) {
        L.polyline(this.muestra.map(m => [m[1], m[2]]), { color: '#94a3b8', weight: 2, dashArray: '4 6' }).addTo(this.capa);
      }
      const linea = r.geometria ?? [];
      if (linea.length) {
        L.polyline(linea, { color: '#2563eb', weight: 4, opacity: 0.85 }).addTo(this.capa);
        L.circleMarker(linea[0], { radius: 7, color: '#fff', weight: 2, fillColor: '#16a34a', fillOpacity: 1 }).bindTooltip('Inicio').addTo(this.capa);
        L.circleMarker(linea[linea.length - 1], { radius: 7, color: '#fff', weight: 2, fillColor: '#dc2626', fillOpacity: 1 }).bindTooltip('Fin').addTo(this.capa);
      }

      const puntos = [...linea];
      this.det.hallazgos.forEach((h, i) => {
        if (h.lat === null) return;
        const icono = L.divIcon({ className: '', html: `<div class="rc-pin rc-sev-${h.severidad || 'nada'}">${i + 1}</div>`, iconSize: [26, 26], iconAnchor: [13, 13] });
        const m = L.marker([h.lat, h.lng], { icon: icono, draggable: this.det.puede_editar })
          .bindTooltip(`${i + 1}. ${h.categoria || 'Sin categoría'}`)
          .on('click', () => this.enfocar(h.id))
          .addTo(this.capa);
        if (this.det.puede_editar) {
          m.on('dragend', () => { const p = m.getLatLng(); this.guardarHallazgo(h, { lat: p.lat, lng: p.lng }); });
        }
        puntos.push([h.lat, h.lng]);
      });
      if (encuadrar && puntos.length) this.mapa.fitBounds(L.latLngBounds(puntos), { padding: [30, 30], maxZoom: 18 });
      setTimeout(() => this.mapa && this.mapa.invalidateSize(), 50);
    },
    enfocar(id) {
      this.activoId = id;
      document.getElementById(`rc-hal-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },
    ubicar(h) {
      this.ubicandoId = h.id;
      this.activoId = h.id;
    },
    clicMapa(ev) {
      if (!this.ubicandoId) return;
      const h = this.det.hallazgos.find(x => x.id === this.ubicandoId);
      this.ubicandoId = null;
      if (h) this.guardarHallazgo(h, { lat: ev.latlng.lat, lng: ev.latlng.lng });
    },

    // ── Recorte ─────────────────────────────────────────────────────────
    etiquetaPunto(i) {
      const m = this.muestra[i];
      if (!m) return '';
      return m[3] ? new Date(m[3] * 1000).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : `punto ${m[0] + 1}`;
    },
    previaRecorte() {
      if (!this.capaPrevia) return;
      this.capaPrevia.clearLayers();
      const { desde, hasta } = this.recorte;
      if (hasta <= desde) return;
      L.polyline(this.muestra.slice(desde, hasta + 1).map(m => [m[1], m[2]]), { color: '#f59e0b', weight: 6, opacity: 0.6 }).addTo(this.capaPrevia);
    },
    async guardarRecorte() {
      await this.actualizarRecorrido({ recorte_desde: this.muestra[this.recorte.desde][0], recorte_hasta: this.muestra[this.recorte.hasta][0] });
    },
    async quitarRecorte() {
      await this.actualizarRecorrido({ recorte_desde: 0, recorte_hasta: this.rec.puntos_total - 1 });
    },
    async actualizarRecorrido(cambios) {
      this.ocupado = true;
      try {
        const { data } = await axios.put(`/talento/api/recorridos/${this.rec.id}`, cambios);
        this.aviso = { tipo: 'ok', texto: 'Recorte guardado.' };
        await this.ponerDetalle(data, false);
      } catch (e) {
        this.aviso = { tipo: 'error', texto: this.error(e) };
      } finally { this.ocupado = false; }
    },

    // ── Hallazgos ───────────────────────────────────────────────────────
    async guardarHallazgo(h, cambios) {
      try {
        const { data } = await axios.put(`/talento/api/recorridos/${this.rec.id}/hallazgos/${h.id}`, cambios);
        const hecho = data.hallazgos.find(x => x.id === h.id);
        if (this.errores[h.id]) {
          if (hecho?.faltantes?.length) this.errores[h.id] = hecho.faltantes; else delete this.errores[h.id];
        }
        this.aviso = null;
        await this.ponerDetalle(data, false);
      } catch (e) {
        this.aviso = { tipo: 'error', texto: this.error(e) };
        // Volver a lo guardado (el select o el texto se quedaron con lo que no se aceptó).
        const { data } = await axios.get(`/talento/api/recorridos/${this.rec.id}`);
        this.det = null;
        await this.$nextTick();
        this.quitarMapa();
        await this.ponerDetalle(data, false);
      }
    },
    async unir(h, ev) {
      const destino = Number(ev.target.value);
      ev.target.value = '';
      if (!destino || !confirm(`¿Unir el hallazgo ${this.numero(h.id)} dentro del ${this.numero(destino)}? Sus fotos pasan a ese hallazgo.`)) return;
      try {
        const { data } = await axios.post(`/talento/api/recorridos/${this.rec.id}/hallazgos/${h.id}/unir`, { destino_id: destino });
        delete this.errores[h.id];
        await this.ponerDetalle(data, false);
      } catch (e) { this.aviso = { tipo: 'error', texto: this.error(e) }; }
    },
    async eliminarHallazgo(h) {
      if (!confirm(`¿Eliminar el hallazgo ${this.numero(h.id)} y sus fotos?`)) return;
      try {
        const { data } = await axios.delete(`/talento/api/recorridos/${this.rec.id}/hallazgos/${h.id}`);
        delete this.errores[h.id];
        await this.ponerDetalle(data, false);
      } catch (e) { this.aviso = { tipo: 'error', texto: this.error(e) }; }
    },
    async enviar() {
      if (!confirm('¿Enviar el recorrido? Después ya no se puede cambiar.')) return;
      this.ocupado = true;
      try {
        const { data } = await axios.post(`/talento/api/recorridos/${this.rec.id}/enviar`);
        this.errores = {};
        this.aviso = { tipo: 'ok', texto: 'Recorrido enviado.' };
        await this.ponerDetalle(data, false);
      } catch (e) {
        this.errores = { ...(e?.response?.data?.errors?.hallazgos ?? {}) };
        this.aviso = { tipo: 'error', texto: this.error(e, 'No se pudo enviar.') };
        const primero = Object.keys(this.errores)[0];
        if (primero) this.enfocar(Number(primero));
      } finally { this.ocupado = false; }
    },
    async eliminarRecorrido() {
      if (!confirm('¿Eliminar este recorrido? Solo se puede mientras es borrador.')) return;
      try {
        await axios.delete(`/talento/api/recorridos/${this.rec.id}`);
        this.cerrar();
      } catch (e) { this.aviso = { tipo: 'error', texto: this.error(e) }; }
    },

    // ── Fotos (de una en una: barra de avance y sin topar el tamaño máximo de la petición) ──
    async subirFotos(id, archivos) {
      const fallas = [];
      let repetidas = 0;
      this.subida = { total: archivos.length, hechas: 0 };
      for (const f of archivos) {
        const fd = new FormData();
        fd.append('foto', f);
        try {
          const { data } = await axios.post(`/talento/api/recorridos/${id}/fotos`, fd);
          if (data?.duplicada) repetidas++;
        } catch (e) {
          fallas.push(`${f.name}: ${this.error(e, 'no se pudo subir')}`);
        }
        this.subida.hechas++;
      }
      this.subida = null;
      return { fallas, repetidas };
    },
    resumenFotos({ fallas, repetidas }, subidas) {
      const partes = [`${subidas - fallas.length - repetidas} foto(s) agregada(s)`];
      if (repetidas) partes.push(`${repetidas} ya estaba(n)`);
      if (fallas.length) partes.push(`no se pudieron subir: ${fallas.join('; ')}`);
      return { tipo: fallas.length ? 'error' : 'ok', texto: partes.join(' · ') + '.' };
    },
    async subirFotosExtra(ev) {
      const archivos = [...(ev.target.files || [])];
      if (!archivos.length) return;
      const r = await this.subirFotos(this.rec.id, archivos);
      ev.target.value = '';
      const { data } = await axios.get(`/talento/api/recorridos/${this.rec.id}`);
      await this.ponerDetalle(data, true);
      this.aviso = this.resumenFotos(r, archivos.length);
    },

    // ── Importar ────────────────────────────────────────────────────────
    abrirImportar() {
      this.imp = { show: true, colaborador_id: null, colaborador_nombre: '', buscar: '', sugerencias: [], modo: 'a_pie', titulo: '', tarea_id: '', error: '', guardando: false };
    },
    buscarColaborador() {
      clearTimeout(this.timerBuscar);
      this.timerBuscar = setTimeout(async () => {
        if (!this.imp.buscar) { this.imp.sugerencias = []; return; }
        const { data } = await axios.get('/talento/api/recorridos/colaboradores', { params: { search: this.imp.buscar } });
        this.imp.sugerencias = data ?? [];
      }, 300);
    },
    elegirColaborador(c) {
      this.imp.colaborador_id = c.id;
      this.imp.colaborador_nombre = this.nombre(c);
      this.imp.buscar = this.imp.colaborador_nombre;
      this.imp.sugerencias = [];
    },
    async importar() {
      const archivo = this.$refs.archivoRuta?.files?.[0];
      if (!this.imp.colaborador_id) { this.imp.error = 'Elige al colaborador.'; return; }
      if (!archivo) { this.imp.error = 'Elige el archivo de ruta.'; return; }
      const fotos = [...(this.$refs.fotosImp?.files || [])];
      const fd = new FormData();
      fd.append('colaborador_id', this.imp.colaborador_id);
      fd.append('modo', this.imp.modo);
      if (this.imp.titulo) fd.append('titulo', this.imp.titulo);
      if (this.imp.tarea_id) fd.append('tarea_id', this.imp.tarea_id);
      fd.append('archivo', archivo);
      this.imp.guardando = true;
      this.imp.error = '';
      try {
        const { data } = await axios.post('/talento/api/recorridos', fd);
        const id = data.recorrido.id;
        const r = fotos.length ? await this.subirFotos(id, fotos) : null;
        this.imp.show = false;
        await this.abrir(id);
        if (r) this.aviso = this.resumenFotos(r, fotos.length);
      } catch (e) {
        this.imp.error = this.error(e, 'No se pudo importar.');
      } finally { this.imp.guardando = false; }
    },

    // ── Catálogo ────────────────────────────────────────────────────────
    async abrirCategorias() {
      const { data } = await axios.get('/talento/api/hallazgo-categorias');
      this.cat = { show: true, lista: data ?? [], mensaje: '', error: false };
    },
    async guardarCategoria(c) {
      const datos = { nombre: c.nombre, severidad_sugerida: c.severidad_sugerida, activo: !!c.activo };
      if (c.orden !== null && c.orden !== '') datos.orden = c.orden;
      try {
        const { data } = c.id
          ? await axios.put(`/talento/api/hallazgo-categorias/${c.id}`, datos)
          : await axios.post('/talento/api/hallazgo-categorias', datos);
        Object.assign(c, data);
        this.cat.mensaje = `«${data.nombre}» guardada.`;
        this.cat.error = false;
      } catch (e) {
        this.cat.mensaje = this.error(e);
        this.cat.error = true;
      }
    },
  },
};
</script>

<style scoped>
.talento-recorridos .rc-mapa { height: 560px; width: 100%; }
.talento-recorridos .rc-ubicando { cursor: crosshair; }
.talento-recorridos .rc-mini { width: 64px; height: 48px; object-fit: cover; border-radius: 4px; border: 1px solid var(--tc-border, #d1d5db); }
.talento-recorridos .rc-hallazgo.rc-activo { outline: 2px solid #2563eb; }
.talento-recorridos .rc-hallazgo.rc-incompleto { outline: 2px solid #dc2626; }
.talento-recorridos .rc-unir { width: auto; max-width: 140px; }
.talento-recorridos .rc-texto { white-space: pre-line; }
.talento-recorridos .rc-num,
.talento-recorridos :deep(.rc-pin) {
  display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto;
  width: 26px; height: 26px; border-radius: 50%; color: #fff; font-weight: 700; font-size: 12px;
  border: 2px solid #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, .4);
}
/* Mapa de Red pone a TODO marcador arrastrable un borde punteado azul (estilo global); aquí no. */
.talento-recorridos :deep(.leaflet-marker-draggable) { border: 0; background: transparent; }
.talento-recorridos .rc-sev-nada, .talento-recorridos :deep(.rc-sev-nada) { background: #64748b; }
.talento-recorridos .rc-sev-baja, .talento-recorridos :deep(.rc-sev-baja) { background: #16a34a; }
.talento-recorridos .rc-sev-media, .talento-recorridos :deep(.rc-sev-media) { background: #ca8a04; }
.talento-recorridos .rc-sev-alta, .talento-recorridos :deep(.rc-sev-alta) { background: #ea580c; }
.talento-recorridos .rc-sev-critica, .talento-recorridos :deep(.rc-sev-critica) { background: #b91c1c; }
@media (max-width: 991px) { .talento-recorridos .rc-mapa { height: 380px; } }
</style>

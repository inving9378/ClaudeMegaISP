<template>
  <div class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
    <div class="modal-dialog modal-fullscreen">
      <div class="modal-content tc-wrap" :class="{ 'tc-dark': darkMode }">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0">{{ plantillaId ? 'Editar plantilla' : 'Nueva plantilla' }} · {{ tipo ? tipo.name : '' }}</h5>
            <small v-if="ordenes" class="text-muted">{{ ordenes }} {{ ordenes === 1 ? 'orden usa' : 'órdenes usan' }} esta plantilla. Las órdenes ya creadas no se modifican: cada una guarda la plantilla con la que se creó.</small>
          </div>
          <button type="button" class="btn-close" aria-label="Cerrar" @click="$emit('cerrar')"></button>
        </div>

        <div v-if="cargando" class="modal-body text-center py-5"><div class="spinner-border text-primary"></div></div>
        <div v-else class="modal-body poe-cuerpo">
          <div class="row g-4">
            <!-- ── Editor ── -->
            <div class="col-lg-7">
              <section class="poe-seccion">
                <h6>Datos generales</h6>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="poe_nombre">Nombre <span class="text-danger">*</span></label>
                    <input id="poe_nombre" v-model="form.name" class="form-control" :class="{ 'is-invalid': err('name') }" maxlength="160">
                    <div class="invalid-feedback">{{ err('name') }}</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="poe_titulo">Título de la orden</label>
                    <input id="poe_titulo" v-model="form.title" class="form-control" :class="{ 'is-invalid': err('title') }" maxlength="160" placeholder="Si se deja vacío, el nombre">
                    <div class="invalid-feedback">{{ err('title') }}</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="poe_prioridad">Prioridad</label>
                    <select id="poe_prioridad" v-model="form.priority" class="form-select tc-select">
                      <option v-for="p in catalogos.prioridades" :key="p" :value="p">{{ p }}</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="poe_minutos">Tiempo estimado (min)</label>
                    <input id="poe_minutos" v-model.number="form.estimated_minutes" type="number" min="1" max="1440" class="form-control" :class="{ 'is-invalid': err('estimated_minutes') }">
                    <div class="invalid-feedback">{{ err('estimated_minutes') }}</div>
                  </div>
                  <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                      <input id="poe_activa" v-model="form.active" class="form-check-input" type="checkbox" role="switch">
                      <label class="form-check-label" for="poe_activa">Activa</label>
                    </div>
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="poe_desc">Instrucciones para el técnico</label>
                    <textarea id="poe_desc" v-model="form.description" class="form-control" rows="3" maxlength="5000" :class="{ 'is-invalid': err('description') }"></textarea>
                    <div class="invalid-feedback">{{ err('description') }}</div>
                  </div>
                </div>
              </section>

              <section class="poe-seccion">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">Campos <small class="text-muted">({{ campos.length }} de {{ topes.campos }})</small></h6>
                  <button class="tc-btn tc-btn-ok" :disabled="campos.length >= topes.campos" @click="agregarCampo"><i class="fa fa-plus me-1"></i> Agregar campo</button>
                </div>
                <div v-if="err('fields')" class="alert alert-danger py-1">{{ err('fields') }}</div>
                <div v-if="!campos.length" class="text-muted small mb-2">Sin campos todavía.</div>

                <div v-for="(c, i) in campos" :key="c._uid" class="poe-campo" :data-campo-editor="i">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong class="small">{{ i + 1 }}. {{ c.label || 'Campo sin etiqueta' }}</strong>
                    <div class="text-nowrap">
                      <small class="text-muted me-2">{{ c.key ? 'clave: ' + c.key : 'clave: se genera al guardar' }}</small>
                      <button class="tc-btn me-1" :disabled="i === 0" title="Subir" aria-label="Subir campo" @click="moverCampo(i, -1)"><i class="fa fa-arrow-up"></i></button>
                      <button class="tc-btn me-1" :disabled="i === campos.length - 1" title="Bajar" aria-label="Bajar campo" @click="moverCampo(i, 1)"><i class="fa fa-arrow-down"></i></button>
                      <button class="tc-btn tc-btn-bad" title="Quitar" aria-label="Quitar campo" @click="quitarCampo(i)"><i class="fa fa-times"></i></button>
                    </div>
                  </div>
                  <div class="row g-2">
                    <div class="col-md-5">
                      <label class="form-label small" :for="'poe_l_' + c._uid">Etiqueta <span class="text-danger">*</span></label>
                      <input :id="'poe_l_' + c._uid" v-model="c.label" class="form-control form-control-sm" :maxlength="topes.etiqueta" :class="{ 'is-invalid': errC(i, 'label') || errC(i, 'key') }">
                      <div class="invalid-feedback">{{ errC(i, 'label') || errC(i, 'key') }}</div>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small" :for="'poe_t_' + c._uid">Tipo</label>
                      <select :id="'poe_t_' + c._uid" v-model="c.type" class="form-select form-select-sm tc-select" :class="{ 'is-invalid': errC(i, 'type') }">
                        <option v-for="t in catalogos.tipos_campo" :key="t" :value="t">{{ etiquetaTipo(t) }}</option>
                      </select>
                      <div class="invalid-feedback">{{ errC(i, 'type') }}</div>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small" :for="'poe_w_' + c._uid">Se captura</label>
                      <select :id="'poe_w_' + c._uid" v-model="c.when" class="form-select form-select-sm tc-select">
                        <option value="creacion">Al crear</option>
                        <option value="cierre">Al cerrar</option>
                      </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                      <div class="form-check mb-1">
                        <input :id="'poe_r_' + c._uid" v-model="c.required" class="form-check-input" type="checkbox">
                        <label class="form-check-label small" :for="'poe_r_' + c._uid">Obligatorio</label>
                      </div>
                    </div>
                    <div v-if="c.type !== 'tabla'" class="col-md-3">
                      <label class="form-label small" :for="'poe_u_' + c._uid">Unidad</label>
                      <input :id="'poe_u_' + c._uid" v-model="c.unit" class="form-control form-control-sm" :maxlength="topes.unidad" placeholder="dBm, m…" :class="{ 'is-invalid': errC(i, 'unit') }">
                      <div class="invalid-feedback">{{ errC(i, 'unit') }}</div>
                    </div>
                    <div :class="c.type !== 'tabla' ? 'col-md-6' : 'col-md-9'">
                      <label class="form-label small" :for="'poe_h_' + c._uid">Ayuda</label>
                      <input :id="'poe_h_' + c._uid" v-model="c.help" class="form-control form-control-sm" :maxlength="topes.ayuda" :class="{ 'is-invalid': errC(i, 'help') }">
                      <div class="invalid-feedback">{{ errC(i, 'help') }}</div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                      <div class="form-check mb-1" :title="puedeIdentificar(c) ? '' : 'Solo texto, número o selección que se pide al crear'">
                        <input :id="'poe_id_' + c._uid" v-model="c.identificador" class="form-check-input" type="checkbox" :disabled="!puedeIdentificar(c)" :class="{ 'is-invalid': errC(i, 'identificador') }">
                        <label class="form-check-label small" :for="'poe_id_' + c._uid">Usar en el título</label>
                        <div class="invalid-feedback">{{ errC(i, 'identificador') }}</div>
                      </div>
                    </div>

                    <div v-if="conOpciones(c.type)" class="col-12">
                      <label class="form-label small" :for="'poe_o_' + c._uid">Opciones (una por renglón, de {{ topes.min_opciones }} a {{ topes.max_opciones }})</label>
                      <textarea :id="'poe_o_' + c._uid" v-model="c.opcionesTexto" class="form-control form-control-sm" rows="3" :class="{ 'is-invalid': errC(i, 'options') }"></textarea>
                      <div class="invalid-feedback">{{ errC(i, 'options') }}</div>
                    </div>

                    <div v-if="c.type === 'tabla'" class="col-12 poe-tabla">
                      <div class="d-flex flex-wrap gap-3 align-items-end mb-2">
                        <div class="form-check">
                          <input :id="'poe_fijas_' + c._uid" v-model="c.dynamic" class="form-check-input" type="radio" :value="false">
                          <label class="form-check-label small" :for="'poe_fijas_' + c._uid">Filas fijas</label>
                        </div>
                        <div class="form-check">
                          <input :id="'poe_din_' + c._uid" v-model="c.dynamic" class="form-check-input" type="radio" :value="true">
                          <label class="form-check-label small" :for="'poe_din_' + c._uid">Filas que agrega el técnico</label>
                        </div>
                        <template v-if="!c.dynamic">
                          <div>
                            <label class="form-label small mb-0" :for="'poe_rc_' + c._uid">Cuántas filas</label>
                            <input :id="'poe_rc_' + c._uid" v-model.number="c.rows_count" type="number" min="1" :max="topes.filas_tabla" class="form-control form-control-sm" style="width:6rem" :class="{ 'is-invalid': errC(i, 'rows_count') }">
                          </div>
                          <div>
                            <label class="form-label small mb-0" :for="'poe_rp_' + c._uid">Nombre de cada fila</label>
                            <input :id="'poe_rp_' + c._uid" v-model="c.row_label_prefix" class="form-control form-control-sm" maxlength="40" placeholder="Conector" style="width:10rem">
                          </div>
                        </template>
                        <div v-else>
                          <label class="form-label small mb-0" :for="'poe_mr_' + c._uid">Máximo de filas</label>
                          <input :id="'poe_mr_' + c._uid" v-model.number="c.max_rows" type="number" min="1" :max="topes.filas_tabla" class="form-control form-control-sm" style="width:6rem" :class="{ 'is-invalid': errC(i, 'max_rows') }">
                        </div>
                      </div>
                      <div v-if="errC(i, 'rows_count') || errC(i, 'max_rows') || errC(i, 'columns')" class="text-danger small mb-1">{{ errC(i, 'rows_count') || errC(i, 'max_rows') || errC(i, 'columns') }}</div>
                      <table class="table table-sm mb-1">
                        <thead><tr><th>Columna</th><th>Tipo</th><th>Unidad</th><th>Opciones (una por renglón)</th><th></th></tr></thead>
                        <tbody>
                          <tr v-for="(col, j) in c.columns" :key="col._uid">
                            <td>
                              <input v-model="col.label" class="form-control form-control-sm" :maxlength="topes.etiqueta" :aria-label="'Columna ' + (j + 1)" :class="{ 'is-invalid': errCol(i, j, 'label') || errCol(i, j, 'key') }">
                              <div class="invalid-feedback">{{ errCol(i, j, 'label') || errCol(i, j, 'key') }}</div>
                            </td>
                            <td>
                              <select v-model="col.type" class="form-select form-select-sm tc-select" :aria-label="'Tipo de la columna ' + (j + 1)">
                                <option v-for="t in catalogos.tipos_columna" :key="t" :value="t">{{ etiquetaTipo(t) }}</option>
                              </select>
                            </td>
                            <td><input v-model="col.unit" class="form-control form-control-sm" :maxlength="topes.unidad" style="width:6rem" :aria-label="'Unidad de la columna ' + (j + 1)" :class="{ 'is-invalid': errCol(i, j, 'unit') }"></td>
                            <td>
                              <textarea v-if="col.type === 'seleccion'" v-model="col.opcionesTexto" class="form-control form-control-sm" rows="2" :aria-label="'Opciones de la columna ' + (j + 1)" :class="{ 'is-invalid': errCol(i, j, 'options') }"></textarea>
                              <div class="invalid-feedback">{{ errCol(i, j, 'options') }}</div>
                            </td>
                            <td><button class="tc-btn tc-btn-bad" :aria-label="'Quitar columna ' + (j + 1)" @click="c.columns.splice(j, 1)"><i class="fa fa-times"></i></button></td>
                          </tr>
                        </tbody>
                      </table>
                      <button class="tc-btn" @click="c.columns.push(columnaVacia())"><i class="fa fa-plus me-1"></i> Agregar columna</button>
                    </div>
                  </div>
                </div>
              </section>

              <section class="poe-seccion">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">Materiales</h6>
                  <button class="tc-btn tc-btn-ok" @click="materiales.push(materialVacio())"><i class="fa fa-plus me-1"></i> Agregar material</button>
                </div>
                <div v-if="err('checklist')" class="alert alert-danger py-1">{{ err('checklist') }}</div>
                <table v-if="materiales.length" class="table table-sm align-middle">
                  <thead><tr><th>Material</th><th style="width:7rem">Cantidad</th><th style="width:7rem">Unidad</th><th style="width:3rem"></th></tr></thead>
                  <tbody>
                    <tr v-for="(m, k) in materiales" :key="m._uid">
                      <td><input v-model="m.label" class="form-control form-control-sm" maxlength="255" :aria-label="'Material ' + (k + 1)" :class="{ 'is-invalid': err('checklist.' + k + '.label') }"></td>
                      <td><input v-model="m.cantidad" type="number" min="0" step="any" class="form-control form-control-sm" :aria-label="'Cantidad del material ' + (k + 1)" :class="{ 'is-invalid': err('checklist.' + k + '.cantidad') }"></td>
                      <td><input v-model="m.unidad" class="form-control form-control-sm" maxlength="30" :aria-label="'Unidad del material ' + (k + 1)"></td>
                      <td><button class="tc-btn tc-btn-bad" :aria-label="'Quitar material ' + (k + 1)" @click="materiales.splice(k, 1)"><i class="fa fa-times"></i></button></td>
                    </tr>
                  </tbody>
                </table>
                <div v-else class="text-muted small">Sin materiales.</div>
              </section>
            </div>

            <!-- ── Vista previa en vivo ── -->
            <div class="col-lg-5">
              <div class="poe-previa">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">Vista previa</h6>
                  <small class="text-muted">{{ previa.cargando ? 'Actualizando…' : (hayErroresPrevia ? 'Hay datos por corregir' : 'Al día') }}</small>
                </div>
                <div class="poe-previa-bloque">
                  <div class="small fw-semibold mb-2">Así se verá al crear la orden</div>
                  <campos-plantilla v-if="camposCreacion.length" v-model="muestra" :campos="camposCreacion" id-prefix="poe_prev_crear" />
                  <div v-else class="text-muted small">Ningún campo se pide al crear.</div>
                </div>
                <div class="poe-previa-bloque">
                  <div class="small fw-semibold mb-2">Así se verá para el técnico al cerrarla</div>
                  <campos-plantilla v-if="camposCierre.length" v-model="muestraCierre" :campos="camposCierre" id-prefix="poe_prev_cerrar" />
                  <div v-else class="text-muted small">Ningún campo se captura al cerrar.</div>
                </div>
                <div class="poe-previa-bloque">
                  <div class="small fw-semibold mb-1">Título de la orden</div>
                  <div class="poe-titulo">{{ previa.titulo || '—' }}</div>
                  <div class="small fw-semibold mt-3 mb-1">Texto que verá la app del técnico</div>
                  <pre class="poe-desc">{{ previa.descripcion || '—' }}</pre>
                  <small class="text-muted">Escribe valores de ejemplo arriba para ver cómo se arma el título y el texto.</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <div v-if="error" class="text-danger me-auto small">{{ error }}</div>
          <button class="tc-btn" @click="$emit('cerrar')">Cancelar</button>
          <button class="tc-btn tc-btn-ok" :disabled="guardando || cargando" @click="guardar">
            <span v-if="guardando" class="spinner-border spinner-border-sm me-1"></span>Guardar plantilla
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { darkMode } from '../../../../hook/appConfig.js';
import CamposPlantilla from '../campos/CamposPlantilla.vue';
import { camposDe, MOMENTO_CREACION, MOMENTO_CIERRE } from '../campos/camposPlantilla.js';

const API = '/talento/api/plantillas-orden';
const ETIQUETAS_TIPO = {
  texto: 'Texto corto', texto_largo: 'Texto largo', numero: 'Número', seleccion: 'Selección (una)',
  multi_seleccion: 'Selección múltiple', si_no: 'Sí / No', fecha: 'Fecha', coordenadas: 'Coordenadas', tabla: 'Tabla',
};
let uid = 0;
const nuevoUid = () => ++uid;
const lineas = (texto) => String(texto || '').split('\n').map(s => s.trim()).filter(Boolean);

export default {
  name: 'PlantillaOrdenEditor',
  components: { CamposPlantilla },
  props: {
    tipo: { type: Object, default: null },
    plantillaId: { type: Number, default: null },
    catalogos: { type: Object, required: true },
  },
  emits: ['cerrar', 'guardada'],
  setup() {
    return { darkMode };
  },
  data() {
    return {
      cargando: !!this.plantillaId, guardando: false, error: '', errores: {}, ordenes: 0,
      form: { name: '', title: '', priority: 'Media', estimated_minutes: null, description: '', active: true },
      campos: [], materiales: [],
      muestra: {}, muestraCierre: {},
      previa: { fields: [], titulo: '', descripcion: '', errors: {}, cargando: false },
      pausa: null, ultimaPrevia: 0,
    };
  },
  computed: {
    topes() {
      return Object.assign({ campos: 40, etiqueta: 80, unidad: 12, ayuda: 200, min_opciones: 2, max_opciones: 30, filas_tabla: 100 }, this.catalogos.topes || {});
    },
    camposCreacion() { return camposDe(this.previa.fields || [], MOMENTO_CREACION); },
    camposCierre() { return camposDe(this.previa.fields || [], MOMENTO_CIERRE); },
    hayErroresPrevia() { return Object.keys(this.previa.errors || {}).length > 0; },
    firmaPrevia() {
      return JSON.stringify([this.form.title, this.form.description, this.payloadCampos(), this.payloadMateriales(), this.muestra]);
    },
  },
  watch: {
    firmaPrevia() { this.pedirPrevia(); },
  },
  async mounted() {
    if (this.plantillaId) await this.cargar();
    this.pedirPrevia(0);
  },
  methods: {
    etiquetaTipo(t) { return ETIQUETAS_TIPO[t] || t; },
    conOpciones(t) { return t === 'seleccion' || t === 'multi_seleccion'; },
    puedeIdentificar(c) {
      return (this.catalogos.tipos_identificador || ['texto', 'numero', 'seleccion']).includes(c.type) && c.when === MOMENTO_CREACION;
    },
    err(ruta) { return this.errores[ruta] || ''; },
    errC(i, prop) { return this.errores[`fields.${i}.${prop}`] || ''; },
    errCol(i, j, prop) { return this.errores[`fields.${i}.columns.${j}.${prop}`] || ''; },

    campoVacio() {
      return { _uid: nuevoUid(), key: null, label: '', type: 'texto', required: false, when: MOMENTO_CREACION, unit: '', help: '',
        identificador: false, opcionesTexto: '', dynamic: false, rows_count: 4, row_label_prefix: '', max_rows: 20, columns: [this.columnaVacia()] };
    },
    columnaVacia() { return { _uid: nuevoUid(), key: null, label: '', type: 'texto', unit: '', opcionesTexto: '' }; },
    materialVacio() { return { _uid: nuevoUid(), label: '', cantidad: '', unidad: '' }; },
    desdeServidor(f) {
      return Object.assign(this.campoVacio(), {
        key: f.key, label: f.label, type: f.type, required: !!f.required, when: f.when || MOMENTO_CREACION,
        unit: f.unit || '', help: f.help || '', identificador: !!f.identificador, opcionesTexto: (f.options || []).join('\n'),
        dynamic: !!f.dynamic, rows_count: f.rows_count || 4, row_label_prefix: f.row_label_prefix || '', max_rows: f.max_rows || 20,
        columns: (f.columns || []).map(c => ({ _uid: nuevoUid(), key: c.key, label: c.label, type: c.type || 'texto', unit: c.unit || '', opcionesTexto: (c.options || []).join('\n') })),
      });
    },
    async cargar() {
      this.cargando = true;
      try {
        const { data } = await axios.get(`${API}/plantillas/${this.plantillaId}`);
        this.form = { name: data.name, title: data.title === data.name ? '' : data.title, priority: data.priority || 'Media',
          estimated_minutes: data.estimated_minutes, description: data.description || '', active: !!data.active };
        this.campos = (data.fields || []).map(f => this.desdeServidor(f));
        this.materiales = (data.checklist || []).map(m => ({ _uid: nuevoUid(), label: m.label, cantidad: m.cantidad ?? '', unidad: m.unidad || '' }));
        this.ordenes = data.ordenes || 0;
      } catch (e) {
        this.error = e?.response?.data?.message || 'No se pudo cargar la plantilla.';
      } finally {
        this.cargando = false;
      }
    },

    agregarCampo() {
      this.campos.push(this.campoVacio());
      this.$nextTick(() => {
        const n = this.campos.length - 1;
        this.$el.querySelector(`[data-campo-editor="${n}"] input`)?.focus();
      });
    },
    quitarCampo(i) { this.campos.splice(i, 1); this.errores = {}; },
    moverCampo(i, d) {
      const j = i + d;
      if (j < 0 || j >= this.campos.length) return;
      const lista = [...this.campos];
      [lista[i], lista[j]] = [lista[j], lista[i]];
      this.campos = lista;
      this.errores = {};
    },

    payloadCampos() {
      return this.campos.map((c, i) => {
        const f = { label: c.label, type: c.type, required: !!c.required, when: c.when, sort_order: (i + 1) * 10 };
        if (c.key) f.key = c.key;
        if (c.unit && c.type !== 'tabla') f.unit = c.unit;
        if (c.help) f.help = c.help;
        if (c.identificador && this.puedeIdentificar(c)) f.identificador = true;
        if (this.conOpciones(c.type)) f.options = lineas(c.opcionesTexto);
        if (c.type === 'tabla') {
          f.dynamic = !!c.dynamic;
          if (c.dynamic) f.max_rows = Number(c.max_rows) || 0;
          else { f.rows_count = Number(c.rows_count) || 0; f.row_label_prefix = c.row_label_prefix || ''; }
          f.columns = c.columns.map(col => {
            const x = { label: col.label, type: col.type };
            if (col.key) x.key = col.key;
            if (col.unit) x.unit = col.unit;
            if (col.type === 'seleccion') x.options = lineas(col.opcionesTexto);
            return x;
          });
        }
        return f;
      });
    },
    payloadMateriales() {
      return this.materiales.map(m => ({ label: m.label, cantidad: m.cantidad === '' ? null : m.cantidad, unidad: m.unidad || null }));
    },

    pedirPrevia(espera = 400) {
      clearTimeout(this.pausa);
      this.pausa = setTimeout(() => this.cargarPrevia(), espera);
    },
    async cargarPrevia() {
      const turno = ++this.ultimaPrevia;
      this.previa.cargando = true;
      try {
        const { data } = await axios.post(API + '/vista-previa', {
          plantilla_id: this.plantillaId || undefined, type_id: this.tipo ? this.tipo.id : undefined,
          title: this.form.title, description: this.form.description,
          fields: this.payloadCampos(), checklist: this.payloadMateriales(), valores: this.muestra,
        });
        if (turno !== this.ultimaPrevia) return; // llegó tarde: ya hay una más nueva
        this.previa = { fields: data.fields || [], titulo: data.titulo || '', descripcion: data.descripcion || '', errors: data.errors || {}, cargando: false };
      } catch (e) {
        if (turno === this.ultimaPrevia) this.previa.cargando = false;
      }
    },

    async guardar() {
      if (this.ordenes > 0 && !window.confirm('Las órdenes ya creadas no se modifican: siguen con la plantilla con la que se crearon. ¿Guardar los cambios?')) {
        return;
      }
      this.guardando = true;
      this.error = '';
      this.errores = {};
      const body = {
        type_id: this.tipo ? this.tipo.id : undefined, name: this.form.name, title: this.form.title, priority: this.form.priority,
        estimated_minutes: this.form.estimated_minutes || null, description: this.form.description, active: !!this.form.active,
        fields: this.payloadCampos(), checklist: this.payloadMateriales(),
      };
      try {
        const { data } = this.plantillaId
          ? await axios.put(`${API}/plantillas/${this.plantillaId}`, body)
          : await axios.post(API + '/plantillas', body);
        this.$emit('guardada', data);
      } catch (e) {
        const d = e?.response?.data || {};
        this.errores = d.errors || {};
        this.error = d.error || d.message || 'No se pudo guardar la plantilla.';
        this.$nextTick(() => this.$el.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
      } finally {
        this.guardando = false;
      }
    },
  },
};
</script>

<style scoped>
.poe-cuerpo { overflow-y: auto; }
.poe-seccion { margin-bottom: 1.5rem; }
.poe-campo { border: 1px solid var(--tc-border, rgba(0, 0, 0, .12)); border-radius: .5rem; padding: .75rem; margin-bottom: .75rem; }
.poe-tabla { border-top: 1px dashed var(--tc-border, rgba(0, 0, 0, .12)); padding-top: .5rem; }
.poe-previa { position: sticky; top: 0; }
.poe-previa-bloque { border: 1px solid var(--tc-border, rgba(0, 0, 0, .12)); border-radius: .5rem; padding: .75rem; margin-bottom: .75rem; }
.poe-titulo { font-weight: 600; }
.poe-desc { white-space: pre-wrap; font-size: .85rem; margin: 0; max-height: 18rem; overflow-y: auto; }
</style>

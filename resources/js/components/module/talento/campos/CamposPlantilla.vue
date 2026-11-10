<template>
  <!-- Campos de una plantilla de orden (item #1200). Genérico: recibe el esquema por props y no
       sabe de qué pantalla viene. Lo usan el modal "Nueva orden" (campos de creación), el detalle
       (solo lectura) y, más adelante, el portal (cierre, Fase D) y el editor de plantillas (Fase C). -->
  <div class="row g-3 campos-plantilla">
    <div v-for="c in campos" :key="c.key" :class="anchoDe(c)" :data-campo="c.key">
      <label class="form-label mb-1" :for="idDe(c.key)">
        {{ c.label }}<span v-if="c.required && c.type !== 'tabla' && !soloLectura" class="text-danger"> *</span>
        <small v-if="c.unit && c.type !== 'numero'" class="text-muted">({{ c.unit }})</small>
      </label>

      <!-- ── Solo lectura ───────────────────────────────────────────── -->
      <template v-if="soloLectura">
        <template v-if="c.type === 'tabla'">
          <div v-if="filasConDatos(c).length" class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0 cp-tabla">
              <thead><tr>
                <th></th>
                <th v-for="col in c.columns" :key="col.key" class="small">{{ col.label }}<span v-if="col.unit" class="text-muted"> ({{ col.unit }})</span></th>
              </tr></thead>
              <tbody>
                <tr v-for="f in filasConDatos(c)" :key="f.i">
                  <th class="small text-nowrap fw-normal">{{ etiquetaFila(c, f.i) }}</th>
                  <td v-for="col in c.columns" :key="col.key" class="small">{{ fmt(col, f.fila[col.key]) ?? '' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="small text-muted fst-italic">{{ textoVacio }}</div>
        </template>
        <div v-else class="cp-valor" :class="{ 'text-muted fst-italic': fmt(c, valor(c.key)) === null }">
          {{ fmt(c, valor(c.key)) ?? textoVacio }}
        </div>
      </template>

      <!-- ── Captura ────────────────────────────────────────────────── -->
      <template v-else>
        <input v-if="c.type === 'texto'" :id="idDe(c.key)" type="text" class="form-control"
               :class="{ 'is-invalid': error(c.key) }" maxlength="255"
               :value="valor(c.key) ?? ''" @input="set(c.key, $event.target.value)">

        <textarea v-else-if="c.type === 'texto_largo'" :id="idDe(c.key)" class="form-control" rows="3"
                  :class="{ 'is-invalid': error(c.key) }" maxlength="5000"
                  :value="valor(c.key) ?? ''" @input="set(c.key, $event.target.value)"></textarea>

        <div v-else-if="c.type === 'numero'" class="input-group">
          <input :id="idDe(c.key)" type="number" step="any" class="form-control"
                 :class="{ 'is-invalid': error(c.key) }"
                 :value="valor(c.key) ?? ''" @input="set(c.key, $event.target.value)">
          <span v-if="c.unit" class="input-group-text">{{ c.unit }}</span>
        </div>

        <select v-else-if="c.type === 'seleccion'" :id="idDe(c.key)" class="form-select"
                :class="{ 'is-invalid': error(c.key) }"
                :value="valor(c.key) ?? ''" @change="set(c.key, $event.target.value || null)">
          <option value="">— Seleccionar —</option>
          <option v-for="o in c.options" :key="o" :value="o">{{ o }}</option>
        </select>

        <div v-else-if="c.type === 'multi_seleccion'" :id="idDe(c.key)" class="cp-multi" :class="{ 'is-invalid': error(c.key) }">
          <div v-for="(o, i) in c.options" :key="o" class="form-check form-check-inline">
            <input :id="idDe(c.key) + '_' + i" class="form-check-input" type="checkbox"
                   :checked="(valor(c.key) || []).includes(o)" @change="alternar(c, o, $event.target.checked)">
            <label class="form-check-label" :for="idDe(c.key) + '_' + i">{{ o }}</label>
          </div>
        </div>

        <div v-else-if="c.type === 'si_no'" class="form-check form-switch">
          <input :id="idDe(c.key)" class="form-check-input" type="checkbox" role="switch"
                 :checked="valor(c.key) === true" @change="set(c.key, $event.target.checked)">
          <label class="form-check-label small" :class="{ 'text-muted fst-italic': typeof valor(c.key) !== 'boolean' }" :for="idDe(c.key)">
            {{ valor(c.key) === true ? 'Sí' : (valor(c.key) === false ? 'No' : 'Sin responder') }}
          </label>
        </div>

        <input v-else-if="c.type === 'fecha'" :id="idDe(c.key)" type="date" class="form-control"
               :class="{ 'is-invalid': error(c.key) }"
               :value="valor(c.key) ?? ''" @input="set(c.key, $event.target.value)">

        <input v-else-if="c.type === 'coordenadas'" :id="idDe(c.key)" type="text" class="form-control"
               :class="{ 'is-invalid': error(c.key) }" placeholder="19.432608,-99.133209" inputmode="decimal"
               :value="valor(c.key) ?? ''" @input="set(c.key, $event.target.value)">

        <template v-else-if="c.type === 'tabla'">
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-1 cp-tabla">
              <thead><tr>
                <th v-if="!c.dynamic"></th>
                <th v-for="col in c.columns" :key="col.key" class="small">{{ col.label }}<span v-if="col.unit" class="text-muted"> ({{ col.unit }})</span></th>
                <th v-if="c.dynamic" style="width:1%"></th>
              </tr></thead>
              <tbody>
                <tr v-for="(fila, i) in filasDe(c)" :key="i">
                  <th v-if="!c.dynamic" class="small text-nowrap fw-normal">{{ etiquetaFila(c, i) }}</th>
                  <td v-for="col in c.columns" :key="col.key">
                    <select v-if="col.type === 'seleccion'" class="form-select form-select-sm"
                            :class="{ 'is-invalid': error(`${c.key}.${i}.${col.key}`) }"
                            :value="fila[col.key] ?? ''" @change="setCelda(c, i, col.key, $event.target.value || null)">
                      <option value="">—</option>
                      <option v-for="o in col.options" :key="o" :value="o">{{ o }}</option>
                    </select>
                    <div v-else-if="col.type === 'si_no'" class="form-check form-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" :aria-label="col.label"
                             :checked="fila[col.key] === true" @change="setCelda(c, i, col.key, $event.target.checked)">
                    </div>
                    <input v-else class="form-control form-control-sm"
                           :type="col.type === 'numero' ? 'number' : (col.type === 'fecha' ? 'date' : 'text')"
                           :step="col.type === 'numero' ? 'any' : null"
                           :placeholder="col.type === 'coordenadas' ? 'lat,lng' : null"
                           :class="{ 'is-invalid': error(`${c.key}.${i}.${col.key}`) }"
                           :aria-label="`${col.label} ${etiquetaFila(c, i)}`"
                           :value="fila[col.key] ?? ''" @input="setCelda(c, i, col.key, $event.target.value)">
                    <div v-if="error(`${c.key}.${i}.${col.key}`)" class="invalid-feedback d-block">{{ error(`${c.key}.${i}.${col.key}`) }}</div>
                  </td>
                  <td v-if="c.dynamic">
                    <button type="button" class="btn btn-sm btn-outline-danger" title="Quitar fila" @click="quitarFila(c, i)">
                      <i class="fa fa-times"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <button v-if="c.dynamic && filasDe(c).length < (c.max_rows || 20)" type="button"
                  class="btn btn-sm btn-outline-secondary" @click="agregarFila(c)">
            <i class="fa fa-plus me-1"></i>Agregar fila
          </button>
          <span v-if="c.dynamic" class="small text-muted ms-2">Máximo {{ c.max_rows || 20 }} filas.</span>
        </template>

        <div v-if="error(c.key)" class="invalid-feedback d-block">{{ error(c.key) }}</div>
        <div v-if="c.help" class="form-text">{{ c.help }}</div>
      </template>
    </div>
  </div>
</template>

<script>
import { formatearValor } from './camposPlantilla.js';

export default {
  name: 'CamposPlantilla',
  props: {
    // Esquema: [{key, label, type, required, when, options, unit, help, columns, rows_count, …}]
    campos: { type: Array, default: () => [] },
    // Valores {key: valor}; las tablas son arreglos de filas {columna: valor}.
    modelValue: { type: Object, default: () => ({}) },
    // Errores {key | 'tabla.fila.columna': mensaje}.
    errores: { type: Object, default: () => ({}) },
    soloLectura: { type: Boolean, default: false },
    // Texto para un campo sin valor en solo lectura ("—", "Pendiente"…).
    textoVacio: { type: String, default: '—' },
    // Prefijo de los id de los controles (dos instancias en la misma página no chocan).
    idPrefix: { type: String, default: 'cp' },
  },
  emits: ['update:modelValue'],
  methods: {
    idDe(key) { return `${this.idPrefix}_${key}`; },
    anchoDe(c) {
      return ['texto_largo', 'multi_seleccion', 'tabla'].includes(c.type) ? 'col-12' : 'col-md-6';
    },
    valor(key) { return (this.modelValue || {})[key]; },
    error(key) { return (this.errores || {})[key] || null; },
    fmt(c, v) { return formatearValor(c, v); },
    set(key, v) {
      this.$emit('update:modelValue', { ...(this.modelValue || {}), [key]: v });
    },
    alternar(c, opcion, marcado) {
      const actual = new Set(this.valor(c.key) || []);
      if (marcado) actual.add(opcion); else actual.delete(opcion);
      // En el orden de las opciones de la plantilla.
      this.set(c.key, (c.options || []).filter(o => actual.has(o)));
    },
    // Filas fijas: siempre rows_count (Conector 1..N), aunque vengan menos. Dinámicas: las que haya.
    filasDe(c) {
      const v = Array.isArray(this.valor(c.key)) ? this.valor(c.key) : [];
      if (c.dynamic) return v.map(f => f || {});
      return Array.from({ length: c.rows_count || 0 }, (_, i) => v[i] || {});
    },
    filasConDatos(c) {
      const v = Array.isArray(this.valor(c.key)) ? this.valor(c.key) : [];
      return v.map((fila, i) => ({ i, fila: fila || {} })).filter(f => Object.keys(f.fila).length);
    },
    etiquetaFila(c, i) {
      return `${c.dynamic ? 'Fila' : (c.row_label_prefix || 'Fila')} ${i + 1}`;
    },
    setCelda(c, i, col, v) {
      const filas = this.filasDe(c).map(f => ({ ...f }));
      filas[i] = { ...filas[i], [col]: v };
      this.set(c.key, filas);
    },
    agregarFila(c) { this.set(c.key, [...this.filasDe(c), {}]); },
    quitarFila(c, i) { this.set(c.key, this.filasDe(c).filter((_, j) => j !== i)); },
  },
};
</script>

<style scoped>
.cp-tabla th, .cp-tabla td { vertical-align: middle; }
.cp-tabla .form-control-sm, .cp-tabla .form-select-sm { min-width: 6rem; }
.cp-valor { white-space: pre-line; }
.cp-multi.is-invalid { border-left: 3px solid var(--bs-danger, #dc3545); padding-left: .5rem; }
</style>

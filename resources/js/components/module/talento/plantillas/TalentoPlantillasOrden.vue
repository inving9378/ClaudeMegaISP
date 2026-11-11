<template>
  <div class="talento-plantillas-orden tc-wrap" :class="{ 'tc-dark': darkMode }">
    <div class="tc-card">
      <div class="tc-cardhead d-flex align-items-center justify-content-between flex-wrap gap-2 p-3">
        <div>
          <h5 class="tc-h1 mb-0">Plantillas de orden</h5>
          <small class="text-muted">Tipos de orden de planta externa y sus plantillas: qué datos se piden al crear la orden, qué captura el técnico al cerrarla y qué materiales lleva.</small>
        </div>
        <div class="d-flex gap-2">
          <button class="tc-btn tc-btn-primary" :disabled="importando" @click="$refs.archivoImportar.click()">
            <span v-if="importando" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fa fa-file-import me-1"></i> Importar
          </button>
          <input ref="archivoImportar" type="file" accept=".json,application/json" class="d-none" aria-label="Archivo de plantilla" @change="importar">
          <button class="tc-btn tc-btn-ok" @click="abrirTipo(null)"><i class="fa fa-plus me-1"></i> Nuevo tipo</button>
        </div>
      </div>

      <div class="p-3">
        <div v-if="avisoImportar" class="alert py-2" :class="avisoImportar.ok ? 'alert-success' : 'alert-danger'">
          {{ avisoImportar.texto }}
          <ul v-if="avisoImportar.detalle.length" class="mb-0 small"><li v-for="(d, k) in avisoImportar.detalle" :key="k">{{ d }}</li></ul>
        </div>
        <div class="d-flex gap-2 mb-3">
          <input v-model="buscar" @input="buscarConPausa" type="search" class="form-control" style="max-width:320px"
                 placeholder="Buscar tipo por nombre…" aria-label="Buscar tipo">
        </div>

        <div v-if="cargando" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
        <div v-else-if="error" class="alert alert-danger">{{ error }} <button class="tc-btn tc-btn-bad ms-2" @click="cargar">Reintentar</button></div>
        <div v-else class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Tipo</th><th>Área</th><th class="text-center">Plantillas</th><th class="text-center">Puntos</th>
                <th class="text-center">Estado</th><th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!tipos.length"><td colspan="6" class="text-center text-muted py-3">No hay tipos de planta externa{{ buscar ? ' con ese nombre' : '' }}.</td></tr>
              <tr v-for="t in tipos" :key="t.id" :class="{ 'tpo-sel': tipoSel && tipoSel.id === t.id }">
                <td>
                  <div class="fw-semibold">{{ t.name }}</div>
                  <small class="text-muted">{{ t.project || 'Sin proyecto' }} · {{ t.ordenes }} {{ t.ordenes === 1 ? 'orden' : 'órdenes' }}</small>
                </td>
                <td>Planta externa</td>
                <td class="text-center">{{ t.plantillas_activas }}<span class="text-muted"> / {{ t.plantillas }}</span></td>
                <td class="text-center">{{ t.points }}</td>
                <td class="text-center">
                  <div class="form-check form-switch d-inline-block mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" :checked="t.active" :aria-label="'Activo: ' + t.name" @change="activarTipo(t, $event.target.checked)">
                  </div>
                </td>
                <td class="text-end text-nowrap">
                  <button class="tc-btn tc-btn-info me-1" @click="abrirTipo(t)">Editar tipo</button>
                  <button class="tc-btn tc-btn-primary" @click="verPlantillas(t)">Ver plantillas</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Plantillas del tipo elegido -->
    <div v-if="tipoSel" class="tc-card mt-3">
      <div class="tc-cardhead d-flex align-items-center justify-content-between flex-wrap gap-2 p-3">
        <div>
          <h6 class="mb-0">Plantillas de «{{ tipoSel.name }}»</h6>
          <small class="text-muted">Al crear una orden de este tipo se elige una plantilla activa. Editar una plantilla no cambia las órdenes ya creadas.</small>
        </div>
        <div class="d-flex gap-2">
          <button class="tc-btn tc-btn-ok" @click="abrirEditor(null)"><i class="fa fa-plus me-1"></i> Nueva plantilla</button>
        </div>
      </div>
      <div class="p-3">
        <div v-if="cargandoPlantillas" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>
        <div v-else class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr><th>Plantilla</th><th class="text-center">Campos</th><th class="text-center">Materiales</th><th class="text-center">Activa</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
              <tr v-if="!plantillas.length"><td colspan="5" class="text-center text-muted py-3">Este tipo todavía no tiene plantillas.</td></tr>
              <tr v-for="(p, i) in plantillas" :key="p.id">
                <td>
                  <div class="fw-semibold">{{ p.name }}</div>
                  <small class="text-muted">{{ p.ordenes }} {{ p.ordenes === 1 ? 'orden la usa' : 'órdenes la usan' }}</small>
                </td>
                <td class="text-center">{{ p.campos }} <small class="text-muted d-block">{{ p.campos_creacion }} al crear · {{ p.campos_cierre }} al cerrar</small></td>
                <td class="text-center">{{ p.materiales }}</td>
                <td class="text-center">
                  <div class="form-check form-switch d-inline-block mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" :checked="p.active" :aria-label="'Activa: ' + p.name" @change="activarPlantilla(p, $event.target.checked)">
                  </div>
                </td>
                <td class="text-end text-nowrap">
                  <button class="tc-btn tc-btn-info me-1" @click="abrirEditor(p)">Editar</button>
                  <button class="tc-btn tc-btn-primary me-1" @click="duplicar(p)">Duplicar</button>
                  <a class="tc-btn me-1" :href="urlExportar(p)" download data-spa-skip title="Descargar la plantilla en JSON (sin ids) para importarla en otro sistema">Exportar</a>
                  <button class="tc-btn me-1" :disabled="i === 0" title="Subir" aria-label="Subir" @click="mover(p, 'arriba')"><i class="fa fa-arrow-up"></i></button>
                  <button class="tc-btn" :disabled="i === plantillas.length - 1" title="Bajar" aria-label="Bajar" @click="mover(p, 'abajo')"><i class="fa fa-arrow-down"></i></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="avisoPlantillas" class="alert alert-danger py-2 mb-0">{{ avisoPlantillas }}</div>
      </div>
    </div>

    <!-- Alta / edición de tipo -->
    <div v-if="tipoModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content tc-wrap" :class="{ 'tc-dark': darkMode }">
          <div class="modal-header">
            <h5 class="modal-title">{{ tipoModal.id ? 'Editar tipo de orden' : 'Nuevo tipo de orden' }}</h5>
            <button type="button" class="btn-close" aria-label="Cerrar" @click="tipoModal.show = false"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label" for="tpo_nombre">Nombre <span class="text-danger">*</span></label>
              <input id="tpo_nombre" v-model="tipoModal.name" class="form-control" :class="{ 'is-invalid': tipoModal.errores.name }" maxlength="120">
              <div class="invalid-feedback">{{ tipoModal.errores.name }}</div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-sm-4">
                <label class="form-label" for="tpo_puntos">Puntos</label>
                <input id="tpo_puntos" v-model.number="tipoModal.points" type="number" min="0" max="1000" class="form-control" :class="{ 'is-invalid': tipoModal.errores.points }">
                <div class="invalid-feedback">{{ tipoModal.errores.points }}</div>
                <small class="text-muted">Los puntos alimentan compensación (#1204).</small>
              </div>
              <div class="col-sm-8">
                <label class="form-label" for="tpo_proyecto">Proyecto <span class="text-danger">*</span></label>
                <select id="tpo_proyecto" v-model="tipoModal.project_id" class="form-select tc-select" :class="{ 'is-invalid': tipoModal.errores.project_id }">
                  <option :value="null">— Seleccionar —</option>
                  <option v-for="pr in catalogos.proyectos" :key="pr.id" :value="pr.id">{{ pr.title }}</option>
                </select>
                <div class="invalid-feedback">{{ tipoModal.errores.project_id }}</div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label d-block">Evidencias obligatorias al cerrar</label>
              <div class="tpo-evid">
                <div v-for="ev in catalogos.evidencias" :key="ev.id" class="form-check">
                  <input :id="'tpo_ev_' + ev.id" class="form-check-input" type="checkbox" :value="ev.id" v-model="tipoModal.evidencias" :disabled="!ev.permitida">
                  <label class="form-check-label" :for="'tpo_ev_' + ev.id">
                    {{ ev.name }} <small v-if="!ev.permitida" class="text-muted">— no disponible: {{ ev.motivo }}</small>
                  </label>
                </div>
              </div>
              <div v-if="errorEvidencias" class="text-danger small mt-1">{{ errorEvidencias }}</div>
            </div>
            <div class="row g-3">
              <div class="col-sm-6">
                <div class="form-check form-switch">
                  <input id="tpo_activo" v-model="tipoModal.active" class="form-check-input" type="checkbox" role="switch">
                  <label class="form-check-label" for="tpo_activo">Activo (se ofrece al crear órdenes)</label>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-check form-switch">
                  <input id="tpo_cobro" class="form-check-input" type="checkbox" role="switch" :checked="false" disabled>
                  <label class="form-check-label" for="tpo_cobro">Cobrable: No</label>
                </div>
                <small class="text-muted">{{ catalogos.nota_cobro }}</small>
              </div>
            </div>
            <p class="small text-muted mt-3 mb-0">Área: planta externa · Categoría: campo (fijas en esta pantalla).</p>
            <div v-if="tipoModal.error" class="alert alert-danger py-2 mt-3 mb-0">{{ tipoModal.error }}</div>
          </div>
          <div class="modal-footer">
            <button class="tc-btn" @click="tipoModal.show = false">Cancelar</button>
            <button class="tc-btn tc-btn-ok" :disabled="tipoModal.guardando" @click="guardarTipo">
              <span v-if="tipoModal.guardando" class="spinner-border spinner-border-sm me-1"></span>Guardar
            </button>
          </div>
        </div>
      </div>
    </div>

    <plantilla-orden-editor v-if="editor.show" :tipo="tipoSel" :plantilla-id="editor.plantillaId" :catalogos="catalogos"
                            @cerrar="editor.show = false" @guardada="alGuardarPlantilla" />
  </div>
</template>

<script>
import axios from 'axios';
import { darkMode } from '../../../../hook/appConfig.js';
import PlantillaOrdenEditor from './PlantillaOrdenEditor.vue';

const API = '/talento/api/plantillas-orden';

export default {
  name: 'TalentoPlantillasOrden',
  components: { PlantillaOrdenEditor },
  setup() {
    return { darkMode };
  },
  data() {
    return {
      tipos: [], buscar: '', cargando: true, error: '', pausa: null,
      catalogos: { proyectos: [], evidencias: [], prioridades: [], nota_cobro: '' },
      tipoSel: null, plantillas: [], cargandoPlantillas: false, avisoPlantillas: '',
      tipoModal: this.tipoVacio(),
      editor: { show: false, plantillaId: null },
      importando: false, avisoImportar: null,
    };
  },
  computed: {
    errorEvidencias() {
      const e = this.tipoModal.errores;
      return Object.keys(e).filter(k => k.startsWith('evidencias')).map(k => e[k])[0] || '';
    },
  },
  async mounted() {
    await Promise.all([this.cargar(), this.cargarCatalogos()]);
  },
  methods: {
    tipoVacio() {
      return { show: false, id: null, name: '', points: 0, puntosAntes: 0, project_id: null, evidencias: [], active: true, errores: {}, error: '', guardando: false };
    },
    async cargarCatalogos() {
      try {
        const { data } = await axios.get(API + '/catalogos');
        this.catalogos = data || this.catalogos;
      } catch (e) {
        this.error = e?.response?.data?.message || 'No se pudieron cargar los catálogos.';
      }
    },
    async cargar() {
      this.cargando = true;
      this.error = '';
      try {
        const { data } = await axios.get(API + '/tipos', { params: { buscar: this.buscar || undefined } });
        this.tipos = Array.isArray(data) ? data : [];
        if (this.tipoSel) this.tipoSel = this.tipos.find(t => t.id === this.tipoSel.id) || this.tipoSel;
      } catch (e) {
        this.error = e?.response?.data?.message || 'No se pudo cargar la lista de tipos.';
      } finally {
        this.cargando = false;
      }
    },
    buscarConPausa() {
      clearTimeout(this.pausa);
      this.pausa = setTimeout(() => this.cargar(), 300);
    },
    abrirTipo(t) {
      this.tipoModal = Object.assign(this.tipoVacio(), t ? {
        id: t.id, name: t.name, points: t.points, puntosAntes: t.points, project_id: t.project_id,
        evidencias: [...(t.evidencias || [])], active: t.active,
      } : {}, { show: true });
    },
    async guardarTipo() {
      const m = this.tipoModal;
      if (Number(m.points) !== Number(m.puntosAntes)
          && !window.confirm('Los puntos alimentan compensación (#1204). ¿Guardar el cambio de puntos?')) {
        return;
      }
      m.guardando = true;
      m.errores = {};
      m.error = '';
      const body = { name: m.name, points: Number(m.points) || 0, project_id: m.project_id, evidencias: m.evidencias, active: m.active };
      try {
        if (m.id) await axios.put(`${API}/tipos/${m.id}`, body);
        else await axios.post(API + '/tipos', body);
        m.show = false;
        await this.cargar();
      } catch (e) {
        const d = e?.response?.data || {};
        m.errores = d.errors || {};
        m.error = d.error || d.message || 'No se pudo guardar el tipo.';
      } finally {
        m.guardando = false;
      }
    },
    async activarTipo(t, activo) {
      try {
        const { data } = await axios.patch(`${API}/tipos/${t.id}/activo`, { active: activo });
        Object.assign(t, data);
      } catch (e) {
        this.error = e?.response?.data?.message || 'No se pudo cambiar el estado del tipo.';
        await this.cargar();
      }
    },
    async verPlantillas(t) {
      this.tipoSel = t;
      await this.cargarPlantillas();
      this.$nextTick(() => this.$el.querySelector('.tc-card.mt-3')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    },
    async cargarPlantillas() {
      if (!this.tipoSel) return;
      this.cargandoPlantillas = true;
      this.avisoPlantillas = '';
      try {
        const { data } = await axios.get(`${API}/tipos/${this.tipoSel.id}/plantillas`);
        this.plantillas = Array.isArray(data) ? data : [];
      } catch (e) {
        this.avisoPlantillas = e?.response?.data?.message || 'No se pudieron cargar las plantillas.';
      } finally {
        this.cargandoPlantillas = false;
      }
    },
    async activarPlantilla(p, activa) {
      try {
        const { data } = await axios.patch(`${API}/plantillas/${p.id}/activo`, { active: activa });
        Object.assign(p, data);
        await this.cargar();
      } catch (e) {
        this.avisoPlantillas = e?.response?.data?.message || 'No se pudo cambiar el estado de la plantilla.';
        await this.cargarPlantillas();
      }
    },
    async duplicar(p) {
      try {
        await axios.post(`${API}/plantillas/${p.id}/duplicar`);
        await Promise.all([this.cargarPlantillas(), this.cargar()]);
      } catch (e) {
        this.avisoPlantillas = e?.response?.data?.message || 'No se pudo duplicar la plantilla.';
      }
    },
    async mover(p, direccion) {
      try {
        const { data } = await axios.post(`${API}/plantillas/${p.id}/mover`, { direccion });
        this.plantillas = Array.isArray(data) ? data : this.plantillas;
      } catch (e) {
        this.avisoPlantillas = e?.response?.data?.message || 'No se pudo mover la plantilla.';
      }
    },
    urlExportar(p) {
      return `${API}/plantillas/${p.id}/exportar`;
    },
    async importar(ev) {
      const archivo = ev.target.files && ev.target.files[0];
      ev.target.value = '';
      if (!archivo) return;
      this.importando = true;
      this.avisoImportar = null;
      try {
        const texto = await archivo.text();
        let contenido;
        try { contenido = JSON.parse(texto); } catch (e) { throw { mensaje: 'El archivo no es un JSON válido.' }; }
        const { data } = await axios.post(API + '/importar', { archivo: contenido });
        const p = data.plantilla || {};
        this.avisoImportar = { ok: true, texto: `Plantilla «${p.name}» importada en «${p.tipo}», inactiva: revísala en el editor y actívala.`, detalle: data.notas || [] };
        await this.cargar();
        const tipo = this.tipos.find(t => t.id === p.type_id);
        if (tipo) await this.verPlantillas(tipo);
      } catch (e) {
        const d = e?.response?.data || {};
        this.avisoImportar = { ok: false, texto: e?.mensaje || d.error || d.message || 'No se pudo importar la plantilla.', detalle: Object.values(d.errors || {}).slice(0, 8) };
      } finally {
        this.importando = false;
      }
    },
    abrirEditor(p) {
      this.editor = { show: true, plantillaId: p ? p.id : null };
    },
    async alGuardarPlantilla() {
      this.editor.show = false;
      await Promise.all([this.cargarPlantillas(), this.cargar()]);
    },
  },
};
</script>

<style scoped>
.tpo-evid { columns: 2; column-gap: 1.5rem; }
@media (max-width: 576px) { .tpo-evid { columns: 1; } }
.tpo-sel td { background: var(--tc-row-sel, rgba(25, 135, 84, .08)); }
</style>

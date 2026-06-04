<template>
  <div class="talento-sitios">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h5 class="mb-0"><i class="fa fa-map-pin me-2 text-primary"></i>Ubicaciones de Checada</h5>
      <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
          <label class="form-label mb-0 small fw-semibold">Radio global:</label>
          <input v-model.number="defaultRadius" type="number" min="10" max="5000" step="10"
                 class="form-control form-control-sm" style="width:90px;">
          <span class="small text-muted">m</span>
          <button @click="saveDefaultRadius" class="btn btn-sm btn-outline-primary" :disabled="savingRadius">
            <span v-if="savingRadius"><span class="spinner-border spinner-border-sm"></span></span>
            <span v-else>Guardar</span>
          </button>
        </div>
        <button @click="openCreate" class="btn btn-primary btn-sm">
          <i class="fa fa-plus me-1"></i> Nueva ubicación
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="row g-2 mb-3">
      <div class="col-md-3">
        <input v-model="filters.search" @input="debounceLoad" type="text"
               class="form-control form-control-sm" placeholder="Buscar…">
      </div>
      <div class="col-md-2">
        <select v-model="filters.type" @change="load" class="form-select form-select-sm">
          <option value="">Todos los tipos</option>
          <option value="bodega">Bodega</option>
          <option value="oficina">Oficina</option>
          <option value="predio">Predio</option>
          <option value="otro">Otro</option>
        </select>
      </div>
    </div>

    <!-- Tabla -->
    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
    <div v-else class="table-responsive mb-3">
      <table class="table table-hover table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>Nombre</th>
            <th>Tipo</th>
            <th class="text-center">Radio</th>
            <th>Coordenadas</th>
            <th class="text-center">Activa</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in sites" :key="s.id">
            <td class="fw-semibold">{{ s.name }}</td>
            <td><span class="badge bg-light text-dark">{{ typeLabel(s.type) }}</span></td>
            <td class="text-center">{{ s.radius_m }}m</td>
            <td class="font-monospace small">{{ round(s.latitude, 5) }}, {{ round(s.longitude, 5) }}</td>
            <td class="text-center">
              <span class="badge" :class="s.active ? 'bg-success' : 'bg-secondary'">{{ s.active ? 'Sí' : 'No' }}</span>
            </td>
            <td>
              <button @click="openEdit(s)" class="btn btn-xs btn-outline-primary me-1">Editar</button>
              <button @click="deleteSite(s)" class="btn btn-xs btn-outline-danger">Borrar</button>
            </td>
          </tr>
          <tr v-if="!sites.length">
            <td colspan="6" class="text-center text-muted py-4">No hay ubicaciones configuradas. Crea la primera.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal crear/editar con mapa -->
    <div v-if="modal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ modal.id ? 'Editar ubicación' : 'Nueva ubicación de checada' }}</h5>
            <button @click="closeModal" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input v-model="modal.name" type="text" class="form-control">
                  </div>
                  <div class="col-6">
                    <label class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select v-model="modal.type" class="form-select">
                      <option value="bodega">Bodega</option>
                      <option value="oficina">Oficina</option>
                      <option value="predio">Predio</option>
                      <option value="otro">Otro</option>
                    </select>
                  </div>
                  <div class="col-6">
                    <label class="form-label">Radio (m) <span class="text-danger">*</span></label>
                    <input v-model.number="modal.radius_m" type="number" min="10" max="5000" class="form-control">
                  </div>
                  <div class="col-6">
                    <label class="form-label">Latitud</label>
                    <input v-model.number="modal.latitude" type="number" step="0.0000001" class="form-control" @change="updateMarker">
                  </div>
                  <div class="col-6">
                    <label class="form-label">Longitud</label>
                    <input v-model.number="modal.longitude" type="number" step="0.0000001" class="form-control" @change="updateMarker">
                  </div>
                  <div class="col-12">
                    <div class="form-check">
                      <input v-model="modal.active" type="checkbox" class="form-check-input" id="siteActive">
                      <label class="form-check-label" for="siteActive">Activa</label>
                    </div>
                  </div>
                  <div class="col-12 text-muted small">
                    <i class="fa fa-info-circle me-1"></i>Haz clic en el mapa para fijar las coordenadas.
                  </div>
                </div>
              </div>
              <div class="col-md-8">
                <div v-if="modal.mapReady" ref="siteMapEl" style="height:380px;border-radius:8px;"></div>
                <div v-else class="bg-light d-flex align-items-center justify-content-center" style="height:380px;border-radius:8px;">
                  <div class="spinner-border text-primary"></div>
                </div>
              </div>
              <div v-if="modal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ modal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="closeModal" class="btn btn-secondary" :disabled="modal.saving">Cancelar</button>
            <button @click="saveSite" class="btn btn-primary" :disabled="modal.saving">
              <span v-if="modal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
              <span v-else>Guardar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import L from 'leaflet';

export default {
  name: 'TalentoSitios',
  data() {
    return {
      sites: [],
      loading: true,
      filters: { search: '', type: '' },
      defaultRadius: 300,
      savingRadius: false,
      modal: { show: false, id: null, name: '', type: 'oficina', radius_m: 200,
               latitude: 19.4326, longitude: -99.1332, active: true,
               mapReady: false, saving: false, error: '' },
      siteMap: null,
      siteMarker: null,
      siteCircle: null,
      searchTimeout: null,
    };
  },
  mounted() { this.load(); this.loadDefaultRadius(); },
  methods: {
    debounceLoad() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.load(), 350);
    },
    async load() {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/sitios', { params: this.filters });
        this.sites = data ?? [];
      } finally { this.loading = false; }
    },
    async loadDefaultRadius() {
      try {
        const { data } = await axios.get('/talento/api/sitios/config/radio');
        this.defaultRadius = data?.default_radius_m ?? 300;
      } catch {}
    },
    async saveDefaultRadius() {
      this.savingRadius = true;
      try {
        await axios.put('/talento/api/sitios/config/radio', { radius_m: this.defaultRadius });
      } finally { this.savingRadius = false; }
    },
    openCreate() {
      this.modal = { show: true, id: null, name: '', type: 'oficina', radius_m: 200,
                     latitude: 19.4326, longitude: -99.1332, active: true,
                     mapReady: false, saving: false, error: '' };
      this.initSiteMap();
    },
    openEdit(s) {
      this.modal = { show: true, id: s.id, name: s.name, type: s.type, radius_m: s.radius_m,
                     latitude: parseFloat(s.latitude), longitude: parseFloat(s.longitude),
                     active: s.active, mapReady: false, saving: false, error: '' };
      this.initSiteMap();
    },
    closeModal() {
      if (this.siteMap) { this.siteMap.remove(); this.siteMap = null; this.siteMarker = null; this.siteCircle = null; }
      this.modal.show = false;
    },
    async initSiteMap() {
      this.modal.mapReady = true;
      await this.$nextTick();
      if (!this.$refs.siteMapEl) return;
      if (this.siteMap) { this.siteMap.remove(); }
      const lat = this.modal.latitude || 19.4326;
      const lng = this.modal.longitude || -99.1332;
      this.siteMap = L.map(this.$refs.siteMapEl).setView([lat, lng], 16);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.siteMap);
      this.siteMarker = L.marker([lat, lng], { draggable: true }).addTo(this.siteMap);
      this.siteCircle = L.circle([lat, lng], { radius: this.modal.radius_m, color: '#3b82f6', fillOpacity: 0.15 }).addTo(this.siteMap);
      this.siteMap.on('click', (e) => {
        const { lat, lng } = e.latlng;
        this.modal.latitude  = Math.round(lat * 1e7) / 1e7;
        this.modal.longitude = Math.round(lng * 1e7) / 1e7;
        this.updateMarker();
      });
      this.siteMarker.on('dragend', (e) => {
        const { lat, lng } = e.target.getLatLng();
        this.modal.latitude  = Math.round(lat * 1e7) / 1e7;
        this.modal.longitude = Math.round(lng * 1e7) / 1e7;
        this.updateCircle();
      });
    },
    updateMarker() {
      if (!this.siteMarker) return;
      const ll = [this.modal.latitude, this.modal.longitude];
      this.siteMarker.setLatLng(ll);
      this.updateCircle();
      this.siteMap?.panTo(ll);
    },
    updateCircle() {
      if (!this.siteCircle) return;
      this.siteCircle.setLatLng([this.modal.latitude, this.modal.longitude]);
      this.siteCircle.setRadius(this.modal.radius_m || 200);
    },
    async saveSite() {
      this.modal.error = '';
      if (!this.modal.name) { this.modal.error = 'El nombre es requerido.'; return; }
      if (!this.modal.latitude || !this.modal.longitude) { this.modal.error = 'Fija las coordenadas en el mapa.'; return; }
      this.modal.saving = true;
      try {
        const payload = {
          name: this.modal.name, type: this.modal.type,
          latitude: this.modal.latitude, longitude: this.modal.longitude,
          radius_m: this.modal.radius_m, active: this.modal.active,
        };
        if (this.modal.id) {
          await axios.put(`/talento/api/sitios/${this.modal.id}`, payload);
        } else {
          await axios.post('/talento/api/sitios', payload);
        }
        this.closeModal();
        this.load();
      } catch (e) {
        this.modal.error = e.response?.data?.message ?? 'Error al guardar.';
      } finally { this.modal.saving = false; }
    },
    async deleteSite(s) {
      if (!confirm(`¿Eliminar "${s.name}"?`)) return;
      await axios.delete(`/talento/api/sitios/${s.id}`);
      this.load();
    },
    typeLabel(t) { return { bodega: 'Bodega', oficina: 'Oficina', predio: 'Predio', otro: 'Otro' }[t] ?? t; },
    round(n, d) { return Number(n ?? 0).toFixed(d); },
  },
  watch: {
    'modal.radius_m'() { this.updateCircle(); },
  },
};
</script>

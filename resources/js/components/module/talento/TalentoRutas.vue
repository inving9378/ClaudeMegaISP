<template>
  <div class="talento-rutas">

    <!-- LIST VIEW -->
    <template v-if="!selectedRouteId">
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h5 class="mb-0"><i class="fa fa-route me-2 text-primary"></i>Rutas de Planta Interna</h5>
        <button @click="openCreate" class="btn btn-sm btn-primary"><i class="fa fa-plus me-1"></i>Nueva ruta</button>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-2">
          <input v-model="filters.date" @change="load" type="date" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <select v-model="filters.status" @change="load" class="form-select form-select-sm">
            <option value="">Todos</option>
            <option value="draft">Borrador</option>
            <option value="active">Activa</option>
            <option value="completed">Completada</option>
          </select>
        </div>
      </div>

      <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr><th>Técnico</th><th>Fecha</th><th>Paradas</th><th>Estado</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="r in routes" :key="r.id">
              <td class="fw-semibold small">{{ r.colaborador?.user?.name }}</td>
              <td class="small">{{ fmtDate(r.date) }}</td>
              <td class="text-center">{{ r.stops_count ?? '—' }}</td>
              <td><span class="badge" :class="statusColor(r.status)">{{ statusLabel(r.status) }}</span></td>
              <td class="d-flex gap-1">
                <button @click="openRoute(r.id)" class="btn btn-xs btn-primary">Ver ruta</button>
                <button v-if="r.status === 'draft'" @click="activateRoute(r.id)" class="btn btn-xs btn-outline-success">Activar</button>
              </td>
            </tr>
            <tr v-if="!routes.length">
              <td colspan="5" class="text-center text-muted py-4">Sin rutas para esta fecha.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- ROUTE DETAIL VIEW -->
    <template v-else-if="routeData">
      <div class="d-flex align-items-center gap-3 mb-3">
        <button @click="selectedRouteId=null; routeData=null" class="btn btn-sm btn-outline-secondary">
          <i class="fa fa-arrow-left me-1"></i>Volver
        </button>
        <h5 class="mb-0">
          {{ routeData.colaborador?.user?.name }} — {{ fmtDate(routeData.date) }}
          <span class="ms-2 badge" :class="statusColor(routeData.status)">{{ statusLabel(routeData.status) }}</span>
        </h5>
        <button v-if="routeData.status !== 'draft'" @click="analyzeDeviations" class="ms-auto btn btn-sm btn-outline-warning" :disabled="analyzing">
          <span v-if="analyzing"><span class="spinner-border spinner-border-sm me-1"></span></span>
          <span v-else><i class="fa fa-search-location me-1"></i>Analizar desvíos</span>
        </button>
      </div>

      <div class="row g-0" style="height: calc(100vh - 200px); min-height:500px;">
        <!-- Mapa -->
        <div class="col-md-8 col-xl-9">
          <div v-if="mapReady" ref="mapEl" style="height:100%;border-radius:8px 0 0 8px;"></div>
          <div v-else class="bg-light d-flex align-items-center justify-content-center h-100 rounded">
            <div class="spinner-border text-primary"></div>
          </div>
        </div>

        <!-- Panel lateral -->
        <div class="col-md-4 col-xl-3 border-start" style="overflow-y:auto;height:100%;">
          <div class="p-3">
            <!-- Paradas -->
            <h6 class="text-uppercase text-muted small mb-2">Paradas ({{ routeData.stops?.length ?? 0 }})</h6>
            <div v-for="(stop, i) in routeData.stops" :key="stop.id"
                 class="d-flex align-items-start gap-2 mb-3">
              <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:24px;height:24px;font-size:12px;">{{ stop.sequence }}</div>
              <div class="small">
                <div class="fw-semibold">OT #{{ stop.work_order_id }}</div>
                <div v-if="stop.work_order?.type" class="text-muted">{{ stop.work_order?.type }}</div>
                <div v-if="stop.eta" class="text-muted">ETA: {{ fmtTime(stop.eta) }}</div>
                <div v-if="stop.arrived_at" class="text-success">
                  <i class="fa fa-check-circle me-1"></i>{{ fmtTime(stop.arrived_at) }}
                </div>
              </div>
            </div>

            <!-- Desvíos -->
            <div v-if="routeData.deviations?.length" class="mt-3">
              <h6 class="text-uppercase text-muted small mb-2 text-warning">
                ⚠️ Desvíos detectados ({{ routeData.deviations.length }})
              </h6>
              <div v-for="d in routeData.deviations" :key="d.id"
                   @click="focusDeviation(d)"
                   class="card bg-warning-subtle border-warning border mb-2 cursor-pointer p-2 small">
                <div class="fw-semibold">{{ d.deviation_m }}m · {{ d.sustained_minutes }} min</div>
                <div class="text-muted">{{ fmtdt(d.detected_at) }}</div>
              </div>
            </div>
            <div v-else-if="routeData.deviations" class="mt-3 small text-success">
              <i class="fa fa-check-circle me-1"></i>Sin desvíos detectados.
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Modal nueva ruta -->
    <div v-if="createModal.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:9999">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Nueva ruta del día</h5>
            <button @click="createModal.show=false" type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Colaborador <span class="text-danger">*</span></label>
                <input v-model="createModal.colSearch" @input="debounceColSearch" type="text"
                       class="form-control" placeholder="Buscar…">
                <ul v-if="createModal.suggestions.length" class="list-group mt-1 position-absolute shadow" style="z-index:10001;max-height:160px;overflow-y:auto">
                  <li v-for="c in createModal.suggestions" :key="c.id" @click="selectCol(c)"
                      class="list-group-item list-group-item-action small cursor-pointer">{{ c.user?.name }}</li>
                </ul>
                <div v-if="createModal.colaborador_id" class="mt-1 small text-success">
                  <i class="fa fa-check-circle me-1"></i>{{ createModal.colaborador_name }}
                </div>
              </div>
              <div class="col-md-3">
                <label class="form-label">Fecha</label>
                <input v-model="createModal.date" type="date" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label">Órdenes del día <span class="text-danger">*</span> (en orden de visita)</label>
                <input v-model="createModal.woSearch" @input="debounceWoSearch" type="text"
                       class="form-control mb-2" placeholder="Buscar OT por técnico…">
                <ul v-if="createModal.woSuggestions.length" class="list-group mb-2" style="max-height:150px;overflow-y:auto">
                  <li v-for="wo in createModal.woSuggestions" :key="wo.id"
                      @click="addStop(wo)"
                      class="list-group-item list-group-item-action d-flex justify-content-between small cursor-pointer">
                    <span>OT #{{ wo.id }} — {{ wo.type?.name }}</span>
                    <span class="badge bg-light text-dark">{{ wo.latitude ? '📍' : '—' }}</span>
                  </li>
                </ul>
                <div v-for="(stop, i) in createModal.stops" :key="i"
                     class="d-flex align-items-center gap-2 mb-1 small">
                  <span class="badge bg-primary">{{ i+1 }}</span>
                  <span>OT #{{ stop.id }} — {{ stop.type?.name }}</span>
                  <button @click="createModal.stops.splice(i,1)" class="btn btn-xs btn-outline-danger ms-auto">✕</button>
                </div>
              </div>
              <div v-if="createModal.error" class="col-12">
                <div class="alert alert-danger py-2 small mb-0">{{ createModal.error }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="createModal.show=false" class="btn btn-secondary" :disabled="createModal.saving">Cancelar</button>
            <button @click="saveRoute" class="btn btn-primary" :disabled="createModal.saving">
              <span v-if="createModal.saving"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
              <span v-else>Crear ruta</span>
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
  name: 'TalentoRutas',
  data() {
    const today = new Date().toISOString().substring(0, 10);
    return {
      routes: [],
      loading: true,
      filters: { date: today, status: '' },
      selectedRouteId: null,
      routeData: null,
      mapReady: false,
      map: null,
      analyzing: false,
      createModal: {
        show: false, colaborador_id: null, colaborador_name: '', colSearch: '',
        suggestions: [], date: today, stops: [], woSearch: '', woSuggestions: '',
        saving: false, error: '',
      },
      colSearchTimeout: null,
      woSearchTimeout: null,
    };
  },
  mounted() { this.load(); },
  beforeUnmount() { if (this.map) { this.map.remove(); this.map = null; } },
  methods: {
    async load() {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/rutas', { params: this.filters });
        this.routes = data?.data ?? [];
      } finally { this.loading = false; }
    },
    async openRoute(id) {
      this.selectedRouteId = id;
      const { data } = await axios.get(`/talento/api/rutas/${id}`);
      this.routeData = data;
      this.mapReady = true;
      await this.$nextTick();
      this.buildMap();
    },
    buildMap() {
      if (!this.$refs.mapEl) return;
      if (this.map) { this.map.remove(); }
      this.map = L.map(this.$refs.mapEl).setView([19.4326, -99.1332], 12);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.map);

      const bounds = [];

      // Expected route: dashed line through stop coordinates
      const stopCoords = (this.routeData?.stops ?? [])
        .filter(s => s.work_order?.latitude && s.work_order?.longitude)
        .map(s => [parseFloat(s.work_order.latitude), parseFloat(s.work_order.longitude)]);

      if (stopCoords.length > 1) {
        L.polyline(stopCoords, { color: '#3b82f6', weight: 3, dashArray: '8 6', opacity: 0.7 }).addTo(this.map);
        stopCoords.forEach(c => bounds.push(c));
      }

      // Number markers for stops
      (this.routeData?.stops ?? []).forEach((stop, i) => {
        if (!stop.work_order?.latitude) return;
        const ll = [parseFloat(stop.work_order.latitude), parseFloat(stop.work_order.longitude)];
        L.marker(ll, {
          icon: L.divIcon({ className: '', html: `<div style="background:#3b82f6;color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;">${i+1}</div>`, iconSize: [22, 22], iconAnchor: [11, 11] })
        }).bindPopup(`Parada ${i+1}: OT #${stop.work_order_id}`).addTo(this.map);
      });

      // Actual route: solid red polyline from pings
      const pingCoords = (this.routeData?.pings ?? [])
        .map(p => [parseFloat(p.latitude), parseFloat(p.longitude)])
        .filter(([a, b]) => a && b);

      if (pingCoords.length > 1) {
        L.polyline(pingCoords, { color: '#ef4444', weight: 2, opacity: 0.8 }).addTo(this.map);
        pingCoords.forEach(c => bounds.push(c));
      }

      // Deviation markers
      (this.routeData?.deviations ?? []).forEach(d => {
        const ll = [parseFloat(d.detected_lat), parseFloat(d.detected_lng)];
        L.circleMarker(ll, { radius: 10, color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 0.8 })
          .bindPopup(`⚠️ Desvío: ${d.deviation_m}m · ${d.sustained_minutes} min`)
          .addTo(this.map);
        bounds.push(ll);
      });

      if (bounds.length > 0) {
        this.map.fitBounds(bounds, { padding: [30, 30] });
      }
    },
    focusDeviation(d) {
      if (!this.map) return;
      this.map.panTo([parseFloat(d.detected_lat), parseFloat(d.detected_lng)]);
    },
    async activateRoute(id) {
      await axios.post(`/talento/api/rutas/${id}/activar`);
      this.load();
    },
    async analyzeDeviations() {
      this.analyzing = true;
      try {
        await axios.post(`/talento/api/rutas/${this.selectedRouteId}/analizar-desvios`);
        await this.openRoute(this.selectedRouteId);
      } finally { this.analyzing = false; }
    },
    openCreate() {
      this.createModal = {
        show: true, colaborador_id: null, colaborador_name: '', colSearch: '',
        suggestions: [], date: this.filters.date, stops: [], woSearch: '', woSuggestions: [],
        saving: false, error: '',
      };
    },
    debounceColSearch() {
      clearTimeout(this.colSearchTimeout);
      this.colSearchTimeout = setTimeout(async () => {
        if (!this.createModal.colSearch) return;
        const { data } = await axios.get('/talento/api/colaboradores', { params: { search: this.createModal.colSearch, per_page: 10 } });
        this.createModal.suggestions = data?.data ?? [];
      }, 300);
    },
    selectCol(c) {
      this.createModal.colaborador_id = c.id;
      this.createModal.colaborador_name = c.user?.name;
      this.createModal.colSearch = c.user?.name;
      this.createModal.suggestions = [];
    },
    debounceWoSearch() {
      clearTimeout(this.woSearchTimeout);
      this.woSearchTimeout = setTimeout(async () => {
        if (!this.createModal.woSearch) return;
        const { data } = await axios.get('/talento/api/ordenes', {
          params: { search: this.createModal.woSearch, status: 'pending', per_page: 10 }
        });
        this.createModal.woSuggestions = data?.data ?? [];
      }, 300);
    },
    addStop(wo) {
      if (this.createModal.stops.find(s => s.id === wo.id)) return;
      this.createModal.stops.push(wo);
      this.createModal.woSearch = '';
      this.createModal.woSuggestions = [];
    },
    async saveRoute() {
      this.createModal.error = '';
      if (!this.createModal.colaborador_id) { this.createModal.error = 'Selecciona un colaborador.'; return; }
      if (!this.createModal.stops.length) { this.createModal.error = 'Agrega al menos una parada.'; return; }
      this.createModal.saving = true;
      try {
        await axios.post('/talento/api/rutas', {
          colaborador_id: this.createModal.colaborador_id,
          date: this.createModal.date,
          work_order_ids: this.createModal.stops.map(s => s.id),
        });
        this.createModal.show = false;
        this.load();
      } catch (e) {
        this.createModal.error = e.response?.data?.message ?? 'Error al crear ruta.';
      } finally { this.createModal.saving = false; }
    },
    statusColor(s) { return { draft: 'bg-secondary', active: 'bg-primary', completed: 'bg-success' }[s] ?? 'bg-light'; },
    statusLabel(s) { return { draft: 'Borrador', active: 'Activa', completed: 'Completada' }[s] ?? s; },
    fmtDate(d) {
      if (!d) return '—';
      return new Date(d + 'T12:00:00').toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' });
    },
    fmtTime(d) {
      if (!d) return '—';
      return new Date(d).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    },
    fmtdt(d) {
      if (!d) return '—';
      return new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    },
  },
};
</script>

<template>
  <div class="talento-mapa-vivo">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0"><i class="fa fa-map-marked-alt me-2 text-primary"></i>Ubicación en Vivo</h5>
      <div class="d-flex align-items-center gap-3">
        <span class="badge bg-primary">{{ checados.length }} checado{{ checados.length !== 1 ? 's' : '' }}</span>
        <button @click="refresh" :disabled="loading" class="btn btn-sm btn-outline-secondary">
          <i class="fa fa-sync-alt" :class="{'fa-spin': loading}"></i> Actualizar
        </button>
        <small class="text-muted">Auto-refresh 30s</small>
      </div>
    </div>

    <div class="row g-0" style="height: calc(100vh - 200px); min-height: 500px;">

      <!-- Mapa -->
      <div class="col-md-8 col-xl-9">
        <div v-if="mapReady" ref="mapEl" style="height:100%;width:100%;border-radius:8px 0 0 8px;"></div>
        <div v-else class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
          <div class="spinner-border text-primary"></div>
        </div>
      </div>

      <!-- Panel lateral -->
      <div class="col-md-4 col-xl-3 border-start" style="overflow-y:auto;height:100%;">
        <div class="p-3">
          <h6 class="text-uppercase text-muted small mb-3">Colaboradores checados</h6>
          <div v-if="!checados.length" class="text-muted small text-center py-4">
            <i class="fa fa-satellite-dish fa-2x mb-2 d-block text-muted"></i>
            Sin colaboradores checados ahora mismo.
          </div>
          <div v-for="c in checados" :key="c.colaborador_id"
               @click="focusColaborador(c)"
               :class="['card border-0 shadow-sm mb-2 cursor-pointer', selected?.colaborador_id === c.colaborador_id ? 'border-primary border' : '']"
               style="transition: box-shadow 0.15s;">
            <div class="card-body py-2 px-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="fw-semibold small">{{ c.name }}</div>
                <span v-if="c.flagged" class="badge bg-warning text-dark"><i class="fa fa-flag"></i></span>
              </div>
              <div class="small text-muted">
                <i class="fa fa-sign-in-alt me-1"></i>{{ fmtTime(c.check_in_at) }}
                <span v-if="c.expected_end_at" class="ms-2">
                  <i class="fa fa-clock me-1"></i>{{ fmtTime(c.expected_end_at) }}
                </span>
              </div>
              <div v-if="c.last_ping" class="small text-muted mt-1">
                <i class="fa fa-location-arrow me-1"></i>Último ping: {{ timeAgo(c.last_ping.recorded_at) }}
                <span v-if="c.last_ping.battery != null" class="ms-2">
                  <i class="fa fa-battery-half me-1"></i>{{ c.last_ping.battery }}%
                </span>
              </div>
              <div v-else class="small text-warning mt-1"><i class="fa fa-exclamation-circle me-1"></i>Sin pings aún</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import L from 'leaflet';

export default {
  name: 'TalentoMapaVivo',
  data() {
    return {
      checados: [],
      selected: null,
      loading: true,
      mapReady: false,
      map: null,
      markers: {},
      polylines: {},
      routeData: {},
      pollTimer: null,
    };
  },
  async mounted() {
    this.mapReady = true;
    await this.$nextTick();
    this.initMap();
    await this.refresh();
    this.pollTimer = setInterval(() => this.refresh(), 30000);
  },
  beforeUnmount() {
    clearInterval(this.pollTimer);
    if (this.map) { this.map.remove(); this.map = null; }
  },
  methods: {
    initMap() {
      if (!this.$refs.mapEl) return;
      this.map = L.map(this.$refs.mapEl, { zoomControl: true }).setView([19.4326, -99.1332], 12);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors', maxZoom: 19,
      }).addTo(this.map);
    },
    async refresh() {
      this.loading = true;
      try {
        const { data } = await axios.get('/talento/api/ubicacion/en-vivo');
        this.checados = data ?? [];
        this.updateMap();
      } catch (e) {
        console.error('Error refreshing live locations', e);
      } finally { this.loading = false; }
    },
    async focusColaborador(c) {
      this.selected = c;
      // Load full route
      try {
        const { data } = await axios.get(`/talento/api/ubicacion/${c.colaborador_id}/ruta`);
        this.routeData[c.colaborador_id] = data;
        this.drawRoute(c.colaborador_id, data);
      } catch {}
    },
    updateMap() {
      if (!this.map) return;
      const ids = new Set(this.checados.map(c => c.colaborador_id));

      // Remove stale markers
      Object.keys(this.markers).forEach(id => {
        if (!ids.has(parseInt(id))) {
          this.map.removeLayer(this.markers[id]);
          delete this.markers[id];
        }
      });

      this.checados.forEach(c => {
        if (!c.last_ping) return;
        const lat = parseFloat(c.last_ping.latitude);
        const lng = parseFloat(c.last_ping.longitude);
        if (!lat || !lng) return;

        const color = c.flagged ? '#f59e0b' : '#3b82f6';
        const marker = L.circleMarker([lat, lng], {
          radius: 9, color, fillColor: color, fillOpacity: 0.85, weight: 2,
        }).bindPopup(`<b>${c.name}</b><br>Entrada: ${this.fmtTime(c.check_in_at)}<br>Últ ping: ${this.timeAgo(c.last_ping.recorded_at)}`);

        if (this.markers[c.colaborador_id]) {
          this.markers[c.colaborador_id].setLatLng([lat, lng]);
        } else {
          marker.addTo(this.map);
          this.markers[c.colaborador_id] = marker;
        }
      });
    },
    drawRoute(colaboradorId, data) {
      if (!this.map) return;
      // Remove old polyline
      if (this.polylines[colaboradorId]) {
        this.map.removeLayer(this.polylines[colaboradorId]);
      }
      const pings = data?.pings ?? [];
      if (pings.length < 2) return;

      const coords = pings.map(p => [parseFloat(p.latitude), parseFloat(p.longitude)]).filter(([a, b]) => a && b);
      const poly = L.polyline(coords, { color: '#3b82f6', weight: 3, opacity: 0.7 }).addTo(this.map);
      this.polylines[colaboradorId] = poly;

      // Add vehicle position if available
      if (data?.vehicle_position) {
        const vp = data.vehicle_position;
        L.circleMarker([vp.lat, vp.lng], {
          radius: 8, color: '#22c55e', fillColor: '#22c55e', fillOpacity: 0.9, weight: 2,
        }).bindPopup(`Vehículo asignado<br>${this.timeAgo(vp.recorded_at)}`).addTo(this.map);
      }

      // Fit to route
      if (coords.length) this.map.fitBounds(poly.getBounds(), { padding: [30, 30] });
    },
    fmtTime(d) {
      if (!d) return '—';
      return new Date(d).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    },
    timeAgo(d) {
      if (!d) return '—';
      const diff = Math.round((Date.now() - new Date(d).getTime()) / 60000);
      if (diff < 1) return 'ahora';
      if (diff < 60) return `hace ${diff} min`;
      return `hace ${Math.round(diff / 60)}h`;
    },
  },
};
</script>

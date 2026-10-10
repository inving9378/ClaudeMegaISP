<template>
  <div v-if="loading" class="text-center py-4"><div class="spinner-border text-primary"></div></div>

  <div v-else-if="data && data.es_tecnico">
    <h6 class="text-muted small text-uppercase mb-3 mt-4">Mi progreso</h6>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="h4 mb-0 fw-bold">{{ data.trabajos_asignados }}</div>
            <div class="small text-muted">Trabajos asignados</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="h4 mb-0 fw-bold">{{ data.completados }}</div>
            <div class="small text-muted">Completados</div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-6">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div v-if="data.calidad_pct !== null" class="h4 mb-0 fw-bold">{{ data.calidad_pct }}%</div>
            <div v-else class="small text-muted">Aún sin datos suficientes para calcular el % de calidad.</div>
            <div v-if="data.calidad_pct !== null" class="small text-muted">Sin incidencias (período)</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold small">Calidad de servicio</div>
          <div class="card-body">
            <canvas ref="donutChart" height="160"></canvas>
            <div v-if="!hasCalidadData" class="text-center text-muted small mt-2">Sin tareas completadas todavía.</div>
          </div>
        </div>
      </div>
      <div class="col-md-7">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold small">Garantías — últimos {{ data.period_days }} días</div>
          <div class="card-body">
            <canvas ref="lineChart" height="140"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Mapa "a dónde debo ir hoy" (Fase 4, solo técnico de track externa) -->
    <div v-if="data.es_externo" class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold small">Dónde debo ir hoy</div>
      <div class="card-body">
        <div ref="mapaEl" style="height:320px;border-radius:8px;overflow:hidden"></div>
        <div v-if="data.mapa && !data.mapa.puntos.length" class="small text-muted mt-2">
          No tiene trabajos pendientes con dirección registrada en este momento.
        </div>
        <div v-if="data.mapa && data.mapa.sin_coordenadas" class="small text-muted mt-1">
          {{ data.mapa.sin_coordenadas }} trabajo(s) pendiente(s) sin ubicación registrada del cliente.
        </div>
      </div>
    </div>

    <!-- Trabajos recientes -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold small">Trabajos recientes</div>
      <div class="list-group list-group-flush" v-if="data.recent_orders && data.recent_orders.length">
        <div class="list-group-item d-flex justify-content-between align-items-center small" v-for="o in data.recent_orders" :key="'co' + o.id">
          <span><i class="fa fa-wrench me-2 text-muted"></i>{{ (o.type && o.type.name) || ('OT #' + o.id) }}</span>
          <span class="text-muted">{{ o.status }}<span v-if="o.created_at"> · {{ o.created_at.slice(0, 10) }}</span></span>
        </div>
      </div>
      <div v-else class="card-body text-center text-muted small">Sin trabajos recientes en este período.</div>
    </div>

    <!-- Regla de desempeño semanal (comisiones + penalizaciones, 2026-10-10) -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold small">Regla de desempeño semanal</div>
      <div class="card-body">
        <template v-if="data.regla_desempeno && data.regla_desempeno.tiene_evaluacion">
          <div class="small text-muted mb-2">
            {{ data.regla_desempeno.regla_nombre || 'Regla sin nombre' }}
            · semana {{ data.regla_desempeno.week_start }} – {{ data.regla_desempeno.week_end }}
          </div>
          <span class="badge" :class="reglaBadgeClass">{{ reglaOutcomeLabel }}</span>
          <div class="small text-muted mt-2">
            Prospectos: {{ data.regla_desempeno.prospects_count }} · Ventas: {{ data.regla_desempeno.sales_count }}
          </div>
          <div v-if="data.regla_desempeno.bonus_amount_applied" class="small text-success mt-1">
            Bono aplicado: ${{ data.regla_desempeno.bonus_amount_applied }}
          </div>
          <div v-if="data.regla_desempeno.penalty_amount" class="small text-danger mt-1">
            Penalización: ${{ data.regla_desempeno.penalty_amount }}
          </div>
        </template>
        <div v-else class="text-center text-muted small">Sin reglas definidas todavía.</div>
      </div>
    </div>
  </div>
</template>

<script>
import Chart from "chart.js/auto";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

export default {
  name: "TalentoFichaCalidad",
  props: {
    colaboradorId: { type: [Number, String], required: true },
  },
  data() {
    return {
      loading: true,
      data: null,
      donutInstance: null,
      lineInstance: null,
      mapaInstance: null,
    };
  },
  computed: {
    hasCalidadData() {
      return (this.data?.completados ?? 0) + (this.data?.garantias_non_billable ?? 0) > 0;
    },
    hasSerieData() {
      return Array.isArray(this.data?.serie?.categories) && this.data.serie.categories.length > 0;
    },
    reglaOutcomeLabel() {
      const labels = {
        no_rule: "Sin regla asignada",
        penalty_pending: "Penalización pendiente",
        bonus: "Bono ganado",
        meets_quota: "Cumple la cuota",
      };
      return labels[this.data?.regla_desempeno?.outcome] || this.data?.regla_desempeno?.outcome || "—";
    },
    reglaBadgeClass() {
      const classes = {
        no_rule: "bg-secondary",
        penalty_pending: "bg-danger",
        bonus: "bg-success",
        meets_quota: "bg-primary",
      };
      return classes[this.data?.regla_desempeno?.outcome] || "bg-secondary";
    },
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    if (this.donutInstance) this.donutInstance.destroy();
    if (this.lineInstance) this.lineInstance.destroy();
    if (this.mapaInstance) this.mapaInstance.remove();
  },
  watch: {
    colaboradorId() {
      this.load();
    },
  },
  methods: {
    cssVar(name, fallback) {
      const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
      return v || fallback;
    },
    async load() {
      this.loading = true;
      if (this.donutInstance) { this.donutInstance.destroy(); this.donutInstance = null; }
      if (this.lineInstance) { this.lineInstance.destroy(); this.lineInstance = null; }
      if (this.mapaInstance) { this.mapaInstance.remove(); this.mapaInstance = null; }

      const r = await axios.get(`/talento/api/colaboradores/${this.colaboradorId}/progreso`).catch(() => null);
      this.data = r?.data ?? null;
      this.loading = false;

      if (this.data?.es_tecnico) {
        await this.$nextTick();
        this.buildDonut();
        this.buildLine();
        if (this.data.es_externo) this.buildMapa();
      }
    },
    buildDonut() {
      if (!this.$refs.donutChart) return;
      // Sin tareas todavía: la dona se dibuja igual (gris, neutra) en vez de
      // desaparecer — nunca se inventa un dato, solo se evita el hueco en blanco.
      if (!this.hasCalidadData) {
        this.donutInstance = new Chart(this.$refs.donutChart, {
          type: "doughnut",
          data: { labels: ["Sin datos"], datasets: [{ data: [1], backgroundColor: ["#e5e7eb"], borderWidth: 0 }] },
          options: { responsive: true, plugins: { legend: { display: false }, tooltip: { enabled: false } }, cutout: "65%" },
        });
        return;
      }
      const sinFallas = this.data.completados || 0;
      const conFallas = this.data.garantias_non_billable || 0;
      this.donutInstance = new Chart(this.$refs.donutChart, {
        type: "doughnut",
        data: {
          labels: ["Sin incidencias", "Con incidencias (garantía)"],
          datasets: [{
            data: [sinFallas, conFallas],
            backgroundColor: [this.cssVar("--bs-success", "#16a34a"), this.cssVar("--bs-danger", "#dc2626")],
            borderWidth: 0,
          }],
        },
        options: { responsive: true, plugins: { legend: { position: "bottom" } }, cutout: "65%" },
      });
    },
    buildLine() {
      if (!this.$refs.lineChart) return;
      const serie = this.data.serie || {};
      this.lineInstance = new Chart(this.$refs.lineChart, {
        type: "line",
        data: {
          labels: serie.categories,
          datasets: [
            { label: "Garantías <6m (no pagables)", data: serie.garantias_non_billable || [], borderColor: this.cssVar("--bs-danger", "#dc2626"), backgroundColor: "rgba(220,53,69,.12)", fill: true, tension: 0.3 },
            { label: "Garantías >6m (pagables)", data: serie.garantias_billable || [], borderColor: this.cssVar("--bs-warning", "#d97706"), backgroundColor: "rgba(217,119,6,.10)", fill: true, tension: 0.3 },
          ],
        },
        options: { responsive: true, plugins: { legend: { position: "top" } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
      });
    },
    buildMapa() {
      if (!this.$refs.mapaEl) return;
      this.mapaInstance = L.map(this.$refs.mapaEl, { zoomControl: true });
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap",
        maxZoom: 19,
      }).addTo(this.mapaInstance);

      const puntos = this.data.mapa?.puntos || [];
      if (puntos.length) {
        const color = this.cssVar("--bs-primary", "#0d6efd");
        const colorEnCurso = this.cssVar("--bs-warning", "#d97706");
        const marcadores = puntos.map((p) => {
          const m = L.circleMarker([p.lat, p.lng], {
            radius: 9,
            color: p.status === "in_progress" ? colorEnCurso : color,
            fillColor: p.status === "in_progress" ? colorEnCurso : color,
            fillOpacity: 0.85,
            weight: 2,
          }).addTo(this.mapaInstance);
          const tipo = p.tipo ? `<br>${p.tipo}` : "";
          m.bindPopup(`<strong>${p.cliente}</strong><br>${p.address || "Sin dirección"}${tipo}`);
          return m;
        });
        this.mapaInstance.fitBounds(L.featureGroup(marcadores).getBounds(), { padding: [30, 30], maxZoom: 16 });
      } else {
        this.mapaInstance.setView([19.4326, -99.1332], 10);
      }
      setTimeout(() => { if (this.mapaInstance) this.mapaInstance.invalidateSize(); }, 150);
    },
  },
};
</script>

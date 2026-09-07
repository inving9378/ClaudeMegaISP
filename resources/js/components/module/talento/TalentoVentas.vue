<template>
  <div class="talento-ventas">

    <div class="alert alert-light small mb-3 py-2">
      <i class="fa fa-info-circle me-1 text-primary"></i>
      Vista <strong>solo lectura</strong>. Estas cifras se calculan con el mismo motor del dashboard de Vendedores, filtradas a tus propias ventas y prospectos.
    </div>

    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>

    <div v-else-if="!esVendedor" class="alert alert-warning">
      <i class="fa fa-exclamation-triangle me-1"></i> {{ message || 'No tienes una cuenta de vendedor asociada.' }}
    </div>

    <div v-else>
      <!-- Filtro de rango -->
      <div class="row g-2 mb-3 align-items-center">
        <div class="col-auto">
          <input v-model="filters.from" @change="load" type="date" class="form-control form-control-sm" title="Desde">
        </div>
        <div class="col-auto">
          <input v-model="filters.to" @change="load" type="date" class="form-control form-control-sm" title="Hasta">
        </div>
        <div class="col-auto" v-if="filters.from || filters.to">
          <button @click="clearRange" class="btn btn-sm btn-outline-secondary"><i class="fa fa-times me-1"></i>Limpiar</button>
        </div>
      </div>

      <!-- KPIs -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-circle p-3 bg-primary-subtle" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
                <i class="fa fa-handshake text-primary" style="font-size:18px;"></i>
              </div>
              <div>
                <div class="h4 mb-0 fw-bold">{{ totalVentas }}</div>
                <div class="small text-muted">Ventas en el rango</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-circle p-3 bg-info-subtle" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
                <i class="fa fa-user-plus text-info" style="font-size:18px;"></i>
              </div>
              <div>
                <div class="h4 mb-0 fw-bold">{{ totalProspectos }}</div>
                <div class="small text-muted">Prospectos en el rango</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-circle p-3 bg-danger-subtle" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
                <i class="fa fa-times-circle text-danger" style="font-size:18px;"></i>
              </div>
              <div>
                <div class="h4 mb-0 fw-bold">{{ data.lost_sales ?? 0 }}</div>
                <div class="small text-muted">Ventas perdidas (total)</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Chart: ventas y prospectos por fecha -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold small">Ventas y prospectos por fecha</div>
        <div class="card-body">
          <div v-if="!hasSeriesData" class="alert alert-light text-center mb-0">Sin datos en el periodo</div>
          <canvas v-else ref="seriesChart" height="90"></canvas>
        </div>
      </div>

      <div class="row g-3">
        <!-- Ventas por medio -->
        <div class="col-md-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold small">Ventas por medio</div>
            <div class="card-body p-0">
              <div v-if="!salesByMedium.length" class="alert alert-light text-center m-3 mb-0">Sin datos</div>
              <table v-else class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Medio</th><th class="text-end">Total</th><th class="text-end">%</th></tr></thead>
                <tbody>
                  <tr v-for="m in salesByMedium" :key="m.name">
                    <td class="small">{{ m.name }}</td>
                    <td class="text-end small">{{ m.total }}</td>
                    <td class="text-end small">{{ fmt1(m.percentage) }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Prospectos por estado -->
        <div class="col-md-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold small">Prospectos por estado</div>
            <div class="card-body p-0">
              <div v-if="!prospectsByStatus.length" class="alert alert-light text-center m-3 mb-0">Sin datos</div>
              <table v-else class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Estado</th><th class="text-end">Total</th><th class="text-end">%</th></tr></thead>
                <tbody>
                  <tr v-for="p in prospectsByStatus" :key="p.crm_status">
                    <td class="small">{{ p.crm_status }}</td>
                    <td class="text-end small">{{ p.total }}</td>
                    <td class="text-end small">{{ fmt1(p.percentage) }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Comparativo mensual -->
      <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold small">Ventas: mes actual vs. mes anterior</div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-6">
              <div class="h4 mb-0 fw-bold">{{ currentMonthTotal }}</div>
              <div class="small text-muted">Mes actual</div>
            </div>
            <div class="col-6">
              <div class="h4 mb-0 fw-bold text-muted">{{ previousMonthTotal }}</div>
              <div class="small text-muted">Mes anterior</div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import Chart from 'chart.js/auto';

export default {
  name: 'TalentoVentas',
  data() {
    return {
      loading: true,
      esVendedor: false,
      message: '',
      data: {},
      filters: { from: '', to: '' },
      chartInstance: null,
    };
  },
  computed: {
    salesAndProspects() {
      return this.data.sales_and_prospects ?? { sales: [], prospects: [] };
    },
    hasSeriesData() {
      return (this.salesAndProspects.sales?.length ?? 0) > 0 || (this.salesAndProspects.prospects?.length ?? 0) > 0;
    },
    totalVentas() {
      return (this.salesAndProspects.sales ?? []).reduce((sum, s) => sum + Number(s.sales ?? 0), 0);
    },
    totalProspectos() {
      return (this.salesAndProspects.prospects ?? []).reduce((sum, p) => sum + Number(p.prospects ?? 0), 0);
    },
    salesByMedium() {
      return this.data.sales_by_medium ?? [];
    },
    prospectsByStatus() {
      return this.data.prospects_by_status ?? [];
    },
    currentMonthTotal() {
      return (this.data.compare_sales?.current_month ?? []).reduce((sum, d) => sum + Number(d.sales ?? 0), 0);
    },
    previousMonthTotal() {
      return (this.data.compare_sales?.previous_month ?? []).reduce((sum, d) => sum + Number(d.sales ?? 0), 0);
    },
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    if (this.chartInstance) this.chartInstance.destroy();
  },
  methods: {
    clearRange() {
      this.filters.from = '';
      this.filters.to = '';
      this.load();
    },
    async load() {
      this.loading = true;
      const params = {};
      if (this.filters.from && this.filters.to) {
        params.range = [this.filters.from, this.filters.to];
      } else if (this.filters.from) {
        params.range = [this.filters.from];
      }
      const r = await axios.get('/talento/api/mis-ventas', { params }).catch(() => null);
      this.loading = false;
      this.esVendedor = !!r?.data?.es_vendedor;
      this.message = r?.data?.message ?? '';
      this.data = r?.data ?? {};
      if (this.esVendedor) {
        this.$nextTick(() => this.rebuildChart());
      }
    },
    rebuildChart() {
      if (!this.hasSeriesData || !this.$refs.seriesChart) return;
      const salesMap = new Map((this.salesAndProspects.sales ?? []).map(s => [s.date, s.sales]));
      const prospectsMap = new Map((this.salesAndProspects.prospects ?? []).map(p => [p.date, p.prospects]));
      const labels = [...new Set([...salesMap.keys(), ...prospectsMap.keys()])].sort();
      const salesData = labels.map(d => salesMap.get(d) ?? 0);
      const prospectsData = labels.map(d => prospectsMap.get(d) ?? 0);
      if (this.chartInstance) this.chartInstance.destroy();
      this.chartInstance = new Chart(this.$refs.seriesChart, {
        type: 'bar',
        data: {
          labels,
          datasets: [
            { label: 'Ventas', data: salesData, backgroundColor: 'rgba(54,162,235,0.7)' },
            { label: 'Prospectos', data: prospectsData, backgroundColor: 'rgba(255,159,64,0.7)' },
          ],
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } },
      });
    },
    fmt1(v) { return v != null ? Number(v).toFixed(1) : '0.0'; },
  },
};
</script>

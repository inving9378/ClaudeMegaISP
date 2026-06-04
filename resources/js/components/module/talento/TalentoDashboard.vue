<template>
  <div class="talento-dashboard">

    <!-- Info cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3" v-for="card in infoCards" :key="card.key">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle p-3" :class="card.bgClass" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
              <i :class="['fa', card.icon, card.textClass]" style="font-size:18px;"></i>
            </div>
            <div>
              <div class="h4 mb-0 fw-bold">{{ card.value }}</div>
              <div class="small text-muted">{{ card.label }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link" :class="{active:tab==='produccion'}" href="#" @click.prevent="tab='produccion'"><i class="fa fa-chart-bar me-1"></i>Producción diaria</a></li>
      <li class="nav-item"><a class="nav-link" :class="{active:tab==='tecnico'}" href="#" @click.prevent="tab='tecnico'"><i class="fa fa-user-hard-hat me-1"></i>Mi panel</a></li>
      <li class="nav-item"><a class="nav-link" :class="{active:tab==='calculadora'}" href="#" @click.prevent="tab='calculadora'"><i class="fa fa-calculator me-1"></i>Calculadora de pago</a></li>
      <li class="nav-item"><a class="nav-link" :class="{active:tab==='supervisor'}" href="#" @click.prevent="tab='supervisor'"><i class="fa fa-users me-1"></i>Mi equipo</a></li>
    </ul>

    <!-- TAB: Producción diaria -->
    <div v-if="tab==='produccion'">
      <div class="d-flex gap-2 flex-wrap mb-3 align-items-center">
        <label class="small text-muted me-1">Días:</label>
        <select v-model="prodDays" @change="loadDailyProduction" class="form-select form-select-sm" style="width:90px">
          <option :value="7">7</option>
          <option :value="14">14</option>
          <option :value="30">30</option>
          <option :value="60">60</option>
        </select>
        <div class="form-check form-check-inline ms-3">
          <input class="form-check-input" type="checkbox" v-model="showInstalaciones" id="chkInst">
          <label class="form-check-label small" for="chkInst">Instalaciones</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" v-model="showGarantias6" id="chkG6">
          <label class="form-check-label small" for="chkG6">Garantías &lt;6m</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" v-model="showGarantias6plus" id="chkG6p">
          <label class="form-check-label small" for="chkG6p">Garantías &gt;6m</label>
        </div>
      </div>

      <div v-if="loadingProd" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else-if="dailyData.length === 0" class="alert alert-light text-center">Sin datos en el periodo</div>
      <div v-else class="card border-0 shadow-sm">
        <div class="card-body">
          <canvas ref="prodChart" height="100"></canvas>
        </div>
      </div>
    </div>

    <!-- TAB: Panel del técnico -->
    <div v-if="tab==='tecnico'">
      <div class="mb-3 d-flex gap-2 align-items-center">
        <label class="small text-muted">Colaborador:</label>
        <select v-model="tecnicoId" @change="loadTecnico" class="form-select form-select-sm" style="width:240px">
          <option value="">— Seleccionar —</option>
          <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name ?? c.id }}</option>
        </select>
      </div>

      <div v-if="loadingTecnico" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else-if="!tecnicoData" class="alert alert-light">Selecciona un colaborador.</div>
      <div v-else class="row g-3">
        <!-- Cuota -->
        <div class="col-md-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold small">Cuota semanal</div>
            <div class="card-body">
              <div class="d-flex justify-content-between mb-1 small">
                <span>{{ tecnicoData.internal_units + tecnicoData.external_points }} unidades</span>
                <span class="text-muted">Meta: {{ tecnicoData.weekly_quota }}</span>
              </div>
              <div class="progress mb-2" style="height:12px">
                <div class="progress-bar" :class="quotaPct>=100?'bg-success':'quotaPct>=75?\'bg-warning\':\'bg-danger\''"
                     :style="{width: Math.min(quotaPct,100)+'%'}"></div>
              </div>
              <div class="small text-muted">{{ fmt1(quotaPct) }}% alcanzado</div>
            </div>
          </div>
        </div>
        <!-- Pago proyectado -->
        <div class="col-md-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold small">Pago proyectado (preview)</div>
            <div class="card-body">
              <div class="h3 fw-bold text-success mb-1">${{ fmt2(tecnicoData.gross_pay_projected) }}</div>
              <div class="small text-muted mb-2">Sueldo base: ${{ fmt2(tecnicoData.base_salary) }}</div>
              <div v-if="tecnicoData.breakdown?.length" class="small">
                <div v-for="b in tecnicoData.breakdown" :key="b.concept" class="d-flex justify-content-between border-bottom py-1">
                  <span>{{ b.concept }}</span>
                  <span :class="b.amount >= 0 ? 'text-success' : 'text-danger'">${{ fmt2(b.amount) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: Calculadora -->
    <div v-if="tab==='calculadora'">
      <div class="card border-0 shadow-sm" style="max-width:480px">
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Colaborador</label>
            <select v-model="calcColabId" class="form-select form-select-sm">
              <option value="">— Seleccionar —</option>
              <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name ?? c.id }}</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Unidades hipotéticas</label>
            <input v-model.number="calcUnits" type="number" min="0" class="form-control form-control-sm">
          </div>
          <button @click="runSimulation" class="btn btn-primary btn-sm w-100" :disabled="loadingCalc || !calcColabId">
            <span v-if="loadingCalc" class="spinner-border spinner-border-sm me-1"></span>
            Simular pago
          </button>

          <div v-if="simResult" class="mt-3">
            <div class="alert alert-success py-2 mb-2">
              <strong>Pago simulado: ${{ fmt2(simResult.gross_pay_simulated) }}</strong>
              <div class="small">Base ${{ fmt2(simResult.base_salary) }} + Sobreproducción ${{ fmt2(simResult.overproduction) }}</div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-0">
                <thead class="table-light"><tr><th>Unidades</th><th>Pago bruto</th></tr></thead>
                <tbody>
                  <tr v-for="s in simResult.scenarios" :key="s.units"
                      :class="s.units===simResult.hypothetical_units ? 'table-success' : ''">
                    <td>{{ s.units }}</td>
                    <td>${{ fmt2(s.gross) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: Equipo del supervisor -->
    <div v-if="tab==='supervisor'">
      <div class="mb-3 d-flex gap-2 align-items-center">
        <label class="small text-muted">Supervisor:</label>
        <select v-model="supervisorId" @change="loadEquipo" class="form-select form-select-sm" style="width:240px">
          <option value="">— Seleccionar —</option>
          <option v-for="c in colaboradores" :key="c.id" :value="c.id">{{ c.user?.name ?? c.id }}</option>
        </select>
      </div>
      <div v-if="loadingEquipo" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else-if="!equipoData" class="alert alert-light">Selecciona un supervisor.</div>
      <div v-else>
        <div class="mb-2 small text-muted">{{ equipoData.team_size }} miembro(s) · Semana {{ equipoData.period_start }} – {{ equipoData.period_end }}</div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
              <tr><th>Colaborador</th><th>Nivel</th><th>Unidades</th><th>Cuota</th><th>% cuota</th><th>Pago proyectado</th></tr>
            </thead>
            <tbody>
              <tr v-for="m in equipoData.members" :key="m.id">
                <td class="fw-semibold small">{{ m.name }}</td>
                <td class="small text-muted">{{ m.level ?? '—' }}</td>
                <td>{{ m.units }}</td>
                <td>{{ m.quota }}</td>
                <td>
                  <div class="progress" style="height:8px;min-width:60px">
                    <div class="progress-bar" :class="(m.quota_pct??0)>=100?'bg-success':(m.quota_pct??0)>=75?'bg-warning':'bg-danger'"
                         :style="{width:Math.min(m.quota_pct??0,100)+'%'}"></div>
                  </div>
                  <span class="small">{{ m.quota_pct != null ? fmt1(m.quota_pct)+'%' : '—' }}</span>
                </td>
                <td class="text-success fw-semibold">${{ fmt2(m.gross_pay) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoDashboard',
  data() {
    return {
      tab: 'produccion',
      infoCardData: null,
      dailyData: [],
      loadingProd: false,
      prodDays: 30,
      showInstalaciones: true,
      showGarantias6: true,
      showGarantias6plus: true,
      chartInstance: null,
      colaboradores: [],
      tecnicoId: '',
      tecnicoData: null,
      loadingTecnico: false,
      calcColabId: '',
      calcUnits: 0,
      simResult: null,
      loadingCalc: false,
      supervisorId: '',
      equipoData: null,
      loadingEquipo: false,
    };
  },
  computed: {
    infoCards() {
      if (!this.infoCardData) return [];
      const d = this.infoCardData;
      return [
        { key: 'colabs', label: 'Colaboradores activos', value: d.active_colaboradores ?? 0, icon: 'fa-users', bgClass: 'bg-primary-subtle', textClass: 'text-primary' },
        { key: 'asist',  label: 'Asistencia hoy',        value: d.checked_in_today ?? 0,    icon: 'fa-clock',  bgClass: 'bg-success-subtle', textClass: 'text-success' },
        { key: 'ord',    label: 'Órdenes hoy',           value: d.orders_today ?? 0,         icon: 'fa-tasks',  bgClass: 'bg-warning-subtle', textClass: 'text-warning' },
        { key: 'alrt',   label: 'Alertas',               value: (d.alerts?.credentials ?? 0) + (d.alerts?.desvios ?? 0), icon: 'fa-exclamation-triangle', bgClass: 'bg-danger-subtle', textClass: 'text-danger' },
      ];
    },
    quotaPct() {
      if (!this.tecnicoData || !this.tecnicoData.weekly_quota) return 0;
      const units = (this.tecnicoData.internal_units ?? 0) + (this.tecnicoData.external_points ?? 0);
      return Math.round((units / this.tecnicoData.weekly_quota) * 100 * 10) / 10;
    },
  },
  mounted() {
    this.loadInfoCards();
    this.loadDailyProduction();
    this.loadColaboradores();
  },
  watch: {
    showInstalaciones() { this.rebuildChart(); },
    showGarantias6()    { this.rebuildChart(); },
    showGarantias6plus(){ this.rebuildChart(); },
  },
  methods: {
    async loadInfoCards() {
      const r = await axios.get('/talento/api/dashboard/info-cards').catch(() => null);
      if (r?.data) this.infoCardData = r.data;
    },
    async loadDailyProduction() {
      this.loadingProd = true;
      const r = await axios.get('/talento/api/dashboard/daily-production', { params: { days: this.prodDays } }).catch(() => null);
      this.loadingProd = false;
      if (r?.data?.series) {
        this.dailyData = r.data.series;
        await this.$nextTick();
        this.rebuildChart();
      }
    },
    rebuildChart() {
      if (!this.dailyData.length || !this.$refs.prodChart) return;
      if (this.chartInstance) { this.chartInstance.destroy(); this.chartInstance = null; }

      const labels = this.dailyData.map(d => d.date);
      const datasets = [];
      if (this.showInstalaciones)  datasets.push({ label: 'Instalaciones',  data: this.dailyData.map(d => d.installed ?? d.validated ?? 0), borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.15)', fill: true, tension: 0.3 });
      if (this.showGarantias6)     datasets.push({ label: 'Garantías <6m',  data: this.dailyData.map(d => d.warranty_under_6m ?? 0),         borderColor: '#ffc107', backgroundColor: 'rgba(255,193,7,.1)',  fill: true, tension: 0.3 });
      if (this.showGarantias6plus) datasets.push({ label: 'Garantías >6m',  data: this.dailyData.map(d => d.warranty_over_6m ?? 0),          borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,.1)',  fill: true, tension: 0.3 });

      if (!datasets.length) return;

      if (typeof Chart === 'undefined') return;
      this.chartInstance = new Chart(this.$refs.prodChart, {
        type: 'line',
        data: { labels, datasets },
        options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } },
      });
    },
    async loadColaboradores() {
      const r = await axios.get('/talento/api/colaboradores?per_page=200').catch(() => null);
      this.colaboradores = r?.data?.data ?? [];
    },
    async loadTecnico() {
      if (!this.tecnicoId) { this.tecnicoData = null; return; }
      this.loadingTecnico = true;
      const r = await axios.get(`/talento/api/dashboard/tecnico/${this.tecnicoId}`).catch(() => null);
      this.loadingTecnico = false;
      this.tecnicoData = r?.data ?? null;
    },
    async runSimulation() {
      if (!this.calcColabId) return;
      this.loadingCalc = true;
      const r = await axios.post('/talento/api/dashboard/simulate-pay', { colaborador_id: this.calcColabId, hypothetical_units: this.calcUnits }).catch(() => null);
      this.loadingCalc = false;
      this.simResult = r?.data ?? null;
    },
    async loadEquipo() {
      if (!this.supervisorId) { this.equipoData = null; return; }
      this.loadingEquipo = true;
      const r = await axios.get(`/talento/api/dashboard/equipo/${this.supervisorId}`).catch(() => null);
      this.loadingEquipo = false;
      this.equipoData = r?.data ?? null;
    },
    fmt1(v) { return v != null ? Math.round(v * 10) / 10 : '—'; },
    fmt2(v) { return v != null ? Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'; },
  },
};
</script>

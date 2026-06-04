<template>
  <div class="talento-escalafon">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h5 class="mb-0"><i class="fa fa-trophy me-2 text-warning"></i>Escalafón de Colaboradores</h5>
      <div class="d-flex gap-2 align-items-center">
        <label class="small text-muted">Semanas:</label>
        <select v-model="weeksBack" @change="load" class="form-select form-select-sm" style="width:80px">
          <option :value="2">2</option>
          <option :value="4">4</option>
          <option :value="8">8</option>
          <option :value="12">12</option>
        </select>
        <button v-if="canManage" @click="openConfig" class="btn btn-sm btn-outline-secondary">
          <i class="fa fa-sliders-h me-1"></i>Pesos
        </button>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link" :class="{active:tab==='ranking'}" href="#" @click.prevent="tab='ranking'"><i class="fa fa-list-ol me-1"></i>Posiciones</a></li>
      <li class="nav-item"><a class="nav-link" :class="{active:tab==='improved'}" href="#" @click.prevent="tab='improved'"><i class="fa fa-arrow-trend-up me-1"></i>Más mejorado</a></li>
    </ul>

    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-warning"></div></div>

    <!-- Ranking -->
    <div v-else-if="tab==='ranking'">
      <div v-if="!ranking.length" class="alert alert-light text-center">Sin datos de colaboradores activos</div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:50px">#</th>
              <th>Colaborador</th>
              <th>Nivel</th>
              <th>Estrellas</th>
              <th>Puntaje</th>
              <th title="Cuota">Cuota</th>
              <th title="Calidad inspecciones">Calidad</th>
              <th title="Bono salud">Salud</th>
              <th title="Sin penalizaciones">Normas</th>
              <th title="Asistencia">Asist.</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in ranking" :key="row.colaborador_id"
                :class="row.rank<=3 ? 'table-warning' : ''">
              <td class="text-center fw-bold">
                <span v-if="row.rank===1">🥇</span>
                <span v-else-if="row.rank===2">🥈</span>
                <span v-else-if="row.rank===3">🥉</span>
                <span v-else>{{ row.rank }}</span>
              </td>
              <td>
                <div class="fw-semibold small">{{ row.name }}</div>
                <div v-if="row.improvement_delta != null" class="small" :class="row.improvement_delta>=0?'text-success':'text-danger'">
                  <i :class="['fa', row.improvement_delta>=0?'fa-arrow-up':'fa-arrow-down']"></i>
                  {{ Math.abs(row.improvement_delta) }} pts vs periodo anterior
                </div>
              </td>
              <td class="small text-muted">{{ row.level ?? '—' }}</td>
              <td>
                <span v-for="s in row.stars" :key="s" class="text-warning">★</span>
                <span v-for="s in (5-row.stars)" :key="'e'+s" class="text-muted">☆</span>
              </td>
              <td>
                <div class="fw-bold">{{ row.total_score }}</div>
                <div class="progress" style="height:4px;min-width:60px">
                  <div class="progress-bar bg-warning" :style="{width:row.total_score+'%'}"></div>
                </div>
              </td>
              <td class="small">{{ fmt1(row.components?.quota) }}%</td>
              <td class="small">{{ fmt1(row.components?.quality) }}%</td>
              <td class="small">{{ fmt1(row.components?.health_bonus) }}%</td>
              <td class="small">{{ fmt1(row.components?.normas) }}%</td>
              <td class="small">{{ fmt1(row.components?.attendance) }}%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Más mejorado -->
    <div v-else-if="tab==='improved'">
      <div class="alert alert-info alert-sm py-2 mb-3 small">
        <i class="fa fa-info-circle me-1"></i>Compara las últimas 2 semanas vs las 2 anteriores.
      </div>
      <div v-if="!mostImproved.length" class="alert alert-light text-center">Sin datos suficientes</div>
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light"><tr><th>#</th><th>Colaborador</th><th>Δ Puntaje</th><th>Puntaje actual</th></tr></thead>
          <tbody>
            <tr v-for="(row, idx) in mostImproved" :key="row.colaborador_id">
              <td>{{ idx + 1 }}</td>
              <td class="fw-semibold small">{{ row.name }}</td>
              <td :class="row.improvement_delta>=0?'text-success fw-bold':'text-danger'">
                {{ row.improvement_delta >= 0 ? '+' : '' }}{{ row.improvement_delta }}
              </td>
              <td>{{ row.total_score }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal config pesos -->
    <div class="modal fade" id="modalEscalafonConfig" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Configurar pesos del puntaje</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-light small mb-3">Los pesos se normalizan automáticamente a 100.</div>
            <div v-for="(val, key) in editWeights" :key="key" class="mb-3">
              <label class="form-label small fw-semibold">{{ weightLabels[key] }} (actual: {{ val }})</label>
              <input v-model.number="editWeights[key]" type="range" class="form-range" min="0" max="100">
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary btn-sm" @click="saveConfig" :disabled="savingConfig">
              <span v-if="savingConfig" class="spinner-border spinner-border-sm me-1"></span>
              Guardar
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'TalentoEscalafon',
  data() {
    return {
      tab: 'ranking',
      ranking: [],
      loading: false,
      weeksBack: 4,
      canManage: false,
      editWeights: { quota: 35, quality: 25, health_bonus: 15, normas: 15, attendance: 10 },
      savingConfig: false,
      weightLabels: { quota: 'Cuota/Producción', quality: 'Calidad de caja', health_bonus: 'Bono de salud de red', normas: 'Normas (sin penaliz.)', attendance: 'Asistencia' },
      configModal: null,
    };
  },
  computed: {
    mostImproved() {
      return [...this.ranking]
        .filter(r => r.improvement_delta != null)
        .sort((a, b) => (b.improvement_delta ?? 0) - (a.improvement_delta ?? 0))
        .slice(0, 10);
    },
  },
  mounted() {
    this.load();
    this.loadConfig();
    this.canManage = window.__talento_can_manage_escalafon ?? false;
  },
  methods: {
    async load() {
      this.loading = true;
      const r = await axios.get('/talento/api/escalafon', { params: { weeks_back: this.weeksBack } }).catch(() => null);
      this.loading = false;
      this.ranking = r?.data?.ranking ?? [];
    },
    async loadConfig() {
      const r = await axios.get('/talento/api/escalafon/config').catch(() => null);
      if (r?.data?.weights) this.editWeights = { ...r.data.weights };
    },
    openConfig() {
      if (!this.configModal) {
        this.configModal = new bootstrap.Modal(document.getElementById('modalEscalafonConfig'));
      }
      this.configModal.show();
    },
    async saveConfig() {
      this.savingConfig = true;
      const r = await axios.put('/talento/api/escalafon/config', { weights: this.editWeights }).catch(() => null);
      this.savingConfig = false;
      if (r?.data?.ok) {
        this.configModal?.hide();
        this.load();
      }
    },
    fmt1(v) { return v != null ? Math.round(v * 10) / 10 : '—'; },
  },
};
</script>

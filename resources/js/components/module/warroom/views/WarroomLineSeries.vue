<template>
  <div class="wls-root">
    <!-- Header con controles opcionales -->
    <div class="wls-header" v-if="showControls">
      <div class="wls-title" v-if="title">{{ title }}</div>
      <div class="wls-controls">
        <slot name="controls" />
        <div class="wls-gran-pills">
          <button
            v-for="g in granularidades"
            :key="g.value"
            class="wls-pill"
            :class="{ active: localGranularidad === g.value }"
            @click="localGranularidad = g.value"
          >{{ g.label }}</button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="wls-placeholder">
      <div class="wls-spinner"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!labels || labels.length === 0" class="wls-placeholder">
      <span class="wls-empty-icon">📊</span>
      <span class="wls-empty-text">Sin datos para el período seleccionado</span>
    </div>

    <!-- Gráfica -->
    <div v-else class="wls-chart-area">
      <canvas ref="canvasEl" class="wls-canvas"></canvas>
    </div>

    <!-- Leyenda custom chips clicables -->
    <div v-if="!loading && series && series.length" class="wls-legend">
      <button
        v-for="(s, i) in series"
        :key="i"
        class="wls-chip"
        :class="{ hidden: !visibleSet[i] }"
        :style="{ '--chip-color': resolveColor(i) }"
        @click="toggleSerie(i)"
      >
        <span class="wls-chip-dot"></span>
        <span class="wls-chip-label">{{ s.nombre }}</span>
      </button>
    </div>
  </div>
</template>

<script>
import { Chart } from 'chart.js/auto';

const PALETTE = [
  '#534AB7','#1D9E75','#BA7517','#D4537E','#185FA5',
  '#8b80f8','#5ddbb3','#f5c47a','#f0a3be','#80b8f0',
];

const GRAN_LABELS = {
  dia:    'Día',
  semana: 'Semana',
  mes:    'Mes',
  ano:    'Año',
};

export default {
  name: 'WarroomLineSeries',

  props: {
    // Array de { nombre, color?, datos[] }
    series:   { type: Array,  default: () => [] },
    labels:   { type: Array,  default: () => [] },
    loading:  { type: Boolean, default: false },
    title:    { type: String,  default: '' },
    granularidad: { type: String, default: 'semana' },
    showControls: { type: Boolean, default: true },
    // Unit label for tooltip Y axis
    yLabel:   { type: String, default: '' },
  },

  emits: ['update:granularidad'],

  data() {
    return {
      chart: null,
      visibleSet: {},
      localGranularidad: this.granularidad,
      granularidades: [
        { value: 'dia',    label: 'Día' },
        { value: 'semana', label: 'Semana' },
        { value: 'mes',    label: 'Mes' },
        { value: 'ano',    label: 'Año' },
      ],
    };
  },

  watch: {
    granularidad(val) {
      this.localGranularidad = val;
    },
    localGranularidad(val) {
      this.$emit('update:granularidad', val);
    },
    series: {
      deep: true,
      handler() {
        this.initVisibility();
        this.$nextTick(() => this.renderChart());
      },
    },
    labels: {
      deep: true,
      handler() {
        this.$nextTick(() => this.renderChart());
      },
    },
  },

  mounted() {
    this.initVisibility();
    this.$nextTick(() => this.renderChart());
  },

  beforeUnmount() {
    this.destroyChart();
  },

  methods: {
    resolveColor(index) {
      const s = this.series[index];
      return (s && s.color) ? s.color : PALETTE[index % PALETTE.length];
    },

    initVisibility() {
      const next = {};
      (this.series || []).forEach((_, i) => {
        // Preserve existing toggle state, default visible
        next[i] = this.visibleSet[i] !== undefined ? this.visibleSet[i] : true;
      });
      this.visibleSet = next;
    },

    toggleSerie(index) {
      this.visibleSet = { ...this.visibleSet, [index]: !this.visibleSet[index] };
      if (this.chart) {
        const meta = this.chart.getDatasetMeta(index);
        meta.hidden = !this.visibleSet[index];
        this.chart.update('none');
      }
    },

    destroyChart() {
      if (this.chart) {
        this.chart.destroy();
        this.chart = null;
      }
    },

    renderChart() {
      if (!this.$refs.canvasEl) return;
      this.destroyChart();

      if (!this.labels || this.labels.length === 0 || !this.series || this.series.length === 0) return;

      const datasets = this.series.map((s, i) => {
        const color = this.resolveColor(i);
        return {
          label:           s.nombre,
          data:            s.datos || [],
          borderColor:     color,
          backgroundColor: color + '22',
          pointBackgroundColor: color,
          pointBorderColor: '#1a1a2e',
          pointRadius:     4,
          pointHoverRadius: 6,
          borderWidth:     2,
          tension:         0.3,
          fill:            false,
          hidden:          !this.visibleSet[i],
        };
      });

      const yLabel = this.yLabel;
      const gran   = GRAN_LABELS[this.localGranularidad] || '';

      this.chart = new Chart(this.$refs.canvasEl, {
        type: 'line',
        data: {
          labels:   this.labels,
          datasets,
        },
        options: {
          responsive:          true,
          maintainAspectRatio: false,
          interaction: {
            mode:      'index',
            intersect: false,
          },
          plugins: {
            legend: { display: false }, // leyenda custom
            tooltip: {
              backgroundColor: '#12122a',
              borderColor:     '#534AB7',
              borderWidth:     1,
              titleColor:      '#c0b8ff',
              bodyColor:       '#e0deff',
              padding:         10,
              callbacks: {
                title: (items) => {
                  const raw = items[0]?.label ?? '';
                  return gran ? `${gran}: ${raw}` : raw;
                },
                label: (ctx) => {
                  const val = ctx.parsed.y ?? 0;
                  return `  ${ctx.dataset.label}: ${val}${yLabel ? ' ' + yLabel : ''}`;
                },
              },
            },
          },
          scales: {
            x: {
              grid:    { color: 'rgba(255,255,255,0.07)' },
              ticks:   { color: '#9896b8', maxTicksLimit: 12, maxRotation: 30 },
              border:  { color: 'rgba(255,255,255,0.1)' },
            },
            y: {
              beginAtZero: true,
              grid:        { color: 'rgba(255,255,255,0.07)' },
              ticks:       { color: '#9896b8', precision: 0 },
              border:      { color: 'rgba(255,255,255,0.1)' },
            },
          },
        },
      });
    },
  },
};
</script>

<style scoped>
.wls-root {
  background: #12122a;
  border-radius: 10px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.wls-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
}

.wls-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: #c0b8ff;
}

.wls-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.wls-gran-pills {
  display: flex;
  gap: 4px;
  background: #1a1a3a;
  border-radius: 6px;
  padding: 3px;
}

.wls-pill {
  background: transparent;
  border: none;
  color: #9896b8;
  font-size: 0.78rem;
  padding: 3px 10px;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  white-space: nowrap;
}
.wls-pill.active {
  background: #534AB7;
  color: #fff;
}
.wls-pill:hover:not(.active) {
  background: rgba(83,74,183,0.3);
  color: #c0b8ff;
}

.wls-placeholder {
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: #6e6c88;
  font-size: 0.9rem;
}

.wls-spinner {
  width: 28px;
  height: 28px;
  border: 3px solid rgba(83,74,183,0.3);
  border-top-color: #534AB7;
  border-radius: 50%;
  animation: wls-spin 0.8s linear infinite;
}
@keyframes wls-spin { to { transform: rotate(360deg); } }

.wls-empty-icon { font-size: 1.4rem; }

.wls-chart-area {
  position: relative;
  height: 260px;
}

.wls-canvas {
  width: 100% !important;
  height: 100% !important;
}

/* Leyenda chips */
.wls-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.wls-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 20px;
  padding: 4px 12px 4px 8px;
  cursor: pointer;
  transition: opacity 0.15s, background 0.15s;
  font-size: 0.78rem;
  color: #c0b8ff;
}
.wls-chip:hover {
  background: rgba(255,255,255,0.10);
}
.wls-chip.hidden {
  opacity: 0.35;
  background: rgba(255,255,255,0.02);
}

.wls-chip-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--chip-color, #534AB7);
  flex-shrink: 0;
}

.wls-chip-label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 120px;
}
</style>

<template>
  <div class="talento-roadmap">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0"><i class="fa fa-road me-2 text-primary"></i>Roadmap — Talento Meganet</h5>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary"></div>
    </div>

    <div v-else class="row g-3">
      <div v-for="item in items" :key="item.id" class="col-12 col-md-6 col-xl-4">
        <div class="card h-100 border-0 shadow-sm" :class="cardClass(item.status)">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-2">
              <span class="badge me-2" :class="badgeClass(item.status)">Fase {{ item.phase }}</span>
              <span class="badge" :class="statusBadge(item.status)">{{ statusLabel(item.status) }}</span>
            </div>
            <h6 class="card-title fw-semibold">{{ item.title }}</h6>
            <p class="card-text text-muted small mb-2">{{ item.scope }}</p>
            <div v-if="item.target_date" class="small text-muted">
              <i class="fa fa-calendar-alt me-1"></i> {{ formatDate(item.target_date) }}
            </div>
            <div v-if="item.notes" class="small text-secondary mt-1 fst-italic">{{ item.notes }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TalentoRoadmap',
  data() {
    return {
      items: [],
      loading: true,
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      try {
        const { data } = await axios.get('/talento/api/roadmap');
        this.items = data ?? [];
      } catch (e) {
        console.error(e);
      } finally {
        this.loading = false;
      }
    },
    cardClass(status) {
      return { 'border-success': status === 'done', 'border-warning': status === 'in_progress' };
    },
    badgeClass(status) {
      if (status === 'done') return 'bg-success';
      if (status === 'in_progress') return 'bg-warning text-dark';
      return 'bg-secondary';
    },
    statusBadge(status) {
      if (status === 'done') return 'bg-success-subtle text-success';
      if (status === 'in_progress') return 'bg-warning-subtle text-warning';
      return 'bg-light text-muted';
    },
    statusLabel(s) {
      return { done: 'Completada', in_progress: 'En progreso', backlog: 'Pendiente' }[s] ?? s;
    },
    formatDate(d) {
      if (!d) return '';
      return new Date(d).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    },
  },
};
</script>

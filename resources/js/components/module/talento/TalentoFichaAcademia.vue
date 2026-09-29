<template>
  <div class="tc-card">
    <div class="tc-cardhead p-3">
      <h5 class="tc-h1 mb-0"><i class="fa fa-graduation-cap me-2 text-primary"></i>Academia</h5>
    </div>
    <div class="p-3">
      <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <template v-else>
        <div class="mb-3">
          <div class="d-flex justify-content-between small mb-1">
            <span>Progreso general</span>
            <span>{{ progress.certified_count }} / {{ progress.total_courses }} cursos certificados ({{ progress.completion_pct }}%)</span>
          </div>
          <div class="progress" style="height:8px">
            <div class="progress-bar bg-success" :style="{ width: progress.completion_pct + '%' }"></div>
          </div>
        </div>

        <div class="table-responsive mb-4">
          <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
              <tr><th>Curso</th><th>Examen</th><th>Práctica</th><th>Certificado</th></tr>
            </thead>
            <tbody>
              <tr v-for="c in progress.courses" :key="c.course_id">
                <td>{{ c.title }}</td>
                <td><i class="fa" :class="c.exam_passed ? 'fa-check-circle text-success' : 'fa-times-circle text-muted'"></i></td>
                <td><i class="fa" :class="c.practical_ok ? 'fa-check-circle text-success' : 'fa-times-circle text-muted'"></i></td>
                <td><i class="fa" :class="c.certified ? 'fa-check-circle text-success' : 'fa-times-circle text-muted'"></i></td>
              </tr>
            </tbody>
          </table>
        </div>

        <h6 class="mb-2">Certificaciones</h6>
        <div v-if="!certifications.length" class="text-muted small">Sin certificaciones emitidas todavía.</div>
        <ul v-else class="list-group list-group-flush">
          <li v-for="c in certifications" :key="c.id" class="list-group-item px-0 py-2 small d-flex justify-content-between">
            <span>{{ c.course?.title ?? '—' }}</span>
            <span class="text-muted">{{ formatFecha(c.certified_at) }}</span>
          </li>
        </ul>
      </template>
    </div>
  </div>
</template>

<script>
/**
 * Solo lectura — "lo mío" (progreso + certificaciones de UN colaborador), a
 * diferencia de TalentoAcademia.vue (catálogo/gestión de cursos, cosa
 * distinta). Consume el endpoint self/supervisor/manage-scoped
 * (TalentoColaboradorController::miFichaAcademia, que delega en
 * TalentoAcademyController::progressForColaborador/certificationsForColaborador
 * sin duplicar esa lógica) — David, 28-sep-2026.
 */
export default {
  name: 'TalentoFichaAcademia',
  props: {
    colaboradorId: { type: [Number, String], required: true },
  },
  data() {
    return {
      loading: true,
      progress: { certified_count: 0, total_courses: 0, completion_pct: 0, courses: [] },
      certifications: [],
    };
  },
  mounted() { this.load(); },
  methods: {
    async load() {
      this.loading = true;
      try {
        const { data } = await axios.get(`/talento/mi-ficha/${this.colaboradorId}/academia`);
        this.progress = data?.progress ?? this.progress;
        this.certifications = data?.certifications ?? [];
      } catch (e) { /* deja los defaults */ }
      finally { this.loading = false; }
    },
    formatFecha(f) {
      if (!f) return '—';
      return new Date(f).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    },
  },
};
</script>

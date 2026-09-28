<template>
  <div class="tc-card p-3">
    <div class="row g-4">
      <div class="col-md-6">
        <h6 class="text-muted small text-uppercase mb-3">Datos generales</h6>
        <dl class="row mb-0 small">
          <dt class="col-5">Nombre</dt><dd class="col-7">{{ colaborador.user?.name ?? '—' }}</dd>
          <dt class="col-5">Email</dt><dd class="col-7">{{ colaborador.user?.email ?? '—' }}</dd>
          <dt class="col-5">Tipo</dt><dd class="col-7">{{ colaborador.type ?? '—' }}</dd>
          <dt class="col-5">Departamento</dt><dd class="col-7">{{ colaborador.department ?? '—' }}</dd>
          <dt class="col-5">Puesto</dt><dd class="col-7">{{ colaborador.puesto?.nombre ?? colaborador.job_title ?? '—' }}</dd>
          <dt class="col-5">Supervisor</dt><dd class="col-7">{{ colaborador.supervisor?.user?.name ?? '—' }}</dd>
          <dt class="col-5">Ingreso</dt><dd class="col-7">{{ formatDate(colaborador.hire_date) }}</dd>
          <dt class="col-5">Status</dt><dd class="col-7">
            <span class="tc-status" :class="statusBadge(colaborador.status)">{{ statusLabel(colaborador.status) }}</span>
          </dd>
        </dl>
      </div>
      <div class="col-md-6">
        <h6 class="text-muted small text-uppercase mb-3">Roles del sistema</h6>
        <div v-if="colaborador.user?.role_names?.length">
          <span v-for="r in colaborador.user.role_names" :key="r" class="tc-status is-slate me-1 mb-1">
            {{ r }}
          </span>
        </div>
        <div v-else class="text-muted small">Sin roles asignados.</div>

        <h6 class="text-muted small text-uppercase mb-2 mt-4">Subordinados</h6>
        <div v-if="colaborador.subordinados?.length" class="small">
          <div v-for="s in colaborador.subordinados" :key="s.id">{{ s.user?.name }}</div>
        </div>
        <div v-else class="text-muted small">Sin subordinados.</div>

        <div class="mt-4">
          <a :href="`/administracion/user/${colaborador.user_id}/editar`" target="_blank" class="tc-btn tc-btn-info">
            <i class="fa fa-key me-1"></i> Gestión de acceso (Administradores)
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "TalentoFichaInformacion",
  props: {
    colaborador: { type: Object, required: true },
  },
  methods: {
    statusBadge(s) {
      return { active: "is-ok", inactive: "is-slate", suspended: "is-warn" }[s] ?? "is-slate";
    },
    statusLabel(s) {
      return { active: "Activo", inactive: "Inactivo", suspended: "Suspendido" }[s] ?? s;
    },
    formatDate(d) {
      if (!d) return "—";
      return new Date(d).toLocaleDateString("es-MX", { year: "numeric", month: "short", day: "numeric" });
    },
  },
};
</script>

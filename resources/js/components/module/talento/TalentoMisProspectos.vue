<template>
  <!-- "Mis prospectos" dentro de la ficha (2026-10-23): los prospectos del vendedor, como en el
       Portal (/talento/portal/prospectos, self-scoped por Actor). Si puede editar prospectos, cada
       uno abre su ficha del CRM. -->
  <div class="talento-mis-prospectos">
    <div v-if="cargando" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
    <div v-else-if="error" class="alert alert-warning small">{{ error }}</div>
    <div v-else-if="!prospectos.length" class="text-center text-muted small py-4">
      <i class="fa fa-users fa-2x d-block mb-2"></i>No tienes prospectos asignados.
    </div>
    <template v-else>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <input v-model="buscar" type="text" class="form-control form-control-sm" style="max-width:260px" placeholder="Buscar por nombre o teléfono…">
        <select v-model="estado" class="form-select form-select-sm tc-select" style="max-width:200px">
          <option value="">Todos los estados ({{ prospectos.length }})</option>
          <option v-for="e in estados" :key="e.nombre" :value="e.nombre">{{ e.nombre }} ({{ e.n }})</option>
        </select>
      </div>
      <div class="tc-card">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Prospecto</th><th>Contacto</th><th>Estado</th><th>Desde</th></tr></thead>
            <tbody>
              <tr v-for="p in filtrados" :key="p.crm_id">
                <td class="small fw-semibold">
                  <a v-if="puedeAbrirCrm" :href="`/crm/editar/${p.crm_id}`">{{ nombre(p) }}</a>
                  <span v-else>{{ nombre(p) }}</span>
                </td>
                <td class="small text-muted">{{ p.phone || '—' }}<span v-if="p.email"> · {{ p.email }}</span></td>
                <td><span class="tc-status" :class="clase(p.crm_status)">{{ p.crm_status || 'Sin estado' }}</span></td>
                <td class="small text-muted">{{ p.created_at ? p.created_at.slice(0, 10) : '—' }}</td>
              </tr>
              <tr v-if="!filtrados.length"><td colspan="4" class="text-center text-muted small py-3">Ningún prospecto coincide.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
const CLASES = { Ganado: "is-ok", Instalacion: "is-ok", Perdido: "is-bad", Interesado: "is-warn", Contactado: "is-info", Nuevo: "is-info" };

export default {
  name: "TalentoMisProspectos",
  props: {
    puedeAbrirCrm: { type: Boolean, default: false },
  },
  data() {
    return { cargando: true, error: "", prospectos: [], buscar: "", estado: "" };
  },
  computed: {
    estados() {
      const n = {};
      this.prospectos.forEach((p) => { const e = p.crm_status || "Sin estado"; n[e] = (n[e] || 0) + 1; });
      return Object.keys(n).sort().map((nombre) => ({ nombre, n: n[nombre] }));
    },
    filtrados() {
      const q = this.buscar.trim().toLowerCase();
      return this.prospectos.filter((p) =>
        (!this.estado || (p.crm_status || "Sin estado") === this.estado)
        && (!q || this.nombre(p).toLowerCase().includes(q) || String(p.phone || "").includes(q)));
    },
  },
  async mounted() {
    try {
      const { data } = await axios.get("/talento/portal/prospectos");
      this.prospectos = Array.isArray(data?.prospectos) ? data.prospectos : [];
    } catch (e) {
      this.error = e?.response?.data?.error || "No se pudieron cargar tus prospectos.";
    } finally {
      this.cargando = false;
    }
  },
  methods: {
    nombre(p) { return [p.name, p.father_last_name, p.mother_last_name].filter(Boolean).join(" ") || "Sin nombre"; },
    clase(s) { return CLASES[s] ?? "is-info"; },
  },
};
</script>

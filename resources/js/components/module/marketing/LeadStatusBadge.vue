<template>
  <mkt-insignia-estado :texto="etiqueta" :tono="tono" />
</template>

<script>
import MktInsigniaEstado from "./kit/MktInsigniaEstado.vue";
import { etiquetaEstadoLead, tonoEstadoLead } from "./kit/formato";

/** Estado del lead como insignia del kit (mismas props que antes). */
export default {
  name: "LeadStatusBadge",
  components: { MktInsigniaEstado },
  props: { status: { type: String, default: "new" } },
  computed: {
    etiqueta() { return etiquetaEstadoLead(this.status || "new"); },
    tono() { return tonoEstadoLead(this.status || "new"); },
  },
};
</script>

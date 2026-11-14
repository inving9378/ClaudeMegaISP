<template>
  <span class="estado-chip mkt-score" :class="`estado-tono--${tonoSistema}`" :title="`Puntuación ${valor} de 100`">{{ valor }}</span>
</template>

<script>
/**
 * Puntuación del lead (0–100): ≥61 alta, 31–60 media, ≤30 baja. Mismas props que antes.
 * Se pinta con el chip de estado del sistema (`.estado-chip` + `.estado-tono--*`, como la ficha de Cliente):
 * alta = verde (activo), media = ámbar (inactivo), baja = rojo (bloqueado). Sin colores propios.
 */
export default {
  name: "LeadScoreBadge",
  props: { score: { type: Number, default: 0 } },
  computed: {
    valor() { return Number.isFinite(Number(this.score)) ? Number(this.score) : 0; },
    nivel() {
      if (this.valor >= 61) return "alto";
      if (this.valor >= 31) return "medio";
      return "bajo";
    },
    tonoSistema() {
      return { alto: "activo", medio: "inactivo", bajo: "bloqueado" }[this.nivel];
    },
  },
};
</script>

<style scoped>
.mkt-score {
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
</style>

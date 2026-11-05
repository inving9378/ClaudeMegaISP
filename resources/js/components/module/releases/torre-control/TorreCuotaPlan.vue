<template>
  <span class="tcp-chip" :class="[clase, { 'tcp-oscuro': oscuro }]" :title="detalle">
    <i class="bi bi-speedometer2 me-1"></i>
    <template v-if="!cuota">Plan Claude: sin medición</template>
    <template v-else>
      Plan Claude:
      <span v-if="cuota.cinco_horas">5 h {{ pct(cuota.cinco_horas) }}</span>
      <span v-if="cuota.cinco_horas && cuota.semana"> · </span>
      <span v-if="cuota.semana">semana {{ pct(cuota.semana) }}</span>
      <span class="tcp-edad">({{ cuota.medido_hace }})</span>
    </template>
  </span>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import { darkMode } from "../../../../hook/appConfig.js";

/**
 * #1068 — cuánto se ha usado de la suscripción de Claude con la que trabajan las terminales. El dato
 * lo deja la línea de estado de Claude Code (no hay API pública); por eso siempre se muestra su edad.
 */
export default {
  name: "TorreCuotaPlan",
  setup() {
    const cuota = ref(null);
    let timer = null;

    async function cargar() {
      try {
        const { data } = await axios.get("/api/roadmap/torre/cuota-plan");
        cuota.value = data.cuota || null;
      } catch (e) {
        cuota.value = null;
      }
    }

    const pct = (v) => (v.reiniciada ? "se reinició" : `${v.pct}%`);
    const maximo = computed(() => {
      const c = cuota.value;
      if (!c) return null;
      return Math.max(...[c.cinco_horas, c.semana].filter((v) => v && v.pct !== null).map((v) => v.pct), 0);
    });
    const clase = computed(() => {
      if (!cuota.value || cuota.value.vieja) return "tcp-gris";
      if (maximo.value >= 90) return "tcp-rojo";
      if (maximo.value >= 70) return "tcp-ambar";
      return "tcp-verde";
    });
    const detalle = computed(() => {
      const c = cuota.value;
      if (!c) return "Aún no hay medición. Se toma sola cuando alguien tiene abierta una terminal de Claude Code.";
      const parte = (nombre, v) => {
        if (!v) return null;
        if (v.reiniciada) return `${nombre}: se reinició después de la última medición.`;
        return `${nombre}: ${v.pct}% usado` + (v.reinicia_texto ? `, se reinicia ${v.reinicia_texto}.` : ".");
      };
      return [
        parte("Ventana de 5 horas", c.cinco_horas),
        parte("Semana", c.semana),
        `Medido ${c.medido_hace}` + (c.vieja ? " (dato viejo)" : "") + ". Se actualiza solo cuando hay una terminal de Claude Code abierta.",
      ].filter(Boolean).join(" ");
    });

    onMounted(() => {
      cargar();
      timer = setInterval(cargar, 120000);
    });
    onBeforeUnmount(() => {
      if (timer) clearInterval(timer);
    });

    return { cuota, pct, clase, detalle, oscuro: computed(() => darkMode.value) };
  },
};
</script>

<style scoped>
.tcp-chip {
    display: inline-flex; align-items: center; gap: 2px; white-space: nowrap;
    font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; border: 1px solid;
}
.tcp-edad { font-weight: 400; opacity: .75; margin-left: 4px; }
.tcp-verde { color: #166534; background: #f0fdf4; border-color: #bbf7d0; }
.tcp-ambar { color: #92400e; background: #fffbeb; border-color: #fde68a; }
.tcp-rojo  { color: #991b1b; background: #fef2f2; border-color: #fecaca; }
.tcp-gris  { color: #475569; background: #f8fafc; border-color: #e2e8f0; }
/* #1120 — modo oscuro: fondo translúcido y texto claro de alto contraste (el pastel claro no se lee) */
.tcp-oscuro.tcp-verde { color: #bbf7d0; background: rgba(34,197,94,.18); border-color: #22c55e; }
.tcp-oscuro.tcp-ambar { color: #fde68a; background: rgba(245,158,11,.18); border-color: #f59e0b; }
.tcp-oscuro.tcp-rojo  { color: #fecaca; background: rgba(239,68,68,.2); border-color: #ef4444; }
.tcp-oscuro.tcp-gris  { color: #e2e8f0; background: rgba(148,163,184,.2); border-color: #94a3b8; }
.tcp-oscuro .tcp-edad { opacity: .9; }
</style>

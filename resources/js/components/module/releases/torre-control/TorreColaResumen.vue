<template>
  <span class="tcr-wrap">
    <button type="button" class="tcr-chip" :class="[clase, { 'tcr-oscuro': oscuro }]" :title="titulo" @click="abierto = !abierto">
      <i class="bi bi-list-task me-1"></i>
      <template v-if="!cola">Cola: …</template>
      <template v-else>
        Cola: <b>{{ cola.tomables }}</b> tomables · <b>{{ cola.bloqueados }}</b> bloqueados · <b>{{ cola.en_curso }}</b> en curso
        <span v-if="ritmo && ritmo.motivo !== 'normal'" class="tcr-ritmo">· ritmo {{ ritmo.max }}/6</span>
      </template>
    </button>

    <div v-if="abierto && cola" class="tcr-panel" :class="{ 'tcr-oscuro': oscuro }">
      <div class="tcr-sec">
        <b>Ritmo:</b> {{ ritmo ? ritmo.texto : 'sin dato' }}
      </div>
      <div class="tcr-sec" v-if="cola.tomables">
        <b>Tomables:</b> {{ cola.salen_ya }} salen en la próxima ronda<span v-if="cola.esperan_turno">, {{ cola.esperan_turno }} esperan turno de su módulo</span>.
      </div>
      <div class="tcr-sec">
        <b>Por qué están bloqueados</b>
        <ul>
          <li v-for="c in cola.causas" :key="c.causa">
            <b>{{ c.total }}</b> {{ c.texto }}
            <span class="tcr-quien">{{ c.destraba === 'irving' ? '(te toca)' : '(se libera solo)' }}</span>
            <span class="tcr-ids">{{ c.ejemplos.map((i) => '#' + i).join(' ') }}</span>
          </li>
        </ul>
      </div>
      <div class="tcr-sec" v-if="cola.requiere_irving.length">
        <b>Lo que solo tú destrabas</b> <span class="tcr-quien">(por cuántos pendientes libera)</span>
        <ol>
          <li v-for="r in cola.requiere_irving" :key="r.id">
            <a :href="'/roadmap/item/' + r.id" target="_blank" rel="noopener">#{{ r.id }}</a>
            <span v-if="r.destraba"> libera {{ r.destraba }}</span> — {{ r.que_hacer }}
            <div class="tcr-tit">{{ r.titulo }}</div>
          </li>
        </ol>
      </div>
      <div v-if="cola.incoherencias.length" class="tcr-sec tcr-alerta">
        ⚠ {{ cola.incoherencias.length }} pendiente(s) que la Hoja de ruta y el despachador cuentan distinto:
        {{ cola.incoherencias.map((i) => '#' + i.id).join(' ') }}
      </div>
    </div>
  </span>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import { darkMode } from "../../../../hook/appConfig.js";

/**
 * Limpieza de la Hoja de ruta — la cola en vivo, con la MISMA definición que usa el despachador
 * (y Jarvis): tomables, bloqueados con su causa, en curso, y el ritmo según la cuota del plan.
 */
export default {
  name: "TorreColaResumen",
  setup() {
    const cola = ref(null);
    const ritmo = ref(null);
    const abierto = ref(false);
    let timer = null;

    async function cargar() {
      try {
        const { data } = await axios.get("/api/roadmap/torre/cola");
        cola.value = data.cola || null;
        ritmo.value = data.ritmo || null;
      } catch (e) {
        /* sin dato: el chip se queda con lo último */
      }
    }

    const clase = computed(() => {
      if (!cola.value) return "tcr-gris";
      if (ritmo.value && ritmo.value.motivo === "cuota_alta") return "tcr-rojo";
      if (cola.value.tomables === 0 && cola.value.bloqueados > 0) return "tcr-ambar";
      return "tcr-verde";
    });
    const titulo = computed(() => {
      if (!cola.value) return "Cargando la cola…";
      const top = (cola.value.causas || []).slice(0, 4).map((c) => `${c.total} ${c.texto}`).join("; ");
      return `Tomables: una terminal libre los toma sola. Bloqueados: ${top}. Clic para el detalle.`;
    });

    onMounted(() => {
      cargar();
      timer = setInterval(cargar, 20000);
    });
    onBeforeUnmount(() => {
      if (timer) clearInterval(timer);
    });

    return { cola, ritmo, abierto, clase, titulo, oscuro: computed(() => darkMode.value) };
  },
};
</script>

<style scoped>
.tcr-wrap { position: relative; display: inline-flex; }
.tcr-chip {
    display: inline-flex; align-items: center; gap: 2px; white-space: nowrap; cursor: pointer;
    font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; border: 1px solid;
}
.tcr-ritmo { font-weight: 400; margin-left: 4px; }
.tcr-verde { color: #166534; background: #f0fdf4; border-color: #bbf7d0; }
.tcr-ambar { color: #92400e; background: #fffbeb; border-color: #fde68a; }
.tcr-rojo  { color: #991b1b; background: #fef2f2; border-color: #fecaca; }
.tcr-gris  { color: #475569; background: #f8fafc; border-color: #e2e8f0; }
.tcr-oscuro.tcr-verde { color: #bbf7d0; background: rgba(34,197,94,.18); border-color: #22c55e; }
.tcr-oscuro.tcr-ambar { color: #fde68a; background: rgba(245,158,11,.18); border-color: #f59e0b; }
.tcr-oscuro.tcr-rojo  { color: #fecaca; background: rgba(239,68,68,.2); border-color: #ef4444; }
.tcr-oscuro.tcr-gris  { color: #e2e8f0; background: rgba(148,163,184,.2); border-color: #94a3b8; }
.tcr-panel {
    position: absolute; top: calc(100% + 6px); left: 0; z-index: 1060; width: min(520px, calc(100vw - 32px));
    max-height: 70vh; overflow: auto; padding: 10px 12px; border-radius: 10px; font-size: 12.5px;
    background: #fff; color: #0f172a; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(15, 23, 42, .15);
}
.tcr-panel.tcr-oscuro { background: #1e293b; color: #e2e8f0; border-color: #334155; }
.tcr-sec { margin-bottom: 8px; }
.tcr-sec ul, .tcr-sec ol { margin: 4px 0 0; padding-left: 18px; }
.tcr-sec li { margin-bottom: 3px; }
.tcr-quien { opacity: .7; font-size: 11.5px; }
.tcr-ids { opacity: .6; font-size: 11px; margin-left: 4px; }
.tcr-tit { opacity: .75; font-size: 11.5px; }
.tcr-alerta { color: #b45309; }
</style>

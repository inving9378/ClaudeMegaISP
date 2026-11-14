<template>
  <ol v-if="activities.length" class="mkt-linea">
    <li v-for="act in activities" :key="act.id" class="mkt-linea__item" :class="`mkt-linea__item--${tono(act.type)}`">
      <span class="mkt-linea__icono" aria-hidden="true"><q-icon :name="icono(act.type)" size="16px" /></span>
      <div class="mkt-linea__cuerpo">
        <div class="mkt-linea__texto">{{ act.description || "Actividad" }}</div>
        <div v-if="act.metadata && act.metadata.reason" class="mkt-linea__detalle">{{ act.metadata.reason }}</div>
        <div class="mkt-linea__meta">
          <time :datetime="act.happened_at">{{ formatoFecha(act.happened_at, { conHora: true }) }}</time>
          <span v-if="act.user && act.user.name"> · {{ act.user.name }}</span>
        </div>
      </div>
    </li>
  </ol>
  <mkt-estado-vacio
    v-else
    icono="history"
    titulo="Sin actividad registrada"
    texto="Aquí aparecerán mensajes, cambios de etapa, asignaciones y calificaciones de este lead."
  />
</template>

<script>
import MktEstadoVacio from "./kit/MktEstadoVacio.vue";
import { formatoFecha } from "./kit/formato";

const ICONOS = {
  created: "person_add",
  message_sent: "send",
  message_received: "inbox",
  score_updated: "auto_awesome",
  stage_changed: "swap_horiz",
  assigned: "assignment_ind",
  note: "sticky_note_2",
  call: "call",
  meeting: "event",
  conversion: "check_circle",
  lost: "cancel",
  custom: "label",
};
const TONOS = {
  created: "exito", conversion: "exito", assigned: "info", score_updated: "primario", stage_changed: "info", lost: "peligro",
};

/** Línea de tiempo de actividades del lead (mismas props que antes: `activities`). */
export default {
  name: "LeadActivityTimeline",
  components: { MktEstadoVacio },
  props: { activities: { type: Array, default: () => [] } },
  methods: {
    formatoFecha,
    icono(t) { return ICONOS[t] || "circle"; },
    tono(t) { return TONOS[t] || "neutro"; },
  },
};
</script>

<style scoped>
.mkt-linea { list-style: none; margin: 0; padding: 0; position: relative; }
.mkt-linea__item {
  --tono: var(--mkt-neutro, #74788d);
  position: relative;
  display: grid;
  grid-template-columns: 32px minmax(0, 1fr);
  gap: 12px;
  padding-bottom: var(--mkt-esp-2, 16px);
}
.mkt-linea__item:not(:last-child)::before {
  content: "";
  position: absolute;
  left: 15px;
  top: 32px;
  bottom: 0;
  width: 2px;
  background: var(--mkt-borde, #e9e9ef);
}
.mkt-linea__item--exito { --tono: var(--mkt-exito, #2ab57d); }
.mkt-linea__item--info { --tono: var(--mkt-info, #4ba6ef); }
.mkt-linea__item--primario { --tono: var(--mkt-primario, #5156be); }
.mkt-linea__item--peligro { --tono: var(--mkt-peligro, #fd625e); }
.mkt-linea__icono {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  color: var(--tono);
  background: color-mix(in srgb, var(--tono) 14%, transparent);
}
.mkt-linea__cuerpo { min-width: 0; padding-top: 5px; }
.mkt-linea__texto { font-size: 14px; color: var(--mkt-texto, #343a40); overflow-wrap: anywhere; }
.mkt-linea__detalle { margin-top: 2px; font-size: 13px; color: var(--mkt-texto-suave, #74788d); overflow-wrap: anywhere; }
.mkt-linea__meta { margin-top: 2px; font-size: 12px; color: var(--mkt-texto-suave, #74788d); }
</style>

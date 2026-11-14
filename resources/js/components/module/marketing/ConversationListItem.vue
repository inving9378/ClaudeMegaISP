<template>
  <button type="button" class="mkt-conv" :class="{ 'mkt-conv--activa': active }" :aria-current="active ? 'true' : null" @click="$emit('select', conversation)">
    <span class="mkt-conv__avatar" :class="`mkt-conv__avatar--${tono}`" aria-hidden="true">{{ inicial }}</span>
    <span class="mkt-conv__cuerpo">
      <span class="mkt-conv__fila">
        <span class="mkt-conv__nombre">{{ conversation.lead?.full_name || conversation.lead?.phone || "—" }}</span>
        <span class="mkt-conv__hora">{{ tiempoRelativo }}</span>
      </span>
      <span class="mkt-conv__fila">
        <span class="mkt-conv__vista">
          <q-icon :name="conversation.ai_handled ? 'smart_toy' : 'person'" size="14px" :class="conversation.ai_handled ? 'mkt-conv__bot' : ''" :aria-label="conversation.ai_handled ? 'Atiende el bot' : 'Atiende una persona'" />
          {{ vistaPrevia }}
        </span>
        <span v-if="conversation.unread_count > 0" class="mkt-conv__sin-leer" :aria-label="`${conversation.unread_count} sin leer`">{{ conversation.unread_count }}</span>
      </span>
    </span>
  </button>
</template>

<script>
import { tonoEstadoLead } from "./kit/formato";

/** Fila de la bandeja de conversaciones (mismas props y evento `select` que antes). */
export default {
  name: "ConversationListItem",
  emits: ["select"],
  props: {
    conversation: { type: Object, required: true },
    active: { type: Boolean, default: false },
  },
  computed: {
    inicial() { return (this.conversation.lead?.full_name || this.conversation.lead?.phone || "?").charAt(0).toUpperCase(); },
    tono() { return tonoEstadoLead(this.conversation.lead?.status); },
    vistaPrevia() {
      const m = this.conversation.last_message;
      if (!m) return "Sin mensajes";
      const texto = String(m.content || "");
      const corto = texto.length > 50 ? `${texto.substring(0, 50)}…` : texto;
      return (m.direction === "outbound" ? "Tú: " : "") + corto;
    },
    tiempoRelativo() {
      const d = this.conversation.last_message_at;
      if (!d) return "";
      const diff = Math.floor((Date.now() - new Date(d)) / 1000);
      if (diff < 60) return "ahora";
      if (diff < 3600) return `${Math.floor(diff / 60)} min`;
      if (diff < 86400) return `${Math.floor(diff / 3600)} h`;
      return new Intl.DateTimeFormat("es-MX", { day: "numeric", month: "short", timeZone: "America/Mexico_City" }).format(new Date(d)).replace(/\./g, "");
    },
  },
};
</script>

<style scoped>
.mkt-conv {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 12px var(--mkt-esp-2, 16px);
  border: 0;
  border-bottom: 1px solid var(--mkt-borde, #e9e9ef);
  background: transparent;
  text-align: left;
  font: inherit;
  color: inherit;
  cursor: pointer;
  box-shadow: inset 3px 0 0 transparent;
}
.mkt-conv:hover { background: var(--mkt-fondo-suave, #f8f8fb); }
.mkt-conv:focus-visible { outline: 2px solid var(--mkt-primario, #5156be); outline-offset: -2px; }
.mkt-conv--activa {
  background: color-mix(in srgb, var(--mkt-primario, #5156be) 8%, transparent);
  box-shadow: inset 3px 0 0 var(--mkt-carpeta, #4f46e5);
}
.mkt-conv__avatar {
  --tono: var(--mkt-neutro, #74788d);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  flex: 0 0 40px;
  border-radius: 50%;
  font-weight: 600;
  color: var(--tono);
  background: color-mix(in srgb, var(--tono) 16%, transparent);
}
.mkt-conv__avatar--exito { --tono: var(--mkt-exito, #2ab57d); }
.mkt-conv__avatar--info { --tono: var(--mkt-info, #4ba6ef); }
.mkt-conv__avatar--aviso { --tono: var(--mkt-aviso, #ffbf53); }
.mkt-conv__avatar--primario { --tono: var(--mkt-primario, #5156be); }
.mkt-conv__avatar--peligro { --tono: var(--mkt-peligro, #fd625e); }
.mkt-conv__cuerpo { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1 1 auto; }
.mkt-conv__fila { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-width: 0; }
.mkt-conv__nombre { font-weight: 600; font-size: 14px; color: var(--mkt-texto, #343a40); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mkt-conv__hora { flex: 0 0 auto; font-size: 12px; color: var(--mkt-texto-suave, #74788d); }
.mkt-conv__vista { display: flex; align-items: center; gap: 4px; min-width: 0; font-size: 13px; color: var(--mkt-texto-suave, #74788d); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mkt-conv__bot { color: var(--mkt-primario, #5156be); }
.mkt-conv__sin-leer {
  flex: 0 0 auto;
  min-width: 20px;
  padding: 0 6px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  line-height: 20px;
  text-align: center;
  color: #fff;
  background: var(--mkt-primario, #5156be);
}
</style>

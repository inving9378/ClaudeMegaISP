<template>
  <div class="mkt-burbuja-fila" :class="`mkt-burbuja-fila--${message.direction === 'outbound' ? 'sale' : 'entra'}`">
    <div class="mkt-burbuja" :class="{ 'mkt-burbuja--sale': message.direction === 'outbound', 'mkt-burbuja--bot': message.sender === 'ai_agent' }">
      <div class="mkt-burbuja__texto">{{ message.content }}</div>
      <div class="mkt-burbuja__meta">
        <q-icon v-if="message.sender === 'ai_agent'" name="smart_toy" size="12px" aria-label="Respuesta del bot" />
        <time :datetime="message.sent_at">{{ hora }}</time>
      </div>
    </div>
  </div>
</template>

<script>
/** Burbuja de mensaje (misma prop `message` que antes). Texto siempre interpolado con llaves (sin HTML crudo). */
export default {
  name: "MessageBubble",
  props: { message: { type: Object, required: true } },
  computed: {
    hora() {
      const d = this.message.sent_at;
      if (!d) return "";
      return new Intl.DateTimeFormat("es-MX", { hour: "2-digit", minute: "2-digit", timeZone: "America/Mexico_City" }).format(new Date(d));
    },
  },
};
</script>

<style scoped>
.mkt-burbuja-fila { display: flex; padding: 2px 12px; }
.mkt-burbuja-fila--entra { justify-content: flex-start; }
.mkt-burbuja-fila--sale { justify-content: flex-end; }
.mkt-burbuja {
  max-width: min(70%, 560px);
  padding: 8px 12px;
  border-radius: 12px;
  border-bottom-left-radius: 4px;
  font-size: 14px;
  line-height: 1.5;
  overflow-wrap: anywhere;
  white-space: pre-line;
  color: var(--mkt-texto, #343a40);
  background: var(--mkt-fondo-tarjeta, #fff);
  border: 1px solid var(--mkt-borde, #e9e9ef);
}
.mkt-burbuja--sale {
  border-radius: 12px;
  border-bottom-right-radius: 4px;
  background: color-mix(in srgb, var(--mkt-exito, #2ab57d) 16%, var(--mkt-fondo-tarjeta, #fff));
  border-color: color-mix(in srgb, var(--mkt-exito, #2ab57d) 30%, transparent);
}
.mkt-burbuja--sale.mkt-burbuja--bot {
  background: color-mix(in srgb, var(--mkt-primario, #5156be) 12%, var(--mkt-fondo-tarjeta, #fff));
  border-color: color-mix(in srgb, var(--mkt-primario, #5156be) 28%, transparent);
}
.mkt-burbuja__meta { display: flex; align-items: center; justify-content: flex-end; gap: 4px; margin-top: 2px; font-size: 11px; color: var(--mkt-texto-suave, #74788d); white-space: normal; }
@media (max-width: 599px) {
  .mkt-burbuja { max-width: 85%; }
}
</style>

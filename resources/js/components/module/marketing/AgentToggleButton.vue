<template>
  <div class="mkt-bot" :class="{ 'mkt-bot--activo': modelValue }">
    <q-icon :name="modelValue ? 'smart_toy' : 'person'" size="18px" aria-hidden="true" />
    <span class="mkt-bot__texto">{{ modelValue ? "Bot activo" : "Atiende una persona" }}</span>
    <q-toggle :model-value="modelValue" color="positive" dense :aria-label="modelValue ? 'Apagar el bot' : 'Encender el bot'" @update:model-value="pedirCambio" />
    <mkt-dialogo v-model="confirmar" titulo="¿Tomar el control de la conversación?" :ancho="440">
      <p class="mkt-bot__aviso">El bot dejará de responder automáticamente y tú atenderás esta conversación.</p>
      <template #acciones>
        <mkt-boton tipo="volver" etiqueta="Cancelar" @click="confirmar = false" />
        <mkt-boton tipo="principal" etiqueta="Sí, tomar el control" @click="confirmarApagado" />
      </template>
    </mkt-dialogo>
  </div>
</template>

<script>
import MktDialogo from "./kit/MktDialogo.vue";

/** Interruptor del bot (mismo v-model y evento `toggled` que antes; apagarlo pide confirmación). */
export default {
  name: "AgentToggleButton",
  components: { MktDialogo },
  emits: ["update:modelValue", "toggled"],
  props: { modelValue: { type: Boolean, default: true } },
  data() { return { confirmar: false }; },
  methods: {
    pedirCambio(val) {
      if (this.modelValue && !val) {
        this.confirmar = true;
        return;
      }
      this.$emit("update:modelValue", val);
      this.$emit("toggled", val);
    },
    confirmarApagado() {
      this.confirmar = false;
      this.$emit("update:modelValue", false);
      this.$emit("toggled", false);
    },
  },
};
</script>

<style scoped>
.mkt-bot {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 2px 4px 2px 10px;
  border: 1px solid var(--mkt-borde, #e9e9ef);
  border-radius: 999px;
  font-size: 13px;
  font-weight: 500;
  color: var(--mkt-texto-suave, #74788d);
}
.mkt-bot--activo { color: var(--mkt-primario, #5156be); border-color: color-mix(in srgb, var(--mkt-primario, #5156be) 35%, transparent); }
.mkt-bot__texto { white-space: nowrap; }
.mkt-bot__aviso { margin: 0; font-size: 14px; line-height: 1.5; color: var(--mkt-texto, #343a40); }
@media (max-width: 599px) {
  .mkt-bot__texto { display: none; }
}
</style>

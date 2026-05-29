<template>
  <div class="row items-center q-gutter-xs">
    <q-icon :name="modelValue ? 'smart_toy' : 'person'" :color="modelValue ? 'purple' : 'grey-6'" size="18px" />
    <span class="text-caption" :class="modelValue ? 'text-purple' : 'text-grey-6'">
      Bot {{ modelValue ? 'ON' : 'OFF' }}
    </span>
    <q-toggle :model-value="modelValue" color="purple" dense @update:model-value="requestToggle" />
    <q-dialog v-model="confirmDialog">
      <q-card>
        <q-card-section class="text-h6">¿Tomar control de la conversación?</q-card-section>
        <q-card-section>La IA dejará de responder automáticamente. Tú tomarás el control.</q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn unelevated color="primary" label="Sí, tomar control" @click="confirmToggle" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
export default {
  name: 'AgentToggleButton',
  emits: ['update:modelValue', 'toggled'],
  props: { modelValue: { type: Boolean, default: true } },
  data() { return { confirmDialog: false }; },
  methods: {
    requestToggle(val) {
      if (this.modelValue && !val) {
        // Turning off AI — confirm
        this.confirmDialog = true;
      } else {
        this.$emit('update:modelValue', val);
        this.$emit('toggled', val);
      }
    },
    confirmToggle() {
      this.confirmDialog = false;
      this.$emit('update:modelValue', false);
      this.$emit('toggled', false);
    }
  }
}
</script>

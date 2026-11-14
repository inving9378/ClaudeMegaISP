<template>
  <span v-if="source" class="mkt-fuente">
    <q-icon v-if="source.icon" :name="source.icon" size="14px" aria-hidden="true" />
    <span>{{ source.name }}</span>
  </span>
</template>

<script>
/**
 * Fuente del lead (de marketing_lead_sources). Mismas props que antes.
 * Diseño unificado con Clientes (§2.13): chip neutro con los tokens del kit; el color guardado de la fuente ya no
 * se pinta (quedaba fuera de la paleta del sistema).
 */
export default {
  name: "LeadSourceChip",
  props: { source: { type: Object, default: null } },
};
</script>

<style scoped>
.mkt-fuente {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  max-width: 100%;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 500;
  line-height: 20px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--mkt-texto, #343a40);
  background: var(--mkt-fondo-suave, #f8f8fb);
  border: 1px solid var(--mkt-borde, #e9e9ef);
}
</style>

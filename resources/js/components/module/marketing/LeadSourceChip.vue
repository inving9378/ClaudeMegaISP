<template>
  <q-chip v-if="source" dense :style="{ background: source.color + '22', color: source.color, border: '1px solid ' + source.color }">
    <q-icon v-if="source.icon" :name="source.icon" size="12px" class="q-mr-xs" />
    {{ source.name }}
  </q-chip>
</template>

<script>
export default {
  name: 'LeadSourceChip',
  props: { source: { type: Object, default: null } }
}
</script>

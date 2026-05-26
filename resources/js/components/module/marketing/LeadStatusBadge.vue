<template>
  <q-badge :color="color" :label="label" class="q-px-sm" />
</template>

<script>
const STATUS = {
  new:         { label: 'Nuevo',       color: 'grey-6' },
  contacted:   { label: 'Contactado',  color: 'info' },
  qualified:   { label: 'Calificado',  color: 'amber-7' },
  negotiating: { label: 'Negociando',  color: 'orange' },
  won:         { label: 'Ganado',      color: 'positive' },
  lost:        { label: 'Perdido',     color: 'negative' },
  archived:    { label: 'Archivado',   color: 'grey-4' },
};
export default {
  name: 'LeadStatusBadge',
  props: { status: { type: String, default: 'new' } },
  computed: {
    color() { return (STATUS[this.status] || STATUS.new).color; },
    label() { return (STATUS[this.status] || STATUS.new).label; }
  }
}
</script>

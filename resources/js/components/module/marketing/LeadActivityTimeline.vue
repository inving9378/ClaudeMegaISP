<template>
  <q-timeline color="primary" layout="dense">
    <q-timeline-entry
      v-for="act in activities"
      :key="act.id"
      :icon="iconForType(act.type)"
      :color="colorForType(act.type)"
    >
      <template #subtitle>
        {{ formatDate(act.happened_at) }}
        <span v-if="act.user" class="text-grey-6"> · {{ act.user.name }}</span>
      </template>
      <div>{{ act.description }}</div>
      <div v-if="act.metadata && act.metadata.reason" class="text-caption text-grey-7 q-mt-xs">
        {{ act.metadata.reason }}
      </div>
    </q-timeline-entry>
    <q-timeline-entry v-if="activities.length === 0" icon="info" color="grey">
      <template #subtitle>—</template>
      <span class="text-grey-6">Sin actividad registrada</span>
    </q-timeline-entry>
  </q-timeline>
</template>

<script>
const TYPE_ICONS = {
  created:       'person_add',
  message_sent:  'send',
  message_received: 'inbox',
  score_updated: 'auto_awesome',
  stage_changed: 'swap_horiz',
  assigned:      'assignment_ind',
  note:          'sticky_note_2',
  call:          'call',
  meeting:       'event',
  conversion:    'check_circle',
  lost:          'cancel',
  custom:        'label',
};
const TYPE_COLORS = {
  created: 'positive', conversion: 'positive', assigned: 'info',
  score_updated: 'purple', stage_changed: 'teal', lost: 'negative',
};
export default {
  name: 'LeadActivityTimeline',
  props: { activities: { type: Array, default: () => [] } },
  methods: {
    iconForType(t) { return TYPE_ICONS[t] || 'circle'; },
    colorForType(t) { return TYPE_COLORS[t] || 'grey'; },
    formatDate(d) {
      if (!d) return '';
      return new Date(d).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
    }
  }
}
</script>

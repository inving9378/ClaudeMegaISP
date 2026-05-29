<template>
  <q-item clickable :active="active" active-class="bg-blue-1" @click="$emit('select', conversation)">
    <q-item-section avatar>
      <q-avatar :color="statusColor" text-color="white" size="42px">
        {{ initial }}
      </q-avatar>
    </q-item-section>
    <q-item-section>
      <q-item-label class="text-weight-medium row items-center">
        <span class="col">{{ conversation.lead?.full_name || conversation.lead?.phone || '—' }}</span>
        <q-badge v-if="conversation.unread_count > 0" color="primary" :label="conversation.unread_count" rounded class="q-ml-xs" />
      </q-item-label>
      <q-item-label caption lines="1" class="row items-center q-gutter-xs">
        <q-icon v-if="conversation.ai_handled" name="smart_toy" size="12px" color="purple" />
        <q-icon v-else name="person" size="12px" color="grey-6" />
        <span>{{ lastMessagePreview }}</span>
        <span class="col"></span>
        <span class="text-grey-5" style="font-size:11px">{{ relativeTime }}</span>
      </q-item-label>
    </q-item-section>
  </q-item>
</template>

<script>
const STATUS_COLORS = { new: 'grey-6', contacted: 'blue', qualified: 'amber-7', negotiating: 'orange', won: 'positive', lost: 'negative' };

export default {
  name: 'ConversationListItem',
  emits: ['select'],
  props: {
    conversation: { type: Object, required: true },
    active: { type: Boolean, default: false },
  },
  computed: {
    initial() { return (this.conversation.lead?.full_name || this.conversation.lead?.phone || '?').charAt(0).toUpperCase(); },
    statusColor() { return STATUS_COLORS[this.conversation.lead?.status] || 'grey-6'; },
    lastMessagePreview() {
      const m = this.conversation.last_message;
      if (!m) return 'Sin mensajes';
      const preview = (m.content || '').substring(0, 50);
      return (m.direction === 'outbound' ? '↗ ' : '') + preview + (m.content?.length > 50 ? '...' : '');
    },
    relativeTime() {
      const d = this.conversation.last_message_at;
      if (!d) return '';
      const diff = Math.floor((Date.now() - new Date(d)) / 1000);
      if (diff < 60) return 'ahora';
      if (diff < 3600) return `${Math.floor(diff/60)}m`;
      if (diff < 86400) return `${Math.floor(diff/3600)}h`;
      return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
    },
  }
}
</script>

<template>
  <div :class="['message-bubble-wrap', message.direction]">
    <div :class="['bubble', message.direction, message.sender === 'ai_agent' ? 'ai' : '']">
      <div class="bubble-text">{{ message.content }}</div>
      <div class="bubble-meta">
        <q-icon v-if="message.sender === 'ai_agent'" name="smart_toy" size="10px" class="q-mr-xs" style="opacity:.6" />
        <span class="bubble-time">{{ formatTime(message.sent_at) }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MessageBubble',
  props: { message: { type: Object, required: true } },
  methods: {
    formatTime(d) {
      if (!d) return '';
      return new Date(d).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    }
  }
}
</script>

<style scoped>
.message-bubble-wrap { display: flex; padding: 2px 12px; }
.message-bubble-wrap.inbound  { justify-content: flex-start; }
.message-bubble-wrap.outbound { justify-content: flex-end; }
.bubble {
  max-width: 70%;
  padding: 8px 12px;
  border-radius: 12px;
  font-size: 14px;
  line-height: 1.5;
  word-break: break-word;
  position: relative;
}
.bubble.inbound  { background: #f0f0f0; color: #222; border-bottom-left-radius: 2px; }
.bubble.outbound { background: #dcf8c6; color: #222; border-bottom-right-radius: 2px; }
.bubble.outbound.ai { background: #e8d5f5; }
.bubble-meta { display: flex; align-items: center; justify-content: flex-end; margin-top: 2px; }
.bubble-time { font-size: 10px; color: #888; }
</style>

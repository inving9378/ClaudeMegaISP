<template>
  <div class="day-divider">
    <span class="day-label">{{ label }}</span>
  </div>
</template>

<script>
export default {
  name: 'MessageDayDivider',
  props: { date: { type: String, required: true } },
  computed: {
    label() {
      const d = new Date(this.date);
      const today = new Date();
      const yesterday = new Date(); yesterday.setDate(yesterday.getDate() - 1);
      if (d.toDateString() === today.toDateString()) return 'Hoy';
      if (d.toDateString() === yesterday.toDateString()) return 'Ayer';
      return d.toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' });
    }
  }
}
</script>

<style scoped>
.day-divider { text-align: center; padding: 8px 0; }
.day-label { background: #e0e0e0; border-radius: 8px; padding: 2px 12px; font-size: 12px; color: #555; }
</style>

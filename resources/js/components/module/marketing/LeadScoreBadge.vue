<template>
  <q-badge :color="color" :label="score" class="text-white q-px-sm" style="font-size:0.8rem;" />
</template>

<script>
export default {
  name: 'LeadScoreBadge',
  props: { score: { type: Number, default: 0 } },
  computed: {
    color() {
      if (this.score >= 61) return 'positive';
      if (this.score >= 31) return 'warning';
      return 'negative';
    }
  }
}
</script>

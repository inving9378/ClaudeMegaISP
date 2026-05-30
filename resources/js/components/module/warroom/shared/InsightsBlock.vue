<template>
    <div class="wr-insights-block">
        <div class="wr-insights-title">
            <i class="ti ti-bulb me-1"></i> Insights
            <span v-if="source === 'ai'" class="wr-insights-badge-ai">IA</span>
        </div>

        <!-- Skeleton -->
        <template v-if="loading">
            <q-skeleton v-for="i in 3" :key="i" type="text" class="mb-1" :width="`${70 + i * 8}%`" />
        </template>

        <!-- Sin insights -->
        <div v-else-if="!insights || insights.length === 0" class="wr-insights-empty">
            Sin insights generados para este período.
            <span v-if="canRegenerate">
                <a href="#" @click.prevent="$emit('regenerate')" class="wr-insights-regen">Generar ahora</a>
            </span>
        </div>

        <!-- Lista de insights -->
        <ul v-else class="wr-insights-list">
            <li
                v-for="(item, i) in insights"
                :key="i"
                :class="`wr-insight-${item.type ?? 'neutral'}`"
            >
                <i :class="typeIcon(item.type)"></i>
                {{ item.text }}
            </li>
        </ul>
    </div>
</template>

<script setup>
defineProps({
    insights:      { type: Array, default: () => [] },
    loading:       { type: Boolean, default: false },
    source:        { type: String, default: null },
    canRegenerate: { type: Boolean, default: false },
});
defineEmits(['regenerate']);

function typeIcon(type) {
    if (type === 'positivo')  return 'ti ti-circle-check';
    if (type === 'atencion')  return 'ti ti-alert-triangle';
    if (type === 'oportunidad') return 'ti ti-bolt';
    return 'ti ti-point';
}
</script>

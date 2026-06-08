<template>
    <div class="wr-insights-block">
        <div class="wr-insights-title">
            <i class="ti ti-bulb me-1"></i> Insights
            <span v-if="source === 'ai'" class="wr-insights-badge-ai">IA</span>
            <span v-if="status === 'generating'" class="wr-insights-badge-gen">
                <i class="ti ti-loader-2 wr-spin me-1"></i>generando…
            </span>
        </div>

        <!-- Skeleton -->
        <template v-if="loading">
            <q-skeleton v-for="i in 3" :key="i" type="text" class="mb-1" :width="`${70 + i * 8}%`" />
        </template>

        <!-- Sin insights y generando -->
        <div v-else-if="!insights || insights.length === 0" class="wr-insights-empty">
            <template v-if="status === 'generating'">
                <i class="ti ti-loader-2 wr-spin me-1"></i>
                Generando insights en background, listo en unos segundos…
            </template>
            <template v-else>
                Sin insights generados para este período.
                <span v-if="canRegenerate">
                    <a href="#" @click.prevent="$emit('regenerate')" class="wr-insights-regen">Generar ahora</a>
                </span>
            </template>
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

        <!-- Badge "actualizando" cuando hay insights viejos pero se está regenerando -->
        <div v-if="insights?.length && status === 'generating'" class="wr-insights-stale">
            <i class="ti ti-refresh me-1 wr-spin"></i>Actualizando…
        </div>
    </div>
</template>

<script setup>
defineProps({
    insights:      { type: Array, default: () => [] },
    loading:       { type: Boolean, default: false },
    source:        { type: String, default: null },
    status:        { type: String, default: 'ready' },
    canRegenerate: { type: Boolean, default: false },
});
defineEmits(['regenerate']);

function typeIcon(type) {
    if (type === 'positivo')    return 'ti ti-circle-check';
    if (type === 'atencion')    return 'ti ti-alert-triangle';
    if (type === 'oportunidad') return 'ti ti-bolt';
    return 'ti ti-point';
}
</script>

<style scoped>
.wr-insights-badge-gen {
    display: inline-flex;
    align-items: center;
    font-size: 10px;
    font-weight: 500;
    color: #6b6b85;
    margin-left: 6px;
    gap: 2px;
}
.wr-insights-stale {
    font-size: 10px;
    color: #6b6b85;
    margin-top: 4px;
}
.wr-spin {
    display: inline-block;
    animation: wr-spin 1s linear infinite;
}
@keyframes wr-spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
</style>

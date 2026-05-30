<template>
    <span class="wr-comparison-stat" :class="directionClass">
        <i :class="arrowIcon"></i>
        {{ formattedDelta }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    current:   { type: Number, default: 0 },
    previous:  { type: Number, default: 0 },
    suffix:    { type: String, default: '%' },
    invert:    { type: Boolean, default: false }, // para métricas donde bajar es bueno (tickets, etc.)
    decimals:  { type: Number, default: 1 },
});

const delta = computed(() => {
    if (!props.previous || props.previous === 0) return null;
    if (props.previous < 10) return null; // base demasiado pequeña para % significativo
    return ((props.current - props.previous) / Math.abs(props.previous)) * 100;
});

const direction = computed(() => {
    if (delta.value === null) return 'neutral';
    if (delta.value === 0) return 'neutral';
    const positive = delta.value > 0;
    return props.invert ? (positive ? 'down' : 'up') : (positive ? 'up' : 'down');
});

const directionClass = computed(() => `wr-delta-${direction.value}`);

const arrowIcon = computed(() => {
    if (direction.value === 'up')   return 'ti ti-trending-up';
    if (direction.value === 'down') return 'ti ti-trending-down';
    return 'ti ti-minus';
});

const formattedDelta = computed(() => {
    if (delta.value === null) {
        if (props.current > 0 && (!props.previous || props.previous < 10)) return 'Nuevo';
        return '—';
    }
    const sign = delta.value > 0 ? '+' : '';
    return `${sign}${delta.value.toFixed(props.decimals)}${props.suffix}`;
});
</script>

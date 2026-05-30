<template>
    <div class="wr-kpi-card" :class="[`accent-${accent}`, size === 'hero' ? 'wr-kpi-hero' : '']">
        <!-- Skeleton -->
        <template v-if="loading">
            <q-skeleton class="wr-skel-label mb-1" type="text" width="60%" />
            <q-skeleton class="wr-skel-value" type="text" width="75%" />
            <q-skeleton class="wr-skel-delta mt-1" type="text" width="40%" />
        </template>

        <!-- Content -->
        <template v-else>
            <div class="wr-kpi-header">
                <span v-if="icon" class="wr-kpi-icon">
                    <i :class="`ti ${icon}`"></i>
                </span>
                <span class="wr-kpi-label">{{ label }}</span>
            </div>
            <div class="wr-kpi-value">{{ value ?? '—' }}</div>
            <div v-if="delta !== null" class="wr-kpi-delta">
                <span :class="deltaClass">
                    <i :class="deltaIcon"></i>
                    {{ delta }}
                </span>
                <span class="wr-kpi-prev" v-if="previousValue">
                    vs {{ previousValue }}
                </span>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    label:         { type: String, required: true },
    value:         { type: [String, Number], default: null },
    previousValue: { type: [String, Number], default: null },
    delta:         { type: String, default: null },
    deltaDirection:{ type: String, default: 'neutral' }, // up | down | neutral
    accent:        { type: String, default: 'purple' },  // purple | green | orange | blue | pink
    icon:          { type: String, default: null },
    size:          { type: String, default: 'normal' },  // normal | hero
    loading:       { type: Boolean, default: false },
});

const deltaClass = computed(() => {
    if (props.deltaDirection === 'up')   return 'wr-delta-up';
    if (props.deltaDirection === 'down') return 'wr-delta-down';
    return 'wr-delta-neutral';
});

const deltaIcon = computed(() => {
    if (props.deltaDirection === 'up')   return 'ti ti-trending-up';
    if (props.deltaDirection === 'down') return 'ti ti-trending-down';
    return 'ti ti-minus';
});
</script>

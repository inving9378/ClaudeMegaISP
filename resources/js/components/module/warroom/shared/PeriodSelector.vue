<template>
    <div class="wr-period-selector d-flex align-items-center gap-2">
        <button class="wr-period-btn" @click="prev" :disabled="atMinPeriod" title="Mes anterior">
            <i class="ti ti-chevron-left"></i>
        </button>
        <span class="wr-period-label">{{ displayLabel }}</span>
        <button class="wr-period-btn" @click="next" :disabled="atCurrentMonth" title="Mes siguiente">
            <i class="ti ti-chevron-right"></i>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, required: true }, // YYYY-MM
});
const emit = defineEmits(['update:modelValue']);

const MONTHS_ES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const MIN_MONTHS_BACK = 12;

const parsed = computed(() => {
    const [y, m] = props.modelValue.split('-').map(Number);
    return { year: y, month: m };
});

const displayLabel = computed(() => {
    return `${MONTHS_ES[parsed.value.month - 1]} ${parsed.value.year}`;
});

const atCurrentMonth = computed(() => {
    const now = new Date();
    return parsed.value.year === now.getFullYear() && parsed.value.month === (now.getMonth() + 1);
});

const atMinPeriod = computed(() => {
    const now = new Date();
    const minDate = new Date(now.getFullYear(), now.getMonth() - MIN_MONTHS_BACK, 1);
    const current = new Date(parsed.value.year, parsed.value.month - 1, 1);
    return current <= minDate;
});

function next() {
    if (atCurrentMonth.value) return;
    let { year, month } = parsed.value;
    month++;
    if (month > 12) { month = 1; year++; }
    emit('update:modelValue', `${year}-${String(month).padStart(2, '0')}`);
}

function prev() {
    if (atMinPeriod.value) return;
    let { year, month } = parsed.value;
    month--;
    if (month < 1) { month = 12; year--; }
    emit('update:modelValue', `${year}-${String(month).padStart(2, '0')}`);
}
</script>

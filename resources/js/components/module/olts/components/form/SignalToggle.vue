<template>
    <div class="row">
        <div
            class="col col-auto text-right"
            style="padding: 8px 0px !important; min-width: 55px !important"
        >
            <label for="">Señal</label>
        </div>
        <div class="col q-ml-sm">
            <q-btn-toggle
                v-model="model"
                clearable
                toggle-color="grey-4"
                :options="[
                    {
                        value: 'Very good',
                        slot: 'good',
                    },
                    {
                        value: 'Warning',
                        slot: 'warning',
                    },
                    {
                        value: 'Critical',
                        slot: 'critical',
                    },
                ]"
                @update:model-value="(val) => emits('change', 'signal', val)"
            >
                <template v-slot:good>
                    <q-icon name="mdi-signal" color="positive" />
                    <q-tooltip> Buena </q-tooltip>
                </template>
                <template v-slot:warning>
                    <q-icon name="mdi-signal" color="warning" />
                    <q-tooltip> Regular </q-tooltip>
                </template>
                <template v-slot:critical>
                    <q-icon name="mdi-signal" color="negative" />
                    <q-tooltip> Crítica </q-tooltip>
                </template>
            </q-btn-toggle>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";

defineOptions({
    name: "StatusToggle",
});

const props = defineProps({
    modelValue: String,
});

const emits = defineEmits(["change"]);

const model = ref(null);

onMounted(() => {
    model.value = props.modelValue ?? null;
});

watch(
    () => props.modelValue,
    (n) => {
        model.value = n ?? null;
    }
);
</script>

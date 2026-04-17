<template>
    <div class="row">
        <div
            class="col col-auto text-right"
            style="padding: 8px 0px !important; min-width: 55px !important"
        >
            <label for=""> Modo </label>
        </div>
        <div class="col q-ml-sm">
            <q-btn-toggle
                v-model="model"
                clearable
                toggle-color="grey-4"
                toggle-text-color="black"
                :options="[
                    {
                        value: 'Bridging',
                        slot: 'bridging',
                    },
                    {
                        value: 'Routing',
                        slot: 'routing',
                    },
                ]"
                @update:model-value="(val) => emits('change', 'mode', val)"
            >
                <template v-slot:bridging>
                    <q-item-label>B</q-item-label>
                    <q-tooltip> Bridging </q-tooltip>
                </template>
                <template v-slot:routing>
                    <q-item-label>R</q-item-label>
                    <q-tooltip> Routing </q-tooltip>
                </template>
            </q-btn-toggle>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";

defineOptions({
    name: "ModeToggle",
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

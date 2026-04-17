<template>
    <div class="row">
        <div
            class="col col-auto tetx-right"
            style="padding: 8px 0px !important; min-width: 55px !important"
        >
            <label for=""> Estado </label>
        </div>
        <div class="col q-ml-sm">
            <q-btn-toggle
                v-model="model"
                clearable
                toggle-color="grey-4"
                :options="[
                    {
                        value: 'Online',
                        slot: 'online',
                    },
                    {
                        value: 'Power fail',
                        slot: 'fail',
                    },
                    {
                        value: 'LOS',
                        slot: 'los',
                    },
                    {
                        value: 'Offline',
                        slot: 'offline',
                    },
                    {
                        value: 'Disabled',
                        slot: 'disabled',
                    },
                ]"
                @update:model-value="(val) => emits('change', 'status', val)"
            >
                <template v-slot:online>
                    <q-icon name="mdi-earth" color="positive" />
                    <q-tooltip> En línea </q-tooltip>
                </template>
                <template v-slot:fail>
                    <q-icon name="fa fa-plug" color="grey" />
                    <q-tooltip> Power fail </q-tooltip>
                </template>
                <template v-slot:los>
                    <q-icon name="mdi-link-variant-off" color="negative" />
                    <q-tooltip> Pérdida de señal </q-tooltip>
                </template>
                <template v-slot:offline>
                    <q-icon name="mdi-earth" color="grey" />
                    <q-tooltip> Offline </q-tooltip>
                </template>
                <template v-slot:disabled>
                    <q-icon name="mdi-cancel" color="grey" />
                    <q-tooltip> Admin deshabilitado </q-tooltip>
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

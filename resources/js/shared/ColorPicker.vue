<template>
    <label for="object-name"
        >{{ label }}
        <q-icon
            name="mdi-square"
            :style="{
                color: model,
            }"
            v-if="model"
    /></label>
    <q-input
        dense
        outlined
        v-model="model"
        class="my-input"
        :rules="required ? [(val) => !!val || 'Requerido'] : null"
    >
        <template v-slot:append>
            <q-icon name="colorize" class="cursor-pointer">
                <q-popup-proxy
                    cover
                    transition-show="scale"
                    transition-hide="scale"
                >
                    <q-color
                        v-model="model"
                        no-header
                        no-footer
                        default-view="palette"
                        class="no-gutter-x"
                        @update:model-value="(val) => emits('change', val)"
                    />
                </q-popup-proxy>
            </q-icon>
        </template>
    </q-input>
</template>

<script setup>
import { ref, watch, onMounted } from "vue";
import { Vue3ColorPicker } from "@cyhnkckali/vue3-color-picker";
import "@cyhnkckali/vue3-color-picker/dist/style.css";
import { getAjaxDefaultValue } from "../helpers/Request";

defineOptions({
    name: "ColorPicker",
});

const props = defineProps({
    modelValue: String,
    label: {
        type: String,
        default: "Color",
    },
    required: {
        type: Boolean,
        default: true,
    },
});

const emits = defineEmits(["change"]);

const model = ref(null);

onMounted(() => {
    model.value = props.modelValue;
});

watch(
    () => props.modelValue,
    (n) => {
        model.value = n;
    }
);
</script>

<style scoped></style>

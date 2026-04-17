<template>
    <div class=" justify-content-between">
        <button
            v-if="!showColor"
            type="button"
            class="btn btn-outline-primary waves-effect waves-light me-auto"
            @click="toggleColor"
            :style="`background-color: ${color}`"
        >
            <span>Color</span>
        </button>
        <button
            v-if="showColor"
            type="button"
            class="btn btn-outline-primary waves-effect waves-light me-auto"
            @click="setColor"
            :style="`background-color: ${color}`"
        >
            <span>Aplicar</span>
        </button>
        <button
            v-if="showColor"
            @click="toggleColor"
            type="button"
            class="btn btn-outline-primary waves-effect waves-light me-auto"
        >
            Cancelar
        </button>
    </div>

    <div class="d-flex justify-content-center">
        <Vue3ColorPicker
            v-if="showColor"
            v-model="color"
            mode="solid"
            :showColorList="false"
            :showEyeDrop="false"
            type="RGBA"
        />
    </div>
</template>

<script>
import { ref, watch, onMounted } from "vue";
import { Vue3ColorPicker } from "@cyhnkckali/vue3-color-picker";
import "@cyhnkckali/vue3-color-picker/dist/style.css";
import { getAjaxDefaultValue } from "../helpers/Request";

export default {
    name: "QColorPicker",
    components: {
        Vue3ColorPicker,
    },
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String,
    },
    emits: ["update-field", "click"],
    setup(props, { emit }) {
        const color = ref("#38DE6B"); // valor inicial del color
        const showColor = ref(false);

        onMounted(async () => {
            if(props.modelValue && props.modelValue != ''){
                color.value = props.modelValue;
            }

            emit("update-field", {
                value: color,
                field: props.property.field,
            });
        });

        const toggleColor = () => {
            showColor.value = !showColor.value;
        };

        const closePicker = () => {
            showColor.value = false;
        };

        const setColor = () => {
            showColor.value = false;
            emit("update-field", {
                value: color,
                field: props.property.field,
            });
        };

        const getValByDefaultValue = async () => {
            return typeof props.property.default_value === "object" &&
                props.property.default_value &&
                props.property.default_value.request
                ? await getAjaxDefaultValue(
                      props.property.default_value.request
                  )
                : props.property.default_value ?? null;
        };

        return {
            color,
            toggleColor,
            showColor,
            closePicker,
            setColor,
        };
    },
};
</script>

<style scoped></style>

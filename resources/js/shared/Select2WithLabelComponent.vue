<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } form-group row`"
    >
        <label
            :for="field"
            class="col-sm-12 col-md-3 col-form-label text-sm-center text-md-end"
            >{{ label }}</label
        >
        <div class="col-sm-12 col-md-8">
            <div>
                <select
                    :class="{ 'form-control': true, 'parsley-error': hasError }"
                    :id="field"
                    :name="field"
                    :disabled="false"
                    v-model="val"
                >
                    <option value="null" :text="placeholder"></option>
                    <option
                        v-for="option in options.val"
                        :value="option.value"
                        :text="option.text"
                    ></option>
                </select>
            </div>

            <ul
                v-if="hasError"
                class="parsley-errors-list filled"
                aria-hidden="false"
            >
                <li class="parsley-required" v-text="error"></li>
            </ul>
        </div>
    </div>
</template>

<script>
import { reactive, ref, watch, onMounted, onBeforeUnmount } from "vue";
import {
    selectTransform,
    getOptions,
    convertToSelect2,
} from "../helpers/Transform";

export default {
    name: "Select2WithLabelComponent",
    props: {
        label: String,
        field: String,
        error: String,
        placeholder: {
            type: String,
            default: "",
        },
        hasError: {
            type: Boolean,
            default: false,
        },
        options: {
            type: Object,
            default: [],
        },
        modelValue: {
            type: String,
            default: null,
        },
    },
    setup(props, { emit }) {
        const val = ref(props.modelValue);
        const options = reactive({
            val: [],
        });

        let choiceInstance = null;

        watch(val, () => {
            emit("update-field", { value: val, field: props.field });
        });

        onMounted(async () => {
            options.val = props.options.options
                ? selectTransform(props.options.options)
                : await getOptions(props.options.search);

            $(document).ready(async () => {
                choiceInstance = await convertToSelect2(props.field, val, options.val);
            });
        });

        // Sin componente activo, este onBeforeUnmount es preventivo: evita que, si
        // alguna pantalla llega a reutilizar esta instancia (mismo patrón de
        // fieldsJson singleton que los demás select-component), la instancia de
        // Choices.js quede colgada con listeners globales de document sin liberar.
        onBeforeUnmount(() => {
            if (choiceInstance) {
                try {
                    choiceInstance.destroy();
                } catch (error) {
                    console.error(error);
                }
                choiceInstance = null;
            }
        });

        return {
            val,
            options,
        };
    },
};
</script>

<style scoped></style>

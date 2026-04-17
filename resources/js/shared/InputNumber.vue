<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
    >
        <label
            :for="property.field"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            {{ property.label }}
        </label>
        <div class="col-sm-12 col-md-9">
            <input
                type="text"
                :name="property.field"
                :placeholder="property.placeholder"
                :class="{ 'form-control': true }"
                v-model="inputValue"
                @input="validateNumber"
                :disabled="property.disabled"
            />
            <div class="col-sm-12 col-md-9 d-flex align-items-center">
                <span v-if="property.hint" class="small ps-2">{{
                    property.hint
                }}</span>
            </div>
            <div
                v-if="errors.has(property.field)"
                class="pristine-error text-help"
            >
                {{ errors.get(property.field) }}
            </div>
        </div>
    </div>
</template>
<script>
import { onMounted, ref, watch } from "vue";
import { getAjaxDefaultValue } from "../helpers/Request";
import _ from "lodash";

export default {
    name: "InputNumberAsString",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: [String, Number],
    },
    setup(props, { emit }) {
        const inputValue = ref("");
        const min = ref(null);
        const max = ref(null);

        const validateNumber = () => {
            // Si el valor está vacío, permitirlo y emitir
            if (
                inputValue.value === "" ||
                inputValue.value === "-" ||
                inputValue.value === "."
            ) {
                emit("update-field", {
                    value: inputValue.value,
                    field: props.property.field,
                });
                return;
            }

            // Expresión regular que permite números con decimales y negativos
            const numericRegex = /^-?\d*\.?\d*$/;

            if (!numericRegex.test(inputValue.value)) {
                // Buscamos el último valor válido
                const matches = inputValue.value.match(/^-?\d*\.?\d*/);
                inputValue.value = matches ? matches[0] : "";
                return;
            }

            // Validación de mínimo y máximo
            try {
                const numericValue = Number(inputValue.value);

                if (!isNaN(numericValue)) {
                    if (min.value !== null && numericValue < min.value) {
                        inputValue.value = min.value.toString();
                    } else if (max.value !== null && numericValue > max.value) {
                        inputValue.value = max.value.toString();
                    }
                }
            } catch (error) {
                console.warn("Error converting to number:", error);
                inputValue.value = "";
            }

            emit("update-field", {
                value: inputValue.value,
                field: props.property.field,
            });
        };

        onMounted(async () => {
            min.value = props.property?.options?.min ?? null;
            max.value = props.property?.options?.max ?? null;

            if (props.modelValue !== undefined && props.modelValue !== null) {
                inputValue.value = props.modelValue.toString();
            } else {
                inputValue.value = await getValByDefaultValue();
                emit("update-field", {
                    value: inputValue.value,
                    field: props.property.field,
                });
            }
        });

        const getValByDefaultValue = async () => {
            const defaultValue = props.property.default_value;
            if (typeof defaultValue === "object" && defaultValue?.request) {
                const value = await getAjaxDefaultValue(defaultValue.request);
                return value ? value.toString() : "";
            }

            // Agregar verificación para valores null/undefined
            if (defaultValue === null || defaultValue === undefined) {
                return "";
            }

            return defaultValue.toString();
        };
        watch(inputValue, (newVal) => {
            emit("update-field", {
                value: newVal,
                field: props.property.field,
            });
        });

        return { inputValue, min, max, validateNumber };
    },
};
</script>
<style scoped></style>

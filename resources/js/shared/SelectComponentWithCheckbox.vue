<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
    >
        <label :for="uniqueId" :class="`${property.class_label}`">
            {{ property.label }}
        </label>
        <div :class="`${property.class_field}`" style="padding-right: 0">
            <select
                :class="{ 'form-control': true }"
                :name="property.field"
                :id="uniqueId"
                :disabled="property.disabled"
                :v-model="val || []"
                multiple
            >
                <option
                    v-for="option in options.val"
                    :value="option.value"
                    :text="option.text"
                ></option>
            </select>
            <Input-Checkbox-Default-Val
                v-if="!isEdit"
                :data="`${property.field}, ${property.module_id}`"
                :val="val ?? null"
                :checked="property.checked"
            >
            </Input-Checkbox-Default-Val>
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
import { onMounted, reactive, ref, watch, nextTick } from "vue";
import {
    getOptions,
    getOptionsWithoutId,
    selectTransform,
    convertToBoostrapSelect,
} from "../helpers/Transform";
import InputCheckboxDefaultVal from "./InputCheckboxDefaultVal.vue";

import {
    setDefaultValue,
    uncheck,
    isEdit,
    setDefaultValueSelect,
} from "../hook/comunValues";
export default {
    name: "SelectComponentWithCheckbox",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: Array | String,
        id: {
            type: String,
            default: null,
        },
    },
    components: {
        InputCheckboxDefaultVal,
    },
    setup(props, { emit }) {
        const val = ref([]);
        const options = reactive({
            val: [],
        });
        const isInitialized = ref(false);

        const uniqueId = ref(
            `${props.property.field}_${props.property.module_id}_${
                props.property.position
            }_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
        );

        const getDefaultValueIfExist = async () => {
            let data = [];
            await axios["post"]("/get-default-fields-value", {
                module_id: props.property.module_id,
                field: props.property.field,
            }).then((response) => {
                data = response.data.value;
                if (data != undefined) {
                    data = convertToArray(data);
                } else {
                    data = [];
                }
            });
            return data;
        };

        const convertToArray = (valor) => {
            if (Array.isArray(valor)) {
                return valor; // Ya es un array, lo devolvemos tal cual
            } else if (typeof valor === "string") {
                return valor.split(","); // Es un string, lo dividimos
            }
            return []; // Retornamos un array vacío en caso de `undefined` o `null`
        };

        watch(val, () => {
            //TODO REVISAR YOSVANY
            if (val.value === "[]") {
                val.value = [];
            }
            emit("update-field", { value: val, field: props.property.field });
            uncheck(props.property.field, props.property.module_id);
            if (isInitialized.value) {
                setDefaultValueSelect(
                    val.value,
                    props.property.field,
                    props.property.module_id
                );
            }
        });

        onMounted(async () => {
            val.value = props.modelValue
                ? convertToArray(props.modelValue)
                : await getDefaultValueIfExist();
            options.val = props.property.options
                ? selectTransform(props.property.options)
                : props.id
                ? await getOptionsWithoutId(props.property.search, props.id)
                : await getOptions(props.property.search);

            if (Object.keys(options.val).length) {
                // Espera a que el DOM esté completamente actualizado
                await nextTick();

                $(document).ready(function () {
                    convertToBoostrapSelect(uniqueId.value, val, options.val);
                });
            }

            isInitialized.value = true;
        });

        return {
            val,
            options,
            setDefaultValue,
            isEdit,
            uniqueId,
        };
    },
};
</script>

<style scoped></style>

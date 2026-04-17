<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
        :key="opts"
    >
        <label :for="property.field" :class="`${property.class_label}`">
            {{ property.label }}
        </label>
        <div :class="`${property.class_field}`">
            <select
                :class="{ 'form-control': true }"
                :name="property.field"
                :id="property.field"
                :disabled="property.disabled"
                :data="`${property.field}, ${property.module_id}`"
                v-model="val"
                :val="val ?? null"
            ></select>
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
import { reactive, ref, watch, onMounted } from "vue";
import {
    selectTransform,
    getOptions,
    convertToSelect2,
} from "../helpers/Transform";
import {
    setDefaultValue,
    uncheck,
    isEdit,
    setDefaultValueSelect,
} from "../hook/comunValues";
import InputCheckboxDefaultVal from "./InputCheckboxDefaultVal.vue";

export default {
    name: "Select2Component",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        idModel: {
            type: String,
            default: null,
        },
        modelValue: String | Number,
    },
    components: {
        InputCheckboxDefaultVal,
    },
    setup(props, { emit }) {
        const val = ref(props.modelValue);
        const options = ref([]);
        const opts = reactive(options);
        const choice = ref();
        const idMod = ref(props.idModel);
        const isInitialized = ref(false);

        watch(val, (newValue, oldValue) => {
            if (newValue != oldValue) {
                emit("update-field", {
                    value: val,
                    field: props.property.field,
                });
                uncheck(props.property.field, props.property.module_id);
                if (isInitialized.value) {
                    setDefaultValueSelect(
                        val.value,
                        props.property.field,
                        props.property.module_id
                    );
                }
            }
        });

        watch(
            () => props.idModel,
            (actual, actionBefore) => {
                idMod.value = actual;
            }
        );

        watch(
            () => props.modelValue,
            (actual, actionBefore) => {
                if (choice.value) {
                    choice.value.setChoiceByValue(actual);
                }
            }
        );

        onMounted(async () => {
            options.value = props.property.options
                ? selectTransform(props.property.options)
                : await getOptions(props.property.search, idMod.value);

            $(document).ready(async () => {
                choice.value = await convertToSelect2(
                    props.property.field,
                    options,
                    props.modelValue,
                    props.property.placeholder
                );
            });

            isInitialized.value = true;
        });

        return {
            val,
            opts,
            setDefaultValue,
            isEdit,
        };
    },
};
</script>

<style scoped></style>

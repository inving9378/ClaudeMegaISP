<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
        :key="opts"
    >
        <label :for="uniqueId" :class="`${property.class_label}`">
            {{ property.label }}
        </label>
        <div :class="`${property.class_field}`">
            <select
                :class="{ 'form-control': true }"
                :id="uniqueId"
                :name="property.field"
                :disabled="property.disabled"
                v-model="val"
            >
                <option value="null" :text="property.placeholder"></option>
                <option
                    v-for="option in opts"
                    :key="option.value"
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
import { reactive, ref, watch, onMounted, nextTick } from "vue";
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
import { getUserAuthenticated } from "../helpers/Request";
import InputCheckboxDefaultVal from "./InputCheckboxDefaultVal.vue";

export default {
    name: "SelectComponent",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String | Number,
    },
    components: {
        InputCheckboxDefaultVal,
    },
    emits: ["update-field", "update-default-field"],
    setup(props, { emit }) {
        const val = ref(props.modelValue);
        const options = ref([]);
        const opts = reactive(options);
        const isInitialized = ref(false);

        const uniqueId = ref(
            `${props.property.field}_${props.property.module_id}_${props.property.position}_${Date.now()}`
        );


        watch(val, (newValue, oldValue) => {
            if (newValue != oldValue) {
                emit("update-field", {
                    value: val,
                    field: props.property.field,
                });
            }
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
            let defaulValue = props.property.default_value;
            if (defaulValue === "user_authenticated") {
                defaulValue = await getUserAuthenticated();
            } else {
                defaulValue = defaulValue === "on" ? null : defaulValue;
            }

            if (typeof props.modelValue === "number") {
                val.value = props.modelValue.toString() ?? defaulValue ?? null;
            } else {
                val.value = props.modelValue ?? defaulValue ?? null;
            }

            options.value = props.property.options
                ? selectTransform(props.property.options)
                : await getOptions(props.property.search);

            await nextTick(function () {
                convertToSelect2(
                    uniqueId.value,
                    options,
                    val
                );
            });

            isInitialized.value = true;
        });

        return {
            val,
            opts,
            setDefaultValue,
            isEdit,
            uniqueId
        };
    },
};
</script>

<style scoped></style>

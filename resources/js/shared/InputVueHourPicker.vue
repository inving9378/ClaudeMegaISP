<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'} `"
    >
        <label :for="property.field" :class="`${property.class_label}`">
            {{ property.label }}
        </label>
        <div :class="`${property.class_field}`">
            <VueDatePicker
                :id="property.field"
                v-model="val"
                position="right"
                locale="es"
                :teleport="true"
                time-picker
                :placeholder="property.placeholder"
                :disabled="property.disabled"
            >
            </VueDatePicker>
            <div
                v-if="errors.has(property.field)"
                class="pristine-error text-help"
                v-html="errors.get(property.field)"
            ></div>
        </div>
    </div>
</template>

<script>
import { onMounted, ref, watch } from "vue";
import { getAjaxDefaultValue } from "../helpers/Request";
import VueDatePicker from "@vuepic/vue-datepicker";

export default {
    name: "InputVueHourPicker",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: Date | String,
    },
    components: {
        VueDatePicker,
    },
    setup(props, { emit }) {
        const val = ref("");
        onMounted(async () => {
            val.value = props.modelValue ?? (await getValByDefaultValue());
            emit("update-field", {
                value: val,
                field: props.property.field,
            });
        });

        const getValByDefaultValue = async () => {
            return typeof props.property.default_value === "object" &&
                props.property.default_value &&
                props.property.default_value.request
                ? await getAjaxDefaultValue(
                      props.property.default_value.request
                  )
                : props.property.default_value ?? null;
        };

        watch(val, () => {
            emit("update-field", { value: val, field: props.property.field });
        });

        return {
            val,
        };
    },
};
</script>

<style scoped></style>

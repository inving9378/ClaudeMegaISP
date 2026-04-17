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
                :placeholder="property.placeholder"
                :disabled="property.disabled"
                :preview-format="format"
                :format="format"
            />
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
import VueDatePicker from "@vuepic/vue-datepicker";

export default {
    name: "InputVuePickerSingle",
    props: {
        errors: {
            type: Object,
            default: () => ({}),
        },
        property: Object,
        modelValue: [Date, String],
    },
    components: {
        VueDatePicker,
    },
    setup(props, { emit }) {
        const val = ref(
            props.modelValue ? new Date(props.modelValue) : new Date()
        );

        const format = (date) => {
            const day = String(date.getDate()).padStart(2, "0");
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        };

        const toMySQLDateTime = (date) => {
            if (!(date instanceof Date)) return null;
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const day = String(date.getDate()).padStart(2, "0");
            const hours = String(date.getHours()).padStart(2, "0");
            const minutes = String(date.getMinutes()).padStart(2, "0");
            const seconds = String(date.getSeconds()).padStart(2, "0");
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        };

        const emitValue = () => {
            emit("update-field", {
                value: toMySQLDateTime(val.value),
                field: props.property.field,
            });
        };

        onMounted(emitValue);
        watch(val, emitValue);

        return {
            val,
            format,
        };
    },
};
</script>

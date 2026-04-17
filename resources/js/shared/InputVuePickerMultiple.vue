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
                range
                multi-calendars
                style="width: 350px"
                :format="format"
                :dark="darkMode"
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

<script setup>
import { onMounted, ref, watch } from "vue";
import { getAjaxDefaultValue } from "../helpers/Request";
import { darkMode } from "../hook/appConfig";
import VueDatePicker from "@vuepic/vue-datepicker";

defineOptions({
    name: "InputVuePickerMultiple",
});

const props = defineProps({
    errors: {
        type: Object,
        default: {},
    },
    property: Object,
    modelValue: Date | String,
});

const emits = defineEmits(["update-field"]);

const val = ref(props.modelValue ?? new Date());

onMounted(async () => {
    emits("update-field", {
        value: val,
        field: props.property.field,
    });
});

const format = (dates) => {
    if (!Array.isArray(dates)) {
        dates = [dates];
    }

    return dates
        .map((date) => {
            const day = String(date.getDate()).padStart(2, "0");
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const year = date.getFullYear();
            return `${day}-${month}-${year}`;
        })
        .join(",");
};

const getValByDefaultValue = async () => {
    return typeof props.property.default_value === "object" &&
        props.property.default_value &&
        props.property.default_value.request
        ? await getAjaxDefaultValue(props.property.default_value.request)
        : props.property.default_value ?? null;
};

watch(val, () => {
    emits("update-field", { value: val, field: props.property.field });
});
</script>

<style scoped></style>

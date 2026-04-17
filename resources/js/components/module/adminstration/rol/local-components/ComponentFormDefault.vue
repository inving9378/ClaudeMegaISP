<template>
    <InputCheckbox
        v-if="json.type == 'input-checkbox'"
        :property="json"
        :errors="errors"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputCheckboxWithInputs
        v-if="json.type == 'input-checkbox-with-inputs'"
        :property="json"
        :errors="errors"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />
</template>

<script type="application/javascript">

import InputCheckboxWithInputs from "./InputCheckboxWithInputs";
import InputCheckbox from "./InputCheckbox";
export default {
    name: "ComponentFormDefault",
    props: {
        id: {
            type: String,
            default: null,
        },
        json: Object,
        errors: {
            type: Object,
            default: {},
        },
        modelValue: String | Array,
    },
    emits: ["update-field", "clear-error", "clear-depend-error"],
    components: {
        InputCheckboxWithInputs,
        InputCheckbox
    },
    setup(props, { emit }) {
        const updateThisField = (value) => {
            emit("update-field", value);
        };

        const clearError = (field) => {
            emit("clear-error", { field });
        };

        const clearDependErrors = ({ field }) => {
            emit("clear-error", { field });
        };

        return {
            updateThisField,
            clearError,
            clearDependErrors,
        };
    },
};
</script>

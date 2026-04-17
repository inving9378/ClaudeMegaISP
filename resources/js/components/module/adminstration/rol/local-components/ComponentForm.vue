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
</template>

<script type="application/javascript">

import InputCheckbox from "./InputCheckbox";

export default {
    name: "ComponentForm",
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
    emits: ["update-field", "clear-error"],
    components: {
        InputCheckbox
    },
    setup(props, { emit }) {
        const updateThisField = (value) => {
            emit("update-field", value);
        };

        const clearError = (field) => {
            emit("clear-error", { field });
        };

        return {
            updateThisField,
            clearError,
        };
    },
};
</script>

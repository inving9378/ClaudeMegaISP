<template>

    <InputDateRangePicker
        v-if="json.type == 'data-range-picker'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />
    <InputText
        v-if="json.type == 'input-string'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputScanner
        v-if="json.type == 'input-scanner'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputFile
        v-if="json.type == 'input-file'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputPassword
        v-if="json.type == 'input-password'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
        @click="clearError(json.field)"
    />

    <InputPasswordInModal
        v-if="json.type == 'input-password-in-modal'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
        @click="clearError(json.field)"
    />

    <InputGroupGenerateUser
        v-if="json.type == 'input-group-generate-user'"
        :id="idModel"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
        @click="clearError(json.field)"
    />

    <InputGroupText
        v-if="json.type == 'input-group-text'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputGroupNumber
        v-if="json.type == 'input-group-number'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputGroupTextInit
        v-if="json.type == 'input-group-text-init'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputGroupMultiple
        v-if="json.type == 'input-group-multiple'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputNumber
        v-if="json.type == 'input-number'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputCheckbox
        v-if="json.type == 'input-checkbox'"
        :property="json"
        :errors="errors"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <DateTimeLocal
        v-if="json.type == 'date-time-local'"
        :property="json"
        :errors="errors"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <SelectComponent
        v-if="json.type == 'select-component'"
        :property="json"
        :errors="errors"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <Select2Component
        v-if="json.type == 'select-2-component'"
        :property="json"
        :errors="errors"
        :idModel="idModel"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <Select2EstadoMunicipioColoniaComponent
        v-if="json.type == 'select-2-estado-municipio-colonia-component'"
        :property="json"
        :errors="errors"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <SelectComponentWithCheckbox
        v-if="
            json.type == 'select-component-with-checkbox' ||
            json.type == 'select-component-with-checkbox-without-id'
        "
        :id="
            json.type == 'select-component-with-checkbox-without-id' ? id : null
        "
        :property="json"
        :errors="errors"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputCheckboxAfterWithouValidationError
        v-if="json.type == 'input-checkbox-after-withou-validation-error' && id"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @change="clearError(json.field)"
        @update-field="updateThisField"
    />

    <SelectSingleAddItems
        v-if="json.type == 'select-single-add-items'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />
</template>

<script type="application/javascript">

import InputText from "../shared/InputText";
import InputNumber from "../shared/InputNumber";
import InputGroupText from "../shared/InputGroupText";
import InputGroupMultiple from "../shared/InputGroupMultiple";
import InputGroupTextInit from "../shared/InputGroupTextInit";
import InputGroupNumber from "../shared/InputGroupNumber";
import InputCheckbox from "../shared/InputCheckbox";
import InputCheckboxAfterWithouValidationError from "../shared/InputCheckboxAfterWithouValidationError";
import SelectComponent from "../shared/SelectComponent";
import SelectComponentWithCheckbox from "../shared/SelectComponentWithCheckbox";
import InputPasswordInModal from "../shared/InputPasswordInModal";
import InputGroupGenerateUser from "../shared/InputGroupGenerateUser";
import SelectSingleAddItems from "../components/funcionales/SelectSingleAddItems";
import DateTimeLocal from "../shared/DateTimeLocal";
import InputFile from "../shared/InputFile";
import InputPassword from "../shared/InputPassword";
import Select2Component from "../shared/Select2Component";
import Select2EstadoMunicipioColoniaComponent from "../shared/Select2EstadoMunicipioColoniaComponent";
import InputDateRangePicker from "../shared/InputDateRangePicker";
import InputScanner from "../shared/InputScanner.vue";

export default {
    name: "ComponentForm",
    props: {
        id: {
            type: String | Number,
            default: null,
        },
        json: Object,
        errors: {
            type: Object,
            default: {},
        },
        idModel: {
            type: String,
            default: null,
        },
        modelValue: String | Array,
    },
    emits: ["update-field", "clear-error"],
    components: {
        Select2EstadoMunicipioColoniaComponent,
        Select2Component,
        InputPassword,
        InputFile,
        InputText,
        InputScanner,
        InputNumber,
        InputGroupText,
        InputGroupMultiple,
        InputGroupTextInit,
        InputGroupNumber,
        InputCheckbox,
        InputCheckboxAfterWithouValidationError,
        SelectComponent,
        SelectComponentWithCheckbox,
        InputPasswordInModal,
        InputGroupGenerateUser,
        SelectSingleAddItems,
        DateTimeLocal,
        InputDateRangePicker
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

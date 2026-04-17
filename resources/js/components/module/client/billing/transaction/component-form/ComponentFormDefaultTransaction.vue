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
        v-if="json.type == 'input-string' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />

    <InputPriceTransaction
        v-if="json.type == 'input-price-transaction' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />

    <Text
        v-if="json.type == 'text' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />

    <InputTextArea
        v-if="json.type == 'input-text-area' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputFile
        v-if="json.type == 'input-file' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputPassword
        v-if="json.type == 'input-password' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
        @click="clearError(json.field)"
    />

    <InputPasswordInModal
        v-if="json.type == 'input-password-in-modal' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputGroupGenerateUser
        v-if="json.type == 'input-group-generate-user' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
        @click="clearError(json.field)"
    />

    <InputGroupText
        v-if="json.type == 'input-group-text' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputGroupNumber
        v-if="json.type == 'input-group-number' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputGroupTextInit
        v-if="json.type == 'input-group-text-init' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputGroupMultiple
        v-if="json.type == 'input-group-multiple' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputNumber
        v-if="json.type == 'input-number' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputCheckbox
        v-if="json.type == 'input-checkbox' && json.include"
        :property="json"
        :errors="errors"
        @change="clearError(json.field)"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputCheckboxWithInputs
        v-if="json.type == 'input-checkbox-with-inputs' && json.include"
        :property="json"
        :errors="errors"
        @change="clearAllError(modelValue)"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <SelectComponetWithGroupInputs
        v-if="json.type == 'select-component-with-group-inputs' && json.include"
        :property="json"
        :errors="errors"
        @change="clearError(json.field)"
        @clear-depend-errors="clearDependErrors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <DateTimeLocal
        v-if="json.type == 'date-time-local' && json.include"
        :property="json"
        :errors="errors"
        @change="clearError(json.field)"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <SelectComponent
        v-if="json.type == 'select-component' && json.include"
        :property="json"
        :errors="errors"
        @change="clearError(json.field)"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <SelectComponentWithCheckbox
        v-if="
            (json.type == 'select-component-with-checkbox' ||
            json.type == 'select-component-with-checkbox-without-id') &&
            json.include
        "
        :id="
            json.type == 'select-component-with-checkbox-without-id' ? id : null
        "
        :property="json"
        :errors="errors"
        @click="clearError(json.field)"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputCheckboxAfterWithouValidationError
        v-if="json.type == 'input-checkbox-after-withou-validation-error' && json.include"
        :property="json"
        :errors="errors"
       :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @change="clearError(json.field)"
        @update-field="updateThisField"
    />

    <SelectSingleAddItems
        v-if="json.type == 'select-single-add-items' && json.include"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />
</template>

<script type="application/javascript">
import InputText from "../../../../../../shared/InputText";
import Text from "../../../../../../shared/Text";
import InputTextArea from "../../../../../../shared/InputTextArea";
import InputPassword from "../../../../../../shared/InputPassword";
import InputNumber from "../../../../../../shared/InputNumber";
import InputGroupText from "../../../../../../shared/InputGroupText";
import InputGroupMultiple from "../../../../../../shared/InputGroupMultiple";
import InputGroupTextInit from "../../../../../../shared/InputGroupTextInit";
import InputGroupNumber from "../../../../../../shared/InputGroupNumber";
import InputCheckbox from "../../../../../../shared/InputCheckbox";
import InputCheckboxWithInputs from "../../../../../../shared/InputCheckboxWithInputs";
import SelectComponetWithGroupInputs from "../../../../../../shared/SelectComponetWithGroupInputs";

import InputCheckboxAfterWithouValidationError from "../../../../../../shared/InputCheckboxAfterWithouValidationError";
import SelectComponent from "../../../../../../shared/SelectComponent";
import SelectComponentWithCheckbox from "../../../../../../shared/SelectComponentWithCheckbox";
import InputPasswordInModal from "../../../../../../shared/InputPasswordInModal";
import InputGroupGenerateUser from "../../../../../../shared/InputGroupGenerateUser";
import SelectSingleAddItems from "../../../../../../components/funcionales/SelectSingleAddItems";
import DateTimeLocal from "../../../../../../shared/DateTimeLocal";
import InputFile from "../../../../../../shared/InputFile";
import InputPriceTransaction from "./components/InputPriceTransaction";
import InputDateRangePicker from "../../../../../../shared/InputDateRangePicker";


export default {
    name: "ComponentFormDefaultTransaction",
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
        InputFile,
        InputText,
        Text,
        InputTextArea,
        InputPassword,
        InputNumber,
        InputGroupText,
        InputGroupMultiple,
        InputGroupTextInit,
        InputGroupNumber,
        InputCheckbox,
        InputCheckboxWithInputs,
        InputCheckboxAfterWithouValidationError,
        SelectComponent,
        SelectComponentWithCheckbox,
        InputPasswordInModal,
        InputGroupGenerateUser,
        SelectSingleAddItems,
        DateTimeLocal,
        SelectComponetWithGroupInputs,
        InputPriceTransaction,
        InputDateRangePicker
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

        const clearAllError = (object) => {
            _.forEach(object, (v,k) => {
                emit("clear-error", { field: k});
            })
        }

        return {
            updateThisField,
            clearError,
            clearAllError,
            clearDependErrors,
        };
    },
};
</script>

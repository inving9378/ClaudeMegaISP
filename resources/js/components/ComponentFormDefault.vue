<template>
    <InputText
        v-if="json.type == 'input-string' && json.include"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />
    <InputScanner
        v-if="json.type == 'input-scanner' && json.include"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />

    <InputInformation
        v-if="json.type == 'input-string-information' && json.include"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />

    <InputHidden
        v-if="json.type == 'input-hidden' && json.include"
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
    <SpanInput
        v-if="json.type == 'span-input' && json.include"
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

    <InputFileImagen
        v-if="json.type == 'input-file-imagen' && json.include"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputMultipleFile
        v-if="json.type == 'input-multiple-file' && json.include"
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
        :id="id"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
        @clear-error="clearError"
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

    <InputCheckboxLeftOrder
        v-if="json.type == 'input-checkbox-left-order' && json.include"
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
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <SelectComponetWithGroupInputs
        v-if="json.type == 'select-component-with-group-inputs' && json.include"
        :property="json"
        :errors="errors"
        :idModel="idModel"
        @change="clearError(json.field)"
        @clear-depend-errors="clearDependErrors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />
    <SelectComponentWithSearch
        v-if="json.type == 'select_component_with_search' && json.include"
        :property="json"
        :errors="errors"
        :idModel="idModel"
        @change="clearError(json.field)"
        @clear-depend-errors="clearDependErrors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />
    <SelectComponentWithSearchClient
        v-if="
            json.type == 'select_component_with_search_client' && json.include
        "
        :property="json"
        :errors="errors"
        :idModel="idModel"
        :id="id"
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

    <InputTime
        v-if="json.type == 'input-time' && json.include"
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

    <Select2Component
        v-if="json.type == 'select-2-component' && json.include"
        :property="json"
        :errors="errors"
        :idModel="idModel"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <SelectLongOptions
        v-if="json.type == 'select-long-component' && json.include"
        :property="json"
        :errors="errors"
        :idModel="idModel"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <SelectComponentClient
        v-if="json.type == 'select-component-client' && json.include"
        :property="json"
        :errors="errors"
        :idModel="idModel"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <SelectTemplateListVerificationComponent
        v-if="json.type == 'select_template_list_verification' && json.include"
        :property="json"
        :errors="errors"
        :idModel="idModel"
        @change="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
        :id="id"
    />

    <Select2EstadoMunicipioColoniaComponent
        v-if="json.type == 'select-2-estado-municipio-colonia-component'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @clear-error="clearError"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <Select2TypeTemplateSelectComponent
        v-if="json.type == 'select-2-type-template'"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @clear-error="clearError"
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

    <SelectComponentTeam
        v-if="json.type == 'select-group-team' && json.include"
        :id="id"
        :property="json"
        :errors="errors"
        @click="clearError(json.field)"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @update-field="updateThisField"
    />

    <InputCheckboxAfterWithouValidationError
        v-if="
            json.type == 'input-checkbox-after-withou-validation-error' &&
            json.include
        "
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

    <RouterNetwork
        v-if="json.type == 'router-network' && json.include"
        :idModel="idModel"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @change="clearError(json.field)"
        @update-field="updateThisField"
    />

    <InputVuePickerSingle
        v-if="json.type == 'input_vue_datepicker_single' && json.include"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />

    <InputVuePickerMultiple
        v-if="json.type == 'input_vue_datepicker_multiple' && json.include"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />

    <InputVueHourPicker
        v-if="json.type == 'input_vue_hour_picker' && json.include"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />

    <InputModalWithGoogleMapForm
        v-if="json.type == 'input_modal_google_map' && json.include"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />

    <QColorPicker
        v-if="json.type == 'color-picker' && json.include"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />

    <InputEditor
        v-if="json.type == 'input-editor' && json.include"
        :property="json"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @click="clearError(json.field)"
        @update-field="updateThisField"
    />
</template>

<script type="application/javascript">
import InputText from "../shared/InputText";
import InputHidden from "../shared/InputHidden";
import Text from "../shared/Text";
import InputTextArea from "../shared/InputTextArea";
import InputPassword from "../shared/InputPassword";
import InputNumber from "../shared/InputNumber.vue";
import InputGroupText from "../shared/InputGroupText";
import InputGroupMultiple from "../shared/InputGroupMultiple";
import InputGroupTextInit from "../shared/InputGroupTextInit";
import InputGroupNumber from "../shared/InputGroupNumber";
import InputCheckbox from "../shared/InputCheckbox";
import InputCheckboxLeftOrder from "../shared/InputCheckboxLeftOrder";
import InputCheckboxWithInputs from "../shared/InputCheckboxWithInputs";
import SelectComponetWithGroupInputs from "../shared/SelectComponetWithGroupInputs";
import InputCheckboxAfterWithouValidationError from "../shared/InputCheckboxAfterWithouValidationError";
import SelectComponent from "../shared/SelectComponent";
import Select2Component from "../shared/Select2Component";
import SelectLongOptions from "../shared/SelectLongOptions";
import SelectComponentClient from "../shared/SelectComponentClient.vue";
import SelectComponentWithCheckbox from "../shared/SelectComponentWithCheckbox";
import InputPasswordInModal from "../shared/InputPasswordInModal";
import InputGroupGenerateUser from "../shared/InputGroupGenerateUser";
import SelectSingleAddItems from "../components/funcionales/SelectSingleAddItems";
import DateTimeLocal from "../shared/DateTimeLocal";
import InputTime from "../shared/InputTime";
import InputFile from "../shared/InputFile";
import InputFileImagen from "../shared/InputFileImagen";
import InputMultipleFile from "../shared/InputMultipleFile";
import InputInformation from "../shared/InputInformation";
import Select2EstadoMunicipioColoniaComponent from "../shared/Select2EstadoMunicipioColoniaComponent";
import Select2TypeTemplateSelectComponent from "../shared/Select2TypeTemplateSelectComponent.vue";
import RouterNetwork from "../shared/RouterNetwork";
import SelectComponentWithSearch from "../shared/SelectComponentWithSearch.vue";
import SelectComponentWithSearchClient from "../shared/SelectComponentWithSearchClient.vue";
import InputVuePickerSingle from "../shared/InputVuePickerSingle.vue";
import InputVueHourPicker from "../shared/InputVueHourPicker.vue";
import InputModalWithGoogleMapForm from "../shared/InputModalWithGoogleMapForm.vue";
import InputVuePickerMultiple from "../shared/InputVuePickerMultiple.vue";

import SelectTemplateListVerificationComponent from "../shared/SelectTemplateListVerificationComponent.vue";
import QColorPicker from "../shared/QColorPicker.vue";
import SelectComponentTeam from "../shared/SelectComponentTeam.vue";
import InputEditor from "../shared/InputEditor.vue";
import SpanInput from "../shared/SpanInput.vue";
import InputScanner from "../shared/InputScanner.vue";

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
        idModel: {
            type: String,
            default: null,
        },
        modelValue: String | Array | Number,
    },
    emits: ["update-field", "clear-error", "clear-depend-error"],
    components: {
        SelectComponentTeam,
        Select2TypeTemplateSelectComponent,
        Select2EstadoMunicipioColoniaComponent,
        InputInformation,
        InputFile,
        InputMultipleFile,
        InputText,        
        InputHidden,
        Text,
        InputTextArea,
        InputPassword,
        InputNumber,
        InputGroupText,
        InputGroupMultiple,
        InputGroupTextInit,
        InputGroupNumber,
        InputCheckbox,
        InputCheckboxLeftOrder,
        InputCheckboxWithInputs,
        InputCheckboxAfterWithouValidationError,
        SelectComponent,
        Select2Component,
        SelectLongOptions,
        SelectComponentWithCheckbox,
        InputPasswordInModal,
        InputGroupGenerateUser,
        SelectSingleAddItems,
        DateTimeLocal,
        SelectComponetWithGroupInputs,
        RouterNetwork,
        InputTime,
        SelectComponentWithSearch,
        SelectComponentWithSearchClient,
        InputVuePickerSingle,
        InputVueHourPicker,
        InputModalWithGoogleMapForm,
        SelectTemplateListVerificationComponent,
        InputVuePickerMultiple,
        QColorPicker,
        InputEditor,
        InputFileImagen,
        SpanInput,
        SelectComponentClient,
        InputScanner,
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

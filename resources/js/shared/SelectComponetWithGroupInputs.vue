<template>
    <div
        :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
    >
        <label
            :for="property.field"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            {{ property.label }}
        </label>
        <div class="col-sm-12 col-md-9">
            <select
                :class="{ 'form-control': true }"
                :id="property.field"
                :name="property.field"
                :disabled="property.disabled"
                v-model="val"
                @change="clearAllErrors"
            >
                <option value="null" :text="property.placeholder"></option>
                <option
                    v-for="option in options.val"
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

    <div v-for="val in fieldsJson">
        <ComponentForm
            v-if="isNotDependField(val)"
            :json="val"
            :errors="errors"
            :key="val"
            :idModel="idModel"
            v-model="dataForm.data[val.field]"
            @update-field="updateThisField"
            @clear-error="clearError"
        />
    </div>
</template>

<script>
import { watch, ref, reactive, onMounted } from "vue";
import Form from "../helpers/Form";
import ComponentForm from "../components/ComponentForm.vue";
import { getOptions, selectTransform } from "../helpers/Transform";

import { setDefaultValue, uncheck, isEdit } from "../hook/comunValues";
import InputCheckboxDefaultVal from "./InputCheckboxDefaultVal.vue";

export default {
    name: "SelectComponetWithGroupInputs",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        idModel: {
            type: String,
            default: null,
        },
        property: Object,
        modelValue: Boolean | Number,
    },
    emits: ["update-field", "change", "clear-depend-errors"],
    components: {
        ComponentForm,
        InputCheckboxDefaultVal,
    },
    setup(props, { emit }) {
        const val = ref(props.modelValue);
        const fieldsJson = ref({});
        const dependSelected = ref(val.value);
        const dataForm = reactive({
            data: new Form({}),
        });
        const options = reactive({
            val: [],
        });

        onMounted(async () => {
            options.val = props.property.options
                ? selectTransform(props.property.options)
                : await getOptions(props.property.search);

            fieldsJson.value = props.property.inputs_depend;
            dataForm.data = new Form(fieldsJson.value);
        });
        watch(val, () => {
            dependSelected.value = val.value;
            emit("update-field", { value: val, field: props.property.field });
            uncheck(props.property.field, props.property.module_id);
        });

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
            emit("clear-depend-errors", { field });
        };

        const clearAllErrors = () => {
            _.forEach(fieldsJson.value, (val) => {
                let field = val.field;
                dataForm.data.errors.clear(field);
                emit("clear-depend-errors", { field });
            });
        };

        const updateThisField = ({ field, value }) => {
            emit("update-field", { field, value });
        };

        const isNotDependField = (objectfieldsJsonInput) => {
            return (
                objectfieldsJsonInput.type != "depend-field" &&
                objectfieldsJsonInput.depend == dependSelected.value
            );
        };

        return {
            val,
            fieldsJson,
            dataForm,
            clearError,
            clearAllErrors,
            updateThisField,
            isNotDependField,
            options,

            setDefaultValue,
            isEdit,
        };
    },
};
</script>

<style scoped></style>

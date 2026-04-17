<template>
    <div class="form-group row item align-items-center">
        <label
            class="
                col-sm-12 col-md-3 col-form-label
                text-sm-center text-md-end
            "
            >{{ property.label }}</label
        >
        <div class="col-sm-12 col-md-8 d-flex flex-column" dir="ltr">
            <input type="checkbox" :id="property.field" switch="none" v-model="val" />
            <label class="m-0" :for="property.field"></label>
            <ul
                v-if="errors.has(property.field)"
                class="parsley-errors-list filled"
                aria-hidden="false"
            >
                <li class="parsley-required" v-text="errors.get(property.field)"></li>
            </ul>
        </div>
    </div>
    <hr>
    <div v-if="show" v-for="val in fieldsJson">
        <ComponentForm
            v-if="val.type != 'depend-field'"
            :json="val"
            :errors="errors"
            :key="val"
            v-model="dataForm.data[val.field]"
            @update-field="updateThisField"
            @clear-error="clearError"
        />
    </div>
</template>

<script>
import { watch, ref, reactive, onMounted } from "vue";
import Form from "../../../../../helpers/Form";
import ComponentForm from "./ComponentForm";

export default {
    name: "InputCheckboxWithInputs",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: Boolean | Number,
    },
    emits: ["update-field", "change"],
    components: {
        ComponentForm,
    },
    setup(props, { emit }) {
        const val = ref(!!_.toInteger(props.modelValue));
        const show = ref(props.property.depend == props.modelValue);

        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });

        onMounted(() => {
            show.value = val.value == props.property.depend;
            fieldsJson.value = props.property.inputs_depend;
            dataForm.data = new Form(fieldsJson.value);
        });

        watch(val, () => {
            show.value = val.value == props.property.depend;
            if (val.value == false){
                _.forEach(fieldsJson.value, v => {
                    updateThisField({field: v.field, value: false});
                    dataForm.data[v.field] = false;
                })
            }
            emit("update-field", { value: val, field: props.property.field });
        });

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const updateThisField = ({ field, value }) => {
            emit("update-field", { field, value });
        };

        return {
            val,
            show,
            fieldsJson,
            dataForm,
            clearError,
            updateThisField,
        };
    },
};
</script>

<style scoped></style>

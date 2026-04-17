<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } form-group row align-items-center`"
    >
        <label
            class="col-sm-12 col-md-3 col-form-label text-sm-center text-md-end"
            >{{ property.label }}</label
        >
        <div class="col-sm-12 col-md-8 d-flex flex-column" dir="ltr">
            <input
                type="checkbox"
                :id="property.field"
                switch="none"
                v-model="val"
            />
            <label class="m-0" :for="property.field"></label>
            <ul
                v-if="errors.has(property.field)"
                class="parsley-errors-list filled"
                aria-hidden="false"
            >
                <li
                    class="parsley-required pristine-error text-help"
                    v-text="errors.get(property.field)"
                ></li>
            </ul>
        </div>
    </div>

    <div v-if="show" v-for="val in fieldsJsonCheck">
        <ComponentForm
            v-if="val.type != 'depend-field'"
            :json="val"
            :errors="errors"
            :key="val"
            v-model="dataFormCheck.data[val.field]"
            @update-field="updateThisField"
            @clear-error="clearError"
        />
    </div>
</template>

<script>
import { watch, ref, reactive, onMounted } from "vue";
import Form from "../helpers/Form";
import ComponentForm from "../components/ComponentForm.vue";

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
        const val = ref(false);
        const show = ref(props.property.depend == props.modelValue);

        const fieldsJsonCheck = ref({});
        const dataFormCheck = reactive({
            data: new Form({}),
        });

        onMounted(async () => {
            val.value = props.modelValue
                ? !!_.toInteger(props.modelValue)
                : await getValByDefaultValue();
            show.value = val.value == props.property.depend;
            fieldsJsonCheck.value = props.property.inputs_depend;
            dataFormCheck.data = new Form(fieldsJsonCheck.value);
        });

        watch(val, () => {
            show.value = val.value == props.property.depend;
            emit("update-field", { value: val, field: props.property.field });
        });

        const getValByDefaultValue = async () => {
            return props.property.default_value
                ? !!_.toInteger(props.property.default_value)
                : false;
        };
        const clearError = ({ field }) => {
            dataFormCheck.data.errors.clear(field);
        };

        const updateThisField = ({ field, value }) => {
            emit("update-field", { field, value });
        };

        return {
            val,
            show,
            fieldsJsonCheck,
            dataFormCheck,
            clearError,
            updateThisField,
        };
    },
};
</script>

<style scoped></style>

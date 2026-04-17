<template>
    <div :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2 item  ${errors.has(property.field) && 'has-danger'}`">
        <div class="col-sm-12 col-md-3 align-self-center">
            <input
                type="checkbox"
                :id="property.field"
                switch="none"
                v-model="val"
            />
            <label class="m-0" :for="property.field"></label>
            <div v-if="errors.has(property.field)" class="pristine-error text-help">
                {{ errors.get(property.field) }}
            </div>
        </div>
        <label
            :for="property.field"
            :class="`col-sm-12 col-md-9 col-form-label pr-2`"
        >
            {{ property.label }}
        </label>
    </div>
</template>

<script>
import {watch, ref, onMounted} from "vue";

export default {
    name: "InputCheckboxLeftOrder",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: Boolean | Number,
    },
    setup(props, { emit }) {
        const val = ref(false);

        onMounted(async () => {
            val.value  = props.modelValue  ? !!_.toInteger(props.modelValue) : await getValByDefaultValue();
            emit("update-field", {
                value: val,
                field: props.property.field,
            });
        });

        watch(val, () => {
            emit("update-field", { value: val, field: props.property.field });
        });

        const getValByDefaultValue = () => {
            return props.property.default_value ? !!_.toInteger(props.property.default_value) : false;
        }

        return {
            val,
        };
    },
};
</script>

<style scoped></style>

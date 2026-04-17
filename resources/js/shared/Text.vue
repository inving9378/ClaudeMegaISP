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
            <input
                type="text"
                :name="property.field"
                :placeholder="property.placeholder"
                class="form-control no-border"
                v-model="val"
                :disabled="true"
            />
            <div v-if="errors.has(property.field)" class="pristine-error text-help">
                {{ errors.get(property.field) }}
            </div>
        </div>
    </div>
</template>

<script>
import {ref, watch} from "vue";

export default {
    name: "InputText",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String,
    },
    setup(props, {emit}) {
        const val = ref(props.modelValue);

        watch(val, () => {
            emit("update-field", {value: val, field: props.property.field});
        });

        return {
            val
        };
    },
};
</script>

<style scoped></style>

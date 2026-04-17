<template>
    <div :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2 item align-items-center`">
        <label
            :for="property.field"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
        </label>
        <div class="col-sm-12 col-md-9 d-flex align-items-center">
            <input
                type="checkbox"
                :id="property.field"
                switch="none"
                v-model="val"
            />
            <label class="m-0" :for="property.field"></label>
            <span v-if="property.hint" class="small ps-2">{{ property.hint }}</span>
        </div>
    </div>
</template>

<script>
import {watch, ref} from "vue";

export default {
    name: "InputCheckboxAfterWithouValidationError",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: Boolean | Number,
    },
    setup(props, {emit}) {
        const val = ref(!!_.toInteger(props.modelValue));

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

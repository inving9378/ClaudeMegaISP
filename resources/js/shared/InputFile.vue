<template>
    <div
        :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row m-auto mb-2 ${errors.has(property.field) && 'has-danger'}`"
    >
        <label
            :for="`custom_${property.field}`"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            {{ property.label }}
        </label>

        <div class="col-sm-12 col-lg-8">
            <input
                type="file"
                class="custom-file-input"
                :id="`custom_${property.field}`"
                :name="property.field"
                @change="uploadFile"
            />
            <label
                class="custom-file-label"
                :for="`custom_${property.field}`"
                >{{ fileName }}</label
            >
        </div>
        <div v-if="errors.has(property.field)" class="pristine-error text-help">
            {{ errors.get(property.field) }}
        </div>
    </div>
</template>

<script>
import { ref, watch } from "vue";

export default {
    name: "InputFile",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String | File,
    },
    setup(props, { emit }) {
        const val = ref(props.modelValue);
        const fileName = ref("");
        try {
            let file = JSON.parse(props.modelValue);
            fileName.value = file.name;
        } catch (e) {
            if (typeof props.modelValue == "object") {
                let file = props.modelValue;
                if (file) fileName.value = file.name;
            } else {
                fileName.value = props.modelValue;
            }
        }

        const uploadFile = (e) => {
            if (e.target.files.length) {
                fileName.value = e.target.files[0].name;
                val.value = e.target.files[0];
            }
        };

        watch(val, () => {
            if (!val.value) fileName.value = "";
            emit("update-field", { value: val, field: props.property.field });
        });

        return {
            val,
            fileName,
            uploadFile,
        };
    },
};
</script>

<style scoped></style>

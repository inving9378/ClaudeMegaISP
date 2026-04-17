<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
    >
        <label :for="property.field" :class="`${property.class_label}`">
            {{ titleText }}
        </label>
        <div :class="`${property.class_field}`">
            <div :id="property.field"></div>
            <textarea
                :id="`html-${property.field}`"
                class="form-control hidden"
                v-model="editor"
            ></textarea>
            <div
                v-if="errors.has(property.field)"
                class="pristine-error text-help"
            >
                {{ errors.get(property.field) }}
            </div>
        </div>
    </div>
</template>

<script>
import { convertToCkeditor } from "../helpers/Transform";
import { ref, watch, onMounted } from "vue";

export default {
    name: "InputEditor",
    props: {
        title: String,
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String,
    },
    setup(props, { emit }) {
        const editor = ref();

        const titleText = ref(
            props.property.label ? props.property.label : props.title
        );

        watch(editor, () => {
            emit("update-field", {
                value: editor,
                field: props.property.field,
            });
        });

        onMounted(async () => {
            $(document).ready(function () {
                convertToCkeditor(props.property.field, editor);
                let content = document.querySelector(".ck-editor__main");
                let code = document.getElementById(
                    `html-${props.property.field}`
                );
            });
        });

        return { titleText, editor };
    },
};
</script>

<style scoped></style>

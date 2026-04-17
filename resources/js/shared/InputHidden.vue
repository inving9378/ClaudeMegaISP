<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2`"
    >
        <div class="col-sm-12 col-md-9">
            <input
                type="hidden"
                :name="property.field"
                v-model="val"
                :disabled="true"
            />
        </div>
    </div>
</template>

<script>
import { onMounted, ref, watch } from "vue";

export default {
    name: "InputHidden",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String | Array,
    },
    setup(props, { emit }) {
        const val = ref(null);
        onMounted(async () => {
            val.value = props.modelValue ?? null;
            emit("update-field", {
                value: val,
                field: props.property.field,
            });
        });

        watch(val, () => {
            emit("update-field", { value: val, field: props.property.field });
        });

        return {
            val,
        };
    },
};
</script>

<style scoped></style>

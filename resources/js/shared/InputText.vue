<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'} `"
    >
        <label :for="property.field" :class="`${property.class_label}`">
            {{ property.label }}
        </label>
        <div :class="`${property.class_field}`">
            <input
                type="text"
                :name="property.field"
                :placeholder="property.placeholder"
                :class="{
                    'form-control': true,
                }"
                v-model="val"
                :disabled="property.disabled"
            />
            <div
                v-if="errors.has(property.field)"
                class="pristine-error text-help"
                v-html="errors.get(property.field)"
            ></div>
        </div>
    </div>
</template>

<script>
import { onMounted, ref, watch } from "vue";
import { getAjaxDefaultValue } from "../helpers/Request";

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
    setup(props, { emit }) {
        const val = ref(null);
        onMounted(async () => {
            val.value = props.modelValue ?? (await getValByDefaultValue());
            emit("update-field", {
                value: val,
                field: props.property.field,
            });
        });

        const getValByDefaultValue = async () => {
            return typeof props.property.default_value === "object" &&
                props.property.default_value &&
                props.property.default_value.request
                ? await getAjaxDefaultValue(
                      props.property.default_value.request
                  )
                : props.property.default_value ?? null;
        };

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

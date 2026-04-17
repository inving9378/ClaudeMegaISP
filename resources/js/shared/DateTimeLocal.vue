<template>
    <div
        :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
        :key="val"
    >
        <label
            :for="property.field"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            {{ property.label }}
        </label>
        <div class="col-sm-12 col-md-9">
            <input
                type="datetime-local"
                class="form-control"
                :name="property.field"
                :disabled="property.disabled"
                :value="val"
                v-on:change="changeVal"
            />
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
import {onMounted, ref, watch} from "vue";
import {getDefaultValue} from "../helpers/Request";

export default {
    name: "DateTimeLocal",
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

        const default_values = {
            'now': 'ajax'
        };

        onMounted(async () => {
            if (props.modelValue) {
                val.value = props.modelValue;
            } else {
                if (default_values[props.property.default_value] && default_values[props.property.default_value] == 'ajax'){
                    props.property.default_value = await getDefaultValue(props.property.default_value);
                }
                val.value = props.property.default_value ??  null;
            }
            emit("update-field", {
                value: val,
                field: props.property.field,
            });
        });

        watch(val, () => {
            emit("update-field", { value: val, field: props.property.field });
        });

        const changeVal = (e) => {
            val.value = $(e.target).val();
        }

        return {
            val,changeVal
        };
    },
};
</script>

<style scoped></style>

<template>
    <div :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`" :key="val">
        <label
            :for="property.field"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            {{ property.label }}
        </label>
        <div class="col-sm-12 col-md-9">
            <input
                class="form-control"
                :id="`${property.field}-range`"
                :name="property.field"
                :disabled="property.disabled"
                :value="val"
            />
            <div v-if="errors.has(property.field)" class="pristine-error text-help">
                {{ errors.get(property.field) }}
            </div>
        </div>
    </div>
</template>

<script>
import {onMounted, ref, watch} from "vue";
import {convertToDateRangePicker} from "../helpers/Transform";

export default {
    name: "InputDateRangePicker",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String,
    },
        setup(props, { emit }) {
            const val = ref(props.modelValue ?? '');

            watch(val, () => {
                emit("update-field", { value: val, field: props.property.field });
            });

            onMounted(async () => {
                $(document).ready(function () {
                    convertToDateRangePicker(props.property.field);

                    $(`#${props.property.field}-range`).on('apply.daterangepicker', function(ev, picker) {
                        let valRange = picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY');
                        $(this).val(valRange);
                        val.value = valRange;
                    });

                    $(`#${props.property.field}-range`).on('cancel.daterangepicker', function(ev, picker) {
                        $(this).val('');
                        val.value = '';
                    });
                });
            });

            return {val};
    },
};
</script>

<style scoped>

</style>

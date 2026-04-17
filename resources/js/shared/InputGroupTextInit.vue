<template>
    <div
        :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
    >
        <label
            :for="property.field"
            :class="`d-sm-block d-md-none col-sm-12 col-form-label pe-2`"
        >
            {{ property.label }}
        </label>

        <div class="input-group">
            <label
                :for="property.field"
                :class="`d-md-block d-none col-md-3 col-form-label text-md-end pe-2 `"
            >
                {{ property.label }}
            </label>
            <div class="ms-1 input-group-text">
                {{ property.inputGroup }}
            </div>
            <input
                type="text"
                :id="property.field"
                :name="property.field"
                :placeholder="property.placeholder"
                :aria-label="property.placeholder"
                :aria-describedby="`${property.field}1`"
                :class="'form-control col-sm-12 col-md-9'"
                v-model="val"
                :disabled="property.disabled"
            />
        </div>
        <span class="col-sm-12 col-md-3"></span>
        <div v-if="errors.has(property.field)" class="col-sm-9 pristine-error text-help">
            {{ errors.get(property.field) }}
        </div>
    </div>
</template>

<script>
import {ref, watch} from "vue";

export default {
    name: "InputGroupText",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String,
    },
    setup(props, {emit}){
        const val = ref(props.modelValue);

        watch(val,()=>{
            emit('update-field',{'value': val,'field': props.property.field})
        })

        return {
            val
        }
    }
}
</script>

<style scoped>

</style>

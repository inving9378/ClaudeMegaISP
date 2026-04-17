
<template>
    <div>
    <div class="form-group row">
        <label :for="field" class="col-sm-12 col-md-3 col-form-label text-sm-center text-md-end">{{  label }}</label>
        <div class="col-sm-12 col-md-8">
            <div>
                <select
                    :class="{'form-control': true, 'parsley-error': hasError }"
                    :id="field"
                    :name="field"
                    :disabled="false"
                    v-model="val"
                >
                    <option value=null :text="placeholder"></option>
                    <option v-for="option in options.val" :value="option.value" :text="option.text"></option>
                </select>
            </div>

            <ul v-if="hasError" class="parsley-errors-list filled" aria-hidden="false">
                <li class="parsley-required" v-text="error"></li>
            </ul>
        </div>
    </div>
    <div class="form-group row" v-if="show">
        <label class="col-sm-12 col-md-3 col-form-label text-sm-center text-md-end">{{  labelInput }}</label>
        <div class="col-sm-12 col-md-6 d-flex flex-column align-content-center" dir="ltr">
            <input
                type="checkbox"
                :id="fieldInput"
                switch="none"
                v-model="valInput"
            />
            <label class="m-0" :for="fieldInput" ></label>
            <ul v-if="hasErrorInput" class="parsley-errors-list filled" aria-hidden="false">
                <li class="parsley-required" v-text="errorInput"></li>
            </ul>
        </div>
    </div>
    </div>

</template>

<script>

import {reactive, ref, watch, onMounted} from "vue";
import {selectTransform, getOptions} from "../helpers/Transform"

export default {
    name: "SelectComponentWithInputCheckbox",
    props: {
        label: String,
        field: String,
        error: String,
        placeholder: String,
        hasError: {
            type: Boolean,
            default: false
        },
        disabled:{
            type: Boolean,
            default: false
        },
        options: {
            type: Object,
            default: []
        },
        modelValue: {
            type: String,
            default: ''
        },
        depend: String,

        labelInput: String,
        errorInput: String,
        hasErrorInput: {
            type: Boolean,
            default: false
        },
        valueInput: String|Number,
        fieldInput: String
    },
    setup(props,{emit}){

        const val = ref(props.modelValue);
        const valInput = ref(props.valueInput);
        const show = ref(props.depend == props.modelValue);

        const options = reactive({
            val: [],
        });

        watch(val,()=>{
            show.value = (val.value == props.depend)

            emit('update-field',{'value': val,'field': props.field})
        })

        watch(valInput,()=>{
            emit('update-field',{'value': valInput,'field': props.fieldInput})
        })

        onMounted(async () => {
            options.val = props.options.options ? selectTransform(props.options.options)
                : await getOptions(props.options.search)
        })

        return {
            val,
            valInput,
            show,
            options
        }
    }
}
</script>

<style scoped>

</style>

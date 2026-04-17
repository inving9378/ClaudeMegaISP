<template>
    <div class="hello">
        <select id="new" multiple="multiple">
            <option value="1">January</option>
            <option value="12">December</option>
        </select>
    </div>
</template>

<script>
import {onMounted, reactive, ref, watch} from "vue";
import {getOptions, selectTransform} from "../helpers/Transform";

// require ('multiple-select/src/multiple-select')

export default {
    name: "Multiple",
    props: {
        label: String,
        field: String,
        error: String,
        placeholder: String,
        hasError: {
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
    },
    setup(props,{emit}){

        const val = ref(props.modelValue);
        const options = reactive({
            val: [],
        });

        watch(val,()=>{
            emit('update-field',{'value': val,'field': props.field})
        })

        onMounted(async () => {
            $('#new').multipleSelect();
        })

        return {
            val,
            options
        }
    }
}
</script>

<style scoped>

</style>

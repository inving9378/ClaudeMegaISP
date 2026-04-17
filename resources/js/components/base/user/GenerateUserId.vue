<template>
    <div class="form-group row">
        <label :for="field" class="col-sm-12 col-md-3 col-form-label text-sm-center text-md-end">Usuario</label>
        <div class="input-group col-sm-12 col-md-6">
            <input
                type="text"
                :name="field"
                :class="{'form-control': true, 'parsley-error': hasError, 'h-100': !hasError }"
                v-model="val"
            >
            <div class="input-group-append cursor-pointer" @click="getUserId">
                <span class="input-group-text bg-primary text-white" :id="`${field}1`"><i class="fa fa-key"></i></span>
            </div>
            <ul v-if="hasError" class="col-sm-12 parsley-errors-list filled" aria-hidden="false">
                <li class="parsley-required" v-text="error"></li>
            </ul>
        </div>
    </div>
</template>

<script>
import {reactive, ref, watch} from "vue";
import Form from "../../../helpers/Form";

export default {
    name: "GenerateUserId",
    props: {
        label: String,
        field: String,
        error: String,
        hasError: {
            type: Boolean,
            default: false
        },
        modelValue: String
    },
    setup(props, {emit}){
        const val = ref(props.modelValue);
        const dataForm = reactive(new Form({
            id: val
        }));

        const getUserId = () => {
            dataForm.post(`/user/get-next-user`)
                .then(result => val.value = result+1);
        }

        watch(val,()=>{
            emit('update-field',{'value': val,'field': props.field})
        })

        return {
            val,
            getUserId
        }
    }
}
</script>

<style scoped>

</style>

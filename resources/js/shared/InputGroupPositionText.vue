<template>
    <div
        :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } form-group row`"
    >
        <label
            :for="field"
            class="col-sm-12 col-md-3 col-form-label text-sm-center text-md-end"
            >{{ label }}</label
        >
        <div class="input-group col-sm-12 col-md-8">
            <input
                type="text"
                :name="field"
                :placeholder="placeholder"
                :aria-label="placeholder"
                :aria-describedby="`${field}1`"
                :class="{
                    'form-control': true,
                    'parsley-error': hasError,
                    'h-100': !hasError,
                }"
                v-model="val"
            >

            <div class="input-group-prepend">
            <a href="#" class="uil-map-marker btn btn-primary pull-right" data-toggle="modal" data-target="#geodataposition">
            </a>
            </div>
            <ul v-if="hasError" class="col-sm-12 parsley-errors-list filled" aria-hidden="false">
                <li class="parsley-required" v-text="error"></li>
            </ul>
        </div>
    </div>


    <div class="modal fade" id="geodataposition">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span>X</span>
                    </button>
                </div>
                <div class="modal-body">
                    <GoogleMapModalGeoPosition
                    ></GoogleMapModalGeoPosition>
                </div>

            </div>
        </div>
    </div>
</template>

<script>
import {ref, watch} from "vue";
import GoogleMapModalGeoPosition from '../components/base/googlemap/GoogleMapModalGeoPosition';

export default {
    name: "InputGroupPositionText",
    components:{
        GoogleMapModalGeoPosition,
    },
    props: {
        label: String,
        field: String,
        error: String,
        placeholder: {
            type: String,
            default: ''
        },
        hasError: {
            type: Boolean,
            default: false
        },
        modelValue: String,
        inputGroupEnd:{
            type: String,
            default: ''
        }
    },
    setup(props, {emit}){
        const val = ref(props.modelValue);

        watch(val,()=>{
            emit('update-field',{'value': val,'field': props.field})
        })

        return {
            val
        }
    }
}
</script>

<style scoped>

</style>

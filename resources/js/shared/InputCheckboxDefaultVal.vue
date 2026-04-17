<template>
    <input
        type="checkbox"
        class="form-check-input"
        :data="data"
        :value="val"
        :checked="checked"
        @change="setDefaultValue"
        style="position: relative; top: -30px; float: right; left: -25px"
    />
</template>

<script>
import { watch, ref, onMounted } from "vue";
import { setDefaultValue } from "../hook/comunValues";

export default {
    name: "InputCheckboxDefaultVal",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        data: String,
        checked: Boolean,
        val: String | Number,
    },
    setup(props, { emit }) {
        return {
            setDefaultValue,
        };
    },
};
</script>

<style scoped></style>

<template></template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { message } from "../../../helpers/toastMsg";

defineOptions({
    name: "MessageResponse",
});

const props = defineProps({
    type: {
        type: String,
        default: "success",
    },
    message: String,
});

const text = ref(props.message);
onMounted(() => {
    if (text.value) {
        message(text.value, props.type);
    }
});

watch(
    () => props.message,
    (n) => {
        text.value = n;
    }
);

watch(text, (n) => {
    if (!_.isEmpty(n)) {
        message(n, props.type);
    }
});
</script>

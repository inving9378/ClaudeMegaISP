<template></template>

<script>
import { onMounted } from "vue";

export default {
    name: "Message",
    props: {
        message: String,
        module: String,
    },
    setup(props) {
        onMounted(() => {
            toastr.success(`${props.message}`, module);
        });

        return {};
    },
};
</script>

<style scoped></style>

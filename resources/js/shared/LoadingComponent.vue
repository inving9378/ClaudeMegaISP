<template>
    <div :class="`loading loading-${showWithColor} text-center m-auto`" v-if="loading">
        <div class="lds-roller">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>
</template>

<script>
export default {
    name: "LoadingComponent",
    props: {
        loading: Boolean,
        showWithColor: {
            type: String,
            default: ''
        }
    }
}
</script>

<style scoped>

</style>

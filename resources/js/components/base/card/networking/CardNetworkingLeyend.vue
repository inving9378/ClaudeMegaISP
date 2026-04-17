<template>
    <div :class="`col-lg-4 alert  ${cardClass} spl-legend-block-item-container d-flex`">
        <div :class="`spl-legend-block-item-icon ${subCardClass}`">
            <i :class="`${iclass} fa-2x spl-legend-item-icon`" :title="title"></i>
        </div>
        <div class="ms-1 align-self-center"> - {{ title }} </div>
    </div>
</template>

<script>
export default {
    name: "CardNetworkingLeyend",
    props: {
        iclass: String,
        title: String,
        cardClass: {
            type: String,
            default: ''
        },
        subCardClass: String
    }
}
</script>

<style scoped>

</style>

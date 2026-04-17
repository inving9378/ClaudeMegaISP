<template>
    <div class="col-md-3 col-xl-3 cursor-pointer" @click="redirect(link)">
        <div class="card text-dark bg-light mb-3" style="min-height: 200px;">
            <div class="card-body">
                <h5 class="card-title d-flex justify-content-between">
                    <div>
                        <i :class="`display-4 ${icon}`"></i>
                    </div>
                    <div>
                        <h4 class="mb-1 mt-1">
                            <span data-plugin="counterup">{{ value }}</span>
                        </h4>
                        <p class="text-muted mb-0">{{ label }}</p>
                    </div>
                </h5>
            </div>
            <div class="card-header d-flex justify-content-between">
                <span>Ver</span>
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "TarjetTicket",
    props: {
        icon: String,
        label: String,
        value: String | Number,
        link: String,
        porcent: String | Number,
        labelPorcent: String,
    },
    setup() {
        const redirect = (url) => {
            location.href = url
        }

        return {
            redirect
        };
    }
}
</script>

<style scoped>

</style>

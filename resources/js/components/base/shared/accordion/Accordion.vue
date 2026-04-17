<template>
    <div class="accordion" id="accordionExample">
        <div class="accordion-item" v-for="item in items">
            <h2 class="accordion-header" :id="`heading${item.name}`" v-if="item.name">
                <button class="accordion-button fw-medium collapsed text-capitalize"
                        type="button"
                        data-bs-toggle="collapse" :data-bs-target="`#collapse${item.name}`"
                        aria-expanded="false" :aria-controls="`#collapse${item.name}`">
                    {{ item.name }}
                </button>
            </h2>
            <div :id="`collapse${item.name}`"
                 class="accordion-collapse collapse"
                 :aria-labelledby="`heading${item.name}`"
                 data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    <div class="text-muted">
                        {{ item.value }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "Accordion",
    props: {
        items: Object|Array
    }
}
</script>

<style scoped>

</style>

<template>
    <div class="card h-full">
        <div
            class="card-header d-flex justify-content-between align-items-center"
            :style="padding ? `padding: ${padding}` : ''"
        >
            <div class="mb-0 mr-4">
                <h4 class="mb-0">{{ title }}</h4>
            </div>
        </div>
        <div class="card-body">
            <slot name="chart"></slot>
        </div>
    </div>
</template>

<script setup>
import { defineProps } from "vue";

const props = defineProps({
    title: {
        type: String,
        default: "",
    },
    padding: String,
});
</script>
